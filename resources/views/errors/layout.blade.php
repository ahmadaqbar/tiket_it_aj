<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · ALMEGA IT Ticketing</title>
    <style>
        body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:#f9fafb;color:#1f2937;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:1rem}
        .box{max-width:28rem;text-align:center}.code{font-size:3rem;font-weight:700;color:#16432b;margin:0}
        h1{font-size:1.125rem;margin:.5rem 0}p{color:#6b7280;font-size:.875rem}
        a{display:inline-block;margin-top:1.25rem;background:#1b5434;color:#fff;padding:.6rem 1.1rem;border-radius:.5rem;text-decoration:none;font-size:.875rem}
    </style>
</head>
<body>
<div class="box">
    <p class="code">@yield('code')</p>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <a href="{{ url('/') }}">Kembali ke Beranda</a>
</div>
</body>
</html>
