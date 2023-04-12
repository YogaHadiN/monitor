<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Log;
use Illuminate\Database\Eloquent\Model;

class WhatsappRecoveryIndex extends Model
{
    use HasFactory;
    protected $guarded = [];
    public static function boot(){
        parent::boot();
        self::creating(function($model){
            resetWhatsappRegistration( $model->no_telp );
        });
    }
    public function antrian(){
        return $this->belongsTo(Antrian::class);
    }
}
