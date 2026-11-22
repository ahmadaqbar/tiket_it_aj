<?php

namespace Tests\Feature\Ticket;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\SettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketCreationTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'unit_id' => $this->unit('DR')->id,
            'title' => 'WiFi kamar tidak terhubung',
            'category_id' => $this->category('Network & Internet')->id,
            'subcategory_id' => $this->subcategory('Network & Internet', 'WiFi')->id,
            'description' => 'WiFi di kamar 203 tidak bisa terhubung, device lain masih bisa.',
        ], $overrides);
    }

    public function test_user_can_open_create_form_with_unit_prefilled(): void
    {
        $user = $this->makeUser(unitCode: 'DR');

        $this->actingAs($user)->get('/user/tiket/buat')
            ->assertOk()->assertSee('DR — Driam Resort')->assertSee('Network &amp; Internet', false);
    }

    public function test_user_can_create_ticket_and_sees_ticket_number(): void
    {
        Storage::fake('local');
        $user = $this->makeUser(unitCode: 'DR');

        $response = $this->actingAs($user)->post('/user/tiket', $this->payload());

        $ticket = Ticket::firstOrFail();
        $response->assertRedirect(route('user.tickets.show', $ticket))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, $ticket->ticket_number));

        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame(TicketStatus::NEW, $ticket->status);
        $this->assertSame(TicketPriority::MEDIUM, $ticket->priority, 'Prioritas default Sedang');
        $this->assertSame(26, strlen($ticket->public_id));
        $this->assertMatchesRegularExpression('/^IT-DR-\d{8}-001$/', $ticket->ticket_number);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'CREATED', 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.created', 'entity_type' => 'Ticket', 'entity_id' => $ticket->id]);
    }

    public function test_sla_due_dates_follow_priority_policy(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $user = $this->makeUser(unitCode: 'DR');

        $this->actingAs($user)->post('/user/tiket', $this->payload(['priority' => 'URGENT']));

        $ticket = Ticket::firstOrFail();
        $this->assertSame(TicketPriority::URGENT, $ticket->priority);
        $this->assertSame('10:30', $ticket->response_due_at->format('H:i'));
        $this->assertSame('14:00', $ticket->resolution_due_at->format('H:i'));
    }

    public function test_required_fields_are_validated(): void
    {
        $user = $this->makeUser(unitCode: 'DR');

        $this->actingAs($user)->post('/user/tiket', ['unit_id' => $this->unit('DR')->id])
            ->assertSessionHasErrors(['title', 'category_id', 'description']);
        $this->assertSame(0, Ticket::count());
    }

    public function test_user_cannot_create_ticket_for_unit_they_do_not_belong_to(): void
    {
        $user = $this->makeUser(unitCode: 'DR');

        $this->actingAs($user)->post('/user/tiket', $this->payload(['unit_id' => $this->unit('HO')->id]))
            ->assertSessionHasErrors('unit_id');
        $this->assertSame(0, Ticket::count());
    }

    public function test_inactive_unit_is_rejected(): void
    {
        $user = $this->makeUser(unitCode: 'DR');
        $this->unit('DR')->update(['is_active' => false]);

        // Tanpa unit aktif user tidak dapat membuat laporan sama sekali.
        $this->actingAs($user)->post('/user/tiket', $this->payload())->assertForbidden();
    }

    public function test_subcategory_must_belong_to_selected_category(): void
    {
        $user = $this->makeUser(unitCode: 'DR');

        $this->actingAs($user)->post('/user/tiket', $this->payload([
            'category_id' => $this->category('Hardware')->id,
            'subcategory_id' => $this->subcategory('Network & Internet', 'WiFi')->id,
        ]))->assertSessionHasErrors('subcategory_id');
    }

    public function test_inactive_category_is_rejected(): void
    {
        $user = $this->makeUser(unitCode: 'DR');
        $this->category('Network & Internet')->update(['is_active' => false]);

        $this->actingAs($user)->post('/user/tiket', $this->payload(['subcategory_id' => null]))
            ->assertSessionHasErrors('category_id');
    }

    public function test_it_staff_cannot_use_user_ticket_form(): void
    {
        $this->actingAs($this->makeIt())->get('/user/tiket/buat')->assertForbidden();
        $this->actingAs($this->makeIt())->post('/user/tiket', $this->payload())->assertForbidden();
    }

    public function test_user_can_choose_between_multiple_units(): void
    {
        $user = $this->makeUser(unitCode: 'DR');
        $user->units()->attach($this->unit('O2D')->id, ['is_primary' => false]);

        $this->actingAs($user)->post('/user/tiket', $this->payload(['unit_id' => $this->unit('O2D')->id]))
            ->assertSessionHasNoErrors();

        $this->assertStringStartsWith('IT-O2D-', Ticket::firstOrFail()->ticket_number);
    }

    public function test_ticket_creation_is_rate_limited(): void
    {
        $user = $this->makeUser(unitCode: 'DR');

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post('/user/tiket', $this->payload());
        }

        $this->actingAs($user)->post('/user/tiket', $this->payload())->assertStatus(429);
        $this->assertSame(10, Ticket::count());
    }

    public function test_dashboard_shows_real_counts(): void
    {
        $user = $this->makeUser(unitCode: 'DR');
        $this->makeTicket($user);
        $done = $this->makeTicket($user);
        $done->forceFill(['status' => TicketStatus::CLOSED])->save();
        $this->makeTicket($this->makeUser(unitCode: 'DR')); // tiket user lain tidak dihitung

        $response = $this->actingAs($user)->get('/user/dashboard')->assertOk();
        $stats = $response->viewData('stats');
        $this->assertSame(['total' => 2, 'new' => 1, 'in_progress' => 0, 'done' => 1], $stats);
    }

    public function test_attachment_upload_is_stored_privately(): void
    {
        Storage::fake('local');
        $user = $this->makeUser(unitCode: 'DR');

        $this->actingAs($user)->post('/user/tiket', $this->payload([
            'attachments' => [UploadedFile::fake()->image('foto error.jpg', 800, 600)],
        ]))->assertSessionHasNoErrors();

        $attachment = Ticket::firstOrFail()->attachments()->firstOrFail();
        $this->assertSame('image/jpeg', $attachment->mime_type);
        $this->assertSame('foto error.jpg', $attachment->original_name);
        $this->assertMatchesRegularExpression('#^attachments/\d{4}/\d{2}/[0-9A-Z]{26}\.jpg$#', $attachment->stored_path);
        Storage::disk('local')->assertExists($attachment->stored_path);
        $this->assertFileDoesNotExist(public_path($attachment->stored_path));
    }

    /**
     * Memakai file temp sungguhan: UploadedFile::fake() melaporkan MIME dari nama file,
     * sedangkan upload asli dideteksi dari isi file (finfo) — itulah yang ingin diuji.
     */
    private function realUpload(string $clientName, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $clientName, null, null, true);
    }

    public function test_disallowed_attachment_types_are_rejected(): void
    {
        Storage::fake('local');
        $user = $this->makeUser(unitCode: 'DR');

        $this->actingAs($user)->post('/user/tiket', $this->payload([
            'attachments' => [$this->realUpload('shell.php', '<?php system($_GET["c"]);')],
        ]))->assertSessionHasErrors('attachments.0');

        // Nama berekstensi .jpg tetapi isinya HTML → ditolak berdasarkan isi file.
        $this->actingAs($user)->post('/user/tiket', $this->payload([
            'attachments' => [$this->realUpload('foto.jpg', '<html><script>alert(1)</script></html>')],
        ]))->assertSessionHasErrors('attachments.0');

        // SVG (dapat berisi script) tidak diizinkan.
        $this->actingAs($user)->post('/user/tiket', $this->payload([
            'attachments' => [$this->realUpload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
        ]))->assertSessionHasErrors('attachments.0');

        $this->assertSame(0, Ticket::count());
    }

    public function test_real_png_upload_is_accepted_and_extension_comes_from_content(): void
    {
        Storage::fake('local');
        $user = $this->makeUser(unitCode: 'DR');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        $this->actingAs($user)->post('/user/tiket', $this->payload([
            'attachments' => [$this->realUpload('Screenshot.PNG', $png)],
        ]))->assertSessionHasNoErrors();

        $attachment = Ticket::firstOrFail()->attachments()->firstOrFail();
        $this->assertSame('image/png', $attachment->mime_type);
        $this->assertStringEndsWith('.png', $attachment->stored_path);
    }

    public function test_attachment_size_and_count_limits_follow_settings(): void
    {
        Storage::fake('local');
        $user = $this->makeUser(unitCode: 'DR');
        app(SettingService::class)->set('upload.max_kb', 200);
        app(SettingService::class)->set('upload.max_files', 2);

        $this->actingAs($user)->post('/user/tiket', $this->payload([
            'attachments' => [UploadedFile::fake()->create('besar.pdf', 300, 'application/pdf')],
        ]))->assertSessionHasErrors('attachments.0');

        $this->actingAs($user)->post('/user/tiket', $this->payload([
            'attachments' => array_fill(0, 3, UploadedFile::fake()->image('a.png')),
        ]))->assertSessionHasErrors('attachments');
    }
}
