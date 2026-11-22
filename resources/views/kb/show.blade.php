@php $isIt = auth()->user()->isItStaff(); @endphp
<x-layouts::app :title="$article->title">
    <div class="mb-4"><a href="{{ route('kb.index') }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; {{ $isIt ? 'Knowledge Base' : 'Pusat Bantuan' }}</a></div>

    <div class="grid gap-6 lg:grid-cols-3">
        <article class="card space-y-6 p-5 sm:p-7 lg:col-span-2">
            <header>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-gray-500">{{ $article->category?->name }}{{ $article->subcategory ? ' / '.$article->subcategory->name : '' }}</span>
                    @if ($isIt)
                        <span class="inline-flex rounded-full px-2 py-0.5 font-medium ring-1 ring-inset {{ $article->status->badgeClass() }}">{{ $article->status->label() }}</span>
                        <span @class(['rounded-full px-2 py-0.5', 'bg-sky-50 text-sky-700' => $article->isPublic(), 'bg-gray-100 text-gray-600' => ! $article->isPublic()])>{{ $article->visibility->label() }}</span>
                    @endif
                </div>
                <h1 class="mt-2 text-xl font-semibold text-gray-900 sm:text-2xl">{{ $article->title }}</h1>
            </header>

            @foreach ([['Masalah', $article->problem], ['Gejala', $article->symptoms], ['Akar Masalah', $article->root_cause], ['Langkah Troubleshooting', $article->steps], ['Resolusi', $article->resolution]] as [$heading, $body])
                @if (filled($body))
                    <section>
                        <h2 class="text-sm font-semibold tracking-wide text-gray-500 uppercase">{{ $heading }}</h2>
                        <div class="mt-2 text-sm leading-relaxed whitespace-pre-line text-gray-800">{{ $body }}</div>
                    </section>
                @endif
            @endforeach

            @if ($article->tags->isNotEmpty())
                <div class="flex flex-wrap gap-1.5 border-t border-gray-100 pt-4 text-xs">
                    @foreach ($article->tags as $tag)<a href="{{ route('kb.index', ['tag' => $tag->slug]) }}" class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-600 hover:bg-gray-200">#{{ $tag->name }}</a>@endforeach
                </div>
            @endif
        </article>

        <aside class="space-y-4">
            <div class="card p-5 text-sm">
                <dl class="space-y-2">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Diperbarui</dt><dd>{{ $article->updated_at->translatedFormat('d M Y') }}</dd></div>
                    @if ($isIt)
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">Penulis</dt><dd>{{ $article->author?->name }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">Diubah oleh</dt><dd>{{ $article->editor?->name ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">Dilihat</dt><dd>{{ $article->view_count }}×</dd></div>
                        @if ($article->sourceTicket)
                            <div class="flex justify-between gap-2"><dt class="text-gray-500">Dari tiket</dt><dd><a class="font-mono text-xs text-brand-700 hover:underline" href="{{ route('it.tickets.show', $article->sourceTicket) }}">{{ $article->sourceTicket->ticket_number }}</a></dd></div>
                        @endif
                    @endif
                </dl>
            </div>
            @can('update', $article)
                <div class="card space-y-2 p-5">
                    <a href="{{ route('kb.edit', $article) }}" class="btn-secondary w-full"><x-icon name="pencil" class="h-4 w-4" /> Ubah Artikel</a>
                    @foreach (['publish' => 'Publikasikan', 'draft' => 'Kembalikan ke Draft', 'archive' => 'Arsipkan'] as $action => $label)
                        @continue(($action === 'publish' && $article->isPublished()) || ($action === 'draft' && $article->status === \App\Enums\KbStatus::DRAFT) || ($action === 'archive' && $article->status === \App\Enums\KbStatus::ARCHIVED))
                        <form method="POST" action="{{ route('kb.transition', [$article, $action]) }}">@csrf
                            <button type="submit" @class(['w-full', 'btn-primary' => $action === 'publish', 'btn-secondary' => $action !== 'publish'])>{{ $label }}</button>
                        </form>
                    @endforeach
                </div>
            @endcan
            @unless ($isIt)
                <div class="card p-5 text-sm text-gray-600">Masih bermasalah? <a href="{{ route('user.tickets.create') }}" class="font-medium text-brand-700 hover:underline">Buat laporan</a> ke Tim IT.</div>
            @endunless
        </aside>
    </div>
</x-layouts::app>
