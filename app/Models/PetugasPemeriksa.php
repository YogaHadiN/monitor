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
    public function getJamPraktekTerlewatAttribute(){
        $jam_akhir = Carbon::parse( $this->jam_akhir_default )->subMinutes(30);
        $now = Carbon::now();
        if ($jam_akhir->lt($now)) {
            return true;
        }
        return false;
    }
    public function getJadwalHariIniAttribute(){
        $jam_mulai = Carbon::parse( $this->jam_mulai_default )->format('H:i');
        $jam_akhir = Carbon::parse( $this->jam_akhir_default )->format('H:i');
        return "$jam_mulai - $jam_akhir";
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

        // Hitung reservasi online terjadwal hari ini — KECUALI waitlist
        // (schedulled_booking=2). Waitlist belum claim slot, jadi tidak
        // boleh mengisi kuota; kalau diikut-sertakan, slot_pendaftaran
        // selalu 0 ketika waitlist sudah ada → command
        // reservasi:send-waitlist-inquiry skip dan pasien waitlist
        // tidak pernah dapat notifikasi slot kosong.
        $jumlah_reservasi_schedulled = $this->schedulled_reservations()
            ->where('schedulled_booking', 1)
            ->count();

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
        // "Menunggu" = antrian yg belum selesai dilayani dokter, dilihat
        // dari perspektif customer daftar_online (dia bakal ngantri di
        // belakang semua ini). Include:
        //   - Antrian          : walk-in / sudah tiba klinik, menunggu dipanggil
        //   - ReservasiOnline  : sudah booking online, belum tiba
        //   - AntrianPoli      : di ruang tunggu poli
        //   - AntrianPeriksa   : sedang diperiksa dokter
        // Exclude AntrianKasir (sudah selesai dgn dokter, tinggal bayar)
        // dan Periksa (fully done).
        //
        // Previously missing \App\Models\Antrian → sisa under-report
        // besar (contoh 2026-09-01: Dr Andri actual 7 pending, tapi
        // display "3" karena hanya AntrianPeriksa yg terhitung).
        return $this->hasMany(\App\Models\Antrian::class, 'petugas_pemeriksa_id')
                    ->whereIn('antriable_type', [
                        \App\Models\Antrian::class,
                        \App\Models\ReservasiOnline::class,
                        \App\Models\AntrianPoli::class,
                        \App\Models\AntrianPeriksa::class,
                    ]);
    }

    public function getSisaAntrianAttribute(){
        $count = $this->antrian_menunggus->count();

        // Tambah pending ReservasiOnline: customer sudah pick dokter
        // via WA bot (petugas_pemeriksa_id ter-set) tapi flow belum
        // finalisasi jadi Antrian. Filter reservasi_selesai=0 +
        // no_telp belum punya Antrian hari ini supaya tidak double
        // count kalau customer sudah selesai di path lain.
        $today = date('Y-m-d');
        $count += \App\Models\ReservasiOnline::where('petugas_pemeriksa_id', $this->id)
            ->whereDate('created_at', $today)
            ->where('reservasi_selesai', 0)
            ->whereNotExists(function ($q) use ($today) {
                $q->select(\DB::raw(1))
                  ->from('antrians')
                  ->whereColumn('antrians.no_telp', 'reservasi_onlines.no_telp')
                  ->whereDate('antrians.created_at', $today);
            })
            ->count();

        return $count;
    }

    public function antrian_panggil(){
        return $this->belongsTo(Antrian::class, 'antrian_panggil_id');
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
