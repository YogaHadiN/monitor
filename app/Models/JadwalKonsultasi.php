<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Traits\BelongsToTenant;

class JadwalKonsultasi extends Model
{
    use HasFactory, BelongsToTenant;

    protected $casts = [
        'jam_mulai' => 'datetime',
        'jam_akhir' => 'datetime',
    ];

    public function hari(){
        return $this->belongsTo(Hari::class);
    }
    public function staf(){
        return $this->belongsTo(Staf::class);
    }
    public function tipeKonsultasi(){
        return $this->belongsTo(TipeKonsultasi::class);
    }
    public function getJamAkhirAttribute($value){
        return !is_null( $value ) ? Carbon::parse($value)->format("H:i") : $value;
    }
    public function getJamMulaiBookingAttribute($value){
        return !is_null( $value ) ? Carbon::parse($value)->format("H:i") : $value;
    }
    public function getJamMulaiAttribute($value){
        return !is_null( $value ) ? Carbon::parse($value)->format("H:i") : $value;
    }
    public function setJamMulaiBookingAttribute( $value ) {
      $this->attributes['jam_mulai'] = (new Carbon($value))->format('H:i');
    }
    public function setJamMulaiAttribute( $value ) {
      $this->attributes['jam_mulai'] = (new Carbon($value))->format('H:i');
    }
    public function setJamAkhirAttribute( $value ) {
      $this->attributes['jam_akhir'] = (new Carbon($value))->format('H:i');
    }
}
