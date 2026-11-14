<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isItAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isItAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isItAdmin();
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->isItAdmin() && ! $actor->is($target);
    }
}
