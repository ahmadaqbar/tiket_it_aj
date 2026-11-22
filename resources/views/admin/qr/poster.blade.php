<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; text-align: center; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .org { font-size: 11pt; color: #6b7280; margin: 0; }
        h1 { font-size: 20pt; margin: 6mm 0 2mm; color: #16432b; }
        .code { font-size: 12pt; color: #374151; margin: 0 0 6mm; }
        img { width: 80mm; height: 80mm; }
        .steps { font-size: 10pt; color: #374151; margin-top: 6mm; line-height: 1.6; }
        .foot { font-size: 8pt; color: #9ca3af; margin-top: 8mm; }
    </style>
</head>
<body>
@foreach ($units as $row)
    <div class="page">
        <p class="org">{{ \App\Support\Branding::organization() }}</p>
        <h1>Lapor Kendala IT</h1>
        <p class="code">{{ $row['unit']->code }} — {{ $row['unit']->name }}</p>
        <img src="{{ $row['png'] }}" alt="QR">
        <div class="steps">1. Scan QR dengan kamera HP<br>2. Login dengan akun Anda<br>3. Isi laporan — unit sudah terisi otomatis</div>
        <p class="foot">{{ \App\Support\Branding::appName() }}</p>
    </div>
@endforeach
</body>
</html>
