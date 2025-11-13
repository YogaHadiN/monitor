<?php
namespace App\Http\Controllers;
use App\Models\Antrian;
use App\Models\Asuransi;
use App\Models\Pasien;
use App\Models\Tenant;
use App\Models\DokterBpjs;
use App\Models\PetugasPemeriksa;

use App\Models\AntrianPoli;
use App\Models\AntrianPeriksa;

use Illuminate\Http\Request;
use Input;
use Log;
use Response;
use Hash;
use DateTime;
use App\Models\Classes\Yoga;
use App\Models\User;
use App\Models\PoliBpjs;
use App\Models\MobileJknUser;
use App\Models\JenisAntrian;
use App\Models\JadwalKonsultasi;
use Carbon\Carbon;
use App\Models\AntrolJkntoken;
use DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class AntrianOnlineController extends Controller
{
    public $base_url;
    public $message;
    public $pasien;
    public $tipe_konsultasi;
    public $antrian;
    public $tenant_id;
    public $tenant;
    public function __construct()
    {
        $this->base_url = 'https://apijkn-dev.bpjs-kesehatan.go.id/antreanfktp_dev';
        $this->tenant_id = 1;
    }
    public function token(Request $request){
        $username = $request->header('x-username');
        $password = $request->header('x-password');

        $token = JWTAuth::attempt([
            "email" => $username,
            "password" => $password
        ]);

        if(!empty($token)){
            $secret_key     = "YOUR_SECRET_KEY";
            $issuedat_claim = time(); // issued at
            $notbefore_claim= $issuedat_claim + 10; //not before in seconds
            $expire_claim   = $issuedat_claim + 80; // expire time in seconds, 80 jeda 1 menit

            //Simpan Stamp Expired
            $datetimeFormat = 'Y-m-d H:i:s';
            $date           = new DateTime('now', new \DateTimeZone('Asia/Jakarta'));//Zona Indonesia
            $date->setTimestamp($expire_claim);
            $tgljam         = $date->format($datetimeFormat);

            AntrolJkntoken::create([
                'token'     => $token,
                'stamp'     => $expire_claim,
                'date_time' => $tgljam
            ]);

            return Response::json([
                'response' => [
                    'token' => $token
                ],
                'metadata' => [
                    'message' => 'OK',
                    'code' => 200
                ]
            ], 200);
        }

        return Response::json([
            'metadata' => [
                'message' => 'Username atau Password Tidak Sesuai',
                'code' => 201
            ]
        ], 201);

    }
    public function status_antrean($kodepoli, $tanggal){
        $poli_bpjs     = PoliBpjs::where('kdPoli', $kodepoli)->first();

        if (
            is_null( $poli_bpjs ) ||
            (
                !is_null( $poli_bpjs ) &&
                is_null( $poli_bpjs->tipe_konsultasi )
            )
        ) {
            return Response::json([
                'metadata' => [
                    'message' => 'Poli tidak ditemukan',
                    'code' => 201
                ]
            ], 201);
        }

        $tipe_konsultasi = $poli_bpjs->tipe_konsultasi;

        try {
            Carbon::createFromFormat('Y-m-d', $tanggal);
        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
             $response = '{
                    "metadata": {
                        "message": "Format Tanggal Tidak Sesuai, format yang benar adalah yyyy-mm-dd",
                        "code": 201
                    }
                }';
            return Response::json(json_decode( $response, true ), 201);
        }

        $todaynumber = strtotime('today');
        $tanggalnumber = strtotime($tanggal);

        if ($tanggalnumber < $todaynumber) {
             $response = '{
                            "metadata": {
                                "message": "Tanggal Periksa Tidak Berlaku",
                                "code": 201
                            }
                        }';
            return Response::json(json_decode( $response, true ), 201);
        }

        $startOfDay    = Carbon::parse($tanggal)->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay      = Carbon::parse($tanggal)->endOfDay()->format('Y-m-d H:i:s');

        $antrians      = Antrian::where('tipe_konsultasi_id', $tipe_konsultasi->id)
                                    ->whereBetween('created_at', [ $startOfDay, $endOfDay ])
                                    ->where('tenant_id', 1)
                                    ->get();

        $total_antrean = $antrians->count();
        $sisa_antrean = 0;
        $antrian_terakhir_id = $tipe_konsultasi->antrian?->id;
        $return = [];
        foreach ($antrians as $antrian) {
            if (
                $antrian->id > $antrian_terakhir_id &&
                (
                    $antrian->antriable_type == 'App\Models\Antrian' ||
                    $antrian->antriable_type == 'App\Models\AntrianPoli' ||
                    $antrian->antriable_type == 'App\Models\AntrianPeriksa'
                )
            ) {
                $sisa_antrean++;
            }

            $return[] = [
                'nomor_antrian' => $antrian->nomor_antrian,
                'id'            => $antrian->id,
                'nomor'         => $antrian->nomor,
                'tenant_id'     => $antrian->nomor,
            ];
        }

        $antrian_terakhir_id = optional($tipe_konsultasi->ruangan)->antrian_id;
        if (!is_null( $antrian_terakhir_id )) {
            $antrian_terakhir = Antrian::find( $antrian_terakhir_id );
            if (is_null( $antrian_terakhir )) {
                $antrian_terakhir = Antrian::where('ruangan_id', $tipe_konsultasi->ruangan_id)->orderBy('id', 'desc')->first();
            }
        } else {
            $antrian_terakhir = Antrian::where('ruangan_id', $tipe_konsultasi->ruangan_id)->orderBy('id', 'desc')->first();
        }

        $petugas_pemeriksas = PetugasPemeriksa::with('staf.dokter_bpjs')
                                                ->whereDate('tanggal', Carbon::now())
                                                ->where('tipe_konsultasi_id', $tipe_konsultasi->id)
                                                ->where('jam_mulai', '<', Carbon::now())
                                                ->where('jam_akhir', '>', Carbon::now())
                                                ->get();
        $response = [];

        foreach ($petugas_pemeriksas as $petugas_pemeriksa) {
            if (!is_null($petugas_pemeriksa->staf->dokter_bpjs)) {

                $total_antrean = Antrian::where('antriable_type', Antrian::class)
                                            ->whereDate('created_at', Carbon::now())
                                            ->where('petugas_pemeriksa_id', $petugas_pemeriksa->id)
                                            ->count();

                $total_antrean += AntrianPoli::where('staf_id', $petugas_pemeriksa->staf_id)
                                            ->whereDate('tanggal', Carbon::now())
                                            ->count();

                $total_antrean += AntrianPeriksa::where('staf_id', $petugas_pemeriksa->staf_id)
                                            ->whereDate('tanggal', Carbon::now())
                                            ->count();




                $response['response'][] = [
                    "namapoli"       => $tipe_konsultasi->poli_bpjs->nmPoli,
                    "totalantrean"   => (string) $total_antrean,
                    "sisaantrean"    => $total_antrean,
                    "antreanpanggil" => optional($petugas_pemeriksa->antrian_panggil)->nomor_antrian ?? "-",
                    "keterangan"     => "",
                    "kodedokter"     => $petugas_pemeriksa->staf->dokter_bpjs->kdDokter,
                    "namadokter"     => $petugas_pemeriksa->staf->dokter_bpjs->nama,
                    "jampraktek"     => $petugas_pemeriksa->jadwal_hari_ini
                ];
            }
        }

        $response['metadata'] = [
            'message' => 'Ok',
            'code'    => 200
        ];

return response()->json($response, 200);
    }
    public function ambil_antrean()
    {
        $request        = Input::all();
        $nomorkartu     = $request['nomorkartu']     ?? null;
        $nik            = $request['nik']            ?? null;
        $kodepoli       = $request['kodepoli']       ?? null;
        $kodedokter     = $request['kodedokter']     ?? null;
        $tanggalperiksa = $request['tanggalperiksa'] ?? null;
        $keluhan        = $request['keluhan']        ?? null;

        //==========================
        // VALIDASI BEROBAT SEKALI
        //==========================
        if ($this->validasiPasienBpjsHanyaBisaBerobatSekali($nomorkartu)) {
            return Response::json([
                'metadata' => [
                    'message' => 'Nomor Antrean Hanya Dapat Diambil 1 Kali Pada Tanggal Yang Sama',
                    'code'    => 201,
                ],
            ], 201);
        }

        //==========================
        // VALIDASI POLI LIBUR
        //==========================
        if ($this->poliSedangLibur($kodepoli)) {
            return Response::json([
                'metadata' => [
                    'message' => 'Pendaftaran ke Poli Ini Sedang Tutup',
                    'code'    => 201,
                ],
            ], 201);
        }

        //==========================
        // VALIDASI DOKTER TIDAK DITEMUKAN
        //==========================
        if ($this->dokterTidakDitemukan($kodedokter)) {

            // hati-hati: kalau benar2 tidak ditemukan, jangan akses ->nama tanpa cek
            $dokter_bpjs  = DokterBpjs::where('kdDokter', $kodedokter)->first();
            $nama_dokter  = optional($dokter_bpjs)->nama ?? 'yang dipilih';

            return Response::json([
                'metadata' => [
                    'message' => 'Jadwal Dokter ' . $nama_dokter . ' tersebut Belum tersedia, Silahkan Reschedule Tanggal dan Jam Praktek Lainnya',
                    'code'    => 201,
                ],
            ], 201);
        }

        //==========================
        // VALIDASI POLI SUDAH TUTUP PENDAFARAN ONLINE
        //==========================
        if ($this->registrasiOnlineDitutup($kodepoli)) {
            return Response::json([
                'metadata' => [
                    'message' => $this->message,
                    'code'    => 201,
                ],
            ], 201);
        }

        //==========================
        // VALIDASI PENDAFTARAN POLI GIGI BELUM BUKA
        //==========================
        if ($this->pendaftaranPoliGigiBelumDibuka()) {
            return Response::json([
                'metadata' => [
                    'message' => 'Pendaftaran Poli Gigi hanya melalui whatsapp +62-882-0151-92532',
                    'code'    => 201,
                ],
            ], 201);
        }

        //==========================
        // VALIDASI PENDAFTARAN POLI GIGI DITUTUP
        //==========================
        if ($this->pendaftaranPoliGigiDitutup($kodepoli)) {
            return Response::json([
                'metadata' => [
                    'message' => 'Pendaftaran Poli Gigi hanya melalui whatsapp +62-882-0151-92532',
                    'code'    => 201,
                ],
            ], 201);
        }

        //==========================
        // VALIDASI PENDAFTARAN HANYA UNTUK POLI UMUM
        //==========================
        if ($kodepoli !== '001') {
            return Response::json([
                'metadata' => [
                    'message' => 'Pendaftaran online hanya untuk Poli Umum',
                    'code'    => 201,
                ],
            ], 201);
        }

        //==========================
        // VALIDASI PENDAFTARAN HANYA PADA HARI YANG SAMA
        //==========================
        if ($tanggalperiksa !== date('Y-m-d')) {
            return Response::json([
                'metadata' => [
                    'message' => 'Antrian hanya bisa diambil pada hari yang sama',
                    'code'    => 201,
                ],
            ], 201);
        }

        //==========================
        // VALIDASI DATA PASIEN TIDAK DITEMUKAN
        //==========================
        if ($this->dataPasienTidakDitemukan($nomorkartu, $nik)) {
            return Response::json([
                'metadata' => [
                    'message' => 'Data pasien ini tidak ditemukan, silahkan Melakukan Registrasi Pasien Baru',
                    'code'    => 202,
                ],
            ], 202);
        }

        //==========================
        // VALIDASI ANTRIAN DOKTER GIGI NON AKTIF (backup kalau nanti 002 dibuka lagi)
        //==========================
        if ($kodepoli == '002') {
            return Response::json([
                'metadata' => [
                    'message' => 'Mohon maaf untuk sementara antrian online dokter gigi tidak dapat digunakan. Harap dapat datang secara langsung pada saat jadwal dokter gigi telah dimulai',
                    'code'    => 202,
                ],
            ], 202);
        }

        //==========================
        // PROSES BUAT ANTRIAN
        //==========================
        //
        $poli_bpjs             = PoliBpjs::where('kdPoli', $kodepoli)->first();
        $this->tipe_konsultasi = $poli_bpjs->tipe_konsultasi ?? null;

        if (is_null($this->tipe_konsultasi)) {
            return Response::json([
                'metadata' => [
                    'message' => 'Tipe konsultasi untuk poli ini belum di-setting',
                    'code'    => 201,
                ],
            ], 201);
        }

        $ruangan_id                         = $this->tipe_konsultasi->ruangan_id;
        $fc                                 = new FasilitasController;
        $fc->tipe_konsultasi_id             = $this->tipe_konsultasi->id;
        $fc->ruangan_id                     = $ruangan_id;
        $fc->input_registrasi_pembayaran_id = 2;

        $antrian                        = $fc->antrianPost();
        $antrian->no_telp               = $request['nohp'] ?? null;
        $antrian->antriable_id          = $antrian->id;
        $antrian->pasien_id             = $this->pasien->id;
        $antrian->tanggal_lahir         = $this->pasien->tanggal_lahir;
        $antrian->sudah_hadir_di_klinik = 0;
        $antrian->alamat                = $this->pasien->alamat;
        $antrian->nama                  = $this->pasien->nama;
        $antrian->nomor_bpjs            = $nomorkartu;
        $antrian->reservasi_online      = 1;
        $antrian->registrasi_pembayaran_id = 2;
        $antrian->verifikasi_bpjs       = 1;
        $antrian->save();

        // update no telp pasien jika sebelumnya kosong
        if (empty(trim($this->pasien->no_telp ?? '')) && !empty($request['nohp'] ?? null)) {
            $this->pasien->no_telp = $request['nohp'];
            $this->pasien->save();
        }

        // pesan keterangan
        $keterangan = "Apabila antrean terlewat harap mengambil antrean kembali";
        if ($kodepoli == '002') {
            $keterangan .= ". Terakhir penerimaan pasien adalah 1 jam sebelum pelayanan berakhir.";
        }

        // null-safe untuk ruangan & antrian ruangan
        $antreanPanggil = optional(optional($antrian->ruangan)->antrian)->nomor_antrian;

        $response = [
            'response' => [
                'nomorantrean'   => $antrian->nomor_antrian,
                'angkaantrean'   => (int) $antrian->nomor,
                'namapoli'       => optional(optional($antrian->tipe_konsultasi)->poli_bpjs)->nmPoli,
                'sisaantrean'    => (string) ($antrian->sisa_antrian ?? 0),
                'antreanpanggil' => $antreanPanggil ? (string) $antreanPanggil : '',
                'keterangan'     => $keterangan,
            ],
            'metadata' => [
                'message' => 'Ok',
                'code'    => 200,
            ],
        ];

        return Response::json($response, 200);
    }

    public function sisa_antrean($nomorkartu_jkn,$kode_poli,$tanggalperiksa){

        $startOfDay = Carbon::parse($tanggalperiksa)->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay   = Carbon::parse($tanggalperiksa)->endOfDay()->format('Y-m-d H:i:s');
        $antrian    = Antrian::where('nomor_bpjs', $nomorkartu_jkn)
                            ->whereBetween('created_at', [$startOfDay, $endOfDay])
                            ->first();
        if (
            is_null( $antrian ) ||
            is_null( $antrian->tipe_konsultasi->poli_bpjs )
        ) {
            return Response::json([
                'metadata' => [
                    'message' => 'Antrean Tidak Ditemukan',
                    'code' => 201
                ]
            ], 200);
        } else {
            $response = '{
                "response": {
                        "nomorantrean" : "' . $antrian->nomor_antrian. '",
                        "namapoli" : "' . $antrian->tipe_konsultasi->poli_bpjs->nmPoli. '",
                        "sisaantrean" : "'.$antrian->sisa_antrian.'",
                        "antreanpanggil" : "'.$antrian->ruangan->antrian->nomor_antrian.'",
                        "keterangan" : "Apabila antrean terlewat harap mengambil antrean kembali."
                },
                "metadata": {
                    "message": "Ok",
                    "code": 200
                }
            }';
            return Response::json(json_decode( $response, true ), 200);
        }
    }

    public function pasien_baru(){
        session()->put('tenant_id', 1);
        $pasien_sudah_terdaftar = Pasien::where('nomor_asuransi_bpjs', Input::get('nomorkartu'))
                                            ->orWhere('nomor_ktp', Input::get('nik'))
                                            ->first();

        if (!is_null(  $pasien_sudah_terdaftar  )) {
            $response = '{
                        "metadata": {
                            "message": "Pasien sudah terdaftar di FKTP sebelumnya dengan nama ' .$pasien_sudah_terdaftar->nama. '",
                            "code": 500
                        }
                    }';
            return Response::json(json_decode( $response, true ), 200);
        }

        $pasien                      = new Pasien;
        $pasien->nama                = Input::get('nama');
        $pasien->nomor_asuransi_bpjs = Input::get('nomorkartu');
        $pasien->nomor_asuransi      = Input::get('nomorkartu');
        $pasien->nomor_ktp           = Input::get('nik');
        $pasien->sex                 = Input::get('jeniskelamin') == 'L' ? 1 : 0;
        $pasien->tanggal_lahir       = Input::get('tanggallahir');
        $pasien->alamat              = Input::get('alamat');
        $pasien->tenant_id           = $this->tenant_id;
        $pasien->asuransi_id         = Asuransi::Bpjs()->id;
        $pasien->jenis_peserta_id    = 1;
        if (
            $pasien->save()
        ) {

            $response = '{
                        "metadata": {
                            "message": "Ok",
                            "code": 200
                        }
                    }';
            return Response::json(json_decode( $response, true ), 200);
        }
    }
    public function batal_antrean(){
        $request                  = Input::all();
        $antrean_sudah_dibatalkan = false;

        if ($this->antrean_tidak_ditemukan()) {
            $response = '{
                            "metadata": {
                                "message": "Antrean Tidak Ditemukan",
                                "code": 201
                            }
                        }';
            return Response::json(json_decode( $response, true ), 201);
        } else if (
            $this->antrean_sudah_dilayani()
        ) {
            $response = '{
                        "metadata": {
                            "message": "Pasien Sudah Dilayani, Antrean Tidak Dapat Dibatalkan",
                            "code": 201
                        }
                    }';
            return Response::json(json_decode( $response, true ), 201);
        } else if (
            $antrean_sudah_dibatalkan
        ) {
            $response = '{
                            "metadata": {
                                "message": "Antrean Tidak Ditemukan atau Sudah Dibatalkan",
                                "code": 201
                            }
                        }';
            return Response::json(json_decode( $response, true ), 201);
        } else {
            $antrian_id = $this->antrian->id;
            $this->antrian->antriable->delete();
            if ( !is_null( Antrian::find( $antrian_id ) ) ) {
                $this->antrian->delete();
            }
            $response = '{
                            "metadata": {
                                "message": "Ok",
                                "code": 200
                            }
                        }';
            return Response::json(json_decode( $response, true ), 200);
        }
    }
    public function registrasiOnlineDitutup($kodepoli){
        $poli_bpjs = PoliBpjs::where('kdPoli', $kodepoli)->first();
        if (!$poli_bpjs || !$poli_bpjs->poli || !$poli_bpjs->poli->tipe_konsultasi) {
            $this->message = 'Poli tidak ditemukan / belum di-setting';
            return true; // anggap registrasi online ditutup
        }
        $poli            = $poli_bpjs->poli;
        $tipe_konsultasi = $poli->tipe_konsultasi;
        $now = Carbon::now();
        $petugas_pemeriksas = PetugasPemeriksa::whereDate('tanggal', $now)
                                            ->where('tipe_konsultasi_id', $tipe_konsultasi->id)
                                            ->where('jam_mulai', '<=', $now)
                                            ->where('jam_akhir', '>=', $now)
                                            ->get();
        $online_dibuka = false;
        foreach ($petugas_pemeriksas as $petugas_pemeriksa) {
            if ( $petugas_pemeriksa->online_registration_enabled ) {
                $online_dibuka = true;
            }
        }

        if (
            !$online_dibuka
        ) {
            $this->message = 'registrasi online ditutup';
        }

        return !$online_dibuka;
    }
    public function validasiPasienBpjsHanyaBisaBerobatSekali($nomorkartu)
    {
        $tz        = 'Asia/Jakarta';
        $startOfDay = Carbon::now($tz)->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay   = Carbon::now($tz)->endOfDay()->format('Y-m-d H:i:s');

        // 1. Cek di tabel antrians (registrasi_pembayaran_id = 2 = BPJS)
        $sudahAdaAntrian = Antrian::where('nomor_bpjs', $nomorkartu)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->where('registrasi_pembayaran_id', 2)
            ->exists();

        if ($sudahAdaAntrian) {
            return true;
        }

        // 2. Cek di tabel periksas (join ke pasiens, filter nomor_asuransi_bpjs)
        $sqlPeriksas = "
            SELECT prx.id
            FROM periksas AS prx
            JOIN pasiens AS psn ON psn.id = prx.pasien_id
            WHERE prx.tenant_id = 1
              AND prx.tanggal BETWEEN ? AND ?
              AND prx.asuransi_id = 32
              AND psn.nomor_asuransi_bpjs = ?
            LIMIT 1
        ";

        $dataPeriksas = DB::select($sqlPeriksas, [$startOfDay, $endOfDay, $nomorkartu]);

        if (count($dataPeriksas) > 0) {
            return true;
        }

        // 3. Cek di tabel antrian_polis
        $sqlAntrianPolis = "
            SELECT prx.id
            FROM antrian_polis AS prx
            JOIN pasiens AS psn ON psn.id = prx.pasien_id
            WHERE prx.tenant_id = 1
              AND prx.tanggal BETWEEN ? AND ?
              AND prx.asuransi_id = 32
              AND psn.nomor_asuransi_bpjs = ?
            LIMIT 1
        ";

        $dataAntrianPolis = DB::select($sqlAntrianPolis, [$startOfDay, $endOfDay, $nomorkartu]);

        if (count($dataAntrianPolis) > 0) {
            return true;
        }

        // 4. Cek di tabel antrian_periksas
        $sqlAntrianPeriksas = "
            SELECT prx.id
            FROM antrian_periksas AS prx
            JOIN pasiens AS psn ON psn.id = prx.pasien_id
            WHERE prx.tenant_id = 1
              AND prx.tanggal BETWEEN ? AND ?
              AND prx.asuransi_id = 32
              AND psn.nomor_asuransi_bpjs = ?
            LIMIT 1
        ";

        $dataAntrianPeriksas = DB::select($sqlAntrianPeriksas, [$startOfDay, $endOfDay, $nomorkartu]);

        if (count($dataAntrianPeriksas) > 0) {
            return true;
        }

        // Kalau lolos semua: artinya belum berobat hari ini
        return false;
    }
    public function poliSedangLibur($kodepoli){
        $poli_bpjs = PoliBpjs::where('kdPoli', $kodepoli)->first();
        $tipe_konsultasi_id = $poli_bpjs->tipe_konsultasi->id;

        return PetugasPemeriksa::whereDate('tanggal', Carbon::now())
            ->where('tipe_konsultasi_id', $tipe_konsultasi_id)
            ->count() < 1;
    }

    public function dokterTidakDitemukan($kodedokter){
        $dokter_bpjs = DokterBpjs::where('kdDokter', $kodedokter)->first();
        if (!$dokter_bpjs || !$dokter_bpjs->staf) {
            // anggap "tidak ditemukan"
            return true;
        }
        $staf = $dokter_bpjs->staf;
        $now         = Carbon::now();
        return PetugasPemeriksa::whereDate('tanggal', $now)
            ->where('staf_id', $staf->id)
            ->where('jam_mulai', '<=', $now)
            ->where('jam_akhir', '>=', $now)
            ->count() < 1;

    }
    public function dataPasienTidakDitemukan($nomorkartu, $nik){
        $this->pasien = Pasien::where('nomor_asuransi_bpjs', $nomorkartu)->first();
        if (
            is_null( $this->pasien ) &&
            !empty( trim( $nik ) )
        ) {
            $this->pasien = Pasien::where('nomor_ktp', $nik)->first();
        }
        return is_null( $this->pasien );
    }
    public function antrean_tidak_ditemukan(){
        $startOfDay = Carbon::parse( Input::get('tanggalperiksa') )->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay = Carbon::parse( Input::get('tanggalperiksa') )->endOfDay()->format('Y-m-d H:i:s');
        $this->antrian = Antrian::where('nomor_bpjs', Input::get('nomorkartu'))
                            ->whereBetween('created_at', [
                                $startOfDay, $endOfDay
                            ])
                            ->where('tenant_id', 1)
                            ->first();
        return is_null( $this->antrian );
    }

    public function antrean_sudah_dilayani(){
        return
            !is_null( $this->antrian ) &&
            $this->antrian->antriable_type !== 'App\Models\Antrian' &&
            $this->antrian->antriable_type !== 'App\Models\AntrianPoli' &&
            $this->antrian->antriable_type !== 'App\Models\AntrianPeriksa';
    }
    public function pendaftaranPoliGigiBelumDibuka(){
        return true;
    }
    public function pendaftaranPoliGigiDitutup($kodepoli){
        if ( $kodepoli == '002' ) {
            return true;
        }
        $tenant = Tenant::find(1);
        return $tenant->dokter_gigi_stop_pelayanan_hari_ini;
    }

    /**
     * undocumented function
     *
     * @return void
     */
    private function getDokter()
    {
        return null;
    }


}
