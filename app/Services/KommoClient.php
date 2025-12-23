<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class KommoClient
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $subdomain = config('services.kommo.subdomain');
        $this->token = (string) config('services.kommo.token');

        if (!$subdomain || !$this->token) {
            throw new \RuntimeException('Kommo config belum lengkap (subdomain / token)');
        }

        $this->baseUrl = 'https://' . $subdomain . '.amocrm.com';
    }

    public function getContactById(string|int $contactId): array
    {
        $res = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(15)
            ->get($this->baseUrl . '/api/v4/contacts/' . $contactId);

        if (!$res->ok()) {
            throw new \RuntimeException(
                'Kommo API error ' . $res->status() . ' : ' . $res->body()
            );
        }

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
            if (
                is_array($field)
                && ($field['field_code'] ?? null) === 'PHONE'
            ) {
                return $field['values'][0]['value'] ?? null;
            }
        }

        return null;
    }
}
