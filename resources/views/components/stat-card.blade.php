@props(['label', 'value', 'tone' => 'gray', 'hint' => null])
@php
    $tones = [
        'gray' => 'text-gray-900',
        'brand' => 'text-brand-700',
        'blue' => 'text-blue-700',
        'amber' => 'text-amber-700',
        'green' => 'text-green-700',
    ];
@endphp
<div class="card p-4">
    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">{{ $label }}</p>
    <p class="mt-2 text-2xl font-semibold {{ $tones[$tone] ?? $tones['gray'] }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
