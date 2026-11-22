<?php

namespace App\Policies;

use App\Enums\TicketStatus;
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

    /** Antrean bersama & semua aksi pengelolaan tiket (status, prioritas, kategori, PIC). */
    public function manage(User $user, ?Ticket $ticket = null): bool
    {
        return $user->isItStaff();
    }

    /** Balasan publik: pelapor & IT, selama tiket belum Closed. */
    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && $ticket->status !== TicketStatus::CLOSED;
    }

    /** Catatan internal: hanya IT (tetap boleh pada tiket Closed untuk dokumentasi). */
    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return $user->isItStaff();
    }

    /** Konfirmasi selesai / buka kembali: hanya pelapor, hanya saat Resolved. */
    public function respondToResolution(User $user, Ticket $ticket): bool
    {
        return $ticket->user_id === $user->id && $ticket->status === TicketStatus::RESOLVED;
    }
}
