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
        });
    }
}
