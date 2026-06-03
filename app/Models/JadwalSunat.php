<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mirror dari atika (single source of truth tetap di atika, tapi
 * monitor butuh model ini untuk Eloquent insert + cek konflik
 * via sunat bot booking flow). Tabel `jadwal_sunats` ada di shared
 * DB `jatielok`.
 */
class JadwalSunat extends Model
{
    protected $table = 'jadwal_sunats';

    protected $fillable = [
        'tenant_id', 'pasien_id', 'tanggal', 'jam', 'status',
        'nama_pasien', 'no_telp', 'catatan', 'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];
}
