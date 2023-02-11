<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Log;
use Illuminate\Database\Eloquent\Model;

class WhatsappMainMenu extends Model
{
    use HasFactory;
    protected $guarded = [];
    public static function boot(){
        parent::boot();
        self::creating(function($model){
            Log::info('creating WhatsappMainMenu');
            resetWhatsappRegistration( $model->no_telp );
        });
    }
}
