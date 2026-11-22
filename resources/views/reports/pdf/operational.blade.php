@extends('reports.pdf.layout')
@section('content')
    <h1>Laporan Operasional IT</h1>
    <p class="muted">{{ $organization }} · Periode {{ $period->label() }} · {{ $unit?->label() ?? 'Semua unit' }} · {{ $rows->count() }} tiket</p>
    @if ($truncated)<p><strong>Catatan:</strong> PDF dibatasi {{ $rows->count() }} tiket pertama. Gunakan Excel untuk data lengkap.</p>@endif
    <table>
        <thead><tr><th>No. Tiket</th><th>Tanggal</th><th>Unit</th><th>Pelapor</th><th>Kategori</th><th style="width:22%">Judul & Deskripsi</th><th style="width:20%">Respons IT</th><th>Status</th><th>PIC</th><th>Durasi</th></tr></thead>
        <tbody>
        @forelse ($rows as $t)
            <tr>
                <td>{{ $t->ticket_number }}</td><td>{{ $t->created_at->format('d/m/Y H:i') }}</td><td>{{ $t->unit->code }}</td><td>{{ $t->reporter?->name }}</td>
                <td>{{ $t->category->name }}{{ $t->subcategory ? ' / '.$t->subcategory->name : '' }}</td>
                <td><strong>{{ $t->title }}</strong><br>{{ \Illuminate\Support\Str::limit($t->description, 220) }}</td>
                <td>{{ \Illuminate\Support\Str::limit((string) $t->it_response, 220) ?: '—' }}</td>
                <td>{{ $t->status->label() }}</td><td>{{ $t->assignee?->name ?? '—' }}</td>
                <td>{{ $t->resolved_at ? \App\Services\AnalyticsService::formatMinutes((int) $t->created_at->diffInMinutes($t->resolved_at)) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="muted">Tidak ada tiket pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
