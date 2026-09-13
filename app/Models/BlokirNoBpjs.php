<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mirror model dari atika App\Models\BlokirNoBpjs.
 * Share DB, tabel `blokir_no_bpjs` sama.
 */
class BlokirNoBpjs extends Model
{
    protected $table = 'blokir_no_bpjs';
    protected $guarded = [];

    public static function alasanBlokir(?string $nomor): ?string
    {
        if ($nomor === null) return null;
        $clean = preg_replace('/\D+/', '', (string) $nomor);
        if ($clean === '') return null;

        $row = static::where('nomor_bpjs', $clean)->first();
        return $row ? (string) ($row->alasan ?: 'Nomor BPJS ini di-blokir') : null;
    }
}
