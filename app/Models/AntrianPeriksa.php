<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AntrianPeriksa extends Model
{
	public function antrian(){
        return $this->morphOne(Antrian::class, 'antriable');
	}
    public function gambarPeriksa(){
        return $this->morphMany(GambarPeriksa::class, 'gambarable');
       
    }
    
}
