<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\QrCodeService;
use App\Services\UnitService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class QrCodeController extends Controller
{
    public function __construct(private readonly QrCodeService $qr) {}

    public function index(): View
    {
        $this->authorize('viewAny', Unit::class);

        return view('admin.qr.index', [
            'units' => Unit::ordered()->get()->map(fn (Unit $u) => ['unit' => $u, 'svg' => $this->qr->svg($u), 'url' => $this->qr->url($u)]),
        ]);
    }

    public function png(Unit $unit): Response
    {
        $this->authorize('update', $unit);

        return response($this->qr->png($unit), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="qr-'.strtolower($unit->code).'.png"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function pdf(Unit $unit): Response
    {
        $this->authorize('update', $unit);

        return Pdf::loadView('admin.qr.poster', [
            'units' => collect([['unit' => $unit, 'png' => $this->qr->pngDataUri($unit)]]),
        ])->setPaper('a5')->download('qr-'.strtolower($unit->code).'.pdf');
    }

    /** Semua unit aktif dalam satu PDF (satu halaman per unit). */
    public function pdfAll(): Response
    {
        $this->authorize('viewAny', Unit::class);
        $units = Unit::active()->ordered()->get()->map(fn (Unit $u) => ['unit' => $u, 'png' => $this->qr->pngDataUri($u)]);

        return Pdf::loadView('admin.qr.poster', ['units' => $units])->setPaper('a5')->download('qr-semua-unit.pdf');
    }

    public function print(Unit $unit): View
    {
        $this->authorize('update', $unit);

        return view('admin.qr.print', ['unit' => $unit, 'svg' => $this->qr->svg($unit), 'url' => $this->qr->url($unit)]);
    }

    public function regenerate(Unit $unit, UnitService $units): RedirectResponse
    {
        $this->authorize('update', $unit);
        $units->regenerateQrToken($unit);

        return back()->with('success', "Token QR {$unit->code} diganti. QR lama tidak berlaku lagi — cetak ulang QR unit ini.");
    }
}
