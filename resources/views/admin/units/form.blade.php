@php $isEdit = $unit->exists; @endphp
<x-layouts::app :title="$isEdit ? 'Ubah Unit' : 'Tambah Unit'">
    <x-page-header :title="$isEdit ? 'Ubah Unit: '.$unit->code : 'Tambah Unit'">
        <x-slot:actions><a href="{{ route('admin.units.index') }}" class="btn-secondary">Kembali</a></x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $isEdit ? route('admin.units.update', $unit) : route('admin.units.store') }}" class="card max-w-2xl space-y-4 p-6">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                @if ($codeEditable)
                    <x-form.input name="code" label="Kode Unit" :value="$unit->code" required maxlength="10" class="uppercase" hint="Dipakai di nomor tiket, mis. IT-HO-20261114-001" />
                @else
                    <input type="hidden" name="code" value="{{ $unit->code }}">
                    <label class="form-label">Kode Unit</label>
                    <input class="form-input" value="{{ $unit->code }}" disabled>
                    <p class="form-hint">Terkunci karena sudah digunakan pada nomor tiket.</p>
                @endif
            </div>
            <div class="sm:col-span-2">
                <x-form.input name="name" label="Nama Unit" :value="$unit->name" required />
            </div>
        </div>
        <x-form.input name="description" label="Keterangan" :value="$unit->description" hint="Opsional, mis. alamat atau lokasi." />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="sort_order" label="Urutan Tampil" type="number" min="0" :value="$unit->sort_order ?? 0" />
            <div class="flex items-end pb-2">
                <x-form.checkbox name="is_active" label="Unit aktif" :checked="$unit->is_active" hint="Unit nonaktif tidak bisa dipilih saat membuat tiket dan QR-nya ditolak." />
            </div>
        </div>

        <div class="flex gap-2 border-t border-gray-100 pt-5">
            <button type="submit" class="btn-primary">{{ $isEdit ? 'Simpan Perubahan' : 'Tambah Unit' }}</button>
            <a href="{{ route('admin.units.index') }}" class="btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts::app>
