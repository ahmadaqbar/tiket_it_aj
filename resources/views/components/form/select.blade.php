@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false, 'hint' => null])
@php
    $id = $attributes->get('id', $name);
    $selected = (string) old($name, $value);
@endphp
<div>
    <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif
            {{ $attributes->except('id')->merge(['class' => 'form-input'.($errors->has($name) ? ' border-red-400' : '')]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($selected === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
    @error($name)<p class="form-error">{{ $message }}</p>@enderror
</div>
