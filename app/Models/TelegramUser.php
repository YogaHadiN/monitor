<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Row satu per chat_id yg pernah interaksi dgn bot. Diisi otomatis
 * saat webhook pertama menerima update dari chat_id yg belum
 * dikenal. Field pasien_id di-set kalau link token diverifikasi
 * (deep link t.me/bot?start=<token>).
 */
class TelegramUser extends Model
{
    protected $guarded = [];
    protected $casts = [
        'chat_id'      => 'integer',
        'pasien_id'    => 'integer',
        'tenant_id'    => 'integer',
        'last_seen_at' => 'datetime',
    ];

    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'pasien_id');
    }

    public static function findOrCreateByChatId(int $chatId, array $profile = []): self
    {
        return self::firstOrCreate(
            ['chat_id' => $chatId],
            array_merge($profile, ['last_seen_at' => now()])
        );
    }
}
