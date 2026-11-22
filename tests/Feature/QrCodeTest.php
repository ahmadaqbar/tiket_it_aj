<?php

namespace Tests\Feature;

use App\Enums\TicketSource;
use App\Models\Ticket;
use Tests\TestCase;

class QrCodeTest extends TestCase
{
    public function test_guest_scanning_qr_is_sent_to_login_then_to_prefilled_form(): void
    {
        $unit = $this->unit('DR');
        $user = $this->makeUser(attributes: ['username' => 'staf.dr'], unitCode: 'DR');
        $user->units()->attach($this->unit('HO')->id, ['is_primary' => false]);

        $this->get("/qr/{$unit->qr_token}")->assertRedirect('/login');
        $this->get('/login')->assertSee('Laporan untuk unit')->assertSee('DR — Driam Resort');

        $this->post('/login', ['login' => 'staf.dr', 'password' => 'password123'])->assertRedirect("/qr/{$unit->qr_token}");
        $this->get("/qr/{$unit->qr_token}")->assertRedirect(route('user.tickets.create'));

        $form = $this->get('/user/tiket/buat')->assertOk();
        $this->assertSame($unit->id, $form->viewData('defaultUnitId'));
    }

    public function test_ticket_created_after_qr_scan_has_source_qr(): void
    {
        $unit = $this->unit('DR');
        $user = $this->makeUser(unitCode: 'DR');

        $this->actingAs($user)->get("/qr/{$unit->qr_token}");
        $this->actingAs($user)->post('/user/tiket', [
            'unit_id' => $unit->id, 'title' => 'Laporan dari QR', 'category_id' => $this->category()->id,
            'description' => 'Dibuat setelah scan QR di lokasi unit.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(TicketSource::QR, Ticket::firstOrFail()->source);
        $this->assertNull(session('qr_unit_id'), 'Sesi QR dibersihkan setelah laporan terkirim');
    }

    public function test_invalid_or_inactive_qr_is_rejected(): void
    {
        $this->get('/qr/'.str_repeat('a', 48))->assertNotFound();
        $this->get('/qr/bukan-token')->assertNotFound();

        $unit = $this->unit('OMA');
        $unit->update(['is_active' => false]);
        $this->get("/qr/{$unit->qr_token}")->assertNotFound();
    }

    public function test_qr_does_not_grant_access_to_any_ticket_data(): void
    {
        $unit = $this->unit('DR');
        $ticket = $this->makeTicket($this->makeUser(unitCode: 'DR'), ['title' => 'Data rahasia unit DR']);

        $response = $this->get("/qr/{$unit->qr_token}");
        $response->assertRedirect('/login');
        $this->assertGuest();
        $this->get("/user/tiket/{$ticket->public_id}")->assertRedirect('/login');
    }

    public function test_user_from_other_unit_gets_warning_and_no_prefill(): void
    {
        $user = $this->makeUser(unitCode: 'HO');

        $this->actingAs($user)->get('/qr/'.$this->unit('DR')->qr_token)
            ->assertRedirect(route('user.tickets.create'))
            ->assertSessionHas('warning');
        $this->assertNull(session('qr_unit_id'));
    }

    public function test_it_staff_scanning_qr_sees_unit_queue(): void
    {
        $unit = $this->unit('SH');

        $this->actingAs($this->makeIt())->get("/qr/{$unit->qr_token}")
            ->assertRedirect(route('it.tickets.index', ['unit_id' => $unit->id]));
    }

    public function test_qr_scan_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get('/qr/'.str_repeat('b', 48));
        }
        $this->get('/qr/'.str_repeat('b', 48))->assertStatus(429);
    }

    public function test_qr_management_is_admin_only(): void
    {
        $unit = $this->unit('DR');
        foreach (['/admin/qr', "/admin/qr/{$unit->id}.png", "/admin/qr/{$unit->id}.pdf", "/admin/qr/{$unit->id}/cetak"] as $uri) {
            $this->actingAs($this->makeUser())->get($uri)->assertForbidden();
            $this->actingAs($this->makeIt())->get($uri)->assertForbidden();
        }
    }

    public function test_admin_can_preview_and_download_png_pdf_and_print(): void
    {
        $admin = $this->makeAdmin();
        $unit = $this->unit('DR');

        $this->actingAs($admin)->get('/admin/qr')->assertOk()->assertSee('<svg', false)->assertSee(route('qr.show', $unit->qr_token));

        $png = $this->actingAs($admin)->get("/admin/qr/{$unit->id}.png")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $png->getContent());
        $this->assertNotFalse(imagecreatefromstring($png->getContent()));

        $pdf = $this->actingAs($admin)->get("/admin/qr/{$unit->id}.pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringStartsWith('%PDF', $this->actingAs($admin)->get('/admin/qr/semua.pdf')->assertOk()->getContent());

        $this->actingAs($admin)->get("/admin/qr/{$unit->id}/cetak")->assertOk()->assertSee('window.print()', false);
    }

    public function test_regenerating_token_invalidates_old_qr(): void
    {
        $unit = $this->unit('DR');
        $old = $unit->qr_token;

        $this->actingAs($this->makeAdmin())->post("/admin/qr/{$unit->id}/regenerate")->assertSessionHasNoErrors();
        auth()->logout();

        $this->get("/qr/{$old}")->assertNotFound();
        $this->get('/qr/'.$unit->fresh()->qr_token)->assertRedirect('/login');
        $this->assertDatabaseHas('audit_logs', ['action' => 'unit.qr_regenerated', 'entity_id' => $unit->id]);
    }

    public function test_pwa_manifest_uses_branding(): void
    {
        $this->get('/manifest.webmanifest')->assertOk()
            ->assertJsonPath('short_name', 'ALMEGA IT')
            ->assertJsonPath('display', 'standalone');
    }
}
