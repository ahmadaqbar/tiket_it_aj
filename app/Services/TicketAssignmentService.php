<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assignment PIC bersifat opsional; tiket tanpa PIC tetap berada di shared queue.
 */
class TicketAssignmentService
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function assign(Ticket $ticket, ?User $assignee, User $actor, ?string $note = null): Ticket
    {
        if ($assignee && (! $assignee->isItStaff() || ! $assignee->is_active)) {
            throw ValidationException::withMessages(['assigned_to' => 'PIC harus staf IT yang aktif.']);
        }
        if ($ticket->isClosed()) {
            throw ValidationException::withMessages(['assigned_to' => 'Tiket yang sudah ditutup tidak dapat di-assign.']);
        }
        if ($ticket->assigned_to === $assignee?->id) {
            return $ticket;
        }

        return DB::transaction(function () use ($ticket, $assignee, $actor, $note) {
            $previous = $ticket->assignee;

            TicketAssignment::where('ticket_id', $ticket->id)->whereNull('unassigned_at')->update(['unassigned_at' => now()]);
            if ($assignee) {
                TicketAssignment::create([
                    'ticket_id' => $ticket->id, 'assigned_to' => $assignee->id, 'assigned_by' => $actor->id, 'note' => $note,
                ]);
            }

            $ticket->forceFill(['assigned_to' => $assignee?->id])->save();

            $this->tickets->history($ticket, $actor, $assignee ? TicketHistory::ASSIGNED : TicketHistory::UNASSIGNED,
                $previous?->name, $assignee?->name);
            $this->audit->log('ticket.assigned', $ticket, "{$ticket->ticket_number}: PIC ".($assignee?->name ?? 'dilepas'),
                ['assigned_to' => $previous?->id], ['assigned_to' => $assignee?->id]);
            if ($assignee) {
                $this->notifications->assigned($ticket, $assignee, $actor);
            }

            return $ticket;
        });
    }
}
