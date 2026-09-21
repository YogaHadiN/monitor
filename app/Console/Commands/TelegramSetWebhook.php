<?php

namespace App\Console\Commands;

use App\Services\TelegramClient;
use Illuminate\Console\Command;

/**
 * Helper CLI utk register / cek / hapus webhook Telegram.
 *
 * Usage:
 *   php artisan telegram:webhook set                    # pakai TELEGRAM_WEBHOOK_URL dari .env
 *   php artisan telegram:webhook set --url=https://...  # override URL
 *   php artisan telegram:webhook info
 *   php artisan telegram:webhook delete
 */
class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:webhook {action=info : set|info|delete} {--url= : Override URL utk set}';
    protected $description = 'Register / cek / hapus webhook Telegram Bot';

    public function handle(TelegramClient $tg): int
    {
        if (!$tg->isEnabled()) {
            $this->error('TELEGRAM_BOT_TOKEN kosong. Set di .env dulu.');
            return self::FAILURE;
        }

        $action = $this->argument('action');

        return match ($action) {
            'set'    => $this->setWebhook($tg),
            'info'   => $this->info_($tg),
            'delete' => $this->delete($tg),
            default  => $this->badAction($action),
        };
    }

    private function setWebhook(TelegramClient $tg): int
    {
        $url = $this->option('url') ?: (string) config('telegram.webhook_url', '');
        if ($url === '') {
            $this->error('Set --url=... atau TELEGRAM_WEBHOOK_URL di .env');
            return self::FAILURE;
        }

        $secret = (string) config('telegram.webhook_secret', '');
        $res    = $tg->setWebhook($url, $secret ?: null);

        if (($res['ok'] ?? false) === true) {
            $this->info('Webhook set ke ' . $url);
            $this->line('Secret: ' . ($secret !== '' ? '(configured)' : '(none)'));
            return self::SUCCESS;
        }

        $this->error('Gagal set webhook: ' . json_encode($res));
        return self::FAILURE;
    }

    private function info_(TelegramClient $tg): int
    {
        $res = $tg->getWebhookInfo();
        if (($res['ok'] ?? false) !== true) {
            $this->error('Gagal ambil info: ' . json_encode($res));
            return self::FAILURE;
        }

        $r = $res['result'] ?? [];
        $this->line('URL              : ' . ($r['url'] ?? '-'));
        $this->line('Pending updates  : ' . ($r['pending_update_count'] ?? 0));
        $this->line('Last error date  : ' . (isset($r['last_error_date']) ? date('Y-m-d H:i:s', $r['last_error_date']) : '-'));
        $this->line('Last error msg   : ' . ($r['last_error_message'] ?? '-'));
        $this->line('Max connections  : ' . ($r['max_connections'] ?? '-'));
        $this->line('Allowed updates  : ' . implode(',', $r['allowed_updates'] ?? []));
        $this->line('Has secret token : ' . (($r['has_custom_certificate'] ?? false) || !empty($r['ip_address']) ? 'possibly' : '(check by request)'));
        return self::SUCCESS;
    }

    private function delete(TelegramClient $tg): int
    {
        if (!$this->confirm('Hapus webhook? Bot tidak akan terima update lagi.')) {
            return self::SUCCESS;
        }

        $res = $tg->deleteWebhook(false);
        if (($res['ok'] ?? false) === true) {
            $this->info('Webhook dihapus.');
            return self::SUCCESS;
        }

        $this->error('Gagal hapus: ' . json_encode($res));
        return self::FAILURE;
    }

    private function badAction(string $action): int
    {
        $this->error("Action '{$action}' tidak dikenal. Pakai: set | info | delete");
        return self::FAILURE;
    }
}
