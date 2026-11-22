<?php

namespace App\Services;

use App\Enums\KbStatus;
use App\Enums\RoleCode;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketReply;
use App\Models\Unit;
use App\Models\User;
use App\Support\ReportPeriod;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Seluruh angka analytics dihitung dari database dengan agregasi SQL (tanpa data dummy).
 *
 * Definisi (konsisten untuk dashboard & laporan):
 * - Tiket masuk      : created_at dalam periode.
 * - Status ringkasan : status SAAT INI dari tiket yang masuk dalam periode.
 * - Tiket selesai    : resolved_at dalam periode (Resolved/Closed; tiket yang dibuka kembali tidak dihitung).
 * - Response time    : first_response_at − created_at, tiket masuk periode yang sudah direspons.
 * - Resolution time  : resolved_at − created_at, tiket masuk periode yang sudah selesai.
 * - SLA tercapai     : first_response_at ≤ response_due_at / resolved_at ≤ resolution_due_at.
 * - Isu berulang     : kombinasi unit + kategori + subkategori dengan ≥ RECURRING_MIN tiket masuk dalam periode.
 */
class AnalyticsService
{
    public const RECURRING_MIN = 3;

    public function compute(ReportPeriod $period, ?int $unitId = null): array
    {
        $created = fn (): Builder => Ticket::query()
            ->whereBetween('created_at', [$period->start, $period->end])
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId));

        $statusCounts = $created()->selectRaw('status, COUNT(*) total')->groupBy('status')->pluck('total', 'status');
        $byStatus = collect(TicketStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($statusCounts[$s->value] ?? 0)]);

        $resolvedInPeriod = Ticket::query()->whereBetween('resolved_at', [$period->start, $period->end])
            ->whereIn('status', TicketStatus::doneValues())
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))->count();

        $times = $created()->selectRaw('
                AVG(CASE WHEN first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, first_response_at) END) avg_response,
                SUM(first_response_at IS NOT NULL) responded,
                AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, resolved_at) END) avg_resolution,
                SUM(resolved_at IS NOT NULL) resolved,
                SUM(first_response_at IS NOT NULL AND response_due_at IS NOT NULL) response_sla_total,
                SUM(first_response_at IS NOT NULL AND response_due_at IS NOT NULL AND first_response_at <= response_due_at) response_sla_met,
                SUM(resolved_at IS NOT NULL AND resolution_due_at IS NOT NULL) resolution_sla_total,
                SUM(resolved_at IS NOT NULL AND resolution_due_at IS NOT NULL AND resolved_at <= resolution_due_at) resolution_sla_met,
                SUM(reopened_count > 0) reopened')->first();

        $total = (int) $byStatus->sum();
        $pct = fn ($met, $all) => (int) $all > 0 ? round(100 * (int) $met / (int) $all, 1) : null;

        return [
            'period' => ['label' => $period->label(), 'start' => $period->start, 'end' => $period->end, 'granularity' => $period->granularity()],
            'unit' => $unitId ? Unit::find($unitId) : null,
            'summary' => [
                'total' => $total,
                'by_status' => $byStatus->all(),
                'resolved_in_period' => $resolvedInPeriod,
                'open_now' => Ticket::query()->open()->when($unitId, fn ($q) => $q->where('unit_id', $unitId))->count(),
                'overdue_now' => Ticket::query()->open()->whereNotNull('resolution_due_at')->where('resolution_due_at', '<', now())
                    ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))->count(),
                'reopened' => (int) $times->reopened,
            ],
            'times' => [
                'avg_response_minutes' => $times->avg_response !== null ? (int) round($times->avg_response) : null,
                'responded' => (int) $times->responded,
                'avg_resolution_minutes' => $times->avg_resolution !== null ? (int) round($times->avg_resolution) : null,
                'resolved' => (int) $times->resolved,
                'response_sla_pct' => $pct($times->response_sla_met, $times->response_sla_total),
                'resolution_sla_pct' => $pct($times->resolution_sla_met, $times->resolution_sla_total),
            ],
            'trend' => $this->trend($period, $unitId),
            'by_unit' => $this->byUnit($created),
            'by_category' => $this->byCategory($created),
            'by_priority' => collect(TicketPriority::cases())->mapWithKeys(fn ($p) => [$p->label() => 0])
                ->merge($created()->selectRaw('priority, COUNT(*) total')->groupBy('priority')->pluck('total', 'priority')
                    ->mapWithKeys(fn ($v, $k) => [TicketPriority::from($k)->label() => (int) $v]))->all(),
            'recurring' => $this->recurring($created),
            'it_activity' => $this->itActivity($period, $unitId),
        ];
    }

    /** Tren tiket masuk vs selesai per hari/minggu/bulan (bucket kosong diisi 0). */
    private function trend(ReportPeriod $period, ?int $unitId): array
    {
        $g = $period->granularity();
        $expr = fn (string $col) => match ($g) {
            'day' => "DATE({$col})",
            'week' => "DATE(DATE_SUB({$col}, INTERVAL WEEKDAY({$col}) DAY))",
            default => "DATE_FORMAT({$col}, '%Y-%m-01')",
        };

        $created = Ticket::query()->whereBetween('created_at', [$period->start, $period->end])
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->selectRaw($expr('created_at').' bucket, COUNT(*) total')->groupBy('bucket')->pluck('total', 'bucket');
        $resolved = Ticket::query()->whereBetween('resolved_at', [$period->start, $period->end])->whereIn('status', TicketStatus::doneValues())
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->selectRaw($expr('resolved_at').' bucket, COUNT(*) total')->groupBy('bucket')->pluck('total', 'bucket');

        $start = match ($g) {
            'day' => $period->start,
            'week' => $period->start->startOfWeek(),
            default => $period->start->startOfMonth(),
        };
        $buckets = collect(CarbonPeriod::create($start, '1 '.$g, $period->end))->map(fn ($d) => $d->format('Y-m-d'));

        return [
            'granularity' => $g,
            'labels' => $buckets->map(fn ($d) => match ($g) {
                'day' => Carbon::parse($d)->translatedFormat('d M'),
                'week' => 'Mg '.Carbon::parse($d)->translatedFormat('d M'),
                default => Carbon::parse($d)->translatedFormat('M Y'),
            })->values()->all(),
            'created' => $buckets->map(fn ($d) => (int) ($created[$d] ?? 0))->values()->all(),
            'resolved' => $buckets->map(fn ($d) => (int) ($resolved[$d] ?? 0))->values()->all(),
        ];
    }

    private function byUnit(\Closure $created): array
    {
        $counts = $created()->selectRaw('unit_id, COUNT(*) total')->groupBy('unit_id')->pluck('total', 'unit_id');

        return Unit::ordered()->get(['id', 'code', 'name'])
            ->map(fn ($u) => ['code' => $u->code, 'name' => $u->name, 'total' => (int) ($counts[$u->id] ?? 0)])
            ->filter(fn ($r) => $r['total'] > 0)
            ->sortByDesc('total')->values()->all();
    }

    private function byCategory(\Closure $created): array
    {
        $counts = $created()->selectRaw('category_id, COUNT(*) total')->groupBy('category_id')->pluck('total', 'category_id');

        return Category::ordered()->get(['id', 'name'])
            ->map(fn ($c) => ['name' => $c->name, 'total' => (int) ($counts[$c->id] ?? 0)])
            ->filter(fn ($r) => $r['total'] > 0)->sortByDesc('total')->values()->all();
    }

    /** Isu berulang + saran pencegahan berbasis aturan (bukan klaim AI). */
    private function recurring(\Closure $created): array
    {
        $rows = $created()
            ->selectRaw('unit_id, category_id, subcategory_id, COUNT(*) total, MAX(created_at) last_at, SUM(reopened_count > 0) reopened')
            ->groupBy('unit_id', 'category_id', 'subcategory_id')
            ->havingRaw('COUNT(*) >= ?', [self::RECURRING_MIN])
            ->orderByDesc('total')->limit(15)->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $units = Unit::whereIn('id', $rows->pluck('unit_id'))->pluck('code', 'id');
        $categories = Category::whereIn('id', $rows->pluck('category_id'))->pluck('name', 'id');
        $subs = DB::table('subcategories')->whereIn('id', $rows->pluck('subcategory_id')->filter())->pluck('name', 'id');
        $kbCovered = DB::table('kb_articles')->where('status', KbStatus::PUBLISHED->value)->whereNotNull('subcategory_id')->pluck('subcategory_id')->flip();

        return $rows->map(function ($r) use ($units, $categories, $subs, $kbCovered) {
            $category = $categories[$r->category_id] ?? '-';
            $sub = $r->subcategory_id ? ($subs[$r->subcategory_id] ?? null) : null;

            return [
                'unit' => $units[$r->unit_id] ?? '-',
                'category' => $category,
                'subcategory' => $sub,
                'total' => (int) $r->total,
                'reopened' => (int) $r->reopened,
                'last_at' => Carbon::parse($r->last_at),
                'has_kb' => $r->subcategory_id ? $kbCovered->has($r->subcategory_id) : false,
                'suggestion' => $this->preventiveSuggestion($category, $sub, $units[$r->unit_id] ?? '-', (int) $r->total),
            ];
        })->all();
    }

    private function preventiveSuggestion(string $category, ?string $sub, string $unit, int $total): string
    {
        $topic = $sub ?? $category;
        $c = mb_strtolower($category);

        $action = match (true) {
            str_contains($c, 'hardware') => "pertimbangkan pemeriksaan/maintenance preventif perangkat {$topic}",
            str_contains($c, 'network') => "tinjau kualitas & konfigurasi jaringan ({$topic}), termasuk perangkat AP/router",
            str_contains($c, 'software') => "dokumentasikan solusi {$topic} di Knowledge Base dan pertimbangkan pelatihan pengguna",
            str_contains($c, 'cctv') => 'jadwalkan pengecekan kamera, DVR/NVR, dan kapasitas penyimpanan',
            str_contains($c, 'server') => "tinjau monitoring & backup {$topic}",
            str_contains($c, 'user') || str_contains($c, 'account') => 'tinjau prosedur akun dan sosialisasi pengguna',
            default => 'telusuri akar masalah dan dokumentasikan di Knowledge Base',
        };

        return "{$total} tiket {$topic} di unit {$unit} pada periode ini — {$action}.";
    }

    private function itActivity(ReportPeriod $period, ?int $unitId): array
    {
        $staff = User::query()->withRole(RoleCode::IT, RoleCode::IT_ADMIN)->withTrashed()->get(['id', 'name', 'role_id', 'is_active']);
        $ticketScope = fn ($q) => $unitId ? $q->whereHas('ticket', fn ($t) => $t->where('unit_id', $unitId)) : $q;

        $replies = TicketReply::query()->whereBetween('created_at', [$period->start, $period->end])->whereIn('user_id', $staff->pluck('id'))
            ->tap($ticketScope)->selectRaw("user_id, SUM(visibility = 'PUBLIC') public_replies, SUM(visibility = 'INTERNAL') internal_notes")
            ->groupBy('user_id')->get()->keyBy('user_id');
        $resolved = TicketHistory::query()->whereBetween('created_at', [$period->start, $period->end])
            ->where('action', TicketHistory::STATUS_CHANGED)->where('new_value', TicketStatus::RESOLVED->value)
            ->tap($ticketScope)->selectRaw('user_id, COUNT(*) total')->groupBy('user_id')->pluck('total', 'user_id');
        $openAssigned = Ticket::query()->open()->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->selectRaw('assigned_to, COUNT(*) total')->groupBy('assigned_to')->pluck('total', 'assigned_to');

        return $staff->map(fn ($u) => [
            'name' => $u->name,
            'public_replies' => (int) ($replies[$u->id]->public_replies ?? 0),
            'internal_notes' => (int) ($replies[$u->id]->internal_notes ?? 0),
            'resolved' => (int) ($resolved[$u->id] ?? 0),
            'open_assigned' => (int) ($openAssigned[$u->id] ?? 0),
        ])->filter(fn ($r) => $r['public_replies'] + $r['internal_notes'] + $r['resolved'] + $r['open_assigned'] > 0)
            ->sortByDesc('resolved')->values()->all();
    }

    /**
     * Rekomendasi tindak lanjut berbasis data (aturan sederhana, angka dapat diverifikasi).
     *
     * @return array<int, string>
     */
    public function recommendations(array $a): array
    {
        $r = [];
        $t = $a['times'];
        $s = $a['summary'];

        if ($s['total'] === 0) {
            return ['Belum ada tiket pada periode ini.'];
        }
        if ($t['resolution_sla_pct'] !== null && $t['resolution_sla_pct'] < 80) {
            $r[] = "Kepatuhan SLA penyelesaian {$t['resolution_sla_pct']}% (< 80%). Tinjau beban kerja tim, prioritas, atau target SLA.";
        }
        if ($t['response_sla_pct'] !== null && $t['response_sla_pct'] < 80) {
            $r[] = "Kepatuhan SLA respons {$t['response_sla_pct']}% (< 80%). Pertimbangkan jadwal piket untuk memantau antrean tiket baru.";
        }
        if ($s['overdue_now'] > 0) {
            $r[] = "{$s['overdue_now']} tiket terbuka saat ini melewati target SLA — prioritaskan penanganannya.";
        }
        $reopenRate = round(100 * $s['reopened'] / max(1, $s['total']), 1);
        if ($reopenRate >= 10) {
            $r[] = "{$reopenRate}% tiket dibuka kembali oleh pelapor. Perkuat verifikasi sebelum menandai Resolved.";
        }
        foreach (array_slice($a['recurring'], 0, 3) as $rec) {
            $r[] = ucfirst($rec['suggestion']).($rec['has_kb'] ? '' : ' Belum ada artikel Knowledge Base untuk topik ini.');
        }
        if (! empty($a['by_unit'][0]) && $s['total'] >= 10) {
            $top = $a['by_unit'][0];
            $share = round(100 * $top['total'] / $s['total']);
            if ($share >= 30) {
                $r[] = "Unit {$top['code']} menyumbang {$share}% tiket. Pertimbangkan kunjungan/pemeriksaan menyeluruh di unit tersebut.";
            }
        }

        return $r !== [] ? $r : ['Tidak ada temuan khusus pada periode ini. Pertahankan kinerja layanan.'];
    }

    public static function formatMinutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }
        if ($minutes < 60) {
            return $minutes.' mnt';
        }
        if ($minutes < 1440) {
            return intdiv($minutes, 60).' jam '.($minutes % 60).' mnt';
        }

        return intdiv($minutes, 1440).' hari '.intdiv($minutes % 1440, 60).' jam';
    }
}
