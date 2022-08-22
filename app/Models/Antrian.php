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
		return $this->morphto()->withDefault();
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
	public function poli(){
		return $this->belongsTo('App\Models\Poli');
	}

    public static function createFromWhatsappRegistration($whatsapp_registration){
        $antrian                           = new Antrian;
        $antrian->jenis_antrian_id         = 1;
        $antrian->nomor                    = Antrian::nomorAntrian();
        $antrian->no_telp                  = $whatsapp_registration->no_telp;
        $antrian->nama                     = $whatsapp_registration->nama;
        $antrian->tanggal_lahir            = $whatsapp_registration->tanggal_lahir;
        $antrian->tenant_id                = 1;
        $antrian->registrasi_pembayaran_id = $whatsapp_registration->registrasi_pembayaran_id;
        $antrian->save();
        $antrian->antriable_id             = $antrian->id;
		$antrian->antriable_type           = 'App\\Models\\Antrian';
        $antrian->save();
        return $antrian;
    }
    
}
