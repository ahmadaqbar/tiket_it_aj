<x-layouts::app title="Ganti Password">
    <x-page-header title="Ganti Password" subtitle="Gunakan minimal 8 karakter yang mengandung huruf dan angka." />

    <div class="card max-w-lg p-6">
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <x-form.input name="current_password" label="Password Saat Ini" type="password" autocomplete="current-password" required />
            <x-form.input name="password" label="Password Baru" type="password" autocomplete="new-password" required />
            <x-form.input name="password_confirmation" label="Konfirmasi Password Baru" type="password" autocomplete="new-password" required />
            <div class="flex gap-2 pt-2">
                <button type="submit" class="btn-primary">Simpan Password</button>
                @unless (auth()->user()->must_change_password)
                    <a href="{{ route('profile.show') }}" class="btn-secondary">Batal</a>
                @endunless
            </div>
        </form>
    </div>
</x-layouts::app>
