<?php

namespace App\Services;

use App\Models\NoTelp;
use Illuminate\Support\Facades\Log;

/**
 * Single decision point buat pilih channel outbound (Telegram vs WA).
 * Rule: kalau nomor punya `no_telps.telegram_chat_id` (user pernah
 * share via bot Telegram), prioritas kirim via Telegram. Kalau
 * Telegram gagal ATAU tidak ada chat_id, fallback ke WA (caller
 * yang handle actual WA send).
 *
 * Return array:
 *   ['sent_via_telegram' => bool, 'ok' => bool, 'result' => array]
 *
 * Kalau sent_via_telegram=false, caller lanjut kirim via WA path
 * yang sudah ada.
 */
class ChannelDispatcher
{
    private TelegramClient $tg;

    public function __construct(TelegramClient $tg)
    {
        $this->tg = $tg;
    }

    /**
     * Coba kirim text via Telegram. Return true kalau berhasil terkirim
     * via TG (caller boleh SKIP WA); false kalau caller harus lanjut
     * WA path.
     *
     * @param string $noTelp phone in E.164-ish format (62xxx)
     * @param string $message plain text
     * @param array  $telegramExtra optional (parse_mode, reply_markup, dll)
     */
    public function trySendTelegram(string $noTelp, string $message, array $telegramExtra = []): bool
    {
        if (!$this->tg->isEnabled()) return false;
        if ($noTelp === '' || $message === '') return false;

        $noTelp = $this->normalizePhone($noTelp);
        $chatId = NoTelp::withoutGlobalScopes()
            ->where('no_telp', $noTelp)
            ->value('telegram_chat_id');

        if (empty($chatId)) return false;

        // Convert WA markdown (*bold* _italic_ ~strike~ `code`) → HTML
        // Telegram supaya karakter tidak muncul literal. Kecuali caller
        // sudah set parse_mode.
        if (!isset($telegramExtra['parse_mode'])) {
            $message = $this->waMarkdownToTelegramHtml($message);
            $telegramExtra['parse_mode'] = 'HTML';
            $telegramExtra['disable_web_page_preview'] = $telegramExtra['disable_web_page_preview'] ?? true;
        }

        $result = $this->tg->sendMessage((int) $chatId, $message, $telegramExtra);
        $ok = (bool) ($result['ok'] ?? false);

        if (!$ok) {
            Log::warning('CHANNEL_DISPATCHER_TG_FAILED_FALLBACK_WA', [
                'no_telp' => $noTelp,
                'chat_id' => $chatId,
                'reason'  => $result['description'] ?? $result['reason'] ?? 'unknown',
            ]);
        }
        return $ok;
    }

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '' || $digits === null) return '';
        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }
        return $digits;
    }

    /**
     * WA markdown → Telegram HTML (mirror TelegramWablasBridge).
     */
    private function waMarkdownToTelegramHtml(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/```([\s\S]+?)```/', '<pre>$1</pre>', $text);
        $text = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $text);
        $text = preg_replace('/\*([^*\n]+)\*/', '<b>$1</b>', $text);
        $text = preg_replace('/~([^~\n]+)~/', '<s>$1</s>', $text);
        $text = preg_replace(
            '/(^|[\s.,!?;:(\[])_([^_\n]+)_(?=[\s.,!?;:)\]]|$)/u',
            '$1<i>$2</i>',
            $text
        );
        return $text;
    }
}
