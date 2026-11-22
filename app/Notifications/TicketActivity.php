<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Notifikasi in-app (channel database, sinkron — tidak butuh queue worker).
 * Tidak pernah memuat isi catatan internal.
 */
class TicketActivity extends Notification
{
    public const CREATED = 'created';

    public const REPLIED_BY_IT = 'replied_by_it';

    public const REPLIED_BY_REPORTER = 'replied_by_reporter';

    public const STATUS_CHANGED = 'status_changed';

    public const RESOLVED = 'resolved';

    public const CLOSED = 'closed';

    public const REOPENED = 'reopened';

    public const ASSIGNED = 'assigned';

    public function __construct(
        public readonly Ticket $ticket,
        public readonly string $event,
        public readonly ?User $actor = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $t = $this->ticket;
        $no = $t->ticket_number;

        $message = match ($this->event) {
            self::CREATED => "Tiket baru {$no} ({$t->unit?->code}): ".Str::limit($t->title, 80),
            self::REPLIED_BY_IT => "Tim IT membalas tiket {$no}",
            self::REPLIED_BY_REPORTER => ($this->actor?->name ?? 'Pelapor')." membalas tiket {$no}",
            self::STATUS_CHANGED => "Status tiket {$no} menjadi {$t->status->label()}",
            self::RESOLVED => "Tiket {$no} telah ditangani. Mohon konfirmasi penyelesaian.",
            self::CLOSED => "Tiket {$no} telah ditutup",
            self::REOPENED => "Tiket {$no} dibuka kembali oleh pelapor",
            self::ASSIGNED => "Anda ditetapkan sebagai PIC tiket {$no}",
            default => "Aktivitas baru pada tiket {$no}",
        };

        return [
            'event' => $this->event,
            'ticket_public_id' => $t->public_id,
            'ticket_number' => $no,
            'title' => Str::limit($t->title, 120),
            'message' => $message,
            'actor' => $this->actor?->name,
        ];
    }
}
