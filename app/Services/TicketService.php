<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class TicketService
{
    public function __construct(
        private readonly TicketNumberService $numbers,
        private readonly AttachmentService $attachments,
        private readonly AuditService $audit,
    ) {}

    /**
     * Membuat tiket baru beserta nomor, SLA, lampiran, history, dan audit dalam satu transaksi.
     *
     * @param  array{unit_id:int,category_id:int,subcategory_id?:?int,title:string,description:string,priority?:?string}  $data
     * @param  array<UploadedFile>  $files
     */
    public function create(User $reporter, array $data, array $files = [], TicketSource $source = TicketSource::WEB): Ticket
    {
        try {
            $ticket = DB::transaction(function () use ($reporter, $data, $files, $source) {
                $unit = Unit::findOrFail($data['unit_id']);
                $priority = isset($data['priority']) && $data['priority'] !== null
                    ? TicketPriority::from($data['priority'])
                    : TicketPriority::DEFAULT;
                $sla = SlaPolicy::activeFor($priority);
                $now = now();

                $ticket = new Ticket([
                    'user_id' => $reporter->id,
                    'unit_id' => $unit->id,
                    'category_id' => $data['category_id'],
                    'subcategory_id' => $data['subcategory_id'] ?? null,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'priority' => $priority,
                    'status' => TicketStatus::NEW,
                    'source' => $source,
                    'sla_policy_id' => $sla?->id,
                    'response_due_at' => $sla ? $now->copy()->addMinutes($sla->response_minutes) : null,
                    'resolution_due_at' => $sla ? $now->copy()->addMinutes($sla->resolution_minutes) : null,
                ]);
                $ticket->ticket_number = $this->numbers->next($unit, $now);
                $ticket->save();

                foreach ($files as $file) {
                    $this->attachments->store($ticket, $file, $reporter);
                }

                $this->history($ticket, $reporter, TicketHistory::CREATED, null, TicketStatus::NEW->value,
                    $files !== [] ? count($files).' lampiran' : null);

                $this->audit->log('ticket.created', $ticket, "Membuat tiket {$ticket->ticket_number}", newValues: [
                    'ticket_number' => $ticket->ticket_number,
                    'unit_id' => $unit->id,
                    'category_id' => $ticket->category_id,
                    'priority' => $priority->value,
                    'source' => $source->value,
                    'attachments' => count($files),
                ]);

                return $ticket;
            });
        } catch (Throwable $e) {
            $this->attachments->discardWritten();
            throw $e;
        }

        $this->attachments->forgetWritten();

        return $ticket;
    }

    public function history(Ticket $ticket, ?User $actor, string $action, ?string $old = null, ?string $new = null, ?string $note = null): TicketHistory
    {
        return TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'old_value' => $old,
            'new_value' => $new,
            'note' => $note,
        ]);
    }
}
