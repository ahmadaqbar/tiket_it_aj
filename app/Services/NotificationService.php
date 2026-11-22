<?php

namespace App\Services;

use App\Enums\RoleCode;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Menentukan penerima notifikasi tiket. Pelaku aksi tidak pernah menerima notifikasi atas aksinya sendiri.
 */
class NotificationService
{
    public function ticketCreated(Ticket $ticket, User $actor): void
    {
        $this->send($this->itStaff(), $ticket, TicketActivity::CREATED, $actor);
    }

    public function replied(Ticket $ticket, User $actor): void
    {
        if ($actor->isItStaff()) {
            $this->send(collect([$ticket->reporter]), $ticket, TicketActivity::REPLIED_BY_IT, $actor);
        } else {
            $this->send($this->handlers($ticket), $ticket, TicketActivity::REPLIED_BY_REPORTER, $actor);
        }
    }

    public function statusChanged(Ticket $ticket, ?User $actor): void
    {
        $event = match ($ticket->status->value) {
            'RESOLVED' => TicketActivity::RESOLVED,
            'CLOSED' => TicketActivity::CLOSED,
            default => TicketActivity::STATUS_CHANGED,
        };
        $this->send(collect([$ticket->reporter]), $ticket, $event, $actor);
    }

    public function confirmedByReporter(Ticket $ticket, User $actor): void
    {
        $this->send($ticket->assignee ? collect([$ticket->assignee]) : collect(), $ticket, TicketActivity::CLOSED, $actor);
    }

    public function reopenedByReporter(Ticket $ticket, User $actor): void
    {
        $this->send($this->handlers($ticket), $ticket, TicketActivity::REOPENED, $actor);
    }

    public function assigned(Ticket $ticket, User $assignee, User $actor): void
    {
        $this->send(collect([$assignee]), $ticket, TicketActivity::ASSIGNED, $actor);
    }

    /** PIC bila ada; bila tidak, seluruh staf IT aktif (shared queue). */
    private function handlers(Ticket $ticket): Collection
    {
        $ticket->loadMissing('assignee');

        return $ticket->assignee && $ticket->assignee->is_active ? collect([$ticket->assignee]) : $this->itStaff();
    }

    private function itStaff(): Collection
    {
        return User::query()->active()->withRole(RoleCode::IT, RoleCode::IT_ADMIN)->get();
    }

    private function send(Collection $recipients, Ticket $ticket, string $event, ?User $actor): void
    {
        $recipients = $recipients->filter(fn ($u) => $u && $u->is_active && $u->id !== $actor?->id)->unique('id');
        if ($recipients->isEmpty()) {
            return;
        }
        $ticket->loadMissing('unit:id,code');
        Notification::send($recipients, new TicketActivity($ticket, $event, $actor));
    }
}
