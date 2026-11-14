<?php

namespace Database\Factories;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'role_id' => fn () => Role::idFor(RoleCode::USER),
            'name' => fake()->name(),
            'username' => fake()->unique()->userName().Str::lower(Str::random(3)),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'password' => static::$password ??= 'password123',
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(RoleCode $code): static
    {
        return $this->state(fn () => ['role_id' => Role::idFor($code)]);
    }

    public function it(): static
    {
        return $this->role(RoleCode::IT);
    }

    public function admin(): static
    {
        return $this->role(RoleCode::IT_ADMIN);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn () => ['must_change_password' => true]);
    }

    public function forUnit(Unit $unit): static
    {
        return $this->afterCreating(fn (User $user) => $user->units()->attach($unit->id, ['is_primary' => true]));
    }
}
