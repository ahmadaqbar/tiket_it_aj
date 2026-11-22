<?php

namespace App\Models;

use App\Enums\ReplyVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketReply extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'message', 'visibility'];

    protected function casts(): array
    {
        return ['visibility' => ReplyVisibility::class];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', ReplyVisibility::PUBLIC->value);
    }

    /** Balasan yang boleh dilihat user tertentu — satu-satunya pintu untuk menampilkan percakapan. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isItStaff() ? $query : $query->public();
    }

    public function isInternal(): bool
    {
        return $this->visibility === ReplyVisibility::INTERNAL;
    }
}
