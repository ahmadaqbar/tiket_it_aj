<?php

namespace App\Http\Controllers\It;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('it.dashboard', [
            'user' => $request->user(),
            'activeUnits' => Unit::active()->count(),
            'activeUsers' => User::active()->count(),
            'unassignedNew' => Ticket::query()->where('status', TicketStatus::NEW->value)->whereNull('assigned_to')
                ->with(['unit:id,code', 'reporter:id,name'])->oldest()->limit(8)->get(),
            'myOpen' => Ticket::query()->open()->where('assigned_to', $request->user()->id)->count(),
            'overdue' => Ticket::query()->open()->whereNotNull('resolution_due_at')->where('resolution_due_at', '<', now())->count(),
            'statusCounts' => Ticket::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }
}
