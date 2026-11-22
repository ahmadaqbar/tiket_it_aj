<?php

namespace Tests\Feature\Ticket;

use App\Models\TicketAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketPrivacyTest extends TestCase
{
    public function test_user_can_view_own_ticket(): void
    {
        $owner = $this->makeUser(unitCode: 'DR');
        $ticket = $this->makeTicket($owner);

        $this->actingAs($owner)->get("/user/tiket/{$ticket->public_id}")
            ->assertOk()->assertSee($ticket->ticket_number)->assertSee($ticket->title);
    }

    public function test_user_cannot_view_another_users_ticket_even_in_same_unit(): void
    {
        $ticket = $this->makeTicket($this->makeUser(unitCode: 'DR'));
        $other = $this->makeUser(unitCode: 'DR');

        $this->actingAs($other)->get("/user/tiket/{$ticket->public_id}")->assertNotFound();
    }

    public function test_numeric_id_cannot_be_used_in_url(): void
    {
        $owner = $this->makeUser(unitCode: 'DR');
        $ticket = $this->makeTicket($owner);

        $this->actingAs($owner)->get("/user/tiket/{$ticket->id}")->assertNotFound();
    }

    public function test_ticket_lists_only_contain_own_tickets(): void
    {
        $owner = $this->makeUser(unitCode: 'DR');
        $mine = $this->makeTicket($owner, ['title' => 'Tiket milik saya sendiri']);
        $theirs = $this->makeTicket($this->makeUser(unitCode: 'DR'), ['title' => 'Tiket rahasia orang lain']);

        $this->actingAs($owner)->get('/user/tiket')->assertSee($mine->title)->assertDontSee($theirs->title);
        $this->actingAs($owner)->get('/user/tiket?q=rahasia')->assertDontSee($theirs->title);
        $this->actingAs($owner)->get('/user/dashboard')->assertDontSee($theirs->title);
    }

    public function test_history_page_lists_only_closed_tickets(): void
    {
        $owner = $this->makeUser(unitCode: 'DR');
        $open = $this->makeTicket($owner, ['title' => 'Tiket masih berjalan']);
        $closed = $this->makeTicket($owner, ['title' => 'Tiket sudah ditutup']);
        $closed->forceFill(['status' => 'CLOSED'])->save();

        $this->actingAs($owner)->get('/user/tiket/riwayat')->assertSee($closed->title)->assertDontSee($open->title);
        $this->actingAs($owner)->get('/user/tiket')->assertSee($open->title)->assertDontSee($closed->title);
    }

    public function test_attachment_download_is_restricted_to_owner_and_it(): void
    {
        Storage::fake('local');
        $owner = $this->makeUser(unitCode: 'DR');
        $ticket = $this->makeTicket($owner, files: [UploadedFile::fake()->image('bukti.png')]);
        $attachment = $ticket->attachments()->firstOrFail();

        $this->actingAs($owner)->get("/lampiran/{$attachment->id}")->assertOk()->assertDownload('bukti.png');
        $this->actingAs($this->makeIt())->get("/lampiran/{$attachment->id}")->assertOk();
        $this->actingAs($this->makeAdmin())->get("/lampiran/{$attachment->id}")->assertOk();
        $this->actingAs($this->makeUser(unitCode: 'DR'))->get("/lampiran/{$attachment->id}")->assertNotFound();
        auth()->logout();
        $this->get("/lampiran/{$attachment->id}")->assertRedirect('/login');
    }

    public function test_internal_attachment_is_hidden_from_ticket_owner(): void
    {
        Storage::fake('local');
        $owner = $this->makeUser(unitCode: 'DR');
        $ticket = $this->makeTicket($owner, files: [UploadedFile::fake()->image('internal.png')]);
        $attachment = $ticket->attachments()->firstOrFail();
        $attachment->update(['visibility' => TicketAttachment::VISIBILITY_INTERNAL]);

        $this->actingAs($owner)->get("/lampiran/{$attachment->id}")->assertNotFound();
        $this->actingAs($owner)->get("/user/tiket/{$ticket->public_id}")->assertDontSee('internal.png');
        $this->actingAs($this->makeIt())->get("/lampiran/{$attachment->id}")->assertOk();
    }

    public function test_attachment_is_served_with_safe_headers(): void
    {
        Storage::fake('local');
        $owner = $this->makeUser(unitCode: 'DR');
        $ticket = $this->makeTicket($owner, files: [UploadedFile::fake()->image('bukti.png')]);
        $attachment = $ticket->attachments()->firstOrFail();

        $this->actingAs($owner)->get("/lampiran/{$attachment->id}?inline=1")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/png');
    }
}
