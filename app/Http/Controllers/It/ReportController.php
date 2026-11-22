<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(): View
    {
        $this->authorize('manage', Ticket::class);

        return view('it.reports.index', [
            'units' => Unit::ordered()->get(['id', 'code', 'name']),
            'presets' => ReportPeriod::PRESETS,
            'lastMonth' => now()->subMonthNoOverflow()->format('Y-m'),
        ]);
    }

    public function download(Request $request, ReportService $reports, AuditService $audit): Response
    {
        $this->authorize('manage', Ticket::class);
        $request->validate([
            'type' => ['required', Rule::in(['operational', 'management'])],
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
        ]);
        $period = ReportPeriod::fromRequest($request, 'last_month');
        $unitId = $request->integer('unit_id') ?: null;
        $type = $request->string('type')->toString();
        $format = $request->string('format')->toString();
        $name = 'laporan-'.($type === 'operational' ? 'operasional' : 'manajemen').'-'.$period->start->format('Ymd').'-'.$period->end->format('Ymd').'.'.$format;

        $audit->log('report.generated', description: "Laporan {$type} ({$format}) {$period->label()}", newValues: ['type' => $type, 'format' => $format, 'unit_id' => $unitId] + $period->toArray());

        if ($format === 'pdf') {
            $pdf = $type === 'operational' ? $reports->operationalPdf($period, $unitId) : $reports->managementPdf($period, $unitId);

            return $pdf->download($name);
        }

        $path = $type === 'operational' ? $reports->operationalXlsx($period, $unitId) : $reports->managementXlsx($period, $unitId);

        return response()->download($path, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }
}
