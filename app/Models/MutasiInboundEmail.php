<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabel `mutasi_inbound_emails` dibuat & dimigrasi dari sisi atika
 * (lihat /Users/yogahadinugroho/Sites/atika/database/migrations/
 *  2026_05_30_080000_create_mutasi_inbound_emails_table.php).
 * Monitor & atika berbagi DB `jatielok`, jadi model ini hanya bayangan
 * di sisi monitor — jangan tambah migration di sini.
 */
class MutasiInboundEmail extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'verdicts'     => 'array',
        'received_at'  => 'datetime',
        'processed_at' => 'datetime',
    ];
}
