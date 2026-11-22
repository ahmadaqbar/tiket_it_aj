<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Rentang waktu analytics & laporan (zona waktu aplikasi).
 */
final class ReportPeriod
{
    public const PRESETS = [
        'today' => 'Hari Ini',
        'this_week' => 'Minggu Ini',
        'this_month' => 'Bulan Ini',
        'last_month' => 'Bulan Lalu',
        'this_year' => 'Tahun Ini',
        'month' => 'Bulan Tertentu',
        'custom' => 'Rentang Kustom',
    ];

    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $preset,
    ) {}

    public static function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(array_keys(self::PRESETS))],
            'month' => ['nullable', 'required_if:period,month', 'date_format:Y-m'],
            'from' => ['nullable', 'required_if:period,custom', 'date'],
            'to' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:from'],
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')],
        ];
    }

    public static function fromRequest(Request $request, string $default = 'this_month'): self
    {
        $data = $request->validate(self::rules());

        return self::make($data['period'] ?? $default, $data['from'] ?? null, $data['to'] ?? null, $data['month'] ?? null);
    }

    public static function make(string $preset, ?string $from = null, ?string $to = null, ?string $month = null): self
    {
        $now = CarbonImmutable::now();

        [$start, $end] = match ($preset) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'this_week' => [$now->startOfWeek(), $now->endOfWeek()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->startOfYear(), $now->endOfYear()],
            'month' => [CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth(), CarbonImmutable::createFromFormat('Y-m', $month)->endOfMonth()],
            'custom' => [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay()],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };

        // Batasi rentang maksimum 3 tahun agar query tetap ringan di shared hosting.
        if ($start->diffInDays($end) > 1100) {
            $start = $end->subYears(3)->startOfDay();
        }

        return new self($start, $end, array_key_exists($preset, self::PRESETS) ? $preset : 'this_month');
    }

    /** day | week | month — otomatis dari panjang rentang. */
    public function granularity(): string
    {
        $days = $this->start->diffInDays($this->end);

        return $days <= 31 ? 'day' : ($days <= 120 ? 'week' : 'month');
    }

    public function label(): string
    {
        return $this->start->translatedFormat('d M Y').' – '.$this->end->translatedFormat('d M Y');
    }

    public function toArray(): array
    {
        return ['period' => $this->preset, 'from' => $this->start->toDateString(), 'to' => $this->end->toDateString()];
    }
}
