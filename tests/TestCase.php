<?php

namespace Tests;

use App\Enums\RoleCode;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, UnitSeeder::class, SettingSeeder::class]);
    }

    protected function unit(string $code = 'HO'): Unit
    {
        return Unit::where('code', $code)->firstOrFail();
    }

    protected function makeUser(RoleCode $role = RoleCode::USER, array $attributes = [], ?string $unitCode = 'HO'): User
    {
        $factory = User::factory()->role($role)->state($attributes);

        if ($role === RoleCode::USER && $unitCode !== null) {
            $factory = $factory->forUnit($this->unit($unitCode));
        }

        return $factory->create();
    }

    protected function makeIt(array $attributes = []): User
    {
        return $this->makeUser(RoleCode::IT, $attributes);
    }

    protected function makeAdmin(array $attributes = []): User
    {
        return $this->makeUser(RoleCode::IT_ADMIN, $attributes);
    }
}
