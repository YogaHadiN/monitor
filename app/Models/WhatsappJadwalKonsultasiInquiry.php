<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappJadwalKonsultasiInquiry extends Model
{
    use HasFactory;
    protected $guarded = [];
    public static function boot(){
        parent::boot();
        self::creating(function($model){
            Log::info('creating WhatsappJadwalKonsultasiInquiry');
            resetWhatsappRegistration( $model->no_telp );
        });
    }
}
