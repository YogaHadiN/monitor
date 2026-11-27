<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * dr. Yoga 2026-11-27: cek berkala koneksi WhatsApp Meta (WABA) di Watzap.
 * - connection_status/is_connected/waba.connected harus true
 * - webhook_url harus ke klinikjatielok.com/api/watzap/incoming; kalau berubah/kosong → set ulang (reconnect webhook)
 * Output 1 baris: "OK ..." atau "DOWN ..." (dipakai health_monitor Hermes).
 */
class WatzapHealth extends Command
{
    protected $signature   = 'watzap:health {--no-fix : jangan coba perbaiki webhook}';
    protected $description = 'Cek koneksi WhatsApp Meta (Watzap WABA) + reconnect webhook bila lepas.';

    private string $webhook = 'https://www.klinikjatielok.com/api/watzap/incoming';

    public function handle(): int
    {
        $info = $this->cek();
        if ($info['ok'] === null) {
            $this->line('DOWN Watzap API tidak bisa dihubungi: ' . $info['detail']);
            return self::SUCCESS;
        }

        $masalah = [];
        $lic = $info['lic'];
        $nomor = $lic['wa_number'] ?? '?';

        if (!($info['waba_connected'] ?? false) || !($lic['is_connected'] ?? false) || ($lic['connection_status'] ?? '') !== 'connected') {
            $masalah[] = 'nomor ' . $nomor . ' status=' . ($lic['connection_status'] ?? 'unknown') . ' (WABA terputus dari Meta — perlu reconnect di dashboard Watzap)';
        }
        if (($lic['quality_rating'] ?? 'GREEN') === 'RED') {
            $masalah[] = 'quality rating RED';
        }

        $wh = (string) ($lic['webhook_url'] ?? '');
        if ($wh !== $this->webhook) {
            if (!$this->option('no-fix') && $this->setWebhook((string) ($lic['key'] ?? ''))) {
                $ulang = $this->cek();
                $wh2 = (string) ($ulang['lic']['webhook_url'] ?? '');
                Log::warning('WATZAP_WEBHOOK_RESET', ['lama' => $wh, 'baru' => $wh2]);
                if ($wh2 !== $this->webhook) {
                    $masalah[] = "webhook salah ({$wh}) dan gagal diset ulang";
                }
            } else {
                $masalah[] = "webhook salah ({$wh})";
            }
        }

        if ($masalah) {
            Log::error('WATZAP_HEALTH_DOWN', ['masalah' => $masalah]);
            $this->line('DOWN WhatsApp Meta (Watzap): ' . implode('; ', $masalah));
        } else {
            $this->line('OK WhatsApp Meta ' . $nomor . ' connected, quality ' . ($lic['quality_rating'] ?? '-'));
        }
        return self::SUCCESS;
    }

    private function cek(): array
    {
        try {
            $r = Http::acceptJson()->timeout(20)->retry(2, 2000)
                ->post('https://api.watzap.id/v1/checking_key', ['api_key' => env('WATZAP_TOKEN')]);
        } catch (\Throwable $e) {
            return ['ok' => null, 'detail' => $e->getMessage()];
        }
        if (!$r->ok() || !($r->json('status'))) {
            return ['ok' => null, 'detail' => 'HTTP ' . $r->status() . ' ' . mb_substr((string) $r->json('message'), 0, 120)];
        }
        $d   = $r->json('data') ?: [];
        $lic = collect($d['licenses_key'] ?? [])->firstWhere('type', 'official')
            ?? collect($d['licenses_key'] ?? [])->first() ?? [];
        return ['ok' => true, 'waba_connected' => (bool) ($d['waba']['connected'] ?? false), 'lic' => $lic];
    }

    private function setWebhook(string $numberKey): bool
    {
        // throttle: maks 1x per 30 menit
        if (Cache::has('watzap_set_webhook_try')) return false;
        Cache::put('watzap_set_webhook_try', 1, now()->addMinutes(30));
        try {
            $r = Http::acceptJson()->timeout(20)->post('https://api.watzap.id/v1/set_webhook', [
                'api_key'      => env('WATZAP_TOKEN'),
                'number_key'   => $numberKey ?: env('WATZAP_NUMBER_KEY'),
                'endpoint_url' => $this->webhook,
            ]);
            return $r->ok() && (bool) $r->json('status');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
