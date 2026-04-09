<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WatzapService
{
    public function sendText(string $phone, string $message)
    {
        $response = Http::withHeaders([
            'Authorization' => config('services.watzap.token'),
            'Accept' => 'application/json',
        ])->post(config('services.watzap.base_url') . '/SEND_TEXT_ENDPOINT', [
            'phone'   => $phone,
            'message' => $message,
        ]);

        if (!$response->successful()) {
            \Log::error('WATZAP SEND FAILED', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        return $response->json();
    }
}
