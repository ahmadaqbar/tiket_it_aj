<x-layouts::app :title="$ticket->ticket_number">
    <div class="mb-4">
        <a href="{{ $ticket->isClosed() ? route('user.tickets.history') : route('user.tickets.index') }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; {{ $ticket->isClosed() ? 'Riwayat' : 'Tiket Saya' }}</a>
    </div>

    @can('respondToResolution', $ticket)
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 sm:p-5" x-data="{ reopen: {{ $errors->has('reason') ? 'true' : 'false' }} }">
            <p class="font-medium text-green-900">Tim IT menyatakan kendala ini sudah ditangani.</p>
            <p class="mt-1 text-sm text-green-800">Mohon periksa kembali. Apakah kendala sudah benar-benar selesai?</p>
            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                <form method="POST" action="{{ route('user.tickets.confirm', $ticket) }}">
                    @csrf
                    <button class="btn-primary w-full" type="submit">Ya, sudah selesai</button>
                </form>
                <button type="button" class="btn-secondary" @click="reopen = !reopen">Belum, buka kembali</button>
            </div>
            <form x-show="reopen" x-cloak method="POST" action="{{ route('user.tickets.reopen', $ticket) }}" class="mt-4 space-y-2">
                @csrf
                <label for="reason" class="form-label">Apa yang masih bermasalah?</label>
                <textarea id="reason" name="reason" rows="3" required minlength="5" maxlength="1000" class="form-input">{{ old('reason') }}</textarea>
                @error('reason')<p class="form-error">{{ $message }}</p>@enderror
                <button class="btn-secondary" type="submit">Kirim &amp; Buka Kembali</button>
            </form>
            <p class="mt-3 text-xs text-green-700">Tiket akan ditutup otomatis bila tidak ada konfirmasi dalam beberapa hari.</p>
        </div>
    @endcan

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
                        <x-ticket.attachment-list :files="$ticket->attachments" class="mt-2" />
                    </div>
                @endif
            </div>

            <div class="card p-5 sm:p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Percakapan</h2>
                <x-ticket.conversation :ticket="$ticket" />

                @can('reply', $ticket)
                    <div class="mt-6 border-t border-gray-100 pt-5">
                        <x-ticket.reply-form :ticket="$ticket" :max-kb="$maxKb" :max-files="$maxFiles" />
                    </div>
                @else
                    <p class="mt-5 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">Tiket sudah ditutup. Buat laporan baru bila kendala muncul kembali.</p>
                @endcan
            </div>
        </div>

        <aside class="space-y-6">
            <div class="card p-5 sm:p-6">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-gray-500">Unit</dt><dd class="font-medium text-gray-900">{{ $ticket->unit->label() }}</dd></div>
                    <div><dt class="text-gray-500">Kategori</dt><dd class="font-medium text-gray-900">{{ $ticket->category->name }}{{ $ticket->subcategory ? ' / '.$ticket->subcategory->name : '' }}</dd></div>
                    <div><dt class="text-gray-500">Dilaporkan</dt><dd class="font-medium text-gray-900">{{ $ticket->created_at->translatedFormat('d M Y H:i') }}</dd></div>
                    <div><dt class="text-gray-500">PIC IT</dt><dd class="font-medium text-gray-900">{{ $ticket->assignee?->name ?? 'Belum ditetapkan' }}</dd></div>
                    @if ($ticket->source === \App\Enums\TicketSource::QR)
                        <div><dt class="text-gray-500">Sumber</dt><dd class="font-medium text-gray-900">QR Code</dd></div>
                    @endif
                </dl>
            </div>
            <div class="card p-5 sm:p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Riwayat Aktivitas</h2>
                <x-ticket.timeline :histories="$ticket->histories" />
            </div>
        </aside>
    </div>
</x-layouts::app>
