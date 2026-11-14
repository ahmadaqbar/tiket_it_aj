@php
    $isEdit = $user->exists;
    $selectedUnits = array_map('intval', old('unit_ids', $isEdit ? $user->units->pluck('id')->all() : []));
    $primaryUnit = (int) old('primary_unit_id', $isEdit ? $user->units->firstWhere('pivot.is_primary', true)?->id : null);
    $userRoleId = $roles->firstWhere('code', \App\Enums\RoleCode::USER)?->id;
@endphp
<x-layouts::app :title="$isEdit ? 'Ubah User' : 'Tambah User'">
    <x-page-header :title="$isEdit ? 'Ubah User: '.$user->username : 'Tambah User'">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">Kembali</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ $isEdit ? route('admin.users.update', $user) : route('admin.users.store') }}"
              class="card space-y-5 p-6 lg:col-span-2"
              x-data="{ roleId: '{{ old('role_id', $user->role_id ?? $userRoleId) }}', units: @js($selectedUnits), primary: {{ $primaryUnit ?: 'null' }} }">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="name" label="Nama Lengkap" :value="$user->name" required />
                <x-form.input name="username" label="Username" :value="$user->username" required hint="Huruf kecil, angka, titik, - atau _" autocomplete="off" />
                <x-form.input name="email" label="Email" type="email" :value="$user->email" hint="Opsional" />
                <x-form.input name="phone" label="Nomor HP" :value="$user->phone" hint="Opsional" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="role_id" class="form-label">Role <span class="text-red-500">*</span></label>
                    <select id="role_id" name="role_id" class="form-input" x-model="roleId" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role_id')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end pb-2">
                    <x-form.checkbox name="is_active" label="Akun aktif" :checked="$user->is_active" />
                </div>
            </div>

            @unless ($isEdit)
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="password" label="Password Sementara" type="password" required autocomplete="new-password" hint="User wajib menggantinya saat login pertama." />
                    <x-form.input name="password_confirmation" label="Konfirmasi Password" type="password" required autocomplete="new-password" />
                </div>
            @endunless

            <fieldset>
                <legend class="form-label">
                    Unit
                    <span x-show="roleId == '{{ $userRoleId }}'" class="text-red-500">*</span>
                    <span x-show="roleId != '{{ $userRoleId }}'" class="font-normal text-gray-500">(opsional untuk IT — IT dapat melihat semua unit)</span>
                </legend>
                <p class="form-hint mb-2">Centang unit yang dapat dilaporkan user, lalu pilih satu sebagai unit utama.</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($units as $unit)
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2" :class="units.includes({{ $unit->id }}) && 'border-brand-300 bg-brand-50'">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" x-model.number="units"
                                       class="h-4 w-4 rounded border-gray-300 text-brand-700 focus:ring-brand-500">
                                <span><span class="font-semibold">{{ $unit->code }}</span> {{ $unit->name }}
                                    @unless ($unit->is_active)<span class="text-xs text-gray-400">(nonaktif)</span>@endunless</span>
                            </label>
                            <label class="flex items-center gap-1 text-xs text-gray-500" x-show="units.includes({{ $unit->id }})">
                                <input type="radio" name="primary_unit_id" value="{{ $unit->id }}" x-model.number="primary" class="h-3.5 w-3.5 text-brand-700 focus:ring-brand-500"> Utama
                            </label>
                        </div>
                    @endforeach
                </div>
                @error('unit_ids')<p class="form-error">{{ $message }}</p>@enderror
                @error('unit_ids.*')<p class="form-error">{{ $message }}</p>@enderror
                @error('primary_unit_id')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>

            <div class="flex gap-2 border-t border-gray-100 pt-5">
                <button type="submit" class="btn-primary">{{ $isEdit ? 'Simpan Perubahan' : 'Buat User' }}</button>
                <a href="{{ route('admin.users.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>

        @if ($isEdit)
            <div class="space-y-6">
                <div class="card p-6">
                    <h2 class="text-base font-semibold text-gray-900">Informasi</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Dibuat</dt><dd>{{ $user->created_at->translatedFormat('d M Y') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Login terakhir</dt><dd>{{ $user->last_login_at?->translatedFormat('d M Y H:i') ?? '-' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">IP terakhir</dt><dd class="font-mono text-xs">{{ $user->last_login_ip ?? '-' }}</dd></div>
                    </dl>
                </div>

                @can('resetPassword', $user)
                    <form method="POST" action="{{ route('admin.users.password', $user) }}" class="card space-y-4 p-6">
                        @csrf
                        @method('PUT')
                        <h2 class="text-base font-semibold text-gray-900">Reset Password</h2>
                        <p class="text-xs text-gray-500">Sesi aktif user akan diputus dan user wajib mengganti password saat login berikutnya.</p>
                        <x-form.input name="password" id="reset_password" label="Password Baru" type="password" required autocomplete="new-password" />
                        <x-form.input name="password_confirmation" id="reset_password_confirmation" label="Konfirmasi" type="password" required autocomplete="new-password" />
                        <button type="submit" class="btn-danger w-full" onclick="return confirm('Reset password user ini?')">Reset Password</button>
                    </form>
                @endcan
            </div>
        @endif
    </div>
</x-layouts::app>
