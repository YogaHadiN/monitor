<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper Telegram Bot API. Semua method idempotent-safe:
 * kalau bot_token kosong → return ['ok' => false, 'reason' => ...],
 * jangan throw — supaya migration WA → TG bisa gradual (kode
 * caller boleh call TelegramClient meski token belum di-set).
 */
class TelegramClient
{
    private string $baseUrl;
    private string $token;
    private bool $enabled;
    private bool $debug;
    private int $timeout;

    public function __construct()
    {
        $this->token   = (string) config('telegram.bot_token', '');
        $this->enabled = $this->token !== '';
        $this->baseUrl = 'https://api.telegram.org/bot' . $this->token;
        $this->debug   = (bool) config('telegram.debug', false);
        $this->timeout = (int) config('telegram.timeout_seconds', 10);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Kirim pesan text. $extra bisa berisi inline_keyboard,
     * reply_to_message_id, parse_mode='Markdown|HTML', dll.
     */
    public function sendMessage(int|string $chatId, string $text, array $extra = []): array
    {
        return $this->call('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text'    => $text,
        ], $extra));
    }

    /**
     * Kirim foto. $photo bisa URL, file_id yg pernah di-upload, atau
     * multipart file (perlu ->attach dulu — belum handle di sini).
     */
    public function sendPhoto(int|string $chatId, string $photo, array $extra = []): array
    {
        return $this->call('sendPhoto', array_merge([
            'chat_id' => $chatId,
            'photo'   => $photo,
        ], $extra));
    }

    /**
     * Edit message reply markup (inline keyboard) — dipakai untuk
     * ubah tombol setelah user tap salah satu (mis. gray-out).
     */
    public function editMessageReplyMarkup(int|string $chatId, int $messageId, array $replyMarkup): array
    {
        return $this->call('editMessageReplyMarkup', [
            'chat_id'      => $chatId,
            'message_id'   => $messageId,
            'reply_markup' => $replyMarkup,
        ]);
    }

    /**
     * Callback query harus di-answer dalam 15 detik supaya loading
     * spinner di tombol hilang. Kalau tidak di-answer, user melihat
     * spinner terus.
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): array
    {
        $payload = ['callback_query_id' => $callbackQueryId];
        if (!is_null($text)) {
            $payload['text']       = $text;
            $payload['show_alert'] = $showAlert;
        }
        return $this->call('answerCallbackQuery', $payload);
    }

    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        $payload = [
            'url'             => $url,
            'allowed_updates' => ['message', 'callback_query'],
        ];
        if (!empty($secretToken)) {
            $payload['secret_token'] = $secretToken;
        }
        return $this->call('setWebhook', $payload);
    }

    public function deleteWebhook(bool $dropPendingUpdates = false): array
    {
        return $this->call('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates,
        ]);
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo', []);
    }

    public function getMe(): array
    {
        return $this->call('getMe', []);
    }

    /**
     * Deteksi error terminal (bot di-block, user deactivated, chat
     * hilang) → auto-clear no_telps.telegram_chat_id + telegram_users
     * link supaya outbound berikut auto-fallback ke WA via
     * ChannelDispatcher. User baru bisa re-enable dgn `/start` ulang
     * di bot.
     */
    private function handleTerminalError(int $status, string $description, mixed $chatId): void
    {
        if (empty($chatId)) return;

        $desc = mb_strtolower($description);
        $isTerminal =
            ($status === 403 && str_contains($desc, 'bot was blocked'))
            || ($status === 403 && str_contains($desc, 'user is deactivated'))
            || ($status === 403 && str_contains($desc, "bot can't initiate"))
            || ($status === 400 && str_contains($desc, 'chat not found'))
            || ($status === 400 && str_contains($desc, 'peer_id_invalid'));

        if (!$isTerminal) return;

        try {
            $chatIdInt = (int) $chatId;

            \App\Models\NoTelp::where('telegram_chat_id', $chatIdInt)
                ->update(['telegram_chat_id' => null]);

            if (class_exists(\App\Models\Pasien::class)) {
                \App\Models\Pasien::where('telegram_chat_id', $chatIdInt)
                    ->update(['telegram_chat_id' => null]);
            }

            \App\Models\TelegramUser::where('chat_id', $chatIdInt)
                ->update(['no_telp' => null, 'no_telp_shared_at' => null]);

            Log::info('TELEGRAM_TERMINAL_ERROR_CLEARED_CHAT_ID', [
                'chat_id'     => $chatIdInt,
                'status'      => $status,
                'description' => $description,
            ]);
        } catch (\Throwable $e) {
            Log::error('TELEGRAM_TERMINAL_CLEAR_FAILED', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function call(string $method, array $payload): array
    {
        if (!$this->enabled) {
            return ['ok' => false, 'reason' => 'telegram_bot_disabled'];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl . '/' . $method, $payload);

            $body = $response->json();

            if ($this->debug) {
                Log::info('TELEGRAM_CALL', [
                    'method'  => $method,
                    'payload' => $payload,
                    'status'  => $response->status(),
                    'body'    => $body,
                ]);
            }

            if (!$response->ok() || !($body['ok'] ?? false)) {
                Log::warning('TELEGRAM_CALL_FAILED', [
                    'method'      => $method,
                    'status'      => $response->status(),
                    'description' => $body['description'] ?? null,
                    'chat_id'     => $payload['chat_id'] ?? null,
                ]);

                $this->handleTerminalError(
                    (int) $response->status(),
                    (string) ($body['description'] ?? ''),
                    $payload['chat_id'] ?? null
                );

                return array_merge(['ok' => false], $body ?: []);
            }

            return $body;
        } catch (\Throwable $e) {
            Log::error('TELEGRAM_CALL_EXCEPTION', [
                'method'  => $method,
                'error'   => $e->getMessage(),
                'chat_id' => $payload['chat_id'] ?? null,
            ]);
            return ['ok' => false, 'reason' => 'exception', 'error' => $e->getMessage()];
        }
    }
}
