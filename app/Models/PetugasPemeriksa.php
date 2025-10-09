<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\ReservasiOnline;
use Log;

class PetugasPemeriksa extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function staf(){
        return $this->belongsTo(Staf::class);
    }
    public function antrians(){
        return $this->hasMany(Antrian::class);
    }
    public function schedulled_reservations(){
        return $this->hasMany(SchedulledReservation::class);
    }
    public function waitlist_reservations(){
        return $this->hasMany(WaitlistReservation::class);
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
    public function getJadwalHariIniAvailableAttribute(){
        $jam_mulai = Carbon::parse( $this->jam_mulai_default )->format('H:i');
        $jam_akhir = Carbon::parse( $this->jam_akhir_default )->format('H:i');
        return " ( $jam_mulai - $jam_akhir )";
    }
    public function getSlotPendaftaranAvailableAttribute(): int
    {
        return $this->slot_pendaftaran > 0 || $this->max_booking == 0;
    }

    public function getSlotPendaftaranAttribute(): int
    {
        $tz    = 'Asia/Jakarta';
        $today = now($tz)->toDateString();

        // Hitung antrian hari ini untuk petugas ini (sesuaikan nama kolom tanggal di tabel antrian)
        $jumlah_antrian = $this->antrians()
            ->whereDate('created_at', $today)
            ->count();

        // Hitung reservasi online terjadwal hari ini
        $jumlah_reservasi_schedulled = $this->schedulled_reservations->count();

        $existing = $jumlah_antrian + $jumlah_reservasi_schedulled;
        $max      = (int) ($this->max_booking ?? 0);

        return max($max - $existing, 0);
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
    public function antrian(){
        return $this->hasMany(Antrian::class);
    }

    public function antrian_menunggus()
    {
        return $this->hasMany(\App\Models\Antrian::class, 'antriable_id')
                    ->whereIn('antriable_type', [
                        \App\Models\Antrian::class,
                        \App\Models\AntrianPoli::class,
                        \App\Models\AntrianPeriksa::class,
                    ]);
    }
    public function getSisaAntrianAttribute(){
        return $this->antrian_menunggus->count();
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
