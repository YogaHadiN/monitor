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
        if (is_null($this->periksa)) {
            return false;
        }
        foreach ($this->periksa->terapii as $terapi) {
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

    public function getStatusKodeAttribute(){
        $p = $this->periksa;
        if (is_null($p))                                  return 'menunggu';
        if (self::hasTs($p->jam_penyerahan_obat))         return 'siap_diambil';
        if (self::hasTs($p->jam_selesai_obat))            return 'tunggu_dipanggil';
        if (self::hasTs($p->jam_obat_mulai_diracik))      return 'diproses';
        return 'menunggu';
    }

    public function getStatusLabelAttribute(){
        switch ($this->status_kode) {
            case 'diproses':
                return $this->obat_racikan ? 'Sedang Diracik' : 'Sedang Disiapkan';
            case 'tunggu_dipanggil':
                return 'Menunggu Panggilan';
            case 'siap_diambil':
                return 'Siap Diambil';
            case 'menunggu':
            default:
                return 'Menunggu';
        }
    }

    public static function hasTs($value){
        if (is_null($value))                return false;
        $s = trim((string) $value);
        if ($s === '')                      return false;
        if (str_starts_with($s, '0000-00-00')) return false;
        if ($s === '00:00:00')              return false;
        return true;
    }
}
