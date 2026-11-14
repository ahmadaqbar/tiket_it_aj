<?php

namespace App\Services;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    private const AUDITED = ['role_id', 'name', 'username', 'email', 'phone', 'is_active'];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{role_id:int,name:string,username:string,email:?string,phone:?string,password:string,is_active?:bool,unit_ids?:array<int>,primary_unit_id?:?int}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                ...Arr::only($data, ['role_id', 'name', 'username', 'email', 'phone', 'password']),
                'is_active' => $data['is_active'] ?? true,
                'must_change_password' => true,
            ]);

            $this->syncUnits($user, $data['unit_ids'] ?? [], $data['primary_unit_id'] ?? null);

            $this->audit->log('user.created', $user, "Membuat user {$user->username}",
                newValues: [...Arr::only($user->getAttributes(), self::AUDITED), 'unit_ids' => $data['unit_ids'] ?? []]);

            return $user;
        });
    }

    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor) {
            $before = Arr::only($user->getAttributes(), self::AUDITED);
            $beforeUnits = $user->units()->pluck('units.id')->sort()->values()->all();

            $this->guardSelfLockout($user, $actor, $data);

            $user->fill(Arr::only($data, ['role_id', 'name', 'username', 'email', 'phone', 'is_active']));
            $user->save();

            $this->syncUnits($user, $data['unit_ids'] ?? [], $data['primary_unit_id'] ?? null);
            $afterUnits = $user->units()->pluck('units.id')->sort()->values()->all();

            $log = $this->audit->logChanges('user.updated', $user, $before);
            if ($beforeUnits !== $afterUnits) {
                $this->audit->log('user.units_changed', $user, "Mengubah unit user {$user->username}",
                    ['unit_ids' => $beforeUnits], ['unit_ids' => $afterUnits]);
            }

            if ($log && array_key_exists('is_active', $log->new_values ?? [])) {
                $this->audit->log($user->is_active ? 'user.activated' : 'user.deactivated', $user,
                    ($user->is_active ? 'Mengaktifkan' : 'Menonaktifkan')." user {$user->username}");
                if (! $user->is_active) {
                    $this->terminateSessions($user);
                }
            }

            return $user->refresh();
        });
    }

    public function resetPassword(User $user, string $newPassword): void
    {
        DB::transaction(function () use ($user, $newPassword) {
            $user->forceFill([
                'password' => $newPassword,
                'must_change_password' => true,
                'remember_token' => null,
            ])->save();

            $this->terminateSessions($user);
            $this->audit->log('user.password_reset', $user, "Reset password user {$user->username}");
        });
    }

    /**
     * Mengganti password sendiri lalu memutus sesi di perangkat lain (sesi saat ini dipertahankan).
     */
    public function changeOwnPassword(User $user, string $newPassword, ?string $currentSessionId = null): void
    {
        $user->forceFill([
            'password' => $newPassword,
            'must_change_password' => false,
        ])->save();

        $this->terminateSessions($user, exceptSessionId: $currentSessionId);
        $this->audit->log('auth.password_changed', $user, 'Mengubah password sendiri');
    }

    /**
     * Unit wajib untuk role USER; opsional untuk IT/IT Admin.
     *
     * @param  array<int>  $unitIds
     */
    public function syncUnits(User $user, array $unitIds, ?int $primaryUnitId): void
    {
        $unitIds = array_values(array_unique(array_map('intval', $unitIds)));

        if ($unitIds !== [] && ($primaryUnitId === null || ! in_array($primaryUnitId, $unitIds, true))) {
            $primaryUnitId = $unitIds[0];
        }

        $user->units()->sync(collect($unitIds)->mapWithKeys(fn ($id) => [
            $id => ['is_primary' => $id === $primaryUnitId],
        ])->all());

        $user->unsetRelation('units');
    }

    /**
     * Memutus sesi aktif user (session driver database) dan membatalkan cookie "ingat saya".
     */
    public function terminateSessions(User $user, ?string $exceptSessionId = null): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))
                ->delete();
        }
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    /** Mencegah IT Admin menonaktifkan / menurunkan role dirinya sendiri, atau admin aktif terakhir. */
    private function guardSelfLockout(User $user, User $actor, array $data): void
    {
        $adminRoleId = Role::idFor(RoleCode::IT_ADMIN);
        $losesAdmin = $user->role_id === $adminRoleId
            && ((isset($data['role_id']) && (int) $data['role_id'] !== $adminRoleId)
                || (array_key_exists('is_active', $data) && ! $data['is_active']));

        if (! $losesAdmin) {
            return;
        }

        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'role_id' => 'Anda tidak dapat menonaktifkan atau menurunkan role akun Anda sendiri.',
            ]);
        }

        $otherActiveAdmins = User::query()->active()->where('role_id', $adminRoleId)->whereKeyNot($user->id)->count();
        if ($otherActiveAdmins === 0) {
            throw ValidationException::withMessages([
                'role_id' => 'Minimal harus ada satu IT Admin aktif.',
            ]);
        }
    }

    public static function verifyPassword(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }
}
