@extends('reports.pdf.layout')
@php
    use App\Services\AnalyticsService as A;
    $s = $a['summary']; $t = $a['times'];
    $max = fn (array $rows) => max(1, collect($rows)->max('total') ?? 1);
@endphp
@section('content')
    <h1>Laporan Manajemen Layanan IT</h1>
    <p class="muted">{{ $organization }} · Periode {{ $a['period']['label'] }} · {{ $a['unit']?->label() ?? 'Semua unit' }}</p>

    <h2>Ringkasan</h2>
    <table class="grid"><tr>
        <td class="card"><div class="muted">Tiket masuk</div><div class="big">{{ $s['total'] }}</div></td>
        <td class="card"><div class="muted">Selesai dalam periode</div><div class="big">{{ $s['resolved_in_period'] }}</div></td>
        <td class="card"><div class="muted">Pending</div><div class="big">{{ $s['by_status']['PENDING'] }}</div></td>
        <td class="card"><div class="muted">Terbuka saat ini</div><div class="big">{{ $s['open_now'] }}</div></td>
        <td class="card"><div class="muted">Rata-rata respons</div><div class="big">{{ A::formatMinutes($t['avg_response_minutes']) }}</div></td>
        <td class="card"><div class="muted">Rata-rata penyelesaian</div><div class="big">{{ A::formatMinutes($t['avg_resolution_minutes']) }}</div></td>
    </tr></table>
    <p>Status tiket periode ini: @foreach ($s['by_status'] as $k => $v){{ \App\Enums\TicketStatus::from($k)->label() }} {{ $v }}@if (! $loop->last) · @endif @endforeach.
       SLA respons tercapai {{ $t['response_sla_pct'] ?? '—' }}%, SLA penyelesaian tercapai {{ $t['resolution_sla_pct'] ?? '—' }}%. Dibuka kembali: {{ $s['reopened'] }}.</p>

    <h2>Tren Tiket</h2>
    <table><thead><tr><th>Periode</th><th class="num">Masuk</th><th style="width:45%"></th><th class="num">Selesai</th></tr></thead><tbody>
        @php $tmax = max(1, max($a['trend']['created'] ?: [1])); @endphp
        @foreach ($a['trend']['labels'] as $i => $label)
            <tr><td>{{ $label }}</td><td class="num">{{ $a['trend']['created'][$i] }}</td>
                <td><div class="barbg"><div class="bar" style="width: {{ round(100 * $a['trend']['created'][$i] / $tmax) }}%"></div></div></td>
                <td class="num">{{ $a['trend']['resolved'][$i] }}</td></tr>
        @endforeach
    </tbody></table>

    <h2>Statistik per Unit</h2>
    <table><thead><tr><th>Unit</th><th class="num">Tiket</th><th style="width:55%"></th></tr></thead><tbody>
        @forelse ($a['by_unit'] as $u)<tr><td>{{ $u['code'] }} — {{ $u['name'] }}</td><td class="num">{{ $u['total'] }}</td><td><div class="barbg"><div class="bar" style="width: {{ round(100 * $u['total'] / $max($a['by_unit'])) }}%"></div></div></td></tr>
        @empty <tr><td colspan="3" class="muted">—</td></tr> @endforelse
    </tbody></table>

    <h2>Statistik per Kategori</h2>
    <table><thead><tr><th>Kategori</th><th class="num">Tiket</th><th style="width:55%"></th></tr></thead><tbody>
        @forelse ($a['by_category'] as $c)<tr><td>{{ $c['name'] }}</td><td class="num">{{ $c['total'] }}</td><td><div class="barbg"><div class="bar" style="width: {{ round(100 * $c['total'] / $max($a['by_category'])) }}%"></div></div></td></tr>
        @empty <tr><td colspan="3" class="muted">—</td></tr> @endforelse
    </tbody></table>

    <h2>Aktivitas Pekerjaan IT</h2>
    <table><thead><tr><th>Staf</th><th class="num">Balasan</th><th class="num">Catatan internal</th><th class="num">Diselesaikan</th><th class="num">PIC terbuka</th></tr></thead><tbody>
        @forelse ($a['it_activity'] as $r)<tr><td>{{ $r['name'] }}</td><td class="num">{{ $r['public_replies'] }}</td><td class="num">{{ $r['internal_notes'] }}</td><td class="num">{{ $r['resolved'] }}</td><td class="num">{{ $r['open_assigned'] }}</td></tr>
        @empty <tr><td colspan="5" class="muted">Belum ada aktivitas.</td></tr> @endforelse
    </tbody></table>

    <h2>Permasalahan Berulang</h2>
    @if ($a['recurring'] === [])<p class="muted">Tidak ada isu berulang (≥ {{ A::RECURRING_MIN }} tiket kombinasi unit + kategori yang sama).</p>
    @else
        <table><thead><tr><th>Unit</th><th>Kategori</th><th class="num">Jumlah</th><th>Artikel KB</th><th>Saran pencegahan</th></tr></thead><tbody>
            @foreach ($a['recurring'] as $r)<tr><td>{{ $r['unit'] }}</td><td>{{ $r['category'] }}{{ $r['subcategory'] ? ' / '.$r['subcategory'] : '' }}</td><td class="num">{{ $r['total'] }}</td><td>{{ $r['has_kb'] ? 'Ada' : 'Belum' }}</td><td>{{ $r['suggestion'] }}</td></tr>@endforeach
        </tbody></table>
    @endif

    <h2>Rekomendasi Tindak Lanjut</h2>
    <ol>@foreach ($a['recommendations'] as $rec)<li>{{ $rec }}</li>@endforeach</ol>
@endsection
