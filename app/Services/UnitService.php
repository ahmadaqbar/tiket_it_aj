<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnitService
{
    private const AUDITED = ['code', 'name', 'description', 'is_active', 'sort_order'];

    public function __construct(private readonly AuditService $audit) {}

    public function create(array $data): Unit
    {
        return DB::transaction(function () use ($data) {
            $unit = new Unit(Arr::only($data, self::AUDITED));
            $unit->code = strtoupper($data['code']);
            $unit->qr_token = Unit::generateQrToken();
            $unit->qr_token_generated_at = now();
            $unit->save();

            $this->audit->log('unit.created', $unit, "Membuat unit {$unit->code}",
                newValues: Arr::only($unit->getAttributes(), self::AUDITED));

            return $unit;
        });
    }

    public function update(Unit $unit, array $data): Unit
    {
        return DB::transaction(function () use ($unit, $data) {
            $before = Arr::only($unit->getAttributes(), self::AUDITED);

            $fields = Arr::only($data, ['name', 'description', 'is_active', 'sort_order']);
            if (isset($data['code']) && $this->codeIsEditable($unit)) {
                $fields['code'] = strtoupper($data['code']);
            }
            $unit->fill($fields)->save();

            $this->audit->logChanges('unit.updated', $unit, $before);

            return $unit;
        });
    }

    public function regenerateQrToken(Unit $unit): Unit
    {
        return DB::transaction(function () use ($unit) {
            $unit->qr_token = Unit::generateQrToken();
            $unit->qr_token_generated_at = now();
            $unit->save();

            $this->audit->log('unit.qr_regenerated', $unit, "Regenerate token QR unit {$unit->code}");

            return $unit;
        });
    }

    /**
     * Kode unit menjadi bagian nomor tiket, sehingga terkunci setelah ada tiket.
     * (Tabel tickets ditambahkan pada Sprint 2.)
     */
    public function codeIsEditable(Unit $unit): bool
    {
        if (! Schema::hasTable('tickets')) {
            return true;
        }

        return ! DB::table('tickets')->where('unit_id', $unit->id)->exists();
    }
}
