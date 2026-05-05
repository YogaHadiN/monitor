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
            'api_key'     => env('WATZAP_TOKEN'),
            'phone_no'    => $phone,
            'message'     => $message,
            'apps_source' => null,
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

        // WatZap's waba_send_image_url returns HTTP 200 even when the supplied
        // URL 404s, so without this guard a missing media file is silently
        // dropped and the caller never falls back to the text bubble.
        $headStatus = $this->probeUrl($imageUrl);
        if ($headStatus < 200 || $headStatus >= 300) {
            Log::error('WATZAP_SEND_IMAGE_URL_UNREACHABLE', [
                'phone' => $phone,
                'url'   => $imageUrl,
                'head'  => $headStatus,
            ]);
            return ['ok' => false, 'reason' => 'image_url_unreachable_' . $headStatus];
        }

        $payload = [
            'api_key'  => env('WATZAP_TOKEN'),
            'phone_no' => $phone,
            'url'      => $imageUrl,
            'caption'  => $caption,
        ];

        Log::info('WATZAP_SEND_IMAGE_REQUEST', [
            'phone'   => $phone,
            'url'     => $imageUrl,
            'caption' => $caption,
            'payload' => $this->redactPayload($payload),
        ]);

        $response = Http::acceptJson()->post('https://api.watzap.id/v1/waba_send_image_url', $payload);

        $body  = $this->safeJson($response->body());
        $apiOk = $response->ok() && $this->bodyIndicatesSuccess($body);

        if (!$apiOk) {
            Log::error('WATZAP_SEND_IMAGE_FAILED', [
                'status' => $response->status(),
                'phone'  => $phone,
                'url'    => $imageUrl,
                'resp'   => $body,
            ]);
        } else {
            Log::info('WATZAP_SEND_IMAGE_OK', [
                'phone' => $phone,
                'url'   => $imageUrl,
                'resp'  => $body,
            ]);
        }

        return [
            'ok'     => $apiOk,
            'status' => $response->status(),
            'resp'   => $body,
            'reason' => $apiOk ? null : 'http_' . $response->status(),
        ];
    }

    public function sendVideo(string $phone, string $videoUrl, string $caption = ''): array
    {
        $phone = $this->normalizePhone($phone);
        $videoUrl = trim($videoUrl);

        if ($phone === '' || $videoUrl === '') {
            return ['ok' => false, 'reason' => 'invalid_phone_or_video'];
        }

        $headStatus = $this->probeUrl($videoUrl);
        if ($headStatus < 200 || $headStatus >= 300) {
            Log::error('WATZAP_SEND_VIDEO_URL_UNREACHABLE', [
                'phone' => $phone,
                'url'   => $videoUrl,
                'head'  => $headStatus,
            ]);
            return ['ok' => false, 'reason' => 'video_url_unreachable_' . $headStatus];
        }

        $payload = [
            'api_key'  => env('WATZAP_TOKEN'),
            'phone_no' => $phone,
            'url'      => $videoUrl,
            'caption'  => $caption,
        ];

        Log::info('WATZAP_SEND_VIDEO_REQUEST', [
            'phone'   => $phone,
            'url'     => $videoUrl,
            'caption' => $caption,
            'payload' => $this->redactPayload($payload),
        ]);

        $response = Http::acceptJson()->post('https://api.watzap.id/v1/waba_send_file_url', $payload);

        $body  = $this->safeJson($response->body());
        $apiOk = $response->ok() && $this->bodyIndicatesSuccess($body);

        if (!$apiOk) {
            Log::error('WATZAP_SEND_VIDEO_FAILED', [
                'status' => $response->status(),
                'phone'  => $phone,
                'url'    => $videoUrl,
                'resp'   => $body,
            ]);
        } else {
            Log::info('WATZAP_SEND_VIDEO_OK', [
                'phone' => $phone,
                'url'   => $videoUrl,
                'resp'  => $body,
            ]);
        }

        return [
            'ok'     => $apiOk,
            'status' => $response->status(),
            'resp'   => $body,
            'reason' => $apiOk ? null : 'http_' . $response->status(),
        ];
    }

    private function redactPayload(array $payload): array
    {
        if (isset($payload['api_key']) && is_string($payload['api_key']) && $payload['api_key'] !== '') {
            $payload['api_key'] = substr($payload['api_key'], 0, 4) . '***';
        }
        return $payload;
    }

    private function probeUrl(string $url): int
    {
        try {
            $resp = Http::timeout(5)->withOptions(['allow_redirects' => true])->head($url);
            return $resp->status();
        } catch (\Throwable $e) {
            Log::warning('WATZAP_URL_PROBE_FAILED', ['url' => $url, 'err' => $e->getMessage()]);
            return 0;
        }
    }

    private function bodyIndicatesSuccess($body): bool
    {
        if (!is_array($body)) {
            return true;
        }
        // WatZap returns status as a numeric string ("200"), not an int, so
        // is_numeric must be checked before is_string — otherwise "200"
        // hits the string branch and fails the OK/SUCCESS whitelist.
        if (array_key_exists('status', $body)) {
            $s = $body['status'];
            if (is_bool($s))     return $s === true;
            if (is_numeric($s)) {
                $n = (int) $s;
                return $n === 1 || ($n >= 200 && $n < 300);
            }
            if (is_string($s))   return in_array(strtolower($s), ['ok', 'success', 'sent'], true);
        }
        if (array_key_exists('ack', $body) && is_string($body['ack'])) {
            return strtolower($body['ack']) === 'successfully';
        }
        if (array_key_exists('ack_status', $body)) {
            return strtolower((string) $body['ack_status']) === 'successfully_sent';
        }
        return true;
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
