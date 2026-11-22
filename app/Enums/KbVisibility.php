<?php

namespace App\Enums;

enum KbVisibility: string
{
    /** Hanya IT & IT Admin. */
    case INTERNAL = 'INTERNAL';
    /** Dapat dibaca semua user (pusat bantuan). */
    case PUBLIC = 'PUBLIC';

    public function label(): string
    {
        return match ($this) {
            self::INTERNAL => 'Internal IT',
            self::PUBLIC => 'Publik (User)',
        };
    }
}
