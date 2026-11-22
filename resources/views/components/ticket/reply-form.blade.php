@props(['ticket', 'templates' => collect(), 'canInternal' => false, 'internalOnly' => false, 'maxKb' => 5120, 'maxFiles' => 5])
<form method="POST" action="{{ route('tickets.reply', $ticket) }}" enctype="multipart/form-data" class="space-y-3"
      x-data="{ visibility: '{{ $internalOnly ? 'INTERNAL' : old('visibility', 'PUBLIC') }}', message: @js(old('message', '')), sending: false }" @submit="sending = true">
    @csrf

    @if ($internalOnly)
        <input type="hidden" name="visibility" value="INTERNAL">
    @elseif ($canInternal)
        <div class="inline-flex rounded-lg bg-gray-100 p-1 text-sm" role="radiogroup" aria-label="Jenis pesan">
            <label class="cursor-pointer rounded-md px-3 py-1.5" :class="visibility === 'PUBLIC' ? 'bg-white font-medium shadow-sm text-gray-900' : 'text-gray-600'">
                <input type="radio" name="visibility" value="PUBLIC" x-model="visibility" class="sr-only"> Balasan Publik
            </label>
            <label class="cursor-pointer rounded-md px-3 py-1.5" :class="visibility === 'INTERNAL' ? 'bg-amber-100 font-medium text-amber-900 shadow-sm' : 'text-gray-600'">
                <input type="radio" name="visibility" value="INTERNAL" x-model="visibility" class="sr-only"> Catatan Internal
            </label>
        </div>
        <p class="text-xs" :class="visibility === 'INTERNAL' ? 'text-amber-700' : 'text-gray-500'"
           x-text="visibility === 'INTERNAL' ? 'Hanya terlihat oleh IT & IT Admin.' : 'Terlihat oleh pelapor.'"></p>
    @endif

    @if ($templates->isNotEmpty())
        <div x-show="visibility === 'PUBLIC'">
            <label for="quick_reply" class="sr-only">Template balasan cepat</label>
            <select id="quick_reply" class="form-input py-2 text-sm" @change="if ($event.target.value) { message = $event.target.value; $event.target.value = ''; $nextTick(() => $refs.msg.focus()) }">
                <option value="">Gunakan template balasan cepat…</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->message }}">{{ $template->title }} — {{ \Illuminate\Support\Str::limit($template->message, 50) }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <label for="message" class="sr-only">Pesan</label>
        <textarea id="message" name="message" rows="4" required maxlength="5000" x-ref="msg" x-model="message"
                  class="form-input" :class="visibility === 'INTERNAL' && 'border-amber-300 bg-amber-50/50'"
                  placeholder="Tulis pesan…"></textarea>
        @error('message')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt" aria-label="Lampiran"
               class="block w-full text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-medium hover:file:bg-gray-200 sm:max-w-xs">
        <button type="submit" class="btn-primary sm:ml-auto" :disabled="sending"
                :class="visibility === 'INTERNAL' && '!bg-amber-600 hover:!bg-amber-700'">
            @if ($canInternal || $internalOnly)
                <span x-text="sending ? 'Mengirim…' : (visibility === 'INTERNAL' ? 'Simpan Catatan Internal' : 'Kirim Balasan')"></span>
            @else
                <span x-text="sending ? 'Mengirim…' : 'Kirim Balasan'">Kirim Balasan</span>
            @endif
        </button>
    </div>
    <p class="form-hint">Lampiran opsional: maks. {{ $maxFiles }} file × {{ number_format($maxKb / 1024, 0) }} MB.</p>
    @error('attachments')<p class="form-error">{{ $message }}</p>@enderror
    @foreach ($errors->get('attachments.*') as $messages)<p class="form-error">{{ $messages[0] }}</p>@endforeach
</form>
