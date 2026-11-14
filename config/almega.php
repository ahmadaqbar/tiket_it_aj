<?php

/*
| Konfigurasi khusus ALMEGA IT TICKETING SYSTEM.
| Nilai runtime yang dapat diubah IT Admin disimpan di tabel `settings`.
*/

return [
    'organization' => 'PT Almega Jaya Group',
    'production_url' => 'https://it.almegajaya.com',

    'initial_admin' => [
        'name' => env('INITIAL_ADMIN_NAME', 'IT Administrator'),
        'username' => env('INITIAL_ADMIN_USERNAME', 'itadmin'),
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],
];
