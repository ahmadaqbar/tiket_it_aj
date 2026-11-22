<?php

namespace Tests\Feature\Admin;

use App\Models\QuickReplyTemplate;
use App\Models\Unit;
use App\Services\AttachmentService;
use App\Services\SettingService;
use App\Support\Branding;
use Database\Seeders\UnitSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'app_name' => 'ALMEGA IT TICKETING SYSTEM', 'short_name' => 'ALMEGA IT', 'organization_name' => 'PT Almega Jaya Group',
            'support_team' => 'Divisi IT PT Almega Jaya Group', 'primary_color' => '#1b5434', 'ticket_number_prefix' => 'IT',
            'auto_close_days' => 3, 'upload_max_kb' => 5120, 'upload_max_files' => 5, 'kb_public_enabled' => 1, 'ai_enabled' => 0,
        ], $overrides);
    }

    public function test_almega_defaults_are_preserved_without_any_setting(): void
    {
        $this->assertSame('ALMEGA IT TICKETING SYSTEM', Branding::appName());
        $this->assertSame('ALMEGA IT', Branding::shortName());
        $this->assertSame('PT Almega Jaya Group', Branding::organization());
        $this->assertSame('', Branding::themeCss(), 'Warna default tidak menyuntikkan CSS apa pun');
        $this->get('/login')->assertSee('ALMEGA IT TICKETING SYSTEM')->assertSee('PT Almega Jaya Group');
    }

    public function test_settings_page_is_admin_only(): void
    {
        $this->actingAs($this->makeUser())->get('/admin/settings')->assertForbidden();
        $this->actingAs($this->makeIt())->get('/admin/settings')->assertForbidden();
        $this->actingAs($this->makeIt())->put('/admin/settings', $this->payload(['app_name' => 'Hack']))->assertForbidden();
        $this->actingAs($this->makeAdmin())->get('/admin/settings')->assertOk()->assertSee('System Settings');
    }

    public function test_admin_rebrands_for_product_edition_and_it_is_audited(): void
    {
        $this->actingAs($this->makeAdmin())->put('/admin/settings', $this->payload([
            'app_name' => 'Helpdesk IT Hotel Mawar', 'short_name' => 'Helpdesk Mawar', 'organization_name' => 'Hotel Mawar',
            'support_team' => 'Tim IT Hotel Mawar', 'contact_email' => 'it@hotelmawar.test', 'contact_phone' => '0812-0000-1111',
            'primary_color' => '#1d4ed8',
        ]))->assertSessionHasNoErrors();
        auth()->logout();

        $this->get('/login')->assertSee('Helpdesk IT Hotel Mawar')->assertSee('Hotel Mawar')->assertSee('it@hotelmawar.test')->assertDontSee('Almega');
        $this->assertStringContainsString('--color-brand-700:#1d4ed8', Branding::themeCss());
        $this->actingAs($this->makeUser())->get('/user/dashboard')->assertSee('--color-brand-700:#1d4ed8', false)->assertSee('0812-0000-1111');
        $this->get('/manifest.webmanifest')->assertJsonPath('short_name', 'Helpdesk Mawar')->assertJsonPath('theme_color', '#1d4ed8');
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_ticket_prefix_and_limits_take_effect(): void
    {
        $this->actingAs($this->makeAdmin())->put('/admin/settings', $this->payload(['ticket_number_prefix' => 'hd', 'upload_max_files' => 2]))
            ->assertSessionHasNoErrors();

        $this->assertStringStartsWith('HD-HO-', $this->makeTicket($this->makeUser())->ticket_number);
        $this->assertSame(2, app(AttachmentService::class)->maxFiles());
    }

    public function test_validation(): void
    {
        $this->actingAs($this->makeAdmin())->put('/admin/settings', $this->payload([
            'primary_color' => 'merah', 'ticket_number_prefix' => 'IT-01', 'upload_max_kb' => 999999, 'contact_email' => 'bukan-email',
        ]))->assertSessionHasErrors(['primary_color', 'ticket_number_prefix', 'upload_max_kb', 'contact_email']);
    }

    public function test_logo_upload_served_without_public_symlink_and_removable(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put('/admin/settings', $this->payload(['logo' => UploadedFile::fake()->image('logo.png', 200, 80)]))->assertSessionHasNoErrors();
        $path = app(SettingService::class)->get('branding.logo_path');
        Storage::disk('local')->assertExists($path);
        $this->assertNotNull(Branding::logoUrl());
        auth()->logout();
        $this->get('/branding/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/login')->assertSee(route('branding.logo', [], false), false);

        $this->actingAs($admin)->put('/admin/settings', $this->payload(['remove_logo' => 1]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($path);
        $this->get('/branding/logo')->assertNotFound();
    }

    public function test_logo_rejects_non_images(): void
    {
        $this->actingAs($this->makeAdmin())->put('/admin/settings', $this->payload([
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ]))->assertSessionHasErrors('logo');
    }

    public function test_ai_toggle_from_settings(): void
    {
        $this->actingAs($this->makeAdmin())->put('/admin/settings', $this->payload(['ai_enabled' => 1]));
        $this->assertTrue((bool) app(SettingService::class)->get('ai.enabled'));

        // Tanpa API key AI tetap tidak tersedia, dengan alasan jelas.
        config(['ai.api_key' => null]);
        $this->actingAs($this->makeAdmin())->get('/admin/settings')->assertSee('API key AI belum dikonfigurasi');
    }

    public function test_api_key_is_never_shown_on_settings_page(): void
    {
        config(['ai.api_key' => 'sk-ant-SUPER-SECRET-VALUE']);

        $this->actingAs($this->makeAdmin())->get('/admin/settings')->assertOk()->assertSee('Terkonfigurasi')->assertDontSee('SUPER-SECRET');
    }

    public function test_quick_reply_templates_management(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/admin/settings/templates', ['title' => 'Jadwal kunjungan', 'message' => 'Teknisi akan datang hari ini.', 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $template = QuickReplyTemplate::where('title', 'Jadwal kunjungan')->firstOrFail();

        $this->actingAs($admin)->put("/admin/settings/templates/{$template->id}", ['title' => 'Jadwal', 'message' => 'Teknisi datang besok.', 'is_active' => 0])
            ->assertSessionHasNoErrors();
        $this->assertFalse($template->fresh()->is_active);

        $ticket = $this->makeTicket($this->makeUser());
        $this->actingAs($this->makeIt())->get("/it/tiket/{$ticket->public_id}")->assertDontSee('Teknisi datang besok.');

        $this->actingAs($admin)->delete("/admin/settings/templates/{$template->id}");
        $this->assertNull($template->fresh());
        $this->actingAs($this->makeIt())->post('/admin/settings/templates', ['title' => 'x', 'message' => 'y'])->assertForbidden();
    }

    public function test_manual_scheduled_task_run_for_hosting_without_cron(): void
    {
        $ticket = $this->makeTicket($this->makeUser());
        $ticket->forceFill(['status' => 'RESOLVED', 'resolved_at' => now()->subDays(10)])->save();

        $this->actingAs($this->makeAdmin())->post('/admin/settings/run-tasks')->assertSessionHas('success');

        $this->assertSame('CLOSED', $ticket->fresh()->status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.scheduled_tasks_run']);
        $this->actingAs($this->makeIt())->post('/admin/settings/run-tasks')->assertForbidden();
    }

    public function test_product_edition_seeds_one_generic_unit(): void
    {
        Unit::query()->delete();
        config(['helpdesk.edition' => 'product']);

        $this->seed(UnitSeeder::class);

        $this->assertSame(['HQ'], Unit::pluck('code')->all());
    }
}
