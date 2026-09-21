<?php

return [
    // Token dari @BotFather. Format: "1234567890:ABC-DEF...".
    // Kosong = bot disabled (semua request TelegramClient jadi no-op).
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

    // Username bot (tanpa @). Dipakai untuk generate deep link
    // t.me/<username>?start=<payload>.
    'bot_username' => env('TELEGRAM_BOT_USERNAME', ''),

    // Secret di header X-Telegram-Bot-Api-Secret-Token. Kalau di-set,
    // TelegramController::webhook menolak request yg header-nya
    // tidak match. Kosong = skip verifikasi (dev / early phase).
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),

    // Public URL webhook — dipakai command telegram:set-webhook.
    // Prod: https://www.klinikjatielok.com/telegram/webhook (via
    // WAF exception, kayak Wablas).
    'webhook_url' => env('TELEGRAM_WEBHOOK_URL', ''),

    // Kalau true, TelegramClient log tiap outbound request + response
    // ke storage/logs/laravel.log. Prod default off.
    'debug' => (bool) env('TELEGRAM_DEBUG', false),

    // Timeout HTTP call ke api.telegram.org. Bot API biasanya < 1s
    // tapi upload media bisa lebih lama.
    'timeout_seconds' => (int) env('TELEGRAM_TIMEOUT_SECONDS', 10),

    // Token onboarding (deep link) berlaku berapa jam. Setelah expired,
    // pasien harus request link baru dari receptionist.
    'link_token_ttl_hours' => (int) env('TELEGRAM_LINK_TOKEN_TTL_HOURS', 24),
];
