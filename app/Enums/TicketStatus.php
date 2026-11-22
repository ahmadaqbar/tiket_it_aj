<?php

namespace App\Enums;

enum TicketStatus: string
{
    case NEW = 'NEW';
    case IN_PROGRESS = 'IN_PROGRESS';
    case PENDING = 'PENDING';
    case RESOLVED = 'RESOLVED';
    case CLOSED = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Baru',
            self::IN_PROGRESS => 'Dalam Proses',
            self::PENDING => 'Pending',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
        };
    }

    /** Kelas Tailwind badge — warna konsisten di seluruh aplikasi. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::NEW => 'bg-blue-50 text-blue-700 ring-blue-600/20',
            self::IN_PROGRESS => 'bg-amber-50 text-amber-800 ring-amber-600/20',
            self::PENDING => 'bg-purple-50 text-purple-700 ring-purple-600/20',
            self::RESOLVED => 'bg-green-50 text-green-700 ring-green-600/20',
            self::CLOSED => 'bg-gray-100 text-gray-600 ring-gray-500/20',
        };
    }

    /** Tiket yang masih membutuhkan tindakan IT. */
    public function isOpen(): bool
    {
        return in_array($this, [self::NEW, self::IN_PROGRESS, self::PENDING], true);
    }

    /** @return array<string> */
    public static function openValues(): array
    {
        return [self::NEW->value, self::IN_PROGRESS->value, self::PENDING->value];
    }

    /** @return array<string> */
    public static function doneValues(): array
    {
        return [self::RESOLVED->value, self::CLOSED->value];
    }
}
