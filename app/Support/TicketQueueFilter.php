<?php

namespace App\Support;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Filter shared queue IT. Dipakai oleh halaman antrean dan export agar hasilnya identik.
 */
class TicketQueueFilter
{
    public const SORTS = [
        'newest' => 'Terbaru',
        'oldest' => 'Terlama',
        'priority' => 'Prioritas tertinggi',
        'updated' => 'Aktivitas terakhir',
        'due' => 'Batas SLA terdekat',
    ];

    /** @param  array<string, mixed>  $filters */
    public function __construct(public readonly array $filters) {}

    public static function fromRequest(Request $request): self
    {
        return new self($request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'reporter' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'unit_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'subcategory_id' => ['nullable', 'integer'],
            'assigned' => ['nullable', 'regex:/^(none|me|\d+)$/'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'overdue' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ]));
    }

    public function apply(Builder $query, User $viewer): Builder
    {
        $f = $this->filters;

        $query
            ->search($f['q'] ?? null)
            ->when($f['reporter'] ?? null, fn ($q, $name) => $q->whereHas('reporter', fn ($r) => $r->where('name', 'like', "%{$name}%")))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($f['unit_id'] ?? null, fn ($q, $v) => $q->where('unit_id', $v))
            ->when($f['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($f['subcategory_id'] ?? null, fn ($q, $v) => $q->where('subcategory_id', $v))
            ->when($f['date_from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($f['date_to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when(! empty($f['overdue']), fn ($q) => $q->open()->whereNotNull('resolution_due_at')->where('resolution_due_at', '<', now()));

        match ($f['assigned'] ?? null) {
            null => null,
            'none' => $query->whereNull('assigned_to'),
            'me' => $query->where('assigned_to', $viewer->id),
            default => $query->where('assigned_to', (int) $f['assigned']),
        };

        match ($f['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'URGENT' THEN 4 WHEN 'HIGH' THEN 3 WHEN 'MEDIUM' THEN 2 ELSE 1 END DESC")->orderBy('created_at'),
            'updated' => $query->orderByDesc('updated_at')->orderByDesc('id'),
            'due' => $query->orderByRaw('resolution_due_at IS NULL')->orderBy('resolution_due_at'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        return $query;
    }

    public function query(User $viewer): Builder
    {
        return $this->apply(Ticket::query(), $viewer);
    }

    public function get(string $key): mixed
    {
        return $this->filters[$key] ?? null;
    }

    public function isFiltered(): bool
    {
        return collect($this->filters)->except('sort')->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    }
}
