<?php

namespace App\Http\Controllers;

use App\Services\SettingService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan logo dari storage privat (tanpa storage:link — symlink sering tidak tersedia di shared hosting).
 */
class BrandingController extends Controller
{
    public function logo(SettingService $settings): StreamedResponse
    {
        $path = (string) $settings->get('branding.logo_path');
        abort_unless($path !== '' && str_starts_with($path, 'branding/') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
