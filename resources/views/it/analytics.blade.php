@php
    use App\Services\AnalyticsService as A;
    $s = $a['summary']; $t = $a['times'];
    $statusLabels = collect($s['by_status'])->mapWithKeys(fn ($v, $k) => [\App\Enums\TicketStatus::from($k)->label() => $v]);
    $chartData = ['trend' => $a['trend'], 'byUnit' => $a['by_unit'], 'byCategory' => $a['by_category'], 'byStatus' => $statusLabels, 'byPriority' => $a['by_priority']];
@endphp
<x-layouts::app title="Analytics">
    <x-page-header title="IT Analytics" :subtitle="$a['period']['label'].($a['unit'] ? ' · '.$a['unit']->label() : ' · Semua unit')" />

    <form method="GET" class="card mb-6 p-4">
        <x-period-filter :units="$units" :filters="$filters">
            <div class="flex items-end"><button class="btn-primary w-full" type="submit">Terapkan</button></div>
        </x-period-filter>
        @if ($errors->any())<p class="form-error mt-2">{{ $errors->first() }}</p>@endif
    </form>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <x-stat-card label="Total Tiket" :value="$s['total']" hint="Masuk dalam periode" />
        @foreach ($statusLabels as $label => $value)<x-stat-card :label="$label" :value="$value" />@endforeach
    </div>

    <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Rata-rata Respons" :value="A::formatMinutes($t['avg_response_minutes'])" :hint="$t['responded'].' tiket · SLA '.($t['response_sla_pct'] !== null ? $t['response_sla_pct'].'%' : '—')" tone="brand" />
        <x-stat-card label="Rata-rata Penyelesaian" :value="A::formatMinutes($t['avg_resolution_minutes'])" :hint="$t['resolved'].' tiket · SLA '.($t['resolution_sla_pct'] !== null ? $t['resolution_sla_pct'].'%' : '—')" tone="brand" />
        <x-stat-card label="Selesai dalam Periode" :value="$s['resolved_in_period']" tone="green" />
        <x-stat-card label="Terbuka / Lewat SLA" :value="$s['open_now'].' / '.$s['overdue_now']" hint="Kondisi saat ini" tone="amber" />
    </div>

    @if ($s['total'] === 0 && $s['resolved_in_period'] === 0)
        <div class="card mt-6"><x-empty-state icon="chart" message="Belum ada data tiket pada periode ini." /></div>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="card p-5 lg:col-span-2"><h2 class="mb-3 text-sm font-semibold text-gray-900">Tren Tiket ({{ ['day' => 'harian', 'week' => 'mingguan', 'month' => 'bulanan'][$a['trend']['granularity']] }})</h2><div class="h-64"><canvas id="chart-trend" aria-label="Grafik tren tiket"></canvas></div></div>
            <div class="card p-5"><h2 class="mb-3 text-sm font-semibold text-gray-900">Status</h2><div class="h-64"><canvas id="chart-status" aria-label="Grafik status"></canvas></div></div>
            <div class="card p-5"><h2 class="mb-3 text-sm font-semibold text-gray-900">Tiket per Unit</h2><div class="h-72"><canvas id="chart-unit" aria-label="Grafik per unit"></canvas></div></div>
            <div class="card p-5"><h2 class="mb-3 text-sm font-semibold text-gray-900">Tiket per Kategori</h2><div class="h-72"><canvas id="chart-category" aria-label="Grafik per kategori"></canvas></div></div>
            <div class="card p-5"><h2 class="mb-3 text-sm font-semibold text-gray-900">Tiket per Prioritas</h2><div class="h-72"><canvas id="chart-priority" aria-label="Grafik per prioritas"></canvas></div></div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="card p-5">
                <h2 class="mb-3 text-sm font-semibold text-gray-900">Top 5 Unit</h2>
                <ol class="space-y-2 text-sm">
                    @forelse (array_slice($a['by_unit'], 0, 5) as $i => $u)
                        <li class="flex items-center justify-between"><span><span class="mr-2 text-gray-400">{{ $i + 1 }}.</span>{{ $u['code'] }} — {{ $u['name'] }}</span><span class="font-semibold">{{ $u['total'] }}</span></li>
                    @empty <li class="text-gray-500">—</li> @endforelse
                </ol>
            </div>
            <div class="card p-5 lg:col-span-2">
                <h2 class="mb-3 text-sm font-semibold text-gray-900">Aktivitas Tim IT</h2>
                <div class="overflow-x-auto"><table class="table-base">
                    <thead><tr><th>Staf</th><th>Balasan</th><th>Catatan internal</th><th>Diselesaikan</th><th>PIC terbuka</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($a['it_activity'] as $row)
                            <tr><td>{{ $row['name'] }}</td><td>{{ $row['public_replies'] }}</td><td>{{ $row['internal_notes'] }}</td><td class="font-semibold">{{ $row['resolved'] }}</td><td>{{ $row['open_assigned'] }}</td></tr>
                        @empty <tr><td colspan="5" class="text-gray-500">Belum ada aktivitas.</td></tr> @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>

        <div class="card mt-6 p-5">
            <h2 class="text-sm font-semibold text-gray-900">Isu Berulang <span class="font-normal text-gray-500">(≥ {{ A::RECURRING_MIN }} tiket unit + kategori + subkategori yang sama)</span></h2>
            @if ($a['recurring'] === [])
                <p class="mt-3 text-sm text-gray-500">Tidak ada isu berulang pada periode ini.</p>
            @else
                <div class="mt-3 overflow-x-auto"><table class="table-base">
                    <thead><tr><th>Unit</th><th>Kategori</th><th>Jumlah</th><th>Dibuka kembali</th><th>Terakhir</th><th>Artikel KB</th><th>Saran pencegahan</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($a['recurring'] as $r)
                            <tr>
                                <td class="font-semibold">{{ $r['unit'] }}</td>
                                <td>{{ $r['category'] }}{{ $r['subcategory'] ? ' / '.$r['subcategory'] : '' }}</td>
                                <td class="font-semibold">{{ $r['total'] }}</td><td>{{ $r['reopened'] }}</td>
                                <td class="text-xs whitespace-nowrap">{{ $r['last_at']->translatedFormat('d M Y') }}</td>
                                <td>{!! $r['has_kb'] ? '<span class="text-green-700">Ada</span>' : '<span class="text-amber-700">Belum</span>' !!}</td>
                                <td class="max-w-md text-xs text-gray-600">{{ $r['suggestion'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
    @endif

    <div class="card mt-6 p-5">
        <h2 class="text-sm font-semibold text-gray-900">Rekomendasi Tindak Lanjut</h2>
        <p class="text-xs text-gray-500">Dihasilkan dari aturan berbasis data periode ini (bukan AI).</p>
        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-700">@foreach ($recommendations as $rec)<li>{{ $rec }}</li>@endforeach</ul>
    </div>

    <script type="application/json" id="analytics-data">@json($chartData)</script>
    @push('scripts') @vite('resources/js/charts.js') @endpush
</x-layouts::app>
