@php
    $messages = [
        'success' => ['bg-green-50 border-green-200 text-green-800', session('success')],
        'status' => ['bg-green-50 border-green-200 text-green-800', session('status')],
        'warning' => ['bg-amber-50 border-amber-200 text-amber-800', session('warning')],
        'error' => ['bg-red-50 border-red-200 text-red-800', session('error')],
    ];
@endphp
@foreach ($messages as $key => [$classes, $message])
    @if ($message)
        <div x-data="{ show: true }" x-show="show" role="alert"
             class="mb-4 flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm {{ $classes }}">
            <span>{{ $message }}</span>
            <button type="button" @click="show = false" class="shrink-0 opacity-60 hover:opacity-100" aria-label="Tutup">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>
    @endif
@endforeach
