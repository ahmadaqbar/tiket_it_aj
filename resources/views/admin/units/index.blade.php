<x-layouts::app title="Unit Management">
    <x-page-header title="Unit Management" subtitle="Unit tidak dapat dihapus agar data historis tetap utuh — gunakan status nonaktif.">
        <x-slot:actions>
            <a href="{{ route('admin.units.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Tambah Unit</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Urutan</th><th>Kode</th><th>Nama Unit</th><th>Jumlah User</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($units as $unit)
                        <tr>
                            <td class="text-gray-500">{{ $unit->sort_order }}</td>
                            <td class="font-mono font-semibold">{{ $unit->code }}</td>
                            <td>
                                <span class="font-medium text-gray-900">{{ $unit->name }}</span>
                                @if ($unit->description)<span class="block text-xs text-gray-500">{{ $unit->description }}</span>@endif
                            </td>
                            <td>{{ $unit->users_count }}</td>
                            <td><x-active-badge :active="$unit->is_active" /></td>
                            <td class="text-right"><a href="{{ route('admin.units.edit', $unit) }}" class="btn-secondary btn-sm"><x-icon name="pencil" class="h-3.5 w-3.5" /> Ubah</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="building" message="Belum ada unit." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts::app>
