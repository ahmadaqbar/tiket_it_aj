<?php

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use Illuminate\Database\Seeder;

/**
 * Target SLA default (menit). Nilai yang sudah diubah IT Admin tidak ditimpa.
 */
class SlaPolicySeeder extends Seeder
{
    public const DEFAULTS = [
        'LOW' => ['SLA Rendah', 480, 4320],
        'MEDIUM' => ['SLA Sedang', 240, 1440],
        'HIGH' => ['SLA Tinggi', 60, 480],
        'URGENT' => ['SLA Mendesak', 30, 240],
    ];

    public function run(): void
    {
        foreach (TicketPriority::cases() as $priority) {
            [$name, $response, $resolution] = self::DEFAULTS[$priority->value];
            SlaPolicy::firstOrCreate(['priority' => $priority->value], [
                'name' => $name, 'response_minutes' => $response, 'resolution_minutes' => $resolution, 'is_active' => true,
            ]);
        }
    }
}
