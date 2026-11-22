<?php

namespace App\Http\Controllers;

use App\Support\Branding;
use Illuminate\Http\JsonResponse;

class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => Branding::appName(),
            'short_name' => Branding::shortName(),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f9fafb',
            'theme_color' => '#16432b',
            'lang' => 'id',
            'icons' => [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
