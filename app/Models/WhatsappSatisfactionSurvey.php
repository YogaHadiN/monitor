<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappSatisfactionSurvey extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function antrian(){
        return $this->belongsTo(Antrian::class);
    }
    public static function boot(){
        parent::boot();
        self::creating(function($model){
            Log::info('creating WhatsappSatisfactionSurvey');
            resetWhatsappRegistration( $model->no_telp );
        });
    }
}
