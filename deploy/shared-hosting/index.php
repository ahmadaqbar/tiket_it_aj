<?php

/*
|--------------------------------------------------------------------------
| Entry point untuk shared hosting yang document root-nya WAJIB di public_html
|--------------------------------------------------------------------------
| Struktur:
|   /home/<cpanel_user>/almega-it/          ← aplikasi Laravel (di LUAR public_html, tidak dapat diakses publik)
|   /home/<cpanel_user>/public_html/it/     ← document root subdomain: isi folder almega-it/public + file INI
|
| Salin file ini ke public_html/it/index.php (menimpa index.php bawaan),
| lalu sesuaikan $appPath bila nama folder aplikasi berbeda.
*/

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Lokasi aplikasi Laravel. Default: dua tingkat di atas public_html/it → /home/<user>/almega-it
$appPath = realpath(__DIR__.'/../../almega-it');

if ($appPath === false || ! is_file($appPath.'/vendor/autoload.php')) {
    http_response_code(500);
    exit('Folder aplikasi tidak ditemukan. Periksa $appPath di index.php.');
}

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appPath.'/bootstrap/app.php';

// public_path() menunjuk ke folder ini (public_html/it) — dipakai untuk asset build (Vite), ikon PWA, dll.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
