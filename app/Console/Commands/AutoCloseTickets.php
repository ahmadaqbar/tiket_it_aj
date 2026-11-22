<?php

namespace App\Console\Commands;

use App\Services\TicketStatusService;
use Illuminate\Console\Command;

class AutoCloseTickets extends Command
{
    protected $signature = 'tickets:auto-close';

    protected $description = 'Menutup tiket Resolved yang tidak dikonfirmasi pelapor dalam batas hari (setting ticket.auto_close_days)';

    public function handle(TicketStatusService $status): int
    {
        $closed = $status->autoCloseResolved();
        $this->info("{$closed->count()} tiket ditutup otomatis.");

        return self::SUCCESS;
    }
}
