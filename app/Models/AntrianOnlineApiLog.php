<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AntrianOnlineApiLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'request_headers' => 'array',
    ];
}
