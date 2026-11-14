<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Unit extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_active', 'sort_order'];

    /** Token QR tidak pernah ikut serialisasi otomatis. */
    protected $hidden = ['qr_token'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'qr_token_generated_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_units')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Token acak kriptografis 48 karakter hex (192 bit). */
    public static function generateQrToken(): string
    {
        do {
            $token = bin2hex(random_bytes(24));
        } while (static::query()->where('qr_token', $token)->exists());

        return $token;
    }

    public function label(): string
    {
        return "{$this->code} — {$this->name}";
    }
}
