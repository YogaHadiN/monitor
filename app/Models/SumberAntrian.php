<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SumberAntrian extends Model
{
    protected $guarded = [];
    public $timestamps = false;

    public const MOBILE_JKN   = 'mobile_jkn';
    public const WEB_KLINIK   = 'web_klinik';
    public const WHATSAPP_BOT = 'whatsapp_bot';
    public const WALK_IN      = 'walk_in';

    /**
     * Cache id-by-nama dalam memori per-request supaya tidak query berulang
     * untuk lookup yang sama.
     */
    private static array $idCache = [];

    /**
     * Ambil ID untuk nama channel tertentu. Return null kalau nama tidak terdaftar.
     * Pakai konstanta di atas supaya typo-safe:
     *
     *     SumberAntrian::idFor(SumberAntrian::MOBILE_JKN)
     */
    public static function idFor(string $nama): ?int
    {
        if ( array_key_exists($nama, self::$idCache) ) {
            return self::$idCache[$nama];
        }
        return self::$idCache[$nama] = static::where('nama', $nama)->value('id');
    }
}
