<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant; 
use Carbon\Carbon;

class PetugasPemeriksa extends Model
{
    use BelongsToTenant,HasFactory;
    protected $guarded = [];
    public function staf(){
        return $this->belongsTo(Staf::class);
    }
    public function ruangan(){
        return $this->belongsTo(Ruangan::class);
    }
    public function tipe_konsultasi(){
        return $this->belongsTo(TipeKonsultasi::class);
    }
    public function getSisaAntrianAttribute(){
        return Antrian::whereDate("created_at", '>=', date('Y-m-d') )
                                ->whereRaw(
                                    "
                                        (
                                            antriable_type = 'App\\\\\Models\\\\\Antrian' or
                                            antriable_type = 'App\\\\\Models\\\\\AntrianPoli' or
                                            antriable_type = 'App\\\\\Models\\\\\AntrianPeriksa'
                                        )
                                    "
                                )->where('staf_id', $this->staf_id)->count();
    }

    public function getWaktuTungguAttribute(){
        $count = Antrian::where("created_at", '>=', date('Y-m-d 00:00:00') )
                                ->whereRaw(
                                    "
                                        (
                                            antriable_type = 'App\\\\\Models\\\\\Antrian' or
                                            antriable_type = 'App\\\\\Models\\\\\AntrianPoli' or
                                            antriable_type = 'App\\\\\Models\\\\\AntrianPeriksa'
                                        )
                                    "
                                )->where('staf_id', $this->staf_id)->count();
        if ( $count < 4 ) {
            return '10-20 menit';
        } else {
            return 3 * $count . '-' . 10 * $count . ' menit';
        }
    }
    
    public function getTanggalAttribute( $value ) {
        return Carbon::parse($value)->format('d-m-Y');
    }
}
