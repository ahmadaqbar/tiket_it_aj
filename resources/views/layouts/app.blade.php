@php
    use App\Support\Navigation;
    $authUser = auth()->user();
    $sections = Navigation::sectionsFor($authUser);
    $mobileNav = Navigation::mobileFor($authUser);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ \App\Support\Branding::primaryColor() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ \App\Support\Branding::shortName() }}</title>
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @if ($themeCss = \App\Support\Branding::themeCss())<style>{{ $themeCss }}</style>@endif
</head>
<body class="min-h-screen" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">

    {{-- Overlay mobile --}}
    <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden" @click="sidebarOpen = false"></div>

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform lg:translate-x-0"
           :class="{ 'translate-x-0': sidebarOpen }" aria-label="Navigasi utama">
        <div class="flex h-16 items-center justify-between border-b border-gray-100 px-5">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                @if ($logo = \App\Support\Branding::logoUrl())
                    <img src="{{ $logo }}" alt="Logo" class="h-8 w-auto max-w-[3rem] object-contain">
                @else
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-700 text-sm font-bold text-white">IT</span>
                @endif
                <span class="leading-tight">
                    <span class="block text-sm font-semibold text-gray-900">{{ \App\Support\Branding::shortName() }}</span>
                    <span class="block text-[11px] text-gray-500">Ticketing System</span>
                </span>
            </a>
            <button type="button" class="text-gray-500 lg:hidden" @click="sidebarOpen = false" aria-label="Tutup menu">
                <x-icon name="x" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4">
            @foreach ($sections as $section)
                @if ($section['heading'])
                    <p class="mt-5 mb-2 px-3 text-[11px] font-semibold tracking-wider text-gray-400 uppercase">{{ $section['heading'] }}</p>
                @endif
                <ul class="space-y-0.5">
                    @foreach ($section['items'] as $item)
                        @php $active = Navigation::isActive($item); @endphp
                        <li>
                            <a href="{{ route($item['route'], $item['params'] ?? []) }}"
                               @class([
                                   'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition',
                                   'bg-brand-50 font-medium text-brand-800' => $active,
                                   'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => ! $active,
                               ])
                               @if ($active) aria-current="page" @endif>
                                <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <div class="border-t border-gray-100 p-3">
            <a href="{{ route('profile.show') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-gray-50">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">
                    {{ strtoupper(mb_substr($authUser->name, 0, 1)) }}
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-gray-800">{{ $authUser->name }}</span>
                    <span class="block text-xs text-gray-500">{{ $authUser->role->name }}</span>
                </span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-red-50 hover:text-red-700">
                    <x-icon name="logout" /> Keluar
                </button>
            </form>
        </div>
    </aside>

    <div class="lg:pl-64">
        {{-- Top bar --}}
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-200 bg-white/95 px-4 backdrop-blur sm:px-6">
            <button type="button" class="-ml-1 rounded-lg p-1.5 text-gray-600 hover:bg-gray-100 lg:hidden" @click="sidebarOpen = true" aria-label="Buka menu">
                <x-icon name="menu" class="h-6 w-6" />
            </button>
            <p class="truncate text-sm font-medium text-gray-700">{{ $title ?? '' }}</p>
            <div class="ml-auto flex items-center gap-2">
                @isset($topbar){{ $topbar }}@endisset
                <livewire:notification-bell />
            </div>
        </header>

        <main @class(['mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8', 'pb-24 lg:pb-6' => $mobileNav !== []])>
            <x-flash />
            {{ $slot }}
        </main>
    </div>

    {{-- Bottom navigation mobile (role USER) --}}
    @if ($mobileNav !== [])
        <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Navigasi bawah">
            <ul class="grid" style="grid-template-columns: repeat({{ count($mobileNav) }}, minmax(0, 1fr))">
                @foreach ($mobileNav as $item)
                    @php $active = Navigation::isActive($item); @endphp
                    <li>
                        <a href="{{ route($item['route']) }}" @class([
                            'flex flex-col items-center gap-1 py-2 text-[11px]',
                            'font-semibold text-brand-700' => $active,
                            'text-gray-500' => ! $active,
                        ])>
                            <x-icon :name="$item['icon']" class="h-6 w-6" />
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    @livewireScripts
    @stack('scripts')
    <script>
        if ('serviceWorker' in navigator) { window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {})); }
    </script>
</body>
</html>
