<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;

class GambarPeriksa extends Model
{
    use HasFactory;
    protected $guarded = [];
	public function gambarable(){
		return $this->morphTo();
	}
    public static function boot(){
        parent::boot();
        self::created(function($model){
            Log::info(' $model->tenant_id == 0 ');
            Log::info( $model->tenant_id == 0 );
            $model->tenant_id = 1;
            $model->save();
        });
    }
}
