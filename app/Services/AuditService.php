<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditService
{
    /** Kolom yang tidak boleh pernah tersimpan di audit log. */
    private const SENSITIVE = ['password', 'remember_token', 'qr_token', 'current_password', 'password_confirmation'];

    public function log(
        string $action,
        ?Model $entity = null,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $userId = null,
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'description' => $description ? Str::limit($description, 250) : null,
            'old_values' => $this->clean($oldValues),
            'new_values' => $this->clean($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
        ]);
    }

    /**
     * Mencatat perubahan model: hanya atribut yang benar-benar berubah pada save() terakhir.
     *
     * Memakai Model::getChanges() (perbandingan Eloquent yang memahami cast), bukan perbandingan string,
     * agar nilai setara seperti 0 dan false tidak tercatat sebagai perubahan (BUG-01).
     * Harus dipanggil setelah $entity->save().
     */
    public function logChanges(string $action, Model $entity, array $before, ?string $description = null): ?AuditLog
    {
        $changedKeys = array_values(array_intersect(array_keys($entity->getChanges()), array_keys($before)));

        if ($changedKeys === [] && $description === null) {
            return null;
        }

        return $this->log(
            $action,
            $entity,
            $description,
            Arr::only($before, $changedKeys),
            Arr::only($entity->getAttributes(), $changedKeys),
        );
    }

    private function clean(array $values): ?array
    {
        $values = Arr::except($values, self::SENSITIVE);

        return $values === [] ? null : $values;
    }
}
