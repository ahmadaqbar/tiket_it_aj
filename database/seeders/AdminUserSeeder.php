<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Membuat satu akun IT Admin awal dari config/helpdesk.php (.env) bila belum ada IT Admin.
 * Tidak ada password yang ditulis di source code.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRoleId = Role::idFor(RoleCode::IT_ADMIN);

        if (User::withTrashed()->where('role_id', $adminRoleId)->exists()) {
            return;
        }

        $config = config('helpdesk.initial_admin');
        $password = $config['password'] ?: Str::password(16, symbols: false);

        User::create([
            'role_id' => $adminRoleId,
            'name' => $config['name'],
            'username' => strtolower($config['username']),
            'email' => $config['email'] ?: null,
            'password' => $password,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        if (! $config['password']) {
            $this->command?->warn('Password IT Admin awal (catat & segera ganti): '.$password);
        }
        $this->command?->info('Akun IT Admin awal dibuat: '.$config['username']);
    }
}
