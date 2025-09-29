<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WablasClient
{
    private string $baseUrl;
    private string $token;
    private int $timeout;
    private int $retries;

    public function __construct(?string $baseUrl = null, ?string $token = null, ?int $timeout = null, ?int $retries = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? env('WABLAS_BASE_URL', ''), '/');
        $this->token   = $token   ?? env('WABLAS_TOKEN', '');
        $this->timeout = $timeout ?? (int) env('WABLAS_TIMEOUT', 20);
        $this->retries = $retries ?? (int) env('WABLAS_RETRIES', 2);

        if ($this->baseUrl === '' || $this->token === '') {
            throw new \InvalidArgumentException('Wablas base URL / token belum diset.');
        }
    }

    /**
     * Kirim pesan teks sederhana.
     * @return array{ok:bool, response:mixed}
     */
    public function sendMessage(string $phone, string $message): array
    {
        return $this->post('/send-message', [
            'phone'   => $this->normalizePhone($phone),
            'message' => $message,
        ]);
    }

    /**
     * Kirim gambar via URL.
     */
    public function sendImage(string $phone, string $caption, string $imageUrl): array
    {
        return $this->post('/send-image', [
            'phone'   => $this->normalizePhone($phone),
            'caption' => $caption,
            'image'   => $imageUrl,
        ]);
    }

    /**
     * Kirim dokumen via URL.
     */
    public function sendDocument(string $phone, string $documentUrl, ?string $filename = null): array
    {
        return $this->post('/send-document', [
            'phone'     => $this->normalizePhone($phone),
            'document'  => $documentUrl,
            'filename'  => $filename, // opsional tergantung gateway
        ]);
    }

    /**
     * Kirim template quick reply / button (tergantung dukungan Wablas endpoint-mu).
     * $buttons contoh: [['id'=>'yes','title'=>'Ya'], ['id'=>'no','title'=>'Tidak']]
     */
    public function sendTemplate(string $phone, string $header, string $body, array $buttons = []): array
    {
        return $this->post('/send-template', [
            'phone'   => $this->normalizePhone($phone),
            'header'  => $header,
            'content' => $body,
            'buttons' => $buttons,
        ]);
    }

    /**
     * Broadcast sederhana ke banyak nomor (max sesuai limit gateway).
     */
    public function broadcast(array $phones, string $message): array
    {
        $payload = [
            'broadcast' => array_map(fn($p) => [
                'phone' => $this->normalizePhone($p),
                'message' => $message,
            ], $phones),
        ];
        return $this->post('/broadcast', $payload);
    }

    /**
     * Set typing indicator.
     */
    public function setTyping(string $phone, int $seconds = 3): array
    {
        return $this->post('/typing', [
            'phone'   => $this->normalizePhone($phone),
            'seconds' => max(1, min($seconds, 10)),
        ]);
    }

    /**
     * Tandai pesan telah dibaca (jika endpoint tersedia di instansimu).
     */
    public function markAsRead(string $phone, string $messageId): array
    {
        return $this->post('/mark-as-read', [
            'phone'      => $this->normalizePhone($phone),
            'message_id' => $messageId,
        ]);
    }

    /**
     * Cek status device.
     */
    public function deviceInfo(): array
    {
        return $this->get('/device/info');
    }

    /* =========================
       Core HTTP helpers
       ========================= */

    private function http()
    {
        return Http::withHeaders([
                'Authorization' => $this->token,
                'Accept'        => 'application/json',
            ])
            ->timeout($this->timeout)
            ->retry($this->retries, 250, function ($exception, $request) {
                // Retry jika 429/5xx
                if (method_exists($exception, 'getCode')) {
                    $code = $exception->getCode();
                    return in_array($code, [0, 408, 425, 429, 500, 502, 503, 504], true);
                }
                return false;
            });
    }

    private function get(string $path, array $query = []): array
    {
        $url = $this->baseUrl . $path;
        try {
            $resp = $this->http()->get($url, $query);
            return $this->wrap($resp->successful(), $resp->json() ?? $resp->body());
        } catch (Throwable $e) {
            Log::warning('[Wablas][GET] '.$url.' => '.$e->getMessage());
            return $this->wrap(false, ['error' => $e->getMessage()]);
        }
    }

    private function post(string $path, array $payload): array
    {
        $url = $this->baseUrl . $path;
        try {
            $resp = $this->http()->post($url, $payload);
            // Beberapa instansi mengembalikan {status:true|false, message:...}
            $json = $resp->json();
            $ok   = $resp->successful() && ($json['status'] ?? true) !== false;
            return $this->wrap($ok, $json ?? $resp->body());

        } catch (Throwable $e) {
            Log::warning('[Wablas][POST] '.$url.' => '.$e->getMessage(), ['payload' => $payload]);
            return $this->wrap(false, ['error' => $e->getMessage()]);
        }
    }

    private function wrap(bool $ok, $response): array
    {
        return ['ok' => $ok, 'response' => $response];
    }

    private function normalizePhone(string $phone): string
    {
        $p = preg_replace('/\D+/', '', $phone ?? '');
        if (!$p) return $p;
        // Normalisasi: 08xxxx -> 628xxxx
        if (str_starts_with($p, '0')) {
            $p = '62' . substr($p, 1);
        }
        return $p;
    }
}
