<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalSunatBlackout extends Model
{
    protected $table = 'jadwal_sunat_blackouts';

    protected $fillable = [
        'tenant_id', 'start_date', 'end_date',
        'blocked_slots', 'reason', 'note', 'created_by',
    ];

    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
        'blocked_slots' => 'array',
    ];
}
