<?php

namespace Tests\Feature;

use Carbon\Carbon;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ReportTest extends TestCase
{
    private function seedTickets(): array
    {
        $this->travelTo(Carbon::parse('2026-10-15 09:00'));
        $it = $this->makeIt(['name' => 'Budi IT']);
        $ticket = $this->makeTicket($this->makeUser(attributes: ['name' => 'Rina Kasir'], unitCode: 'DR'), ['title' => 'Printer kasir macet']);
        $this->actingAs($it)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'RAHASIA-INTERNAL-NOTE', 'visibility' => 'INTERNAL']);
        $this->actingAs($it)->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'RESOLVED', 'message' => 'Roller printer dibersihkan.']);
        $this->travelBack();

        return [$it, $ticket];
    }

    private function sheets(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $out = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $out[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();

        return $out;
    }

    public function test_operational_excel_contains_ticket_and_it_response_without_internal_notes(): void
    {
        [$it, $ticket] = $this->seedTickets();

        $response = $this->actingAs($it)->get('/it/laporan/unduh?type=operational&format=xlsx&period=month&month=2026-10');

        $response->assertOk()->assertDownload('laporan-operasional-20261001-20261031.xlsx');
        $sheets = $this->sheets($response->baseResponse->getFile()->getPathname());
        $flat = json_encode($sheets);
        $this->assertStringContainsString($ticket->ticket_number, $flat);
        $this->assertStringContainsString('Roller printer dibersihkan.', $flat, 'Respons IT publik termasuk');
        $this->assertStringContainsString('Rina Kasir', $flat);
        $this->assertStringNotContainsString('RAHASIA-INTERNAL-NOTE', $flat);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.generated']);
    }

    public function test_management_excel_has_all_sections(): void
    {
        [$it] = $this->seedTickets();

        $response = $this->actingAs($it)->get('/it/laporan/unduh?type=management&format=xlsx&period=month&month=2026-10');
        $sheets = $this->sheets($response->baseResponse->getFile()->getPathname());

        $this->assertSame(['Ringkasan', 'Per Unit', 'Per Kategori', 'Tren', 'Aktivitas IT', 'Isu Berulang', 'Rekomendasi'], array_keys($sheets));
        $this->assertContains(['Tiket masuk', 1], $sheets['Ringkasan']);
        $this->assertSame(['DR', 'Driam Resort', 1], $sheets['Per Unit'][1]);
        $this->assertSame('Budi IT', $sheets['Aktivitas IT'][1][0]);
        $this->assertStringNotContainsString('RAHASIA-INTERNAL-NOTE', json_encode($sheets));
    }

    public function test_pdf_reports_are_valid_pdfs(): void
    {
        [$it] = $this->seedTickets();

        foreach (['operational', 'management'] as $type) {
            $response = $this->actingAs($it)->get("/it/laporan/unduh?type={$type}&format=pdf&period=month&month=2026-10");
            $response->assertOk();
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_unit_filter_and_empty_period(): void
    {
        [$it] = $this->seedTickets();

        $response = $this->actingAs($it)->get('/it/laporan/unduh?type=operational&format=xlsx&period=month&month=2026-10&unit_id='.$this->unit('HO')->id);
        $rows = $this->sheets($response->baseResponse->getFile()->getPathname())['Operasional'];
        // Judul, periode, header (baris kosong dilewati pembaca) — tanpa tiket DR.
        $this->assertCount(3, $rows);
        $this->assertStringNotContainsString('IT-DR-', json_encode($rows));

        $this->actingAs($it)->get('/it/laporan/unduh?type=management&format=pdf&period=month&month=2020-01')->assertOk();
    }

    public function test_reports_are_restricted_and_validated(): void
    {
        $this->actingAs($this->makeUser())->get('/it/laporan')->assertForbidden();
        $this->actingAs($this->makeUser())->get('/it/laporan/unduh?type=operational&format=pdf')->assertForbidden();
        $this->actingAs($this->makeIt())->get('/it/laporan')->assertOk()->assertSee('Unduh PDF');
        $this->actingAs($this->makeIt())->get('/it/laporan/unduh?type=x&format=doc')->assertSessionHasErrors(['type', 'format']);
    }
}
