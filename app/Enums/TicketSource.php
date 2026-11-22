<?php

namespace App\Enums;

enum TicketSource: string
{
    case WEB = 'WEB';
    case QR = 'QR';

    public function label(): string
    {
        return match ($this) {
            self::WEB => 'Web',
            self::QR => 'QR Code',
        };
    }
}
