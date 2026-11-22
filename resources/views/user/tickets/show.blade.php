<x-layouts::app :title="$ticket->ticket_number">
    <div class="mb-4">
        <a href="{{ route('user.tickets.index') }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; Tiket Saya</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card p-5 sm:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-sm font-semibold text-gray-700">{{ $ticket->ticket_number }}</span>
                    <x-status-badge :status="$ticket->status" />
                    <x-priority-badge :priority="$ticket->priority" />
                </div>
                <h1 class="mt-3 text-lg font-semibold text-gray-900 sm:text-xl">{{ $ticket->title }}</h1>
                <div class="mt-4 text-sm leading-relaxed whitespace-pre-line text-gray-700">{{ $ticket->description }}</div>

                @if ($ticket->attachments->isNotEmpty())
                    <div class="mt-5 border-t border-gray-100 pt-4">
                        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Lampiran</p>
                        <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($ticket->attachments as $file)
                                <li class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                    <span class="min-w-0 truncate" title="{{ $file->original_name }}">{{ $file->original_name }}</span>
                                    <span class="flex shrink-0 items-center gap-2 text-xs">
                                        <span class="text-gray-400">{{ $file->humanSize() }}</span>
                                        @if ($file->isImage())
                                            <a href="{{ route('attachments.show', [$file, 'inline' => 1]) }}" target="_blank" rel="noopener" class="text-brand-700 hover:underline">Lihat</a>
                                        @endif
                                        <a href="{{ route('attachments.show', $file) }}" class="text-brand-700 hover:underline">Unduh</a>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="card p-5 sm:p-6">
                <h2 class="text-base font-semibold text-gray-900">Riwayat Aktivitas</h2>
                <ol class="mt-4 space-y-4 border-l border-gray-200 pl-4">
                    @foreach ($ticket->histories as $history)
                        <li class="relative">
                            <span class="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full bg-brand-500 ring-4 ring-white"></span>
                            <p class="text-sm font-medium text-gray-800">{{ $history->label() }}</p>
                            <p class="text-xs text-gray-500">{{ $history->user?->name ?? 'Sistem' }} · {{ $history->created_at->translatedFormat('d M Y H:i') }}</p>
                            @if ($history->note)<p class="mt-1 text-xs text-gray-600">{{ $history->note }}</p>@endif
                        </li>
                    @endforeach
                </ol>
                <p class="mt-5 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">Balasan dari tim IT akan tampil di halaman ini.</p>
            </div>
        </div>

        <aside class="card h-fit p-5 sm:p-6">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-gray-500">Unit</dt><dd class="font-medium text-gray-900">{{ $ticket->unit->label() }}</dd></div>
                <div><dt class="text-gray-500">Kategori</dt><dd class="font-medium text-gray-900">{{ $ticket->category->name }}{{ $ticket->subcategory ? ' / '.$ticket->subcategory->name : '' }}</dd></div>
                <div><dt class="text-gray-500">Dilaporkan</dt><dd class="font-medium text-gray-900">{{ $ticket->created_at->translatedFormat('d M Y H:i') }}</dd></div>
                <div><dt class="text-gray-500">PIC IT</dt><dd class="font-medium text-gray-900">{{ $ticket->assignee?->name ?? 'Belum ditetapkan' }}</dd></div>
                @if ($ticket->source === \App\Enums\TicketSource::QR)
                    <div><dt class="text-gray-500">Sumber</dt><dd class="font-medium text-gray-900">QR Code</dd></div>
                @endif
            </dl>
        </aside>
    </div>
</x-layouts::app>
