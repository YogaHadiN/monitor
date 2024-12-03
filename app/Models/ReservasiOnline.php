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
    public function registrasi_pembayaran(){
        return $this->belongsTo(RegistrasiPembayaran::class);
    }
    public static function boot(){
        parent::boot();
        self::creating(function($model){
            ReservasiOnline::where('no_telp', $model->no_telp )->delete();
        });
    }
    public function pasien(){
        return $this->belongsTo(Pasien::class);
    }
    public function staf(){
        return $this->belongsTo(Staf::class);
    }
}
