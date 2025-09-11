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

        Log::info(__LINE__);
        Log::info("====================");
        Log::info("ANTRIAN");
        Log::info($antrians);
        Log::info("====================");
        Log::info("WEB_REGIS");
        Log::info($web_registration);
        Log::info("====================");
        if (
            count( $antrians ) &&
            is_null( $web_registration )
        ) {
            Log::info(__LINE__);
            return view('web_registrations.nomor_antrian', compact(
                'antrians'
            ));
        } else if (
            is_null( $web_registration ) ||
            (
                !is_null( $web_registration ) &&
                !is_null( $web_registration->no_telp ) &&
                is_null( $web_registration->tipe_konsultasi_id )
            )
        ) {
            Log::info(__LINE__);
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

            Log::info(__LINE__);
            return view('web_registrations.registrasi_pembayaran');
        } else if (
            !is_null( $web_registration ) &&
            !is_null( $web_registration->tipe_konsultasi_id ) &&
            !is_null( $web_registration->registrasi_pembayaran_id ) &&
            is_null( $web_registration->register_previously_saved_patient )
        ) {

            Log::info(__LINE__);
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

            Log::info(__LINE__);
            return view('web_registrations.nomor_asuransi_bpjs');
        } else if (
            !is_null( $web_registration ) &&
            !is_null( $web_registration->tipe_konsultasi_id ) &&
            !is_null( $web_registration->registrasi_pembayaran_id ) &&
            !is_null( $web_registration->register_previously_saved_patient ) &&
            !is_null( $web_registration->nomor_asuransi_bpjs ) &&
            is_null( $web_registration->nama )
        ) {

            Log::info(__LINE__);
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

            Log::info(__LINE__);
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

            Log::info(__LINE__);
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

            Log::info(__LINE__);
            $web_registration = WebRegistration::where('no_telp', $no_telp)
                                            ->whereDate('created_at', date('Y-m-d'))
                                            ->first();
            $tipe_konsultasi_id = $web_registration->tipe_konsultasi_id;
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
            Log::info(__LINE__);
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
        $cek                = $this->cek( $tipe_konsultasi_id );
        $this->message            = $cek['message'];
        $web_registration   = null;
        if (empty( $this->message )) {
            $web_registration = WebRegistration::whereDate('created_at', date('Y-m-d'))
                                                ->where('no_telp', $no_telp)
                                                ->first();
            if (is_null( $web_registration )) {
                $web_registration = WebRegistration::create([
                    'no_telp'            => $no_telp,
                    'tipe_konsultasi_id' => $tipe_konsultasi_id,
                ]);
            } else {
                $web_registration->tipe_konsultasi_id = $tipe_konsultasi_id;
                $web_registration->save();
            }
        }

        if ( $tipe_konsultasi_id == '2' ) { // dokter gigi
            $wb             = new WablasController;
            $wb->tenant     = $this->tenant;
            $this->message_wablas = $wb->validasiDokterPengambilanAntrianDokterGigi();
            if (!is_null( $this->message_wablas )) {
                $this->message    = $this->message_wablas;
            }
        }
        $message =  view('web_registrations.message', [ 'message' => $this->message ])->render();
        return compact(
            'message'
        );
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
    public function staf(){
        $petugas_pemeriksa_id    = Input::get('value');
        $no_telp = Input::get('no_telp');
        $web_registration = WebRegistration::where('no_telp', $no_telp)
                                            ->whereDate('created_at', date('Y-m-d'))
                                            ->first();

        $petugas_pemeriksa = PetugasPemeriksa::find( $petugas_pemeriksa_id );

        $web_registration->staf_id = $petugas_pemeriksa->staf_id;
        $web_registration->ruangan_id = $petugas_pemeriksa->ruangan_id;
        $web_registration->save();

        $this->message = null;
        $message =  view('web_registrations.message', [ 'message' => $this->message ])->render();
        return compact(
            'message',
        );
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

        Log::info("============================================");
        Log::info( $this->message );
        Log::info("============================================");
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
        $web_registration->data_terkonfirmasi = 1;
        $web_registration->save();

        $wablas = new WablasController;
        $antrian                = $wablas->antrianPost( $web_registration->ruangan_id );

        Log::info('=====================');
        Log::info(640);
        Log::info($web_registration);
        Log::info('=====================');

        $antrian->nama                     = $web_registration->nama;
        $antrian->nomor_bpjs               = $web_registration->nomor_asuransi_bpjs;
        $antrian->no_telp                  = $web_registration->no_telp;
        $antrian->tanggal_lahir            = $web_registration->tanggal_lahir;
        $antrian->alamat                   = $web_registration->alamat;
        $antrian->registrasi_pembayaran_id = $web_registration->registrasi_pembayaran_id;
        $antrian->pasien_id                = $web_registration->pasien_id;
        $antrian->ruangan_id               = $web_registration->ruangan_id;
        $antrian->tipe_konsultasi_id       = $web_registration->tipe_konsultasi_id;
        $antrian->staf_id                  = $web_registration->staf_id;
        $antrian->reservasi_online         = 1;
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
        $antrian = Antrian::where('no_telp',  $no_telp )
                            ->whereDate('created_at', date('Y-m-d'))
                            ->whereRaw(
                                "(
                                    antriable_type = 'App\\\Models\\\Antrian' or
                                    antriable_type = 'App\\\Models\\\AntrianPeriksa' or
                                    antriable_type = 'App\\\Models\\\AntrianPoli'
                                )"
                            )
                            ->delete();
    }

    public function daftar_lagi(){
        $no_telp = Input::get('no_telp');
        WebRegistration::create([
            'no_telp' => $no_telp,
            'tipe_konsultasi_id' => null
        ]);
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
     * undocumented function
     *
     * @return void
     */
}
