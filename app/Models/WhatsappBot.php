<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappBot extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function staf(){
        return $this->belongsTo(Staf::class);
    }
    public static function boot(){
        parent::boot();
        self::creating(function($model){
            resetWhatsappRegistration( $model->no_telp );
        });
    }
}
