<?php

namespace Tests\Feature\Ticket;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\SettingService;
use Tests\TestCase;

class TicketStatusTest extends TestCase
{
    private function setStatus(Ticket $ticket, string $status, array $extra = []): Ticket
    {
        $ticket->forceFill(['status' => $status, ...$extra])->save();

        return $ticket;
    }

    public function test_it_changes_status_and_history_is_recorded(): void
    {
        $it = $this->makeIt();
        $ticket = $this->makeTicket($this->makeUser());

        $this->actingAs($it)->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'IN_PROGRESS'])->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(TicketStatus::IN_PROGRESS, $ticket->status);
        $this->assertNotNull($ticket->first_response_at);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'STATUS_CHANGED', 'old_value' => 'NEW', 'new_value' => 'IN_PROGRESS', 'user_id' => $it->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.status_changed', 'entity_id' => $ticket->id]);
    }

    public function test_resolved_requires_resolution_note_which_becomes_public_reply(): void
    {
        $it = $this->makeIt();
        $ticket = $this->makeTicket($this->makeUser());

        $this->actingAs($it)->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'RESOLVED'])->assertSessionHasErrors('message');
        $this->assertSame(TicketStatus::NEW, $ticket->fresh()->status);

        $this->actingAs($it)->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'RESOLVED', 'message' => 'Cartridge printer diganti.'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(TicketStatus::RESOLVED, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertSame('Cartridge printer diganti.', TicketReply::where('visibility', 'PUBLIC')->firstOrFail()->message);
    }

    public function test_it_can_close_with_message(): void
    {
        $ticket = $this->setStatus($this->makeTicket($this->makeUser()), 'IN_PROGRESS');

        $this->actingAs($this->makeIt())->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'CLOSED', 'message' => 'Tiket telah kami tutup.'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(TicketStatus::CLOSED, $ticket->status);
        $this->assertNotNull($ticket->closed_at);
        $this->assertNotNull($ticket->resolved_at, 'Tiket CLOSED dihitung sebagai selesai');
        $this->assertSame(1, TicketReply::count());
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $it = $this->makeIt();
        $closed = $this->setStatus($this->makeTicket($this->makeUser()), 'CLOSED');
        $this->actingAs($it)->put("/it/tiket/{$closed->public_id}/status", ['status' => 'IN_PROGRESS'])->assertSessionHasErrors('status');
        $this->assertSame(TicketStatus::CLOSED, $closed->fresh()->status);

        $new = $this->makeTicket($this->makeUser());
        $this->actingAs($it)->put("/it/tiket/{$new->public_id}/status", ['status' => 'NEW'])->assertSessionHasErrors('status');
    }

    public function test_user_cannot_change_status_freely(): void
    {
        $owner = $this->makeUser();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($owner)->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'CLOSED'])->assertForbidden();
        $this->actingAs($owner)->post("/user/tiket/{$ticket->public_id}/konfirmasi")->assertForbidden();
        $this->assertSame(TicketStatus::NEW, $ticket->fresh()->status);
    }

    public function test_reporter_confirms_resolution(): void
    {
        $owner = $this->makeUser();
        $ticket = $this->setStatus($this->makeTicket($owner), 'RESOLVED', ['resolved_at' => now()]);

        $this->actingAs($owner)->get("/user/tiket/{$ticket->public_id}")->assertSee('Ya, sudah selesai');
        $this->actingAs($owner)->post("/user/tiket/{$ticket->public_id}/konfirmasi")->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(TicketStatus::CLOSED, $ticket->status);
        $this->assertNotNull($ticket->closed_at);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'CONFIRMED', 'user_id' => $owner->id]);
    }

    public function test_reporter_reopens_resolved_ticket_with_reason(): void
    {
        $owner = $this->makeUser();
        $ticket = $this->setStatus($this->makeTicket($owner), 'RESOLVED', ['resolved_at' => now()]);

        $this->actingAs($owner)->post("/user/tiket/{$ticket->public_id}/buka-kembali", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($owner)->post("/user/tiket/{$ticket->public_id}/buka-kembali", ['reason' => 'Printer masih macet setelah 1 jam'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(TicketStatus::IN_PROGRESS, $ticket->status);
        $this->assertNull($ticket->resolved_at, 'Tiket yang dibuka kembali tidak lagi dihitung selesai');
        $this->assertSame(1, $ticket->reopened_count);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'REOPENED', 'note' => 'Printer masih macet setelah 1 jam']);
        $this->assertStringContainsString('Printer masih macet', TicketReply::firstOrFail()->message);
    }

    public function test_other_user_cannot_confirm_or_reopen(): void
    {
        $ticket = $this->setStatus($this->makeTicket($this->makeUser()), 'RESOLVED', ['resolved_at' => now()]);
        $other = $this->makeUser();

        $this->actingAs($other)->post("/user/tiket/{$ticket->public_id}/konfirmasi")->assertForbidden();
        $this->actingAs($other)->post("/user/tiket/{$ticket->public_id}/buka-kembali", ['reason' => 'iseng saja ya'])->assertForbidden();
        $this->assertSame(TicketStatus::RESOLVED, $ticket->fresh()->status);
    }

    public function test_it_can_reopen_resolved_ticket(): void
    {
        $ticket = $this->setStatus($this->makeTicket($this->makeUser()), 'RESOLVED', ['resolved_at' => now()]);

        $this->actingAs($this->makeIt())->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'IN_PROGRESS'])->assertSessionHasNoErrors();

        $this->assertSame(1, $ticket->fresh()->reopened_count);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'REOPENED']);
    }

    public function test_auto_close_closes_only_stale_resolved_tickets(): void
    {
        app(SettingService::class)->set('ticket.auto_close_days', 3);
        $owner = $this->makeUser();
        $stale = $this->setStatus($this->makeTicket($owner), 'RESOLVED', ['resolved_at' => now()->subDays(4)]);
        $recent = $this->setStatus($this->makeTicket($owner), 'RESOLVED', ['resolved_at' => now()->subDay()]);
        $open = $this->setStatus($this->makeTicket($owner), 'IN_PROGRESS');

        $this->artisan('tickets:auto-close')->expectsOutput('1 tiket ditutup otomatis.')->assertSuccessful();

        $this->assertSame(TicketStatus::CLOSED, $stale->fresh()->status);
        $this->assertSame(TicketStatus::RESOLVED, $recent->fresh()->status);
        $this->assertSame(TicketStatus::IN_PROGRESS, $open->fresh()->status);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $stale->id, 'action' => 'AUTO_CLOSED', 'user_id' => null]);
    }

    public function test_auto_close_can_be_disabled(): void
    {
        app(SettingService::class)->set('ticket.auto_close_days', 0);
        $ticket = $this->setStatus($this->makeTicket($this->makeUser()), 'RESOLVED', ['resolved_at' => now()->subDays(30)]);

        $this->artisan('tickets:auto-close')->assertSuccessful();

        $this->assertSame(TicketStatus::RESOLVED, $ticket->fresh()->status);
    }
}
