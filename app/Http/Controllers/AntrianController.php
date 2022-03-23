<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AntrianPeriksa;
use App\Models\AntrianPoli;
use App\Models\Antrian;
use App\Models\JenisAntrian;
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

	  public function __construct()
    {
        $this->middleware('avail')->only('antri');
    }
	  public function index(){
		  dd('',  Antrian::orderBy('updated_at', 'desc')->first() );
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
	public function convertSoundToArray(){
		$nomor_antrian = Input::get('nomor_antrian');
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
		$result[] =	'silahkanmenuju';
		$result[] = $ruangan;
		return $result;
	
	}
	public function updateJumlahAntrian( $panggil_pasien = true ){
		$today = date('Y-m-d');
		$antrians       = Antrian::with(
									'jenis_antrian.poli_antrian', 
									'antriable', 
									'jenis_antrian.antrian_terakhir'
								)
								->whereRaw('created_at between "' . $today . ' 00:00:00" and "' .$today. ' 23:59:59"')
								->where('antriable_type', 'not like', 'App\Models\Periksa')
								->orderBy('id')
								->get();
		$jenis_antrian  = JenisAntrian::with( 'antrian_terakhir.jenis_antrian', 'antrian_terakhir.antriable')->orderBy('updated_at', 'desc')->get();
		$antriable_type = $jenis_antrian->first()->antrian_terakhir->antriable_type;
		$jenis_antrians = JenisAntrian::all();
		foreach ($jenis_antrians as $jt) {
			$data['antrian_terakhir_per_poli'][$jt->id] = '-';
		}
		$exclude_from_type                                    = [];
		$reversed_antrians                                    = $antrians->reverse();
		$data['antrian_terakhir_per_poli']['antrian_kasir']   = '-';
		$data['antrian_terakhir_per_poli']['antrian_farmasi'] = '-';

		foreach ($antrians as $ant) {
			if (
				$ant->antriable_type !== 'App\Models\AntrianApotek' &&
				$ant->antriable_type !== 'App\Models\Antrian' &&
				$ant->antriable_type !== 'App\Models\AntrianKasir' &&
				$ant->antriable_type !== 'App\Models\AntrianFarmasi' &&
				$ant->antriable->dipanggil > 0
			) {
				$data['antrian_terakhir_per_poli'][$ant->jenis_antrian_id] = $ant->nomor_antrian;
			}

			if (
				( 
					$ant->antriable_type == 'App\Models\AntrianApotek' || 
					$ant->antriable_type == 'App\Models\AntrianKasir' 
				) &&
				$ant->antriable->dipanggil > 0
			) {
				$data['antrian_terakhir_per_poli']['antrian_kasir'] =  $ant->nomor_antrian;
			}

			if (
				( $ant->antriable_type == 'App\Models\AntrianFarmasi' ) &&
				$ant->antriable->dipanggil > 0
			) {
				/* dd( $ant ); */
				$data['antrian_terakhir_per_poli']['antrian_farmasi'] =  $ant->nomor_antrian;
			}
		}
		/* dd('data'); */
		/* dd($data['antrian_terakhir_per_poli']); */
		if ( $panggil_pasien ) {
			$antrian_dipanggil = $antrians->sortByDesc('updated_at')->first();
			$data['panggilan']['nomor_antrian'] = $antrian_dipanggil->nomor_antrian;
			if (
				$antrian_dipanggil->antriable_type == 'App\Models\Antrian'
			) {
				$data['panggilan']['poli'] = 'Pendaftaran';
			} else if (
				$antrian_dipanggil->antriable_type == 'App\Models\AntrianApotek' || 
				$antrian_dipanggil->antriable_type == 'App\Models\AntrianKasir'
			) {
				$data['panggilan']['poli']                          = 'Antrian Kasir';
			} else if (
				$antrian_dipanggil->antriable_type == 'App\Models\AntrianFarmasi'
			){
				$data['panggilan']['poli'] = 'Antrian Farmasi';
			} else {
				$data['panggilan']['poli'] = ucwords($antrian_dipanggil->jenis_antrian->jenis_antrian);
			}
		}

		foreach ($antrians as $antrian) {
			if (
				$antrian->antriable_type !== 'App\Models\Antrian' &&
				$antrian->antriable_type !== 'App\Models\AntrianApotek' &&
				$antrian->antriable_type !== 'App\Models\AntrianKasir' &&
				$antrian->antriable_type !== 'App\Models\AntrianFarmasi'
			) {
				if (
					isset($antrian->jenis_antrian->antrian_terakhir)
				) {
					$data['data'][ $antrian->jenis_antrian_id ]['nomor_antrian_terakhir'] = $antrian->jenis_antrian->antrian_terakhir->nomor_antrian;
				} else {
					$data['data'][ $antrian->jenis_antrian_id ]['nomor_antrian_terakhir'] = '-';
				}
			}
		}

		foreach ($antrians as $antrian) {
			if (
				$antrian->antriable_type == 'App\Models\Antrian'
			) {
				if (
					isset($antrian->jenis_antrian->antrian_terakhir)
				) {
					$data['data']['antrian_pendaftaran']['nomor_antrian_terakhir'] = $antrian->jenis_antrian->antrian_terakhir->nomor_antrian;
				} else {
					$data['data']['antrian_pendaftaran']['nomor_antrian_terakhir'] = '-';
				}
			} else if (
				$antrian->antriable_type == 'App\Models\AntrianApotek' &&
				$antrian->antriable_type == 'App\Models\AntrianKasir'
			){
				if (
					isset($antrian->jenis_antrian->antrian_terakhir)
				) {
					$data['data']['antrian_kasir']['nomor_antrian_terakhir'] = $antrian->jenis_antrian->antrian_terakhir->nomor_antrian;
				} else {
					$data['data']['antrian_kasir']['nomor_antrian_terakhir'] = '-';
				}
			} else if (
				$antrian->antriable_type == 'App\Models\AntrianFarmasi'
			){
				if (
					isset($antrian->jenis_antrian->antrian_terakhir)
				) {
					$data['data']['antrian_farmasi']['nomor_antrian_terakhir'] = $antrian->jenis_antrian->antrian_terakhir->nomor_antrian;
				} else {
					$data['data']['antrian_farmasi']['nomor_antrian_terakhir'] = '-';
				}
			}
		}

		foreach ($jenis_antrian as $ja) {
			if (!isset($data['data'][$ja->id]) && $ja->id <5) {
				$data['data'][$ja->id]['nomor_antrian_terakhir'] = '-';
				$data['data'][$ja->id]['jumlah']                 = 0;
			}
		}
		$bisa                 = [];
		$tidak_bisa           = [];
		foreach ($jenis_antrians as $j) {
			if ( isset( $j->antrian_terakhir_id ) ) {
				$data['jenis_antrian_ids'][] = 
					[
						'id'             => $j->id
					];
			}
		}

		$data['antrian_by_type']['antrian_kasir']   = [];
		$data['antrian_by_type']['antrian_farmasi']   = [];
		$data['antrian_by_type']['antrian_periksa'] = [];


		/* dd( $data['antrian_terakhir_per_poli'] ); */
		$include_only = $data['antrian_terakhir_per_poli'];
		/* dd('include_only1', $include_only ); */
		unset( $include_only['antrian_pendaftaran'] );
		/* dd('include_only', $include_only ); */

		foreach ($antrians as $ant) {
			if (!in_array( $ant->nomor_antrian, $include_only)) {
				if (
					$ant->antriable_type == 'App\Models\Antrian' ||
					$ant->antriable_type == 'App\Models\AntrianPeriksa' ||
					$ant->antriable_type == 'App\Models\AntrianPoli'
				) {
					$data['antrian_by_type']['antrian_periksa'][$ant->jenis_antrian_id][] = [
						'nomor_antrian'  => $ant->nomor_antrian,
						'antriable_type' => $ant->antriable_type
					];
				} else if (
					(
						$ant->antriable_type == 'App\Models\AntrianApotek' ||
						$ant->antriable_type == 'App\Models\AntrianKasir'
					) && $ant->antriable->periksa->asuransi_id == '0'
				) {
					$data['antrian_by_type']['antrian_kasir'][] = [
						'nomor_antrian'   => $ant->nomor_antrian,
						'antriable_type'  => $ant->antriable_type,
						'selesai_periksa' => $ant->antriable->periksa->created_at
					];
				} else if (
					$ant->antriable_type == 'App\Models\AntrianFarmasi'
				) {
					$data['antrian_by_type']['antrian_farmasi'][] = [
						'nomor_antrian'   => $ant->nomor_antrian,
						'antriable_type'  => $ant->antriable_type,
						'selesai_periksa' => $ant->antriable->periksa->created_at
					];
				} else {
					if (!isset($data['antrian_by_type'][$ant->antriable_type])) {
						$data['antrian_by_type'][$ant->antriable_type] = [];
					}
					$data['antrian_by_type'][$ant->antriable_type][] = [
						'nomor_antrian'  => $ant->nomor_antrian,
						'antriable_type' => $ant->antriable_type
					];
				}
		    }
		}
		$columns = array_column($data['antrian_by_type']['antrian_kasir'], 'selesai_periksa');
		array_multisort($columns, SORT_ASC, $data['antrian_by_type']['antrian_kasir']);

		$columns = array_column($data['antrian_by_type']['antrian_farmasi'], 'selesai_periksa');
		array_multisort($columns, SORT_ASC, $data['antrian_by_type']['antrian_farmasi']);

		return $data;
	}
	public function webhook(){
		header("Content-Type: text/plain");

		/* Masuk dengan kode unik. 2 angka acak di depan dan 2 angka acak di belakang dengan id antrian di tengahnya */

		/* if ( */ 
		/* 	!is_null($antrian) */
		/* ) { */
		/* 	$whatsapp_registration = WhatsappRegistration::where('no_telp', $no_telp) */
		/* 												->whereRaw("DATE_ADD( updated_at, interval 6 hour ) > '" . date('Y-m-d H:i:s') . "'") */
		/* 												->first(); */

		/* 	if ( is_null($whatsapp_registration) ) { */
		/* 		$wa       = new WhatsappRegistration; */
		/* 		$wa->no_telp   = $no_telp ; */
		/* 		$wa->save(); */
		/* 	} */

		/* 	echo 'antrian dengan id ' . $antrian->id . ' ditemukan ' . $no_telp; */
		/* } */

		$message    = $this->clean($_POST['message']);
		if(isset($message)) {
			$no_telp               = $_POST['phone'];
			$antrian_id            = substr(substr($message, 2), 0, -2);

			$antrian               = Antrian::where('id', $antrian_id)->where('created_at', 'like', date('Y-m-d') . '%')->first();

			$whatsapp_registration = WhatsappRegistration::where('no_telp', $no_telp)
														->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
														->first();
			$response              = '';
			$input_tidak_tepat     = false;
			if ( !is_null($antrian) ) {
				if ( is_null( $whatsapp_registration ) ) {
					$whatsapp_registration            = new WhatsappRegistration;
					$whatsapp_registration->no_telp   = $no_telp;
					$whatsapp_registration->save();
				}
			} else if (  
				substr($this->clean($message), 0, 5) == 'ulang' &&
				!is_null( $whatsapp_registration ) 
			) {
				$columns = array_keys( $whatsapp_registration->getOriginal() );
				foreach ($columns as $column) {
					if (
						!(
							 $column == 'id' ||
							 $column == 'created_at' ||
							 $column == 'antrian_id' ||
							 $column == 'updated_at' ||
							 $column == 'no_telp'
						)
					) {
						$whatsapp_registration->$column = null;
					}
				}
				$whatsapp_registration->save();
			} else if ( 
					!is_null( $whatsapp_registration ) &&
					is_null( $whatsapp_registration->poli ) 
			) {
				if (
					$this->clean($message) == 'a' ||
					$this->clean($message) == 'b' ||
					$this->clean($message) == 'c' ||
					$this->clean($message) == 'd' || //estetika
					$this->clean($message) == 'e'
				) {
					if ( $this->clean($message) == 'b' ) {
						if ( $this->gigi_buka) {
							$this->input_poli($whatsapp_registration, $message);
						} else {
							echo 'Mohon Maaf Pendaftaran ke Poli Gigi saat ini tutup, Silahkan coba lagi pada hari *Senin - Jumat jam 15.00 - 20.00*';
							echo PHP_EOL;
							echo '===========================';
							echo PHP_EOL;
						}
					}
					else if ( $this->clean($message) == 'd' ) {
						if ( $this->estetika_buka) {
							$this->input_poli($whatsapp_registration, $message);
						} else {
							echo 'Mohon Maaf Pendaftaran ke Poli Estetika /Kecantikan saat ini tutup, Silahkan coba lagi pada hari *Senin - Jumat jam 11.00 - 17.00*';
							echo PHP_EOL;
							echo '===========================';
							echo PHP_EOL;
						}
					} else {
						$this->input_poli($whatsapp_registration, $message);
					}
				} else {
					$input_tidak_tepat = true;
				}
			} else if ( 
					!is_null( $whatsapp_registration ) &&
					is_null( $whatsapp_registration->pembayaran ) 
			){
				if (
					$this->clean($message) == 'a' ||
					$this->clean($message) == 'b' ||
					$this->clean($message) == 'c'
				) {
					$whatsapp_registration->pembayaran  = $this->clean($message);
					if ( $this->clean($message) != 'b' ) {
						$whatsapp_registration->nomor_bpjs = '000';
					}
					if ( $this->clean($message) != 'c' ) {
						$whatsapp_registration->nama_asuransi = $this->clean($message);
					}
					$whatsapp_registration->save();
				} else {
					$input_tidak_tepat = true;
				}
			} else if ( 
					!is_null( $whatsapp_registration ) &&
					is_null( $whatsapp_registration->nama_asuransi ) 
			){
				$whatsapp_registration->nama_asuransi  = $this->clean($message);
				$whatsapp_registration->save();
			} else if ( 
				!is_null( $whatsapp_registration ) &&
				is_null( $whatsapp_registration->nomor_bpjs ) 
			) {

				$whatsapp_registration->nomor_bpjs = $this->clean($message);
				$whatsapp_registration->save();
				$pesertaBpjs                       = $this->pesertaBpjs($this->clean($message));

				if ( isset( $pesertaBpjs['response'] )&& isset( $pesertaBpjs['response']['nama'] )  ) {
					$whatsapp_registration->nama          = ucfirst(strtolower($pesertaBpjs['response']['nama']));
					$whatsapp_registration->tanggal_lahir = Carbon::CreateFromFormat('d-m-Y',$pesertaBpjs['response']['tglLahir'])->format('Y-m-d');

					/* if (  !$pesertaBpjs['response']['aktif'] ) { */
					/* 	$whatsapp_registration->pembayaran          = null; */
					/* 	$whatsapp_registration->nomor_bpjs          = null; */
					/* 	echo "Status Kepesertaan BPJS Anda *Tidak Aktif*"; */
					/* 	echo PHP_EOL; */
					/* 	echo "Mohon gunakan pembayaran yang lainnya selain BPJS"; */
					/* 	echo PHP_EOL; */
					/* 	echo "Apabila Anda yakin ini adalah kesalahan, silahkan mendaftar secara manual"; */
					/* 	echo PHP_EOL; */
					/* 	echo "==================="; */
					/* 	echo PHP_EOL; */
					/* } else { */
					/* 	echo "Status Kepesertaan BPJS Anda *Aktif*"; */
					/* 	echo PHP_EOL; */
					/* 	echo "==================="; */
					/* 	echo PHP_EOL; */
					/* } */
					$whatsapp_registration->save();
				}

			} else if ( 
				!is_null( $whatsapp_registration ) &&
				is_null( $whatsapp_registration->nama ) 
			) {
				$whatsapp_registration->nama  = ucfirst(strtolower($this->clean($message)));;
				$whatsapp_registration->save();
				Log::info(json_encode($whatsapp_registration));
			} else if ( 
				!is_null( $whatsapp_registration ) &&
				is_null( $whatsapp_registration->tanggal_lahir ) 
			) 
			{
				if ( $this->validateDate($this->clean($message), $format = 'd-m-Y') ) {
					$whatsapp_registration->tanggal_lahir  = Carbon::CreateFromFormat('d-m-Y',$this->clean($message))->format('Y-m-d');
					$whatsapp_registration->save();
				} else {
					$input_tidak_tepat = true;
				}
			}
		/* } else if ( */ 
		/* 	!is_null( $whatsapp_registration ) && */
		/* 	is_null( $whatsapp_registration->demam ) */ 
		/* ) */ 
		/* { */
		/* 	if ( $this->clean($message) == 'ya')  { */
			/* 		$whatsapp_registration->demam  = 1; */
			/* 		$whatsapp_registration->save(); */
			/* 	} else if ( $this->clean($message) == 'tidak') { */
			/* 		$whatsapp_registration->demam  = 0; */
			/* 		$whatsapp_registration->save(); */
			/* 	} else { */
			/* 		$input_tidak_tepat = true; */
			/* 	} */
			/* } else if ( */ 
			/* 	!is_null( $whatsapp_registration ) && */
			/* 	is_null( $whatsapp_registration->batuk_pilek ) */ 
			/* ) */ 
			/* { */
			/* 	if ( $this->clean($message) == 'ya')  { */
			/* 		$whatsapp_registration->batuk_pilek  = 1; */
			/* 		$whatsapp_registration->save(); */
			/* 	} else if ( $this->clean($message) == 'tidak')  { */
			/* 		$whatsapp_registration->batuk_pilek  = 0; */
			/* 		$whatsapp_registration->save(); */
			/* 	} else { */
			/* 		$input_tidak_tepat = true; */
			/* 	} */
			/* } else if ( */ 
			/* 	!is_null( $whatsapp_registration ) && */
			/* 	is_null( $whatsapp_registration->nyeri_menelan ) */ 
			/* ) */ 
			/* { */
			/* 	if ( $this->clean($message) == 'ya')  { */
			/* 		$whatsapp_registration->nyeri_menelan  = 1; */
			/* 		$whatsapp_registration->save(); */
			/* 	} else if ( $this->clean($message) == 'tidak')  { */
			/* 		$whatsapp_registration->nyeri_menelan  = 0; */
			/* 		$whatsapp_registration->save(); */
			/* 	} else { */
			/* 		$input_tidak_tepat = true; */
			/* 	} */
			/* } else if ( */ 
			/* 	!is_null( $whatsapp_registration ) && */
			/* 	is_null( $whatsapp_registration->sesak_nafas ) */ 
			/* ) { */
			/* 	Log::info('============================ sesak nafas =========================================='); */
			/* 	if ( $this->clean($message)             == 'ya')  { */
			/* 		$whatsapp_registration->sesak_nafas  = 1; */
			/* 		$whatsapp_registration->save(); */
			/* 	} else if ( $this->clean($message)      == 'tidak')  { */
			/* 		$whatsapp_registration->sesak_nafas  = 0; */
			/* 		$whatsapp_registration->save(); */
			/* 	} else { */
			/* 		$input_tidak_tepat = true; */
			/* 	} */
			/* } else if ( */ 
			/* 	!is_null( $whatsapp_registration ) && */
			/* 	is_null( $whatsapp_registration->kontak_covid ) */ 
			/* ) { */
			/* 	if ( $this->clean($message) == 'ya')  { */
			/* 		$whatsapp_registration->kontak_covid  = 1; */
			/* 		$whatsapp_registration->save(); */
			/* 	} else if ( $this->clean($message) == 'tidak')  { */
			/* 		$whatsapp_registration->kontak_covid  = 0; */
			/* 		$whatsapp_registration->save(); */
			/* 	} else { */
			/* 		$input_tidak_tepat = true; */
			/* 	} */
			/* } */

			Log::info('whatsapp_registration');
			Log::info( json_encode($whatsapp_registration) );
			if (
				!is_null( $whatsapp_registration ) 
			) {
				if (
				 !is_null( $whatsapp_registration->nama ) ||
				 !is_null( $whatsapp_registration->poli ) ||
				 !is_null( $whatsapp_registration->pembayaran ) ||
				 !is_null( $whatsapp_registration->tanggal_lahir )
				) {
					$response .=    "*Uraian Pengisian Anda*";
					$response .= PHP_EOL;
					$response .= PHP_EOL;
				}
				if ( !is_null( $whatsapp_registration->nama ) ) {
					$response .= 'Nama Pasien: ' . ucwords($whatsapp_registration->nama)  ;
					$response .= PHP_EOL;
				}
				if ( !is_null( $whatsapp_registration->poli ) ) {
					$response .= 'Poli Tujuan : ';
					$response .= $this->formatPoli( $whatsapp_registration->poli );
					$response .= PHP_EOL;
				}
				if ( !is_null( $whatsapp_registration->pembayaran ) ) {
					$response .= 'Pembayaran : ';
					$response .= ucwords($whatsapp_registration->nama_pembayaran);
					$response .= PHP_EOL;
				}
				if ( !is_null( $whatsapp_registration->tanggal_lahir ) ) {
					$response .= 'Tanggal Lahir : '.  Carbon::CreateFromFormat('Y-m-d',$whatsapp_registration->tanggal_lahir)->format('d M Y');;
					$response .= PHP_EOL;
				}
				if (
				 !is_null( $whatsapp_registration->nama ) ||
				 !is_null( $whatsapp_registration->poli ) ||
				 !is_null( $whatsapp_registration->pembayaran ) ||
				 !is_null( $whatsapp_registration->tanggal_lahir )
				) {
					$response .= PHP_EOL;
					$response .=    "==================";
					$response .= PHP_EOL;
					$response .= PHP_EOL;
				}
			}


			if ( !is_null($whatsapp_registration) ) {
				$response .=  $this->botKirim($whatsapp_registration);
				$response .=  PHP_EOL;
				$response .=  PHP_EOL;
				$response .= "==============";
				$response .=  PHP_EOL;
				$response .=  PHP_EOL;
				$response .=  "Balas *ulang* apa bila ada kesalahan dan Anda akan mengulangi pertanyaan dari awal";
				if ( $input_tidak_tepat ) {
					$response .=  PHP_EOL;
					$response .=  PHP_EOL;
				$response .=    "==================";
					$response .= PHP_EOL;
					$response .= '```Input yang anda masukkan salah```';
					$response .= PHP_EOL;
					$response .= '```Mohon Diulangi```';
					$response .= PHP_EOL;
				}
				$input_tidak_tepat = false;
				echo $response;
			}
			/* Sms::send($no_telp, $response); */
		}
		/* Konfirmasi mengantri berapa orang lagi sebelum didaftarkan dan perkiraan jam berapa dipanggil untuk masuk ruang dokter */

		/* Pilihan untuk pembayaran */
		/* Biaya pribadi, BPJS , pembayaran lainnya */

		/* Biaya pribadi : */
		/* Nama : */
		/* Tanggal Lahir : */
		/* Alamat jika tanggal lahir tidak ditemukan */

		/* Bpjs : */
		/* Nomor BPJS: */
		/* Jika tidak ditemukan */
		/* Nama : */
		/* Tanggal Lahir : */
		/* Alamat jika tanggal lahir tidak ditemukan */

		/* Pembayaran Lainnya: */
		/* Nomor Asuransi : */
		/* Nama : */
		/* Tanggal Lahir : */
		/* Nama Pembayar : */

		/* Untuk pembayaran pribadi poli rapid */
		/* Nama : */
		/* Alamat : */
		/* Tanggal Lahir : */
		/* No KTP : */
		/* Pilihan untuk dikirim lewat WA atau email atau diambil langsung (menunggu 30 menit untuk hasil) */

		/* Mengingatkan 1 antrian sebelumnya */

		/* Pilihan untuk transfer langsung untuk pembayaran tunai (menunggu untuk antrian kasir) */

		/* Setelah pemeriksaan pasien akan diinformasikan mengenai waktu tunggu apotek dan informasi total biaya, pilihan untuk pembayaran tunai atau transfer */

		/* Survey kepuasan pelanggan pada akhir pemeriksaan */

	}
	private function clean($param)
	{
		if (empty( trim($param) )) {
			return null;
		}
		return strtolower( trim($param) );
	}

	private function botKirim($whatsapp_registration)
	{
		if ( is_null( $whatsapp_registration->poli ) ) {
			$text  = 'Terima kasih telah mendaftar sebagai pasien di' ;
			$text .= PHP_EOL;
			$text .= '*KLINIK JATI ELOK*' ;
			$text .= PHP_EOL;
			$text .= 'Dengan senang hati kami akan siap membantu Anda.';
			$text .= PHP_EOL;
			$text .= "==============";
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Bisa dibantu berobat ke dokter apa?';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Balas *A* untuk *Dokter Umum*, ';
			$text .= PHP_EOL;
			if ( $this->gigi_buka ) {
				$text .= PHP_EOL;
				$text .= 'Balas *B* untuk *Dokter Gigi*, ';
				$text .= PHP_EOL;
			}
			$text .= PHP_EOL;
			$text .= 'Balas *C* untuk *Suntik KB/Periksa Hamil*.';
			if ( $this->estetika_buka ) {
				$text .= PHP_EOL;
				$text .= PHP_EOL;
				$text .= 'Balas *D* untuk *Dokter Estetika/Kecantikan*';
			}
			/* $text .= PHP_EOL; */
			/* $text .= PHP_EOL; */
			/* $text .= 'Balas *E* untuk USG Kebidanan'; */
			return $text;

		}
		if ( is_null( $whatsapp_registration->pembayaran ) ) {
			$text = 'Bisa dibantu pembayaran menggunakan apa? ';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Balas *A* untuk *Biaya Pribadi*, '  ;
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Balas *B* untuk *BPJS*, ';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Balas *C* untuk *Asuransi/Pembayaran Lain*';
			$text .= PHP_EOL;
			return $text;
		}
		if ( is_null( $whatsapp_registration->nomor_bpjs ) ) {
			$text = 'Bisa dibantu *Nomor BPJS* pasien? ';
			return $text;
		}
		if ( is_null( $whatsapp_registration->nama_asuransi ) ) {
			return  'Bisa dibantu *Nama Asuransi Yang Digunakan* pasien?';
		}
		if ( is_null( $whatsapp_registration->nama ) ) {
			return  'Bisa dibantu *Nama Lengkap* pasien?';
		}
		if ( is_null( $whatsapp_registration->tanggal_lahir ) ) {
			return  'Bisa dibantu *Tanggal Lahir* pasien? ' . PHP_EOL . PHP_EOL . 'Contoh : *19 Juli 2003*, Balas dengan *19-07-2003*';
		}
		/* if ( is_null( $whatsapp_registration->demam ) ) { */
		/* 	return 'Apakah pasien memiliki keluhan demam?' . PHP_EOL . PHP_EOL .  'Balas *ya/tidak*'; */
		/* } */
		/* if ( is_null( $whatsapp_registration->batuk_pilek ) ) { */
		/* 	return 'Apakah pasien memiliki keluhan batuk pilek? ' . PHP_EOL .  PHP_EOL . 'Balas *ya/tidak*'; */
		/* } */
		/* if ( is_null( $whatsapp_registration->nyeri_menelan ) ) { */
		/* 	return 'Apakah pasien memiliki keluhan nyeri menelan? ' . PHP_EOL .   PHP_EOL .'Balas *ya/tidak*'; */
		/* } */
		/* if ( is_null( $whatsapp_registration->sesak_nafas ) ) { */
		/* 	return 'Apakah pasien memiliki keluhan sesak nafas? ' . PHP_EOL .   PHP_EOL .'Balas *ya/tidak*'; */
		/* } */
		/* if ( is_null( $whatsapp_registration->kontak_covid ) ) { */

		/* 	$text = 'Apakah pasien memiliki riwayat kontak dengan seseorang yang terkonfirmasi/ positif COVID 19 ?' ; */
		/* 	$text .= PHP_EOL; */
		/* 	$text .= PHP_EOL; */
		/* 	$text .= '*Kontak Berarti :*'; */
		/* 	$text .= PHP_EOL; */
		/* 	$text .= '- Tinggal serumah'; */
		/* 	$text .= PHP_EOL; */
		/* 	$text .= '- Kontak tatap muka, misalnya : bercakap-cakap selama beberapa menit'; */
		/* 	$text .= PHP_EOL; */
		/* 	$text .= '- Terkena Batuk pasien terkontaminasi'; */
		/* 	$text .= PHP_EOL; */
		/* 	$text .= '- Berada dalam radius 2 meter selama lebih dari 15 menit dengan kasus terkonfirmasi'; */
		/* 	$text .= PHP_EOL; */
		/* 	$text .= '- Kontak dengan cairan tubuh kasus terkonfirmasi'; */
		/* 	$text .= PHP_EOL; */
		/* 	$text .= PHP_EOL; */
		/* 	$text .= 'Balas *ya/tidak*'; */

		/* 	return $text; */
		/* } */


		$jenis_antrian_id = null;
		if ( $whatsapp_registration->poli == 'a'	) {
			$jenis_antrian_id = 1;
		} else if ( $whatsapp_registration->poli == 'b'	){
			$jenis_antrian_id = 2;
		} else if ( $whatsapp_registration->poli == 'c'	){
			$jenis_antrian_id = 3;
		} else if ( $whatsapp_registration->poli == 'd'	){
			$jenis_antrian_id = 4;
		} else if ( $whatsapp_registration->poli == 'e'	){
			$jenis_antrian_id = 5;
		}

		if ( is_null( Antrian::where('whatsapp_registration_id', $whatsapp_registration->id)->first() ) ) {
			$fasilitas                         = new FasilitasController;
			$antrian                           = $fasilitas->antrianPost( $jenis_antrian_id );
			$nomor_antrian                     = $antrian->nomor_antrian;
			$antrian->whatsapp_registration_id = $whatsapp_registration->id;
			$antrian->save();
			$whatsapp_registration->antrian_id = $antrian->id;
			$whatsapp_registration->save();
		}		

		$text = "Terima kasih atas kesediaan menjawab pertanyaan kami" ;
		$text .= PHP_EOL;
		$text .= "Anda telah terdaftar dengan Nomor Antrian";
		$text .= PHP_EOL;
		$text .= PHP_EOL;
		$text .= "```" . $whatsapp_registration->antrian->nomor_antrian . "```";
		$text .= PHP_EOL;
		$text .= PHP_EOL;
		if (
			is_null(Pasien::where('nomor_asuransi_bpjs',$whatsapp_registration->nomor_bpjs)->first()) &&
			$whatsapp_registration->pembayaran == 'b'
		){
			$text .= "Mohon kesediaannya untuk mengirimkan *foto kartu BPJS, foto Kartu Tanda Penduduk, dan Foto Wajah Pasien* ke nomor ini";
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= "*- Foto kartu BPJS*";
			$text .= PHP_EOL;
			$text .= "*- Foto Kartu Tanda Penduduk*";
			$text .= PHP_EOL;
			$text .= "*- Foto Wajah Pasien* ke nomor ini";
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= "ke nomor ini";
			$text .= PHP_EOL;
			$text .= PHP_EOL;
		}
		$text .= "Silahkan menunggu untuk dilayani";
		$text .= PHP_EOL;
		$text .= PHP_EOL;
		$text .= "==============";
		$text .= PHP_EOL;
		$text .= $this->harus_di_klinik;
		return $text;
	}
}
