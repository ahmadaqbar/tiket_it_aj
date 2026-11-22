<?php

namespace App\Http\Controllers;

use App\Enums\KbStatus;
use App\Enums\KbVisibility;
use App\Enums\ReplyVisibility;
use App\Http\Requests\KbArticleRequest;
use App\Models\Category;
use App\Models\KbArticle;
use App\Models\KbTag;
use App\Models\Ticket;
use App\Services\KnowledgeBaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KnowledgeBaseController extends Controller
{
    public function __construct(private readonly KnowledgeBaseService $kb) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', KbArticle::class);
        $user = $request->user();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'category_id' => ['nullable', 'integer'],
            'tag' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::enum(KbStatus::class)],
            'visibility' => ['nullable', Rule::enum(KbVisibility::class)],
        ]);

        // USER: hanya published + public. IT: semua status (default tanpa arsip) untuk pengelolaan.
        $query = $user->isItStaff()
            ? KbArticle::query()->when($filters['status'] ?? null,
                fn ($q, $s) => $q->where('status', $s),
                fn ($q) => $q->where('status', '!=', KbStatus::ARCHIVED->value))
                ->when($filters['visibility'] ?? null, fn ($q, $v) => $q->where('visibility', $v))
            : KbArticle::query()->readableBy($user);

        $query->with(['category:id,name', 'tags:id,name,slug'])
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['tag'] ?? null, fn ($q, $v) => $q->whereHas('tags', fn ($t) => $t->where('slug', $v)))
            ->latest('updated_at');

        return view('kb.index', [
            'articles' => $this->kb->search($query, $filters['q'] ?? null)->paginate(15)->withQueryString(),
            'filters' => $filters,
            'categories' => Category::ordered()->get(['id', 'name']),
            'tags' => KbTag::query()->whereHas('articles', fn ($q) => $user->isItStaff() ? $q : $q->readableBy($user))->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, KbArticle $article): View
    {
        // 404 (bukan 403) untuk artikel internal/draft agar keberadaannya tidak terungkap ke user.
        abort_unless($request->user()->can('view', $article), 404);
        $this->kb->recordView($article, $request->user());

        return view('kb.show', [
            'article' => $article->load(['category:id,name', 'subcategory:id,name', 'tags', 'author:id,name', 'editor:id,name', 'sourceTicket:id,public_id,ticket_number']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', KbArticle::class);
        $article = new KbArticle(['visibility' => KbVisibility::INTERNAL]);

        // "Jadikan artikel" dari tiket: isi awal dari data tiket (tanpa catatan internal).
        if ($request->filled('from_ticket')) {
            $ticket = Ticket::where('public_id', $request->string('from_ticket'))->firstOrFail();
            $this->authorize('view', $ticket);
            $resolution = $ticket->replies()->where('visibility', ReplyVisibility::PUBLIC->value)
                ->whereHas('author', fn ($q) => $q->whereHas('role', fn ($r) => $r->whereIn('code', ['IT', 'IT_ADMIN'])))
                ->latest('id')->value('message');
            $article->fill([
                'title' => $ticket->title, 'category_id' => $ticket->category_id, 'subcategory_id' => $ticket->subcategory_id,
                'problem' => $ticket->description, 'resolution' => $resolution, 'source_ticket_id' => $ticket->id,
            ]);
        }

        return view('kb.form', $this->formData($article));
    }

    public function store(KbArticleRequest $request): RedirectResponse
    {
        $article = $this->kb->create($request->validated(), $request->user());

        return redirect()->route('kb.show', $article)->with('success', 'Artikel disimpan'.($article->isPublished() ? ' dan dipublikasikan.' : ' sebagai draft.'));
    }

    public function edit(KbArticle $article): View
    {
        $this->authorize('update', $article);

        return view('kb.form', $this->formData($article->load('tags')));
    }

    public function update(KbArticleRequest $request, KbArticle $article): RedirectResponse
    {
        $this->kb->update($article, $request->validated(), $request->user());

        return redirect()->route('kb.show', $article)->with('success', 'Artikel diperbarui.');
    }

    public function transition(Request $request, KbArticle $article, string $action): RedirectResponse
    {
        $this->authorize('update', $article);
        match ($action) {
            'publish' => $this->kb->publish($article, $request->user()),
            'archive' => $this->kb->archive($article, $request->user()),
            'draft' => $this->kb->toDraft($article, $request->user()),
            default => abort(404),
        };

        return back()->with('success', 'Status artikel: '.$article->status->label().'.');
    }

    private function formData(KbArticle $article): array
    {
        return [
            'article' => $article,
            'categories' => Category::ordered()->with('subcategories:id,category_id,name')->get(['id', 'name']),
            'visibilities' => KbVisibility::cases(),
        ];
    }
}
