<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\WatzapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Auto-reply WA nomor klinik → arahkan ke Telegram bot (dr. Yoga 2026-11-04).
 *  - Dijadwalkan dgn delay acak 1–5 menit setelah pesan masuk (lihat dispatchUntuk()).
 *  - Nomor klinik hanya boleh mengirim 1 WA per menit (global): kalau slot
 *    belum tersedia, job di-release ke slot berikutnya.
 *  - 1 kali per nomor per 6 jam (supaya pasien yg kirim beberapa pesan tidak dibalas berulang).
 */
class WaRedirectTelegramReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const PESAN = "Mohon maaf, WhatsApp Klinik Jati Elok tidak dapat membalas pesan lagi 🙏\n\n"
        . "Untuk daftar berobat, cek jadwal dokter, tanya-tanya, dan chat dengan admin, silakan hubungi kami lewat *Telegram*:\n"
        . "👉 https://www.klinikjatielok.com/tg?src=wa_autoreply&start=wa\n\n"
        . "Belum punya Telegram? Unduh gratis di Play Store / App Store, lalu buka link di atas dan tekan *Mulai/Start*.\n\n"
        . "Terima kasih 🙏";

    private const SLOT_KEY = 'wa_klinik_next_send_at';
    private const JEDA_DETIK = 60;

    public int $tries = 30;          // release berulang saat menunggu slot
    public int $timeout = 60;

    public function __construct(public string $phone, public int $messageId = 0)
    {
    }

    public static function dispatchUntuk(string $phone, int $messageId = 0): void
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if ($phone === '') {
            return;
        }
        // 1x per nomor per 6 jam
        if (!Cache::add('wa_redirect_tg:' . $phone, 1, now()->addHours(6))) {
            return;
        }
        $delay = random_int(60, 300);
        self::dispatch($phone, $messageId)->delay(now()->addSeconds($delay));
        Log::info('WA_REDIRECT_TG_DIJADWALKAN', ['phone' => $phone, 'delay_detik' => $delay]);
    }

    public function handle(): void
    {
        // Slot global 1 WA/menit untuk nomor klinik
        $tunggu = Cache::lock('wa_klinik_slot_lock', 10)->block(5, function () {
            $next = (int) Cache::get(self::SLOT_KEY, 0);
            $now = time();
            if ($next > $now) {
                return $next - $now;
            }
            Cache::put(self::SLOT_KEY, $now + self::JEDA_DETIK, now()->addMinutes(10));
            return 0;
        });
        if ($tunggu > 0) {
            $this->release($tunggu + random_int(1, 10));
            return;
        }

        $res = app(WatzapService::class)->sendTextDirect($this->phone, self::PESAN);
        if ($res['ok'] ?? false) {
            Log::info('WA_REDIRECT_TG_TERKIRIM', ['phone' => $this->phone]);
            try {
                Message::create([
                    'no_telp'       => $this->phone,
                    'message'       => self::PESAN,
                    'tanggal'       => date('Y-m-d H:i:s'),
                    'sending'       => 1,
                    'sudah_dibalas' => 1,
                    'tenant_id'     => 1,
                    'touched'       => 1,
                    'chat_admin'    => 1,
                    'chat_sunat'    => 0,
                    'channel'       => 'whatsapp',
                ]);
            } catch (\Throwable $e) {
                Log::warning('WA_REDIRECT_TG_LOG_FAIL', ['err' => $e->getMessage()]);
            }
        } else {
            // Jangan retry (hindari spam); cukup log.
            Log::warning('WA_REDIRECT_TG_GAGAL', ['phone' => $this->phone, 'status' => $res['status'] ?? null, 'resp' => $res['resp'] ?? ($res['reason'] ?? null)]);
        }
    }
}
