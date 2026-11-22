<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    public const VISIBILITY_PUBLIC = 'PUBLIC';

    public const VISIBILITY_INTERNAL = 'INTERNAL';

    protected $fillable = [
        'ticket_id', 'ticket_reply_id', 'user_id', 'original_name', 'stored_path',
        'mime_type', 'size_bytes', 'visibility',
    ];

    /** Path penyimpanan tidak pernah ikut serialisasi. */
    protected $hidden = ['stored_path'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    public function isInternal(): bool
    {
        return $this->visibility === self::VISIBILITY_INTERNAL;
    }

    public function humanSize(): string
    {
        $kb = $this->size_bytes / 1024;

        return $kb >= 1024
            ? number_format($kb / 1024, 1, ',', '.').' MB'
            : number_format(max(1, ceil($kb)), 0, ',', '.').' KB';
    }
}
