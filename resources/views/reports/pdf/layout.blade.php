<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8">
<style>
    @page { margin: 14mm 12mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1f2937; }
    h1 { font-size: 14pt; margin: 0; color: #16432b; } h2 { font-size: 10.5pt; margin: 14px 0 6px; color: #16432b; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
    .muted { color: #6b7280; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #e5e7eb; padding: 3px 4px; vertical-align: top; text-align: left; }
    th { background: #f3f4f6; font-size: 7.5pt; } .num { text-align: right; } .bar { background: #1b5434; height: 8px; } .barbg { background: #e5e7eb; width: 100%; }
    .grid td { border: none; padding: 2px 8px 2px 0; } .card { border: 1px solid #e5e7eb; padding: 6px 8px; } .big { font-size: 13pt; font-weight: bold; }
    footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #9ca3af; }
</style></head>
<body>
<footer>{{ $appName }} · dibuat {{ $generatedAt->translatedFormat('d M Y H:i') }}</footer>
@yield('content')
</body></html>
