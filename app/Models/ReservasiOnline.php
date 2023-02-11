<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservasiOnline extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function whatsappBot(){
        return $this->belongsTo(WhatsappBot::class);
    }
    public function registrasiPembayaran(){
        return $this->belongsTo(RegistrasiPembayaran::class);
    }
    public static function boot(){
        parent::boot();
        self::creating(function($model){
            ReservasiOnline::where('no_telp', $model->no_telp )->delete();
        });
    }
}
