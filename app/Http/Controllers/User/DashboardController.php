<?php

namespace App\Http\Controllers\User;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('units');

        // Satu query agregat untuk seluruh kartu ringkasan.
        $counts = Ticket::query()->ownedBy($user)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (TicketStatus ...$statuses) => (int) collect($statuses)->sum(fn ($s) => $counts[$s->value] ?? 0);

        return view('user.dashboard', [
            'user' => $user,
            'stats' => [
                'total' => (int) $counts->sum(),
                'new' => $count(TicketStatus::NEW),
                'in_progress' => $count(TicketStatus::IN_PROGRESS, TicketStatus::PENDING),
                'done' => $count(TicketStatus::RESOLVED, TicketStatus::CLOSED),
            ],
            'recent' => Ticket::query()->ownedBy($user)->with('unit:id,code')->latest()->limit(5)->get(),
        ]);
    }
}
