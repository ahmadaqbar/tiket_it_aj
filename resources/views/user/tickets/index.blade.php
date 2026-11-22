<x-layouts::app :title="$history ? 'Riwayat' : 'Tiket Saya'">
    <x-page-header :title="$history ? 'Riwayat Tiket' : 'Tiket Saya'"
                   :subtitle="$history ? 'Tiket yang sudah ditutup.' : 'Tiket yang masih berjalan atau menunggu konfirmasi Anda.'">
        <x-slot:actions>
            <a href="{{ route('user.tickets.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Buat Laporan</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4 flex flex-col gap-2 sm:flex-row">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nomor tiket atau judul…" class="form-input sm:max-w-xs" aria-label="Cari">
        @unless ($history)
            <select name="status" class="form-input sm:max-w-[12rem]" aria-label="Status">
                <option value="">Semua status</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        @endunless
        <button class="btn-secondary" type="submit"><x-icon name="search" class="h-4 w-4" /> Cari</button>
    </form>

    @if ($tickets->isEmpty())
        <div class="card"><x-empty-state icon="ticket" :message="$history ? 'Belum ada tiket yang ditutup.' : 'Belum ada tiket aktif.'" /></div>
    @else
        <ul class="space-y-3">
            @foreach ($tickets as $ticket)
                <li>
                    <a href="{{ route('user.tickets.show', $ticket) }}" class="card block p-4 transition hover:border-brand-200 hover:shadow">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-gray-500">{{ $ticket->ticket_number }}</span>
                            <x-status-badge :status="$ticket->status" />
                            <x-priority-badge :priority="$ticket->priority" />
                        </div>
                        <p class="mt-1.5 font-medium text-gray-900">{{ $ticket->title }}</p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $ticket->unit->code }} · {{ $ticket->category->name }} · {{ $ticket->created_at->translatedFormat('d M Y H:i') }}
                        </p>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $tickets->links() }}</div>
    @endif
</x-layouts::app>
