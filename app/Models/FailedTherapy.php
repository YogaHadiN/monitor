<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedTherapy extends Model
{
    use HasFactory;

    protected $table = 'failed_therapies';
    public function antrian(){
        return $this->belongsTo("App\Models\Antrian");
    }
    public static function boot(){
        parent::boot();
        self::creating(function($model){
            resetWhatsappRegistration( $model->no_telp );
        });
    }
}
