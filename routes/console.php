<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduler untuk shared hosting.
| Cron (cPanel), tiap menit:
|   * * * * * /usr/local/bin/php /home/<user>/almega-it/artisan schedule:run >> /dev/null 2>&1
|
| Tidak ada worker permanen: antrean database diproses singkat lalu berhenti
| (--stop-when-empty, --max-time) sehingga aman terhadap batas proses shared hosting.
*/

Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('queue:prune-failed --hours=720')->daily();

// Tiket Resolved tanpa konfirmasi pelapor ditutup otomatis (ticket.auto_close_days).
Schedule::command('tickets:auto-close')->hourly()->withoutOverlapping(10);
