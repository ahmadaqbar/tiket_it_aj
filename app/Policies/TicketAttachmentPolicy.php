<?php

namespace App\Policies;

use App\Models\TicketAttachment;
use App\Models\User;

class TicketAttachmentPolicy
{
    public function __construct(private readonly TicketPolicy $tickets) {}

    /** Boleh mengunduh bila boleh melihat tiketnya; lampiran INTERNAL hanya untuk IT. */
    public function download(User $user, TicketAttachment $attachment): bool
    {
        if (! $attachment->ticket || ! $this->tickets->view($user, $attachment->ticket)) {
            return false;
        }

        return ! $attachment->isInternal() || $user->isItStaff();
    }
}
