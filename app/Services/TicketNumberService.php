<?php

namespace App\Services;

use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Nomor tiket: {PREFIX}-{UNIT}-{YYYYMMDD}-{SEQ}, mis. IT-DR-20261122-001.
 * Sequence per unit per hari, dikunci dengan SELECT ... FOR UPDATE (InnoDB row lock).
 * WAJIB dipanggil di dalam transaksi yang sama dengan insert tiket, sehingga bila insert gagal
 * sequence ikut di-rollback dan nomor tidak pernah terpakai ganda.
 */
class TicketNumberService
{
    public const DEFAULT_PREFIX = 'IT';

    public function __construct(private readonly SettingService $settings) {}

    public function next(Unit $unit, ?CarbonInterface $at = null): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('TicketNumberService::next() harus dipanggil di dalam transaksi database.');
        }

        $at ??= now();
        $date = $at->toDateString();

        DB::table('ticket_sequences')->insertOrIgnore([
            'unit_id' => $unit->id,
            'ticket_date' => $date,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('ticket_sequences')
            ->where('unit_id', $unit->id)
            ->where('ticket_date', $date)
            ->lockForUpdate()
            ->first();

        $number = $sequence->last_number + 1;

        DB::table('ticket_sequences')
            ->where('id', $sequence->id)
            ->update(['last_number' => $number, 'updated_at' => now()]);

        return sprintf('%s-%s-%s-%03d', $this->prefix(), $unit->code, $at->format('Ymd'), $number);
    }

    /** Prefix dari setting (default "IT"); hanya huruf kapital & angka. */
    public function prefix(): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->settings->get('ticket.number_prefix', self::DEFAULT_PREFIX)));

        return $prefix !== '' ? substr($prefix, 0, 10) : self::DEFAULT_PREFIX;
    }
}
