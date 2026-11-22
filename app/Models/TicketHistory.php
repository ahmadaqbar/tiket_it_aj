<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat aktivitas tiket — append-only.
 */
class TicketHistory extends Model
{
    public const UPDATED_AT = null;

    public const CREATED = 'CREATED';

    public const STATUS_CHANGED = 'STATUS_CHANGED';

    public const PRIORITY_CHANGED = 'PRIORITY_CHANGED';

    public const CATEGORY_CHANGED = 'CATEGORY_CHANGED';

    public const ASSIGNED = 'ASSIGNED';

    public const ATTACHMENT_ADDED = 'ATTACHMENT_ADDED';

    protected $fillable = ['ticket_id', 'user_id', 'action', 'old_value', 'new_value', 'note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function label(): string
    {
        return match ($this->action) {
            self::CREATED => 'Tiket dibuat',
            self::STATUS_CHANGED => 'Status diubah',
            self::PRIORITY_CHANGED => 'Prioritas diubah',
            self::CATEGORY_CHANGED => 'Kategori diubah',
            self::ASSIGNED => 'PIC ditetapkan',
            self::ATTACHMENT_ADDED => 'Lampiran ditambahkan',
            default => $this->action,
        };
    }
}
