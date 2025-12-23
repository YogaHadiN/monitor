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
    public function sendMessageToChat(string $chatId, string $text): array
    {
        $chatId = trim($chatId);
        $text   = trim($text);

        if ($chatId === '' || $text === '') {
            throw new \RuntimeException('sendMessageToChat: chatId/text kosong');
        }

        $scopeId = $this->amojoScopeOrFail();

        // Endpoint amojo (domain beda dari CRM)
        $url = $this->amojoBaseUrl . "/v2/{$scopeId}/chats/{$chatId}/messages";

        $payload = [
            'message' => [
                'type' => 'text',
                'text' => $text,
            ],
        ];

        $res = $this->request('POST', $url, $payload);
        $this->assertOk($res, 'Kommo sendMessageToChat');

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
}
