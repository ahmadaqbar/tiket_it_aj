<x-layouts::app title="Category Management">
    <x-page-header title="Category Management" subtitle="Kategori/subkategori yang sudah dipakai tiket tidak dapat dihapus — nonaktifkan saja." />

    @error('category')<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror
    @error('subcategory')<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

    <form method="POST" action="{{ route('admin.categories.store') }}" class="card mb-6 grid gap-3 p-4 sm:grid-cols-6 sm:items-end">
        @csrf
        <div class="sm:col-span-2"><x-form.input name="name" label="Kategori Baru" required /></div>
        <div class="sm:col-span-2"><x-form.input name="description" label="Keterangan" /></div>
        <div><x-form.input name="sort_order" label="Urutan" type="number" min="0" :value="$categories->max('sort_order') + 1" /></div>
        <input type="hidden" name="is_active" value="1">
        <button class="btn-primary" type="submit"><x-icon name="plus" class="h-4 w-4" /> Tambah</button>
    </form>

    <div class="space-y-3">
        @foreach ($categories as $category)
            <div class="card" x-data="{ open: false, edit: false }">
                <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                    <button type="button" @click="open = !open" class="flex min-w-0 flex-1 items-center gap-2 text-left">
                        <span class="text-gray-400" x-text="open ? '▾' : '▸'"></span>
                        <span class="font-medium text-gray-900">{{ $category->name }}</span>
                        <span class="text-xs text-gray-500">{{ $category->subcategories_count }} subkategori · {{ $category->tickets_count }} tiket</span>
                    </button>
                    <x-active-badge :active="$category->is_active" />
                    <button type="button" class="btn-secondary btn-sm" @click="edit = !edit; open = true">Ubah</button>
                    @if ($category->tickets_count === 0)
                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Hapus kategori {{ $category->name }}?')">
                            @csrf @method('DELETE')
                            <button class="btn-sm btn border border-red-200 text-red-700 hover:bg-red-50" type="submit">Hapus</button>
                        </form>
                    @endif
                </div>

                <div x-show="open" x-cloak class="border-t border-gray-100 px-4 py-3">
                    <form x-show="edit" method="POST" action="{{ route('admin.categories.update', $category) }}" class="mb-4 grid gap-3 rounded-lg bg-gray-50 p-3 sm:grid-cols-6 sm:items-end">
                        @csrf @method('PUT')
                        <div class="sm:col-span-2"><label class="form-label">Nama</label><input name="name" value="{{ $category->name }}" class="form-input" required maxlength="100"></div>
                        <div class="sm:col-span-2"><label class="form-label">Keterangan</label><input name="description" value="{{ $category->description }}" class="form-input" maxlength="255"></div>
                        <div><label class="form-label">Urutan</label><input type="number" min="0" name="sort_order" value="{{ $category->sort_order }}" class="form-input"></div>
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="is_active" value="0">
                            <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="rounded border-gray-300 text-brand-700"> Aktif</label>
                            <button class="btn-primary btn-sm" type="submit">Simpan</button>
                        </div>
                    </form>

                    <ul class="divide-y divide-gray-100">
                        @foreach ($category->subcategories as $sub)
                            <li class="flex flex-wrap items-center gap-2 py-2">
                                <form method="POST" action="{{ route('admin.subcategories.update', $sub) }}" class="flex flex-1 flex-wrap items-center gap-2">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $sub->name }}" class="form-input max-w-xs py-1.5" required maxlength="100" aria-label="Nama subkategori">
                                    <input type="number" name="sort_order" value="{{ $sub->sort_order }}" min="0" class="form-input w-20 py-1.5" aria-label="Urutan">
                                    <input type="hidden" name="is_active" value="0">
                                    <label class="flex items-center gap-1 text-xs text-gray-600"><input type="checkbox" name="is_active" value="1" @checked($sub->is_active) class="rounded border-gray-300 text-brand-700"> Aktif</label>
                                    <span class="text-xs text-gray-400">{{ $sub->tickets_count }} tiket</span>
                                    <button class="btn-secondary btn-sm" type="submit">Simpan</button>
                                </form>
                                @if ($sub->tickets_count === 0)
                                    <form method="POST" action="{{ route('admin.subcategories.destroy', $sub) }}" onsubmit="return confirm('Hapus subkategori?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline" type="submit">Hapus</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    <form method="POST" action="{{ route('admin.categories.subcategories.store', $category) }}" class="mt-3 flex flex-wrap items-center gap-2">
                        @csrf
                        <input name="name" placeholder="Subkategori baru…" class="form-input max-w-xs py-1.5" required maxlength="100" aria-label="Subkategori baru">
                        <input type="hidden" name="is_active" value="1">
                        <input type="hidden" name="sort_order" value="{{ $category->subcategories->max('sort_order') + 1 }}">
                        <button class="btn-secondary btn-sm" type="submit"><x-icon name="plus" class="h-3.5 w-3.5" /> Tambah</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts::app>
