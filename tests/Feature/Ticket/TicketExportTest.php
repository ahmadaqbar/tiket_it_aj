<?php

namespace Tests\Feature\Ticket;

use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class TicketExportTest extends TestCase
{
    private function readRows(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();

        return $rows;
    }

    public function test_it_can_export_filtered_queue_to_xlsx_without_internal_notes(): void
    {
        $it = $this->makeIt();
        $dr = $this->makeTicket($this->makeUser(unitCode: 'DR'), ['title' => 'Tiket Driam']);
        $ho = $this->makeTicket($this->makeUser(unitCode: 'HO'), ['title' => 'Tiket Head Office']);
        $this->actingAs($it)->post("/tiket/{$dr->public_id}/balasan", ['message' => 'RAHASIA-INTERNAL', 'visibility' => 'INTERNAL']);

        $response = $this->actingAs($it)->get('/it/tiket/export?unit_id='.$this->unit('DR')->id);

        $response->assertOk()->assertDownload();
        $rows = $this->readRows($response->baseResponse->getFile()->getPathname());
        $this->assertSame('No. Tiket', $rows[0][0]);
        $this->assertCount(2, $rows, 'Header + 1 tiket DR');
        $this->assertSame($dr->ticket_number, $rows[1][0]);
        $this->assertStringNotContainsString('RAHASIA-INTERNAL', json_encode($rows));
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.exported']);
    }

    public function test_user_cannot_export(): void
    {
        $this->actingAs($this->makeUser())->get('/it/tiket/export')->assertForbidden();
    }
}
