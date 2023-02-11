<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;

class WhatsappRegistration extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function antrian(){
        return $this->belongsTo(Antrian::class);
    }
    public function poli(){
        return $this->belongsTo(Poli::class);
    }
    public function registrasiPembayaran(){
        return $this->belongsTo(RegistrasiPembayaran::class);
    }
    public static function boot(){
        parent::boot();
        self::creating(function($model){
            Log::info('creating WhatsappRegistration');
            resetWhatsappRegistration( $model->no_telp );
        });
    }
}
