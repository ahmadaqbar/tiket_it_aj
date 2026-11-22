<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Services\AnalyticsService;
use App\Support\ReportPeriod;
use Carbon\Carbon;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    /** Tiket dengan timestamp terkontrol. */
    private function ticketAt(string $created, array $attrs = [], string $unit = 'HO', array $data = []): Ticket
    {
        $this->travelTo(Carbon::parse($created));
        $ticket = $this->makeTicket($this->makeUser(unitCode: $unit), $data);
        $this->travelBack();
        $ticket->forceFill($attrs)->save();

        return $ticket->fresh();
    }

    private function compute(string $from, string $to, ?int $unitId = null): array
    {
        return app(AnalyticsService::class)->compute(ReportPeriod::make('custom', $from, $to), $unitId);
    }

    public function test_summary_counts_times_and_sla_are_accurate(): void
    {
        // 3 tiket November 2026, 1 tiket Oktober (di luar periode)
        $this->ticketAt('2026-11-02 08:00', ['status' => 'CLOSED', 'first_response_at' => '2026-11-02 08:20', 'resolved_at' => '2026-11-02 10:00', 'closed_at' => '2026-11-02 11:00',
            'response_due_at' => '2026-11-02 12:00', 'resolution_due_at' => '2026-11-03 08:00']);              // respons 20m, selesai 120m, SLA ok
        $this->ticketAt('2026-11-03 09:00', ['status' => 'RESOLVED', 'first_response_at' => '2026-11-03 13:40', 'resolved_at' => '2026-11-04 09:00',
            'response_due_at' => '2026-11-03 13:00', 'resolution_due_at' => '2026-11-04 08:00']);              // respons 280m (lewat), selesai 1440m (lewat)
        $this->ticketAt('2026-11-05 10:00', ['status' => 'PENDING', 'first_response_at' => '2026-11-05 10:30', 'response_due_at' => '2026-11-05 14:00']); // respons 30m
        $this->ticketAt('2026-10-20 10:00', ['status' => 'NEW']);

        $a = $this->compute('2026-11-01', '2026-11-30');

        $this->assertSame(3, $a['summary']['total']);
        $this->assertSame(['NEW' => 0, 'IN_PROGRESS' => 0, 'PENDING' => 1, 'RESOLVED' => 1, 'CLOSED' => 1], $a['summary']['by_status']);
        $this->assertSame(2, $a['summary']['resolved_in_period']);
        $this->assertSame(110, $a['times']['avg_response_minutes']);   // (20+280+30)/3
        $this->assertSame(780, $a['times']['avg_resolution_minutes']); // (120+1440)/2
        $this->assertSame(66.7, $a['times']['response_sla_pct']);      // 2 dari 3
        $this->assertSame(50.0, $a['times']['resolution_sla_pct']);    // 1 dari 2
    }

    public function test_reopened_tickets_are_not_counted_as_resolved(): void
    {
        $this->ticketAt('2026-11-02 08:00', ['status' => 'IN_PROGRESS', 'resolved_at' => null, 'reopened_count' => 1]);

        $a = $this->compute('2026-11-01', '2026-11-30');
        $this->assertSame(0, $a['summary']['resolved_in_period']);
        $this->assertSame(1, $a['summary']['reopened']);
    }

    public function test_by_unit_category_priority_and_unit_filter(): void
    {
        $this->ticketAt('2026-11-02 08:00', [], 'DR', ['priority' => 'URGENT']);
        $this->ticketAt('2026-11-02 09:00', [], 'DR');
        $this->ticketAt('2026-11-03 09:00', [], 'SH', ['category_id' => $this->category('Network & Internet')->id]);

        $a = $this->compute('2026-11-01', '2026-11-30');
        $this->assertSame([['code' => 'DR', 'name' => 'Driam Resort', 'total' => 2], ['code' => 'SH', 'name' => 'Summerville Hotel', 'total' => 1]], $a['by_unit']);
        $this->assertSame(['name' => 'Hardware', 'total' => 2], $a['by_category'][0]);
        $this->assertSame(1, $a['by_priority']['Mendesak']);
        $this->assertSame(2, $a['by_priority']['Sedang']);

        $dr = $this->compute('2026-11-01', '2026-11-30', $this->unit('DR')->id);
        $this->assertSame(2, $dr['summary']['total']);
    }

    public function test_trend_buckets_are_filled_and_granularity_adapts(): void
    {
        $this->ticketAt('2026-11-02 08:00', ['resolved_at' => '2026-11-04 08:00', 'status' => 'RESOLVED']);
        $this->ticketAt('2026-11-02 15:00');

        $daily = $this->compute('2026-11-01', '2026-11-07')['trend'];
        $this->assertSame('day', $daily['granularity']);
        $this->assertCount(7, $daily['labels']);
        $this->assertSame([0, 2, 0, 0, 0, 0, 0], $daily['created']);
        $this->assertSame([0, 0, 0, 1, 0, 0, 0], $daily['resolved']);

        $this->assertSame('week', $this->compute('2026-09-01', '2026-11-30')['trend']['granularity']);
        $monthly = $this->compute('2026-01-01', '2026-12-31')['trend'];
        $this->assertSame('month', $monthly['granularity']);
        $this->assertCount(12, $monthly['labels']);
        $this->assertSame(2, $monthly['created'][10]);
    }

    public function test_recurring_issues_need_three_tickets_same_unit_category_subcategory(): void
    {
        $printer = $this->subcategory('Hardware', 'Printer');
        foreach (['2026-11-02', '2026-11-09', '2026-11-16'] as $d) {
            $this->ticketAt($d.' 08:00', [], 'MK', ['subcategory_id' => $printer->id]);
        }
        $this->ticketAt('2026-11-17 08:00', [], 'HO', ['subcategory_id' => $printer->id]); // unit lain

        $recurring = $this->compute('2026-11-01', '2026-11-30')['recurring'];

        $this->assertCount(1, $recurring);
        $this->assertSame(['MK', 'Hardware', 'Printer', 3], [$recurring[0]['unit'], $recurring[0]['category'], $recurring[0]['subcategory'], $recurring[0]['total']]);
        $this->assertFalse($recurring[0]['has_kb']);
        $this->assertStringContainsString('maintenance preventif', $recurring[0]['suggestion']);
    }

    public function test_recommendations_are_data_driven(): void
    {
        $this->ticketAt('2026-11-03 09:00', ['status' => 'RESOLVED', 'first_response_at' => '2026-11-03 13:40', 'resolved_at' => '2026-11-04 09:00',
            'response_due_at' => '2026-11-03 13:00', 'resolution_due_at' => '2026-11-04 08:00']);

        $service = app(AnalyticsService::class);
        $recs = $service->recommendations($service->compute(ReportPeriod::make('custom', '2026-11-01', '2026-11-30')));

        $this->assertTrue(collect($recs)->contains(fn ($r) => str_contains($r, 'SLA penyelesaian 0%')));
        $this->assertSame(['Belum ada tiket pada periode ini.'], $service->recommendations($service->compute(ReportPeriod::make('custom', '2020-01-01', '2020-01-31'))));
    }

    public function test_period_presets(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22 10:00'));
        $this->assertSame(['2026-10-01', '2026-10-31'], [ReportPeriod::make('last_month')->start->toDateString(), ReportPeriod::make('last_month')->end->toDateString()]);
        $this->assertSame('2026-11-01', ReportPeriod::make('this_month')->start->toDateString());
        $this->assertSame('2026-01-01', ReportPeriod::make('this_year')->start->toDateString());
        $this->assertSame(['2026-02-01', '2026-02-28'], [ReportPeriod::make('month', month: '2026-02')->start->toDateString(), ReportPeriod::make('month', month: '2026-02')->end->toDateString()]);
    }

    public function test_analytics_page_access_and_render(): void
    {
        $this->ticketAt(now()->format('Y-m-d').' 08:00');

        $this->actingAs($this->makeUser())->get('/it/analytics')->assertForbidden();
        $this->actingAs($this->makeIt())->get('/it/analytics')->assertOk()->assertSee('IT Analytics')->assertSee('chart-trend')->assertSee('Rekomendasi Tindak Lanjut');
        $this->actingAs($this->makeIt())->get('/it/analytics?period=custom&from=2026-11-30&to=2026-11-01')->assertSessionHasErrors('to');
    }
}
