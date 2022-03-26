<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AntrianPeriksa;
use App\Models\AntrianPoli;
use App\Models\Antrian;
use App\Models\JenisAntrian;
use App\Models\WhatsappRegistration;
use App\Models\Periksa;
use App\Models\Pasien;
use App\Models\User;
use Input;
use Carbon\Carbon;
use Log;
class WablasController extends Controller
{
	public $antrian;
	/**
	* @param 
	*/
	public function __construct()
	{
		$this->antrian = Antrian::where('id', $antrian_id)->where('created_at', 'like', date('Y-m-d') . '%')->first();
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


			$whatsapp_registration = WhatsappRegistration::where('no_telp', $no_telp)
														->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
														->first();
			$response              = '';
			$input_tidak_tepat     = false;
			if ( !is_null($this->antrian) ) {
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
				is_null( $whatsapp_registration->konfirmasi_nomor_antrian ) 
			){
				if (
					$this->clean($message) == 'a'
				) {
					$whatsapp_registration->konfirmasi_nomor_antrian  = 1;
					$whatsapp_registration->save();

					$this->antrian->whatsapp_registration_id = $whatsapp_registration->id;
					$this->antrian->save();

				} else if (
					$this->clean($message) == 'b'
				) {
					$whatsapp_registration->delete();
					$response .=    "Silahkan kirimkan nomor yang tertera di tiket antrian atau scan *QR Code* yang tertera di tiket antrian";
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
					if ($this->clean($message) == 'a') {
						$whatsapp_registration->nama_asuransi  = 'biaya_pribadi';
						$whatsapp_registration->nomor_asuransi  = '0000';
					}
					if ($this->clean($message) == 'b') {
						$whatsapp_registration->nama_asuransi  = 'BPJS';
					}
					$whatsapp_registration->pembayaran  = $this->clean($message);
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
				is_null( $whatsapp_registration->nomor_asuransi ) 
			) {
				$whatsapp_registration->nomor_asuransi = $this->clean($message);
				//jika pembayaran menggunakan BPJS
				if ($whatsapp_registration->pembayaran == 'b') { //BPJS
					//cari pasien dengan nomor_asuransi_bpjs tersebut
					$pasien = Pasien::where('nomor_asuransi_bpjs', $this->clean($message))->first();
					if (!is_null($pasien)) {
						$whatsapp_registration->nama           = $pasien->nama;
						$whatsapp_registration->nomor_asuransi = $this->clean($message);
						$whatsapp_registration->tanggal_lahir  = $pasien->tanggal_lahir;
						$whatsapp_registration->pasien_id      = $pasien->pasien_id;
					}
					//apabila menggunakan asuransi lain
				} else if ( $whatsapp_registration->pembayaran == 'c' ){ //asuransi lain
					//cari pasien dengan nomor_asuransi tersebut
					$pasiens = Pasien::where('nomor_asuransi', $this->clean($message))->get();
					if ($pasiens->count() == 1) {
						$pasien = $pasiens->first();
						$whatsapp_registration->nama           = $pasien->nama;
						$whatsapp_registration->nomor_asuransi = $this->clean($message);
						$whatsapp_registration->tanggal_lahir  = $pasien->tanggal_lahir;
						$whatsapp_registration->pasien_id      = $pasien->pasien_id;
					}
				}
				$whatsapp_registration->save();
				/* $pesertaBpjs                       = $this->pesertaBpjs($this->clean($message)); */
				/* if ( isset( $pesertaBpjs['response'] )&& isset( $pesertaBpjs['response']['nama'] )  ) { */
				/* 	$whatsapp_registration->nama          = ucfirst(strtolower($pesertaBpjs['response']['nama'])); */
				/* 	$whatsapp_registration->tanggal_lahir = Carbon::CreateFromFormat('d-m-Y',$pesertaBpjs['response']['tglLahir'])->format('Y-m-d'); */
					/* if (  !$pesertaBpjs['response']['aktif'] ) { */
					/* 	$whatsapp_registration->pembayaran          = null; */
					/* 	$whatsapp_registration->nomor_asuransi          = null; */
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
					/* $whatsapp_registration->save(); */
				/* } */
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
					} else if ( $this->clean($message) == 'd' ) {
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
				is_null( $whatsapp_registration->nama ) 
			) {
				$whatsapp_registration->nama  = ucfirst(strtolower($this->clean($message)));;
				$whatsapp_registration->save();
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
			}
			if (!empty($response)) {
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
		if ( is_null( $whatsapp_registration->konfirmasi_nomor_antrian ) ) {
			$text  = 'Terima kasih telah mendaftar sebagai pasien di' ;
			$text .= PHP_EOL;
			$text .= '*KLINIK JATI ELOK*' ;
			$text .= PHP_EOL;
			$text .= 'Dengan senang hati kami akan siap membantu Anda.';
			$text .= PHP_EOL;
			$text .= "==============";
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text = 'Fasilitas ini akan memproses nomor antrian';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= '*'.$this->antrian->nomor_antrian. '*';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Apakah nomor antrian tersebut sama dengan nomor antrian anda?';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Balas *A* bila nomor antrian anda  *'.$this->antrian->nomor_antrian. '*';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Balas *B* bila nomor antrian anda  *BUKAN '.$this->antrian->nomor_antrian. '*';
			$text .= PHP_EOL;
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
		if ( 
			is_null ($whatsapp_registration->nama_asuransi )
		) {
			$text = 'Bisa dibantu informasikan *Nama Asuransi / Nama Perusahaan* yang akan digunakan?';
			return $text;
		}
		if ( is_null( $whatsapp_registration->nomor_asuransi ) ) {
			if ( $whatsapp_registration->pembayaran == 'b' ) {
				$text = 'Bisa dibantu *Nomor BPJS* pasien? ';
			} else if ( $whatsapp_registration->pembayaran == 'c' ){
				$text = 'Bisa dibantu *Nomor Asuransi* pasien? (kosongkan bila pembayar tidak ada nomor asuransi) ';
			}
			return $text;
		}
		if ( is_null( $whatsapp_registration->poli ) ) {
			$text .= 'Bisa dibantu berobat ke dokter apa?';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Balas *A* untuk *Dokter Umum*, ';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Balas *B* untuk *Dokter Gigi*, ';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Balas *C* untuk *Suntik KB/Periksa Hamil*.';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Balas *D* untuk *Dokter Estetika/Kecantikan*';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Balas *E* untuk *USG Kebidanan*';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Balas *F* untuk *Rapid Test Antibodi / Antigen*';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Balas *G* untuk *Cek Rutin gula darah / tekanan darah Prolanis BPJS*';
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
			is_null(Pasien::where('nomor_asuransi_bpjs',$whatsapp_registration->nomor_asuransi)->first()) &&
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

	private function input_poli( $whatsapp_registration, $message ){
		$whatsapp_registration->poli   = $this->clean($message);
		if ( $this->clean($message) == 'd' ) {
			$whatsapp_registration->pembayaran   = 'a';
		}
		$whatsapp_registration->save();
	}
	private function formatPoli($param)
	{
		if ( $this->clean($param) == 'a' ) {
			return ' Dokter Umum';
		} else if (  $this->clean($param) == 'b'  ){
			return ' Dokter Gigi';
		} else if (  $this->clean($param) == 'c'  ){
			return ' Suntik KB / Periksa Hamil';
		} else if (  $this->clean($param) == 'd'  ){
			return ' Dokter Estetik / Kecantikan';
		} else if (  $this->clean($param) == 'e'  ){
			return 'USG Kebidanan';
		}
	}
	/**
	* undocumented function
	*
	* @return void
	*/
	private function formatPembayaran($param)
	{
		if ( $this->clean($param) == 'a' ) {
			return 'Biaya Pribadi';
		} else if (  $this->clean($param) == 'b'  ){
			return 'BPJS';
		} else if (  $this->clean($param) == 'c'  ){
			return 'Asuransi Lain';
		}
	}
	public function pesertaBpjs($nomor_bpjs){
		/* $uri="https://dvlp.bpjs-kesehatan.go.id:9081/pcare-rest-v3.0/dokter/0/13"; //url web service bpjs; */
		/* $uri="https://dvlp.bpjs-kesehatan.go.id:9081/pcare-rest-v3.0/provider/0/3"; //url web service bpjs; */
		$uri="https://dvlp.bpjs-kesehatan.go.id:9081/pcare-rest-v3.0/peserta/" . $nomor_bpjs; //url web service bpjs;

		$consID 	= env('CUSTOMER_KEY_BPJS');//"27802"; customer ID anda
		$secretKey 	= env('SECRET_KEY_BPJS');//"6nNF409D69"; secretKey anda

		$pcareUname = env('PCARE_USERNAME_BPJS');// "klinik_jatielok"; //username pcare
		$pcarePWD 	= env('PASSWORD_BPJS');// "*Bpjs2020"; //password pcare anda
		$kdAplikasi	= env('KODE_APLIKASI_BPJS');// "095"; //kode aplikasi

		/* dd( $consID, $secretKey, $pcareUname, $pcarePWD, $kdAplikasi ); */

		$stamp		= time();
		$data 		= $consID.'&'.$stamp;

		$signature            = hash_hmac('sha256', $data, $secretKey, true);
		$encodedSignature     = base64_encode($signature);
		$encodedAuthorization = base64_encode($pcareUname.':'.$pcarePWD.':'.$kdAplikasi);

		$headers = array( 
					"Accept: application/json", 
					"X-cons-id:".$consID, 
					"X-timestamp: ".$stamp, 
					"X-signature: ".$encodedSignature, 
					"X-authorization: Basic " .$encodedAuthorization 
				); 

		$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
		$data = curl_exec($ch);
		curl_close($ch);
		return json_decode($data, true);

	}
}
