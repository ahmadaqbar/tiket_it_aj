<?php

namespace Tests\Feature\Authorization;

use App\Enums\RoleCode;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    public static function adminRoutes(): array
    {
        return [
            ['/admin/users'],
            ['/admin/users/create'],
            ['/admin/units'],
            ['/admin/units/create'],
            ['/admin/audit-logs'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_user_role_cannot_access_admin_pages(string $uri): void
    {
        $this->actingAs($this->makeUser())->get($uri)->assertForbidden();
    }

    #[DataProvider('adminRoutes')]
    public function test_it_role_cannot_access_admin_pages(string $uri): void
    {
        $this->actingAs($this->makeIt())->get($uri)->assertForbidden();
    }

    #[DataProvider('adminRoutes')]
    public function test_it_admin_can_access_admin_pages(string $uri): void
    {
        $this->actingAs($this->makeAdmin())->get($uri)->assertOk();
    }

    public function test_user_role_cannot_access_it_dashboard(): void
    {
        $this->actingAs($this->makeUser())->get('/it/dashboard')->assertForbidden();
    }

    public function test_it_staff_cannot_access_user_dashboard(): void
    {
        $this->actingAs($this->makeIt())->get('/user/dashboard')->assertForbidden();
    }

    public function test_it_and_admin_can_access_it_dashboard(): void
    {
        $this->actingAs($this->makeIt())->get('/it/dashboard')->assertOk();
        $this->actingAs($this->makeAdmin())->get('/it/dashboard')->assertOk();
    }

    public function test_user_cannot_post_to_admin_endpoints(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/admin/units', ['code' => 'XX', 'name' => 'Hack'])->assertForbidden();
        $this->actingAs($user)->post('/admin/users', ['name' => 'x'])->assertForbidden();
        $this->assertDatabaseMissing('units', ['code' => 'XX']);
    }

    public function test_it_staff_cannot_modify_users_via_direct_request(): void
    {
        $target = $this->makeUser();

        $this->actingAs($this->makeIt())->put("/admin/users/{$target->id}", ['name' => 'Diubah'])->assertForbidden();
        $this->assertNotSame('Diubah', $target->fresh()->name);
    }

    public function test_gates_match_roles(): void
    {
        $user = $this->makeUser();
        $it = $this->makeIt();
        $admin = $this->makeAdmin();

        $this->assertFalse(Gate::forUser($user)->allows('access-it'));
        $this->assertTrue(Gate::forUser($it)->allows('access-it'));
        $this->assertFalse(Gate::forUser($it)->allows('access-admin'));
        $this->assertTrue(Gate::forUser($admin)->allows('access-it'));
        $this->assertTrue(Gate::forUser($admin)->allows('access-admin'));
    }

    public function test_admin_menu_only_rendered_for_admin(): void
    {
        $this->actingAs($this->makeIt())->get('/it/dashboard')->assertDontSee('User Management');
        $this->actingAs($this->makeAdmin())->get('/it/dashboard')->assertSee('User Management');
    }

    public function test_home_redirects_by_role(): void
    {
        $this->actingAs($this->makeUser())->get('/')->assertRedirect(route('user.dashboard'));
        $this->actingAs($this->makeUser(RoleCode::IT_ADMIN))->get('/')->assertRedirect(route('it.dashboard'));
    }
}
