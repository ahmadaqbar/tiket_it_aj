<?php

namespace App\Support;

use App\Services\SettingService;
use Illuminate\Support\Facades\Storage;

/**
 * Identitas aplikasi yang dapat dikonfigurasi (System Settings).
 * Default mengikuti edisi di config/helpdesk.php — Edisi Almega tampil persis seperti sebelumnya.
 */
class Branding
{
    public const DEFAULT_PRIMARY = '#1b5434';

    private static function setting(string $key, ?string $default = null): ?string
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

    /** @return array{email:?string, phone:?string, address:?string} */
    public static function contact(): array
    {
        return [
            'email' => self::setting('contact.email'),
            'phone' => self::setting('contact.phone'),
            'address' => self::setting('contact.address'),
        ];
    }

    public static function primaryColor(): string
    {
        $color = self::setting('branding.primary_color', self::DEFAULT_PRIMARY);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : self::DEFAULT_PRIMARY;
    }

    public static function logoUrl(): ?string
    {
        $path = self::setting('branding.logo_path');

        return $path && Storage::disk('local')->exists($path) ? route('branding.logo', ['v' => substr(md5($path), 0, 8)]) : null;
    }

    /**
     * CSS variable override untuk warna aksen. Kosong bila memakai warna default (tampilan Almega tidak berubah).
     */
    public static function themeCss(): string
    {
        $base = self::primaryColor();
        if ($base === self::DEFAULT_PRIMARY) {
            return '';
        }
        $shades = [50 => 0.94, 100 => 0.86, 200 => 0.72, 500 => 0.25, 600 => 0.12, 700 => 0.0, 800 => -0.2, 900 => -0.35];
        $vars = collect($shades)->map(fn ($amount, $shade) => "--color-brand-{$shade}:".self::mix($base, $amount).';')->implode('');

        return ":root{{$vars}}";
    }

    /** Positif = campur putih, negatif = campur hitam. */
    private static function mix(string $hex, float $amount): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $target = $amount >= 0 ? 255 : 0;
        $t = abs($amount);
        $c = fn ($v) => (int) round($v + ($target - $v) * $t);

        return sprintf('#%02x%02x%02x', $c($r), $c($g), $c($b));
    }
}
