<?php
namespace App\Http\Controllers;
use App\Models\Antrian;
use App\Models\Asuransi;
use App\Models\Pasien;
use App\Models\Tenant;
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

        $antrian_terakhir_id = $tipe_konsultasi->ruangan->antrian_id;
        if (!is_null( $antrian_terakhir_id )) {
            $antrian_terakhir = Antrian::find( $antrian_terakhir_id );
            if (is_null( $antrian_terakhir )) {
                $antrian_terakhir = Antrian::where('ruangan_id', $tipe_konsultasi->ruangan_id)->orderBy('id', 'desc')->first();
            }
        } else {
            $antrian_terakhir = Antrian::where('ruangan_id', $tipe_konsultasi->ruangan_id)->orderBy('id', 'desc')->first();
        }




        $response = '{
            "response": [
                {
                    "namapoli" : "' . $tipe_konsultasi->poli_bpjs->nmPoli. '",
                    "totalantrean" : "' . $total_antrean. '",
                    "sisaantrean" : "' . $sisa_antrean. '",
                    "antreanpanggil" : "' . $antrian_terakhir->nomor_antrian. '",
                    "keterangan" : "",
                    "kodedokter" : 347011,
                    "namadokter" : "dr. JUNIARTI ELYANI",
                    "jampraktek" : "08:00-22:00"
                }
            ],
            "metadata": {
                "message": "Ok",
                "code": 200
            }
        }';
        return Response::json(json_decode( $response, true ), 200);
    }
    public function ambil_antrean(){
        $request        = Input::all();
        $nomorkartu     = $request['nomorkartu'];
        $nik            = $request['nik'];
        $kodepoli       = $request['kodepoli'];
        $kodedokter     = $request['kodedokter'];
        $tanggalperiksa = $request['tanggalperiksa'];
        $keluhan        = $request['keluhan'];

        //==========================
        //VALIDASI BEROBAT SEKALI
        //==========================
        if (
            $this->validasiPasienBpjsHanyaBisaBerobatSekali($nomorkartu)
        ) {
            $response = '{
                "metadata": {
                    "message": "Nomor Antrean Hanya Dapat Diambil 1 Kali Pada Tanggal Yang Sama",
                    "code": 201
                }
            }';
            return Response::json(json_decode( $response, true ), 201);
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
            return Response::json(json_decode( $response, true ), 201);
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
            $this->registrasiOnlineDitutup( $kodepoli )
        ) {
            $response = [
                'metadata' => [
                    'message' => $this->message,
                    'code' => 201
                ]
            ];
            return Response::json($response, 201);
        };

        //==========================
        // VALIDASI PENDAFTARAN POLI GIGI BELUM BUKA
        //==========================
        //
        if (
            $this->pendaftaranPoliGigiBelumDibuka()
        ) {
            $response = [
                'metadata' => [
                    'message' => 'Pendaftaran Poli Gigi baru dibuka pukul 15:00',
                    'code' => 201
                ]
            ];
            return Response::json($response, 201);
        };


        //==========================
        // VALIDASI PENDAFTARAN POLI GIGI DITUTUP
        //==========================
        //
        if (
            $this->pendaftaranPoliGigiDitutup()
        ) {
            $response = [
                'metadata' => [
                    'message' => 'Pendaftaran Poli Gigi baru dibuka pukul 15:00',
                    'code' => 201
                ]
            ];
            return Response::json($response, 201);
        };

        //==========================
        // VALIDASI PENDAFTARAN HANYA UNTUK POLI UMUM DAN POLI GIGI
        //==========================
        //
        if (
            $kodepoli !== '001' &&
            $kodepoli !== '002'
        ) {
            $response = [
                'metadata' => [
                    'message' => 'Pendaftaran online hanya untuk Poli Umum dan Poli Gigi saja',
                    'code' => 201
                ]
            ];
            return Response::json($response, 201);
        };
        //==========================
        // VALIDASI PENDAFTARAN HANYA PADA HARI YANG SAMA
        //==========================
        //
        if (
            $tanggalperiksa !== date('Y-m-d')
        ) {
            $response = [
                'metadata' => [
                    'message' => 'Antrian hanya bisa diambil pada hari yang sama',
                    'code' => 201
                ]
            ];
            return Response::json($response, 201);
        };

        //
        //==========================
        //VALIDASI DATA PASIEN TIDAK DITEMUKAN
        //==========================


        if (
            $this->dataPasienTidakDitemukan($nomorkartu, $nik)
        ) {
            $response = '{
                "metadata": {
                    "message": "Data pasien ini tidak ditemukan, silahkan Melakukan Registrasi Pasien Baru",
                    "code": 202
                }
            }';
            return Response::json(json_decode( $response, true ), 202);
        };

        //==========================
        //VALIDASI ANTRIAN DOKTER GIGI NON AKTIF
        //==========================
        //
        if (
            $kodepoli == '002' &&
            !$this->tenant->dentist_queue_enabled
        ) {
            $response = '{
                "metadata": {
                    "message": "Mohon maaf untuk sementara antrian online dokter gigi tidak dapat digunakan. Harap dapat datang secara langsung pada saat jadwal dokter gigi telah dimulai",
                    "code": 202
                }
            }';
            return Response::json([json_decode( $response, true )], 202);
        };

        $poli_bpjs                         = PoliBpjs::where('kdPoli', $kodepoli)->first();
        $this->tipe_konsultasi             = $poli_bpjs->tipe_konsultasi;
        $ruangan_id                        = $this->tipe_konsultasi->ruangan_id;
        $fc                                = new FasilitasController;
        $fc->tipe_konsultasi_id            = $this->tipe_konsultasi->id;
        $fc->ruangan_id                    = $ruangan_id;
        $antrian                           = $fc->antrianPost();
        $antrian->no_telp                  = $request['nohp'];
        $antrian->antriable_id             = $antrian->id;
        $antrian->pasien_id                = $this->pasien->id;
        $antrian->tanggal_lahir            = $this->pasien->tanggal_lahir;
        $antrian->sudah_hadir_di_klinik    = 0;
        $antrian->alamat                   = $this->pasien->alamat;
        $antrian->nama                     = $this->pasien->nama;
        $antrian->nomor_bpjs               = $nomorkartu;
        $antrian->reservasi_online         = 1;
        $antrian->registrasi_pembayaran_id = 2;
        $antrian->verifikasi_bpjs          = 1;
        $antrian->save();

        if ( empty( trim(   $this->pasien->no_telp   ) ) ) {
           $this->pasien->no_telp = $request['nohp'] ;
           $this->pasien->save();
        }

        $keterangan =  "Apabila antrean terlewat harap mengambil antrean kembali";
        if ($kodepoli == '002') {
            $keterangan =  "Apabila antrean terlewat harap mengambil antrean kembali. Terakhir penerimaan pasien adalah 1 jam sebelum pelayanan berakhir.";
        }

        $response = '{
            "response": {
                    "nomorantrean" : "' . $antrian->nomor_antrian. '",
                    "angkaantrean" : ' .$antrian->nomor. ',
                    "namapoli" : "' . $antrian->tipe_konsultasi->poli_bpjs->nmPoli. '",
                    "sisaantrean" : "'.$antrian->sisa_antrian.'",
                    "antreanpanggil" : "'.$antrian->ruangan->antrian?->nomor_antrian.'",
                    "keterangan" : "' . $keterangan. '"
            },
            "metadata": {
                "message": "Ok",
                "code": 200
            }
        }';
        return Response::json(json_decode( $response, true ), 200);
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
        if (
            $kodepoli == '001' &&
            (
                date("G") < 6 ||
                date("G") >=22
            )
        ) {
            $this->message = "Pendaftaran secara online sudah tutup dan akan buka lagi jam 6 pagi";
            $this->message .= ". Mohon maaf atas ketidaknyamanannya.";
            return true;

        } else if (
            $kodepoli == '002'
        ) {
            $poli_bpjs = PoliBpjs::where('kdPoli', $kodepoli)->first();
            $tipe_konsultasi_id = $poli_bpjs->tipe_konsultasi->id;
            $jadwal =  JadwalKonsultasi::where('tipe_konsultasi_id', $tipe_konsultasi_id)
                ->where('hari_id', date("N"))
                ->first();

            $now = strtotime("now");

            $jam_akhir_online  = date('H:i', strtotime('-3 hours', strtotime( $jadwal->jam_akhir)));
            $jam_akhir_offline = date('H:i', strtotime('-1 hours', strtotime( $jadwal->jam_akhir)));

            if (
                $now < strtotime( $jadwal->jam_mulai )
            ) {
                $this->message = 'Pendaftaran ke poli ini baru dimulai jam ' . Carbon::parse($jadwal->jam_mulai)->format('H:i') ;
            } else if (
                $now > strtotime($jam_akhir_online)
            ) {
                $this->message = "Pengambilan Antrian Poli Gigi Secara Online berakhir jam {$jam_akhir_online}";
                $this->message .= ". Pengambilan antrian secara langsung ditutup jam {$jam_akhir_offline}";
                $this->message .= ". Mohon maaf atas ketidaknyamanannya.";
            }

            $tenant = Tenant::find(1);
            $this->tenant = $tenant;
            return !$jadwal->count();
        } else {
            return false;
        }
    }
    public function validasiPasienBpjsHanyaBisaBerobatSekali($nomorkartu){
        $startOfDay = Carbon::now()->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay   = Carbon::now()->endOfDay()->format('Y-m-d H:i:s');
        $valid = false;
        if (
            Antrian::where('nomor_bpjs', $nomorkartu)
                ->whereBetween('created_at', [
                    $startOfDay, $endOfDay
                ])
                ->where('registrasi_pembayaran_id', 2)
                ->exists()
        ) {
            $valid = true;
        }

        if (
            $valid
        ) {
            $query  = "SELECT * ";
            $query .= "FROM periksas as prx ";
            $query .= "JOIN pasiens as psn on psn.id = prx.pasien_id ";
            $query .= "WHERE prx.tenant_id= 1 ";
            $query .= "AND tanggal between '{$startOfDay}' and '{$endOfDay}' ";
            $query .= "AND prx.asuransi_id = 32 ";
            $query .= "AND psn.nomor_asuransi_bpjs = '{$nomorkartu}' ";
            $data = DB::select($query);
            if (count( $data)) {
                $valid = true;
            }
        }

        if (
            $valid
        ) {
            $query  = "SELECT * ";
            $query .= "FROM antrian_polis as prx ";
            $query .= "JOIN pasiens as psn on psn.id = prx.pasien_id ";
            $query .= "WHERE prx.tenant_id= 1 ";
            $query .= "AND tanggal between '{$startOfDay}' and '{$endOfDay}' ";
            $query .= "AND prx.asuransi_id = 32 ";
            $query .= "AND psn.nomor_asuransi_bpjs = '{$nomorkartu}' ";
            $data = DB::select($query);
            if (count( $data)) {
                $valid = true;
            }
        }


        if (
            $valid
        ) {
            $query  = "SELECT * ";
            $query .= "FROM antrian_periksas as prx ";
            $query .= "JOIN pasiens as psn on psn.id = prx.pasien_id ";
            $query .= "WHERE prx.tenant_id= 1 ";
            $query .= "AND tanggal between '{$startOfDay}' and '{$endOfDay}' ";
            $query .= "AND prx.asuransi_id = 32 ";
            $query .= "AND psn.nomor_asuransi_bpjs = '{$nomorkartu}' ";
            $data = DB::select($query);
            if (count( $data)) {
                $valid = true;
            }
        }

        if (
            $valid
        ) {
            $query  = "SELECT * ";
            $query .= "FROM antrian_kasirs as aks ";
            $query .= "JOIN periksas as prx on prx.id = aks.periksa_id ";
            $query .= "JOIN pasiens as psn on psn.id = prx.pasien_id ";
            $query .= "WHERE prx.tenant_id= 1 ";
            $query .= "AND prx.tanggal between '{$startOfDay}' and '{$endOfDay}' ";
            $query .= "AND prx.asuransi_id = 32 ";
            $query .= "AND psn.nomor_asuransi_bpjs = '{$nomorkartu}' ";
            $data = DB::select($query);
            if (count( $data)) {
                $valid = true;
            }
        }
        return $valid;
    }
    public function poliSedangLibur($kodepoli){
        $poli_bpjs = PoliBpjs::where('kdPoli', $kodepoli)->first();
        if ( !is_null( $poli_bpjs ) ) {
            $tipe_konsultasi_id = $poli_bpjs->tipe_konsultasi->id;
            $tenant = Tenant::find(1);
            return
                !JadwalKonsultasi::where('tipe_konsultasi_id', $tipe_konsultasi_id)->where('hari_id', date("N"))->exists()
                || ( !$tenant->dentist_available && $kodepoli == '002' );
        } else {
            return true;
        }
    }

    public function dokterTidakDitemukan($kodedokter){
        return false;
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
        $kodepoli           = Input::get('kodepoli');
        $poli_bpjs          = PoliBpjs::where('kdPoli', $kodepoli)->first();
        $tipe_konsultasi_id = $poli_bpjs->tipe_konsultasi->id;
        $hari_id            = (int) date('N', strtotime( Input::get('tanggalperiksa') ));

        $jadwal_konsultasi = JadwalKonsultasi::where('tipe_konsultasi_id', $tipe_konsultasi_id )
                                            ->where('hari_id',  $hari_id )
                                            ->first();
        $jam_mulai = strtotime( $jadwal_konsultasi->jam_mulai );
        if (
            $kodepoli == '002' &&
            $jam_mulai > strtotime("now")
        ) {
            return true;
        }
    }
    public function pendaftaranPoliGigiDitutup(){
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
