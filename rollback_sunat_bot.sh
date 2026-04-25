#!/bin/bash
# Rollback script untuk fitur Sunat Bot.
# Jalankan dari root project monitor: ./rollback_sunat_bot.sh
set -e

cd "$(dirname "$0")"

echo "==> Rolling back DB migrations..."
php artisan migrate:rollback --path=database/migrations/2026_04_25_100100_add_flagged_intent_to_messages_table.php || true
php artisan migrate:rollback --path=database/migrations/2026_04_25_100000_create_bot_sessions_table.php || true

echo "==> Deleting bot files..."
rm -rf app/Services/Bot
rm -f  app/Models/BotSession.php
rm -f  database/migrations/2026_04_25_100000_create_bot_sessions_table.php
rm -f  database/migrations/2026_04_25_100100_add_flagged_intent_to_messages_table.php
rm -rf public/bot-assets

echo "==> Reverting WablasController hook..."
php -r '
$path = __DIR__ . "/app/Http/Controllers/WablasController.php";
$src  = file_get_contents($path);
$pattern = "/\n\s*\/\/ ===== SUNAT BOT START.*?\/\/ ===== SUNAT BOT END =====\n/s";
$new = preg_replace($pattern, "", $src);
if ($new === $src) {
    fwrite(STDERR, "WARNING: SUNAT BOT marker tidak ditemukan di WablasController. Cek manual.\n");
} else {
    file_put_contents($path, $new);
    echo "WablasController hook removed.\n";
}
'

echo "==> Removing this rollback script..."
rm -f rollback_sunat_bot.sh

echo "Done. Verify with: git status"
