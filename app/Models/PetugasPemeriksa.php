<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Log;

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

    public static function dokterSaatIni(){
        return PetugasPemeriksa::whereDate('tanggal', date('Y-m-d'))
                                ->where('tipe_konsultasi_id', 1)
                                ->where('jam_mulai', '<' , date('H:i:s'))
                                ->where('jam_akhir', '>' , date('H:i:s'))
                                ->get();
    }
    public function tipe_konsultasi(){
        return $this->belongsTo(TipeKonsultasi::class);
    }
    public function getSisaAntrianAttribute(){
        return $this->antrian->count();
    }

    public function getBelumWaktunyaPraktekAttribute(){
        $now = Carbon::now();
        $jam_mulai_praktek = Carbon::parse( $this->jam_mulai_default );
        if (
            $jam_mulai_praktek->isAfter( $now )
        ) {
            return true;
        } else {
            return false;
        }
    }
    public function getAntrianAttribute(){
        return Antrian::whereDate("created_at",  date('Y-m-d') )
                                ->whereRaw(
                                    "
                                        (
                                            antriable_type = 'App\\\\\Models\\\\\Antrian' or
                                            antriable_type = 'App\\\\\Models\\\\\AntrianPoli' or
                                            antriable_type = 'App\\\\\Models\\\\\AntrianPeriksa'
                                        )
                                    "
                                    )->where('staf_id', $this->staf_id)
                                ->get();
    }

    public function getWaktuTungguAttribute(){
        $count = $this->sisa_antrian;
        if ( $count < 4 ) {
            return '10-20 menit';
        } else {
            $menit_dokter_datang = 0;
            if ( $this->belum_waktunya_praktek ) {
                $now = Carbon::now();
                $jam_mulai = Carbon::parse( $this->jam_mulai_default );
                $menit_dokter_datang = $now->diffInMinutes($jam_mulai);
            }
            $start = ( 4 * $count ) + $menit_dokter_datang;
            $to = ( 10 * $count ) + $menit_dokter_datang;
            return $start . '-' . $to . ' menit';
        }
    }

    public function getTanggalAttribute( $value ) {
        return Carbon::parse($value)->format('d-m-Y');
    }
    public function getJamAkhirAttribute($value){
        return Carbon::parse($value)->format("H:i");
    }
    public function getJamMulaiAttribute($value){
        return Carbon::parse($value)->format("H:i");
    }

    public function getAntrianTerpendekAttribute() {
        $petugas = PetugasPemeriksa::where('tipe_konsultasi_id', $this->tipe_konsultasi_id)
                                    ->where('tanggal', date('Y-m-d'))
                                    ->where('jam_mulai', '<=', date('H:i:s'))
                                    ->where('jam_akhir', '>=', date('H:i:s'))
                                    ->get();

        $data = [];
        foreach ($petugas as $p) {
            $data[] = [
                'petugas' => $p,
                'sisa_antrian' => $p->sisa_antrian
            ];
        }

        usort($data, function($a, $b) {
            return $a['sisa_antrian'] <=> $b['sisa_antrian'];
        });

        $petugas = $data[0]['petugas'];
        return $this->id == $petugas->id;
    }
}
