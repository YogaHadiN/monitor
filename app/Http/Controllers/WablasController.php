<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\AntrianPeriksa;
use App\Models\AntrianPoli;
use App\Models\Antrian;
use App\Models\Tenant;
use App\Models\JenisAntrian;
use App\Models\WhatsappRegistration;
use App\Models\WhatsappComplaint;
use App\Models\Periksa;
use App\Models\Pasien;
use App\Models\User;
use Input;
use Carbon\Carbon;
use Log;
use DB;
use DateTime;
use App\Models\Sms;
class WablasController extends Controller
{
	/**
	* @param 
	*/
	public $antrian;
	public $gigi_buka     = true;
	public $estetika_buka = true;
	public $whatsapp_registration_deleted;
	public $no_telp;
	public $message;

	public function __construct()
	{
		if (
		 !is_null(Input::get('phone')) &&
		 !is_null(Input::get('message'))
		) {
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
        header('Content-Type: application/json');

        if (
            !is_null( $this->no_telp ) &&
            !is_null( $this->message ) &&
            !Input::get('isFromMe') 
        ) {
            return $this->proceedRegistering();
        }   
	}
    /**
     * undocumented function
     *
     * @return void
     */
    private function proceedRegistering()
    {
        header('Content-Type: application/json');
        Log::info('81');
        $whatsapp_registration = WhatsappRegistration::where('no_telp', $this->no_telp)
            ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
            ->first();

        if ( !is_null($whatsapp_registration) ) {
            $this->antrian = $whatsapp_registration->antrian;
        }

        /* $this->antrian  = Antrian::where('kode_unik', $this->message ) */
        /*                          ->first(); */

        $response              = '';
        $input_tidak_tepat     = false;
        /* if ( !is_null($this->antrian) && $this->antrian->antriable_type == 'App\\Models\\Antrian' ) { */
        /*     if ( is_null( $whatsapp_registration ) ) { */
        /*         $whatsapp_registration = $this->createWAregis(); */
        /*     } */
        /* } else if ( */  
        if (  
            substr($this->message, 0, 5) == 'ulang' &&
            isset( $whatsapp_registration ) &&
            !is_null($whatsapp_registration->antrian)
        ) {
            Log::info('105');
            $this->ulangiRegistrasiWhatsapp($whatsapp_registration);
        } else if ( 
            isset( $whatsapp_registration ) &&
            $whatsapp_registration->registering_confirmation < 1
        ){
            Log::info('111');
            if (
                $this->message == 'lanjutkan'
            ) {
                $whatsapp_registration->registering_confirmation = 1;
                $whatsapp_registration->save();
            } 
        } else if ( 
            isset( $whatsapp_registration ) &&
            !is_null( $whatsapp_registration->antrian ) &&
            is_null( $whatsapp_registration->antrian->registrasi_pembayaran_id ) 
        ){
            Log::info('127');
            if (
                $this->message == 'biaya pribadi' ||
                $this->message == 'bpjs' ||
                $this->message == 'lainnya'
            ) {
                if ($this->message == 'biaya pribadi') {
                    Log::info('138');
                    $whatsapp_registration->antrian->registrasi_pembayaran_id  = 1;
                }
                if ($this->message == 'bpjs') {
                    Log::info('142');
                    $whatsapp_registration->antrian->registrasi_pembayaran_id  = 2;
                }
                if ($this->message == 'lainnya') {
                    Log::info('146');
                    $whatsapp_registration->antrian->registrasi_pembayaran_id  = 3;
                }
                $data = $this->queryPreviouslySavedPatientRegistry();
                if (count($data) < 1) {
                    $whatsapp_registration->antrian->register_previously_saved_patient = 0;
                }
                $whatsapp_registration->antrian->save();

            } else {
                Log::info('152');
                $input_tidak_tepat = true;
            }
        } else if ( 
            isset( $whatsapp_registration ) &&
            !is_null( $whatsapp_registration->antrian ) &&
            is_null( $whatsapp_registration->antrian->register_previously_saved_patient ) 
        ) {
            $data = $this->queryPreviouslySavedPatientRegistry();

            $dataCount = count($data);
            if ( (int)$this->message <= $dataCount && (int)$this->message > 0  ) {
                $whatsapp_registration->antrian->register_previously_saved_patient = $this->message;
                $whatsapp_registration->antrian->pasien_id                         = $data[ (int)$this->message -1 ]->pasien_id;
                $whatsapp_registration->antrian->nama                              = $data[ (int)$this->message -1 ]->nama;
                $whatsapp_registration->antrian->tanggal_lahir                     = $data[ (int)$this->message -1 ]->tanggal_lahir;
            } else {
                Log::info('171');
                $whatsapp_registration->antrian->register_previously_saved_patient = $this->message;
            }
            $whatsapp_registration->antrian->save();

        } else if ( 
            isset( $whatsapp_registration ) &&
            !is_null( $whatsapp_registration->antrian ) &&
            is_null( $whatsapp_registration->antrian->nama ) 
        ) {
            Log::info('159');
            $whatsapp_registration->antrian->nama  = ucwords(strtolower($this->message));;
            $whatsapp_registration->antrian->save();
        } else if ( 
            isset( $whatsapp_registration ) &&
            !is_null( $whatsapp_registration->antrian ) &&
            is_null( $whatsapp_registration->antrian->tanggal_lahir ) 
        ) {
            Log::info('167');
            $tanggals = explode(" ", $this->message );

            if ( $this->validateDate($this->message, $format = 'd-m-Y') ) {
                Log::info('169');
                $whatsapp_registration->antrian->tanggal_lahir  = Carbon::CreateFromFormat('d-m-Y',$this->message)->format('Y-m-d');
                $whatsapp_registration->antrian->save();
            } else if(
                (
                    str_contains( $this->message , 'jan') ||
                    str_contains( $this->message , 'feb') ||
                    str_contains( $this->message , 'mar') ||
                    str_contains( $this->message , 'apr') ||
                    str_contains( $this->message , 'mei') ||
                    str_contains( $this->message , 'jun') ||
                    str_contains( $this->message , 'jul') ||
                    str_contains( $this->message , 'agustus') ||
                    str_contains( $this->message , 'ags') ||
                    str_contains( $this->message , 'sept') ||
                    str_contains( $this->message , 'oktober') ||
                    str_contains( $this->message , 'okt') ||
                    str_contains( $this->message , 'nov') ||
                    str_contains( $this->message , 'des')
                ) &&
                count($tanggals) == 3
            ) {
                $tanggal  = $tanggals[0];
                $bulan    = $tanggals[1];
                $tahun    = $tanggals[2];

                if (strlen($tanggal) == 1) {
                    $tanggal = '0' . $tanggal;
                }

                if(
                     trim($bulan) == 'januari' ||
                     trim($bulan) == 'jan'
                ){
                    $bulan = 1;
                } else if(
                    trim($bulan) == 'februari' || 
                    trim($bulan) == 'feb'
                ){
                    $bulan = 2;
                } else if(
                    trim($bulan) == 'maret' || 
                    trim($bulan) == 'mar'
                ){
                    $bulan = 3;
                } else if(
                    trim($bulan) == 'april' || 
                    trim($bulan) == 'apr'
                ){
                    $bulan = 4;
                } else if( trim($bulan) == 'mei' ){
                    $bulan = 5;
                } else if(
                    trim($bulan) == 'juni' || 
                    trim($bulan) == 'jun'
                ){
                    $bulan = 6;
                } else if(
                    trim($bulan) == 'juli' || 
                    trim($bulan) == 'jul'
                ){
                    $bulan = 7;
                } else if( trim($bulan) == 'agustus' ){
                    $bulan = 8;
                } else if(
                    trim($bulan) == 'september' || 
                    trim($bulan) == 'sept' || 
                    trim($bulan) == 'sep'
                ){
                    $bulan = 9;
                } else if(
                     trim($bulan) == 'oktober' ||
                     trim($bulan) == 'okt'
                ){
                    $bulan = 10;
                } else if(
                    trim($bulan) == 'november' || 
                    trim($bulan) == 'nov'
                ){
                    $bulan = 11;
                } else if(
                    trim($bulan) == 'desember' || 
                    trim($bulan) == 'des'
                ){
                    $bulan = 12;
                }

                $databaseFriendlyDateFormat = $tahun . '-' . $bulan . '-' . $tanggal;

                try {
                    Carbon::parse($databaseFriendlyDateFormat);
                    $whatsapp_registration->antrian->tanggal_lahir  = $databaseFriendlyDateFormat;
                    $whatsapp_registration->antrian->save();
                } catch (\Exception $e) {
                    $input_tidak_tepat = true;
                }

            } else {
                Log::info('281');
                $input_tidak_tepat = true;
            }
        } else if ( 
            isset( $whatsapp_registration ) &&
            !is_null( $whatsapp_registration->antrian ) &&
            !is_null( $whatsapp_registration->antrian->registrasi_pembayaran_id ) &&
            !is_null( $whatsapp_registration->antrian->nama ) &&
            !is_null( $whatsapp_registration->antrian->tanggal_lahir ) 
        ) {
            Log::info('181');
            if (
                $this->message == 'lanjutkan' ||
                $this->message == 'ulangi'
            ) {
                Log::info('186');
                if ($this->message == 'lanjutkan') {
                    Log::info('188');
                    $this->whatsapp_registration_deleted = $whatsapp_registration->delete();
                }
                if ($this->message == 'ulangi') {
                    Log::info('192');
                    $this->ulangiRegistrasiWhatsapp($whatsapp_registration);
                }
            } else {
                Log::info('306');
                $input_tidak_tepat = true;
            }
        }

        if (
            isset( $whatsapp_registration ) &&
            !is_null($whatsapp_registration->antrian)
        ) {
            Log::info("205");
            if (
                !is_null( $whatsapp_registration->antrian->nama ) ||
                !is_null( $whatsapp_registration->antrian->registrasi_pembayaran_id ) ||
                !is_null( $whatsapp_registration->antrian->tanggal_lahir )
            ) {
                Log::info("211");
                $response .=  "*Uraian Pengisian Anda*";
                $response .= PHP_EOL;
                $response .= PHP_EOL;
            }
            if ( !is_null( $whatsapp_registration->antrian->nama ) ) {
                Log::info("217");
                $response .= 'Nama Pasien: ' . ucwords($whatsapp_registration->antrian->nama)  ;
                $response .= PHP_EOL;
            }
            if ( !is_null( $whatsapp_registration->antrian->registrasi_pembayaran_id ) ) {
                Log::info("222");
                $response .= 'Pembayaran : ';
                $response .= ucwords($whatsapp_registration->antrian->registrasiPembayaran->pembayaran);
                $response .= PHP_EOL;
            }
            if ( !is_null( $whatsapp_registration->antrian->tanggal_lahir ) ) {
                Log::info("228");
                $response .= 'Tanggal Lahir : '.  Carbon::CreateFromFormat('Y-m-d',$whatsapp_registration->antrian->tanggal_lahir)->format('d M Y');;
                $response .= PHP_EOL;
            }
            if (
                !is_null( $whatsapp_registration->antrian->nama ) ||
                !is_null( $whatsapp_registration->antrian->registrasi_pembayaran_id) ||
                !is_null( $whatsapp_registration->antrian->tanggal_lahir )
            ) {
                Log::info("237");
                $response .= "==================";
                $response .= PHP_EOL;
                $response .= PHP_EOL;
            }
        }

        if ( 
            !is_null($whatsapp_registration)
        ) {
            Log::info("248");
            Log::info("antrian_id " . $whatsapp_registration->antrian_id);
            $payload   = $this->botKirim($whatsapp_registration)[0];
            $category = $payload['category'];
            $response .= $category == 'button' ? $payload['message']['content'] : $payload['message'];

            if (is_null($this->whatsapp_registration_deleted)) {
                Log::info("254");
                /* $response .=  PHP_EOL; */
                /* $response .=  PHP_EOL; */
                /* $response .= "=============="; */
                /* $response .=  PHP_EOL; */
                /* $response .=  PHP_EOL; */
                /* $response .=  "Balas *ulang* apa bila ada kesalahan dan Anda akan mengulangi pertanyaan dari awal"; */
            }

            /* if ( */
            /*     !is_null($whatsapp_registration) && */
            /*     !is_null($whatsapp_registration->periksa_id) && */
            /*     !is_null($whatsapp_registration->periksa) && */
            /*     !is_null($whatsapp_registration->periksa->satisfaction_index) */
            /* ) { */
            /*     $response .=  "Terima kasih sudah menjawab pertanyaan kami. Masukan Anda sangat berarti untuk kemajuan pelayanan kami"; */
            /* } */

            if ( $input_tidak_tepat ) {
                Log::info('382 ss');
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
            Log::info("330");
            $payload   = $this->botKirim($whatsapp_registration)[0];
            $category = $payload['category'];
            if ( $category == 'button' ) {
                Log::info("334");
                $message['buttons'] = $payload['message']['buttons'];
                $message['content'] = $response;
                $payload = null;

                $payload[] = [
                    'category' => 'button',
                    'message'  => json_encode($message),
                    'footer'   => ''
                ];

                return response()->json([
                    'status' => true,
                    'data'   => $payload
                ])->header('Content-Type', 'application/json');

            } else if ( $category == 'text' ){
                Log::info("351");
                echo $response;
            }
        }


        // dokumentasikan whatsapp complaint
        $whatsapp_complaint = WhatsappComplaint::where('no_telp', $this->no_telp)
                                ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
                                ->first();
        if (!is_null( $whatsapp_complaint )) {
            $whatsapp_complaint->antrian->complaint = $this->message;
            $whatsapp_complaint->antrian->save();
            $whatsapp_complaint->delete();

            $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
            $message .= PHP_EOL;
            $message .= "Keluhan atas pelayanan yang kakak rasakan akan segera kami tindak lanjuti.";
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= "Kami berharap dapat melayani anda dengan lebih baik lagi.";
            echo $message;
        }


        // Cek kepuasan pelanggan
        if ( str_contains( $this->message, '(pxid' ) ) {
            Log::info("357");
            $id = explode('pxid', $this->message)[1];
            $id = preg_replace('~\D~', '', $id);

            $satisfaction_index_ini = $this->satisfactionIndex( $this->message );
            $antrian = Antrian::find($id);
            if (
                !is_null($antrian) 
                && !$antrian->satisfaction_index
                && $antrian->no_telp == $this->no_telp
            ) {
                $antrian->satisfaction_index = $satisfaction_index_ini;
                $antrian->save();

                Log::info('$satisfaction_index_ini '. $satisfaction_index_ini);

                // Jika pasien memilih puas sebagai satisfactionIndex, maka berikan balasan untuk mengklik google review
                //
                if ( $satisfaction_index_ini == 3 ) {
                    echo $this->kirimkanLinkGoogleReview();
                    //
                // Jika pasien memilih tidak puas sebagai satisfactionIndex, maka minta balasan alasan pasien tidak puas
                //
                } else if ( $satisfaction_index_ini == 1 ){

                    $complaint             = new WhatsappComplaint;
                    $complaint->no_telp    = $antrian->no_telp;
                    $complaint->antrian_id = $antrian->id;
                    $complaint->save();

                    WhatsappRegistration::where('no_telp', $antrian->no_telp)->delete();

                    $message = "Mohon maaf atas ketidak nyamanan yang kakak alami.";
                    $message .= PHP_EOL;
                    $message .= "Bisa diinfokan kendala yang kakak alami?";
                    echo $message;

                // Selain itu ucapkan terima kasih
                //
                }else {
                    $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
                    $message .= PHP_EOL;
                    $message .= "kami berharap dapat melayani anda dengan lebih baik lagi.";
                    echo $message;
                }
            }
        }


            // Jika pasien berada di antrian kasir
        if (
            $this->antrian &&
            $this->antrian->antriable_type == 'App\\Models\\AntrianKasir'             
        ){
            Log::info("382");
            $this->saveNomorTeleponPasien();
            // kirimkan nomor rekening beserta dengan jumlah yang harus ditransfer
        } else if (
            $this->antrian &&
             $this->antrian->antriable_type == 'App\\Models\\Periksa' 
        ){
            Log::info("389");
            $this->saveNomorTeleponPasien();
            // kirimkan umpan balik pelayanan
            // jika pasien sudah didaftarkan oleh admin
        } else if (
            $this->antrian &&
            $this->antrian->antriable_type != 'App\\Models\\Antrian'
        ){
            Log::info("397");
            $this->saveNomorTeleponPasien();
            echo $this->pesanBalasanBilaTerdaftar( $this->antrian->nomor_antrian );
        }




    }
    
	private function clean($param)
	{
		if (empty( trim($param) ) && trim($param) != '0') {
			return null;
		}

        if ( str_contains($param, "<~ ") ) {
            $param = explode( "<~ ", $param)[1];
        }
		return strtolower( trim($param) );
	}

	private function botKirim($whatsapp_registration)
	{
        if (
            !is_null($whatsapp_registration) &&
             $whatsapp_registration->registering_confirmation < 1
        ) {
			$text = '*KLINIK JATI ELOK*' ;
			$text .= PHP_EOL;
			$text .= "==============";
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Fasilitas ini akan memproses antrian Untuk berkonsultasi ke *Dokter Umum*';
			$text .= PHP_EOL;
			$text .= 'Apakah Anda ingin melanjutkan?';
			$text .= PHP_EOL;

            $message = [
                'buttons' => [
                    'Lanjutkan',
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
		if (
            !is_null($whatsapp_registration) &&
            !is_null($whatsapp_registration->antrian) &&
             is_null( $whatsapp_registration->antrian->registrasi_pembayaran_id ) 
        ) {
			$text = 'Bisa dibantu menggunakan pembayaran apa? ';

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
		if (
            !is_null($whatsapp_registration) &&
            !is_null($whatsapp_registration->antrian) &&
             is_null( $whatsapp_registration->antrian->register_previously_saved_patient ) 
        ) {
            $data = $this->queryPreviouslySavedPatientRegistry();
            $message = 'Pilih pasien yang akan didaftarkan';
			$message .= PHP_EOL;
			$message .= PHP_EOL;

            foreach ($data as $key => $d) {
                $number = $key + 1;
                $message .= $number . '. ' . ucwords($d->nama);
                $message .= PHP_EOL;
            }
            $message .= "0. Lainnya ";
            $payload[] = [
                'category' => 'text',
                'message'  => $message
            ];

			return $payload;
		}
		if (
            !is_null($whatsapp_registration) &&
            !is_null($whatsapp_registration->antrian) &&
             is_null( $whatsapp_registration->antrian->nama ) 
        ) {
            $payload[] = [
                'category' => 'text',
                'message' =>   'Bisa dibantu *Nama Lengkap* pasien?'
            ];

			return $payload;
		}

		if (
            !is_null($whatsapp_registration) &&
            !is_null($whatsapp_registration->antrian) &&
             is_null( $whatsapp_registration->antrian->tanggal_lahir ) 
        ) {
			$text =  'Bisa dibantu *Tanggal Lahir* pasien? ' . PHP_EOL . PHP_EOL . 'Contoh : 19-07-2003';
            $message = $text;
            $payload[] = [
                'category' => 'text',
                'message' => $message
            ];

			return $payload;
		}

		if (
            !is_null($whatsapp_registration) &&
            !is_null($whatsapp_registration->antrian) &&
            !is_null( $whatsapp_registration->antrian->tanggal_lahir ) &&
            is_null( $this->whatsapp_registration_deleted ) 
        ) {
            $text = 'Data anda sudah kami terima. Apakah anda ingin melanjutkan atau ulangi karena ada kesalahan input data?';
            $text .= PHP_EOL;

            $message = [
                'buttons' => [
                    'Lanjutkan',
                    'Ulangi'
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

		if (
            !is_null( $this->whatsapp_registration_deleted ) 
        ) {

            $registeredWhatsapp = WhatsappRegistration::where('no_telp', $this->no_telp)
                ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
                ->get();

            $text = "Terima kasih atas kesediaan menjawab pertanyaan kami" ;
            $text .= PHP_EOL;
            $text .= $this->pesanBalasanBilaTerdaftar( $this->antrian->nomor_antrian );
            if ( $registeredWhatsapp->count() ) {
                $registeredWhatsapp = $registeredWhatsapp->first();

                $text .= PHP_EOL;
                $text .= PHP_EOL;
                $text .= '=======================';
                $text .= PHP_EOL;
                $text .= 'Anda akan memproses antrian ' . $registeredWhatsapp->antrian->nomor_antrian;
                $text .= PHP_EOL;
                $text .= 'Apakah Anda ingin melanjutkan?';
                $text .= PHP_EOL;


                $message = [
                    'buttons' => [
                        'Lanjutkan'
                    ],
                    'content' => $text,
                    'footer' => ''
                ];

                $payload[] = [
                    'category' => 'button',
                    'message' => $message
                ];

                return $payload;
            } else {
                $payload[] = [
                    'category' => 'text',
                    'message' => $text
                ];
                return $payload;
            }
		}
	}

	/*private function input_poli( $whatsapp_registration, $message ){ */
	/*	Log::info('input_poli'); */
	/*	if ($whatsapp_registration->antrian->jenis_antrian_id == 1) { */
	/*		if ( $this->message == 'a' ) { */
	/*			$whatsapp_registration->antrian->poli_id    = 'umum'; */
	/*		} else if ( $this->message == 'b'   ){ */
	/*			if ($whatsapp_registration->antrian->nama_asuransi == 'BPJS') { */
	/*				$whatsapp_registration->antrian->poli_id    = 'prolanis_ht'; */
	/*			} else { */
	/*				$whatsapp_registration->antrian->poli_id    = 'sks'; */
	/*			} */
	/*		} else if ( $this->message == 'c'   ){ */
	/*			if ($whatsapp_registration->antrian->nama_asuransi == 'BPJS') { */
	/*				$whatsapp_registration->antrian->poli_id    = 'prolanis_dm'; */
	/*			} else { */
	/*				$whatsapp_registration->antrian->poli_id    = 'rapid test'; */
	/*			} */
	/*		} */ 
	/*	} */
	/*	if ($whatsapp_registration->antrian->jenis_antrian_id == 3) { */
	/*		if ( $this->message == 'a' ) { */
	/*			$whatsapp_registration->antrian->poli_id    = 'anc'; */
	/*		} else if ( $this->message == 'b'   ){ */
	/*			$whatsapp_registration->antrian->poli_id    = 'kb 1 bulan'; */
	/*		} else if ( $this->message == 'c'   ){ */
	/*			$whatsapp_registration->antrian->poli_id    = 'kb 3 bulan'; */
	/*		} */
	/*	} */
	/*	Log::info('input_poli_saved'); */
	/*	$whatsapp_registration->antrian->save(); */
	/*} */
	/*/1** */
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
		$whatsapp_registration                           = new WhatsappRegistration;
		$whatsapp_registration->no_telp                  = $this->no_telp;
		$whatsapp_registration->registering_confirmation = 0;
		$whatsapp_registration->tenant_id                = 1;
		$whatsapp_registration->antrian_id                = $this->antrian->id;
		$whatsapp_registration->save();

		return $whatsapp_registration;
	}
    /**
     * undocumented function
     *
     * @return void
     */
    private function satisfactionIndex($message){
        if ( str_contains( $message, 'tidak' ) ) {
            return 1;
        } else if ( str_contains( $message, 'biasa' ) ){
            return 2;
        } else if ( str_contains( $message,  'puas'  ) ){
            return 3;
        } else {
            return null;
        }
    }
    public function wablas2(){
        Log::info("wablas_masuk" . strtotime("now"));
        
        header('Content-Type: application/json');
        $payload[] = [
            'category' => 'button',
            'message' => '{"buttons":["button 12","button 22","button 33"],"content":"sending button message.","footer":"footer here"}'
        ];

        return response()->json([
            'status' => true,
            'data' => $payload
        ])->header('Content-Type', 'application/json');
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function ulangiRegistrasiWhatsapp($whatsapp_registration)
    {
        $whatsapp_registration->antrian->registrasi_pembayaran_id = null;
        $whatsapp_registration->antrian->nama                     = null;
        $whatsapp_registration->antrian->tanggal_lahir            = null;
        $whatsapp_registration->antrian->save();
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function pesanBalasanBilaTerdaftar($nomor_antrian)
    {
        $response = "Anda telah terdaftar dengan Nomor Antrian";
        $response .= PHP_EOL;
        $response .= PHP_EOL;
        $response .= "```" . $nomor_antrian . "```" ;
        $response .= PHP_EOL;
        $response .= PHP_EOL;
        $response .= "Silahkan menunggu untuk dilayani";
        $response .=  PHP_EOL;
        $response .=  PHP_EOL;
        $response .=  "Anda dapat menggunakan handphone ini untuk mendaftarkan pasien berikutnya";

        return $response;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function saveNomorTeleponPasien()
    {
        $this->antrian->no_telp = $this->no_telp;
        $this->antrian->save();
    }

    /**
     * undocumented function
     *
     * @return void
     */
    private function kirimkanLinkGoogleReview()
    {
        $message = "Terima kasih atas kesediaan anda memberikan masukan terhadap pelayanan Kami, ";
        $message .= PHP_EOL;
        $message .= "kami berharap dapat melayani anda dengan lebih baik lagi.";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "mohon memberikan nilai layanan kami dengan memberikan ulasan *Bintang 5* di google review Klinik Jati Elok hanya dengan klik link dibawah ini : ";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "https://bit.ly/3DInVOr";
        return $message;
    }
    
    public function sendButton($data){
        $curl = curl_init();
        $token = env('WABLAS_TOKEN');
        $payload = [
            "data" => $data
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER,
            array(
                "Authorization: $token",
                "Content-Type: application/json"
            )
        );
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload) );
        curl_setopt($curl, CURLOPT_URL,  "https://pati.wablas.com/api/v2/send-button");
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $result = curl_exec($curl);
        curl_close($curl);
        /* echo "<pre>"; */
        /* print_r($result); */
    }

    /**
     * undocumented function
     *
     * @return void
     */
    private function queryPreviouslySavedPatientRegistry()
    {
        $query  = "SELECT ";
        $query .= "psn.nama as nama, ";
        $query .= "psn.tanggal_lahir as tanggal_lahir, ";
        $query .= "psn.id as pasien_id ";
        $query .= "FROM antrians as ant ";
        $query .= "JOIN periksas as prx on prx.id = ant.antriable_id and ant.antriable_type ='App\\\Models\\\Periksa' ";
        $query .= "JOIN pasiens as psn on psn.id = prx.pasien_id ";
        $query .= "WHERE antriable_type = 'App\\\Models\\\Periksa' ";
        $query .= "AND ant.no_telp = '{$this->no_telp}' ";
        $query .= "GROUP BY prx.pasien_id";
        return DB::select($query);
    }
    
    
    
    
    
}
