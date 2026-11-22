<?php

namespace Tests\Feature\Ticket;

use App\Enums\ReplyVisibility;
use App\Models\TicketReply;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Prioritas tertinggi: catatan internal TIDAK PERNAH terlihat oleh pelapor.
 */
class InternalNotePrivacyTest extends TestCase
{
    public function test_it_can_add_public_reply_and_internal_note(): void
    {
        $it = $this->makeIt();
        $ticket = $this->makeTicket($this->makeUser());

        $this->actingAs($it)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Kami cek ke lokasi.', 'visibility' => 'PUBLIC'])
            ->assertRedirect(route('it.tickets.show', $ticket));
        $this->actingAs($it)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Password router: rahasia123', 'visibility' => 'INTERNAL'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, TicketReply::where('visibility', 'PUBLIC')->count());
        $this->assertSame(1, TicketReply::where('visibility', 'INTERNAL')->count());
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'INTERNAL_NOTE']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.internal_note', 'entity_id' => $ticket->id]);
    }

    public function test_reporter_never_sees_internal_note_or_its_history_entry(): void
    {
        $owner = $this->makeUser();
        $ticket = $this->makeTicket($owner);
        $this->actingAs($this->makeIt())->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Balasan publik untuk pelapor', 'visibility' => 'PUBLIC']);
        $this->actingAs($this->makeIt())->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'RAHASIA-INTERNAL-XYZ', 'visibility' => 'INTERNAL']);
        // Sesi pelapor terpisah dari sesi IT (buang flash message milik IT).
        $this->flushSession();

        $this->actingAs($owner)->get("/user/tiket/{$ticket->public_id}")
            ->assertOk()
            ->assertSee('Balasan publik untuk pelapor')
            ->assertDontSee('RAHASIA-INTERNAL-XYZ')
            ->assertDontSee('Catatan internal ditambahkan')
            ->assertDontSee('Catatan Internal');
    }

    public function test_it_sees_internal_note(): void
    {
        $ticket = $this->makeTicket($this->makeUser());
        $this->actingAs($this->makeIt())->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'RAHASIA-INTERNAL-XYZ', 'visibility' => 'INTERNAL']);

        $this->actingAs($this->makeAdmin())->get("/it/tiket/{$ticket->public_id}")
            ->assertOk()->assertSee('RAHASIA-INTERNAL-XYZ')->assertSee('tidak terlihat pelapor');
    }

    public function test_reporter_cannot_create_internal_note_even_by_crafting_request(): void
    {
        $owner = $this->makeUser();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($owner)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Mencoba internal', 'visibility' => 'INTERNAL'])
            ->assertRedirect(route('user.tickets.show', $ticket));

        $this->assertSame(0, TicketReply::where('visibility', 'INTERNAL')->count());
        $this->assertSame(ReplyVisibility::PUBLIC, TicketReply::firstOrFail()->visibility);
    }

    public function test_internal_note_attachment_is_internal_and_hidden_from_reporter(): void
    {
        Storage::fake('local');
        $owner = $this->makeUser();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($this->makeIt())->post("/tiket/{$ticket->public_id}/balasan", [
            'message' => 'Screenshot konfigurasi', 'visibility' => 'INTERNAL',
            'attachments' => [UploadedFile::fake()->image('config-router.png')],
        ])->assertSessionHasNoErrors();

        $attachment = $ticket->attachments()->firstOrFail();
        $this->assertSame('INTERNAL', $attachment->visibility);
        $this->assertNotNull($attachment->ticket_reply_id);

        $this->actingAs($owner)->get("/lampiran/{$attachment->id}")->assertNotFound();
        $this->actingAs($owner)->get("/user/tiket/{$ticket->public_id}")->assertDontSee('config-router.png');
        $this->actingAs($this->makeIt())->get("/lampiran/{$attachment->id}")->assertOk();
    }

    public function test_reporter_can_reply_with_attachment(): void
    {
        Storage::fake('local');
        $owner = $this->makeUser();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($owner)->post("/tiket/{$ticket->public_id}/balasan", [
            'message' => 'Ini foto terbaru', 'attachments' => [UploadedFile::fake()->image('foto.jpg')],
        ])->assertSessionHasNoErrors();

        $this->assertSame('PUBLIC', $ticket->attachments()->firstOrFail()->visibility);
        $this->actingAs($this->makeIt())->get("/it/tiket/{$ticket->public_id}")->assertSee('Ini foto terbaru')->assertSee('foto.jpg');
    }

    public function test_other_user_cannot_reply_to_ticket(): void
    {
        $ticket = $this->makeTicket($this->makeUser());

        $this->actingAs($this->makeUser())->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Menyusup'])->assertForbidden();
        $this->assertSame(0, TicketReply::count());
    }

    public function test_it_public_reply_sets_first_response_time_but_internal_note_does_not(): void
    {
        $it = $this->makeIt();
        $ticket = $this->makeTicket($this->makeUser());

        $this->actingAs($it)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Catatan', 'visibility' => 'INTERNAL']);
        $this->assertNull($ticket->fresh()->first_response_at);

        $this->actingAs($it)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Kami tangani', 'visibility' => 'PUBLIC']);
        $this->assertNotNull($ticket->fresh()->first_response_at);
    }

    public function test_reporter_reply_does_not_set_first_response(): void
    {
        $owner = $this->makeUser();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($owner)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Tambahan info']);

        $this->assertNull($ticket->fresh()->first_response_at);
    }

    public function test_closed_ticket_accepts_only_internal_notes(): void
    {
        $owner = $this->makeUser();
        $ticket = $this->makeTicket($owner);
        $ticket->forceFill(['status' => 'CLOSED'])->save();

        $this->actingAs($owner)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Masih rusak'])->assertForbidden();
        $this->actingAs($this->makeIt())->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Balasan publik', 'visibility' => 'PUBLIC'])->assertForbidden();
        $this->actingAs($this->makeIt())->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Dokumentasi pasca-penutupan', 'visibility' => 'INTERNAL'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, TicketReply::count());
    }
}
