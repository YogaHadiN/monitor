<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Catat kapan terakhir pasien chat/berinteraksi ke klinik di no_telps.terakhir_kontak_at
 * — dipanggil di SEMUA webhook masuk (WhatsApp Meta/Watzap, Gowa semua device kecuali HP pribadi,
 * Telegram bot pesan & tombol). dr. Yoga 2026-11-05.
 * Trigger DB trg_messages_terakhir_kontak_ai tetap jalan sbg lapis kedua (pesan yg tersimpan di messages).
 * Tidak pernah melempar exception (webhook tidak boleh gagal krn ini).
 */
class KontakTerakhir
{
    public static function catat(?string $phone = null, $telegramChatId = null, string $channel = ''): void
    {
        try {
            $phone = $phone ? preg_replace('/\D+/', '', $phone) : '';
            if ($phone !== '' && str_starts_with($phone, '0')) {
                $phone = '62' . substr($phone, 1);
            }
            $chat = $telegramChatId !== null && $telegramChatId !== '' ? (string) $telegramChatId : '';
            if ($phone === '' && $chat === '') {
                return;
            }
            // Maks 1 tulis / 60 dtk per nomor / chat (hemat DB saat chat beruntun)
            $key = 'kontak_terakhir:' . $channel . ':' . ($phone !== '' ? $phone : 'tg' . $chat);
            if (!Cache::add($key, 1, 60)) {
                return;
            }
            $q = DB::table('no_telps');
            if ($phone !== '' && $chat !== '') {
                $q->where(fn ($w) => $w->where('no_telp', $phone)->orWhere('telegram_chat_id', $chat));
            } elseif ($phone !== '') {
                $q->where('no_telp', $phone);
            } else {
                $q->where('telegram_chat_id', $chat);
            }
            // Kolom per platform (dr. Yoga 2026-11-05): telegram → terakhir_kontak_telegram_at,
            // WhatsApp (watzap/Meta & gowa) → terakhir_kontak_whatsapp_at. terakhir_kontak_at = paling akhir semua.
            // WA dipisah lagi: klinik utama vs chat sunat (gowa_sunat).
            $set = ['terakhir_kontak_at' => now()];
            if ($channel === 'telegram') {
                $set['terakhir_kontak_telegram_at'] = now();
            } elseif ($channel === 'gowa_sunat') {
                $set['terakhir_kontak_wa_sunat_at'] = now();
                $set['terakhir_kontak_whatsapp_at'] = now();
            } else { // watzap (nomor klinik Meta) & gowa non-sunat
                $set['terakhir_kontak_wa_klinik_at'] = now();
                $set['terakhir_kontak_whatsapp_at'] = now();
            }
            $q->update($set);
        } catch (\Throwable $e) {
            Log::warning('KONTAK_TERAKHIR_FAIL', ['channel' => $channel, 'err' => $e->getMessage()]);
        }
    }
}
