<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisAntrian extends Model
{
	public function poli_antrian(){
		return $this->hasMany(PoliAntrian::class);
	}
	public function antrian_terakhir(){
		return $this->belongsTo(Antrian::class, 'antrian_terakhir_i');
	}
}
