<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    public function test_production_seeder_creates_only_master_data_and_one_admin(): void
    {
        config(['almega.initial_admin.password' => 'AwalAdmin123', 'almega.initial_admin.username' => 'itadmin']);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // idempotent

        $this->assertSame(3, Role::count());
        $this->assertSame(1, User::count());
        $admin = User::firstOrFail();
        $this->assertTrue($admin->isItAdmin());
        $this->assertTrue($admin->must_change_password);
    }

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        (new DemoSeeder)->run();
    }

    public function test_roles_are_exactly_three_levels(): void
    {
        $this->assertEqualsCanonicalizing(
            array_map(fn ($c) => $c->value, RoleCode::cases()),
            Role::pluck('code')->map(fn ($c) => $c->value)->all(),
        );
    }
}
