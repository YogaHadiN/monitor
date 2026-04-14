<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WatzapService
{
    public function sendText(string $phone, string $message): array
    {
        $phone = $this->normalizePhone($phone);
        $message = trim($message);

        if ($phone === '' || $message === '') {
            return ['ok' => false, 'reason' => 'invalid_phone_or_message'];
        }

        $response = Http::acceptJson()->post('https://api.watzap.id/v1/waba_send_message', [
            'api_key'      => env('WATZAP_TOKEN'),
            'number_key'   => env('WATZAP_NUMBER_KEY'),
            'phone_no'     => $phone,
            'message'      => $message,
        ]);

        if (!$response->ok()) {
            Log::error('WATZAP_SEND_TEXT_FAILED', [
                'status' => $response->status(),
                'phone'  => $phone,
                'resp'   => $response->body(),
            ]);
        }

        return [
            'ok'     => $response->ok(),
            'status' => $response->status(),
            'resp'   => $this->safeJson($response->body()),
            'reason' => $response->ok() ? null : 'http_' . $response->status(),
        ];
    }

    public function sendImage(string $phone, string $imageUrl, string $caption = ''): array
    {
        $phone = $this->normalizePhone($phone);
        $imageUrl = trim($imageUrl);

        if ($phone === '' || $imageUrl === '') {
            return ['ok' => false, 'reason' => 'invalid_phone_or_image'];
        }

        $response = Http::acceptJson()->post('https://api.watzap.id/v1/waba_send_image_url', [
            'api_key'      => env('WATZAP_TOKEN'),
            'number_key'   => env('WATZAP_NUMBER_KEY'),
            'phone_no'     => $phone,
            'url'          => $imageUrl,
            'caption'      => $caption,
        ]);

        if (!$response->ok()) {
            Log::error('WATZAP_SEND_IMAGE_FAILED', [
                'status' => $response->status(),
                'phone'  => $phone,
                'url'    => $imageUrl,
                'resp'   => $response->body(),
            ]);
        }

        return [
            'ok'     => $response->ok(),
            'status' => $response->status(),
            'resp'   => $this->safeJson($response->body()),
            'reason' => $response->ok() ? null : 'http_' . $response->status(),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);

        if (strpos($phone, '08') === 0) {
            return '62' . substr($phone, 1);
        }

        if (strpos($phone, '8') === 0) {
            return '62' . $phone;
        }

        return $phone;
    }

    private function safeJson(string $raw)
    {
        $json = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $json : $raw;
    }
}
