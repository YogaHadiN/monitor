<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SunatQna extends Model
{
    protected $table = 'sunat_qnas';

    protected $guarded = [];

    protected $casts = [
        'patterns' => 'array',
        'active'   => 'boolean',
    ];
}
