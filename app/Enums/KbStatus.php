<?php

namespace App\Enums;

enum KbStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case ARCHIVED = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-gray-100 text-gray-700 ring-gray-500/20',
            self::PUBLISHED => 'bg-green-50 text-green-700 ring-green-600/20',
            self::ARCHIVED => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        };
    }
}
