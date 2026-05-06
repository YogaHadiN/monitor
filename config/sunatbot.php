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
    // After a media bubble (image/video) we wait at least this many seconds
    // before the next bubble so the file has time to land on the user's
    // WhatsApp — the WatZap API returns when the upload begins, not when
    // delivery completes, so a too-short gap lets the next text bubble
    // overtake the image. Effective gap = max(media_settle, reply_delay).
    'media_settle_seconds' => (int) env('SUNATBOT_MEDIA_SETTLE_SECONDS', 5),
    // Max seconds an incoming webhook will wait for a previous in-flight
    // reply (same phone) to finish before giving up and dropping the new
    // message. Stay below the upstream webhook timeout so the provider
    // doesn't disconnect mid-wait.
    'reply_lock_wait_seconds' => (int) env('SUNATBOT_REPLY_LOCK_WAIT_SECONDS', 25),
    // Phone numbers (E.164 without "+") that bypass tenants.sunat_bot_enabled
    // so internal QA can keep testing the flow even while the tenant flag
    // is off. Override via SUNATBOT_WHITELIST_PHONES="6281...,6282...".
    'whitelist_phones' => array_values(array_filter(array_map('trim', explode(',', (string) env('SUNATBOT_WHITELIST_PHONES', '6281381912803'))))),
    // After receiving a webhook bubble we hold the message in the
    // bot_pending_buffers row for this many seconds before flushing the
    // combined text to the engine. Each new bubble during the window
    // resets the version, so the previously-scheduled flush job exits
    // and the newest one wins. Tune via SUNATBOT_BUFFER_WINDOW_SECONDS.
    'buffer_window_seconds' => (int) env('SUNATBOT_BUFFER_WINDOW_SECONDS', 30),
    // Intent slugs that should be silently dropped from the classifier
    // result whenever ANY non-generic intent also matched in the same
    // message — they are catch-all acknowledgments ("Silakan kak. Ada
    // yang bisa dibantu?") that add no signal once the client has
    // already asked something specific. Override via env as a
    // comma-separated list.
    'generic_intents' => array_values(array_filter(array_map('trim', explode(',', (string) env('SUNATBOT_GENERIC_INTENTS', 'konsultasi'))))),
];
