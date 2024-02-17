<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periksa extends Model
{
    public function transaksi_periksa(){
        return $this->hasMany('App\Models\TransaksiPeriksa');
    }

	public function antrian(){
        return $this->morphOne('App\Models\Antrian', 'antriable');
	}
    public function pasien(){
        return $this->belongsTo(Pasien::class);
    }
    public function staf(){
        return $this->belongsTo(Staf::class);
    }
    public function terapii(){
        return $this->hasMany('App\Models\Terapi');
    }
    public function getResepRacikanAttribute(){
        $mengandung_racikan = false;
        foreach (json_decode( $this->terapi, true ) as $terapi) {
            if (
                $terapi['signa'] == 'Add' ||
                $terapi['signa'] == 'Puyer'
            ) {
                $mengandung_racikan = true;
                break;
            }
        }
        return $mengandung_racikan;
    }
}

