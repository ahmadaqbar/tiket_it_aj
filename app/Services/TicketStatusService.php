<?php

namespace App\Services;

use App\Enums\ReplyVisibility;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan status tiket (DATABASE_DESIGN.md §5).
 *
 *   NEW ─► IN_PROGRESS ◄─► PENDING
 *    └────────┴──► RESOLVED ◄─┘
 *                   │   ▲ reopen (pelapor / IT)
 *          konfirmasi│   │
 *                   ▼   │
 *                 CLOSED (final)
 */
class TicketStatusService
{
    /** Transisi yang boleh dilakukan IT / IT Admin. */
    public const IT_TRANSITIONS = [
        'NEW' => ['IN_PROGRESS', 'PENDING', 'RESOLVED', 'CLOSED'],
        'IN_PROGRESS' => ['PENDING', 'RESOLVED', 'CLOSED'],
        'PENDING' => ['IN_PROGRESS', 'RESOLVED', 'CLOSED'],
        'RESOLVED' => ['IN_PROGRESS', 'CLOSED'],
        'CLOSED' => [],
    ];

    public function __construct(
        private readonly TicketService $tickets,
        private readonly TicketReplyService $replies,
        private readonly AuditService $audit,
        private readonly SettingService $settings,
    ) {}

    /** @return array<TicketStatus> */
    public function allowedFor(Ticket $ticket): array
    {
        return array_map(fn ($v) => TicketStatus::from($v), self::IT_TRANSITIONS[$ticket->status->value]);
    }

    /**
     * Perubahan status oleh IT. Pesan opsional dikirim sebagai balasan publik ke pelapor;
     * untuk RESOLVED pesan (catatan penyelesaian) wajib.
     */
    public function change(Ticket $ticket, TicketStatus $to, User $actor, ?string $message = null): Ticket
    {
        if (! $actor->isItStaff()) {
            throw ValidationException::withMessages(['status' => 'Hanya IT yang dapat mengubah status tiket.']);
        }
        if (! in_array($to->value, self::IT_TRANSITIONS[$ticket->status->value], true)) {
            throw ValidationException::withMessages([
                'status' => "Status tidak dapat diubah dari {$ticket->status->label()} ke {$to->label()}.",
            ]);
        }
        if ($to === TicketStatus::RESOLVED && trim((string) $message) === '') {
            throw ValidationException::withMessages(['message' => 'Catatan penyelesaian wajib diisi saat menandai tiket Resolved.']);
        }

        return DB::transaction(function () use ($ticket, $to, $actor, $message) {
            $ticket = Ticket::lockForUpdate()->findOrFail($ticket->id);
            $from = $ticket->status;
            $isReopen = $from === TicketStatus::RESOLVED && $to === TicketStatus::IN_PROGRESS;

            // Pesan dikirim sebelum status diterapkan, agar penutupan (CLOSED) tetap dapat menyertakan pesan.
            if (trim((string) $message) !== '') {
                $this->replies->add($ticket, $actor, $message, ReplyVisibility::PUBLIC);
            }

            $this->apply($ticket, $to);
            if ($isReopen) {
                $ticket->reopened_count++;
            }
            $ticket->first_response_at ??= now();
            $ticket->save();

            $this->tickets->history($ticket, $actor, $isReopen ? TicketHistory::REOPENED : TicketHistory::STATUS_CHANGED, $from->value, $to->value);
            $this->audit->log('ticket.status_changed', $ticket, "{$ticket->ticket_number}: {$from->value} → {$to->value}",
                ['status' => $from->value], ['status' => $to->value]);

            return $ticket;
        });
    }

    /** Pelapor mengonfirmasi tiket Resolved sudah benar-benar selesai → CLOSED. */
    public function confirm(Ticket $ticket, User $reporter): Ticket
    {
        $this->guardReporterAction($ticket, $reporter);

        return DB::transaction(function () use ($ticket, $reporter) {
            $ticket = Ticket::lockForUpdate()->findOrFail($ticket->id);
            $this->apply($ticket, TicketStatus::CLOSED);
            $ticket->save();

            $this->tickets->history($ticket, $reporter, TicketHistory::CONFIRMED, TicketStatus::RESOLVED->value, TicketStatus::CLOSED->value);
            $this->audit->log('ticket.confirmed', $ticket, "{$ticket->ticket_number} dikonfirmasi selesai oleh pelapor");

            return $ticket;
        });
    }

    /** Pelapor membuka kembali tiket Resolved yang ternyata belum selesai → IN_PROGRESS. */
    public function reopen(Ticket $ticket, User $reporter, string $reason): Ticket
    {
        $this->guardReporterAction($ticket, $reporter);

        return DB::transaction(function () use ($ticket, $reporter, $reason) {
            $ticket = Ticket::lockForUpdate()->findOrFail($ticket->id);
            $this->apply($ticket, TicketStatus::IN_PROGRESS);
            $ticket->reopened_count++;
            $ticket->save();

            $this->tickets->history($ticket, $reporter, TicketHistory::REOPENED, TicketStatus::RESOLVED->value, TicketStatus::IN_PROGRESS->value, $reason);
            $this->audit->log('ticket.reopened', $ticket, "{$ticket->ticket_number} dibuka kembali oleh pelapor");
            $this->replies->add($ticket, $reporter, 'Tiket dibuka kembali: '.$reason, ReplyVisibility::PUBLIC, recordHistory: false);

            return $ticket;
        });
    }

    /**
     * Menutup otomatis tiket RESOLVED yang tidak dikonfirmasi dalam N hari (setting ticket.auto_close_days).
     * Dijalankan oleh scheduler (cron) — bukan oleh request user.
     *
     * @return Collection<int, Ticket>
     */
    public function autoCloseResolved(): Collection
    {
        $days = (int) $this->settings->get('ticket.auto_close_days', 3);
        if ($days <= 0) {
            return collect();
        }

        $closed = collect();
        Ticket::query()
            ->where('status', TicketStatus::RESOLVED->value)
            ->where('resolved_at', '<=', now()->subDays($days))
            ->orderBy('id')
            ->each(function (Ticket $ticket) use ($days, $closed) {
                DB::transaction(function () use ($ticket, $days, $closed) {
                    $ticket = Ticket::lockForUpdate()->find($ticket->id);
                    if ($ticket?->status !== TicketStatus::RESOLVED) {
                        return;
                    }
                    $this->apply($ticket, TicketStatus::CLOSED);
                    $ticket->save();
                    $this->tickets->history($ticket, null, TicketHistory::AUTO_CLOSED, TicketStatus::RESOLVED->value, TicketStatus::CLOSED->value,
                        "Tidak ada konfirmasi pelapor dalam {$days} hari");
                    $this->audit->log('ticket.auto_closed', $ticket, "{$ticket->ticket_number} ditutup otomatis", userId: null);
                    $closed->push($ticket);
                });
            });

        return $closed;
    }

    /** Menyetel status beserta timestamp terkait (konsisten untuk analytics). */
    private function apply(Ticket $ticket, TicketStatus $to): void
    {
        $ticket->status = $to;

        match ($to) {
            TicketStatus::RESOLVED => $ticket->resolved_at = now(),
            TicketStatus::CLOSED => [$ticket->closed_at = now(), $ticket->resolved_at ??= now()],
            // Dibuka kembali / belum selesai: tidak lagi dihitung sebagai resolved.
            default => $ticket->resolved_at = null,
        };
    }

    private function guardReporterAction(Ticket $ticket, User $reporter): void
    {
        if ($ticket->user_id !== $reporter->id) {
            throw ValidationException::withMessages(['status' => 'Hanya pelapor yang dapat melakukan tindakan ini.']);
        }
        if ($ticket->status !== TicketStatus::RESOLVED) {
            throw ValidationException::withMessages(['status' => 'Tindakan ini hanya untuk tiket berstatus Resolved.']);
        }
    }
}
