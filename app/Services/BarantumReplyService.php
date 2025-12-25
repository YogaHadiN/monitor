<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BarantumReplyService
{
    /**
     * Kirim pesan via Barantum Chat API (text / image).
     *
     * $ctx minimal:
     * - chats_users_id (required)
     * - channel (optional, default 'wa')
     * - message_id (optional) untuk context reply
     *
     * Tambahan:
     * - image_url (optional) jika ada -> type otomatis 'image' + media.link = image_url
     * - message_type (optional) fallback type jika tidak ada image_url
     * - filename (optional) untuk media.filename
     * - chats_bot_id (optional) akan selalu dikirim ("" jika kosong) demi format stabil
     * - company_key (optional) prefer ini, fallback config('services.barantum.company_key')
     */
    public function replyText(array $ctx, string $text): array
    {
        $text = trim($text);

        // kalau text kosong dan tidak ada media, skip
        $imageUrl = (string)($ctx['image_url'] ?? '');
        if ($text === '' && $imageUrl === '') {
            return ['ok' => false, 'reason' => 'empty_text'];
        }

        $sendUrl = config('services.barantum.send_url', 'https://api-chat.barantum.com/api/v1/send-message');

        $usersId = (string)($ctx['chats_users_id'] ?? '');
        if ($usersId === '') {
            return ['ok' => false, 'reason' => 'missing_chats_users_id'];
        }

        // company_uuid: prefer ctx, fallback config
        $companyUuid = (string)($ctx['company_key'] ?? '');
        if ($companyUuid === '') {
            $companyUuid = (string)config('services.barantum.company_key');
        }
        if ($companyUuid === '') {
            return ['ok' => false, 'reason' => 'missing_company_key'];
        }

        $channel = (string)($ctx['channel'] ?? 'wa');

        $hasImage = $imageUrl !== '';

        // type: image jika ada image_url; else pakai message_type atau text
        $type = $hasImage ? 'image' : (string)($ctx['message_type'] ?? 'text');
        if ($type === '') {
            $type = 'text';
        }

        // ============== BODY SESUAI FORMAT CURL (STRUKTUR STABIL) ==============
        $body = [
            'chats_users_id'     => $usersId,
            'chats_message_text' => $text,
            'type'               => $type,
            'media'              => [ // SELALU ADA
                'link'     => $hasImage ? $imageUrl : '',
                'caption'  => $hasImage ? ($text !== '' ? $text : '') : '',
                'filename' => $hasImage ? (string)($ctx['filename'] ?? '') : '',
            ],
            'channel'            => $channel,
            'company_uuid'       => $companyUuid,
        ];

        Log::info('Body', $body);

        // context.message_id (optional)
        $msgId = (string)($ctx['message_id'] ?? '');
        if ($msgId !== '') {
            $body['context'] = [
                'message_id' => $msgId,
            ];
        }

        // chats_bot_id: Anda minta format stabil => field boleh selalu ada
        if (array_key_exists('chats_bot_id', $ctx)) {
            $bot = $ctx['chats_bot_id'];
            $body['chats_bot_id'] = ($bot === null) ? '' : $bot; // bisa "" / number / string
        } else {
            // kalau mau selalu ada walau ctx tidak mengirim
            $body['chats_bot_id'] = '';
        }

        $resp = Http::withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(20)
            ->post($sendUrl, $body);

        if (!$resp->ok()) {
            Log::error('BARANTUM_SEND_MESSAGE_FAILED', [
                'status' => $resp->status(),
                'body'   => $body,
                'resp'   => $resp->body(),
            ]);
        }

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
