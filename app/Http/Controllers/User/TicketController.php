<?php

namespace App\Http\Controllers\User;

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\AttachmentService;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Halaman tiket untuk role USER. Semua query di-scope ke tiket milik user sendiri.
 */
class TicketController extends Controller
{
    public function __construct(private readonly TicketService $tickets) {}

    /** Tiket Saya: tiket yang belum ditutup (termasuk Resolved yang menunggu konfirmasi). */
    public function index(Request $request): View
    {
        return $this->listing($request, history: false);
    }

    /** Riwayat: tiket yang sudah Closed. */
    public function history(Request $request): View
    {
        return $this->listing($request, history: true);
    }

    public function create(Request $request, AttachmentService $attachments): View
    {
        $this->authorize('create', Ticket::class);
        $user = $request->user()->load('units');
        $units = $user->activeUnits();

        // Unit dari QR (Phase 1d) diprioritaskan bila termasuk unit user; selain itu unit utama.
        $qrUnitId = (int) $request->session()->get('qr_unit_id');
        $defaultUnitId = $units->contains('id', $qrUnitId) ? $qrUnitId : ($user->primaryUnit()?->is_active ? $user->primaryUnit()->id : $units->first()?->id);

        return view('user.tickets.create', [
            'units' => $units,
            'defaultUnitId' => $defaultUnitId,
            'categories' => Category::active()->ordered()->with(['subcategories' => fn ($q) => $q->active()])->get(),
            'priorities' => TicketPriority::cases(),
            'maxKb' => $attachments->maxKilobytes(),
            'maxFiles' => $attachments->maxFiles(),
        ]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $source = (int) $request->session()->get('qr_unit_id') === (int) $request->validated('unit_id')
            ? TicketSource::QR : TicketSource::WEB;

        $ticket = $this->tickets->create(
            $request->user(),
            $request->safe()->except('attachments'),
            $request->file('attachments', []),
            $source,
        );
        $request->session()->forget('qr_unit_id');

        return redirect()->route('user.tickets.show', $ticket)
            ->with('success', "Laporan berhasil dikirim. Nomor tiket Anda: {$ticket->ticket_number}");
    }

    public function show(Request $request, Ticket $ticket): View
    {
        // 404 (bukan 403) agar keberadaan tiket milik orang lain tidak terungkap.
        abort_unless($request->user()->can('view', $ticket), 404);

        $user = $request->user();
        // Catatan internal, lampiran internal, dan riwayat internal TIDAK PERNAH dimuat untuk pelapor.
        $ticket->load([
            'unit', 'category', 'subcategory', 'assignee:id,name',
            'attachments' => fn ($q) => $q->whereNull('ticket_reply_id')->where('visibility', TicketAttachment::VISIBILITY_PUBLIC)->orderBy('id'),
            'replies' => fn ($q) => $q->visibleTo($user),
            'replies.author:id,name,role_id',
            'replies.attachments' => fn ($q) => $q->where('visibility', TicketAttachment::VISIBILITY_PUBLIC),
            'histories' => fn ($q) => $q->visibleTo($user),
            'histories.user:id,name',
        ]);

        return view('user.tickets.show', ['ticket' => $ticket, 'maxKb' => app(AttachmentService::class)->maxKilobytes(), 'maxFiles' => app(AttachmentService::class)->maxFiles()]);
    }

    private function listing(Request $request, bool $history): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
        ]);

        $tickets = Ticket::query()
            ->ownedBy($request->user())
            ->with(['unit:id,code,name', 'category:id,name'])
            ->when($history, fn ($q) => $q->where('status', TicketStatus::CLOSED->value),
                fn ($q) => $q->where('status', '!=', TicketStatus::CLOSED->value))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->search($filters['q'] ?? null)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('user.tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'history' => $history,
            'statuses' => $history ? [TicketStatus::CLOSED] : array_filter(TicketStatus::cases(), fn ($s) => $s !== TicketStatus::CLOSED),
        ]);
    }
}
