<?php

namespace App\Enums;

enum TicketPriority: string
{
    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
    case URGENT = 'URGENT';

    public const DEFAULT = self::MEDIUM;

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Rendah',
            self::MEDIUM => 'Sedang',
            self::HIGH => 'Tinggi',
            self::URGENT => 'Mendesak',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::LOW => 'Tidak mengganggu pekerjaan',
            self::MEDIUM => 'Mengganggu sebagian pekerjaan',
            self::HIGH => 'Pekerjaan terhambat',
            self::URGENT => 'Operasional berhenti',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::LOW => 'bg-gray-50 text-gray-600 ring-gray-500/20',
            self::MEDIUM => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            self::HIGH => 'bg-orange-50 text-orange-700 ring-orange-600/20',
            self::URGENT => 'bg-red-50 text-red-700 ring-red-600/20',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
            self::URGENT => 4,
        };
    }
}
