<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Export antrean ke XLSX dengan openspout (streaming, hemat memori untuk shared hosting).
 * Tidak memuat catatan internal maupun balasan — hanya data tiket.
 */
class TicketExportService
{
    public const MAX_ROWS = 10000;

    public const HEADERS = ['No. Tiket', 'Tanggal', 'Unit', 'Pelapor', 'Judul', 'Kategori', 'Subkategori', 'Prioritas', 'Status', 'PIC', 'Respons Pertama', 'Resolved', 'Closed'];

    /** @return array{path:string, rows:int} */
    public function toXlsx(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'tiket').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADERS, (new Style)->setFontBold()));

        $rows = 0;
        $query->with(['unit:id,code', 'reporter:id,name', 'category:id,name', 'subcategory:id,name', 'assignee:id,name'])
            ->limit(self::MAX_ROWS)
            ->chunk(500, function ($tickets) use ($writer, &$rows) {
                foreach ($tickets as $t) {
                    /** @var Ticket $t */
                    $writer->addRow(Row::fromValues([
                        $t->ticket_number,
                        $t->created_at->format('Y-m-d H:i'),
                        $t->unit->code,
                        $t->reporter?->name,
                        $t->title,
                        $t->category->name,
                        $t->subcategory?->name ?? '',
                        $t->priority->label(),
                        $t->status->label(),
                        $t->assignee?->name ?? '',
                        $t->first_response_at?->format('Y-m-d H:i') ?? '',
                        $t->resolved_at?->format('Y-m-d H:i') ?? '',
                        $t->closed_at?->format('Y-m-d H:i') ?? '',
                    ]));
                    $rows++;
                }
            });

        $writer->close();

        return ['path' => $path, 'rows' => $rows];
    }
}
