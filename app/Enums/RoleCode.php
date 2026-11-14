<?php

namespace App\Enums;

enum RoleCode: string
{
    case USER = 'USER';
    case IT = 'IT';
    case IT_ADMIN = 'IT_ADMIN';

    public function label(): string
    {
        return match ($this) {
            self::USER => 'User',
            self::IT => 'IT',
            self::IT_ADMIN => 'IT Admin',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::USER => 1,
            self::IT => 2,
            self::IT_ADMIN => 3,
        };
    }

    /** Role yang termasuk staf IT (melihat seluruh tiket). */
    public function isItStaff(): bool
    {
        return $this === self::IT || $this === self::IT_ADMIN;
    }
}
