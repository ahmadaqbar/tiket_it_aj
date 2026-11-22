<?php

namespace App\Enums;

/**
 * PUBLIC: terlihat oleh pelapor dan IT. INTERNAL: hanya IT & IT Admin.
 */
enum ReplyVisibility: string
{
    case PUBLIC = 'PUBLIC';
    case INTERNAL = 'INTERNAL';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'Balasan Publik',
            self::INTERNAL => 'Catatan Internal',
        };
    }
}
