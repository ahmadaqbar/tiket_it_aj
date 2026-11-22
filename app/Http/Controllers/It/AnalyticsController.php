<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Unit;
use App\Services\AnalyticsService;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analytics): View
    {
        $this->authorize('manage', Ticket::class);
        $period = ReportPeriod::fromRequest($request);
        $unitId = $request->integer('unit_id') ?: null;
        $data = $analytics->compute($period, $unitId);

        return view('it.analytics', [
            'a' => $data,
            'recommendations' => $analytics->recommendations($data),
            'period' => $period,
            'units' => Unit::ordered()->get(['id', 'code', 'name']),
            'filters' => $request->only(['period', 'from', 'to', 'month', 'unit_id']),
        ]);
    }
}
