<x-layouts::app title="User Management">
    <x-page-header title="User Management" subtitle="Kelola akun, role, dan unit pengguna. Tidak ada registrasi publik.">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Tambah User</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <label for="q" class="sr-only">Cari</label>
            <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama, username, email…" class="form-input">
        </div>
        <select name="role_id" class="form-input" aria-label="Role">
            <option value="">Semua role</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected(($filters['role_id'] ?? '') == $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        <select name="unit_id" class="form-input" aria-label="Unit">
            <option value="">Semua unit</option>
            @foreach ($units as $unit)
                <option value="{{ $unit->id }}" @selected(($filters['unit_id'] ?? '') == $unit->id)>{{ $unit->label() }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <select name="status" class="form-input" aria-label="Status">
                <option value="">Semua status</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
            </select>
            <button class="btn-secondary" type="submit" aria-label="Terapkan filter"><x-icon name="search" class="h-4 w-4" /></button>
        </div>
    </form>

    <div class="card overflow-hidden">
        @if ($users->isEmpty())
            <x-empty-state icon="users" message="Tidak ada user yang cocok dengan filter." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr><th>Nama</th><th>Username</th><th>Role</th><th>Unit</th><th>Status</th><th>Login Terakhir</th><th class="text-right">Aksi</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $u)
                            <tr>
                                <td>
                                    <span class="font-medium text-gray-900">{{ $u->name }}</span>
                                    @if ($u->email)<span class="block text-xs text-gray-500">{{ $u->email }}</span>@endif
                                </td>
                                <td class="font-mono text-xs">{{ $u->username }}</td>
                                <td>{{ $u->role->name }}</td>
                                <td class="text-xs">
                                    @forelse ($u->units as $unit)
                                        <span @class(['font-semibold' => $unit->pivot->is_primary])>{{ $unit->code }}</span>@if (! $loop->last), @endif
                                    @empty
                                        <span class="text-gray-400">—</span>
                                    @endforelse
                                </td>
                                <td><x-active-badge :active="$u->is_active" /></td>
                                <td class="text-xs text-gray-500">{{ $u->last_login_at?->translatedFormat('d M Y H:i') ?? 'Belum pernah' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('admin.users.edit', $u) }}" class="btn-secondary btn-sm"><x-icon name="pencil" class="h-3.5 w-3.5" /> Ubah</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-3">{{ $users->links() }}</div>
        @endif
    </div>
</x-layouts::app>
