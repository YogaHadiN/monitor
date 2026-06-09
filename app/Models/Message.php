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
            // Outbound (sending=1) berarti sistem/admin baru saja
            // membalas — anggap SEMUA pesan inbound (sending=0,
            // sudah_dibalas=0) dari nomor ini sudah ke-cover, jadi
            // tutup pending lama. Mencegah PWA nge-nag pesan customer
            // yang sudah obsolete oleh balasan baru. Behavior ini
            // mirror atika/app/Models/Message.php boot() — tabel
            // messages di-share antara kedua app.
            if ((int) $message->sending === 1) {
                Message::where('no_telp', $message->no_telp)
                    ->where('sending', 0)
                    ->where('sudah_dibalas', 0)
                    ->update(['sudah_dibalas' => 1]);
            }
        });
    }
}
