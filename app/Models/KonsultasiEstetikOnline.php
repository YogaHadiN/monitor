<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KonsultasiEstetikOnline extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function gambarPeriksa(){
        return $this->morphMany(GambarPeriksa::class, 'gambarable');
    }
    public static function boot(){
        parent::boot();
        self::creating(function($model){
            KonsultasiEstetikOnline::where('no_telp', $model->no_telp )->delete();
        });
        self::created(function($model){
            $model->tenant_id = 1;
            $model->save();
        });
    }
}
