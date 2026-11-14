<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Unit;
use App\Services\UnitService;
use Database\Seeders\UnitSeeder;
use Tests\TestCase;

class UnitManagementTest extends TestCase
{
    public function test_fifteen_units_are_seeded_from_database_not_hardcoded(): void
    {
        $this->assertSame(15, Unit::count());
        $this->assertEquals(
            ['HO', 'MK', 'GK', 'KMC', 'K57', 'DR', 'TS', 'SCN', 'O2D', 'MMJ', 'PMC', 'POV', 'OMA', 'SH', 'STZ'],
            Unit::ordered()->pluck('code')->all(),
        );
    }

    public function test_each_unit_has_unique_secure_qr_token(): void
    {
        $tokens = Unit::pluck('qr_token');

        $this->assertCount(15, $tokens->unique());
        $tokens->each(fn ($t) => $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $t));
    }

    public function test_unit_seeder_is_idempotent_and_keeps_qr_tokens(): void
    {
        $before = Unit::pluck('qr_token', 'code');
        $this->seed(UnitSeeder::class);

        $this->assertSame(15, Unit::count());
        $this->assertEquals($before, Unit::pluck('qr_token', 'code'));
    }

    public function test_admin_can_list_units(): void
    {
        $this->actingAs($this->makeAdmin())->get('/admin/units')
            ->assertOk()->assertSee('Driam Resort')->assertSee('Stanza Working Space');
    }

    public function test_qr_token_is_not_exposed_in_unit_list(): void
    {
        $token = $this->unit('DR')->qr_token;

        $this->actingAs($this->makeAdmin())->get('/admin/units')->assertDontSee($token);
    }

    public function test_admin_can_create_unit_with_uppercased_code(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/admin/units', [
            'code' => 'nw1', 'name' => 'Unit Baru', 'is_active' => 1, 'sort_order' => 16,
        ])->assertRedirect(route('admin.units.index'));

        $unit = Unit::where('code', 'NW1')->firstOrFail();
        $this->assertSame(48, strlen($unit->qr_token));
        $this->assertDatabaseHas('audit_logs', ['action' => 'unit.created', 'entity_id' => $unit->id, 'user_id' => $admin->id]);
    }

    public function test_unit_code_must_be_unique_and_alphanumeric(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/admin/units', ['code' => 'HO', 'name' => 'Duplikat'])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post('/admin/units', ['code' => 'A B', 'name' => 'Spasi'])->assertSessionHasErrors('code');
    }

    public function test_admin_can_update_and_deactivate_unit_with_audit(): void
    {
        $unit = $this->unit('TS');

        $this->actingAs($this->makeAdmin())->put("/admin/units/{$unit->id}", [
            'code' => 'TS', 'name' => 'Tepi Sungai Resto', 'is_active' => 0, 'sort_order' => 7,
        ])->assertSessionHasNoErrors();

        $unit->refresh();
        $this->assertSame('Tepi Sungai Resto', $unit->name);
        $this->assertFalse($unit->is_active);

        $log = AuditLog::where('action', 'unit.updated')->where('entity_id', $unit->id)->firstOrFail();
        $this->assertSame('Tepi Sungai', $log->old_values['name']);
        $this->assertSame('Tepi Sungai Resto', $log->new_values['name']);
    }

    public function test_units_cannot_be_deleted(): void
    {
        $unit = $this->unit('OMA');

        $this->actingAs($this->makeAdmin())->delete("/admin/units/{$unit->id}")->assertStatus(405);
        $this->assertNotNull($unit->fresh());
        $this->assertFalse($this->makeAdmin()->can('delete', $unit));
    }

    public function test_qr_token_can_be_regenerated_and_is_audited(): void
    {
        $unit = $this->unit('SCN');
        $old = $unit->qr_token;
        $this->actingAs($this->makeAdmin());

        app(UnitService::class)->regenerateQrToken($unit);

        $this->assertNotSame($old, $unit->fresh()->qr_token);
        $this->assertDatabaseHas('audit_logs', ['action' => 'unit.qr_regenerated', 'entity_id' => $unit->id]);
    }
}
