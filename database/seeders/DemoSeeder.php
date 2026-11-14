<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data contoh untuk development lokal SAJA.
 * Jalankan: php artisan db:seed --class=DemoSeeder
 * Ditolak saat APP_ENV=production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder tidak boleh dijalankan di production.');
        }

        $itRole = Role::idFor(RoleCode::IT);
        $userRole = Role::idFor(RoleCode::USER);

        foreach ([['it.budi', 'Budi (IT)'], ['it.sari', 'Sari (IT)']] as [$username, $name]) {
            User::firstOrCreate(['username' => $username], [
                'role_id' => $itRole, 'name' => $name, 'password' => 'password123',
                'is_active' => true, 'must_change_password' => false,
            ]);
        }

        foreach (Unit::ordered()->get() as $unit) {
            $user = User::firstOrCreate(['username' => 'staf.'.strtolower($unit->code)], [
                'role_id' => $userRole, 'name' => "Staf {$unit->name}", 'password' => 'password123',
                'is_active' => true, 'must_change_password' => false,
            ]);
            $user->units()->syncWithoutDetaching([$unit->id => ['is_primary' => true]]);
        }

        $this->command?->info('Demo user dibuat. Password: password123');
    }
}
