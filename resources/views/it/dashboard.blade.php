<x-layouts::app title="Dashboard IT">
    <x-page-header title="Dashboard IT" :subtitle="'Halo, '.$user->name.'. Seluruh tiket dari semua unit dikelola di sini.'" />

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <x-stat-card label="Total Tiket" :value="$statusCounts->sum()" />
        @foreach (\App\Enums\TicketStatus::cases() as $status)
            <a href="{{ route('it.tickets.index', ['status' => $status->value]) }}" class="block transition hover:opacity-80">
                <x-stat-card :label="$status->label()" :value="$statusCounts[$status->value] ?? 0" />
            </a>
        @endforeach
    </div>
    <div class="mt-3 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('it.tickets.index', ['assigned' => 'me']) }}" class="rounded-full bg-white px-3 py-1 ring-1 ring-gray-200 hover:bg-gray-50">Tiket saya (terbuka): <strong>{{ $myOpen }}</strong></a>
        <a href="{{ route('it.tickets.index', ['overdue' => 1]) }}" @class(['rounded-full px-3 py-1 ring-1', 'bg-red-50 text-red-700 ring-red-200' => $overdue > 0, 'bg-white ring-gray-200' => $overdue === 0])>Melewati SLA: <strong>{{ $overdue }}</strong></a>
    </div>
    <p class="mt-3 text-xs text-gray-500">{{ $activeUnits }} unit aktif · {{ $activeUsers }} user aktif</p>

    <div class="card mt-6">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Tiket Baru Tanpa PIC</h2>
            <a href="{{ route('it.tickets.index', ['status' => 'NEW', 'assigned' => 'none', 'sort' => 'oldest']) }}" class="text-xs font-medium text-brand-700 hover:underline">Lihat semua</a>
        </div>
        @if ($unassignedNew->isEmpty())
            <x-empty-state icon="check" message="Tidak ada tiket baru yang menunggu." />
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($unassignedNew as $t)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <a href="{{ route('it.tickets.show', $t) }}" class="min-w-0 hover:underline">
                            <span class="block truncate text-sm font-medium text-gray-900">{{ $t->title }}</span>
                            <span class="block text-xs text-gray-500"><span class="font-mono">{{ $t->ticket_number }}</span> · {{ $t->reporter?->name }} · {{ $t->created_at->diffForHumans() }}</span>
                        </a>
                        <form method="POST" action="{{ route('it.tickets.take', $t) }}">@csrf<button class="btn-secondary btn-sm" type="submit">Ambil</button></form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts::app>
