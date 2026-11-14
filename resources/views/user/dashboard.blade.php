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

    @isset($stats)
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-stat-card label="Total Tiket" :value="$stats['total']" />
            <x-stat-card label="Tiket Baru" :value="$stats['new']" tone="blue" />
            <x-stat-card label="Dalam Proses" :value="$stats['in_progress']" tone="amber" />
            <x-stat-card label="Selesai" :value="$stats['done']" tone="green" />
        </div>
    @else
        <div class="card">
            <x-empty-state icon="ticket" message="Modul tiket sedang disiapkan (Sprint 2). Untuk sementara, silakan hubungi Divisi IT secara langsung." />
        </div>
    @endisset
</x-layouts::app>
