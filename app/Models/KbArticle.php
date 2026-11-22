<?php

namespace App\Models;

use App\Enums\KbStatus;
use App\Enums\KbVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KbArticle extends Model
{
    protected $fillable = [
        'title', 'category_id', 'subcategory_id', 'problem', 'symptoms', 'root_cause', 'steps', 'resolution',
        'visibility', 'source_ticket_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => KbStatus::class,
            'visibility' => KbVisibility::class,
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function sourceTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'source_ticket_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(KbTag::class, 'kb_article_tag')->orderBy('name');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', KbStatus::PUBLISHED->value);
    }

    /**
     * Artikel PUBLISHED yang boleh dibaca user ini. Dipakai untuk pusat bantuan, artikel terkait,
     * dan — penting — sebagai SATU-SATUNYA sumber konteks AI (Phase 5), sehingga AI tidak pernah
     * menerima draft, arsip, atau artikel internal untuk user biasa.
     */
    public function scopeReadableBy(Builder $query, User $user): Builder
    {
        $query->published();

        return $user->isItStaff() ? $query : $query->where('visibility', KbVisibility::PUBLIC->value);
    }

    public function isPublished(): bool
    {
        return $this->status === KbStatus::PUBLISHED;
    }

    public function isPublic(): bool
    {
        return $this->visibility === KbVisibility::PUBLIC;
    }
}
