<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
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

    public const UNASSIGNED = 'UNASSIGNED';

    public const REPLIED = 'REPLIED';

    public const INTERNAL_NOTE = 'INTERNAL_NOTE';

    public const REOPENED = 'REOPENED';

    public const CONFIRMED = 'CONFIRMED';

    public const AUTO_CLOSED = 'AUTO_CLOSED';

    /** Aksi yang hanya boleh terlihat oleh IT. */
    public const INTERNAL_ACTIONS = [self::INTERNAL_NOTE];

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

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isItStaff() ? $query : $query->whereNotIn('action', self::INTERNAL_ACTIONS);
    }

    /** Nilai status ditampilkan dengan label Bahasa Indonesia. */
    public function displayValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return TicketStatus::tryFrom($value)?->label()
            ?? TicketPriority::tryFrom($value)?->label()
            ?? $value;
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
            self::UNASSIGNED => 'PIC dilepas',
            self::REPLIED => 'Balasan dikirim',
            self::INTERNAL_NOTE => 'Catatan internal ditambahkan',
            self::REOPENED => 'Tiket dibuka kembali',
            self::CONFIRMED => 'Penyelesaian dikonfirmasi pelapor',
            self::AUTO_CLOSED => 'Ditutup otomatis',
            default => $this->action,
        };
    }
}
