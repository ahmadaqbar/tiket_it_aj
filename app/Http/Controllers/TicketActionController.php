<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Requests\Ticket\AssignRequest;
use App\Http\Requests\Ticket\ChangeStatusRequest;
use App\Http\Requests\Ticket\ReclassifyRequest;
use App\Http\Requests\Ticket\ReopenRequest;
use App\Http\Requests\Ticket\ReplyRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAssignmentService;
use App\Services\TicketReplyService;
use App\Services\TicketService;
use App\Services\TicketStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Aksi pada tiket. Otorisasi di Form Request (Policy) + aturan bisnis di service.
 */
class TicketActionController extends Controller
{
    public function reply(ReplyRequest $request, Ticket $ticket, TicketReplyService $replies): RedirectResponse
    {
        $visibility = $request->visibility();
        $replies->add($ticket, $request->user(), $request->validated('message'), $visibility, $request->file('attachments', []));

        return $this->backToTicket($request, $ticket)->with('success', $visibility->label().' terkirim.');
    }

    public function status(ChangeStatusRequest $request, Ticket $ticket, TicketStatusService $status): RedirectResponse
    {
        $to = TicketStatus::from($request->validated('status'));
        $status->change($ticket, $to, $request->user(), $request->validated('message'));

        return $this->backToTicket($request, $ticket)->with('success', "Status diubah menjadi {$to->label()}.");
    }

    public function assign(AssignRequest $request, Ticket $ticket, TicketAssignmentService $assignments): RedirectResponse
    {
        $assignee = $request->filled('assigned_to') ? User::findOrFail($request->integer('assigned_to')) : null;
        $assignments->assign($ticket, $assignee, $request->user(), $request->validated('note'));

        return $this->backToTicket($request, $ticket)->with('success', $assignee ? "PIC ditetapkan: {$assignee->name}." : 'PIC dilepas.');
    }

    /** Ambil tiket tanpa PIC untuk diri sendiri (dari antrean). */
    public function take(Request $request, Ticket $ticket, TicketAssignmentService $assignments): RedirectResponse
    {
        $this->authorize('manage', $ticket);
        $assignments->assign($ticket, $request->user(), $request->user());

        return back()->with('success', "{$ticket->ticket_number} sekarang ditangani oleh Anda.");
    }

    public function reclassify(ReclassifyRequest $request, Ticket $ticket, TicketService $tickets): RedirectResponse
    {
        $tickets->reclassify($ticket, $request->user(), TicketPriority::from($request->validated('priority')),
            (int) $request->validated('category_id'), $request->validated('subcategory_id') !== null ? (int) $request->validated('subcategory_id') : null);

        return $this->backToTicket($request, $ticket)->with('success', 'Klasifikasi tiket diperbarui.');
    }

    public function confirm(Request $request, Ticket $ticket, TicketStatusService $status): RedirectResponse
    {
        $this->authorize('respondToResolution', $ticket);
        $status->confirm($ticket, $request->user());

        return $this->backToTicket($request, $ticket)->with('success', 'Terima kasih. Tiket telah ditutup.');
    }

    public function reopen(ReopenRequest $request, Ticket $ticket, TicketStatusService $status): RedirectResponse
    {
        $status->reopen($ticket, $request->user(), $request->validated('reason'));

        return $this->backToTicket($request, $ticket)->with('success', 'Tiket dibuka kembali dan dikembalikan ke tim IT.');
    }

    private function backToTicket(Request $request, Ticket $ticket): RedirectResponse
    {
        return redirect()->route($request->user()->isItStaff() ? 'it.tickets.show' : 'user.tickets.show', $ticket);
    }
}
