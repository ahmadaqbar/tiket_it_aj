<?php

namespace App\Http\Controllers\It;

use App\Enums\RoleCode;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\QuickReplyTemplate;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\AuditService;
use App\Services\KnowledgeBaseService;
use App\Services\TicketExportService;
use App\Services\TicketStatusService;
use App\Support\TicketQueueFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Shared ticket queue: seluruh tiket dari semua unit pada satu halaman untuk IT & IT Admin.
 */
class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage', Ticket::class);
        $filter = TicketQueueFilter::fromRequest($request);

        $tickets = $filter->query($request->user())
            ->with(['unit:id,code,name', 'reporter:id,name', 'category:id,name', 'subcategory:id,name', 'assignee:id,name'])
            ->paginate(20)
            ->withQueryString();

        return view('it.tickets.index', [
            'tickets' => $tickets,
            'filter' => $filter,
            ...$this->filterOptions(),
        ]);
    }

    public function show(Request $request, Ticket $ticket, TicketStatusService $status, AttachmentService $attachments, KnowledgeBaseService $kb): View
    {
        $this->authorize('manage', $ticket);

        $ticket->load([
            'unit', 'reporter.units', 'category', 'subcategory', 'assignee:id,name', 'slaPolicy',
            'replies.author:id,name,role_id', 'replies.attachments',
            'attachments' => fn ($q) => $q->whereNull('ticket_reply_id')->orderBy('id'),
            'histories.user:id,name',
            'assignments.assignee:id,name', 'assignments.assigner:id,name',
        ]);

        return view('it.tickets.show', [
            'ticket' => $ticket,
            'allowedStatuses' => $status->allowedFor($ticket),
            'templates' => QuickReplyTemplate::active()->get(['id', 'title', 'message']),
            'itStaff' => $this->itStaff(),
            'categories' => Category::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $ticket->category_id))
                ->ordered()
                ->with(['subcategories' => fn ($q) => $q->where(fn ($s) => $s->where('is_active', true)->orWhere('id', (int) $ticket->subcategory_id))])
                ->get(),
            'priorities' => TicketPriority::cases(),
            'maxKb' => $attachments->maxKilobytes(),
            'maxFiles' => $attachments->maxFiles(),
            'relatedArticles' => $kb->relatedForTicket($ticket, $request->user()),
        ]);
    }

    public function export(Request $request, TicketExportService $exporter, AuditService $audit): BinaryFileResponse
    {
        $this->authorize('manage', Ticket::class);
        $filter = TicketQueueFilter::fromRequest($request);

        $result = $exporter->toXlsx($filter->query($request->user()));
        $audit->log('ticket.exported', description: "Export antrean tiket ({$result['rows']} baris)", newValues: ['filters' => $filter->filters]);

        return response()
            ->download($result['path'], 'tiket-it-'.now()->format('Ymd-His').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    private function filterOptions(): array
    {
        return [
            'units' => Unit::ordered()->get(['id', 'code', 'name']),
            'categories' => Category::ordered()->with('subcategories:id,category_id,name')->get(['id', 'name']),
            'statuses' => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
            'itStaff' => $this->itStaff(),
            'sorts' => TicketQueueFilter::SORTS,
        ];
    }

    private function itStaff()
    {
        return User::query()->active()->withRole(RoleCode::IT, RoleCode::IT_ADMIN)->orderBy('name')->get(['id', 'name', 'role_id']);
    }
}
