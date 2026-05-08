<?php

return [
    'alamat_klinik' => env('SUNATBOT_ALAMAT_KLINIK', 'Klinik Jati Elok – SunatBoy, Komp. Bumi Jati Elok Blok A1 No. 4-5, Jl. Raya Legok–Parung Panjang Km. 3, Malangnengah, Pagedangan, Tangerang, Banten 15330'),
    'link_maps'     => env('SUNATBOT_LINK_MAPS', 'https://maps.app.goo.gl/WDWMvex5F9YpgPaE9'),
    'nomor_rona'    => env('SUNATBOT_NOMOR_RONA', '0895-3692-69190'),
    // Public base URL where atika serves the bot_media directory.
    // Uploads land in atika/public/bot_media/ and the bot reaches them at
    // <media_base_url>/<filename>. Set SUNATBOT_MEDIA_BASE_URL in .env on prod.
    'media_base_url' => env('SUNATBOT_MEDIA_BASE_URL', ''),
    // After a media bubble (image/video) we wait at least this many seconds
    // before the next bubble so the file has time to land on the user's
    // WhatsApp — the WatZap API returns when the upload begins, not when
    // delivery completes, so a too-short gap lets the next text bubble
    // overtake the image. Non-media bubbles are sent back-to-back with no
    // delay; only this media-settle pause remains.
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
    // and the newest one wins — practically meaning "the bot waits
    // until the customer stops typing for N seconds, then replies".
    // Tune via SUNATBOT_BUFFER_WINDOW_SECONDS.
    'buffer_window_seconds' => (int) env('SUNATBOT_BUFFER_WINDOW_SECONDS', 5),
    // Intent slugs that should be silently dropped from the classifier
    // result whenever ANY non-generic intent also matched in the same
    // message — they are catch-all acknowledgments ("Silakan kak. Ada
    // yang bisa dibantu?") that add no signal once the client has
    // already asked something specific. Override via env as a
    // comma-separated list.
    'generic_intents' => array_values(array_filter(array_map('trim', explode(',', (string) env('SUNATBOT_GENERIC_INTENTS', 'konsultasi'))))),
    // Goodbye bubble shown when the customer sends one of the exit
    // keywords. Override via SUNATBOT_EXIT_MESSAGE in .env.
    'exit_message' => (string) env(
        'SUNATBOT_EXIT_MESSAGE',
        'Sesi konsultasi sunat ditutup. Untuk pertanyaan lain (daftar, jadwal, chat admin), silakan kirim pesan kembali ya kak. Terima kasih 🙏'
    ),
    // Bubble shown when the bot escalates to a human admin (medical
    // condition flagged in step 2.5, customer asked for admin/CS, or
    // closing reached at step 2.10). Override via SUNATBOT_HANDOVER_MESSAGE.
    'handover_message' => (string) env(
        'SUNATBOT_HANDOVER_MESSAGE',
        'Mohon ditunggu kak, admin kami akan menghubungi Anda untuk lanjut konsultasi 🙏'
    ),
    // Substring match (case-insensitive) on the riwayat_kesehatan answer.
    // If any keyword appears, requires_special_handling is set on the
    // session and the bot escalates instead of continuing.
    'special_handling_keywords' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SUNATBOT_SPECIAL_HANDLING_KEYWORDS',
        'pembekuan darah,kelainan jantung,jantung,autis,autisme,kebutuhan khusus,spektrum,down syndrome,cerebral palsy,epilepsi,kejang,hemofilia,thalasemia,leukemia,kanker'
    ))))),
    // Whole-word match (case-insensitive) on any incoming message — if
    // any keyword appears, escalate immediately. Used to honour explicit
    // requests like "minta admin", "mau ngomong sama operator".
    'admin_keywords' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SUNATBOT_ADMIN_KEYWORDS',
        'admin,cs,manusia,petugas,operator'
    ))))),
    // Same word-boundary match as admin_keywords. Fires when the
    // customer signals they are ready to book / register a sunat slot.
    // The bot has no scheduling or payment scope, so this hands off to
    // a human admin even before classify runs (deterministic safety
    // net). The mau_booking_jadwal bot_intent picks up looser phrasing
    // via the AI classifier as a fallback.
    'booking_keywords' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SUNATBOT_BOOKING_KEYWORDS',
        'mau booking,saya mau booking,mau daftar,saya mau daftar,daftar sunat,booking sunat,jadwalkan saya,ambil jadwal,ingin booking,ingin daftar,booking aja,daftar aja,minta jadwalkan,mau diatur jadwal'
    ))))),
    // After step 2.10 (price quote), if the customer's reply matches one
    // of these phrases (exact equality on trimmed/lowered text), close
    // the session and hand over. Otherwise their reply is treated as a
    // follow-up question.
    'closing_phrases' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SUNATBOT_CLOSING_PHRASES',
        'tidak ada,tidak,gak ada,nggak ada,sudah jelas,udah jelas,cukup,sudah,udah,oke,ok,siap,baik,paham'
    ))))),
    // Validation ranges for usia/BB capture (proposal §7).
    'usia_min'        => (int)   env('SUNATBOT_USIA_MIN', 1),
    'usia_max'        => (int)   env('SUNATBOT_USIA_MAX', 18),
    'berat_badan_min' => (float) env('SUNATBOT_BERAT_BADAN_MIN', 5),
    'berat_badan_max' => (float) env('SUNATBOT_BERAT_BADAN_MAX', 100),
];
