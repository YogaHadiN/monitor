<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AntrianPeriksa;
use App\Http\Controllers\QrCodeController;
use App\Models\AntrianPoli;
use App\Models\AntrianApotek;
use App\Models\Tenant;
use App\Models\AntrianKasir;
use App\Models\Ruangan;
use App\Models\Antrian;
use App\Models\WhatsappRegistration;
use App\Models\Periksa;
use App\Models\User;
use Input;
use Log;

class AntrianController extends Controller
{

	public $harus_di_klinik = '```Mohon kehadiran Anda di Klinik saat memanfaatkan fasilitas ini```';
	public $gigi_buka = true;
	public $estetika_buka = true;

    public function __construct(){
        session()->put('tenant_id', 1);
    }
  public function index(){
    $url_register = base64_encode(  'fingerprint/register?user_id=1' );
    $date_now     = date('Y-m-d H:i:s');
    $libur        = strtotime ($date_now) < strtotime( '2025-04-05 00:00:00'  );
    $wa           = new WablasController;
    $text_libur   = $wa->libur();
    return view('antrians.index', [
        'url_register' => $url_register,
        'text_libur' => $text_libur,
        'libur' => $libur
    ]);
  }

	public function antri($id){
		$ap                 = AntrianPoli::find($id);
		$totalAntrian       = $this->totalAntrian($ap->tanggal);
		$antrian_pasien_ini = array_search($ap->antrian, $totalAntrian['antrians']) + 1;
		$antrian_saat_ini   = $totalAntrian['antrian_saat_ini'];

		return view('welcome', compact(
			'antrian_pasien_ini',
			'antrian_saat_ini'
		));
	}
	public function fail(){
		return view('unavailable');
	}

	public function totalAntrian($tanggal){
		$antrians = [];
		$apx_per_tanggal = AntrianPeriksa::where('tanggal',  $tanggal)
										->whereIn('poli', ['umum', 'sks', 'luka'])
										->get();
		$apl_per_tanggal = AntrianPoli::where('tanggal',  $tanggal)
										->whereIn('poli', ['umum', 'sks', 'luka'])
										->get();
		$px_per_tanggal = Periksa::where('tanggal',  $tanggal)
										->whereIn('poli', ['umum', 'sks', 'luka'])
										->orderBy('antrian', 'desc')
										->get();

		foreach ($apx_per_tanggal as $apx) {
			$antrians[$apx->pasien_id] = $apx->antrian;
		}
		foreach ($apl_per_tanggal as $apx) {
			$antrians[$apx->pasien_id] = $apx->antrian;
		}
		foreach ($px_per_tanggal as $apx) {
			$antrians[$apx->pasien_id] = $apx->antrian;
		}
		sort($antrians);
		$antrian_saat_ini   = 1;
		if ( $px_per_tanggal->count() ) {
			$antrian_saat_ini   = array_search($px_per_tanggal->first()->antrian, $antrians);
		}

		$result = compact(
			'antrians',
			'antrian_saat_ini'
		);

		return $result;
	}
	public function monitor(){
		$antrian = 'A1';

		return view('monitor', compact(
			'antrian'
		));
	}

    public function monitor_baru(){
		$url = 'https://api.whatsapp.com/send?phone=6282113781271&text=komplain';
		$url_daftar_online = 'https://api.whatsapp.com/send?phone=6282113781271&text=daftar';
        $qr = new QrCodeController;
        $base64 = $qr->inPdf($url);
        $base64_daftar_online = $qr->inPdf($url_daftar_online);
        $menangani_gawat_darurat = Tenant::find(1)->menangani_gawat_darurat;

		return view('monitor_baru', compact(
            'menangani_gawat_darurat',
            'base64',
            'base64_daftar_online'
		));
    }

	public function convertSoundToArray($antrian_id){
        $antrian = Antrian::find( $antrian_id );
		$nomor_antrian = $antrian->nomor_antrian;
		$ruangan       = $antrian->antriable->ruangan;
        Log::info('=======================================');
        Log::info('antrian');
        Log::info($antrian);
        Log::info('antrian->antriable');
        Log::info($antrian->antriable);
        Log::info('antrian->antriable->ruangan');
        Log::info($antrian->antriable->ruangan);
        Log::info('=======================================');
		$huruf         = strtolower(str_split($nomor_antrian)[0]);
		$angka         = substr($nomor_antrian, 1);

		$result = [];
		$result[] =	'nomorantrian';
		if ( (int)$angka < 12 || $angka == '100' ) {
			$result[] = $huruf;
			$result[] = (int) $angka;
		} else if ( (int) $angka % 100 == 0 ) {
			$angka    = str_split($angka)[0];
			$result[] = (int) $angka;
			$result[] = 'ratus';
		} else if ( (int)$angka < 20  ) {
			$angka    = substr($angka, 1);
			$result[] = $huruf;
			$result[] = (int) $angka;
			$result[] = 'belas';
		} else if ( (int)$angka < 100  ) {
			$angka = str_split($angka, 1);
			if ($angka[1] != '0') {
				$result[] = $huruf;
				$result[] = (int) $angka[0];
				$result[] = 'puluh';
				$result[] = (int) $angka[1];
			} else {
				$result[] =	$huruf;
				$result[] =	(int) $angka[0];
				$result[] =	'puluh';
			}
		} else if ( (int)$angka < 112  ) {
			$angka    = substr($angka, 1);
			$result[] = $huruf;
			$result[] = '100';
			$result[] = (int) $angka;
		} else if ( (int)$angka < 120  ) {
			$angka = substr($angka, 1);
			$angka = str_split($angka);
			$result[] =$huruf;
			$result[] ='100';
			$result[] =(int) $angka[1];
			$result[] ='belas';
		} else if ( (int)$angka < 200  ) {
			$angka = substr($angka, 1);
			$angka = str_split($angka);
			if($angka[1] != '0'){
				$result[] =	$huruf;
				$result[] =	'100';
				$result[] =	(int) $angka[0];
				$result[] ='puluh';
				$result[] =	(int) $angka[1];
			} else {
				$result[] =	$huruf;
				$result[] =	'100';
				$result[] =	(int) $angka[0];
				$result[] =	'puluh';
			}
		} else if( (int)$angka < 999  ) {
			$angka = str_split($angka);
			if(
				$angka[1] == '0' ||
				$angka[1] == '1'  &&  (int)$angka[2] < 2
			){
				$result[] =	$huruf;
				$result[] =	(int) $angka[0];
				$result[] =	'ratus';
				$result[] =	(int) ($angka[1] . $angka[2]);
			} else if(
				(int)$angka[1] > 0 &&
				$angka[2] == '0'
			) {
				$result[] =	$huruf;
				$result[] =	(int) $angka[0];
				$result[] =	'ratus';
				$result[] =	(int) $angka[1];
				$result[] =	'puluh';
			} else if(
			   	$angka[1] == '1'
			) {
				$result[] =	$huruf;
				$result[] =	(int) $angka[0];
				$result[] =	'ratus';
				$result[] =	(int) $angka[2];
				$result[] =	'belas';
			} else {
				$result[] =	$huruf;
				$result[] =	(int) $angka[0];
				$result[] =	'ratus';
				$result[] =	(int) $angka[1];
				$result[] =	'puluh';
				$result[] =	(int) $angka[2];
			}
		}
		$result[] =	'silahkanmenuju';
		$result[] = $ruangan->file_panggilan;
		return $result;

	}

	public function updateJumlahAntrianBaru( $antrian_id, $panggil_pasien = true ){
        $antrian_dipanggil       = Antrian::with('antriable')
                                    ->where('id', $antrian_id)
                                    ->first();
        Log::info('============================');
        Log::info('AntrianController');
        Log::info('antrian_id');
        Log::info( $antrian_id );
        Log::info('============================');

        $ruangans = Ruangan::with('antrian')
                            ->where('ruang_periksa', 1)
                            ->get();
        $antrian_terakhir = [];
        foreach ($ruangans as $ruang) {
            $antrian_terakhir[$ruang->id] = is_null( $ruang->antrian )? '-' :$ruang->antrian->nomor_antrian;
        }
        if (
             !is_null( $antrian_dipanggil ) &&
             !is_null( $antrian_dipanggil->antriable ) &&
             !is_null( $antrian_dipanggil->antriable->ruangan )
        ) {
            $ruangan = $antrian_dipanggil->antriable->ruangan;
            $today = date('Y-m-d');
            if (!is_null( $antrian_dipanggil )) {
                Ruangan::where('antrian_id', $antrian_dipanggil->id)->update([
                    'antrian_id' => null
                ]);
                $ruangan->antrian_id = $antrian_dipanggil->id;
                $ruangan->save();

                $antrian_terakhir[$ruangan->id] = is_null( $ruangan->antrian )? '-' :$ruangan->antrian->nomor_antrian;
            }
        }

        $ruangan = isset( $antrian_dipanggil )? $antrian_dipanggil->ruangan?->nama : null;

        $antrian_dipanggil = [
            'nomor_antrian' => isset( $antrian_dipanggil )? $antrian_dipanggil->nomor_antrian : null,
            'ruangan' => $ruangan,
        ];

        $antrian_apoteks = AntrianApotek::with('periksa.terapii')
                                        ->where('tenant_id',1)
                                        ->get();
        $antrian_kasirs = AntrianKasir::with('periksa.terapii')
                                        ->where('tenant_id',1)
                                        ->get();
        $antrian_obat_jadi = [];
        $antrian_obat_racikan = [];
        foreach ($antrian_apoteks as $a) {
            if ($a->periksa->terapii->count()) {
                if ($a->obat_racikan) {
                    $antrian_obat_racikan[] = [
                        'nomor_antrian' => !is_null( $a->antrian ) ? $a->antrian->nomor_antrian : '-',
                        'nama'          => ucwords( strtolower( $a->periksa->pasien->nama ) ),
                        'antrian_id'    => $a->id ,
                        'periksa_id'    => $a->periksa_id ,
                        'status'        => !is_null( $a->periksa->jam_obat_mulai_diracik ) ?  'Proses' :  'Menunggu'
                    ];
                } else {
                    $antrian_obat_jadi[] = [
                        'nomor_antrian' => !is_null( $a->antrian ) ? $a->antrian->nomor_antrian : "-",
                        'nama'          => ucwords( strtolower( $a->periksa->pasien->nama ) ),
                        'antrian_id'    => $a->id ,
                        'periksa_id'    => $a->periksa_id ,
                        'status'        => !is_null( $a->periksa->jam_obat_mulai_diracik ) ?  'Proses' :  'Menunggu'
                    ];
                }
            }
        }

        foreach ($antrian_kasirs as $a) {
            if ($a->periksa->terapii->count()) {
                if (is_null( $a->periksa->jam_penyerahan_obat )) {
                    if ($a->obat_racikan) {
                        $antrian_obat_racikan[] = [
                            'nomor_antrian' => !is_null( $a->antrian ) ? $a->antrian->nomor_antrian : '-',
                            'nama'          => ucfirst( strtolower( $a->periksa->pasien->nama ) ),
                            'antrian_id'    => $a->id ,
                            'periksa_id'    => $a->periksa_id ,
                            'status'        => 'Selesai'
                        ];
                    } else {
                        $antrian_obat_jadi[] = [
                            'nomor_antrian' => !is_null( $a->antrian ) ? $a->antrian->nomor_antrian : '-',
                            'nama'          => ucfirst( strtolower( $a->periksa->pasien->nama ) ),
                            'periksa_id'    => $a->periksa_id ,
                            'antrian_id'    => $a->id ,
                            'status'        => 'Selesai'
                        ];
                    }
                }
            }
        }

        usort($antrian_obat_jadi, function($a, $b) {
            return $a['periksa_id'] <=> $b['periksa_id'];
        });
        usort($antrian_obat_racikan, function($a, $b) {
            return $a['periksa_id'] <=> $b['periksa_id'];
        });

        $result = [
            "antrian_dipanggil"       => $antrian_dipanggil,
            "antrian_terakhir"        => $antrian_terakhir,
            "antrian_obat_jadi"       => $antrian_obat_jadi ,
            "antrian_obat_racikan"    => $antrian_obat_racikan,
            "menangani_gawat_darurat" => Tenant::find(1)->menangani_gawat_darurat
        ];

        return $result;
    }
    public function getQr($id){
        $antrian = Antrian::find( $id );
        // jika qr tidak ada di dalam row, maka throw error tidak ditemukan
        // jika qr tidak hari ini, maka throw error tidak ditemukan
        // jika qr sudah diproses maka throw error tidak ditemukan
        if (
            is_null( $antrian ) ||
            (
                !is_null( $antrian ) &&
                (
                    is_null( $antrian->qr_code_path_s3 ) ||
                    date('Ymd') !== date('Ymd', strtotime($antrian->created_at)) ||
                    $antrian->antriable_type !== 'App\Models\Antrian'
                )
            )
        ) {
            return 'Data tidak ditemukan silahkan hubungi petugas';
        }
        $urlFile =  \Storage::disk('s3')->url($antrian->qr_code_path_s3) ;
        return "<img src='$urlFile' alt=''/>";
    }

    public function convertSoundToArrayMobile(){
		$nomor_antrian = Input::get('nomor_antrian');
		$no_telp = Input::get('no_telp');
		$ruangan       = Input::get('ruangan');
		$huruf         = strtolower(str_split($nomor_antrian)[0]);
		$angka         = substr($nomor_antrian, 1);

		$result = [];
		$result[] =	'nomorantrian';
		if ( (int)$angka < 12 || $angka == '100' ) {
			$result[] = $huruf;
			$result[] = (int) $angka;
		} else if ( (int) $angka % 100 == 0 ) {
			$angka    = str_split($angka)[0];
			$result[] = (int) $angka;
			$result[] = 'ratus';
		} else if ( (int)$angka < 20  ) {
			$angka    = substr($angka, 1);
			$result[] = $huruf;
			$result[] = (int) $angka;
			$result[] = 'belas';
		} else if ( (int)$angka < 100  ) {
			$angka = str_split($angka, 1);
			if ($angka[1] != '0') {
				$result[] = $huruf;
				$result[] = (int) $angka[0];
				$result[] = 'puluh';
				$result[] = (int) $angka[1];
			} else {
				$result[] =	$huruf;
				$result[] =	(int) $angka[0];
				$result[] =	'puluh';
			}
		} else if ( (int)$angka < 112  ) {
			$angka    = substr($angka, 1);
			$result[] = $huruf;
			$result[] = '100';
			$result[] = (int) $angka;
		} else if ( (int)$angka < 120  ) {
			$angka = substr($angka, 1);
			$angka = str_split($angka);
			$result[] =$huruf;
			$result[] ='100';
			$result[] =(int) $angka[1];
			$result[] ='belas';
		} else if ( (int)$angka < 200  ) {
			$angka = substr($angka, 1);
			$angka = str_split($angka);
			if($angka[1] != '0'){
				$result[] =	$huruf;
				$result[] =	'100';
				$result[] =	(int) $angka[0];
				$result[] ='puluh';
				$result[] =	(int) $angka[1];
			} else {
				$result[] =	$huruf;
				$result[] =	'100';
				$result[] =	(int) $angka[0];
				$result[] =	'puluh';
			}
		} else if( (int)$angka < 999  ) {
			$angka = str_split($angka);
			if(
				$angka[1] == '0' ||
				$angka[1] == '1'  &&  (int)$angka[2] < 2
			){
				$result[] =	$huruf;
				$result[] =	(int) $angka[0];
				$result[] =	'ratus';
				$result[] =	(int) ($angka[1] . $angka[2]);
			} else if(
				(int)$angka[1] > 0 &&
				$angka[2] == '0'
			) {
				$result[] =	$huruf;
				$result[] =	(int) $angka[0];
				$result[] =	'ratus';
				$result[] =	(int) $angka[1];
				$result[] =	'puluh';
			} else if(
			   	$angka[1] == '1'
			) {
				$result[] =	$huruf;
				$result[] =	(int) $angka[0];
				$result[] =	'ratus';
				$result[] =	(int) $angka[2];
				$result[] =	'belas';
			} else {
				$result[] =	$huruf;
				$result[] =	(int) $angka[0];
				$result[] =	'ratus';
				$result[] =	(int) $angka[1];
				$result[] =	'puluh';
				$result[] =	(int) $angka[2];
			}
		}

        $ruangans = [];
        foreach (Ruangan::all() as $rgn) {
            $ruangans[] = [
                'ruangan_id' => $rgn->id,
                'nomor_antrian' => $rgn->antrian?->nomor_antrian
            ];
        }
        $antrians = Antrian::where('no_telp', $no_telp)
                    ->whereRaw(
                        "(
                            antriable_type = 'App\\\Models\\\Antrian' or
                            antriable_type = 'App\\\Models\\\AntrianPeriksa' or
                            antriable_type = 'App\\\Models\\\AntrianPoli'
                        )"
                    )
                    ->whereDate('created_at', date('Y-m-d'))
                    ->get();

        $antrian_view = view('web_registrations.nomor_antrian_container', compact(
            'antrians'
        ))->render();

		$result[] =	'silahkanmenuju';
		$result[] = $ruangan['file_panggilan'];
        $panggilan = $result;
        return compact(
            'antrians',
            'panggilan',
            'antrian_view'
        );
    }
    public function phpinfo(){
        return phpinfo();
    }

}
