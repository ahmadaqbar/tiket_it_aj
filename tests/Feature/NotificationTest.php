<?php

namespace Tests\Feature;

use App\Livewire\NotificationBell;
use App\Notifications\TicketActivity;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    private function events($user): array
    {
        return $user->fresh()->notifications()->get()->pluck('data.event')->all();
    }

    public function test_new_ticket_notifies_all_active_it_staff_but_not_reporter(): void
    {
        $it = $this->makeIt();
        $admin = $this->makeAdmin();
        $inactiveIt = $this->makeIt(['is_active' => false]);
        $reporter = $this->makeUser();

        $this->makeTicket($reporter);

        $this->assertSame([TicketActivity::CREATED], $this->events($it));
        $this->assertSame([TicketActivity::CREATED], $this->events($admin));
        $this->assertSame([], $this->events($inactiveIt));
        $this->assertSame([], $this->events($reporter));
    }

    public function test_it_public_reply_notifies_reporter_but_internal_note_does_not(): void
    {
        $reporter = $this->makeUser();
        $ticket = $this->makeTicket($reporter);
        $it = $this->makeIt();

        $this->actingAs($it)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Catatan rahasia', 'visibility' => 'INTERNAL']);
        $this->assertSame([], $this->events($reporter));

        $this->actingAs($it)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Kami cek', 'visibility' => 'PUBLIC']);
        $this->assertSame([TicketActivity::REPLIED_BY_IT], $this->events($reporter));
        $this->assertStringNotContainsString('Catatan rahasia', json_encode($reporter->fresh()->notifications->pluck('data')));
    }

    public function test_reporter_reply_notifies_pic_only_when_assigned(): void
    {
        $reporter = $this->makeUser();
        $ticket = $this->makeTicket($reporter);
        $pic = $this->makeIt();
        $other = $this->makeIt();
        $this->actingAs($pic)->post("/it/tiket/{$ticket->public_id}/ambil");
        $pic->notifications()->delete();
        $other->notifications()->delete();

        $this->actingAs($reporter)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Info tambahan']);

        $this->assertSame([TicketActivity::REPLIED_BY_REPORTER], $this->events($pic));
        $this->assertSame([], $this->events($other));
    }

    public function test_status_changes_notify_reporter_once(): void
    {
        $reporter = $this->makeUser();
        $ticket = $this->makeTicket($reporter);
        $it = $this->makeIt();

        $this->actingAs($it)->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'IN_PROGRESS']);
        $this->actingAs($it)->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'RESOLVED', 'message' => 'Sudah diperbaiki']);

        // Pesan penyelesaian tidak menimbulkan notifikasi "balasan" ganda.
        $this->assertEqualsCanonicalizing([TicketActivity::STATUS_CHANGED, TicketActivity::RESOLVED], $this->events($reporter));
    }

    public function test_assignment_notifies_assignee_but_not_self_take(): void
    {
        $ticket = $this->makeTicket($this->makeUser());
        $it = $this->makeIt();
        $admin = $this->makeAdmin();
        $it->notifications()->delete();
        $admin->notifications()->delete();

        $this->actingAs($it)->post("/it/tiket/{$ticket->public_id}/ambil");
        $this->assertSame([], $this->events($it));

        $this->actingAs($it)->put("/it/tiket/{$ticket->public_id}/pic", ['assigned_to' => $admin->id]);
        $this->assertSame([TicketActivity::ASSIGNED], $this->events($admin));
    }

    public function test_reopen_and_confirm_notify_it(): void
    {
        $reporter = $this->makeUser();
        $ticket = $this->makeTicket($reporter);
        $pic = $this->makeIt();
        $this->actingAs($pic)->post("/it/tiket/{$ticket->public_id}/ambil");
        $this->actingAs($pic)->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'RESOLVED', 'message' => 'Selesai']);
        $pic->notifications()->delete();

        $this->actingAs($reporter)->post("/user/tiket/{$ticket->public_id}/buka-kembali", ['reason' => 'Masih error setelah restart']);
        $this->assertSame([TicketActivity::REOPENED], $this->events($pic));

        $this->actingAs($pic)->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'RESOLVED', 'message' => 'Selesai lagi']);
        $pic->notifications()->delete();
        $this->actingAs($reporter)->post("/user/tiket/{$ticket->public_id}/konfirmasi");
        $this->assertSame([TicketActivity::CLOSED], $this->events($pic));
    }

    public function test_opening_notification_marks_read_and_redirects_by_role(): void
    {
        $reporter = $this->makeUser();
        $ticket = $this->makeTicket($reporter);
        $it = $this->makeIt();
        $this->actingAs($it)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Halo']);

        $n = $reporter->fresh()->notifications()->firstOrFail();
        $this->actingAs($reporter)->get("/notifikasi/{$n->id}")->assertRedirect(route('user.tickets.show', $ticket));
        $this->assertNotNull($n->fresh()->read_at);

        $this->actingAs($reporter)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Terima kasih']);
        $itNote = $it->fresh()->notifications()->firstOrFail();
        $this->actingAs($it)->get("/notifikasi/{$itNote->id}")->assertRedirect(route('it.tickets.show', $ticket));
    }

    public function test_user_cannot_open_someone_elses_notification(): void
    {
        $it = $this->makeIt();
        $this->makeTicket($this->makeUser());
        $n = $it->fresh()->notifications()->firstOrFail();

        $this->actingAs($this->makeUser())->get("/notifikasi/{$n->id}")->assertNotFound();
        $this->assertNull($n->fresh()->read_at);
    }

    public function test_read_all_and_index(): void
    {
        $it = $this->makeIt();
        $this->makeTicket($this->makeUser(), ['title' => 'Notif satu']);
        $this->makeTicket($this->makeUser(), ['title' => 'Notif dua']);

        $this->actingAs($it)->get('/notifikasi')->assertOk()->assertSee('Notif satu')->assertSee('Notif dua');
        $this->actingAs($it)->post('/notifikasi/baca-semua');
        $this->assertSame(0, $it->fresh()->unreadNotifications()->count());
    }

    public function test_notification_bell_shows_unread_count(): void
    {
        $it = $this->makeIt();
        $this->makeTicket($this->makeUser(), ['title' => 'Monitor kasir mati']);

        $this->actingAs($it);
        Livewire::test(NotificationBell::class)->assertSee('1')->assertSee('Monitor kasir mati')->assertSee('wire:poll', false);
        $this->get('/it/dashboard')->assertOk()->assertSee('Notifikasi (1 belum dibaca)');
    }
}
