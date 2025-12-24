<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Log;

class BarantumReplyService
{
    /**
     * Kirim pesan text via Barantum Chat API.
     *
     * $ctx minimal:
     * - chats_users_id (WA target)  => dari webhook: message_users_id
     * - channel (default 'wa')      => dari webhook: channel
     * - message_id (optional)       => dari webhook: message_id (untuk reply/context)
     *
     * Config:
     * - services.barantum.send_url
     * - services.barantum.company_key  (hash panjang, dipasang ke field company_uuid)
     */
    public function replyText(array $ctx, string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'reason' => 'empty_text'];
        }

        $sendUrl    = config('services.barantum.send_url', 'https://api-chat.barantum.com/api/v1/send-message');
        $companyKey = config('services.barantum.company_key');

        Log::info('-----------------');
        Log::info('company key');
        Log::info($companyKey);
        Log::info('-----------------');

        $usersId = (string)($ctx['chats_users_id'] ?? '');
        if ($usersId === '') {
            return ['ok' => false, 'reason' => 'missing_chats_users_id'];
        }

        if (empty($companyKey)) {
            return ['ok' => false, 'reason' => 'missing_company_key'];
        }

        $body = [
            'chats_users_id'     => $usersId,
            'channel'            => $ctx['channel'] ?? 'wa',
            'chats_message_text' => $text,
            'company_uuid'       => $companyKey, // <-- ini "company key" pada akun Anda
        ];

        // Optional: reply ke pesan inbound tertentu
        $msgId = (string)($ctx['message_id'] ?? '');
        if ($msgId !== '') {
            $body['context'] = ['message_id' => $msgId];
        }

        // Optional: chats_bot_id (Anda bilang boleh "" / optional)
        // - kalau tidak ada => skip
        // - kalau "" => kirim "" (jika Anda memang mau selalu kirim fieldnya)
        if (array_key_exists('chats_bot_id', $ctx)) {
            $bot = $ctx['chats_bot_id'];
            if ($bot === '') {
                $body['chats_bot_id'] = '';
            } elseif (!empty($bot)) {
                $body['chats_bot_id'] = (string)$bot;
            }
        }

        Log::info('=====================');
        Log::info('Parameter');
        Log::info('sendUrl');
        Log::info($sendUrl);
        Log::info('body');
        Log::info($body);
        Log::info('=====================');
        $resp = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])
            ->timeout(20)
            ->post($sendUrl, $body);

        return [
            'ok'     => $resp->ok(),
            'status' => $resp->status(),
            'body'   => $body,
            'resp'   => $this->safeJson($resp->body()),
            'reason' => $resp->ok() ? null : 'http_' . $resp->status(),
        ];
    }

    private function safeJson(string $raw)
    {
        $json = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $json : $raw;
    }
    }
