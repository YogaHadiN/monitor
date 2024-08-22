<?php
namespace App\Http\Controllers;
use App\Models\Antrian;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Input;
use Response;
use Hash;
use DateTime;
use App\Models\Classes\Yoga;
use App\Models\User;
use App\Models\PoliBpjs;
use App\Models\MobileJknUser;
use App\Models\JenisAntrian;
use Carbon\Carbon;
use App\Models\AntrolJkntoken;
use DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class AntrianOnlineController extends Controller
{
    public $base_url;
    public $jenis_antrian;
    public function __construct()
    {
        $this->base_url = 'https://apijkn-dev.bpjs-kesehatan.go.id/antreanfktp_dev';
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
        if (is_null( $poli_bpjs )) {
            return Response::json([
                'metadata' => [
                    'message' => 'Poli tidak ditemukan',
                    'code' => 201
                ]
            ], 201);
        }
        $jenis_antrian = $poli_bpjs->poli->poli_antrian->jenis_antrian;

        try {
            Carbon::createFromFormat('Y-m-d', $tanggal);
        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
             $response = '{
                    "metadata": {
                        "message": "Format Tanggal Tidak Sesuai, format yang benar adalah yyyy-mm-dd",
                        "code": 201
                    }
                }';
            return Response::json([json_decode( $response, true )], 201);
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
            return Response::json([json_decode( $response, true )], 201);
        }

        $startOfDay    = Carbon::parse($tanggal)->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay      = Carbon::parse($tanggal)->endOfDay()->format('Y-m-d H:i:s');
        $antrians      = Antrian::where('jenis_antrian_id', $jenis_antrian->id)
                                    ->whereBetween('created_at', [ $startOfDay, $endOfDay ])
                                    ->get();

        $total_antrean = $antrians->count();
        $sisa_antrean = 0;
        foreach ($antrians as $antrian) {
            if (
                $antrian->antriable_type == 'App\Models\Antrian' ||
                $antrian->antriable_type == 'App\Models\AntrianPoli' ||
                $antrian->antriable_type == 'App\Models\AntrianPeriksa'
            ) {
                $sisa_antrean++;
            }
        }

        $antrian_terakhir_id = $jenis_antrian->antrian_terakhir_id;
        $antrian_terakhir = Antrian::find( $antrian_terakhir_id );

        $response = '{
            "response": {
                    "namapoli" : "' . $jenis_antrian->jenis_antrian. '",
                    "totalantrean" : "' . $total_antrean. '",
                    "sisaantrean" : "' . $sisa_antrean. '",
                    "antreanpanggil" : "' . $antrian_terakhir->nomor_antrian. '",
                    "keterangan" : ""
            },
            "metadata": {
                "message": "Ok",
                "code": 200
            }
        }';
        return Response::json([json_decode( $response, true )], 200);
    }
    public function ambil_antrean(){

        $request        = Input::all();
        $nomorkartu     = $request['nomorkartu'];
        $nik            = $request['nik'];
        $kodepoli       = $request['kodepoli'];
        $kodedokter       = $request['kodedokter'];
        $tanggalperiksa = $request['tanggalperiksa'];
        $keluhan        = $request['keluhan'];

        //==========================
        //VALIDASI BEROBAT SEKALI
        //==========================
        if (
            $this->pasienBpjsHanyaBisaBerobatSekali($nomorkartu)
        ) {
            $response = '{
                "metadata": {
                    "message": "Nomor Antrean Hanya Dapat Diambil 1 Kali Pada Tanggal Yang Sama",
                    "code": 201
                }
            }';
            return Response::json([json_decode( $response, true )], 201);
        }

        //==========================
        //VALIDASI POLI LIBUR
        //==========================

        if (
            $this->poliSedangLibur($kodepoli)
        ) {
            $response = '{
                "metadata": {
                    "message": "Pendaftaran ke Poli Ini Sedang Tutup",
                    "code": 201 
                }
            }';
            return Response::json([json_decode( $response, true )], 201);
        }

        //==========================
        //VALIDASI DOKTER TIDAK DITEMUKAN
        //==========================
        //
            
        if (
            $this->dokterTidakDitemukan( $kodedokter )
        ) {

            $response = [
                'metadata' => [
                    'message' => 'Jadwal Dokter dr. Siti Riani tersebut Belum tersedia, Silahkan Reschedule Tanggal dan Jam Praktek Lainnya',
                    'code' => 201
                ]
            ];
            return Response::json($response, 201);
        };


        //==========================
        //VALIDASI POLI SUDAH TUTUP PENDAFARAN ONLINE
        //==========================
        //

            
        if (
            $this->poliTutup( $kodepoli )
        ) {

            $response = [
                'metadata' => [
                    'message' => 'Pendaftaran Ke Poli Gigi Sudah Tutup Jam  18.00',
                    'code' => 201
                ]
            ];
            return Response::json($response, 201);
        };



        //==========================
        //VALIDASI DATA PASIEN TIDAK DITEMUKAN
        //==========================

        $pasien = Pasien::where('nomor_asuransi_bpjs', $nomorkartu)->first();
        if (is_null( $pasien )) {
            $pasien = Pasien::where('nomor_ktp', $nik)->first();
        }

        if (
            is_null( $pasien )
        ) {
            $response = '{
                "metadata": {
                    "message": "Data pasien ini tidak ditemukan, silahkan Melakukan Registrasi Pasien Baru",
                    "code": 202
                }
            }';
            return Response::json([json_decode( $response, true )], 202);
        };

        $fc = new FasilitasController;
        $antrian = $fc->antrianPost($this->jenis_antrian->id);
        $antrian->no_telp          = $request['nohp'];
        if (!is_null( $pasien )) {
            $antrian->pasien_id      = $pasien->id;
            $antrian->tanggal_lahir  = $pasien->tanggal_lahir;
            $antrian->alamat         = $pasien->alamat;
            $antrian->nama           = $pasien->nama;
            $antrian->reservasi_online = 1;
        }
        $antrian->save();


        $tanggal    = $tanggalperiksa;
        $startOfDay = Carbon::parse($tanggal)->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay   = Carbon::parse($tanggal)->endOfDay()->format('Y-m-d H:i:s');
        $antrian_sisa_antrian   = Antrian::where('jenis_antrian_id', $this->jenis_antrian->id)
                        ->whereRaw(
                            '
                                antriable_type = "App\\\Models\\\Antrian" ||
                                antriable_type = "App\\\Models\\\AntrianPoli" ||
                                antriable_type = "App\\\Models\\\AntrianPeriksa"
                            '
                        )->whereBetween('created_at', [ $startOfDay, $endOfDay ])
                        ->count();

        $antrian_panggil = $antrian->jenis_antrian->antrian_terakhir->nomor_antrian;

        $response = '{
            "response": {
                    "nomorantrean" : "' . $antrian->nomor_antrian. '",
                    "angkaantrean" : "' .$antrian->nomor. '",
                    "namapoli" : "' . $antrian->jenis_antrian->jenis_antrian. '",
                    "sisaantrean" : "'.$antrian_sisa_antrian.'",
                    "antreanpanggil" : "'.$antrian_panggil.'",
                    "keterangan" : "Apabila antrean terlewat harap mengambil antrean kembali."
            },
            "metadata": {
                "message": "Ok",
                "code": 200
            }
        }';
        return Response::json([json_decode( $response, true )], 200);
    }
    public function sisa_antrean($sisaantrean, $kodepoli, $tanggal){
        $sisa_antrean_tidak_ditemukan = false;
        if ( $sisa_antrean_tidak_ditemukan ) {
            return Response::json([
                'metadata' => [
                    'message' => 'Antrean Tidak Ditemukan',
                    'code' => 201
                ]
            ], 200);
        } else {
            $tanggal    = $tanggalperiksa;
            $startOfDay = Carbon::parse($tanggal)->startOfDay()->format('Y-m-d H:i:s');
            $endOfDay   = Carbon::parse($tanggal)->endOfDay()->format('Y-m-d H:i:s');
            $antrian_sisa_antrian   = Antrian::where('jenis_antrian_id', $this->jenis_antrian->id)
                            ->whereRaw(
                                '
                                    antriable_type = "App\\\Models\\\Antrian" ||
                                    antriable_type = "App\\\Models\\\AntrianPoli" ||
                                    antriable_type = "App\\\Models\\\AntrianPeriksa"
                                '
                            )->whereBetween('created_at', [ $startOfDay, $endOfDay ])
                            ->count();

            $antrian_panggil = $antrian->jenis_antrian->antrian_terakhir->nomor_antrian;

            $response = '{
                "response": {
                        "nomorantrean" : "' . $antrian->nomor_antrian. '",
                        "angkaantrean" : "' .$antrian->nomor. '",
                        "namapoli" : "' . $antrian->jenis_antrian->jenis_antrian. '",
                        "sisaantrean" : "'.$antrian_sisa_antrian.'",
                        "antreanpanggil" : "'.$antrian_panggil.'",
                        "keterangan" : "Apabila antrean terlewat harap mengambil antrean kembali."
                },
                "metadata": {
                    "message": "Ok",
                    "code": 200
                }
            }';
            return Response::json([json_decode( $response, true )], 200);
            $response = [
                'response' => [
                    'nomorantrean'   => 'A32',
                    'namapoli'       => 'Poli Umum',
                    'sisaantrean'    => '8',
                    'antreanpanggil' => 'A29',
                    'keterangan'     => ''
                ], 
                'metadata' => [
                    'message' => 'Ok',
                    'code'    => 200
                ]
            ];
            return Response::json($response, 200);
        }
    }

    public function pasien_baru(){
        $request = '{
            "nomorkartu": "00012345678",
            "nik": "3212345678987654",
            "nomorkk": "3212345678987654",
            "nama": "sumarsono",
            "jeniskelamin": "L",
            "tanggallahir": "1985-03-01",
            "alamat": "alamat yang muncul merupakan alamat lengkap",
            "kodeprop": "11",
            "namaprop": "Jawa Barat",
            "kodedati2": "0120",
            "namadati2": "Kab. Bandung",
            "kodekec": "1319",
            "namakec": "Soreang",
            "kodekel": "D2105",
            "namakel": "Cingcin",
            "rw": "001",
            "rt": "013"
        }';
        $response = '{
                        "metadata": {
                            "message": "Ok",
                            "code": 200
                        }
                    }';
        return Response::json([json_decode( $response, true )], 200);

    }
    public function batal_antrean(){

        $request                  = Input::all();
        $antrean_tidak_ditemukan  = false;
        $antrean_sudah_dilayani   = false;
        $antrean_sudah_dibatalkan = true;

        if ($antrean_tidak_ditemukan) {
            
            $response = '{
                            "metadata": {
                                "message": "Antrean Tidak Ditemukan",
                                "code": 201
                            }
                        }';
            return Response::json([json_decode( $response, true )], 201);
        } else if (
            $antrean_sudah_dilayani
        ) {
            $response = '{
                        "metadata": {
                            "message": "Pasien Sudah Dilayani, Antrean Tidak Dapat Dibatalkan",
                            "code": 201
                        }
                    }';
            return Response::json([json_decode( $response, true )], 201);
        } else if (
            $antrean_sudah_dibatalkan
        ) {
            $response = '{
                            "metadata": {
                                "message": "Antrean Tidak Ditemukan atau Sudah Dibatalkan",
                                "code": 201
                            }
                        }';
            return Response::json([json_decode( $response, true )], 201);
        } else {
            $response = '{
                            "metadata": {
                                "message": "Ok",
                                "code": 200
                            }
                        }';

            return Response::json([json_decode( $response, true )], 200);
        }
    }

    public function poliTutup($kodepoli){
        $poli_bpjs = PoliBpjs::where('kodepoli', $kodepoli)->first();
        $tipe_konsultasi_id = $poli_bpjs->poli->tipe_konsultasi_id;
        return !JadwalKonsultasi::where('tipe_konsultasi_id', $tipe_konsultasi_id)
            ->where('hari', date("N"))
            ->where('jam_mulai', '<', date("H:i:s"))
            ->where('jam_akhir', '>', date("H:i:s"))
            ->exists();
    }
    public function pasienBpjsHanyaBisaBerobatSekali($nomorkartu){
        $startOfDay = Carbon::now()->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay   = Carbon::now()->endOfDay()->format('Y-m-d H:i:s');
        $valid = true;
        if (
            Antrian::where('nomor_bpjs', $nomorkartu)->whereBetween('created_at', [
                $startOfDay, $endOfDay
            ])->exists()
        ) {
            $valid = false;
        }

        if (
            $valid
        ) {
            $query  = "SELECT * ";
            $query .= "FROM periksas as prx ";
            $query .= "JOIN pasiens as psn on psn.id = prx.pasien_id ";
            $query .= "WHERE tenant_id=". session()->get('tenant_id') . " ";
            $query .= "AND tanggal between '{$startOfDay}' and '{$endOfDay}' "
            $query .= "AND prx.asuransi_id = 32 "
            $query .= "AND psn.nomor_asuransi_bpjs = '{$nomorkartu}' "
            $data = DB::select($query);
            if (count( $data)) {
                $valid = false;
            }
        }

        if (
            $valid
        ) {
            $query  = "SELECT * ";
            $query .= "FROM antrian_polis as prx ";
            $query .= "JOIN pasiens as psn on psn.id = prx.pasien_id ";
            $query .= "WHERE tenant_id=". session()->get('tenant_id') . " ";
            $query .= "AND tanggal between '{$startOfDay}' and '{$endOfDay}' "
            $query .= "AND prx.asuransi_id = 32 "
            $query .= "AND psn.nomor_asuransi_bpjs = '{$nomorkartu}' "
            $data = DB::select($query);
            if (count( $data)) {
                $valid = false;
            }
        }


        if (
            $valid
        ) {
            $query  = "SELECT * ";
            $query .= "FROM antrian_periksas as prx ";
            $query .= "JOIN pasiens as psn on psn.id = prx.pasien_id ";
            $query .= "WHERE tenant_id=". session()->get('tenant_id') . " ";
            $query .= "AND tanggal between '{$startOfDay}' and '{$endOfDay}' "
            $query .= "AND prx.asuransi_id = 32 "
            $query .= "AND psn.nomor_asuransi_bpjs = '{$nomorkartu}' "
            $data = DB::select($query);
            if (count( $data)) {
                $valid = false;
            }
        }

        if (
            $valid
        ) {
            $query  = "SELECT * ";
            $query .= "FROM antrian_kasirs as prx ";
            $query .= "JOIN pasiens as psn on psn.id = prx.pasien_id ";
            $query .= "WHERE tenant_id=". session()->get('tenant_id') . " ";
            $query .= "AND tanggal between '{$startOfDay}' and '{$endOfDay}' "
            $query .= "AND prx.asuransi_id = 32 "
            $query .= "AND psn.nomor_asuransi_bpjs = '{$nomorkartu}' "
            $data = DB::select($query);
            if (count( $data)) {
                $valid = false;
            }
        }

        return $valid;

    }
    public function poliSedangLibur($kodepoli){
        $poli_bpjs = PoliBpjs::where('kodepoli', $kodepoli)->first();
        $tipe_konsultasi_id = $poli_bpjs->poli->tipe_konsultasi_id;
        return !JadwalKonsultasi::where('tipe_konsultasi_id', $tipe_konsultasi_id)->where('hari', date("N"))->exists();
    }

    public function dokterTidakDitemukan($kodedokter){
        return false;
    }
}    
