<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\AuditService;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    public function test_audit_log_is_append_only(): void
    {
        $log = app(AuditService::class)->log('test.action', description: 'percobaan');

        try {
            $log->update(['action' => 'diubah']);
            $this->fail('Audit log seharusnya tidak dapat diubah.');
        } catch (LogicException) {
        }

        try {
            $log->delete();
            $this->fail('Audit log seharusnya tidak dapat dihapus.');
        } catch (LogicException) {
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'test.action']);
    }

    public function test_sensitive_values_are_stripped(): void
    {
        $log = app(AuditService::class)->log('test.action', newValues: [
            'name' => 'A', 'password' => 'rahasia', 'qr_token' => 'abc', 'remember_token' => 'x',
        ]);

        $this->assertSame(['name' => 'A'], $log->new_values);
    }

    public function test_admin_can_view_and_filter_audit_logs(): void
    {
        $admin = $this->makeAdmin();
        app(AuditService::class)->log('unit.updated', description: 'Ubah unit tertentu');
        app(AuditService::class)->log('auth.login', description: 'Masuk sistem');

        $this->actingAs($admin)->get('/admin/audit-logs?action=unit')
            ->assertOk()->assertSee('Ubah unit tertentu')->assertDontSee('Masuk sistem');
    }

    public function test_there_is_no_route_to_delete_audit_logs(): void
    {
        $log = app(AuditService::class)->log('test.action');

        $this->actingAs($this->makeAdmin())->delete("/admin/audit-logs/{$log->id}")->assertStatus(404);
        $this->assertSame(1, AuditLog::count());
    }

    /** Regression BUG-01: nilai boolean setara (0 vs false) tidak boleh tercatat sebagai perubahan. */
    public function test_unchanged_boolean_is_not_logged_as_change(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser(attributes: ['is_active' => false]);

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'role_id' => $user->role_id,
            'is_active' => 0, 'unit_ids' => [$this->unit()->id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.deactivated']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.updated']);

        $unit = $this->unit('OMA');
        $unit->update(['is_active' => false]);
        $this->actingAs($admin)->put("/admin/units/{$unit->id}", [
            'code' => 'OMA', 'name' => $unit->name, 'is_active' => 0, 'sort_order' => $unit->sort_order,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'unit.updated']);
    }

    public function test_real_change_is_still_logged_with_old_and_new_values(): void
    {
        $user = $this->makeUser(attributes: ['name' => 'Lama']);

        $this->actingAs($this->makeAdmin())->put("/admin/users/{$user->id}", [
            'name' => 'Baru', 'username' => $user->username, 'email' => $user->email, 'role_id' => $user->role_id,
            'is_active' => 1, 'unit_ids' => [$this->unit()->id],
        ]);

        $log = AuditLog::where('action', 'user.updated')->firstOrFail();
        $this->assertSame(['name' => 'Lama'], $log->old_values);
        $this->assertSame(['name' => 'Baru'], $log->new_values);
    }
}
