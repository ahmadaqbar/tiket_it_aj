@props(['units', 'filters' => [], 'default' => 'this_month', 'action' => null])
@php $preset = $filters['period'] ?? $default; @endphp
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" x-data="{ period: '{{ $preset }}' }">
    <div>
        <label class="form-label text-xs" for="period">Periode</label>
        <select id="period" name="period" x-model="period" class="form-input">
            @foreach (\App\Support\ReportPeriod::PRESETS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select>
    </div>
    <div x-show="period === 'month'" x-cloak>
        <label class="form-label text-xs" for="month">Bulan</label>
        <input id="month" type="month" name="month" value="{{ $filters['month'] ?? now()->subMonthNoOverflow()->format('Y-m') }}" class="form-input" :disabled="period !== 'month'">
    </div>
    <div x-show="period === 'custom'" x-cloak>
        <label class="form-label text-xs" for="from">Dari</label>
        <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input" :disabled="period !== 'custom'">
    </div>
    <div x-show="period === 'custom'" x-cloak>
        <label class="form-label text-xs" for="to">Sampai</label>
        <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input" :disabled="period !== 'custom'">
    </div>
    <div>
        <label class="form-label text-xs" for="unit_id">Unit</label>
        <select id="unit_id" name="unit_id" class="form-input">
            <option value="">Semua unit</option>
            @foreach ($units as $u)<option value="{{ $u->id }}" @selected(($filters['unit_id'] ?? '') == $u->id)>{{ $u->code }} — {{ $u->name }}</option>@endforeach
        </select>
    </div>
    {{ $slot }}
</div>
