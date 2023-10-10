<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AntrianKasir extends Model
{
    public function periksa(){
        return $this->belongsTo('App\Models\Periksa');
    }
	public function antrian(){
        return $this->morphOne('App\Models\Antrian', 'antriable');
	}
    public function antars(){
        return $this->morphMany('App\Models\PengantarPasien', 'antarable');
    }
    public function getResepRacikanAttribute(){
        foreach ($this->periksa->terapii as $terapi) {
            if (
                $terapi->signa == 'add' ||
                $terapi->signa == 'puyer' 
            ) {
                return true;
            }
        }
        return false;
    }
}
