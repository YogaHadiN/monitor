<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Antrian;
use Carbon\Carbon;
use App\Models\WhatsappRegistration;
use DateTimeInterface;

class Antrian extends Model
{
    public static function boot(){
        parent::boot();
        self::creating(function($antrian){
            $existing_antrian = Antrian::where('created_at', 'like' , date('Y-m-d') . '%' )
                                            ->where('jenis_antrian_id',  $antrian->jenis_antrian_id )
                                            ->where('tenant_id',  1 )
                                            ->orderBy('nomor', 'desc')
                                            ->first();
            if ( is_null( $existing_antrian ) ) {
                $antrian->nomor = 1;
            } else {
                $antrian->nomor = $existing_antrian->nomor + 1;
            }
        });
        self::created(function($antrian){
            $existing_antrians = Antrian::where('antriable_type', 'App\Models\Antrian')
                ->where('created_at', 'like' , date('Y-m-d') . '%' )
                ->where('id', 'not like' , $antrian->id )
                ->where('tenant_id', 1 )
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
    public function ruangan(){
        return $this->hasOne(Ruangan::class);
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
        $tanggal         = $this->created_at;
        $startOfDay      = Carbon::parse($tanggal)->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay        = Carbon::parse($tanggal)->endOfDay()->format('Y-m-d H:i:s');
        $antrian_panggil = $this->jenis_antrian->antrian_terakhir;
        return Antrian::where('jenis_antrian_id', $this->jenis_antrian->id)
                        ->whereRaw(
                            '(
                                antriable_type = "App\\\Models\\\Antrian" ||
                                antriable_type = "App\\\Models\\\AntrianPoli" ||
                                antriable_type = "App\\\Models\\\AntrianPeriksa"
                            )'
                        )->whereBetween('created_at', [ $startOfDay, $endOfDay ])
                        ->where('id', '>', is_null( $antrian_panggil ) ? 0 : $antrian_panggil->id)
                        ->where('tenant_id', $this->tenant_id)
                        ->count();
    }
}
