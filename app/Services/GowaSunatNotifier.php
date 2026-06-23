<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Helper untuk kirim pesan notifikasi ke staf via gowa sunat device
 * (bukan Meta WABA / Watzap / Wablas). Per user request:
 *   "semua pesan yang ke staf, dengan nomor telpon tujuan diambil
 *    dari database staf, dikirim lewat gowa sunat."
 *
 * Cara kerja: enqueue row ke gowa_outbound_messages (kind=staff_notif,
 * device_id='sunat'). Dispatcher gowa:dispatch-outbox di atika
 * (cron tiap menit, withoutOverlapping 10) yang akan pick up + kirim
 * dgn flow natural: typing presence start → pause → stop → send.
 *
 * Atika + monitor share DB jatielok (same host), jadi insert dari
 * monitor langsung visible ke atika dispatcher tanpa HTTP call.
 *
 * Delay max ~60s (cron tick). Untuk notif booking / operator alert
 * itu acceptable.
 */
class GowaSunatNotifier
{
    /**
     * Enqueue pesan ke nomor staf. Phone otomatis di-normalize ke E.164
     * (0xxx → 62xxx, drop non-digit). Return true kalau berhasil insert.
     */
    public static function notifyStaff(string $phone, string $message, string $label = 'staff_notif'): bool
    {
        $normalized = self::normalize($phone);
        if ($normalized === '' || trim($message) === '') {
            return false;
        }

        try {
            DB::table('gowa_outbound_messages')->insert([
                'kind'         => $label,
                'to_phone'     => $normalized,
                'to_label'     => 'staff',
                'device_id'    => 'sunat',
                'body'         => $message,
                'status'       => 'pending',
                'scheduled_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('GowaSunatNotifier enqueue failed', [
                'phone' => $normalized,
                'err'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    private static function normalize(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone) ?? '';
        if ($d === '') return '';
        if (str_starts_with($d, '0'))      return '62' . substr($d, 1);
        if (str_starts_with($d, '8'))      return '62' . $d;
        return $d;
    }
}
