<?php

return [
    'alamat_klinik' => env('SUNATBOT_ALAMAT_KLINIK', 'Klinik Jati Elok – SunatBoy, Komp. Bumi Jati Elok Blok A1 No. 4-5, Jl. Raya Legok–Parung Panjang Km. 3, Malangnengah, Pagedangan, Tangerang, Banten 15330'),
    'link_maps'     => env('SUNATBOT_LINK_MAPS', 'https://maps.app.goo.gl/WDWMvex5F9YpgPaE9'),
    'nomor_rona'    => env('SUNATBOT_NOMOR_RONA', '0895-3692-69190'),
    // Public base URL where atika serves the bot_media directory.
    // Uploads land in atika/public/bot_media/ and the bot reaches them at
    // <media_base_url>/<filename>. Set SUNATBOT_MEDIA_BASE_URL in .env on prod.
    'media_base_url' => env('SUNATBOT_MEDIA_BASE_URL', ''),
    // Seconds to wait between consecutive reply bubbles so the chat doesn't
    // arrive as one instant burst. Applied between bubbles only, not before
    // the first one. Total handler time grows by delay × (bubbles - 1), so
    // keep it small enough to fit inside the webhook timeout.
    'reply_delay_seconds' => (int) env('SUNATBOT_REPLY_DELAY_SECONDS', 3),
];
