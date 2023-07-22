<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalKonsultasi extends Model
{
    use HasFactory;
    protected $casts = [
        'jam_mulai' => 'datetime',
        'jam_akhir' => 'datetime',
    ];
}
