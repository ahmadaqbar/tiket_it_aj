<?php

namespace Tests\Feature\Security;

use Carbon\Carbon;
use Tests\TestCase;
use ZipArchive;

/**
 * Regression: data dari user tidak boleh menjadi formula di file Excel (formula/CSV injection).
 */
class SpreadsheetInjectionTest extends TestCase
{
    private function sheetXml(string $path): string
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with($zip->getNameIndex($i), 'xl/worksheets/')) {
                $xml .= $zip->getFromIndex($i);
            }
        }
        $zip->close();

        return $xml;
    }

    public function test_queue_export_and_reports_write_user_text_as_plain_strings(): void
    {
        $payload = '=HYPERLINK("http://evil.test","klik")';
        $this->travelTo(Carbon::parse('2026-10-15 09:00'));
        $reporter = $this->makeUser(attributes: ['name' => '=cmd|"/c calc"!A1']);
        $ticket = $this->makeTicket($reporter, ['title' => $payload]);
        $it = $this->makeIt();
        $this->actingAs($it)->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'RESOLVED', 'message' => '=1+1']);
        $this->travelBack();

        $files = [
            $this->actingAs($it)->get('/it/tiket/export')->baseResponse->getFile()->getPathname(),
            $this->actingAs($it)->get('/it/laporan/unduh?type=operational&format=xlsx&period=month&month=2026-10')->baseResponse->getFile()->getPathname(),
        ];

        foreach ($files as $file) {
            $xml = $this->sheetXml($file);
            $this->assertStringContainsString('HYPERLINK', $xml, 'Teks tetap ada sebagai string');
            $this->assertStringNotContainsString('<f>', $xml, 'Tidak boleh ada sel formula');
        }
    }
}
