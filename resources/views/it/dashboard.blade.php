<x-layouts::app title="Dashboard IT">
    <x-page-header title="Dashboard IT" :subtitle="'Halo, '.$user->name.'. Seluruh tiket dari semua unit dikelola di sini.'" />

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-stat-card label="Unit Aktif" :value="$activeUnits" tone="brand" />
        <x-stat-card label="User Aktif" :value="$activeUsers" />
    </div>

    <div class="card mt-6">
        <x-empty-state icon="inbox" message="Antrean tiket bersama (shared queue) akan tersedia setelah Sprint 2–3." />
    </div>
</x-layouts::app>
