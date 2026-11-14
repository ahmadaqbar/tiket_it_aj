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
}
