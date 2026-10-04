<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebRegistration extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];
    public function tipe_konsultasi(){
        return $this->belongsTo(TipeKonsultasi::class);
    }
    public function registrasi_pembayaran(){
        return $this->belongsTo(RegistrasiPembayaran::class);
    }
    public function staf(){
        return $this->belongsTo(Staf::class);
    }
    public function petugas_pemeriksa(){
        return $this->belongsTo(PetugasPemeriksa::class);
    }
}
