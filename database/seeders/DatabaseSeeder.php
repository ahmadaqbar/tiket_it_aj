<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder production-safe: hanya data master + akun IT Admin awal.
 * Tidak ada dummy data. Data contoh: DemoSeeder (khusus development).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UnitSeeder::class,
            SettingSeeder::class,
            CategorySeeder::class,
            SlaPolicySeeder::class,
            QuickReplyTemplateSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
