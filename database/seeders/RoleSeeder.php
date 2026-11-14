<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $descriptions = [
            RoleCode::USER->value => 'Staf unit: membuat dan memantau tiket miliknya sendiri.',
            RoleCode::IT->value => 'Staf IT: menangani seluruh tiket dari semua unit, analytics, dan laporan.',
            RoleCode::IT_ADMIN->value => 'Administrator: seluruh hak IT ditambah master data, user, konfigurasi, dan audit log.',
        ];

        foreach (RoleCode::cases() as $code) {
            Role::updateOrCreate(['code' => $code->value], [
                'name' => $code->label(),
                'description' => $descriptions[$code->value],
                'level' => $code->level(),
            ]);
        }
    }
}
