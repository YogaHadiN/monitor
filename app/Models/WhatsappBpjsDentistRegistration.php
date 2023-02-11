<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Asuransi;

use Log;
class WhatsappBpjsDentistRegistration extends Model
{
    use HasFactory;
    protected $guarded = [];
    public static function boot(){
        parent::boot();
        self::creating(function($model){
            Log::info('creating WhatsappBotDentistRegistration');
            resetWhatsappRegistration( $model->no_telp );
        });
    }
    public function registrasiPembayaran(){
        return $this->belongsTo(RegistrasiPembayaran::class);
    }
    public function getAsuransiIdAttribute(){
        if ( $this->registrasi_pembayaran_id == '2' ) {
            return Asuransi::Bpjs()->id;
        } elseif (   $this->registrasi_pembayaran_id == '1' ){
            return Asuransi::BiayaPribadi()->id;
        } else {
            return $this->pasien->asuransi_id;
        }
    }
    
}
