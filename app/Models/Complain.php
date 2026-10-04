<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complain extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'image_urls' => 'array',
    ];
    public static function boot(){
        parent::boot();
    }
}

