<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AntrianPeriksa;
use App\Models\AntrianPoli;
use App\Models\Antrian;
use App\Models\Tenant;
use App\Models\JenisAntrian;
use App\Models\WhatsappRegistration;
use App\Models\Periksa;
use App\Models\Pasien;
use App\Models\User;
use Input;
use Carbon\Carbon;
use Log;
use DateTime;
use App\Models\Sms;
/* use App\Http\Controllers\FasilitasController; */
class WablasController extends Controller
{
	/**
	* @param 
	*/
	public $antrian;
	public $gigi_buka     = true;
	public $estetika_buka = true;
	public $no_telp;
	public $message;

	public function __construct()
	{

		if (
		 !is_null(Input::get('phone')) &&
		 !is_null(Input::get('message'))
		) {
			$this->no_telp = Input::get('phone');
			$this->message = $this->clean(Input::get('message'));
            $this->no_telp = Input::get('phone');

			// gigi buka
			if ( 
				( date('w') < 1 ||  date('w') > 5)
			) {
				$this->gigi_buka = false;
			}

			if ( !( date('H') >= 15 && date('H') <= 19)) { // jam 3 sore sampai 8 malam 
				$this->gigi_buka = false;
			}


			//estetika_buka
			if ( 
				( date('w') < 1 ||  date('w') > 5)
			) {
				$this->estetika_buka = false;
			}

			if ( !( date('H') >= 11 && date('H') <= 15)) { // jam 11 siang sampai 5 sore 
				$this->estetika_buka = false;
			}
		}
	}
	
	public function webhook(){

		header("Content-Type: text/plain");

        echo 'oke';


        if (
		 !is_null( $this->no_telp ) &&
		 !is_null( $this->message )
        ) {
            Log::info("=========================================");
            Log::info( $this->message );
            Log::info("=========================================");
            $whatsapp_registration = WhatsappRegistration::where('no_telp', $this->no_telp)
                                        ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
                                        ->first();
            $this->tenant  = Tenant::where('kode_unik', $this->message )->first();

            $response              = '';
            $input_tidak_tepat     = false;
            if ( !is_null($this->tenant) ) {
                if ( is_null( $whatsapp_registration ) ) {
                    $whatsapp_registration = $this->createWAregis();
                }
            } else if (  
                substr($this->clean($message), 0, 5) == 'ulang' &&
                isset( $whatsapp_registration ) &&
                !is_null($whatsapp_registration->antrian)
            ) {
                Log::info('antrian');
                $whatsapp_registration->no_telp                  = null;
                $whatsapp_registration->registrasi_pembayaran_id = null;
                $whatsapp_registration->nama                     = null;
                $whatsapp_registration->tanggal_lahir            = null;
                $whatsapp_registration->save();
            } else if ( 
                isset( $whatsapp_registration ) &&
                is_null( $whatsapp_registration->registering_confirmation ) 
            ){
                Log::info('registering_confirmation');
                if (
                    $this->clean($message) == 'Lanjutkan'
                ) {
                    $whatsapp_registration->registering_confirmation  = 1;
                    $whatsapp_registration->save();
                } else if (
                    $this->clean($message) == 'Jangan Lanjutkan'
                ) {
                    $whatsapp_registration->delete();
                    $whatsapp_registration = null;
                    $response .=    "Silahkan scan QR Code Pendaftaran yang tersedia di klinik";
                } else {
                    $input_tidak_tepat = true;
                }
            } else if ( 
                isset( $whatsapp_registration ) &&
                is_null( $whatsapp_registration->registrasi_pembayaran_id ) 
            ){
                Log::info('pembayaran');
                if (
                    $this->clean($message) == 'Biaya Pribadi' ||
                    $this->clean($message) == 'BPJS' ||
                    $this->clean($message) == 'Lainnya'
                ) {
                    if ($this->clean($message) == 'Biaya Pribadi') {
                        $whatsapp_registration->registrasi_pembayaran_id  = 1;
                    }
                    if ($this->clean($message) == 'BPJS') {
                        $whatsapp_registration->registrasi_pembayaran_id  = 2;
                    }
                    if ($this->clean($message) == 'Lainnya') {
                        $whatsapp_registration->registrasi_pembayaran_id  = 3;
                    }
                    $whatsapp_registration->save();
                } else {
                    $input_tidak_tepat = true;
                }
            } else if ( 
                isset( $whatsapp_registration ) &&
                is_null( $whatsapp_registration->tanggal_lahir ) 
            ) {
                Log::info('tanggal_lahir');
                if ( $this->validateDate($this->clean($message), $format = 'd-m-Y') ) {
                    $whatsapp_registration->tanggal_lahir  = Carbon::CreateFromFormat('d-m-Y',$this->clean($message))->format('Y-m-d');
                    $whatsapp_registration->save();
                } else {
                    $input_tidak_tepat = true;
                }
            } else if ( 
                isset( $whatsapp_registration ) &&
                is_null( $whatsapp_registration->nama ) 
            ) {
                Log::info('nama');
                $whatsapp_registration->nama  = ucfirst(strtolower($this->clean($message)));;
                $whatsapp_registration->save();
            /* } else if ( */ 
            /*     isset( $whatsapp_registration ) && */
            /*     is_null( $whatsapp_registration->alamat ) */ 
            /* ) { */
            /*      $whatsapp_registration->alamat  = $this->clean($message); */
            /*      $whatsapp_registration->save(); */
            } else if (
                isset( $whatsapp_registration ) &&
                !is_null($whatsapp_registration->periksa_id) &&
                !is_null($whatsapp_registration->periksa)
            ){
                if (
                    !is_null($this->satisfactionIndex($this->clean($message)))
                ) {
                    $whatsapp_registration->satisfaction_index_id = $this->satisfactionIndex($this->clean($message));
                    $whatsapp_registration->save();
                } else {
                    $input_tidak_tepat = true;
                }
            }
        /* } else if ( */ 
        /* 	
                isset( $whatsapp_registration ) &&
                !is_null($whatsapp_registration->antrian)
 && */
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
            /* 	
                isset( $whatsapp_registration ) &&
                !is_null($whatsapp_registration->antrian)
 && */
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
            /* 	
                isset( $whatsapp_registration ) &&
                !is_null($whatsapp_registration->antrian)
 && */
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
            /* 	
                isset( $whatsapp_registration ) &&
                !is_null($whatsapp_registration->antrian)
 && */
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
            /* 	
                isset( $whatsapp_registration ) &&
                !is_null($whatsapp_registration->antrian)
 && */
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

            if (
                isset( $whatsapp_registration ) &&
                !is_null($whatsapp_registration->antrian)
 
            ) {
                if (
                 !is_null( $whatsapp_registration->nama ) ||
                 !is_null( $whatsapp_registration->registrasi_pembayaran_id ) ||
                 !is_null( $whatsapp_registration->tanggal_lahir )
                ) {
                    $response .=  "*Uraian Pengisian Anda*";
                    $response .= PHP_EOL;
                    $response .= PHP_EOL;
                }
                if ( !is_null( $whatsapp_registration->nama ) ) {
                    $response .= 'Nama Pasien: ' . ucwords($whatsapp_registration->nama)  ;
                    $response .= PHP_EOL;
                }
                if ( !is_null( $whatsapp_registration->registrasi_pembayaran_id ) ) {
                    $response .= 'Pembayaran : ';
                    $response .= ucwords($whatsapp_registration->registrasiPembayaran->pembayaran);
                    $response .= PHP_EOL;
                }
                if ( !is_null( $whatsapp_registration->tanggal_lahir ) ) {
                    $response .= 'Tanggal Lahir : '.  Carbon::CreateFromFormat('Y-m-d',$whatsapp_registration->tanggal_lahir)->format('d M Y');;
                    $response .= PHP_EOL;
                }
                if (
                 !is_null( $whatsapp_registration->nama ) ||
                 !is_null( $whatsapp_registration->registrasi_pembayaran_id) ||
                 !is_null( $whatsapp_registration->tanggal_lahir )
                ) {
                    $response .= PHP_EOL;
                    $response .=    "==================";
                    $response .= PHP_EOL;
                    $response .= PHP_EOL;
                }
            }
            if ( 
                !is_null($whatsapp_registration)
            ) {
                $response .=  $this->botKirim($whatsapp_registration);
                if (
                    !is_null($whatsapp_registration) &&
                    !is_null($whatsapp_registration->antrian_id)
                ) {

                    $response .=  PHP_EOL;
                    $response .=  PHP_EOL;
                    $response .= "==============";
                    $response .=  PHP_EOL;
                    $response .=  PHP_EOL;
                    $response .=  "Balas *ulang* apa bila ada kesalahan dan Anda akan mengulangi pertanyaan dari awal";
                }

                if (
                    !is_null($whatsapp_registration) &&
                    !is_null($whatsapp_registration->periksa_id) &&
                    !is_null($whatsapp_registration->periksa) &&
                    !is_null($whatsapp_registration->periksa->satisfaction_index)
                ) {
                    $response .=  "Terima kasih sudah menjawab pertanyaan kami. Masukan Anda sangat berarti untuk kemajuan pelayanan kami";
                }

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
                /* Sms::send($this->no_telp, $response); */
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

	}
	private function clean($param)
	{
		if (empty( trim($param) ) && trim($param) != '0') {
			return null;
		}
		return strtolower( trim($param) );
	}

	private function botKirim($whatsapp_registration)
	{
		if ( is_null( $whatsapp_registration->registering_confirmation ) ) {
			$text  = 'Terima kasih telah mendaftar sebagai pasien di' ;
			$text .= PHP_EOL;
			$text .= '*KLINIK JATI ELOK*' ;
			$text .= PHP_EOL;
			$text .= 'Dengan senang hati kami akan siap membantu Anda.';
			$text .= PHP_EOL;
			$text .= "==============";
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Fasilitas ini akan memproses antrian anda di Klinik Jati Elok';
			$text .= PHP_EOL;
			$text .= 'Untuk berkonsultasi ke Dokter Umum';
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Apakah Anda ingin melanjutkan?';
			$text .= PHP_EOL;

            $message = [
                'buttons' => [
                    'Lanjutkan',
                    'Jangan Lanjutkan'
                ],
                'content' => $text,
                'footer' => ''
            ];

            $payload[] = [
                'category' => 'button',
                'message' => $message
            ];

			return $payload;
		}
		if ( is_null( $whatsapp_registration->antrian->pembayaran ) ) {
			$text = 'Bisa dibantu pembayaran menggunakan apa? ';

            $message = [
                'buttons' => [
                    'Biaya Pribadi',
                    'BPJS',
                    'Lainnya'
                ],
                'content' => $text,
                'footer' => ''
            ];

            $payload[] = [
                'category' => 'button',
                'message' => $message
            ];

			return $payload;
		}
		/* if ( */ 
		/* 	is_null ($whatsapp_registration->antrian->nama_asuransi ) */
		/* ) { */
		/* 	$text = 'Bisa dibantu informasikan *Nama Asuransi / Nama Perusahaan* yang akan digunakan?'; */
		/* 	return $text; */
		/* } */
		/* if ( is_null( $whatsapp_registration->antrian->nomor_asuransi ) ) { */
		/* 	if ( $whatsapp_registration->antrian->pembayaran == 'b' ) { */
		/* 		$text = 'Bisa dibantu *Nomor BPJS* pasien? '; */
		/* 	} else if ( $whatsapp_registration->antrian->pembayaran == 'c' ){ */
		/* 		$text = 'Bisa dibantu *Nomor Asuransi* pasien?'; */
		/* 		$text .= PHP_EOL; */
		/* 		$text .= 'Balas *0* (nol) jika tidak tahu atau tidak ada nomor asuransi'; */
		/* 	} */
		/* 	return $text; */
		/* } */
		/* if ( is_null( $whatsapp_registration->antrian->nama_asuransi ) ) { */
		/* 	return  'Bisa dibantu *Nama Asuransi Yang Digunakan* pasien?'; */
		/* } */
		if ( is_null( $whatsapp_registration->tanggal_lahir ) ) {
			$text =  'Bisa dibantu *Tanggal Lahir* pasien? ' . PHP_EOL . PHP_EOL . 'Contoh : *19 Juli 2003*, Balas dengan *19-07-2003*';
            $message = 'message : ' . $text;
            $payload[] = [
                'category' => 'text',
                'message' => $message
            ];

			return $payload;
		}
		if ( is_null( $whatsapp_registration->antrian->nama ) ) {
			$text =  'Bisa dibantu *Nama Lengkap* pasien?';
            $message = 'message : ' . $text;
            $payload[] = [
                'category' => 'text',
                'message' => $message
            ];

			return $payload;
		}

		/* if ( is_null( $whatsapp_registration->antrian->poli_id ) ) { */
		/* 	$text = 'Bisa dibantu berobat ke dokter apa?'; */
		/* 	$text .= PHP_EOL; */
		/* 	$text .= PHP_EOL; */
		/* 	$text .= PHP_EOL; */
		/* 	if ($whatsapp_registration->antrian->jenis_antrian_id == 1) { */
		/* 		$text .= 'Balas *A* untuk *Konsultasi ke Dokter Umum*, '; */
		/* 		if ($whatsapp_registration->antrian->nama_asuransi == 'BPJS') { */
		/* 			$text .= PHP_EOL; */
		/* 			$text .= PHP_EOL; */
		/* 			$text .= 'Balas *B* untuk *Cek Rutin tekanan darah Prolanis BPJS*'; */
		/* 			$text .= PHP_EOL; */
		/* 			$text .= PHP_EOL; */
		/* 			$text .= 'Balas *C* untuk *Cek Rutin gula darah Prolanis BPJS*'; */
		/* 		} else { */
		/* 			$text .= PHP_EOL; */
		/* 			$text .= PHP_EOL; */
		/* 			$text .= 'Balas *B* untuk pembuatan *Surat Keterangan Sehat*, '; */
		/* 			$text .= PHP_EOL; */
		/* 			$text .= PHP_EOL; */
		/* 			$text .= 'Balas *C* untuk *Rapid Test Antibodi / Antigen*'; */
		/* 		} */
		/* 	} else if ($whatsapp_registration->antrian->jenis_antrian_id == 3) { */
		/* 		$text .= 'Balas *A* untuk *Periksa Hamil*, '; */
		/* 		$text .= PHP_EOL; */
		/* 		$text .= PHP_EOL; */
		/* 		$text .= 'Balas *B* untuk *Suntik KB 1 bulan*, '; */
		/* 		$text .= PHP_EOL; */
		/* 		$text .= PHP_EOL; */
		/* 		$text .= 'Balas *C* untuk *Suntik KB 3 bulan*'; */
		/* 	} */
		/* 	return $text; */

		/* } */
		/* if ( is_null( $whatsapp_registration->antrian->alamat ) ) { */
		/* 	return  'Bisa dibantu informasikan *Alamat Lengkap* pasien?'; */
		/* } */
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

		/* $jenis_antrian_id = null; */
		/* if ( $whatsapp_registration->antrian->poli_id == 'a'	) { */
		/* 	$jenis_antrian_id = 1; */
		/* } else if ( $whatsapp_registration->antrian->poli_id == 'b'	){ */
		/* 	$jenis_antrian_id = 2; */
		/* } else if ( $whatsapp_registration->antrian->poli_id == 'c'	){ */
		/* 	$jenis_antrian_id = 3; */
		/* } else if ( $whatsapp_registration->antrian->poli_id == 'd'	){ */
		/* 	$jenis_antrian_id = 4; */
		/* } else if ( $whatsapp_registration->antrian->poli_id == 'e'	){ */
		/* 	$jenis_antrian_id = 5; */
		/* } */

		/* if ( is_null( Antrian::where('whatsapp_registration_id', $whatsapp_registration->id)->first() ) ) { */
		/* 	$fasilitas                         = new FasilitasController; */
		/* 	$antrian                           = $fasilitas->antrianPost( $jenis_antrian_id ); */
		/* 	$nomor_antrian                     = $antrian->nomor_antrian; */
		/* 	$antrian->whatsapp_registration_id = $whatsapp_registration->id; */
		/* 	$antrian->save(); */
		/* 	$whatsapp_registration->antrian_id = $antrian->id; */
		/* 	$whatsapp_registration->save(); */
		/* } */		

        $antrian = Antrian::createFromWhatsappRegistration($whatsapp_registration);

		$text = "Terima kasih atas kesediaan menjawab pertanyaan kami" ;
		$text .= PHP_EOL;
		$text .= "Anda telah terdaftar dengan Nomor Antrian";
		$text .= PHP_EOL;
		$text .= PHP_EOL;
		$text .= "```" . $antrian->nomor_antrian . "```";
		$text .= PHP_EOL;
		$text .= PHP_EOL;
		$text .= "Silahkan menunggu untuk dilayani";
		return $text;
	}

	private function input_poli( $whatsapp_registration, $message ){
		Log::info('input_poli');
		if ($whatsapp_registration->antrian->jenis_antrian_id == 1) {
			if ( $this->clean($message) == 'a' ) {
				$whatsapp_registration->antrian->poli_id    = 'umum';
			} else if ( $this->clean($message) == 'b'   ){
				if ($whatsapp_registration->antrian->nama_asuransi == 'BPJS') {
					$whatsapp_registration->antrian->poli_id    = 'prolanis_ht';
				} else {
					$whatsapp_registration->antrian->poli_id    = 'sks';
				}
			} else if ( $this->clean($message) == 'c'   ){
				if ($whatsapp_registration->antrian->nama_asuransi == 'BPJS') {
					$whatsapp_registration->antrian->poli_id    = 'prolanis_dm';
				} else {
					$whatsapp_registration->antrian->poli_id    = 'rapid test';
				}
			} 
		}
		if ($whatsapp_registration->antrian->jenis_antrian_id == 3) {
			if ( $this->clean($message) == 'a' ) {
				$whatsapp_registration->antrian->poli_id    = 'anc';
			} else if ( $this->clean($message) == 'b'   ){
				$whatsapp_registration->antrian->poli_id    = 'kb 1 bulan';
			} else if ( $this->clean($message) == 'c'   ){
				$whatsapp_registration->antrian->poli_id    = 'kb 3 bulan';
			}
		}
		Log::info('input_poli_saved');
		$whatsapp_registration->antrian->save();
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
	private function validateDate($date, $format = 'Y-m-d')
	{
		$d = DateTime::createFromFormat($format, $date);
		// The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
		return $d && $d->format($format) === $date;
	}
	/**
	* undocumented function
	*
	* @return void
	*/
	/**
	* undocumented function
	*
	* @return void
	*/
	private function createWAregis()
	{
		$whatsapp_registration             = new WhatsappRegistration;
		$whatsapp_registration->no_telp    = $this->no_telp;
		$whatsapp_registration->antrian_id = $this->antrian->id;
		$whatsapp_registration->tenant_id  = $this->antrian->tenant_id;
		$whatsapp_registration->save();

		$whatsapp_registration->antrian->no_telp    = $this->no_telp;
		$whatsapp_registration->antrian->save();
		return $whatsapp_registration;
	}
    /**
     * undocumented function
     *
     * @return void
     */
    private function satisfactionIndex($message)
    {
        if ( $message == "Sangat Baik" ) {
            return 5;
        } else if ( $message == 'Baik'  ){
            return 4;
        } else if ( $message == 'Biasa'  ){
            return 3;
        } else if ( $message == 'Buruk'  ){
            return 2;
        } else if ( $message == 'Sangat Buruk'  ){
            return 1;
        } else {
            return null;
        }
    }
    
	
}
