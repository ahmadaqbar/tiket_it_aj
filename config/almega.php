<?php

/*
| Dipertahankan untuk kompatibilitas mundur (Sprint 1). Konfigurasi aktif ada di config/helpdesk.php.
*/

return [
    'organization' => 'PT Almega Jaya Group',
    'production_url' => 'https://it.almegajaya.com',
    'initial_admin' => (require __DIR__.'/helpdesk.php')['initial_admin'],
];
