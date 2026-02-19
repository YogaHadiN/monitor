<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Poli extends Model
{
    use HasFactory;
    public static function konsultasiDokterUmum(){
        return Poli::where('poli', 'Poli Umum - Konsul Dokter')->first();
    }
    protected $guarded = [];

	public static function list(){
		return [ null => 'pilih' ] + Poli::pluck('poli', 'id')->all();
	}
	public function poli_antrian(){
		return $this->hasOne('App\Models\PoliAntrian');
	}
    public function poli_bpjs(){
        return $this->belongsTo(PoliBpjs::class);
    }
    public static function gawatDarurat(){
        return Poli::where('poli', 'Poli Gawat Darurat')->first();
    }
    public static function estetik(){
        return Poli::where('poli', 'Poli Estetika')->first();
    }
    public function tipe_konsultasis()
    {
        return $this->belongsToMany(
            \App\Models\TipeKonsultasi::class,
            'tipe_konsultasi_poli',
            'poli_id',
            'tipe_konsultasi_id'
        );
    }

    public function tipeKonsultasiDefault()
    {
        return $this->tipe_konsultasis()->orderBy('tipe_konsultasis.id')->first();
    }

    // helper: ambil 1 (default pertama)
    public function tipe_konsultasi()
    {
        return $this->tipe_konsultasis()->orderBy('tipe_konsultasis.id')->first();
    }

}
