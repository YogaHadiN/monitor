<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AntrianApotek extends Model
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
    public function getObatRacikanAttribute(){
        $racikan = false;
        $signas = [];
        foreach ($this->periksa->terapii as $terapi) {
            $signas[] = $terapi->signa;
            if (
                $terapi->signa == 'Add' ||
                $terapi->signa == 'Puyer' 
            ) {
                $racikan = true;
                break;
            }
        }
        return $racikan;
    }
}
