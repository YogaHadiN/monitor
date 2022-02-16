<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class Antrian extends Model
{
	public function jenis_antrian(){
		return $this->belongsTo('App\Models\JenisAntrian');
	}

	public function whatsapp_registration(){
		return $this->belongsTo('App\Models\WhatsappRegistration');
	}

	public function antriable(){
		return $this->morphto();
	}

	public function getNomorAntrianAttribute(){
		return $this->jenis_antrian->prefix . $this->nomor;
	}
	public function getJenisAntrianIdAttribute($value){
		if ( is_null($value) ) {
			return '6';
		}
		return $value;
	}

	/**
	 * Prepare a date for array / JSON serialization.
	 *
	 * @param  \DateTimeInterface  $date
	 * @return string
	 */
	protected function serializeDate(DateTimeInterface $date)
	{
		return $date->format('Y-m-d H:i:s');
	}
}
