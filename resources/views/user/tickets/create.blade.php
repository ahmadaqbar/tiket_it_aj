@php
    $categoryData = $categories->map(fn ($c) => [
        'id' => $c->id,
        'name' => $c->name,
        'subs' => $c->subcategories->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
    ])->values();
@endphp
<x-layouts::app title="Buat Laporan">
    <x-page-header title="Buat Laporan" subtitle="Jelaskan kendala Anda. Nomor tiket dibuat otomatis setelah laporan dikirim." />

    <form method="POST" action="{{ route('user.tickets.store') }}" enctype="multipart/form-data"
          class="card max-w-3xl space-y-5 p-5 sm:p-6"
          x-data="ticketForm(@js($categoryData), '{{ old('category_id') }}', '{{ old('subcategory_id') }}', {{ $maxFiles }}, {{ $maxKb }})"
          @submit="submitting = true">
        @csrf

        {{-- Unit --}}
        @if ($units->count() === 1)
            <input type="hidden" name="unit_id" value="{{ $units->first()->id }}">
            <div class="flex items-center gap-2 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-800">
                <x-icon name="building" class="h-4 w-4" /> Unit: <strong>{{ $units->first()->label() }}</strong>
            </div>
        @else
            <x-form.select name="unit_id" label="Unit" required :value="$defaultUnitId"
                :options="$units->mapWithKeys(fn ($u) => [$u->id => $u->label()])->all()" />
        @endif

        <x-form.input name="title" label="Judul Laporan" required maxlength="150" placeholder="Contoh: Printer kasir tidak bisa mencetak" />

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="category_id" class="form-label">Kategori <span class="text-red-500">*</span></label>
                <select id="category_id" name="category_id" class="form-input" x-model="categoryId" @change="subcategoryId = ''" required>
                    <option value="">Pilih kategori…</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="subcategory_id" class="form-label">Subkategori <span class="font-normal text-gray-400">(opsional)</span></label>
                <select id="subcategory_id" name="subcategory_id" class="form-input" x-model="subcategoryId" :disabled="subs.length === 0">
                    <option value="">—</option>
                    <template x-for="sub in subs" :key="sub.id">
                        <option :value="sub.id" x-text="sub.name" :selected="String(sub.id) === String(subcategoryId)"></option>
                    </template>
                </select>
                @error('subcategory_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="description" class="form-label">Deskripsi Kendala <span class="text-red-500">*</span></label>
            <textarea id="description" name="description" rows="5" required maxlength="5000" class="form-input"
                      placeholder="Apa yang terjadi? Sejak kapan? Perangkat/lokasi mana? Pesan error yang muncul?">{{ old('description') }}</textarea>
            @error('description')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <fieldset>
            <legend class="form-label">Prioritas <span class="font-normal text-gray-400">(opsional, default Sedang)</span></legend>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ($priorities as $priority)
                    <label class="flex cursor-pointer flex-col rounded-lg border border-gray-200 px-3 py-2 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                        <span class="flex items-center gap-2 font-medium">
                            <input type="radio" name="priority" value="{{ $priority->value }}" @checked(old('priority') === $priority->value)
                                   class="h-4 w-4 text-brand-700 focus:ring-brand-500">
                            {{ $priority->label() }}
                        </span>
                        <span class="mt-0.5 text-xs text-gray-500">{{ $priority->hint() }}</span>
                    </label>
                @endforeach
            </div>
            @error('priority')<p class="form-error">{{ $message }}</p>@enderror
        </fieldset>

        <div>
            <label for="attachments" class="form-label">Foto / Dokumen <span class="font-normal text-gray-400">(opsional)</span></label>
            <input id="attachments" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                   @change="pickFiles($event)"
                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-gray-200">
            <p class="form-hint">Maks. {{ $maxFiles }} file, masing-masing {{ number_format($maxKb / 1024, 0) }} MB. Format: JPG, PNG, WEBP, PDF, Word, Excel, TXT.</p>
            <ul class="mt-2 space-y-1 text-xs" x-show="files.length" x-cloak>
                <template x-for="f in files" :key="f.name">
                    <li :class="f.tooBig ? 'text-red-600' : 'text-gray-600'" x-text="f.name + ' (' + f.size + ')' + (f.tooBig ? ' — terlalu besar' : '')"></li>
                </template>
            </ul>
            <p class="form-error" x-show="tooMany" x-cloak>Jumlah file melebihi batas {{ $maxFiles }}.</p>
            @error('attachments')<p class="form-error">{{ $message }}</p>@enderror
            @foreach ($errors->get('attachments.*') as $messages)
                <p class="form-error">{{ $messages[0] }}</p>
            @endforeach
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row">
            <a href="{{ route('user.dashboard') }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary sm:ml-auto" :disabled="submitting || hasFileError">
                <span x-show="!submitting">Kirim Laporan</span>
                <span x-cloak x-show="submitting">Mengirim…</span>
            </button>
        </div>
    </form>

    @push('scripts')
        <script>
            function ticketForm(categories, categoryId, subcategoryId, maxFiles, maxKb) {
                return {
                    categories, categoryId, subcategoryId, files: [], tooMany: false, submitting: false,
                    get subs() {
                        const c = this.categories.find(c => String(c.id) === String(this.categoryId));
                        return c ? c.subs : [];
                    },
                    get hasFileError() { return this.tooMany || this.files.some(f => f.tooBig); },
                    pickFiles(e) {
                        const list = Array.from(e.target.files || []);
                        this.tooMany = list.length > maxFiles;
                        this.files = list.map(f => ({
                            name: f.name,
                            size: f.size >= 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.ceil(f.size / 1024) + ' KB',
                            tooBig: f.size > maxKb * 1024,
                        }));
                    },
                };
            }
        </script>
    @endpush
</x-layouts::app>
