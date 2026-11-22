<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak QR {{ $unit->code }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; text-align: center; color: #1f2937; margin: 0; padding: 24px; }
        .qr svg { width: 260px; height: 260px; }
        h1 { color: #16432b; margin: 8px 0 4px; }
        .muted { color: #6b7280; font-size: 14px; }
        .url { font-family: monospace; font-size: 11px; color: #9ca3af; margin-top: 12px; word-break: break-all; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <p class="muted">{{ \App\Support\Branding::organization() }}</p>
    <h1>Lapor Kendala IT</h1>
    <p><strong>{{ $unit->code }} — {{ $unit->name }}</strong></p>
    <div class="qr">{!! $svg !!}</div>
    <p class="muted">Scan QR → login → isi laporan (unit terisi otomatis)</p>
    <p class="url">{{ $url }}</p>
    <button class="no-print" onclick="window.print()">Cetak</button>
</body>
</html>
