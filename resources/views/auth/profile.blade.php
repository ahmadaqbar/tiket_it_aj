<x-layouts::app title="Profil">
    <x-page-header title="Profil Saya" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card p-6 lg:col-span-2">
            <h2 class="text-base font-semibold text-gray-900">Data Kontak</h2>
            <form method="POST" action="{{ route('profile.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                <x-form.input name="name" label="Nama" :value="$user->name" required />
                <x-form.input name="email" label="Email" type="email" :value="$user->email" hint="Opsional. Dapat digunakan untuk login." />
                <x-form.input name="phone" label="Nomor HP" :value="$user->phone" inputmode="tel" />
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
            </form>
        </div>

        <div class="space-y-6">
            <div class="card p-6">
                <h2 class="text-base font-semibold text-gray-900">Informasi Akun</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-gray-500">Username</dt><dd class="font-medium text-gray-900">{{ $user->username }}</dd></div>
                    <div><dt class="text-gray-500">Role</dt><dd class="font-medium text-gray-900">{{ $user->role->name }}</dd></div>
                    <div>
                        <dt class="text-gray-500">Unit</dt>
                        <dd class="font-medium text-gray-900">
                            @forelse ($user->units as $unit)
                                <span class="block">{{ $unit->label() }} @if ($unit->pivot->is_primary)<span class="text-xs font-normal text-brand-700">(utama)</span>@endif</span>
                            @empty
                                <span class="text-gray-500">Semua unit (IT)</span>
                            @endforelse
                        </dd>
                    </div>
                    <div><dt class="text-gray-500">Login terakhir</dt><dd class="font-medium text-gray-900">{{ $user->last_login_at?->translatedFormat('d M Y H:i') ?? '-' }}</dd></div>
                </dl>
            </div>
            <a href="{{ route('password.edit') }}" class="btn-secondary w-full"><x-icon name="key" class="h-4 w-4" /> Ganti Password</a>
        </div>
    </div>
</x-layouts::app>
