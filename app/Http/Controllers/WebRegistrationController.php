<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Input;
use App\Models\WebRegistration;
use App\Models\NoTelp;
use App\Models\Ruangan;
use App\Models\Antrian;
use App\Models\Pasien;
use App\Models\Tenant;
use App\Models\TipeKonsultasi;
use App\Models\PetugasPemeriksa;
use App\Models\SchedulledReservation;
use App\Http\Controllers\WablasController;
use App\Http\Controllers\BpjsApiController;
use Carbon\Carbon;
use Log;

class WebRegistrationController extends Controller
{
    /**
     * @param
     */
    public $tenant;
    public function __construct()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Credentials: true ");
        header("Access-Control-Allow-Methods: OPTIONS, GET, POST");
        header("Access-Control-Allow-Headers: Content-Type, Depth, User-Agent, X-File-Size, X-Requested-With, If-Modified-Since, X-File-Name, Cache-Control");
        session()->put('tenant_id', 1);

        $this->tenant = Tenant::find(1);
        /* $this->middleware('onlyWhenWebRegistrationEnabled', ['only' => ['daftar_online', 'daftar_online_by_phone', 'daftar_online_post']]); */

        $this->middleware('returnErrorIfAntrianDokterGigiNonAktif', ['except' => []]);

    }

    public function daftar_online(){
        return view('web_registrations.daftar_online');
    }

    public function daftar_online_post(){
        $no_telp = Input::get('no_telp');
        $no_telp = $this->convertToWablasFriendlyFormat( $no_telp );
        $no_telp = encrypt_string( $no_telp );
        return redirect('daftar_online/' . $no_telp);
    }

    public function daftar_online_by_phone($no_telp){
        $menangani_gawat_darurat = Tenant::find(1)->menangani_gawat_darurat;
        $no_telp                 = decrypt_string( $no_telp );

        return view('web_registrations.daftar_online_by_phone', compact(
            'no_telp',
            'menangani_gawat_darurat'
        ));
    }
    public function submit_pembayaran(){
        $registrasi_pembayaran_id = Input::get('value');
        $no_telp                  = Input::get('no_telp');

        $web_registration = WebRegistration::where('no_telp', $no_telp)
                                            ->whereDate('created_at', date('Y-m-d'))
                                            ->first();
        $nomor_asuransi_bpjs = null;
        if (
            $registrasi_pembayaran_id != 2 //BPJS
        ) {
            $nomor_asuransi_bpjs = 0;
        }

        $web_registration->registrasi_pembayaran_id = $registrasi_pembayaran_id;
        $web_registration->nomor_asuransi_bpjs = $nomor_asuransi_bpjs;
        $web_registration->save();

        $wablas = new WablasController;
        $wablas->no_telp = $no_telp;
        $previousData = $wablas->queryPreviouslySavedPatientRegistry();

        if ( count($previousData) < 1 ) {
            $web_registration->register_previously_saved_patient = '';
            $web_registration->save();
        }

    }

    public function view_refresh(){
        $no_telp = Input::get('no_telp');
        $web_registration = WebRegistration::where('no_telp', $no_telp)
                                            ->whereDate('created_at', date('Y-m-d'))
                                            ->first();

        $antrians = Antrian::where('no_telp', $no_telp)
                                ->whereDate('created_at', date('Y-m-d'))
                                ->whereRaw(
                                    "(
                                        antriable_type = 'App\\\Models\\\Antrian' or
                                        antriable_type = 'App\\\Models\\\AntrianPeriksa' or
                                        antriable_type = 'App\\\Models\\\AntrianPoli'
                                    )"
                                )
                                ->get();

        $schedulled_reservations = SchedulledReservation::where('no_telp', $no_telp)
                                ->whereDate('created_at', date('Y-m-d'))
                                ->get();

        if (
            (count( $antrians ) || count( $schedulled_reservations )) &&
            is_null( $web_registration )
        ) {
            return view('web_registrations.nomor_antrian', compact(
                'antrians',
                'schedulled_reservations'
            ));
        } else if (
            is_null( $web_registration ) ||
            (
                !is_null( $web_registration ) &&
                !is_null( $web_registration->no_telp ) &&
                is_null( $web_registration->tipe_konsultasi_id )
            )
        ) {
            $tipe_konsultasi_dokter_umum = TipeKonsultasi::find(1);
            $tipe_konsultasi_dokter_gigi = TipeKonsultasi::find(2);
            $tipe_konsultasi_bidan = TipeKonsultasi::find(3);
            return view('web_registrations.tipe_konsultasi', compact(
                'antrians',
                'tipe_konsultasi_dokter_umum',
                'tipe_konsultasi_dokter_gigi',
                'tipe_konsultasi_bidan'
            ));
        } else if (
            !is_null( $web_registration ) &&
            !is_null( $web_registration->tipe_konsultasi_id ) &&
            is_null( $web_registration->registrasi_pembayaran_id )
        ) {

            return view('web_registrations.registrasi_pembayaran');
        } else if (
            !is_null( $web_registration ) &&
            !is_null( $web_registration->tipe_konsultasi_id ) &&
            !is_null( $web_registration->registrasi_pembayaran_id ) &&
            is_null( $web_registration->register_previously_saved_patient )
        ) {

            $wablas = new WablasController;
            $wablas->no_telp = $no_telp;
            $pasiens = $wablas->queryPreviouslySavedPatientRegistry();
            return view('web_registrations.register_previously_saved_patient', compact('pasiens'));
        } else if (
            !is_null( $web_registration ) &&
            !is_null( $web_registration->tipe_konsultasi_id ) &&
            !is_null( $web_registration->registrasi_pembayaran_id ) &&
            is_null( $web_registration->nomor_asuransi_bpjs )
        ) {
            return view('web_registrations.nomor_asuransi_bpjs');
        } else if (
            !is_null( $web_registration ) &&
            !is_null( $web_registration->tipe_konsultasi_id ) &&
            !is_null( $web_registration->registrasi_pembayaran_id ) &&
            !is_null( $web_registration->register_previously_saved_patient ) &&
            !is_null( $web_registration->nomor_asuransi_bpjs ) &&
            is_null( $web_registration->nama )
        ) {
            return view('web_registrations.nama');
        } else if (
            !is_null( $web_registration ) &&
            !is_null( $web_registration->tipe_konsultasi_id ) &&
            !is_null( $web_registration->registrasi_pembayaran_id ) &&
            !is_null( $web_registration->register_previously_saved_patient ) &&
            !is_null( $web_registration->nomor_asuransi_bpjs ) &&
            !is_null( $web_registration->nama ) &&
            is_null( $web_registration->tanggal_lahir )
        ) {
            return view('web_registrations.tanggal_lahir');
        } else if (
            !is_null( $web_registration ) &&
            !is_null( $web_registration->tipe_konsultasi_id ) &&
            !is_null( $web_registration->registrasi_pembayaran_id ) &&
            !is_null( $web_registration->register_previously_saved_patient ) &&
            !is_null( $web_registration->nomor_asuransi_bpjs ) &&
            !is_null( $web_registration->nama ) &&
            !is_null( $web_registration->tanggal_lahir ) &&
            is_null( $web_registration->alamat )
        ) {
            return view('web_registrations.alamat');
        } else if (
            !is_null( $web_registration ) &&
            !is_null( $web_registration->tipe_konsultasi_id ) &&
            !is_null( $web_registration->registrasi_pembayaran_id ) &&
            !is_null( $web_registration->register_previously_saved_patient ) &&
            !is_null( $web_registration->nomor_asuransi_bpjs ) &&
            !is_null( $web_registration->nama ) &&
            !is_null( $web_registration->tanggal_lahir ) &&
            !is_null( $web_registration->alamat ) &&
            is_null( $web_registration->staf_id )
        ) {
            $web_registration = WebRegistration::where('no_telp', $no_telp)
                                            ->whereDate('created_at', date('Y-m-d'))
                                            ->first();
            $tipe_konsultasi_id = $web_registration->tipe_konsultasi_id;

            // Dokter gigi via web = mode reservasi terjadwal: tampilkan SEMUA
            // petugas dgn schedulled_booking_allowed=1 hari ini, tanpa filter jam.
            if ($tipe_konsultasi_id == 2) {
                // Mirror WablasController petugas_pemeriksa_sekarang (line 6030-6037):
                // selain schedulled_booking_allowed, juga butuh online_registration_enabled
                // & registration_enabled. Jadwal yg dimatikan tidak boleh muncul.
                $petugas_pemeriksas = PetugasPemeriksa::whereDate('tanggal', date('Y-m-d'))
                    ->where('tipe_konsultasi_id', 2)
                    ->where('schedulled_booking_allowed', 1)
                    ->where('online_registration_enabled', 1)
                    ->where('registration_enabled', 1)
                    ->where('ruangan_id', '>', 0)
                    ->orderBy('jam_mulai', 'asc')
                    ->get();

                return view('web_registrations.staf', compact('petugas_pemeriksas'));
            }

            $petugas_pemeriksas = PetugasPemeriksa::whereDate('tanggal', date('Y-m-d'))
                                                    ->where('tipe_konsultasi_id', $tipe_konsultasi_id)
                                                    ->get();
            /* dd( $tipe_konsultasi_id ); */
            $this->message = null;

            //
            // jika dokter gigi ada tapi belum masuk waktu pendaftaran
            // buat pesan error pendaftaran dimulai jam sekian
            //
            if (
                 $petugas_pemeriksas->count()
            ) {
                $petugas_pemeriksas = PetugasPemeriksa::where('tanggal', date('Y-m-d'))
                                            ->where('jam_mulai', '<=', date('H:i:s'))
                                            ->where('jam_akhir', '>=', date('H:i:s'))
                                            ->where('tipe_konsultasi_id', $tipe_konsultasi_id)
                                            ->where('ruangan_id','>', 0)
                                            ->get();

                $jumlah_petugas_pemeriksas_saat_ini = $petugas_pemeriksas->count();

                //
                // ANTRIKAN PASIEN UNTUK DOKTER KEDUA
                //
                if (
                    $jumlah_petugas_pemeriksas_saat_ini == 1
                ) {

                    //
                    // JIKA SUDAH ADA 2 RUANGAN YANG DIAKTIFKAN, AKTIFKAN KEDUANYA
                    //

                    //
                    // JIKA PASIEN SUDAH MENUMPUK NAMUN DOKTER KEDUA BELUM DATANG
                    //
                    $sisa_antrian = $petugas_pemeriksas->first()->sisa_antrian;
                    $waktu_tunggu_menit = $sisa_antrian * 3;

                    $jam_mulai_akhir_antrian = Carbon::now()->addMinutes( $waktu_tunggu_menit )->format('H:i:s');
                    $petugas_pemeriksas_nanti = PetugasPemeriksa::where('tanggal', date('Y-m-d'))
                                                ->where('jam_mulai', '<=', $jam_mulai_akhir_antrian)
                                                ->where('jam_akhir', '>=', $jam_mulai_akhir_antrian)
                                                ->where('tipe_konsultasi_id', $tipe_konsultasi_id)
                                                ->get();
                    /* dd($petugas_pemeriksas_nanti); */
                    if ($petugas_pemeriksas_nanti->count() > 1) {
                        $petugas_pemeriksas = $petugas_pemeriksas_nanti;
                    }
                }


                if ( $petugas_pemeriksas->count() > 1 ) {
                    // populate ulang petugas pemeriksa
                    $repopulate = [];
                    foreach ($petugas_pemeriksas as $petugas) {
                        $repopulate[] = [
                            'data'         => $petugas,
                            'sisa_antrian' => $petugas->sisa_antrian
                        ];
                    }
                    usort($repopulate, function($a, $b) {
                        return $a['sisa_antrian'] <=> $b['sisa_antrian'];
                    });

                    $data = [];
                    foreach ($repopulate as $petugas) {
                        $data[] = $petugas['data'];
                    }
                    $petugas_pemeriksas = collect($data);
                }
            }
            return view('web_registrations.staf', compact('petugas_pemeriksas'));
        } else if (
            !is_null( $web_registration ) &&
            (int) ($web_registration->schedulled_booking ?? 0) === 2 &&
            is_null( $web_registration->waitlist_flag )
        ) {
            // Slot petugas penuh — tawarkan waitlist sebelum lanjut.
            $petugas_pemeriksa = $web_registration->petugas_pemeriksa_id
                ? PetugasPemeriksa::find($web_registration->petugas_pemeriksa_id)
                : null;
            return view('web_registrations.waitlist', compact('web_registration', 'petugas_pemeriksa'));
        } else if (
            !is_null( $web_registration ) &&
            !is_null( $web_registration->tipe_konsultasi_id ) &&
            !is_null( $web_registration->registrasi_pembayaran_id ) &&
            !is_null( $web_registration->register_previously_saved_patient ) &&
            !is_null( $web_registration->nomor_asuransi_bpjs ) &&
            !is_null( $web_registration->nama ) &&
            !is_null( $web_registration->tanggal_lahir ) &&
            !is_null( $web_registration->alamat ) &&
            !is_null( $web_registration->staf_id ) &&
            $web_registration->data_terkonfirmasi == 0
        ) {
            return view('web_registrations.data_terkonfirmasi', compact('web_registration'));
        } else if(
            !is_null( $web_registration )
        ) {
            $web_registration->delete();
            $tipe_konsultasi_dokter_umum = TipeKonsultasi::find(1);
            $tipe_konsultasi_dokter_gigi = TipeKonsultasi::find(2);
            $tipe_konsultasi_bidan = TipeKonsultasi::find(3);
            return view('web_registrations.tipe_konsultasi', compact(
                'antrians',
                'tipe_konsultasi_dokter_umum',
                'tipe_konsultasi_dokter_gigi',
                'tipe_konsultasi_bidan'
            ));
        }
    }

    public function submit_tipe_konsultasi(){
        $tipe_konsultasi_id = Input::get('value');
        $no_telp            = Input::get('no_telp');

        // Guard anti double-daftar: kalau sudah ada antrian/reservasi terjadwal
        // aktif hari ini, jangan boleh mulai pendaftaran baru.
        if ($this->sudahPunyaPendaftaranHariIni($no_telp)) {
            $this->message = 'Anda sudah memiliki pendaftaran hari ini. Tidak bisa daftar ulang sebelum yang lama dibatalkan.';
            $message = view('web_registrations.message', ['message' => $this->message])->render();
            return compact('message');
        }

        // Untuk dokter gigi (tipe=2): aturan disamakan dengan WablasController
        // validasiDokterPengambilanAntrianDokterGigi (line 6347-6463) — kecuali
        // fallback "hanya lewat whatsapp" tidak dipakai karena web sudah punya
        // fitur reservasi terjadwal yang sama dengan whatsapp bot.
        if ($tipe_konsultasi_id == '2') {
            $petugas_pemeriksa_terjadwal = PetugasPemeriksa::whereDate('tanggal', date('Y-m-d'))
                ->where('tipe_konsultasi_id', 2)
                ->where('schedulled_booking_allowed', 1)
                ->where('online_registration_enabled', 1)
                ->where('registration_enabled', 1)
                ->orderBy('jam_mulai_default', 'asc')
                ->get();

            if ($petugas_pemeriksa_terjadwal->isEmpty()) {
                $this->message = $this->pesanPelayananGigiLibur();
                $message = view('web_registrations.message', ['message' => $this->message])->render();
                return compact('message');
            }

            $errorWindow = $this->validasiWindowReservasiTerjadwalGigi($petugas_pemeriksa_terjadwal);
            if (!is_null($errorWindow)) {
                $this->message = $errorWindow;
                $message = view('web_registrations.message', ['message' => $this->message])->render();
                return compact('message');
            }

            $web_registration = WebRegistration::whereDate('created_at', date('Y-m-d'))
                ->where('no_telp', $no_telp)
                ->first();

            if (is_null($web_registration)) {
                WebRegistration::create([
                    'no_telp'            => $no_telp,
                    'tipe_konsultasi_id' => $tipe_konsultasi_id,
                ]);
            } else {
                $web_registration->tipe_konsultasi_id = $tipe_konsultasi_id;
                $web_registration->save();
            }
            $this->message = null;
            $message = view('web_registrations.message', ['message' => $this->message])->render();
            return compact('message');
        }

        $cek             = $this->cek($tipe_konsultasi_id);
        $this->message   = $cek['message'];

        if (empty($this->message)) {
            $web_registration = WebRegistration::whereDate('created_at', date('Y-m-d'))
                ->where('no_telp', $no_telp)
                ->first();
            if (is_null($web_registration)) {
                WebRegistration::create([
                    'no_telp'            => $no_telp,
                    'tipe_konsultasi_id' => $tipe_konsultasi_id,
                ]);
            } else {
                $web_registration->tipe_konsultasi_id = $tipe_konsultasi_id;
                $web_registration->save();
            }
        }

        $message = view('web_registrations.message', ['message' => $this->message])->render();
        return compact('message');
    }
    public function nomor_asuransi_bpjs(){
        $nomor_asuransi_bpjs = Input::get('value');
        $no_telp = Input::get('no_telp');
        $web_registration = WebRegistration::where('no_telp', $no_telp)
                                            ->whereDate('created_at', date('Y-m-d'))
                                            ->first();

        $web_registration->nomor_asuransi_bpjs = $nomor_asuransi_bpjs;
        $pasien = Pasien::where('nomor_asuransi_bpjs', $nomor_asuransi_bpjs )->first();

        if (!is_null( $pasien )) {
            $web_registration->nama          = $pasien->nama;
            $web_registration->tanggal_lahir = $pasien->tanggal_lahir;
            $web_registration->alamat        = $pasien->alamat;
            $web_registration->pasien_id     = $pasien->id;
        } else {
            $bpjs                                  = new BpjsApiController;
            $response                              = $bpjs->pencarianNoKartuValid( $nomor_asuransi_bpjs, true );
            $this->message                               = $response['response'];
            if (
                !is_null( $this->message ) &&
                $this->message['aktif'] &&
                isset( $this->message['kdProviderPst'] ) &&
                $this->message['kdProviderPst']['kdProvider'] == '0221B119'
            ) { // jika aktig
                $web_registration->nama                = $this->message['nama'];
                $web_registration->tanggal_lahir       = Carbon::createFromFormat("d-m-Y", $this->message['tglLahir'])->format("Y-m-d");
            }
        }
        $web_registration->save();

        $this->message = null;
        $message =  view('web_registrations.message', [ 'message' => $this->message ])->render();
        return compact(
            'message',
        );
    }

    public function nama(){
        $nama = Input::get('value');
        $no_telp = Input::get('no_telp');
        $web_registration = WebRegistration::where('no_telp', $no_telp)
                                            ->whereDate('created_at', date('Y-m-d'))
                                            ->first();

        $web_registration->nama = $nama;
        $web_registration->save();

        $this->message = null;
        $message =  view('web_registrations.message', [ 'message' => $this->message ])->render();
        return compact(
            'message',
        );
    }

    public function tanggal_lahir(){
        $tanggal_lahir = Input::get('value');
        $no_telp = Input::get('no_telp');
        $web_registration = WebRegistration::where('no_telp', $no_telp)
                                            ->whereDate('created_at', date('Y-m-d'))
                                            ->first();

        $web_registration->tanggal_lahir = Carbon::createFromFormat('d-m-Y', $tanggal_lahir)->format('Y-m-d');
        $web_registration->save();

        $this->message = null;
        $message =  view('web_registrations.message', [ 'message' => $this->message ])->render();
        return compact(
            'message',
        );
    }

    public function alamat(){
        $alamat = Input::get('value');
        $no_telp = Input::get('no_telp');
        $web_registration = WebRegistration::where('no_telp', $no_telp)
                                            ->whereDate('created_at', date('Y-m-d'))
                                            ->first();

        $web_registration->alamat = $alamat;
        $web_registration->save();

        $this->message = null;
        $message =  view('web_registrations.message', [ 'message' => $this->message ])->render();
        return compact(
            'message',
        );
    }
    public function staf()
    {
        $petugas_pemeriksa_id = Input::get('value');
        $no_telp              = Input::get('no_telp');

        $web_registration = WebRegistration::where('no_telp', $no_telp)
            ->whereDate('created_at', date('Y-m-d'))
            ->first();

        $alert_type = 'alert-danger';

        if (!$web_registration) {
            $this->message = 'registrasi tidak ditemukan';
        } else {
            $petugas_pemeriksa = PetugasPemeriksa::find($petugas_pemeriksa_id);
            if ($petugas_pemeriksa) {
                $isSchedulingAllowed = (int) ($petugas_pemeriksa->schedulled_booking_allowed ?? 0) === 1;
                $nama_dokter         = $petugas_pemeriksa->staf->nama_dengan_gelar;

                // Khusus path reservasi terjadwal: aturan disamakan dgn
                // WablasController line 3473-3513 — cek online_registration_enabled,
                // registration_enabled, slot_pendaftaran_available, jam_praktek_terlewat.
                if ($isSchedulingAllowed) {
                    if (!$petugas_pemeriksa->online_registration_enabled) {
                        $web_registration->delete();
                        $this->message = $this->pesanHanyaPendaftaranLangsung($petugas_pemeriksa);
                    } else if (!$petugas_pemeriksa->registration_enabled || $petugas_pemeriksa->jam_praktek_terlewat) {
                        $web_registration->delete();
                        $this->message = $this->pesanPendaftaranSudahDitutup($petugas_pemeriksa);
                    } else if (!$petugas_pemeriksa->slot_pendaftaran_available) {
                        // Slot penuh + scheduling allowed → tawarkan waitlist
                        // (mirror Wablas line 3818-3825 saat finalisasi).
                        $web_registration->staf_id              = $petugas_pemeriksa->staf_id;
                        $web_registration->ruangan_id           = $petugas_pemeriksa->ruangan_id;
                        $web_registration->petugas_pemeriksa_id = $petugas_pemeriksa->id;
                        $web_registration->schedulled_booking   = 2;
                        $web_registration->waitlist_flag        = null;
                        $web_registration->save();

                        $this->message = $this->pesanKuotaPenuhDenganWaitlist($petugas_pemeriksa);
                        $alert_type    = 'alert-warning';
                    } else {
                        $web_registration->staf_id              = $petugas_pemeriksa->staf_id;
                        $web_registration->ruangan_id           = $petugas_pemeriksa->ruangan_id;
                        $web_registration->petugas_pemeriksa_id = $petugas_pemeriksa->id;
                        $web_registration->schedulled_booking   = 1;
                        $web_registration->save();

                        $this->message = $this->pesanReservasiTerjadwalDipilih($petugas_pemeriksa);
                        $alert_type    = 'alert-info';
                    }
                } else {
                    // Path antrian walk-in (non-scheduled) — perilaku existing tidak diubah.
                    if (!$petugas_pemeriksa->registration_enabled) {
                        $web_registration->delete();
                        $this->message = "Pendaftaran ke {$nama_dokter} sudah ditutup";
                    } else if (!$petugas_pemeriksa->slot_pendaftaran_available) {
                        $web_registration->delete();
                        $this->message = "Pendaftaran ke {$nama_dokter} sudah ditutup karena sudah penuh";
                    } else {
                        $web_registration->staf_id              = $petugas_pemeriksa->staf_id;
                        $web_registration->ruangan_id           = $petugas_pemeriksa->ruangan_id;
                        $web_registration->petugas_pemeriksa_id = $petugas_pemeriksa->id;
                        $web_registration->schedulled_booking   = 0;
                        $web_registration->save();

                        $this->message = null;
                    }
                }
            } else {
                $this->message = 'petugas pemeriksa tidak ditemukan';
            }
        }

        $message = view('web_registrations.message', [
            'message'    => $this->message,
            'alert_type' => $alert_type,
        ])->render();

        return compact('message');
    }

    /**
     * Set respon waitlist saat slot penuh (schedulled_booking=2).
     * value=1 → setuju waitlist, lainnya → tolak (web_registration di-delete).
     */
    public function waitlist()
    {
        $no_telp = Input::get('no_telp');
        $value   = (int) Input::get('value');

        $web_registration = WebRegistration::where('no_telp', $no_telp)
            ->whereDate('created_at', date('Y-m-d'))
            ->where('schedulled_booking', 2)
            ->first();

        $alert_type = 'alert-danger';

        if (!$web_registration) {
            $this->message = 'registrasi waitlist tidak ditemukan';
        } else if ($value === 1) {
            $web_registration->waitlist_flag = 1;
            $web_registration->save();
            $this->message = $this->pesanWaitlistDisetujui();
            $alert_type    = 'alert-info';
        } else {
            $web_registration->delete();
            $this->message = 'Reservasi dibatalkan';
        }

        $message = view('web_registrations.message', [
            'message'    => $this->message,
            'alert_type' => $alert_type,
        ])->render();

        return compact('message');
    }

    public function cek($tipe_konsultasi_id){
        $petugas_pemeriksas = PetugasPemeriksa::whereDate('tanggal', date('Y-m-d'))
                                                ->where('tipe_konsultasi_id', $tipe_konsultasi_id)
                                                ->get();
        $this->message = null;

        //
        // jika dokter gigi ada tapi belum masuk waktu pendaftaran
        // buat pesan error pendaftaran dimulai jam sekian
        //
        if (
             $petugas_pemeriksas->count()
        ) {
            if (
                $tipe_konsultasi_id == 2 // dokter gigi
            ) {
                $jam_akhir_gigi = $petugas_pemeriksas[0]->jam_akhir;
                $jam_akhir_pendaftaran_gigi = Carbon::parse( $petugas_pemeriksas[0]->jam_akhir )->subMinutes(30)->format('H:i:s');
                if (
                    $petugas_pemeriksas[0]->jam_mulai >= date("H:i:s")
                ) {
                    $this->message = 'Pendaftaran dokter gigi dimulai jam ' . $petugas_pemeriksas[0]->jam_mulai;
                } else if (
                    $jam_akhir_pendaftaran_gigi <= date("H:i:s")
                ) {
                    $this->message = 'Pendaftaran dokter gigi telah berakhir hari ini';
                }
            } else  {

                $petugas_pemeriksas = PetugasPemeriksa::where('tanggal', date('Y-m-d'))
                                            ->where('jam_mulai', '<=', date('H:i:s'))
                                            ->where('jam_akhir', '>=', date('H:i:s'))
                                            ->where('tipe_konsultasi_id', $tipe_konsultasi_id)
                                            ->where('ruangan_id','>', 0)
                                            ->get();

                $jumlah_petugas_pemeriksas_saat_ini = $petugas_pemeriksas->count();


                //
                // ANTRIKAN PASIEN UNTUK DOKTER KEDUA
                //
                if (
                    $jumlah_petugas_pemeriksas_saat_ini == 1
                ) {

                    //
                    // JIKA SUDAH ADA 2 RUANGAN YANG DIAKTIFKAN, AKTIFKAN KEDUANYA
                    //




                    //
                    // JIKA PASIEN SUDAH MENUMPUK NAMUN DOKTER KEDUA BELUM DATANG
                    //
                    $sisa_antrian = $petugas_pemeriksas->first()->sisa_antrian;
                    $waktu_tunggu_menit = $sisa_antrian * 3;

                    $jam_mulai_akhir_antrian = Carbon::now()->addMinutes( $waktu_tunggu_menit )->format('H:i:s');
                    $petugas_pemeriksas_nanti = PetugasPemeriksa::where('tanggal', date('Y-m-d'))
                                                ->where('jam_mulai', '<=', $jam_mulai_akhir_antrian)
                                                ->where('jam_akhir', '>=', $jam_mulai_akhir_antrian)
                                                ->where('tipe_konsultasi_id', $tipe_konsultasi_id)
                                                ->get();
                    /* dd($petugas_pemeriksas_nanti); */
                    if ($petugas_pemeriksas_nanti->count() > 1) {
                        $petugas_pemeriksas = $petugas_pemeriksas_nanti;
                    }
                }


                if ( $petugas_pemeriksas->count() > 1 ) {
                    // populate ulang petugas pemeriksa
                    $repopulate = [];
                    foreach ($petugas_pemeriksas as $petugas) {
                        $repopulate[] = [
                            'data'         => $petugas,
                            'sisa_antrian' => $petugas->sisa_antrian
                        ];
                    }
                    usort($repopulate, function($a, $b) {
                        return $a['sisa_antrian'] <=> $b['sisa_antrian'];
                    });

                    $data = [];
                    foreach ($repopulate as $petugas) {
                        $data[] = $petugas['data'];
                    }
                    $petugas_pemeriksas = collect($data);
                } else if (
                    $petugas_pemeriksas->count() < 1
                ) {
                    $tipe_konsultasi = TipeKonsultasi::find( $tipe_konsultasi_id );
                    $this->message = 'Tidak ada petugas ' . ucwords( $tipe_konsultasi->tipe_konsultasi ) . ' yang bertugas saat ini';
                }
            }
        } else {
            $tipe_konsultasi = TipeKonsultasi::find( $tipe_konsultasi_id );
            if (is_null( $tipe_konsultasi )) {
                Log::info('===========================');
                Log::info('TIPE KONSULTASI NULL KARENA');
                Log::info(Input::all());
                Log::info('===========================');
            }
            $this->message = 'Tidak ada petugas ' . ucwords( $tipe_konsultasi->tipe_konsultasi ) . ' yang bertugas hari ini';
        }
        return [
            'count'              => count( $petugas_pemeriksas ),
            'petugas'            => $petugas_pemeriksas ,
            'message'            => $this->message,
            'tipe_konsultasi_id' => $tipe_konsultasi_id,
        ];
    }
    public function pasien(){
        $pasien_id = Input::get('value');
        $no_telp = Input::get('no_telp');
        $web_registration = WebRegistration::where('no_telp', $no_telp)
                                        ->whereDate('created_at', date('Y-m-d'))
                                        ->first();
        $bisa_digunakan = true;
        $this->message = null;
        if (!is_null( $pasien_id )) {
            $pasien = Pasien::find( $pasien_id );
            if (!is_null( $pasien )) {
                if (
                    $web_registration->registrasi_pembayaran_id == 2 // Pembayaran BPJS
                ) {
                    $response = $this->cekBpjsApi( $pasien->nomor_asuransi_bpjs );
                    if (
                        isset( $response['bisa_digunakan'] ) &&
                        isset( $response['pesan'] )
                    ) {
                        $bisa_digunakan = $response['bisa_digunakan'] ;
                        $this->message = $response['pesan'] ;
                    }
                }

                if ($bisa_digunakan) {
                    $web_registration->pasien_id = $pasien_id;
                    $web_registration->nama = $pasien->nama;
                    $web_registration->alamat = $pasien->alamat;
                    $web_registration->tanggal_lahir = $pasien->tanggal_lahir;
                    $web_registration->nomor_asuransi_bpjs = $pasien->nomor_asuransi_bpjs;
                    $web_registration->register_previously_saved_patient = 1;
                }
            }
        } else {
            $web_registration->register_previously_saved_patient = 0;
        }

        $web_registration->save();
        $message =  view('web_registrations.message', [ 'message' => $this->message ])->render();
        return compact(
            'message'
        );
    }
    public function lanjutkan(){
        $no_telp = Input::get('no_telp');
        $web_registration = WebRegistration::where('no_telp', $no_telp)
                                        ->whereDate('created_at', date('Y-m-d'))
                                        ->first();

        if (!$web_registration) {
            $this->message = 'registrasi tidak ditemukan';
            $message =  view('web_registrations.message', [ 'message' => $this->message ])->render();
            return compact('message');
        }

        $web_registration->data_terkonfirmasi = 1;
        $web_registration->save();

        $wablas = new WablasController;
        $isScheduled = (int) ($web_registration->schedulled_booking ?? 0) >= 1;

        // ===== RESERVASI TERJADWAL =====
        if ($isScheduled) {
            return \DB::transaction(function () use ($web_registration, $wablas, $no_telp) {
                $petugas_pemeriksa = $web_registration->petugas_pemeriksa_id
                    ? PetugasPemeriksa::find($web_registration->petugas_pemeriksa_id)
                    : null;

                // Slot penuh saat finalisasi → tetapkan ke waitlist (butuh waitlist_flag=1).
                if ($petugas_pemeriksa && !$petugas_pemeriksa->slot_pendaftaran_available) {
                    if ((int) $web_registration->waitlist_flag !== 1) {
                        $web_registration->schedulled_booking = 2;
                        $web_registration->save();

                        $this->message = "Slot {$petugas_pemeriksa->staf->nama_dengan_gelar} sudah penuh. Setujui waitlist untuk melanjutkan.";
                        $message = view('web_registrations.message', [
                            'message'    => $this->message,
                            'alert_type' => 'alert-warning',
                        ])->render();
                        return compact('message');
                    }
                }

                $data = $web_registration->toArray();
                unset($data['id']);
                unset($data['data_terkonfirmasi']);

                // schedulled_reservations punya NOT NULL constraint utk
                // waitlist_flag — coerce null → 0 supaya insert tidak ditolak.
                if (!array_key_exists('waitlist_flag', $data) || is_null($data['waitlist_flag'])) {
                    $data['waitlist_flag'] = 0;
                }

                $schedulled_reservation = SchedulledReservation::fromSourceArray($data);
                $schedulled_reservation->reservasi_selesai = 1;
                $schedulled_reservation->qrcode = $wablas->generateQrCodeForOnlineReservation('B', $schedulled_reservation);
                $schedulled_reservation->save();

                WebRegistration::where('no_telp', $no_telp)->delete();

                $isWaitlist = (int) ($schedulled_reservation->waitlist_flag ?? 0) === 1;
                $this->message = $isWaitlist
                    ? $this->pesanWaitlistTercatat($schedulled_reservation)
                    : $this->pesanReservasiTerjadwalTercatat($schedulled_reservation);
                $message = view('web_registrations.message', [
                    'message'    => $this->message,
                    'alert_type' => 'alert-success',
                ])->render();
                return compact('message');
            });
        }

        // ===== ANTRIAN WALK-IN (existing flow) =====
        $wablas->input_registrasi_pembayaran_id = $web_registration->registrasi_pembayaran_id;
        $antrian                = $wablas->antrianPost( $web_registration->ruangan_id );


        $antrian->nama                     = $web_registration->nama;
        $antrian->nomor_bpjs               = $web_registration->nomor_asuransi_bpjs;
        $antrian->no_telp                  = $web_registration->no_telp;
        $antrian->tanggal_lahir            = $web_registration->tanggal_lahir;
        $antrian->alamat                   = $web_registration->alamat;
        $antrian->pasien_id                = $web_registration->pasien_id;
        $antrian->ruangan_id               = $web_registration->ruangan_id;
        $antrian->tipe_konsultasi_id       = $web_registration->tipe_konsultasi_id;
        $antrian->staf_id                  = $web_registration->staf_id;
        $antrian->reservasi_online         = 1;
        $antrian->sumber_antrian_id        = \App\Models\SumberAntrian::idFor(\App\Models\SumberAntrian::WEB_KLINIK);
        $antrian->sudah_hadir_di_klinik    = 0;
        $antrian->qr_code_path_s3          = $wablas->generateQrCodeForOnlineReservation('A', $antrian);
        $antrian->save();
        $antrian->antriable_id             = $antrian->id;
        $antrian->save();

        WebRegistration::where('no_telp', $no_telp)->delete();

        $this->message =  null;
        $message =  view('web_registrations.message', [ 'message' => $this->message ])->render();
        return compact(
            'message'
        );
    }

    public function ulangi(){
        $no_telp = Input::get('no_telp');
        $web_registration = WebRegistration::where('no_telp', $no_telp)
                                        ->whereDate('created_at', date('Y-m-d'))
                                        ->first();
        if ( !is_null( $web_registration ) ) {
            $web_registration->delete();
        }
        $this->message =  null;
        $message =  view('web_registrations.message', [ 'message' => $this->message ])->render();
        return compact(
            'message'
        );
    }
    public function validasi_bpjs(){
        $nomor_asuransi_bpjs = Input::get('value');
        return $this->cekBpjsApi( $nomor_asuransi_bpjs );
    }
    public function batalkan(){
        $no_telp = Input::get('no_telp');
        Antrian::where('no_telp',  $no_telp )
                ->whereDate('created_at', date('Y-m-d'))
                ->where('sudah_hadir_di_klinik', 0)
                ->whereRaw(
                    "(
                        antriable_type = 'App\\\Models\\\Antrian'
                    )"
                )
                ->delete();

        // Reservasi terjadwal hari ini juga ikut dihapus.
        SchedulledReservation::where('no_telp', $no_telp)
                ->whereDate('created_at', date('Y-m-d'))
                ->delete();
    }

    public function hapus_schedulled_reservation(){
        $no_telp = Input::get('no_telp');
        $id      = Input::get('schedulled_reservation_id');

        $sr = SchedulledReservation::where('id', $id)
            ->where('no_telp', $no_telp)
            ->whereDate('created_at', date('Y-m-d'))
            ->first();

        if (!is_null($sr)) {
            $sr->delete();
        }
    }

    public function daftar_lagi(){
        $no_telp = Input::get('no_telp');

        if ($this->sudahPunyaPendaftaranHariIni($no_telp)) {
            $this->message = 'Anda sudah memiliki pendaftaran hari ini. Tidak bisa daftar ulang sebelum yang lama dibatalkan.';
            $message = view('web_registrations.message', ['message' => $this->message])->render();
            return compact('message');
        }

        WebRegistration::create([
            'no_telp' => $no_telp,
            'tipe_konsultasi_id' => null
        ]);
    }

    /**
     * Cek apakah pasien sudah punya antrian / reservasi terjadwal aktif hari ini.
     * Dipakai sebagai guard anti double-daftar di entry point pendaftaran baru.
     */
    private function sudahPunyaPendaftaranHariIni($no_telp): bool
    {
        $today = date('Y-m-d');

        $hasAntrian = Antrian::where('no_telp', $no_telp)
            ->whereDate('created_at', $today)
            ->whereRaw(
                "(antriable_type = 'App\\\Models\\\Antrian' or
                  antriable_type = 'App\\\Models\\\AntrianPeriksa' or
                  antriable_type = 'App\\\Models\\\AntrianPoli')"
            )
            ->exists();

        if ($hasAntrian) {
            return true;
        }

        return SchedulledReservation::where('no_telp', $no_telp)
            ->whereDate('created_at', $today)
            ->exists();
    }
    public function cekBpjsApi($nomor_asuransi_bpjs){
        $bpjs                                                = new BpjsApiController;
        $response                                            = $bpjs->pencarianNoKartuValid($nomor_asuransi_bpjs, true);
        if (
            !is_null( $response ) &&
            isset( $response['code'] ) &&
            (
                isset( $response['response'] ) ||
                is_null( $response['response'] )
            )

        ) {
            $code                                                = $response['code'];
            $this->message                                             = $response['response'];
            if (
                $code == 204
            ) {// jika tidak ditemukan
                $pesan = "Nomor Kartu $nomor_asuransi_bpjs tidak ditemukan di sistem BPJS";
                $bisa_digunakan = false;
                return compact(
                    'bisa_digunakan',
                    'pesan'
                );

            } else if (
                $code >= 200 &&
                $code <= 299
            ){
                // jika kartu aktif dan provider tepat
                if (
                    !is_null($this->message) &&
                    $this->message['aktif'] &&
                    $this->message['kdProviderPst']['kdProvider'] == '0221B119'
                ) {
                    $pesan = 'Kartu bisa digunakan';
                    $bisa_digunakan = true;
                    return compact(
                        'bisa_digunakan',
                        'pesan'
                    );

                } else if(
                    !is_null($this->message) &&
                    !$this->message['aktif']
                ) {
                    $pesan = 'Kartu tidak aktif karena : ';
                    $pesan .= $this->message['ketAktif'];
                    $bisa_digunakan = false;

                    return compact(
                        'bisa_digunakan',
                        'pesan'
                    );

                } else if(
                    !is_null($this->message) &&
                    $this->message['kdProviderPst']['kdProvider'] !== '0221B119'
                ) {
                    $pesan = 'kartu tidak dapat digunakan karena tercatat aktif di : ' . $this->message['kdProviderPst']['kdProvider'];
                    $pesan .= "Jika menurut Anda ini adalah kesalahan, silahkan langsung daftar di klinik";
                    $bisa_digunakan = false;

                    return compact(
                        'bisa_digunakan',
                        'pesan'
                    );
                }
            }
        }

        $bisa_digunakan = true;
        $pesan = 'Validasi dilanjutkan di Klinik';
        $code = null;
        return compact(
            'bisa_digunakan',
            'pesan'
        );
    }
    public function hapus_antrian(){
        $antrian_id = Input::get('antrian_id');
        $antrian = Antrian::find( $antrian_id );
        if (!is_null( $antrian )) {
            $antrian->delete();
        }
    }
    public function cek_antrian(){
        $data = [];
        $no_telp = Input::get('no_telp');
        $antrians = Antrian::whereDate('created_at', date('Y-m-d'))
                            ->where('no_telp', $no_telp)
                            ->get();

        foreach ($antrians as $antrian) {
            $data[] = [
                'nomor_antrian_terakhir' => $antrian->nomor_antrian_dipanggil,
                'nama_ruangan'           => $antrian->ruangan->nama,
                'nomor_antrian_anda'     => $antrian->nomor_antrian
            ];
        }
        return view('web_registrations.cek_antrian_container', compact('data'))->render();
    }
    public function convertToWablasFriendlyFormat($no_telp) {
         if (
             !empty($no_telp) &&
              $no_telp[0] == 0
         ) {
             $no_telp = '62' . substr($no_telp, 1);
         }
         return $no_telp;
    }

    /**
     * Validasi window pendaftaran reservasi terjadwal dokter gigi.
     * Mirror WablasController validasiDokterPengambilanAntrianDokterGigi
     * (line 6347-6463). Return null bila masih boleh daftar; string pesan
     * error bila di luar window.
     *
     * Aturan:
     *  - Bila tenant punya jam_buka & sekarang < jam_buka → "baru dimulai pukul X"
     *  - Deadline = jam_mulai_default jadwal terakhir - 30 menit. Bila sekarang
     *    >= deadline → "berakhir pukul X" + listing jadwal hari ini.
     */
    private function validasiWindowReservasiTerjadwalGigi($petugas_pemeriksa_terjadwal): ?string
    {
        if ($petugas_pemeriksa_terjadwal->isEmpty()) {
            return null;
        }

        $tz     = 'Asia/Jakarta';
        $nowJkt = Carbon::now($tz);

        if (!empty($this->tenant->jam_buka)) {
            $jam_buka = Carbon::parse($this->tenant->jam_buka, $tz);
            if ($nowJkt->lt($jam_buka)) {
                return
                    "Pendaftaran online baru dimulai pada {$jam_buka->format('H:i')}.\n" .
                    "Silakan mendaftar kembali setelah jam tersebut.\n" .
                    "Mohon maaf atas ketidaknyamanannya.";
            }
        }

        $petugas_terakhir = $petugas_pemeriksa_terjadwal->last();
        if (empty($petugas_terakhir->jam_mulai_default)) {
            return null;
        }

        $deadline        = Carbon::parse($petugas_terakhir->jam_mulai_default, $tz)->subMinutes(30);
        $tipe_konsultasi = ucwords(optional($petugas_terakhir->tipe_konsultasi)->tipe_konsultasi ?? 'Dokter Gigi');

        if ($nowJkt->lt($deadline)) {
            return null;
        }

        $message = "Pendaftaran online terjadwal {$tipe_konsultasi} berakhir pukul {$deadline->format('H:i')}.\n";
        $message .= "Jadwal {$tipe_konsultasi} hari ini :";

        $petugas_hari_ini = PetugasPemeriksa::with('staf')
            ->where('tipe_konsultasi_id', $petugas_terakhir->tipe_konsultasi_id)
            ->whereDate('tanggal', $nowJkt->toDateString())
            ->orderBy('jam_mulai_default')
            ->get();

        foreach ($petugas_hari_ini as $pp) {
            $mulai = Carbon::parse($pp->jam_mulai_default, $tz)->format('H:i');
            $akhir = Carbon::parse($pp->jam_akhir_default, $tz)->subMinutes(30)->format('H:i');
            $nama  = optional($pp->staf)->nama_dengan_gelar ?? 'Dokter';
            $message .= "\n{$nama}: {$mulai}-{$akhir}";
            if ((int) $pp->max_booking > 0) {
                $message .= " (tersisa {$pp->slot_pendaftaran} slot)";
                $message .= "\nSilahkan daftar di klinik jika masih ada slot";
            }
        }

        return $message;
    }

    /**
     * Pesan ketika tidak ada jadwal reservasi terjadwal dokter gigi yang aktif
     * hari ini. Mirror WablasController pelayananPoliGigiLibur (line 6709) tanpa
     * instruksi WA-specific.
     */
    private function pesanPelayananGigiLibur(): string
    {
        return
            "Hari ini pelayanan reservasi terjadwal dokter gigi belum tersedia.\n" .
            "Silakan mendaftar kembali saat jadwal tersedia.\n" .
            "Mohon maaf atas ketidaknyamanannya.";
    }

    /**
     * Pesan dokter hanya melayani pendaftaran langsung (mirror WablasController
     * line 3479-3486 untuk !online_registration_enabled).
     */
    private function pesanHanyaPendaftaranLangsung($petugas_pemeriksa): string
    {
        $nama_dokter = $petugas_pemeriksa->staf->nama_dengan_gelar;
        return
            "{$nama_dokter} hanya bisa pendaftaran langsung.\n" .
            "Silahkan datang mendaftar pada saat dokter berpraktek.\n" .
            "Jam {$petugas_pemeriksa->jadwal_hari_ini} pada hari ini.";
    }

    /**
     * Pesan pendaftaran sudah ditutup (mirror WablasController line 3489-3494
     * untuk !registration_enabled || jam_praktek_terlewat).
     */
    private function pesanPendaftaranSudahDitutup($petugas_pemeriksa): string
    {
        $nama_dokter = $petugas_pemeriksa->staf->nama_dengan_gelar;
        return "Pendaftaran Dokter {$nama_dokter} hari ini sudah ditutup.";
    }

    /**
     * Pesan info saat user memilih dokter dgn mode reservasi terjadwal.
     * Mirror tone WablasController balasanReservasiTerjadwalDibuat —
     * memberi tahu pasien bahwa ini bukan antrian biasa.
     */
    private function pesanReservasiTerjadwalDipilih($petugas_pemeriksa): string
    {
        $nama_dokter = $petugas_pemeriksa->staf->nama_dengan_gelar;
        $mulai       = substr((string) $petugas_pemeriksa->jam_mulai, 0, 5);
        $akhir       = substr((string) $petugas_pemeriksa->jam_akhir, 0, 5);

        return
            "Anda memilih *Reservasi Terjadwal* ke {$nama_dokter}.\n" .
            "• Jam pelayanan: {$mulai}–{$akhir}\n" .
            "• Lengkapi data berikut sampai selesai untuk mendapatkan QR Code.\n" .
            "• Datang sesuai jam pelayanan, lalu *scan QR Code* di klinik.";
    }

    /**
     * Pesan kuota penuh + tawarkan waitlist (mirror WablasController
     * pesanKuotaPenuhPerPetugasDenganWaitlist).
     */
    private function pesanKuotaPenuhDenganWaitlist($petugas_pemeriksa): string
    {
        $nama_dokter = $petugas_pemeriksa->staf->nama_dengan_gelar;
        $mulai       = substr((string) $petugas_pemeriksa->jam_mulai, 0, 5);
        $akhir       = substr((string) $petugas_pemeriksa->jam_akhir, 0, 5);

        return
            "Maaf, kuota *Reservasi Terjadwal* untuk {$nama_dokter} sudah *penuh*.\n" .
            "• Jam pelayanan: {$mulai}–{$akhir}\n" .
            "Apakah Anda ingin masuk *waitlist*? Anda akan otomatis diproses bila ada slot batal.";
    }

    /**
     * Pesan setelah user setuju masuk waitlist (mirror
     * WablasController pesanWaitlistTercatat — tapi sebelum tercatat di
     * tabel schedulled_reservations; itu terjadi di lanjutkan()).
     */
    private function pesanWaitlistDisetujui(): string
    {
        return
            "Siap, Anda kami catat sebagai kandidat *waitlist*.\n" .
            "Lanjutkan mengisi data sampai selesai untuk mengonfirmasi.\n" .
            "Bila ada slot batal, Anda akan diproses secara berurutan.";
    }

    /**
     * Pesan sukses reservasi terjadwal dibuat (mirror WablasController
     * balasanReservasiTerjadwalDibuat).
     */
    private function pesanReservasiTerjadwalTercatat($schedulled_reservation): string
    {
        $nama_dokter = $schedulled_reservation->staf
            ? $schedulled_reservation->staf->nama_dengan_gelar
            : '';

        $msg  = "Reservasi Terjadwal sudah tercatat";
        if ($nama_dokter) {
            $msg .= " ke {$nama_dokter}";
        }
        $msg .= ".\n";
        $msg .= "• *Scan QR Code* (di bawah) saat tiba di klinik.\n";
        $msg .= "• Datang sesuai jam pelayanan; mohon hadir sebelum panggilan.\n";
        $msg .= "• Untuk membatalkan, gunakan tombol *Hapus Reservasi*.";
        return $msg;
    }

    /**
     * Pesan sukses waitlist tercatat (mirror WablasController
     * pesanWaitlistTercatat — dipakai setelah schedulled_reservation
     * dibuat dgn waitlist_flag=1).
     */
    private function pesanWaitlistTercatat($schedulled_reservation): string
    {
        return
            "Anda sudah masuk *waitlist*. Kode waitlist: {$schedulled_reservation->id}.\n" .
            "Bila ada slot batal, Anda akan kami hubungi *secara berurutan*.\n" .
            "Mohon simpan QR Code; akan aktif begitu slot tersedia.";
    }
    /**
     * undocumented function
     *
     * @return void
     */
}
