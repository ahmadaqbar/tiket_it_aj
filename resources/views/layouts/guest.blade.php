<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ \App\Support\Branding::primaryColor() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ \App\Support\Branding::shortName() }}</title>
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($themeCss = \App\Support\Branding::themeCss())<style>{{ $themeCss }}</style>@endif
</head>
<body class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            @if ($logo = \App\Support\Branding::logoUrl())
                <img src="{{ $logo }}" alt="Logo" class="mx-auto h-14 w-auto object-contain">
            @else
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-700 text-lg font-bold text-white">IT</span>
            @endif
            <h1 class="mt-4 text-lg font-semibold text-gray-900">{{ \App\Support\Branding::appName() }}</h1>
            <p class="text-sm text-gray-500">{{ \App\Support\Branding::organization() }}</p>
        </div>
        {{ $slot }}
        @php $contact = \App\Support\Branding::contact(); @endphp
        @if ($contact['email'] || $contact['phone'])
            <p class="mt-6 text-center text-xs text-gray-500">Bantuan: {{ collect([$contact['phone'], $contact['email']])->filter()->implode(' · ') }}</p>
        @endif
        <p class="mt-8 text-center text-xs text-gray-400">&copy; {{ date('Y') }} {{ \App\Support\Branding::supportTeam() }}</p>
    </div>
</body>
</html>
