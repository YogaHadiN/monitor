<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Log;
use Illuminate\Database\Eloquent\Model;

class WhatsappComplaint extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function antrian(){
        return $this->belongsTo(Antrian::class);
    }
    public static function boot(){
        parent::boot();
        self::creating(function($model){
            resetWhatsappRegistration( $model->no_telp );
        });
    }

}
