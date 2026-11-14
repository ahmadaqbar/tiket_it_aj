<x-layouts::guest title="Masuk">
    <div class="card p-6">
        <h2 class="text-base font-semibold text-gray-900">Masuk ke akun Anda</h2>
        <p class="mt-1 text-sm text-gray-500">Gunakan username atau email yang diberikan Divisi IT.</p>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('qr_unit_name'))
            <div class="mt-4 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-800">
                Laporan untuk unit <strong>{{ session('qr_unit_name') }}</strong>. Silakan masuk untuk melanjutkan.
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-5 space-y-4" x-data="{ loading: false }" @submit="loading = true">
            @csrf
            <x-form.input name="login" label="Username / Email" autocomplete="username" autofocus required />
            <x-form.input name="password" label="Password" type="password" autocomplete="current-password" required />

            <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-gray-300 text-brand-700 focus:ring-brand-500">
                Ingat saya di perangkat ini
            </label>

            <button type="submit" class="btn-primary w-full" :disabled="loading">
                <span x-show="!loading">Masuk</span>
                <span x-cloak x-show="loading">Memproses…</span>
            </button>
        </form>
        <p class="mt-5 text-center text-xs text-gray-500">Lupa password? Hubungi IT Admin untuk reset password.</p>
    </div>
</x-layouts::guest>
