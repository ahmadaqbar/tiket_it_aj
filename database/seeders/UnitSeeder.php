<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Edisi Almega: 15 unit operasional PT Almega Jaya Group. Edisi produk: 1 unit awal generik.
 * Idempotent: unit yang sudah ada tidak diubah token QR-nya.
 */
class UnitSeeder extends Seeder
{
    public const UNITS = [
        ['HO', 'Head Office / Almega Jaya'],
        ['MK', 'Main Kitchen'],
        ['GK', 'Gudang Kopi'],
        ['KMC', 'Kost Mega Citra'],
        ['K57', 'Kost A57'],
        ['DR', 'Driam Resort'],
        ['TS', 'Tepi Sungai'],
        ['SCN', 'Scenic Cafe'],
        ['O2D', 'O2 Driam'],
        ['MMJ', 'Maha Mega Jaya'],
        ['PMC', 'Puri Mega Citra'],
        ['POV', 'Point of View Resort'],
        ['OMA', 'OMA'],
        ['SH', 'Summerville Hotel'],
        ['STZ', 'Stanza Working Space'],
    ];

    /** Edisi produk: satu unit awal generik; organisasi menambah unitnya sendiri lewat Unit Management. */
    public const PRODUCT_UNITS = [
        ['HQ', 'Kantor Pusat'],
    ];

    public function run(): void
    {
        $units = config('helpdesk.edition') === 'almega' ? self::UNITS : self::PRODUCT_UNITS;

        foreach ($units as $index => [$code, $name]) {
            $unit = Unit::firstOrNew(['code' => $code]);

            if (! $unit->exists) {
                $unit->fill(['name' => $name, 'is_active' => true, 'sort_order' => $index + 1]);
                $unit->qr_token = Unit::generateQrToken();
                $unit->qr_token_generated_at = now();
                $unit->save();
            }
        }
    }
}
