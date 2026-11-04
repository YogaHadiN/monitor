<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-reply untuk WA yg masuk ke nomor klinik (Meta/Watzap) → arahkan ke
 * Telegram bot. Dikirim lewat **Gowa Kezia** (bukan Watzap) — dr. Yoga 2026-11-04.
 *
 *  - Di-enqueue ke tabel shared `gowa_outbound_messages` (kind=cutoff_notice);
 *    dikirim oleh atika `gowa:dispatch-outbox` (tiap menit).
 *  - Delay acak 1–5 menit setelah pesan masuk (scheduled_at).
 *  - Dispatcher membatasi cutoff_notice maks 1 pesan / 60 detik (global).
 *  - Tetap dikirim lewat WA walaupun nomor sudah punya Telegram chat ID.
 *  - Maks 1x per nomor per hari (tabel shared `gowa_cutoff_notice_sents`).
 *  - Teks: varian acak dari GowaTelegramRedirectMessages (belum pernah diterima nomor tsb).
 */
class WaRedirectTelegramReply
{
    public static function dispatchUntuk(string $phone, int $messageId = 0): void
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if ($phone === '') {
            return;
        }
        try {
            $today = Carbon::now('Asia/Jakarta')->toDateString();
            $inserted = DB::table('gowa_cutoff_notice_sents')->insertOrIgnore([
                'phone' => $phone, 'sent_date' => $today, 'delivered_via' => 'whatsapp',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ((int) $inserted === 0) {
                Log::info('WA_REDIRECT_TG_SUDAH_HARI_INI', ['phone' => $phone]);
                return;
            }

            [$variantNo, $body] = self::pilihVarian($phone);
            $delay = random_int(60, 300);
            $id = DB::table('gowa_outbound_messages')->insertGetId([
                'kind'         => 'cutoff_notice',
                'to_phone'     => $phone,
                'to_label'     => 'meta_wa_redirect',
                'body'         => $body,
                'device_id'    => env('GOWA_KEZIA_DEVICE_ID', 'kezia'),
                'variant_no'   => $variantNo,
                'scheduled_at' => now()->addSeconds($delay),
                'attempts'     => 0,
                'max_attempts' => 3,
                'status'       => 'pending',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            DB::table('gowa_cutoff_notice_sents')->where('phone', $phone)->where('sent_date', $today)
                ->update(['gowa_outbound_message_id' => $id]);
            Log::info('WA_REDIRECT_TG_DIJADWALKAN', ['phone' => $phone, 'outbox_id' => $id, 'delay_detik' => $delay, 'variant' => $variantNo]);
        } catch (\Throwable $e) {
            Log::warning('WA_REDIRECT_TG_ENQUEUE_FAIL', ['phone' => $phone, 'err' => $e->getMessage()]);
        }
    }

    /** @return array{0:int,1:string} */
    private static function pilihVarian(string $phone): array
    {
        $all = \App\Support\GowaTelegramRedirectMessages::all();
        $pernah = DB::table('gowa_outbound_messages')->where('kind', 'cutoff_notice')->where('to_phone', $phone)
            ->whereNotNull('variant_no')->pluck('variant_no')->all();
        $lastUsed = DB::table('gowa_outbound_messages')->where('kind', 'cutoff_notice')->whereNotNull('variant_no')
            ->selectRaw('variant_no, max(id) last_id')->groupBy('variant_no')->pluck('last_id', 'variant_no')->all();
        $kandidat = array_diff(array_keys($all), $pernah);
        if (!$kandidat) {
            $kandidat = array_keys($all);
        }
        usort($kandidat, fn ($a, $b) => ($lastUsed[$a] ?? 0) <=> ($lastUsed[$b] ?? 0));
        $pool = array_slice($kandidat, 0, 25);
        $no = $pool[array_rand($pool)];
        return [(int) $no, $all[$no]];
    }
}
