<?php

namespace App\Models;

use App\Enums\RoleCode;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'role_id', 'name', 'username', 'email', 'phone', 'password',
        'is_active', 'must_change_password',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** Role selalu dibutuhkan untuk otorisasi. */
    protected $with = ['role'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'user_units')
            ->withPivot('is_primary')
            ->withTimestamps()
            ->orderByDesc('user_units.is_primary')
            ->orderBy('units.sort_order');
    }

    public function roleCode(): RoleCode
    {
        return $this->role->code;
    }

    public function hasRole(RoleCode ...$codes): bool
    {
        return in_array($this->roleCode(), $codes, true);
    }

    public function isUser(): bool
    {
        return $this->roleCode() === RoleCode::USER;
    }

    public function isItStaff(): bool
    {
        return $this->roleCode()->isItStaff();
    }

    public function isItAdmin(): bool
    {
        return $this->roleCode() === RoleCode::IT_ADMIN;
    }

    public function primaryUnit(): ?Unit
    {
        return $this->units->firstWhere('pivot.is_primary', true) ?? $this->units->first();
    }

    public function belongsToUnit(int $unitId): bool
    {
        return $this->units->contains('id', $unitId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithRole(Builder $query, RoleCode ...$codes): Builder
    {
        return $query->whereHas('role', fn (Builder $q) => $q->whereIn('code', array_map(fn ($c) => $c->value, $codes)));
    }

    public function homeRoute(): string
    {
        return $this->isItStaff() ? 'it.dashboard' : 'user.dashboard';
    }
}
