<x-layouts::app title="SLA Settings">
    <x-page-header title="SLA Settings" subtitle="Target waktu respons & penyelesaian per prioritas (dalam menit). Perubahan berlaku untuk tiket baru." />

    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($policies as $policy)
            <form method="POST" action="{{ route('admin.sla.update', $policy) }}" class="card space-y-4 p-5">
                @csrf @method('PUT')
                <div class="flex items-center justify-between">
                    <x-priority-badge :priority="$policy->priority" />
                    <x-active-badge :active="$policy->is_active" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label" for="r{{ $policy->id }}">Respons (menit)</label>
                        <input id="r{{ $policy->id }}" type="number" min="5" name="response_minutes" value="{{ $policy->response_minutes }}" class="form-input" required>
                        <p class="form-hint">= {{ \App\Models\SlaPolicy::formatMinutes($policy->response_minutes) }}</p>
                    </div>
                    <div>
                        <label class="form-label" for="s{{ $policy->id }}">Penyelesaian (menit)</label>
                        <input id="s{{ $policy->id }}" type="number" min="5" name="resolution_minutes" value="{{ $policy->resolution_minutes }}" class="form-input" required>
                        <p class="form-hint">= {{ \App\Models\SlaPolicy::formatMinutes($policy->resolution_minutes) }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <x-form.checkbox name="is_active" label="SLA aktif" :checked="$policy->is_active" />
                    <button class="btn-primary btn-sm" type="submit">Simpan</button>
                </div>
            </form>
        @endforeach
    </div>
    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif
</x-layouts::app>
