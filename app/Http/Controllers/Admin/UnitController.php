<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UnitRequest;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function __construct(private readonly UnitService $units) {}

    public function index(): View
    {
        $this->authorize('viewAny', Unit::class);

        return view('admin.units.index', [
            'units' => Unit::query()->withCount('users')->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Unit::class);

        return view('admin.units.form', ['unit' => new Unit(['is_active' => true]), 'codeEditable' => true]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        $unit = $this->units->create($request->validated());

        return redirect()->route('admin.units.index')->with('success', "Unit {$unit->code} berhasil ditambahkan.");
    }

    public function edit(Unit $unit): View
    {
        $this->authorize('update', $unit);

        return view('admin.units.form', ['unit' => $unit, 'codeEditable' => $this->units->codeIsEditable($unit)]);
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $this->units->update($unit, $request->validated());

        return redirect()->route('admin.units.index')->with('success', "Unit {$unit->code} berhasil diperbarui.");
    }
}
