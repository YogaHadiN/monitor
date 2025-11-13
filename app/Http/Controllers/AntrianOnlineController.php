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

/**
 * Controller integrasi Antrean Online BPJS (Mobile JKN)
 *
 * - Token authentication (JWT)
 * - Status antrean
 * - Ambil antrean
 * - Sisa antrean
 * - Registrasi pasien baru BPJS
 * - Pembatalan antrean
 * - Validasi berbagai kondisi (dokter tidak ditemukan, poli libur, dsb)
 */
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
        // Base URL API BPJS DEV
        $this->base_url = 'https://apijkn-dev.bpjs-kesehatan.go.id/antreanfktp_dev';

        // Default tenant selalu 1 (Klinik Jati Elok)
        $this->tenant_id = 1;
        session()->put('tenant_id') = 1;
    }

    /**
     * Login Mobile JKN → mendapatkan JWT token internal
     * Digunakan untuk autentikasi setiap request berikutnya
     */
    public function token(Request $request)
    {
        // Mengambil username/password dari header request
        $username = $request->header('x-username');
        $password = $request->header('x-password');

        // Attempt login menggunakan email + password
        $token = JWTAuth::attempt([
            "email" => $username,
            "password" => $password
        ]);

        // Jika login berhasil
        if (!empty($token)) {

            /**
             * Hitung waktu expired token (80 detik)
             */
            $issuedat_claim = time();
            $expire_claim   = $issuedat_claim + 80;

            // Simpan timestamp expiry dalam format Indonesia
            $date = new DateTime('now', new \DateTimeZone('Asia/Jakarta'));
            $date->setTimestamp($expire_claim);

            // Simpan token ke database
            AntrolJkntoken::create([
                'token'     => $token,
                'stamp'     => $expire_claim,
                'date_time' => $date->format('Y-m-d H:i:s'),
            ]);

            return Response::json([
                'response' => [ 'token' => $token ],
                'metadata' => [ 'message' => 'OK', 'code' => 200 ]
            ], 200);
        }

        // Login gagal
        return Response::json([
            'metadata' => [
                'message' => 'Username atau Password Tidak Sesuai',
                'code' => 201
            ]
        ], 201);
    }

    /**
     * Mengambil status antrean poli berdasarkan kode poli & tanggal
     * Response format mengikuti standar Mobile JKN
     */
    public function status_antrean($kodepoli, $tanggal)
    {
        // Ambil poli BPJS berdasarkan kode
        $poli_bpjs = PoliBpjs::where('kdPoli', $kodepoli)->first();

        // Validasi poli harus ada dan memiliki tipe konsultasi
        if (is_null($poli_bpjs) || is_null($poli_bpjs->tipe_konsultasi)) {
            return Response::json([
                'metadata' => [
                    'message' => 'Poli tidak ditemukan',
                    'code'    => 201
                ]
            ], 201);
        }

        $tipe_konsultasi = $poli_bpjs->tipe_konsultasi;

        // Validasi format tanggal
        try {
            Carbon::createFromFormat('Y-m-d', $tanggal);
        } catch (\Exception $e) {
            return Response::json([
                "metadata" => [
                    "message" => "Format Tanggal Tidak Sesuai, harus yyyy-mm-dd",
                    "code" => 201
                ]
            ], 201);
        }

        // Tanggal tidak boleh di masa lalu
        if (strtotime($tanggal) < strtotime('today')) {
            return Response::json([
                "metadata" => [
                    "message" => "Tanggal Periksa Tidak Berlaku",
                    "code" => 201
                ]
            ], 201);
        }

        /**
         * Ambil daftar antrean berdasarkan tipe konsultasi dan tanggal
         */
        $startOfDay = Carbon::parse($tanggal)->startOfDay();
        $endOfDay   = Carbon::parse($tanggal)->endOfDay();

        $antrians = Antrian::where('tipe_konsultasi_id', $tipe_konsultasi->id)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->where('tenant_id', 1)
            ->get();

        $total_antrean = $antrians->count();

        /**
         * Hitung antrean yang belum dilayani (sisa antrean)
         */
        $sisa_antrean = 0;
        $antrian_terakhir_id = optional($tipe_konsultasi->antrian)->id;

        foreach ($antrians as $antrian) {
            if ($antrian->id > $antrian_terakhir_id) {
                $sisa_antrean++;
            }
        }

        /**
         * Ambil petugas pemeriksa (dokter) yang sedang bertugas saat ini
         */
        $petugas_pemeriksas = PetugasPemeriksa::with('staf.dokter_bpjs')
            ->whereDate('tanggal', Carbon::now())
            ->where('tipe_konsultasi_id', $tipe_konsultasi->id)
            ->where('jam_mulai', '<', Carbon::now())
            ->where('jam_akhir', '>', Carbon::now())
            ->get();

        $response = [];

        foreach ($petugas_pemeriksas as $petugas_pemeriksa) {

            // Hanya tampilkan jika staf memiliki mapping ke Dokter BPJS
            if ($petugas_pemeriksas->isNotEmpty() && !is_null($petugas_pemeriksa->staf->dokter_bpjs)) {

                /**
                 * Hitung total antrean milik dokter tersebut
                 */
                $total = 0;

                $total += Antrian::where('petugas_pemeriksa_id', $petugas_pemeriksa->id)
                    ->whereDate('created_at', Carbon::now())->count();

                $total += AntrianPoli::where('staf_id', $petugas_pemeriksa->staf_id)
                    ->whereDate('tanggal', Carbon::now())->count();

                $total += AntrianPeriksa::where('staf_id', $petugas_pemeriksa->staf_id)
                    ->whereDate('tanggal', Carbon::now())->count();

                /**
                 * Format response sesuai spesifikasi JKN
                 */
                $response['response'][] = [
                    "namapoli"       => $tipe_konsultasi->poli_bpjs->nmPoli,
                    "totalantrean"   => (string)$total,
                    "sisaantrean"    => $total,
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

    /**
     * Ambil antrean BPJS untuk pasien
     * Hanya untuk poli umum (001)
     * Banyak validasi aturan BPJS & operasional klinik
     */
    public function ambil_antrean()
    {
        $request        = Input::all();
        $nomorkartu     = $request['nomorkartu'] ?? null;
        $nik            = $request['nik'] ?? null;
        $kodepoli       = $request['kodepoli'] ?? null;
        $kodedokter     = $request['kodedokter'] ?? null;
        $tanggalperiksa = $request['tanggalperiksa'] ?? null;

        /**
         * VALIDASI: Pasien BPJS hanya boleh berobat sekali per hari
         */
        if ($this->validasiPasienBpjsHanyaBisaBerobatSekali($nomorkartu)) {
            return Response::json([
                'metadata' => [
                    'message' => 'Nomor Antrean hanya dapat diambil 1 kali pada tanggal yang sama',
                    'code'    => 201
                ]
            ], 201);
        }

        /**
         * VALIDASI: Poli sedang libur
         */
        if ($this->poliSedangLibur($kodepoli)) {
            return Response::json([
                'metadata' => [
                    'message' => 'Pendaftaran ke Poli ini sedang tutup',
                    'code'    => 201
                ]
            ], 201);
        }

        /**
         * VALIDASI: Dokter tidak ditemukan / tidak sedang bertugas
         */
        if ($this->dokterTidakDitemukan($kodedokter)) {
            $dokter_bpjs = DokterBpjs::where('kdDokter', $kodedokter)->first();
            $nama_dokter = optional($dokter_bpjs)->nama ?? 'yang dipilih';

            return Response::json([
                'metadata' => [
                    'message' => "Jadwal Dokter $nama_dokter belum tersedia",
                    'code'    => 201
                ]
            ], 201);
        }

        /**
         * VALIDASI: Pendaftaran online ditutup
         */
        if ($this->registrasiOnlineDitutup($kodepoli)) {
            return Response::json([
                'metadata' => [
                    'message' => $this->message,
                    'code'    => 201
                ]
            ], 201);
        }

        /**
         * VALIDASI: Pendaftaran poli gigi diset tutup
         */
        if ($this->pendaftaranPoliGigiDitutup($kodepoli)) {
            return Response::json([
                'metadata' => [
                    'message' => 'Pendaftaran Poli Gigi hanya melalui WhatsApp +62-882-0151-92532',
                    'code'    => 201
                ]
            ], 201);
        }

        /**
         * VALIDASI: Hanya poli umum (001) yang bisa daftar online
         */
        if ($kodepoli !== '001') {
            return Response::json([
                'metadata' => [
                    'message' => 'Pendaftaran online hanya untuk Poli Umum',
                    'code'    => 201
                ]
            ], 201);
        }

        /**
         * VALIDASI: Hanya bisa daftar untuk hari ini
         */
        if ($tanggalperiksa !== date('Y-m-d')) {
            return Response::json([
                'metadata' => [
                    'message' => 'Antrean hanya dapat diambil pada hari yang sama',
                    'code'    => 201
                ]
            ], 201);
        }

        /**
         * VALIDASI: Nomor BPJS atau NIK harus cocok dengan data pasien
         */
        if ($this->dataPasienTidakDitemukan($nomorkartu, $nik)) {
            return Response::json([
                'metadata' => [
                    'message' => 'Data pasien ini tidak ditemukan. Silakan registrasi pasien baru',
                    'code'    => 202
                ]
            ], 202);
        }

        /**
         * PROSES: Buat antrean baru BPJS
         */
        $poli_bpjs = PoliBpjs::where('kdPoli', $kodepoli)->first();
        $this->tipe_konsultasi = $poli_bpjs->tipe_konsultasi;

        if (is_null($this->tipe_konsultasi)) {
            return Response::json([
                'metadata' => [
                    'message' => 'Tipe konsultasi belum disetting',
                    'code'    => 201
                ]
            ], 201);
        }

        /**
         * Generate antrean baru via FasilitasController
         */
        $fc                                 = new FasilitasController;
        $fc->tipe_konsultasi_id             = $this->tipe_konsultasi->id;
        $fc->ruangan_id                     = $this->tipe_konsultasi->ruangan_id;
        $fc->input_registrasi_pembayaran_id = 2; // 2 = BPJS

        $antrian = $fc->antrianPost();

        /**
         * Isi data antrean BPJS
         */
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

        /**
         * Update nomor telepon pasien jika kosong
         */
        if (empty(trim($this->pasien->no_telp))) {
            $this->pasien->no_telp = $request['nohp'] ?? null;
            $this->pasien->save();
        }

        /**
         * Format response JKN
         */
        $antreanPanggil = optional(optional($antrian->ruangan)->antrian)->nomor_antrian;

        $response = [
            'response' => [
                'nomorantrean'   => $antrian->nomor_antrian,
                'angkaantrean'   => (int)$antrian->nomor,
                'namapoli'       => $antrian->tipe_konsultasi->poli_bpjs->nmPoli,
                'sisaantrean'    => (string)($antrian->sisa_antrian ?? 0),
                'antreanpanggil' => $antreanPanggil ?: "",
                'keterangan'     => "Apabila antrean terlewat harap mengambil antrean kembali"
            ],
            'metadata' => [
                'message' => 'Ok',
                'code'    => 200
            ]
        ];

        return Response::json($response, 200);
    }

    /**
     * Mengambil sisa antrean berdasarkan nomor BPJS
     */
    public function sisa_antrean($nomorkartu_jkn, $kode_poli, $tanggalperiksa)
    {
        $startOfDay = Carbon::parse($tanggalperiksa)->startOfDay();
        $endOfDay   = Carbon::parse($tanggalperiksa)->endOfDay();

        $antrian = Antrian::where('nomor_bpjs', $nomorkartu_jkn)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->first();

        // Jika antrean tidak ditemukan
        if (is_null($antrian) || is_null($antrian->tipe_konsultasi->poli_bpjs)) {
            return Response::json([
                'metadata' => [
                    'message' => 'Antrean Tidak Ditemukan',
                    'code'    => 201
                ]
            ], 200);
        }

        /**
         * Response mengikuti format Mobile JKN
         */
        return Response::json([
            'response' => [
                'nomorantrean'   => $antrian->nomor_antrian,
                'namapoli'       => $antrian->tipe_konsultasi->poli_bpjs->nmPoli,
                'sisaantrean'    => $antrian->sisa_antrian,
                'antreanpanggil' => $antrian->ruangan->antrian->nomor_antrian,
                'keterangan'     => 'Apabila antrean terlewat harap mengambil antrean kembali.'
            ],
            'metadata' => [
                'message' => 'Ok',
                'code'    => 200
            ]
        ], 200);
    }

    /**
     * Registrasi pasien baru BPJS
     */
    public function pasien_baru()
    {
        session()->put('tenant_id', 1);

        // Cek apakah pasien sudah ada berdasarkan nomor BPJS atau NIK
        $pasien_sudah_terdaftar = Pasien::where('nomor_asuransi_bpjs', Input::get('nomorkartu'))
            ->orWhere('nomor_ktp', Input::get('nik'))
            ->first();

        if (!is_null($pasien_sudah_terdaftar)) {
            return Response::json([
                'metadata' => [
                    'message' => "Pasien sudah terdaftar di FKTP sebelumnya dengan nama {$pasien_sudah_terdaftar->nama}",
                    'code'    => 500
                ]
            ], 200);
        }

        /**
         * Proses simpan pasien baru
         */
        $pasien                      = new Pasien;
        $pasien->nama                = Input::get('nama');
        $pasien->nomor_asuransi_bpjs = Input::get('nomorkartu');
        $pasien->nomor_asuransi      = Input::get('nomorkartu');
        $pasien->nomor_ktp           = Input::get('nik');
        $pasien->sex                 = Input::get('jeniskelamin') === 'L' ? 1 : 0;
        $pasien->tanggal_lahir       = Input::get('tanggallahir');
        $pasien->alamat              = Input::get('alamat');
        $pasien->tenant_id           = $this->tenant_id;
        $pasien->asuransi_id         = Asuransi::Bpjs()->id;
        $pasien->jenis_peserta_id    = 1;

        if ($pasien->save()) {
            return Response::json([
                'metadata' => [
                    'message' => 'Ok',
                    'code'    => 200
                ]
            ], 200);
        }
    }

    /**
     * Pembatalan antrean BPJS
     */
    public function batal_antrean()
    {
        // Cek apakah antrean ditemukan
        if ($this->antrean_tidak_ditemukan()) {
            return Response::json([
                'metadata' => [
                    'message' => 'Antrean Tidak Ditemukan',
                    'code'    => 201
                ]
            ], 201);
        }

        // Antrean sudah dilayani, tidak bisa dibatalkan
        if ($this->antrean_sudah_dilayani()) {
            return Response::json([
                'metadata' => [
                    'message' => 'Pasien sudah dilayani, antrean tidak dapat dibatalkan',
                    'code'    => 201
                ]
            ], 201);
        }

        /**
         * Proses menghapus antrean
         */
        $antrian_id = $this->antrian->id;

        // Hapus child (antriable)
        $this->antrian->antriable->delete();

        // Hapus antrean utama
        if (!is_null(Antrian::find($antrian_id))) {
            $this->antrian->delete();
        }

        return Response::json([
            'metadata' => [
                'message' => 'Ok',
                'code'    => 200
            ]
        ], 200);
    }

    /**
     * Validator: Cek apakah registrasi online poli sedang dibuka
     */
    public function registrasiOnlineDitutup($kodepoli)
    {
        $poli_bpjs = PoliBpjs::where('kdPoli', $kodepoli)->first();

        // Jika poli atau tipe konsultasi belum disetting → otomatis dianggap tertutup
        if (!$poli_bpjs || !$poli_bpjs->poli || !$poli_bpjs->poli->tipe_konsultasi) {
            $this->message = 'Poli tidak ditemukan / belum di-setting';
            return true;
        }

        $tipe_konsultasi = $poli_bpjs->poli->tipe_konsultasi;
        $now = Carbon::now();

        // Ambil semua petugas pemeriksa hari ini
        $petugas_pemeriksas = PetugasPemeriksa::whereDate('tanggal', $now)
            ->where('tipe_konsultasi_id', $tipe_konsultasi->id)
            ->where('jam_mulai', '<=', $now)
            ->where('jam_akhir', '>=', $now)
            ->get();

        $online_dibuka = false;

        // Cek apakah salah satu dokter mengaktifkan online registration
        foreach ($petugas_pemeriksas as $petugas_pemeriksa) {
            if ($petugas_pemeriksa->online_registration_enabled) {
                $online_dibuka = true;
            }
        }

        if (!$online_dibuka) {
            $this->message = 'Registrasi online ditutup';
        }

        return !$online_dibuka;
    }

    /**
     * Validasi: Pasien BPJS hanya boleh daftar 1x per hari
     */
    public function validasiPasienBpjsHanyaBisaBerobatSekali($nomorkartu)
    {
        $tz = 'Asia/Jakarta';
        $startOfDay = Carbon::now($tz)->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay   = Carbon::now($tz)->endOfDay()->format('Y-m-d H:i:s');

        /**
         * 1. Cek tabel antrian
         */
        if (
            Antrian::where('nomor_bpjs', $nomorkartu)
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->where('registrasi_pembayaran_id', 2)
                ->exists()
        ) {
            return true;
        }

        /**
         * 2. Cek tabel periksas
         */
        $sql = "
            SELECT prx.id
            FROM periksas prx
            JOIN pasiens psn ON psn.id = prx.pasien_id
            WHERE prx.tenant_id = 1
              AND prx.tanggal BETWEEN ? AND ?
              AND prx.asuransi_id = 32
              AND psn.nomor_asuransi_bpjs = ?
            LIMIT 1
        ";

        if (count(DB::select($sql, [$startOfDay, $endOfDay, $nomorkartu])) > 0) return true;

        /**
         * 3. Cek tabel antrian_polis
         */
        $sql = "
            SELECT prx.id
            FROM antrian_polis prx
            JOIN pasiens psn ON psn.id = prx.pasien_id
            WHERE prx.tenant_id = 1
              AND prx.tanggal BETWEEN ? AND ?
              AND prx.asuransi_id = 32
              AND psn.nomor_asuransi_bpjs = ?
            LIMIT 1
        ";

        if (count(DB::select($sql, [$startOfDay, $endOfDay, $nomorkartu])) > 0) return true;

        /**
         * 4. Cek tabel antrian_periksas
         */
        $sql = "
            SELECT prx.id
            FROM antrian_periksas prx
            JOIN pasiens psn ON psn.id = prx.pasien_id
            WHERE prx.tenant_id = 1
              AND prx.tanggal BETWEEN ? AND ?
              AND prx.asuransi_id = 32
              AND psn.nomor_asuransi_bpjs = ?
            LIMIT 1
        ";

        if (count(DB::select($sql, [$startOfDay, $endOfDay, $nomorkartu])) > 0) return true;

        return false;
    }

    /**
     * Validasi: Cek polinya libur atau tidak
     */
    public function poliSedangLibur($kodepoli)
    {
        $poli_bpjs = PoliBpjs::where('kdPoli', $kodepoli)->first();

        $tipe_konsultasi_id = $poli_bpjs->tipe_konsultasi->id;

        // Jika PetugasPemeriksa tidak ada yg bertugas hari ini → poli libur
        return PetugasPemeriksa::whereDate('tanggal', Carbon::now())
            ->where('tipe_konsultasi_id', $tipe_konsultasi_id)
            ->count() < 1;
    }

    /**
     * Validasi: Cek apakah dokter sedang bertugas sesuai jadwal
     */
    public function dokterTidakDitemukan($kodedokter)
    {
        $dokter_bpjs = DokterBpjs::where('kdDokter', $kodedokter)->first();

        // Jika tidak ada mapping dokter → dianggap tidak ditemukan
        if (!$dokter_bpjs || !$dokter_bpjs->staf) {
            return true;
        }

        $staf = $dokter_bpjs->staf;

        /**
         * Cek apakah staf sedang bertugas hari ini dan jam saat ini masuk jadwalnya
         */
        $now = Carbon::now();

        return PetugasPemeriksa::whereDate('tanggal', $now)
            ->where('staf_id', $staf->id)
            ->where('jam_mulai', '<=', $now)
            ->where('jam_akhir', '>=', $now)
            ->count() < 1;
    }

    /**
     * Validasi: Cek apakah pasien ditemukan dari BPJS Number atau NIK
     */
    public function dataPasienTidakDitemukan($nomorkartu, $nik)
    {
        $this->pasien = Pasien::where('nomor_asuransi_bpjs', $nomorkartu)->first();

        if (is_null($this->pasien) && !empty(trim($nik))) {
            $this->pasien = Pasien::where('nomor_ktp', $nik)->first();
        }

        return is_null($this->pasien);
    }

    /**
     * Validasi: Apakah antrean tidak ditemukan berdasarkan tanggal dan nomor BPJS
     */
    public function antrean_tidak_ditemukan()
    {
        $startOfDay = Carbon::parse(Input::get('tanggalperiksa'))->startOfDay();
        $endOfDay   = Carbon::parse(Input::get('tanggalperiksa'))->endOfDay();

        $this->antrian = Antrian::where('nomor_bpjs', Input::get('nomorkartu'))
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->where('tenant_id', 1)
            ->first();

        return is_null($this->antrian);
    }

    /**
     * Validasi: Apakah antrean sudah dilayani
     */
    public function antrean_sudah_dilayani()
    {
        return
            !is_null($this->antrian) &&
            !in_array($this->antrian->antriable_type, [
                'App\Models\Antrian',
                'App\Models\AntrianPoli',
                'App\Models\AntrianPeriksa'
            ]);
    }

    /**
     * Selalu mengembalikan true (karena poli gigi ditutup online)
     */
    public function pendaftaranPoliGigiBelumDibuka()
    {
        return true;
    }

    /**
     * Validasi: Cek poli gigi tutup atau tidak
     */
    public function pendaftaranPoliGigiDitutup($kodepoli)
    {
        if ($kodepoli == '002') {
            return true;
        }

        $tenant = Tenant::find(1);

        return $tenant->dokter_gigi_stop_pelayanan_hari_ini;
    }

    private function getDokter()
    {
        return null;
    }
}

