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

		$jenis_antrians = JenisAntrian::all();
		$reversed_antrians                                    = $antrians->reverse();

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

		$data['data'][1]['nomor_antrian_terakhir'] = '-';
		$data['data'][2]['nomor_antrian_terakhir'] = '-';
		$data['data'][3]['nomor_antrian_terakhir'] = '-';
		$data['data'][4]['nomor_antrian_terakhir'] = '-';
		$data['data'][7]['nomor_antrian_terakhir'] = '-';

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

		$data['data']['timbang_tensi']['nomor_antrian_terakhir'] = '-';
		$data['data']['pendaftaran']['nomor_antrian_terakhir'] = '-';
		foreach ($antrians as $antrian) {
			if (
				$antrian->antriable_type == 'App\Models\Antrian'
			) {
				if (
					isset($antrian->jenis_antrian->antrian_terakhir)
				) {
					$data['data']['pendaftaran']['nomor_antrian_terakhir'] = $antrian->jenis_antrian->antrian_terakhir->nomor_antrian;
				}
			} else if (
				$antrian->antriable_type == 'App\Models\AntrianPoli'
			){
				if (
					isset($antrian->jenis_antrian->antrian_terakhir)
				) {
					$data['data']['timbang_tensi']['nomor_antrian_terakhir'] = $antrian->jenis_antrian->antrian_terakhir->nomor_antrian;
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

		$data['antrian_by_type']['pendaftaran']   = [];
		$data['antrian_by_type']['timbang_tensi']   = [];
		$data['antrian_by_type']['antrian_periksa'] = [];

		$antrian_dipanggils = [];

		foreach ($data['data'] as $x) {
			$antrian_dipanggils[] = $x['nomor_antrian_terakhir'];
		}


		foreach ($antrians as $ant) {
			if (!in_array( $ant->nomor_antrian, $antrian_dipanggils)) {
				if (
					$ant->antriable_type == 'App\Models\AntrianPeriksa'
				) {
					$data['antrian_by_type']['antrian_periksa'][$ant->jenis_antrian_id][] = [
						'nomor_antrian'  => $ant->nomor_antrian,
						'antriable_type' => $ant->antriable_type
					];
				} else if (
					$ant->antriable_type == 'App\Models\Antrian'
				) {
					$data['antrian_by_type']['pendaftaran'][] = [
						'nomor_antrian'   => $ant->nomor_antrian,
						'antriable_type'  => $ant->antriable_type,
						'selesai_periksa' => $ant->created_at
					];
				} else if (
					$ant->antriable_type == 'App\Models\AntrianPoli'
				) {
					$data['antrian_by_type']['timbang_tensi'][] = [
						'nomor_antrian'   => $ant->nomor_antrian,
						'antriable_type'  => $ant->antriable_type,
						'selesai_periksa' => $ant->antriable->created_at
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
		$columns = array_column($data['antrian_by_type']['pendaftaran'], 'selesai_periksa');
		array_multisort($columns, SORT_ASC, $data['antrian_by_type']['pendaftaran']);

		$columns = array_column($data['antrian_by_type']['timbang_tensi'], 'selesai_periksa');
		array_multisort($columns, SORT_ASC, $data['antrian_by_type']['timbang_tensi']);

		return $data;
	}
}
