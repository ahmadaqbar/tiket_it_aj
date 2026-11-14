<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#16432b">
    <title>{{ isset($title) ? $title.' · ' : '' }}ALMEGA IT Ticketing</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-700 text-lg font-bold text-white">IT</span>
            <h1 class="mt-4 text-lg font-semibold text-gray-900">ALMEGA IT TICKETING SYSTEM</h1>
            <p class="text-sm text-gray-500">PT Almega Jaya Group</p>
        </div>
        {{ $slot }}
        <p class="mt-8 text-center text-xs text-gray-400">&copy; {{ date('Y') }} Divisi IT PT Almega Jaya Group</p>
    </div>
</body>
</html>
