#!/bin/bash
# Rollback script untuk fitur Sunat Bot.
# Migrations dipusatkan di project atika; bot code di project monitor.
# Jalankan dari root project monitor: ./rollback_sunat_bot.sh
set -e

MONITOR_DIR="$(cd "$(dirname "$0")" && pwd)"
ATIKA_DIR="$(cd "$MONITOR_DIR/../atika" && pwd)"

echo "==> Rolling back DB migrations dari atika ($ATIKA_DIR)..."
(
    cd "$ATIKA_DIR"
    php artisan migrate:rollback --path=database/migrations/2026_04_25_120000_add_enable_sunat_bot_to_tenants_table.php || true
    php artisan migrate:rollback --path=database/migrations/2026_04_25_100100_add_flagged_intent_to_messages_table.php || true
    php artisan migrate:rollback --path=database/migrations/2026_04_25_100000_create_bot_sessions_table.php || true
)

echo "==> Deleting bot files di monitor..."
rm -rf "$MONITOR_DIR/app/Services/Bot"
rm -f  "$MONITOR_DIR/app/Models/BotSession.php"
rm -rf "$MONITOR_DIR/public/bot-assets"

echo "==> Deleting migration files di atika..."
rm -f "$ATIKA_DIR/database/migrations/2026_04_25_100000_create_bot_sessions_table.php"
rm -f "$ATIKA_DIR/database/migrations/2026_04_25_100100_add_flagged_intent_to_messages_table.php"
rm -f "$ATIKA_DIR/database/migrations/2026_04_25_120000_add_enable_sunat_bot_to_tenants_table.php"

echo "==> Reverting WablasController hook..."
php -r '
$path = "'"$MONITOR_DIR"'/app/Http/Controllers/WablasController.php";
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
rm -f "$MONITOR_DIR/rollback_sunat_bot.sh"

echo "Done. Verify with: cd $MONITOR_DIR && git status; cd $ATIKA_DIR && git status"
