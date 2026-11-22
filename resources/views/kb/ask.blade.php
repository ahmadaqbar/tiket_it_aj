<x-layouts::app title="Tanya Knowledge Base">
    <x-page-header title="Tanya Knowledge Base (AI)" subtitle="Pertanyaan dijawab dari artikel Knowledge Base yang Published. Jawaban adalah saran — verifikasi sebelum diterapkan." />

    @if ($aiUnavailable)
        <div class="card"><x-empty-state icon="sparkle" :message="$aiUnavailable"><a href="{{ route('kb.index') }}" class="btn-secondary mt-4">Cari manual di Knowledge Base</a></x-empty-state></div>
    @else
        <div class="card max-w-3xl p-5 sm:p-6" x-data="kbAsk('{{ route('kb.ask.submit') }}')">
            <form @submit.prevent="submit" class="space-y-3">
                <label for="question" class="form-label">Pertanyaan</label>
                <textarea id="question" x-model="question" rows="3" maxlength="1000" class="form-input" placeholder="Contoh: Bagaimana menangani printer kasir yang tidak bisa print?"></textarea>
                <button type="submit" class="btn-primary" :disabled="loading || question.trim().length < 5" x-text="loading ? 'Mencari jawaban…' : 'Tanyakan'"></button>
            </form>
            <p x-show="error" x-cloak class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" x-text="error"></p>
            <div x-show="result" x-cloak class="mt-6 space-y-4 border-t border-gray-100 pt-5 text-sm">
                <p class="rounded-lg px-3 py-2 text-xs font-medium"
                   :class="result?.based_on_knowledge_base ? 'bg-brand-50 text-brand-800' : 'bg-amber-50 text-amber-800'"
                   x-text="result?.based_on_knowledge_base ? 'Jawaban berdasarkan Knowledge Base internal.' : 'Tidak ada artikel Knowledge Base yang relevan — jawaban berikut adalah saran troubleshooting umum.'"></p>
                <p class="whitespace-pre-line text-gray-800" x-text="result?.answer"></p>
                <ol class="list-decimal space-y-1 pl-5 text-gray-700" x-show="result?.steps?.length"><template x-for="s in (result?.steps ?? [])"><li x-text="s"></li></template></ol>
                <div x-show="result?.sources?.length">
                    <p class="text-xs font-semibold text-gray-500 uppercase">Referensi</p>
                    <ul class="mt-1 space-y-1"><template x-for="src in (result?.sources ?? [])"><li><a :href="src.url" class="text-brand-700 underline" x-text="src.title"></a></li></template></ul>
                </div>
            </div>
        </div>
        @push('scripts')
            <script>
                function kbAsk(url) {
                    return {
                        question: '', loading: false, error: null, result: null,
                        async submit() {
                            this.loading = true; this.error = null; this.result = null;
                            try {
                                const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ question: this.question }) });
                                const json = await res.json().catch(() => ({}));
                                if (!res.ok || !json.ok) { this.error = json.message || (json.errors ? Object.values(json.errors)[0][0] : 'Permintaan gagal.'); return; }
                                this.result = json.data;
                            } catch (e) { this.error = 'Tidak dapat menghubungi server.'; } finally { this.loading = false; }
                        },
                    };
                }
            </script>
        @endpush
    @endif
</x-layouts::app>
