<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Antrian;
use App\Models\WhatsappRegistration;
use DateTimeInterface;

class Antrian extends Model
{
    public static function boot(){
        parent::boot();
        self::created(function($antrian){
            $existing_antrians = Antrian::where('antriable_type', 'App\Models\Antrian')
                ->where('created_at', 'like' , date('Y-m-d') . '%' )
                ->where('id', 'not like' , $antrian->id )
                ->get();
            $existing_antrian_ids = [];
            foreach ($existing_antrians as $ant) {
                $existing_antrian_ids[] = $ant->id;
            }
            $antrian->existing_antrian_ids = json_encode( $existing_antrian_ids );
            $antrian->jam_pasien_mulai_mengantri = date('H:i:s') ;
            $antrian->save();
        });
        self::deleted(function($model){
            WhatsappRegistration::where('antrian_id', $model->id)->delete();
        });
    }
    
    protected $guarded = [];
	public function jenis_antrian(){
		return $this->belongsTo('App\Models\JenisAntrian');
	}

	public function whatsapp_registration(){
		return $this->belongsTo('App\Models\WhatsappRegistration');
	}

    public function pasien(){
        return $this->belongsTo(Pasien::class);
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
        $antrian->nomor                    = Antrian::nomorAntrian(1);
        $antrian->no_telp                  = $whatsapp_registration->no_telp;
        $antrian->nama                     = $whatsapp_registration->nama;
        $antrian->tanggal_lahir            = $whatsapp_registration->tanggal_lahir;
        $antrian->tenant_id                = 1;
        $antrian->kode_unik                = Antrian::kodeUnik();
        $antrian->registrasi_pembayaran_id = $whatsapp_registration->registrasi_pembayaran_id;
        $antrian->save();
        $antrian->antriable_id             = $antrian->id;
		$antrian->antriable_type           = 'App\\Models\\Antrian';
        $antrian->save();
        return $antrian;
    }
    
    public static function nomorAntrian($id)
    {
		$antrians = Antrian::with('jenis_antrian')->where('created_at', 'like', date('Y-m-d') . '%')
							->where('jenis_antrian_id',$id)
							->orderBy('nomor', 'desc')
							->first();

		if ( is_null( $antrians ) ) {
            return 1;

		} else {
			return $antrians->nomor + 1;
		}
    }
    public static function kodeUnik(){
        $kode_unik = substr(str_shuffle(MD5(microtime())), 0, 5);
        while (Antrian::where('created_at', 'like', date('Y-m-d') . '%')->where('kode_unik', $kode_unik)->exists()) {
            $kode_unik = substr(str_shuffle(MD5(microtime())), 0, 5);
        }
        return $kode_unik;
    }
    public function registrasiPembayaran(){
        return $this->belongsTo(RegistrasiPembayaran::class);
    }
    public function getSisaAntrianAttribute(){
        $from = date('Y-m-d 00:00:00');
        $to   = date('Y-m-d 23:59:59');
        return Antrian::where('jenis_antrian_id', $this->jenis_antrian_id)// hapus antrian yang memiliki jenis_antrian_id yang sama 
            ->whereRaw("created_at between '{$from}' and '{$to}'")// yang di lakukan hari ini, 
            ->where('id', '<', $this->id)// yang antriannya sudah terlewat
            ->whereRaw(
                                "(
                                    antriable_type = 'App\\\Models\\\AntrianPeriksa' or
                                    antriable_type = 'App\\\Models\\\Antrian' or
                                    antriable_type = 'App\\\Models\\\AntrianPoli'
                                )"
                                )
            ->count();
    }
}
