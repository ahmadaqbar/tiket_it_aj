<?php

namespace App\Services;

use App\Enums\ReplyVisibility;
use App\Enums\RoleCode;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\Unit;
use App\Models\User;
use App\Support\Branding;
use App\Support\ReportPeriod;
use App\Support\SafeRow;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Laporan Operasional & Manajemen (PDF/Excel). Catatan internal TIDAK PERNAH dimasukkan ke laporan.
 */
class ReportService
{
    /** dompdf berat untuk ribuan baris di shared hosting — PDF dibatasi, Excel untuk data lengkap. */
    public const PDF_MAX_ROWS = 500; // ±7 dtk per 500 baris pada uji performa; aman untuk max_execution_time 30 dtk

    public const XLSX_MAX_ROWS = 10000;

    public function __construct(private readonly AnalyticsService $analytics) {}

    /** @return Collection<int, Ticket> */
    public function operationalRows(ReportPeriod $period, ?int $unitId, int $limit): Collection
    {
        $itIds = User::withTrashed()->withRole(RoleCode::IT, RoleCode::IT_ADMIN)->pluck('id');

        return Ticket::query()
            ->whereBetween('created_at', [$period->start, $period->end])
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->with(['unit:id,code,name', 'reporter:id,name', 'category:id,name', 'subcategory:id,name', 'assignee:id,name'])
            ->addSelect(['it_response' => TicketReply::query()->select('message')
                ->whereColumn('ticket_id', 'tickets.id')
                ->where('visibility', ReplyVisibility::PUBLIC->value)
                ->whereIn('user_id', $itIds)
                ->latest('id')->limit(1)])
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    public function managementData(ReportPeriod $period, ?int $unitId): array
    {
        $a = $this->analytics->compute($period, $unitId);
        $a['recommendations'] = $this->analytics->recommendations($a);

        return $a;
    }

    public function operationalPdf(ReportPeriod $period, ?int $unitId)
    {
        $rows = $this->operationalRows($period, $unitId, self::PDF_MAX_ROWS + 1);

        return Pdf::loadView('reports.pdf.operational', [
            'rows' => $rows->take(self::PDF_MAX_ROWS),
            'truncated' => $rows->count() > self::PDF_MAX_ROWS,
            'period' => $period,
            'unit' => $unitId ? Unit::find($unitId) : null,
            ...$this->header(),
        ])->setPaper('a4', 'landscape');
    }

    public function managementPdf(ReportPeriod $period, ?int $unitId)
    {
        return Pdf::loadView('reports.pdf.management', ['a' => $this->managementData($period, $unitId), ...$this->header()])->setPaper('a4');
    }

    /** @return string path file sementara */
    public function operationalXlsx(ReportPeriod $period, ?int $unitId): string
    {
        $writer = $this->writer($path);
        $writer->getCurrentSheet()->setName('Operasional');
        $bold = (new Style)->setFontBold();
        $writer->addRow(SafeRow::make(['Laporan Operasional IT — '.Branding::organization()], $bold));
        $writer->addRow(SafeRow::make(['Periode', $period->label()]));
        $writer->addRow(SafeRow::make([]));
        $writer->addRow(SafeRow::make(['No. Tiket', 'Tanggal', 'Unit', 'Pelapor', 'Kategori', 'Subkategori', 'Judul', 'Deskripsi', 'Respons IT', 'Prioritas', 'Status', 'PIC', 'Selesai', 'Durasi Penanganan (menit)'], $bold));

        foreach ($this->operationalRows($period, $unitId, self::XLSX_MAX_ROWS) as $t) {
            $writer->addRow(SafeRow::make([
                $t->ticket_number, $t->created_at->format('Y-m-d H:i'), $t->unit->code, $t->reporter?->name, $t->category->name,
                $t->subcategory?->name ?? '', $t->title, mb_substr($t->description, 0, 1000), mb_substr((string) $t->it_response, 0, 1000),
                $t->priority->label(), $t->status->label(), $t->assignee?->name ?? '', $t->resolved_at?->format('Y-m-d H:i') ?? '',
                $t->resolved_at ? (int) $t->created_at->diffInMinutes($t->resolved_at) : '',
            ]));
        }
        $writer->close();

        return $path;
    }

    public function managementXlsx(ReportPeriod $period, ?int $unitId): string
    {
        $a = $this->managementData($period, $unitId);
        $writer = $this->writer($path);
        $bold = (new Style)->setFontBold();
        $sheet = function (string $name, array $headers, iterable $rows, bool $first = false) use ($writer, $bold) {
            if (! $first) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $writer->getCurrentSheet()->setName($name);
            $writer->addRow(SafeRow::make($headers, $bold));
            foreach ($rows as $row) {
                $writer->addRow(SafeRow::make(array_values($row)));
            }
        };

        $s = $a['summary'];
        $t = $a['times'];
        $sheet('Ringkasan', ['Indikator', 'Nilai'], [
            ['Organisasi', Branding::organization()], ['Periode', $a['period']['label']], ['Unit', $a['unit']?->label() ?? 'Semua unit'],
            ['Tiket masuk', $s['total']], ['Tiket selesai (resolved dalam periode)', $s['resolved_in_period']],
            ...collect($s['by_status'])->map(fn ($v, $k) => ['Status '.TicketStatus::from($k)->label(), $v])->values()->all(),
            ['Terbuka saat ini', $s['open_now']], ['Melewati SLA saat ini', $s['overdue_now']], ['Dibuka kembali', $s['reopened']],
            ['Rata-rata respons (menit)', $t['avg_response_minutes'] ?? '-'], ['Rata-rata penyelesaian (menit)', $t['avg_resolution_minutes'] ?? '-'],
            ['SLA respons tercapai (%)', $t['response_sla_pct'] ?? '-'], ['SLA penyelesaian tercapai (%)', $t['resolution_sla_pct'] ?? '-'],
        ], first: true);
        $sheet('Per Unit', ['Kode', 'Unit', 'Tiket'], $a['by_unit']);
        $sheet('Per Kategori', ['Kategori', 'Tiket'], $a['by_category']);
        $sheet('Tren', ['Periode', 'Masuk', 'Selesai'], collect($a['trend']['labels'])->map(fn ($l, $i) => [$l, $a['trend']['created'][$i], $a['trend']['resolved'][$i]]));
        $sheet('Aktivitas IT', ['Staf', 'Balasan publik', 'Catatan internal', 'Diselesaikan', 'PIC tiket terbuka'], $a['it_activity']);
        $sheet('Isu Berulang', ['Unit', 'Kategori', 'Subkategori', 'Jumlah', 'Dibuka kembali', 'Terakhir', 'Ada artikel KB', 'Saran'],
            collect($a['recurring'])->map(fn ($r) => [$r['unit'], $r['category'], $r['subcategory'] ?? '', $r['total'], $r['reopened'], $r['last_at']->format('Y-m-d'), $r['has_kb'] ? 'Ya' : 'Tidak', $r['suggestion']]));
        $sheet('Rekomendasi', ['No', 'Rekomendasi'], collect($a['recommendations'])->map(fn ($r, $i) => [$i + 1, $r]));
        $writer->close();

        return $path;
    }

    private function writer(?string &$path): Writer
    {
        $path = tempnam(sys_get_temp_dir(), 'lap').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        return $writer;
    }

    private function header(): array
    {
        return ['organization' => Branding::organization(), 'appName' => Branding::appName(), 'generatedAt' => now()];
    }
}
