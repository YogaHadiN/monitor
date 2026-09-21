<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Token yg dikirim ke pasien (via QR / SMS / WA) untuk onboarding
 * bot. Contoh: t.me/kliniksunatboy_bot?start=t_abc123xyz
 * Setelah pasien /start dgn token ini, TelegramController link
 * chat_id ↔ pasien_id (di TelegramUser + pasien.telegram_chat_id).
 */
class TelegramLinkToken extends Model
{
    protected $guarded = [];
    protected $casts = [
        'pasien_id'       => 'integer',
        'tenant_id'       => 'integer',
        'expires_at'      => 'datetime',
        'used_at'         => 'datetime',
        'used_by_chat_id' => 'integer',
    ];

    public static function issue(int $pasienId, ?int $tenantId = null, ?int $ttlHours = null): self
    {
        $ttlHours = $ttlHours ?? (int) config('telegram.link_token_ttl_hours', 24);
        return self::create([
            'token'      => 't_' . Str::random(24),
            'pasien_id'  => $pasienId,
            'tenant_id'  => $tenantId,
            'expires_at' => now()->addHours($ttlHours),
        ]);
    }

    public function isValid(): bool
    {
        return is_null($this->used_at) && $this->expires_at->isFuture();
    }

    public function consume(int $chatId): bool
    {
        if (!$this->isValid()) return false;
        $this->used_at         = now();
        $this->used_by_chat_id = $chatId;
        $this->save();
        return true;
    }
}
