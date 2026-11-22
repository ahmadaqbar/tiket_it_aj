<?php

namespace Tests\Feature\Ticket;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use Tests\TestCase;

class ItQueueTest extends TestCase
{
    public function test_only_it_staff_can_access_queue_and_detail(): void
    {
        $owner = $this->makeUser();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($owner)->get('/it/tiket')->assertForbidden();
        $this->actingAs($owner)->get("/it/tiket/{$ticket->public_id}")->assertForbidden();
        $this->actingAs($this->makeIt())->get('/it/tiket')->assertOk()->assertSee($ticket->ticket_number);
        $this->actingAs($this->makeAdmin())->get("/it/tiket/{$ticket->public_id}")->assertOk();
    }

    public function test_queue_shows_tickets_from_all_units(): void
    {
        $dr = $this->makeTicket($this->makeUser(unitCode: 'DR'));
        $ho = $this->makeTicket($this->makeUser(unitCode: 'HO'));
        $sh = $this->makeTicket($this->makeUser(unitCode: 'SH'));

        $this->actingAs($this->makeIt())->get('/it/tiket')
            ->assertSee($dr->ticket_number)->assertSee($ho->ticket_number)->assertSee($sh->ticket_number);
    }

    public function test_filters(): void
    {
        $it = $this->makeIt();
        $budi = $this->makeUser(attributes: ['name' => 'Budi Kasir'], unitCode: 'DR');
        $sari = $this->makeUser(attributes: ['name' => 'Sari Resepsionis'], unitCode: 'SH');
        $a = $this->makeTicket($budi, ['title' => 'Printer macet total', 'priority' => 'URGENT']);
        $b = $this->makeTicket($sari, ['title' => 'WiFi lobby lambat', 'category_id' => $this->category('Network & Internet')->id,
            'subcategory_id' => $this->subcategory('Network & Internet', 'WiFi')->id]);
        $b->forceFill(['status' => 'PENDING', 'assigned_to' => $it->id])->save();

        // Diperiksa lewat data paginator: lonceng notifikasi di layout juga memuat nomor tiket.
        $see = function (string $query, array $visible, array $hidden) use ($it) {
            $numbers = $this->actingAs($it)->get('/it/tiket?'.$query)->assertOk()->viewData('tickets')->pluck('ticket_number');
            foreach ($visible as $t) {
                $this->assertTrue($numbers->contains($t->ticket_number), "{$query}: {$t->ticket_number} harus tampil");
            }
            foreach ($hidden as $t) {
                $this->assertFalse($numbers->contains($t->ticket_number), "{$query}: {$t->ticket_number} tidak boleh tampil");
            }
        };

        $see('unit_id='.$this->unit('DR')->id, [$a], [$b]);
        $see('status=PENDING', [$b], [$a]);
        $see('priority=URGENT', [$a], [$b]);
        $see('category_id='.$this->category('Network & Internet')->id, [$b], [$a]);
        $see('subcategory_id='.$this->subcategory('Network & Internet', 'WiFi')->id, [$b], [$a]);
        $see('reporter=Sari', [$b], [$a]);
        $see('q=macet', [$a], [$b]);
        $see('q='.$b->ticket_number, [$b], [$a]);
        $see('assigned=none', [$a], [$b]);
        $see('assigned=me', [$b], [$a]);
        $see('assigned='.$it->id, [$b], [$a]);
    }

    public function test_date_filter_and_overdue_filter(): void
    {
        $user = $this->makeUser();
        $this->travelTo(now()->subDays(10));
        $old = $this->makeTicket($user, ['priority' => 'URGENT']);
        $this->travelBack();
        $new = $this->makeTicket($user);
        $it = $this->makeIt();

        $this->actingAs($it)->get('/it/tiket?date_from='.now()->toDateString())
            ->assertSee($new->ticket_number)->assertDontSee($old->ticket_number);
        $this->actingAs($it)->get('/it/tiket?overdue=1')
            ->assertSee($old->ticket_number)->assertDontSee($new->ticket_number);
        $this->actingAs($it)->get('/it/tiket?date_from=2026-12-01&date_to=2026-11-01')->assertSessionHasErrors('date_to');
    }

    public function test_sort_by_priority(): void
    {
        $user = $this->makeUser();
        $low = $this->makeTicket($user, ['priority' => 'LOW', 'title' => 'Tiket prioritas rendah']);
        $urgent = $this->makeTicket($user, ['priority' => 'URGENT', 'title' => 'Tiket prioritas mendesak']);

        $this->actingAs($this->makeIt())->get('/it/tiket?sort=priority')
            ->assertSeeInOrder([$urgent->ticket_number, $low->ticket_number]);
    }

    public function test_queue_is_paginated(): void
    {
        $user = $this->makeUser();
        for ($i = 0; $i < 22; $i++) {
            $this->makeTicket($user);
        }

        $response = $this->actingAs($this->makeIt())->get('/it/tiket');
        $this->assertCount(20, $response->viewData('tickets'));
        $this->assertSame(22, $response->viewData('tickets')->total());
    }

    public function test_assignment_and_take(): void
    {
        $it = $this->makeIt(['name' => 'Budi IT']);
        $admin = $this->makeAdmin();
        $ticket = $this->makeTicket($this->makeUser());

        $this->actingAs($it)->post("/it/tiket/{$ticket->public_id}/ambil")->assertSessionHasNoErrors();
        $this->assertSame($it->id, $ticket->fresh()->assigned_to);

        $this->actingAs($admin)->put("/it/tiket/{$ticket->public_id}/pic", ['assigned_to' => $admin->id])->assertSessionHasNoErrors();
        $this->assertSame($admin->id, $ticket->fresh()->assigned_to);

        $this->actingAs($admin)->put("/it/tiket/{$ticket->public_id}/pic", ['assigned_to' => null])->assertSessionHasNoErrors();
        $this->assertNull($ticket->fresh()->assigned_to);

        $this->assertSame(2, $ticket->assignments()->count());
        $this->assertSame(0, $ticket->assignments()->whereNull('unassigned_at')->count());
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'ASSIGNED', 'new_value' => 'Budi IT']);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'UNASSIGNED']);
    }

    public function test_cannot_assign_to_non_it_user(): void
    {
        $ticket = $this->makeTicket($this->makeUser());
        $regular = $this->makeUser();

        $this->actingAs($this->makeIt())->put("/it/tiket/{$ticket->public_id}/pic", ['assigned_to' => $regular->id])
            ->assertSessionHasErrors('assigned_to');
        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_reclassify_priority_recomputes_sla_and_records_history(): void
    {
        $ticket = $this->makeTicket($this->makeUser(), ['priority' => 'LOW']);
        $wifi = $this->subcategory('Network & Internet', 'WiFi');

        $this->actingAs($this->makeIt())->put("/it/tiket/{$ticket->public_id}/klasifikasi", [
            'priority' => 'URGENT', 'category_id' => $wifi->category_id, 'subcategory_id' => $wifi->id,
        ])->assertSessionHasNoErrors();

        $ticket->refresh();
        $urgent = SlaPolicy::where('priority', 'URGENT')->firstOrFail();
        $this->assertSame(TicketPriority::URGENT, $ticket->priority);
        $this->assertSame($urgent->id, $ticket->sla_policy_id);
        $this->assertEquals($ticket->created_at->copy()->addMinutes($urgent->resolution_minutes), $ticket->resolution_due_at);
        $this->assertSame($wifi->id, $ticket->subcategory_id);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'PRIORITY_CHANGED', 'old_value' => 'LOW', 'new_value' => 'URGENT']);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'CATEGORY_CHANGED', 'new_value' => 'Network & Internet / WiFi']);
    }

    public function test_reclassify_rejects_mismatched_subcategory(): void
    {
        $ticket = $this->makeTicket($this->makeUser());

        $this->actingAs($this->makeIt())->put("/it/tiket/{$ticket->public_id}/klasifikasi", [
            'priority' => 'MEDIUM', 'category_id' => $this->category('Hardware')->id,
            'subcategory_id' => $this->subcategory('Network & Internet', 'WiFi')->id,
        ])->assertSessionHasErrors('subcategory_id');
    }

    public function test_ticket_history_shows_full_sequence_for_it(): void
    {
        $it = $this->makeIt();
        $ticket = $this->makeTicket($this->makeUser());
        $this->actingAs($it)->post("/it/tiket/{$ticket->public_id}/ambil");
        $this->actingAs($it)->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'IN_PROGRESS']);
        $this->actingAs($it)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Sedang dicek', 'visibility' => 'INTERNAL']);

        $this->assertSame(['CREATED', 'ASSIGNED', 'STATUS_CHANGED', 'INTERNAL_NOTE'], $ticket->histories()->pluck('action')->all());
        $this->actingAs($it)->get("/it/tiket/{$ticket->public_id}")
            ->assertSeeInOrder(['Tiket dibuat', 'PIC ditetapkan', 'Status diubah', 'Catatan internal ditambahkan']);
    }

    public function test_it_dashboard_lists_unassigned_new_tickets(): void
    {
        $ticket = $this->makeTicket($this->makeUser(), ['title' => 'Belum ada yang pegang']);

        $this->actingAs($this->makeIt())->get('/it/dashboard')->assertSee('Belum ada yang pegang');
    }
}
