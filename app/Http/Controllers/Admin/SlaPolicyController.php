<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SlaPolicyRequest;
use App\Models\SlaPolicy;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * SLA per prioritas. Perubahan hanya berlaku untuk tiket baru (tiket lama menyimpan due date sendiri).
 */
class SlaPolicyController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(): View
    {
        $this->authorize('access-admin');

        return view('admin.sla.index', [
            'policies' => SlaPolicy::all()->sortByDesc(fn (SlaPolicy $p) => $p->priority->weight())->values(),
        ]);
    }

    public function update(SlaPolicyRequest $request, SlaPolicy $slaPolicy): RedirectResponse
    {
        $fields = ['response_minutes', 'resolution_minutes', 'is_active'];
        $before = Arr::only($slaPolicy->getAttributes(), $fields);
        $slaPolicy->fill($request->validated())->save();
        $this->audit->logChanges('sla.updated', $slaPolicy, $before);

        return back()->with('success', "SLA {$slaPolicy->priority->label()} diperbarui.");
    }
}
