@props(['ticket', 'unavailable' => null, 'styles' => []])
{{-- Asisten AI: semua hasil adalah SARAN. Menerapkan saran hanya mengisi form; IT tetap harus menekan Simpan/Kirim. --}}
<div class="card p-5" x-data="aiPanel('{{ route('it.tickets.ai', [$ticket, '__F__']) }}')">
    <div class="mb-3 flex items-center gap-2">
        <x-icon name="sparkle" class="h-5 w-5 text-brand-700" />
        <h2 class="text-sm font-semibold text-gray-900">Asisten AI</h2>
    </div>

    @if ($unavailable)
        <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">{{ $unavailable }}</p>
    @else
        <div class="grid grid-cols-2 gap-2">
            <button type="button" class="btn-secondary btn-sm" @click="run('classify')" :disabled="loading">Klasifikasi</button>
            <button type="button" class="btn-secondary btn-sm" @click="run('troubleshoot')" :disabled="loading">Troubleshooting</button>
            <button type="button" class="btn-secondary btn-sm" @click="run('summary')" :disabled="loading">Ringkasan</button>
            <div class="flex gap-1">
                <select x-model="style" class="form-input min-w-0 px-2 py-1 text-xs" aria-label="Gaya balasan">
                    @foreach ($styles as $key => $label)<option value="{{ $key }}">{{ \Illuminate\Support\Str::before($label, ' —') }}</option>@endforeach
                </select>
                <button type="button" class="btn-secondary btn-sm shrink-0" @click="run('reply')" :disabled="loading">Draf</button>
            </div>
        </div>

        <p x-show="loading" x-cloak class="mt-3 text-xs text-gray-500">AI sedang memproses…</p>
        <p x-show="error" x-cloak class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700" x-text="error"></p>

        <div x-show="result" x-cloak class="mt-4 space-y-3 rounded-lg border border-brand-100 bg-brand-50/40 p-3 text-sm">
            <p class="text-[11px] font-medium tracking-wide text-brand-800 uppercase">Saran AI — periksa sebelum digunakan</p>

            <template x-if="feature === 'classify' && result">
                <div class="space-y-1.5">
                    <p><span class="text-gray-500">Kategori:</span> <span x-text="(result.category ?? '—') + (result.subcategory ? ' / ' + result.subcategory : '')"></span></p>
                    <p><span class="text-gray-500">Prioritas:</span> <span x-text="result.priority_label ?? '—'"></span> · <span class="text-gray-500">Dampak:</span> <span x-text="result.impact ?? '—'"></span></p>
                    <p x-show="result.summary" class="text-gray-700" x-text="result.summary"></p>
                    <ol class="list-decimal space-y-0.5 pl-5 text-gray-700"><template x-for="s in result.troubleshooting"><li x-text="s"></li></template></ol>
                    <button type="button" class="btn-primary btn-sm mt-1" x-show="result.category_id || result.priority"
                            @click="$dispatch('ai-classify', { priority: result.priority, category_id: result.category_id, subcategory_id: result.subcategory_id })">
                        Isi ke form Klasifikasi
                    </button>
                </div>
            </template>

            <template x-if="feature === 'troubleshoot' && result">
                <div class="space-y-1.5">
                    <ol class="list-decimal space-y-0.5 pl-5 text-gray-700"><template x-for="s in result.steps"><li x-text="s"></li></template></ol>
                    <p x-show="result.notes" class="text-xs text-gray-600" x-text="result.notes"></p>
                    <p class="text-xs" :class="result.used_knowledge_base ? 'text-brand-800' : 'text-amber-700'"
                       x-text="result.used_knowledge_base ? 'Merujuk Knowledge Base:' : 'Saran umum — tidak ada artikel Knowledge Base yang dirujuk.'"></p>
                    <ul class="text-xs"><template x-for="r in result.references"><li><a :href="r.url" class="text-brand-700 underline" x-text="r.title"></a></li></template></ul>
                </div>
            </template>

            <template x-if="feature === 'summary' && result">
                <div class="space-y-1.5 text-gray-700">
                    <p><span class="text-gray-500">Masalah:</span> <span x-text="result.problem ?? '—'"></span></p>
                    <div x-show="result.actions.length"><span class="text-gray-500">Tindakan:</span><ul class="list-disc pl-5"><template x-for="a in result.actions"><li x-text="a"></li></template></ul></div>
                    <p><span class="text-gray-500">Status:</span> <span x-text="result.current_status ?? '—'"></span></p>
                    <p x-show="result.resolution"><span class="text-gray-500">Resolusi:</span> <span x-text="result.resolution"></span></p>
                    <div x-show="result.next_steps.length"><span class="text-gray-500">Langkah berikutnya:</span><ul class="list-disc pl-5"><template x-for="n in result.next_steps"><li x-text="n"></li></template></ul></div>
                </div>
            </template>

            <template x-if="feature === 'reply' && result">
                <div class="space-y-2">
                    <p class="whitespace-pre-line text-gray-800" x-text="result.reply ?? '—'"></p>
                    <button type="button" class="btn-primary btn-sm" x-show="result.reply" @click="$dispatch('ai-draft', { text: result.reply })">Masukkan ke form balasan</button>
                    <p class="text-[11px] text-gray-500">Draf tidak terkirim otomatis. Periksa & edit sebelum menekan Kirim Balasan.</p>
                </div>
            </template>
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            function aiPanel(urlTemplate) {
                return {
                    loading: false, error: null, result: null, feature: null, style: 'professional',
                    async run(feature) {
                        this.loading = true; this.error = null; this.result = null; this.feature = feature;
                        try {
                            const res = await fetch(urlTemplate.replace('__F__', feature), {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                body: JSON.stringify({ style: this.style }),
                            });
                            const json = await res.json().catch(() => ({}));
                            if (!res.ok || !json.ok) { this.error = json.message || 'Permintaan AI gagal. Silakan coba lagi.'; return; }
                            this.result = json.data;
                        } catch (e) {
                            this.error = 'Tidak dapat menghubungi server. Periksa koneksi Anda.';
                        } finally { this.loading = false; }
                    },
                };
            }
        </script>
    @endpush
@endonce
