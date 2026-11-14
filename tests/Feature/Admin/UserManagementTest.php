<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rina Kasir',
            'username' => 'Rina.Kasir',
            'email' => 'rina@almegajaya.com',
            'phone' => '08123456789',
            'role_id' => Role::idFor(RoleCode::USER),
            'is_active' => 1,
            'password' => 'Sementara123',
            'password_confirmation' => 'Sementara123',
            'unit_ids' => [$this->unit('DR')->id, $this->unit('O2D')->id],
            'primary_unit_id' => $this->unit('O2D')->id,
        ], $overrides);
    }

    public function test_admin_can_list_and_filter_users(): void
    {
        $admin = $this->makeAdmin();
        $this->makeUser(attributes: ['name' => 'Andi Gudang'], unitCode: 'GK');
        $this->makeUser(attributes: ['name' => 'Bella Hotel'], unitCode: 'SH');

        $this->actingAs($admin)->get('/admin/users?q=Andi')
            ->assertOk()->assertSee('Andi Gudang')->assertDontSee('Bella Hotel');

        $this->actingAs($admin)->get('/admin/users?unit_id='.$this->unit('SH')->id)
            ->assertSee('Bella Hotel')->assertDontSee('Andi Gudang');
    }

    public function test_admin_can_create_user_with_multiple_units(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/admin/users', $this->payload())
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $user = User::where('username', 'rina.kasir')->firstOrFail();
        $this->assertTrue(Hash::check('Sementara123', $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertEqualsCanonicalizing(['DR', 'O2D'], $user->units->pluck('code')->all());
        $this->assertSame('O2D', $user->primaryUnit()->code);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'entity_type' => 'User', 'entity_id' => $user->id, 'user_id' => $admin->id]);
    }

    public function test_audit_log_never_contains_password(): void
    {
        $this->actingAs($this->makeAdmin())->post('/admin/users', $this->payload());

        $log = AuditLog::where('action', 'user.created')->firstOrFail();
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertStringNotContainsString('Sementara123', json_encode($log->new_values));
    }

    public function test_user_role_requires_at_least_one_unit(): void
    {
        $this->actingAs($this->makeAdmin())
            ->post('/admin/users', $this->payload(['unit_ids' => [], 'primary_unit_id' => null]))
            ->assertSessionHasErrors('unit_ids');
    }

    public function test_it_role_can_be_created_without_unit(): void
    {
        $this->actingAs($this->makeAdmin())
            ->post('/admin/users', $this->payload(['username' => 'it.baru', 'role_id' => Role::idFor(RoleCode::IT), 'unit_ids' => [], 'primary_unit_id' => null]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::where('username', 'it.baru')->firstOrFail()->isItStaff());
    }

    public function test_validation_rejects_duplicate_username_and_weak_password(): void
    {
        $admin = $this->makeAdmin();
        $this->makeUser(attributes: ['username' => 'rina.kasir']);

        $this->actingAs($admin)
            ->post('/admin/users', $this->payload(['password' => 'abc', 'password_confirmation' => 'abc']))
            ->assertSessionHasErrors(['username', 'password']);
    }

    public function test_primary_unit_must_be_one_of_selected_units(): void
    {
        $this->actingAs($this->makeAdmin())
            ->post('/admin/users', $this->payload(['primary_unit_id' => $this->unit('HO')->id]))
            ->assertSessionHasErrors('primary_unit_id');
    }

    public function test_admin_can_update_user_and_change_units(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser(unitCode: 'HO');

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => 'Nama Diubah',
            'username' => $user->username,
            'role_id' => $user->role_id,
            'is_active' => 1,
            'unit_ids' => [$this->unit('MK')->id],
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Nama Diubah', $user->name);
        $this->assertSame(['MK'], $user->units->pluck('code')->all());
        $this->assertTrue((bool) $user->units->first()->pivot->is_primary);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'entity_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.units_changed', 'entity_id' => $user->id]);
    }

    public function test_password_cannot_be_changed_through_update_form(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => $user->name, 'username' => $user->username, 'role_id' => $user->role_id,
            'is_active' => 1, 'unit_ids' => [$this->unit()->id],
            'password' => 'Lainnya123', 'password_confirmation' => 'Lainnya123',
        ])->assertSessionHasErrors('password');
    }

    public function test_admin_can_deactivate_user_and_it_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => $user->name, 'username' => $user->username, 'role_id' => $user->role_id,
            'is_active' => 0, 'unit_ids' => [$this->unit()->id],
        ])->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivated', 'entity_id' => $user->id]);
    }

    public function test_admin_cannot_deactivate_or_demote_self(): void
    {
        $admin = $this->makeAdmin();
        $this->makeAdmin(); // admin lain tetap ada

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name, 'username' => $admin->username,
            'role_id' => Role::idFor(RoleCode::IT), 'is_active' => 1,
        ])->assertSessionHasErrors('role_id');

        $this->assertTrue($admin->fresh()->isItAdmin());
    }

    public function test_last_active_admin_cannot_be_deactivated(): void
    {
        $admin = $this->makeAdmin();
        $other = $this->makeAdmin();

        // Dua admin aktif: admin pertama boleh menonaktifkan admin kedua.
        $this->actingAs($admin)->put("/admin/users/{$other->id}", [
            'name' => $other->name, 'username' => $other->username, 'role_id' => $other->role_id, 'is_active' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertFalse($other->fresh()->is_active);

        // Kini $admin adalah satu-satunya IT Admin aktif → tidak boleh dinonaktifkan oleh siapa pun.
        $this->expectException(ValidationException::class);
        app(UserService::class)->update($admin, ['is_active' => false], $other->fresh());
    }

    public function test_admin_can_reset_password_of_other_user(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();

        $this->actingAs($admin)->put("/admin/users/{$user->id}/password", [
            'password' => 'ResetBaru123', 'password_confirmation' => 'ResetBaru123',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('ResetBaru123', $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_reset', 'entity_id' => $user->id]);
    }

    public function test_admin_cannot_reset_own_password_via_admin_endpoint(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put("/admin/users/{$admin->id}/password", [
            'password' => 'ResetBaru123', 'password_confirmation' => 'ResetBaru123',
        ])->assertForbidden();
    }

    public function test_users_cannot_be_deleted_via_http(): void
    {
        $user = $this->makeUser();

        $this->actingAs($this->makeAdmin())->delete("/admin/users/{$user->id}")->assertStatus(405);
        $this->assertNotNull($user->fresh());
    }
}
