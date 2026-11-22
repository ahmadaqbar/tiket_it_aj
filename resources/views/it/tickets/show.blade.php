@php
    $categoryData = $categories->map(fn ($c) => ['id' => $c->id, 'subs' => $c->subcategories->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()])->values();
    $fmt = fn ($dt) => $dt?->translatedFormat('d M Y H:i') ?? '—';
    $duration = function ($from, $to) {
        if (! $from || ! $to) return null;
        $m = (int) $from->diffInMinutes($to);
        return $m < 60 ? $m.' menit' : ($m < 1440 ? floor($m / 60).' jam '.($m % 60).' menit' : floor($m / 1440).' hari '.floor(($m % 1440) / 60).' jam');
    };
@endphp
<x-layouts::app :title="$ticket->ticket_number">
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ url()->previous() !== url()->current() && str_contains(url()->previous(), '/it/tiket') ? url()->previous() : route('it.tickets.index') }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; Antrean tiket</a>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card p-5 sm:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-sm font-semibold text-gray-700">{{ $ticket->ticket_number }}</span>
                    <x-status-badge :status="$ticket->status" />
                    <x-priority-badge :priority="$ticket->priority" />
                    @if ($ticket->isOverdue())<span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Melewati SLA</span>@endif
                    @if ($ticket->reopened_count)<span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Dibuka kembali {{ $ticket->reopened_count }}×</span>@endif
                </div>
                <h1 class="mt-3 text-lg font-semibold text-gray-900 sm:text-xl">{{ $ticket->title }}</h1>
                <p class="mt-1 text-xs text-gray-500">{{ $ticket->unit->label() }} · {{ $ticket->reporter?->name }} · {{ $fmt($ticket->created_at) }} · via {{ $ticket->source->label() }}</p>
                <div class="mt-4 text-sm leading-relaxed whitespace-pre-line text-gray-700">{{ $ticket->description }}</div>
                @if ($ticket->attachments->isNotEmpty())
                    <div class="mt-5 border-t border-gray-100 pt-4">
                        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Lampiran Laporan</p>
                        <x-ticket.attachment-list :files="$ticket->attachments" class="mt-2" />
                    </div>
                @endif
            </div>

            <div class="card p-5 sm:p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Percakapan & Catatan</h2>
                <x-ticket.conversation :ticket="$ticket" />
                <div class="mt-6 border-t border-gray-100 pt-5">
                    @if ($ticket->isClosed())
                        <p class="mb-3 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">Tiket sudah Closed: hanya catatan internal yang dapat ditambahkan.</p>
                    @endif
                    <x-ticket.reply-form :ticket="$ticket" :templates="$ticket->isClosed() ? collect() : $templates" :can-internal="true" :internal-only="$ticket->isClosed()" :max-kb="$maxKb" :max-files="$maxFiles" />
                </div>
            </div>
        </div>

        <aside class="space-y-6">
            {{-- Status --}}
            @if ($allowedStatuses !== [])
                <form method="POST" action="{{ route('it.tickets.status', $ticket) }}" class="card space-y-3 p-5" x-data="{ status: '{{ old('status', $allowedStatuses[0]->value) }}' }">
                    @csrf @method('PUT')
                    <h2 class="text-sm font-semibold text-gray-900">Ubah Status</h2>
                    <select name="status" x-model="status" class="form-input" aria-label="Status baru">
                        @foreach ($allowedStatuses as $s)
                            <option value="{{ $s->value }}">{{ $s === \App\Enums\TicketStatus::IN_PROGRESS && $ticket->status === \App\Enums\TicketStatus::RESOLVED ? 'Buka kembali (Dalam Proses)' : $s->label() }}</option>
                        @endforeach
                    </select>
                    <textarea name="message" rows="3" maxlength="5000" class="form-input" :required="status === 'RESOLVED'"
                              :placeholder="status === 'RESOLVED' ? 'Catatan penyelesaian untuk pelapor (wajib)…' : 'Pesan untuk pelapor (opsional)…'">{{ old('message') }}</textarea>
                    <p class="form-hint">Pesan dikirim sebagai balasan publik.</p>
                    <button class="btn-primary w-full" type="submit">Simpan Status</button>
                </form>
            @endif

            {{-- PIC --}}
            @unless ($ticket->isClosed())
                <form method="POST" action="{{ route('it.tickets.assign', $ticket) }}" class="card space-y-3 p-5">
                    @csrf @method('PUT')
                    <h2 class="text-sm font-semibold text-gray-900">PIC</h2>
                    <select name="assigned_to" class="form-input" aria-label="PIC">
                        <option value="">— Tanpa PIC (shared queue) —</option>
                        @foreach ($itStaff as $staff)
                            <option value="{{ $staff->id }}" @selected($ticket->assigned_to === $staff->id)>{{ $staff->name }}{{ $staff->id === auth()->id() ? ' (saya)' : '' }}</option>
                        @endforeach
                    </select>
                    <button class="btn-secondary w-full" type="submit">Simpan PIC</button>
                </form>

                {{-- Klasifikasi --}}
                <form method="POST" action="{{ route('it.tickets.reclassify', $ticket) }}" class="card space-y-3 p-5"
                      x-data="{ categoryId: '{{ $ticket->category_id }}', subId: '{{ $ticket->subcategory_id }}', cats: @js($categoryData) }">
                    @csrf @method('PUT')
                    <h2 class="text-sm font-semibold text-gray-900">Klasifikasi</h2>
                    <select name="priority" class="form-input" aria-label="Prioritas">
                        @foreach ($priorities as $p)<option value="{{ $p->value }}" @selected($ticket->priority === $p)>{{ $p->label() }}</option>@endforeach
                    </select>
                    <select name="category_id" class="form-input" aria-label="Kategori" x-model="categoryId" @change="subId = ''">
                        @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    <select name="subcategory_id" class="form-input" aria-label="Subkategori" x-model="subId">
                        <option value="">— Tanpa subkategori —</option>
                        <template x-for="s in (cats.find(c => String(c.id) === String(categoryId))?.subs ?? [])" :key="s.id">
                            <option :value="s.id" x-text="s.name" :selected="String(s.id) === String(subId)"></option>
                        </template>
                    </select>
                    <p class="form-hint">Mengubah prioritas menghitung ulang target SLA.</p>
                    <button class="btn-secondary w-full" type="submit">Simpan Klasifikasi</button>
                </form>
            @endunless

            {{-- Knowledge Base terkait --}}
            <div class="card p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Artikel KB Terkait</h2>
                    <a href="{{ route('kb.create', ['from_ticket' => $ticket->public_id]) }}" class="text-xs text-brand-700 hover:underline">Jadikan artikel</a>
                </div>
                @forelse ($relatedArticles as $kbArticle)
                    <a href="{{ route('kb.show', $kbArticle) }}" class="block rounded-lg px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50">{{ $kbArticle->title }}</a>
                @empty
                    <p class="text-xs text-gray-500">Belum ada artikel yang relevan.</p>
                @endforelse
            </div>

            {{-- Info --}}
            <div class="card p-5">
                <h2 class="mb-3 text-sm font-semibold text-gray-900">Informasi</h2>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Pelapor</dt><dd class="text-right font-medium">{{ $ticket->reporter?->name }}@if ($ticket->reporter?->phone)<span class="block text-xs font-normal text-gray-500">{{ $ticket->reporter->phone }}</span>@endif</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Unit</dt><dd class="text-right font-medium">{{ $ticket->unit->label() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Kategori</dt><dd class="text-right font-medium">{{ $ticket->category->name }}{{ $ticket->subcategory ? ' / '.$ticket->subcategory->name : '' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Target respons</dt><dd class="text-right">{{ $fmt($ticket->response_due_at) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Target selesai</dt><dd @class(['text-right', 'font-semibold text-red-600' => $ticket->isOverdue()])>{{ $fmt($ticket->resolution_due_at) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Respons pertama</dt><dd class="text-right">{{ $fmt($ticket->first_response_at) }}@if ($d = $duration($ticket->created_at, $ticket->first_response_at))<span class="block text-xs text-gray-500">{{ $d }}</span>@endif</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Resolved</dt><dd class="text-right">{{ $fmt($ticket->resolved_at) }}@if ($d = $duration($ticket->created_at, $ticket->resolved_at))<span class="block text-xs text-gray-500">{{ $d }}</span>@endif</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Closed</dt><dd class="text-right">{{ $fmt($ticket->closed_at) }}</dd></div>
                </dl>
            </div>

            <div class="card p-5">
                <h2 class="mb-4 text-sm font-semibold text-gray-900">Riwayat Aktivitas</h2>
                <x-ticket.timeline :histories="$ticket->histories" />
            </div>
        </aside>
    </div>
</x-layouts::app>
