<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Queued job di monitor: POST ke atika /api/push/trigger dengan HMAC
 * signature. Dijalankan oleh pasien-queue.service di queue "default"
 * (yang sama dengan job lainnya — non-blocking webhook).
 *
 * Dipakai oleh WablasController saat Message chat_admin=1 baru disimpan.
 */
class DispatchPushTrigger implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public $backoff   = [5, 15, 60];
    public int $timeout = 15;

    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public ?string $tag = null,
    ) {}

    public function handle(): void
    {
        $endpoint = (string) config('pwa.trigger_url');
        $secret   = (string) config('pwa.trigger_secret');

        if ($endpoint === '' || $secret === '') {
            Log::warning('DispatchPushTrigger: endpoint/secret kosong, skip', [
                'endpoint_set' => $endpoint !== '',
                'secret_set'   => $secret !== '',
            ]);
            return;
        }

        $payload = [
            'title' => $this->title,
            'body'  => $this->body,
            'url'   => $this->url,
        ];
        if ($this->tag !== null && $this->tag !== '') {
            $payload['tag'] = $this->tag;
        }

        // JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE supaya signature
        // dihitung di atas body yang sama persis dengan yang dikirim Http.
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sig  = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $resp = Http::acceptJson()
            ->timeout(10)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Signature'  => $sig,
            ])
            ->withBody($body, 'application/json')
            ->post($endpoint);

        if (!$resp->ok()) {
            Log::warning('DispatchPushTrigger: atika non-200', [
                'status' => $resp->status(),
                'body'   => mb_substr($resp->body(), 0, 200),
            ]);
            // Throw supaya queue retry sesuai $tries/$backoff.
            throw new \RuntimeException('Push trigger gagal: HTTP '.$resp->status());
        }

        Log::info('DispatchPushTrigger: ok', [
            'sent' => $resp->json('sent'),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('DispatchPushTrigger: failed', [
            'error' => $e->getMessage(),
            'tag'   => $this->tag,
        ]);
    }
}
