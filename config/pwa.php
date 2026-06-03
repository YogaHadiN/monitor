<?php

return [
    // Endpoint atika /api/push/trigger yang akan menerima HTTP POST
    // dari monitor saat ada pesan chat_admin baru. Atika yang punya
    // VAPID keys + daftar subscription PWA — monitor cuma jadi pemicu.
    'trigger_url'    => env('PUSH_TRIGGER_URL', 'https://www.kezia.id/api/push/trigger'),

    // Shared HMAC secret. SAMA persis dengan PUSH_TRIGGER_SECRET di
    // atika .env (lihat config/pwa.php sisi atika).
    'trigger_secret' => env('PUSH_TRIGGER_SECRET'),
];
