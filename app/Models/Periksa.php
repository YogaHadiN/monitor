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
}

