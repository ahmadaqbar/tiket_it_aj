<?php

namespace Database\Seeders;

use App\Enums\ReplyVisibility;
use App\Enums\RoleCode;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketReplyService;
use App\Services\TicketService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Tiket contoh untuk demo/development (DEMO_TICKETS, default 150, tersebar 60 hari terakhir).
 * Jalankan setelah DemoSeeder: php artisan db:seed --class=DemoTicketSeeder
 * Ditolak saat APP_ENV=production.
 */
class DemoTicketSeeder extends Seeder
{
    private const TITLES = [
        'Printer kasir tidak mencetak struk', 'WiFi tamu lambat', 'PC kasir hang saat transaksi', 'Email tidak bisa kirim lampiran',
        'CCTV parkir mati', 'POS tidak sinkron ke server', 'Reset password akun PMS', 'Internet putus di lobby',
        'Monitor resepsionis berkedip', 'Instalasi aplikasi akuntansi', 'Router kantor restart sendiri', 'Akses folder bersama ditolak',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoTicketSeeder tidak boleh dijalankan di production.');
        }

        $count = (int) env('DEMO_TICKETS', 150);
        $reporters = User::query()->withRole(RoleCode::USER)->with('units')->get()->filter(fn ($u) => $u->activeUnits()->isNotEmpty())->values();
        $itStaff = User::query()->withRole(RoleCode::IT, RoleCode::IT_ADMIN)->active()->get();
        $categories = Category::active()->with(['subcategories' => fn ($q) => $q->active()])->get();
        if ($reporters->isEmpty() || $itStaff->isEmpty()) {
            throw new RuntimeException('Jalankan DemoSeeder terlebih dahulu.');
        }

        $tickets = app(TicketService::class);
        $replies = app(TicketReplyService::class);
        mt_srand(2026);
        // Titik acuan diambil SEKALI: setTestNow di dalam loop mengubah hasil Carbon::now() berikutnya.
        $base = Carbon::now()->toImmutable();

        for ($i = 0; $i < $count; $i++) {
            $created = Carbon::instance($base->subMinutes(mt_rand(60, 60 * 24 * 60)));
            Carbon::setTestNow($created);

            // Beberapa unit & kategori sengaja lebih sering → isu berulang terlihat di analytics.
            $reporter = mt_rand(1, 4) === 1 ? $reporters->first() : $reporters->random();
            $category = mt_rand(1, 3) === 1 ? $categories->first() : $categories->random();
            $sub = $category->subcategories->isNotEmpty() ? $category->subcategories->random() : null;

            $ticket = $tickets->create($reporter, [
                'unit_id' => $reporter->activeUnits()->first()->id, 'category_id' => $category->id, 'subcategory_id' => $sub?->id,
                'title' => self::TITLES[array_rand(self::TITLES)], 'description' => 'Data contoh untuk demo. Kendala dilaporkan oleh staf unit.',
                'priority' => ['LOW', 'MEDIUM', 'MEDIUM', 'HIGH', 'URGENT'][mt_rand(0, 4)],
            ]);

            $roll = mt_rand(1, 100);
            if ($roll > 15) {
                $it = $itStaff->random();
                Carbon::setTestNow($created->copy()->addMinutes(mt_rand(5, 300)));
                $replies->add($ticket, $it, 'Baik, laporan sudah kami terima dan sedang kami cek.', ReplyVisibility::PUBLIC, notify: false);
                $ticket->forceFill(['assigned_to' => $it->id, 'status' => $roll > 85 ? 'PENDING' : 'IN_PROGRESS'])->save();
                if ($roll > 35 && $roll <= 85) {
                    $resolved = $created->copy()->addMinutes(mt_rand(30, 60 * 48));
                    if ($resolved->lessThan($base)) {
                        $ticket->forceFill(['status' => $roll > 55 ? 'CLOSED' : 'RESOLVED', 'resolved_at' => $resolved,
                            'closed_at' => $roll > 55 ? $resolved->copy()->addHours(4) : null])->save();
                    }
                }
            }
        }

        Carbon::setTestNow();
        $this->command?->info("{$count} tiket demo dibuat. Total tiket: ".Ticket::count());
    }
}
