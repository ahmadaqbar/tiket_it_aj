@php $primary = $user->primaryUnit(); @endphp
<x-layouts::app title="Dashboard">
    <div class="mb-6 rounded-xl bg-brand-800 p-5 text-white sm:p-6">
        <p class="text-sm text-brand-100">Selamat datang,</p>
        <h1 class="mt-1 text-xl font-semibold sm:text-2xl">{{ $user->name }}</h1>
        <p class="mt-2 text-sm text-brand-100">
            <x-icon name="building" class="mr-1 inline h-4 w-4" />
            {{ $primary?->label() ?? 'Unit belum ditetapkan' }}
            @if ($user->units->count() > 1)
                <span class="text-brand-200">(+{{ $user->units->count() - 1 }} unit lain)</span>
            @endif
        </p>
        @if (Route::has('user.tickets.create'))
            <a href="{{ route('user.tickets.create') }}" class="btn mt-5 bg-white text-brand-800 hover:bg-brand-50">
                <x-icon name="plus" /> Buat Laporan
            </a>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-stat-card label="Total Tiket" :value="$stats['total']" />
        <x-stat-card label="Tiket Baru" :value="$stats['new']" tone="blue" />
        <x-stat-card label="Dalam Proses" :value="$stats['in_progress']" tone="amber" hint="Termasuk pending" />
        <x-stat-card label="Selesai" :value="$stats['done']" tone="green" hint="Resolved & closed" />
    </div>

    <div class="card mt-6">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Tiket Terbaru</h2>
            <a href="{{ route('user.tickets.index') }}" class="text-xs font-medium text-brand-700 hover:underline">Lihat semua</a>
        </div>
        @if ($recent->isEmpty())
            <x-empty-state icon="ticket" message="Belum ada laporan. Tekan Buat Laporan bila ada kendala IT." />
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($recent as $ticket)
                    <li>
                        <a href="{{ route('user.tickets.show', $ticket) }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-gray-900">{{ $ticket->title }}</span>
                                <span class="block font-mono text-xs text-gray-500">{{ $ticket->ticket_number }} · {{ $ticket->created_at->diffForHumans() }}</span>
                            </span>
                            <x-status-badge :status="$ticket->status" class="shrink-0" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    @php $contact = \App\Support\Branding::contact(); @endphp
    @if (array_filter($contact))
        <div class="card mt-6 p-5 text-sm">
            <h2 class="font-semibold text-gray-900">Kontak {{ \App\Support\Branding::supportTeam() }}</h2>
            <dl class="mt-2 space-y-1 text-gray-600">
                @if ($contact['phone'])<div>Telepon/WA: <span class="font-medium text-gray-900">{{ $contact['phone'] }}</span></div>@endif
                @if ($contact['email'])<div>Email: <span class="font-medium text-gray-900">{{ $contact['email'] }}</span></div>@endif
                @if ($contact['address'])<div>Lokasi: {{ $contact['address'] }}</div>@endif
            </dl>
        </div>
    @endif
</x-layouts::app>
