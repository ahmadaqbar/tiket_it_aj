<?php

namespace App\Support;

use App\Services\SettingService;

/**
 * Identitas aplikasi yang dapat dikonfigurasi (System Settings).
 * Default mengikuti edisi di config/helpdesk.php — Edisi Almega tampil persis seperti sebelumnya.
 */
class Branding
{
    private static function setting(string $key, string $default): string
    {
        $value = app(SettingService::class)->get($key);

        return is_string($value) && trim($value) !== '' ? $value : $default;
    }

    public static function appName(): string
    {
        return self::setting('branding.app_name', config('helpdesk.defaults.app_name'));
    }

    public static function shortName(): string
    {
        return self::setting('branding.short_name', config('helpdesk.defaults.short_name'));
    }

    public static function organization(): string
    {
        return self::setting('organization.name', config('helpdesk.defaults.organization'));
    }

    public static function supportTeam(): string
    {
        return self::setting('organization.support_team', config('helpdesk.defaults.support_team'));
    }
}
