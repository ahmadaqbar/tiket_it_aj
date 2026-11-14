@props(['name', 'label', 'checked' => false, 'value' => 1, 'hint' => null])
<div>
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked))
               class="h-4 w-4 rounded border-gray-300 text-brand-700 focus:ring-brand-500">
        {{ $label }}
    </label>
    @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
    @error($name)<p class="form-error">{{ $message }}</p>@enderror
</div>
