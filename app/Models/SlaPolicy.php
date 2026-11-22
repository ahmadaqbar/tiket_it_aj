<?php

namespace App\Models;

use App\Enums\TicketPriority;
use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    protected $fillable = ['priority', 'name', 'response_minutes', 'resolution_minutes', 'is_active'];

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function activeFor(TicketPriority $priority): ?self
    {
        return static::query()->where('priority', $priority->value)->where('is_active', true)->first();
    }

    public static function formatMinutes(int $minutes): string
    {
        if ($minutes % 1440 === 0) {
            return ($minutes / 1440).' hari';
        }
        if ($minutes % 60 === 0) {
            return ($minutes / 60).' jam';
        }

        return $minutes.' menit';
    }
}
