<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SunatFollowup extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
    ];
}
