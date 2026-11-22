<?php

/*
| Konfigurasi produk "AI IT Ticketing & Helpdesk Kit".
| Edisi:
|   almega  — Edisi Internal PT Almega Jaya Group (default deployment ini)
|   product — Edisi produk untuk organisasi lain
| Nilai yang dapat diubah IT Admin lewat UI disimpan di tabel `settings` (System Settings);
| nilai di sini hanyalah default bila setting belum diisi.
*/

$edition = env('HELPDESK_EDITION', 'almega');

$defaults = [
    'almega' => [
        'app_name' => 'ALMEGA IT TICKETING SYSTEM',
        'short_name' => 'ALMEGA IT',
        'organization' => 'PT Almega Jaya Group',
        'support_team' => 'Divisi IT PT Almega Jaya Group',
    ],
    'product' => [
        'app_name' => 'AI IT Ticketing & Helpdesk',
        'short_name' => 'Helpdesk IT',
        'organization' => 'Nama Organisasi',
        'support_team' => 'Tim IT',
    ],
];

return [
    'edition' => $edition,

    'defaults' => $defaults[$edition] ?? $defaults['product'],

    'initial_admin' => [
        'name' => env('INITIAL_ADMIN_NAME', 'IT Administrator'),
        'username' => env('INITIAL_ADMIN_USERNAME', 'itadmin'),
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],
];
