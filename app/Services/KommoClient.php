<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class KommoClient
{
    protected string $subdomain;
    protected string $token;
    protected string $crmBaseUrl;
    protected string $amojoBaseUrl = 'https://amojo.kommo.com';

    protected string $amojoScopeId;

    public function __construct()
    {
        $this->subdomain     = (string) config('services.kommo.subdomain');
        $this->token         = (string) config('services.kommo.token');
        $this->amojoScopeId  = (string) config('services.kommo.amojo_scope_id');

        if ($this->subdomain === '' || $this->token === '') {
            throw new \RuntimeException('Kommo config belum lengkap (KOMMO_SUBDOMAIN / KOMMO_LONG_LIVED_TOKEN)');
        }

        // scope_id wajib kalau mau kirim pesan (amojo)
        if ($this->amojoScopeId === '') {
            // tetap boleh jalan untuk GET contact, tapi send message akan throw
            // biar developer langsung sadar
        }

        $this->crmBaseUrl = 'https://' . $this->subdomain . '.amocrm.com';
    }

    /* =========================================================
     * Core HTTP helper
     * ========================================================= */

    protected function request(string $method, string $url, array $payload = []): Response
    {
        $client = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(20);

        $method = strtoupper($method);

        return match ($method) {
            'GET'    => $client->get($url, $payload),
            'POST'   => $client->asJson()->post($url, $payload),
            'PUT'    => $client->asJson()->put($url, $payload),
            'PATCH'  => $client->asJson()->patch($url, $payload),
            'DELETE' => $client->delete($url, $payload),
            default  => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    protected function assertOk(Response $res, string $context): void
    {
        if (!$res->successful()) {
            throw new \RuntimeException(
                "{$context} HTTP {$res->status()} : {$res->body()}"
            );
        }
    }

    /* =========================================================
     * CRM API (amocrm.com)
     * ========================================================= */

    public function getContactById(string|int $contactId): array
    {
        $url = $this->crmBaseUrl . '/api/v4/contacts/' . $contactId;

        $res = $this->request('GET', $url);
        $this->assertOk($res, 'Kommo getContactById');

        return $res->json() ?? [];
    }

    /**
     * Ambil phone utama contact (field_code = PHONE)
     */
    public function getContactPrimaryPhone(string|int $contactId): ?string
    {
        $contact = $this->getContactById($contactId);

        $fields = data_get($contact, 'custom_fields_values', []);
        if (!is_array($fields)) {
            return null;
        }

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            if (($field['field_code'] ?? null) !== 'PHONE') {
                continue;
            }

            $values = $field['values'] ?? null;
            if (!is_array($values) || empty($values)) {
                return null;
            }

            $v0 = $values[0] ?? null;
            if (!is_array($v0)) {
                return null;
            }

            $val = $v0['value'] ?? null;

            return is_string($val) && trim($val) !== '' ? $val : null;
        }

        return null;
    }

    /* =========================================================
     * Chats API (amojo.kommo.com)
     * ========================================================= */

    protected function amojoScopeOrFail(): string
    {
        if ($this->amojoScopeId === '') {
            throw new \RuntimeException('KOMMO_AMOJO_SCOPE_ID belum di-set. Ambil dari URL attachment: https://amojo.kommo.com/v2/{SCOPE_ID}/attachments/...');
        }

        return $this->amojoScopeId;
    }

    /**
     * Kirim pesan text ke chat Kommo (Amojo).
     * Mengatasi error 404 "Cannot POST ...amocrm.com/chats/..."
     */
    public function sendMessageToConversation(string $conversationId, string $text): array
    {
        $conversationId = trim($conversationId);
        $text = trim($text);

        if ($conversationId === '' || $text === '') {
            throw new \RuntimeException('sendMessageToConversation: conversationId/text kosong');
        }

        $scopeId = $this->amojoScopeOrFail();

        // ✅ Endpoint yang benar untuk chats API custom channel
        $url = $this->amojoBaseUrl . "/v2/origin/custom/{$scopeId}";

        $payload = [
            'event_type' => 'new_message',
            'payload' => [
                'conversation_id' => $conversationId,
                'message' => [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
        ];

        $res = $this->request('POST', $url, $payload);
        $this->assertOk($res, 'Kommo sendMessageToConversation');

        return $res->json() ?? [];
    }

    /**
     * (Opsional) Kirim gambar ke chat.
     * NOTE: Cara kirim file bisa berbeda (upload dulu jadi attachment_id).
     * Ini template dasar bila endpoint akun dokter mendukung direct link.
     */
    public function sendImageToChat(string $chatId, string $imageUrl, ?string $caption = null): array
    {
        $chatId   = trim($chatId);
        $imageUrl = trim($imageUrl);

        if ($chatId === '' || $imageUrl === '') {
            throw new \RuntimeException('sendImageToChat: chatId/imageUrl kosong');
        }

        $scopeId = $this->amojoScopeOrFail();
        $url = $this->amojoBaseUrl . "/v2/{$scopeId}/chats/{$chatId}/messages";

        $payload = [
            'message' => [
                'type' => 'picture',
                'media' => [
                    'link' => $imageUrl,
                ],
            ],
        ];

        if (is_string($caption) && trim($caption) !== '') {
            $payload['message']['caption'] = trim($caption);
        }

        $res = $this->request('POST', $url, $payload);
        $this->assertOk($res, 'Kommo sendImageToChat');

        return $res->json() ?? [];
    }

    public function sendOutgoingTextViaChatsApi(string $conversationId, string $receiverPhone, string $text): array
    {
        $scopeId = (string) config('services.kommo.amojo_scope_id');
        if ($scopeId === '') {
            throw new \RuntimeException('KOMMO_AMOJO_SCOPE_ID belum di-set');
        }

        // IMPORTANT: endpoint yang benar (bukan amocrm.com)
        $url = "https://amojo.kommo.com/v2/origin/custom/{$scopeId}";

        $now = now();
        $timestamp = $now->timestamp;
        $msec = (int) round(microtime(true) * 1000);

        // msgid HARUS unik per pesan
        $msgid = 'kje-' . $msec . '-' . bin2hex(random_bytes(4));

        $payload = [
            'event_type' => 'new_message',
            'payload' => [
                'timestamp'      => $timestamp,
                'msec_timestamp' => $msec,
                'msgid'          => $msgid,
                'conversation_id'=> $conversationId,

                // OUTGOING: isi sender + receiver
                'sender' => [
                    'id'   => 'kje-bot',
                    'name' => 'KJE Bot',
                    // kalau kamu punya bot ref_id dari registrasi channel, taruh di sini
                    // 'ref_id' => '...'
                ],
                'receiver' => [
                    'id' => 'client-' . preg_replace('/\D+/', '', $receiverPhone),
                    'name' => 'Client',
                    'profile' => [
                        'phone' => $receiverPhone,
                    ],
                ],

                'message' => [
                    'type' => 'text',
                    'text' => $text,
                ],

                // kalau mau tanpa notifikasi (bulk import), bisa true
                'silent' => false,
            ],
        ];

        /**
         * PENTING BANGET:
         * Chats API (amojo/origin/custom) TIDAK memakai Bearer token long-lived seperti CRM v4.
         * Dia butuh header signature (HMAC-SHA1) pakai channel secret.
         * Kalau kamu belum punya channel_id + channel_secret (dari Kommo Support),
         * request akan gagal (bisa 401/403/404 tergantung).
         */
        $channelSecret = (string) env('KOMMO_CHANNEL_SECRET', '');
        if ($channelSecret === '') {
            throw new \RuntimeException('KOMMO_CHANNEL_SECRET belum di-set (Chats API butuh signature)');
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $signature = hash_hmac('sha1', $body, $channelSecret);

        $res = \Illuminate\Support\Facades\Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Signature'  => $signature,
            ])
            ->timeout(20)
            ->post($url, $payload);

        if (!$res->successful()) {
            throw new \RuntimeException("Kommo Chats API HTTP {$res->status()} : {$res->body()}");
        }

        return $res->json() ?? [];
    }
}
