<?php

namespace Tests;

use App\Enums\RoleCode;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Services\TicketService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\QuickReplyTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SlaPolicySeeder;
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
        $this->seed([RoleSeeder::class, UnitSeeder::class, SettingSeeder::class, CategorySeeder::class, SlaPolicySeeder::class, QuickReplyTemplateSeeder::class]);
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

    protected function category(string $name = 'Hardware'): Category
    {
        return Category::where('name', $name)->firstOrFail();
    }

    protected function subcategory(string $category, string $name): Subcategory
    {
        return $this->category($category)->subcategories()->where('name', $name)->firstOrFail();
    }

    /** Membuat tiket lewat service (jalur yang sama dengan form). */
    protected function makeTicket(User $reporter, array $overrides = [], array $files = []): Ticket
    {
        return app(TicketService::class)->create($reporter, array_merge([
            'unit_id' => $reporter->units->first()->id,
            'category_id' => $this->category()->id,
            'subcategory_id' => null,
            'title' => 'Printer kasir tidak bisa mencetak',
            'description' => 'Printer kasir di depan mati total sejak pagi.',
            'priority' => null,
        ], $overrides), $files);
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
