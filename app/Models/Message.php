<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $guarded = [];
    use HasFactory;

    public static function boot()
    {
        parent::boot();
        self::created(function ($message) {
            // Sweep pending tergantung siapa yang balas (mirror logic
            // di atika/app/Models/Message.php — tabel messages shared).
            //
            //   staf_id NOT NULL (admin manusia) → sweep SEMUA pending,
            //   termasuk chat_admin=1.
            //
            //   staf_id NULL (bot auto-reply / sunat bot / menu daftar)
            //   → JANGAN sweep chat_admin=1 pesan; bot reply bukan
            //   jawaban substantif untuk pertanyaan chat_admin,
            //   customer masih nunggu admin manusia. Sweep yang
            //   bukan chat_admin saja.
            if ((int) $message->sending === 1) {
                $query = Message::where('no_telp', $message->no_telp)
                    ->where('sending', 0)
                    ->where('sudah_dibalas', 0);

                if (empty($message->staf_id)) {
                    $query->where('chat_admin', 0);
                }

                $query->update(['sudah_dibalas' => 1]);
            }

            // Attach foto inbound ke komplain pending hari ini (dr. Yoga
            // 2026-10-04). Forward-capture: pasien kirim foto SETELAH
            // text keluhan (seperti komplain 1814 — text 16:19:14 lalu
            // foto 16:19:17). Backward-capture di WablasController
            // menangkap foto SEBELUM text; observer ini menangkap foto
            // SETELAH text.
            if (
                (int) $message->sending === 0
                && !empty($message->image_url)
                && !empty($message->no_telp)
            ) {
                try {
                    $complain = \App\Models\Complain::where('no_telp', $message->no_telp)
                        ->whereNull('auto_reply_sent_at')
                        ->whereDate('created_at', \Carbon\Carbon::now('Asia/Jakarta')->toDateString())
                        ->first();
                    if ($complain) {
                        $existingUrls = is_array($complain->image_urls) ? $complain->image_urls : [];
                        if (!in_array($message->image_url, $existingUrls, true)) {
                            $existingUrls[] = $message->image_url;
                            $complain->image_urls = $existingUrls;
                            $complain->save();
                        }
                    }
                } catch (\Throwable $e) {
                    \Log::warning('MESSAGE_OBSERVER_ATTACH_COMPLAIN_IMAGE_FAIL', [
                        'message_id' => $message->id,
                        'err'        => $e->getMessage(),
                    ]);
                }
            }
        });
    }
}
