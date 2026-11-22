<?php

namespace Tests\Feature\Ticket;

use App\Models\Ticket;
use App\Services\SettingService;
use App\Services\TicketNumberService;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class TicketNumberingTest extends TestCase
{
    public function test_format_is_prefix_unit_date_sequence(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 14));
        $ticket = $this->makeTicket($this->makeUser(unitCode: 'DR'));

        $this->assertSame('IT-DR-20261114-001', $ticket->ticket_number);
    }

    public function test_sequence_increments_per_unit_per_day(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 14));
        $dr = $this->makeUser(unitCode: 'DR');
        $ho = $this->makeUser(unitCode: 'HO');

        $this->assertSame('IT-DR-20261114-001', $this->makeTicket($dr)->ticket_number);
        $this->assertSame('IT-DR-20261114-002', $this->makeTicket($dr)->ticket_number);
        $this->assertSame('IT-HO-20261114-001', $this->makeTicket($ho)->ticket_number, 'Sequence terpisah per unit');

        $this->travelTo(now()->setDate(2026, 11, 15));
        $this->assertSame('IT-DR-20261115-001', $this->makeTicket($dr)->ticket_number, 'Sequence reset per hari');
    }

    public function test_sequence_expands_beyond_three_digits(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 14));
        $unit = $this->unit('DR');
        DB::table('ticket_sequences')->insert(['unit_id' => $unit->id, 'ticket_date' => '2026-11-14', 'last_number' => 999, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('IT-DR-20261114-1000', $this->makeTicket($this->makeUser(unitCode: 'DR'))->ticket_number);
    }

    public function test_failed_ticket_creation_rolls_back_sequence(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 14));
        $unit = $this->unit('DR');

        try {
            DB::transaction(function () use ($unit) {
                app(TicketNumberService::class)->next($unit);
                throw new RuntimeException('gagal insert');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame('IT-DR-20261114-001', $this->makeTicket($this->makeUser(unitCode: 'DR'))->ticket_number);
    }

    public function test_numbering_outside_transaction_is_refused(): void
    {
        $unit = $this->unit('DR');
        $this->expectException(LogicException::class);
        // RefreshDatabase membungkus test dalam transaksi; keluar dulu untuk mensimulasikan pemanggilan tanpa transaksi.
        DB::rollBack();
        try {
            app(TicketNumberService::class)->next($unit);
        } finally {
            DB::beginTransaction();
        }
    }

    public function test_ticket_numbers_are_unique_and_never_reused(): void
    {
        $user = $this->makeUser(unitCode: 'DR');
        $first = $this->makeTicket($user);
        $first->delete(); // soft delete

        $second = $this->makeTicket($user);

        $this->assertNotSame($first->ticket_number, $second->ticket_number);
        $this->assertSame(2, Ticket::withTrashed()->distinct()->count('ticket_number'));
    }

    public function test_prefix_is_configurable_without_changing_default(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 14));
        $user = $this->makeUser(unitCode: 'DR');
        $this->assertStringStartsWith('IT-', $this->makeTicket($user)->ticket_number);

        app(SettingService::class)->set('ticket.number_prefix', 'hd-');
        $this->assertSame('HD-DR-20261114-002', $this->makeTicket($user)->ticket_number);
    }
}
