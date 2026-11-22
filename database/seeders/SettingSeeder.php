<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public const DEFAULTS = [
        ['ticket.number_prefix', 'IT', 'string', 'Prefix nomor tiket (format: PREFIX-UNIT-YYYYMMDD-NNN)'],
        ['ticket.auto_close_days', '3', 'int', 'Tiket Resolved otomatis Closed setelah (hari)'],
        ['upload.max_kb', '5120', 'int', 'Ukuran maksimum lampiran (KB)'],
        ['upload.max_files', '5', 'int', 'Jumlah maksimum lampiran per kiriman'],
        ['kb.public_enabled', '1', 'bool', 'Pusat bantuan (artikel publik) dapat diakses user'],
        ['ai.enabled', '0', 'bool', 'Asisten AI aktif'],
        ['organization.name', 'PT Almega Jaya Group', 'string', 'Nama organisasi'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as [$key, $value, $type, $label]) {
            // Nilai yang sudah diubah admin tidak ditimpa.
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'type' => $type, 'label' => $label]);
        }
    }
}
