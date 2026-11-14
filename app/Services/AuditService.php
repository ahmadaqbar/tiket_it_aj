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
     * Mencatat perubahan model: hanya atribut yang berubah.
     */
    public function logChanges(string $action, Model $entity, array $before, ?string $description = null): ?AuditLog
    {
        $after = Arr::only($entity->getAttributes(), array_keys($before));
        $changedKeys = array_keys(array_filter(
            $after,
            fn ($value, $key) => (string) ($before[$key] ?? '') !== (string) ($value ?? ''),
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changedKeys === [] && $description === null) {
            return null;
        }

        return $this->log(
            $action,
            $entity,
            $description,
            Arr::only($before, $changedKeys),
            Arr::only($after, $changedKeys),
        );
    }

    private function clean(array $values): ?array
    {
        $values = Arr::except($values, self::SENSITIVE);

        return $values === [] ? null : $values;
    }
}
