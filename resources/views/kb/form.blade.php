@php
    $isEdit = $article->exists;
    $categoryData = $categories->map(fn ($c) => ['id' => $c->id, 'subs' => $c->subcategories->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()])->values();
    $field = fn ($name) => old($name, $article->{$name});
    $visibility = old('visibility', $article->visibility?->value ?? 'INTERNAL');
@endphp
<x-layouts::app :title="$isEdit ? 'Ubah Artikel' : 'Artikel Baru'">
    <x-page-header :title="$isEdit ? 'Ubah Artikel' : 'Artikel Knowledge Base Baru'" subtitle="Tulis langkah yang dapat diikuti. Jangan menulis password atau data rahasia — gunakan catatan internal tiket." />

    <form method="POST" action="{{ $isEdit ? route('kb.update', $article) : route('kb.store') }}" class="card max-w-4xl space-y-5 p-5 sm:p-6"
          x-data="{ categoryId: '{{ $field('category_id') }}', subId: '{{ $field('subcategory_id') }}', cats: @js($categoryData) }">
        @csrf
        @if ($isEdit) @method('PUT') @endif
        @if ($article->source_ticket_id)<input type="hidden" name="source_ticket_id" value="{{ $article->source_ticket_id }}">@endif

        <x-form.input name="title" label="Judul" :value="$article->title" required maxlength="200" placeholder="Contoh: Printer kasir Epson tidak mencetak struk" />

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="form-label" for="category_id">Kategori</label>
                <select id="category_id" name="category_id" class="form-input" x-model="categoryId" @change="subId = ''">
                    <option value="">— Tanpa kategori —</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="subcategory_id">Subkategori</label>
                <select id="subcategory_id" name="subcategory_id" class="form-input" x-model="subId">
                    <option value="">—</option>
                    <template x-for="s in (cats.find(c => String(c.id) === String(categoryId))?.subs ?? [])" :key="s.id">
                        <option :value="s.id" x-text="s.name" :selected="String(s.id) === String(subId)"></option>
                    </template>
                </select>
                @error('subcategory_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="form-label" for="visibility">Visibilitas <span class="text-red-500">*</span></label>
                <select id="visibility" name="visibility" class="form-input" required>
                    @foreach ($visibilities as $v)<option value="{{ $v->value }}" @selected($visibility === $v->value)>{{ $v->label() }}</option>@endforeach
                </select>
            </div>
        </div>

        @foreach ([['problem', 'Deskripsi Masalah', true, 3], ['symptoms', 'Gejala', false, 3], ['root_cause', 'Akar Masalah', false, 2], ['steps', 'Langkah Troubleshooting', true, 7], ['resolution', 'Resolusi', false, 3]] as [$name, $label, $required, $rows])
            <div>
                <label class="form-label" for="{{ $name }}">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</label>
                <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" class="form-input" @if ($required) required @endif
                          @if ($name === 'steps') placeholder="1. Cek kabel daya&#10;2. Restart perangkat&#10;3. …" @endif>{{ $field($name) }}</textarea>
                @error($name)<p class="form-error">{{ $message }}</p>@enderror
            </div>
        @endforeach

        <x-form.input name="tags" label="Tag" :value="old('tags', $article->relationLoaded('tags') ? $article->tags->pluck('name')->implode(', ') : '')" hint="Pisahkan dengan koma, mis. printer, epson, kasir" />

        <div class="flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center">
            @unless ($isEdit)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="publish" value="1" class="rounded border-gray-300 text-brand-700"> Langsung publikasikan</label>
            @endunless
            <div class="flex gap-2 sm:ml-auto">
                <a href="{{ $isEdit ? route('kb.show', $article) : route('kb.index') }}" class="btn-secondary">Batal</a>
                <button class="btn-primary" type="submit">Simpan</button>
            </div>
        </div>
    </form>
</x-layouts::app>
