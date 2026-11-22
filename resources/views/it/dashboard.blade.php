<x-layouts::app title="Dashboard IT">
    <x-page-header title="Dashboard IT" :subtitle="'Halo, '.$user->name.'. Seluruh tiket dari semua unit dikelola di sini.'" />

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <x-stat-card label="Total Tiket" :value="$statusCounts->sum()" />
        @foreach (\App\Enums\TicketStatus::cases() as $status)
            <x-stat-card :label="$status->label()" :value="$statusCounts[$status->value] ?? 0" />
        @endforeach
    </div>
    <p class="mt-3 text-xs text-gray-500">{{ $activeUnits }} unit aktif · {{ $activeUsers }} user aktif</p>

    <div class="card mt-6">
        <x-empty-state icon="inbox" message="Antrean tiket bersama (shared queue) akan tersedia pada tahap berikutnya (Phase 1c)." />
    </div>
</x-layouts::app>
