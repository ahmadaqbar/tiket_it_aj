<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * USER hanya tiket miliknya sendiri; IT & IT Admin seluruh tiket (shared queue).
 */
class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isItStaff() || $ticket->user_id === $user->id;
    }

    /** Pembuatan laporan dilakukan oleh role USER (staf unit). */
    public function create(User $user): bool
    {
        return $user->isUser() && $user->activeUnits()->isNotEmpty();
    }
}
