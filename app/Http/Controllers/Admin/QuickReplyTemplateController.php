<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuickReplyTemplate;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuickReplyTemplateController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    private function validated(Request $request): array
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);

        return $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ], [], ['title' => 'judul template', 'message' => 'isi template']);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('access-admin');
        $template = QuickReplyTemplate::create($this->validated($request) + ['sort_order' => (int) $request->input('sort_order', 0)]);
        $this->audit->log('quick_reply.created', $template, "Template: {$template->title}");

        return back()->with('success', 'Template ditambahkan.');
    }

    public function update(Request $request, QuickReplyTemplate $template): RedirectResponse
    {
        $this->authorize('access-admin');
        $template->update($this->validated($request));
        $this->audit->log('quick_reply.updated', $template, "Template: {$template->title}");

        return back()->with('success', 'Template diperbarui.');
    }

    public function destroy(QuickReplyTemplate $template): RedirectResponse
    {
        $this->authorize('access-admin');
        $this->audit->log('quick_reply.deleted', $template, "Template: {$template->title}");
        $template->delete();

        return back()->with('success', 'Template dihapus.');
    }
}
