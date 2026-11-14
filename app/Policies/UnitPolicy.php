<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isItAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isItAdmin();
    }

    public function update(User $actor, Unit $unit): bool
    {
        return $actor->isItAdmin();
    }

    /** Unit tidak pernah dihapus — hanya dinonaktifkan. */
    public function delete(User $actor, Unit $unit): bool
    {
        return false;
    }
}
