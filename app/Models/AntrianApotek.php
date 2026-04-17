<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AntrianApotek extends Model
{
    const STATUS_MENUNGGU         = 1;
    const STATUS_DIPROSES         = 2;
    const STATUS_TUNGGU_DIPANGGIL = 3;
    const STATUS_SIAP_DIAMBIL     = 4;

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
        switch ((int) $this->status) {
            case self::STATUS_DIPROSES:         return 'diproses';
            case self::STATUS_TUNGGU_DIPANGGIL: return 'tunggu_dipanggil';
            case self::STATUS_SIAP_DIAMBIL:     return 'siap_diambil';
            case self::STATUS_MENUNGGU:
            default:                            return 'menunggu';
        }
    }

    public function getStatusLabelAttribute(){
        switch ((int) $this->status) {
            case self::STATUS_DIPROSES:
                return $this->obat_racikan ? 'Sedang Diracik' : 'Sedang Disiapkan';
            case self::STATUS_TUNGGU_DIPANGGIL:
                return 'Menunggu Panggilan';
            case self::STATUS_SIAP_DIAMBIL:
                return 'Siap Diambil';
            case self::STATUS_MENUNGGU:
            default:
                return 'Menunggu';
        }
    }
}
