@php $isIt = auth()->user()->isItStaff(); @endphp
<x-layouts::app :title="$isIt ? 'Knowledge Base' : 'Bantuan'">
    <x-page-header :title="$isIt ? 'Knowledge Base' : 'Pusat Bantuan'"
                   :subtitle="$isIt ? 'Dokumentasi troubleshooting IT. Artikel Published menjadi referensi tim dan (bila diaktifkan) konteks AI.' : 'Panduan mengatasi kendala IT yang sering terjadi.'">
        @if ($isIt)
            <x-slot:actions>
                @if (Route::has('kb.ask'))<a href="{{ route('kb.ask') }}" class="btn-secondary"><x-icon name="sparkle" class="h-4 w-4" /> Tanya AI</a>@endif
                <a href="{{ route('kb.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Artikel Baru</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <form method="GET" class="card mb-4 grid gap-2 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari masalah, gejala, atau solusi…" class="form-input lg:col-span-2" aria-label="Cari">
        <select name="category_id" class="form-input" aria-label="Kategori">
            <option value="">Semua kategori</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category_id'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
        @if ($isIt)
            <select name="status" class="form-input" aria-label="Status">
                <option value="">Draft & Published</option>
                @foreach (\App\Enums\KbStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach
            </select>
            <select name="visibility" class="form-input" aria-label="Visibilitas">
                <option value="">Semua visibilitas</option>
                @foreach (\App\Enums\KbVisibility::cases() as $v)<option value="{{ $v->value }}" @selected(($filters['visibility'] ?? '') === $v->value)>{{ $v->label() }}</option>@endforeach
            </select>
        @endif
        <button class="btn-secondary" type="submit"><x-icon name="search" class="h-4 w-4" /> Cari</button>
    </form>

    @if ($tags->isNotEmpty())
        <div class="mb-4 flex flex-wrap gap-1.5 text-xs">
            @foreach ($tags as $tag)
                <a href="{{ route('kb.index', ['tag' => $tag->slug]) }}" @class(['rounded-full px-2.5 py-1 ring-1', 'bg-brand-700 text-white ring-brand-700' => ($filters['tag'] ?? '') === $tag->slug, 'bg-white text-gray-600 ring-gray-200 hover:bg-gray-50' => ($filters['tag'] ?? '') !== $tag->slug])>#{{ $tag->name }}</a>
            @endforeach
        </div>
    @endif

    @if ($articles->isEmpty())
        <div class="card"><x-empty-state icon="book" :message="($filters['q'] ?? null) ? 'Tidak ada artikel yang cocok.' : 'Belum ada artikel.'" /></div>
    @else
        <ul class="space-y-3">
            @foreach ($articles as $article)
                <li>
                    <a href="{{ route('kb.show', $article) }}" class="card block p-4 transition hover:border-brand-200">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-gray-900">{{ $article->title }}</span>
                            @if ($isIt)
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $article->status->badgeClass() }}">{{ $article->status->label() }}</span>
                                <span @class(['rounded-full px-2 py-0.5 text-xs', 'bg-sky-50 text-sky-700' => $article->isPublic(), 'bg-gray-100 text-gray-600' => ! $article->isPublic()])>{{ $article->visibility->label() }}</span>
                            @endif
                        </div>
                        <p class="mt-1 line-clamp-2 text-sm text-gray-600">{{ $article->problem }}</p>
                        <p class="mt-1.5 text-xs text-gray-500">
                            {{ $article->category?->name ?? 'Tanpa kategori' }}
                            @foreach ($article->tags as $tag) · #{{ $tag->name }}@endforeach
                        </p>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $articles->links() }}</div>
    @endif
</x-layouts::app>
