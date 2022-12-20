<?php 
namespace App\Http\Controllers; 
use Illuminate\Http\Request; 
use App\Models\AntrianPeriksa; 
use App\Models\AntrianPoli;
use App\Models\DentistReservation;
use App\Models\Antrian;
use App\Models\Tenant;
use App\Models\JenisAntrian;
use App\Models\WhatsappRegistration;
use App\Models\WhatsappSatisfactionSurvey;
use App\Models\WhatsappRecoveryIndex;
use App\Models\KuesionerMenungguObat;
use App\Models\WhatsappMainMenu;
use App\Models\WhatsappBpjsDentistRegistration;
use App\Models\WhatsappComplaint;
use App\Models\FailedTherapy;
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
	public $antrian;
	public $gigi_buka     = true;
	public $estetika_buka = true;
	public $whatsapp_registration_deleted;
	public $whatsapp_main_menu;
	public $kuesioner_menunggu_obat;
	public $whatsapp_registration;
	public $whatsapp_recovery_index;
	public $whatsapp_complaint;
	public $tenant;
	public $failed_therapy;
	public $no_telp;
	public $message;
    public $whatsapp_satisfaction_survey;
    public $whatsapp_bpjs_dentist_registrations;

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

        $this->whatsapp_registration = WhatsappRegistration::where('no_telp', $this->no_telp)
                                        ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
                                        ->first();

        $this->whatsapp_complaint = WhatsappComplaint::where('no_telp', $this->no_telp)
                                    ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
                                    ->first();

        $this->failed_therapy = FailedTherapy::where('no_telp', $this->no_telp)
                                ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
                                ->first();

        $this->whatsapp_satisfaction_survey = WhatsappSatisfactionSurvey::where('no_telp', $this->no_telp)
                                ->whereRaw("DATE_ADD( updated_at, interval 23 hour ) > '" . date('Y-m-d H:i:s') . "'")
                                ->first();

        $this->whatsapp_recovery_index = WhatsappRecoveryIndex::where('no_telp', $this->no_telp)
                                ->whereRaw("DATE_ADD( updated_at, interval 23 hour ) > '" . date('Y-m-d H:i:s') . "'")
                                ->first();

        $this->kuesioner_menunggu_obat = KuesionerMenungguObat::where('no_telp', $this->no_telp)
                                ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
                                ->first();

        $this->whatsapp_main_menu = WhatsappMainMenu::where('no_telp', $this->no_telp)
                                ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
                                ->first();

        $this->whatsapp_bpjs_dentist_registrations = WhatsappBpjsDentistRegistration::where('no_telp', $this->no_telp)
                                                    ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
                                                    ->first();

        $this->tenant = Tenant::find(1);

        if (
            !is_null( $this->no_telp ) &&
            !is_null( $this->message ) &&
            !Input::get('isFromMe') 
        ) {
            if ( !is_null( $this->whatsapp_registration ) ) {
                return $this->proceedRegistering(); //register untuk pendaftaran pasien
            } else if (!is_null( $this->whatsapp_complaint )){
                return $this->registerWhatsappComplaint(); //register untuk pendataan complain pasien
            } else if (!is_null( $this->failed_therapy  )) {
                return $this->registerFailedTherapy(); //register untuk pendataan kegagalan terapi
            } else if (!is_null( $this->whatsapp_satisfaction_survey  )) {
                return $this->registerWhatsappSatisfactionSurvey(); //register untuk survey kepuasan pasien
            } else if (!is_null( $this->whatsapp_recovery_index  )) {
                return $this->registerWhatsappRecoveryIndex(); //register untuk survey kesembuhan pasien
            } else if (!is_null( $this->kuesioner_menunggu_obat  )) {
                return $this->registerKuesionerMenungguObat(); //register untuk survey kesembuhan pasien
            } else if (!is_null( $this->whatsapp_main_menu  )) {
                return $this->registerWhatsappMainMenu(); //register untuk survey kesembuhan pasien
            } else if (!is_null( $this->whatsapp_bpjs_dentist_registrations  )) {
                return $this->registerWhatsappBpjsDentistRegistration(); //register untuk survey kesembuhan pasien
            } else {
                /* return $this->createWhatsappMainMenu(); //whatsapp bot main menu */
            }
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

        if ( !is_null($this->whatsapp_registration) ) {
            $this->antrian = $this->whatsapp_registration->antrian;
        }

        /* $this->antrian  = Antrian::where('kode_unik', $this->message ) */
        /*                          ->first(); */

        $response              = '';
        $input_tidak_tepat     = false;
        if (  
            substr($this->message, 0, 5) == 'ulang' &&
            isset( $this->whatsapp_registration ) &&
            !is_null($this->whatsapp_registration->antrian)
        ) {
            $this->ulangiRegistrasiWhatsapp();
        } else if ( 
            isset( $this->whatsapp_registration ) &&
            $this->whatsapp_registration->registering_confirmation < 1
        ){
            if (
                ( $this->message == 'lanjutkan' && $this->tenant->iphone_whatsapp_button_available ) ||
                ( $this->message == 'ya' && !$this->tenant->iphone_whatsapp_button_available )
            ) {
                $this->whatsapp_registration->registering_confirmation = 1;
                $this->whatsapp_registration->save();
            } 
        } else if ( 
            isset( $this->whatsapp_registration ) &&
            !is_null( $this->whatsapp_registration->antrian ) &&
            is_null( $this->whatsapp_registration->antrian->registrasi_pembayaran_id ) 
        ){
            if (
                ( $this->message == 'biaya pribadi' && $this->tenant->iphone_whatsapp_button_available ) ||
                ($this->message == 'bpjs' && $this->tenant->iphone_whatsapp_button_available) ||
                ( $this->message == 'lainnya' && $this->tenant->iphone_whatsapp_button_available ) ||
                ( $this->message == '1' && !$this->tenant->iphone_whatsapp_button_available ) ||
                ($this->message == '2' && !$this->tenant->iphone_whatsapp_button_available) ||
                ( $this->message == '3' && !$this->tenant->iphone_whatsapp_button_available )

            ) {
                if (
                    ( $this->message == 'biaya pribadi' && $this->tenant->iphone_whatsapp_button_available ) ||
                    ( $this->message == '1' && !$this->tenant->iphone_whatsapp_button_available )
                ) {
                    $this->whatsapp_registration->antrian->registrasi_pembayaran_id  = 1;
                }
                if (
                    ( $this->message == 'bpjs' && $this->tenant->iphone_whatsapp_button_available ) ||
                    ( $this->message == '2' && !$this->tenant->iphone_whatsapp_button_available )
                ) {
                    $this->whatsapp_registration->antrian->registrasi_pembayaran_id  = 2;
                }
                if (
                    ( $this->message == 'lainnya' && $this->tenant->iphone_whatsapp_button_available ) ||
                    ( $this->message == '3' && !$this->tenant->iphone_whatsapp_button_available )
                ) {
                    $this->whatsapp_registration->antrian->registrasi_pembayaran_id  = 3;
                }
                $data = $this->queryPreviouslySavedPatientRegistry();
                if (count($data) < 1) {
                    $this->whatsapp_registration->antrian->register_previously_saved_patient = 0;
                }

                $this->whatsapp_registration->antrian->save();

            } else {
                $input_tidak_tepat = true;
            }
        } else if ( 
            isset( $this->whatsapp_registration ) &&
            !is_null( $this->whatsapp_registration->antrian ) &&
            is_null( $this->whatsapp_registration->antrian->register_previously_saved_patient ) 
        ) {
            $data = $this->queryPreviouslySavedPatientRegistry();

            $dataCount = count($data);
            if ( (int)$this->message <= $dataCount && (int)$this->message > 0  ) {
                $this->whatsapp_registration->antrian->register_previously_saved_patient = $this->message;
                $this->whatsapp_registration->antrian->pasien_id                         = $data[ (int)$this->message -1 ]->pasien_id;
                $this->whatsapp_registration->antrian->nama                              = $data[ (int)$this->message -1 ]->nama;
                $this->whatsapp_registration->antrian->tanggal_lahir                     = $data[ (int)$this->message -1 ]->tanggal_lahir;
            } else {
                $this->whatsapp_registration->antrian->register_previously_saved_patient = $this->message;
            }
            $this->whatsapp_registration->antrian->save();

        } else if ( 
            isset( $this->whatsapp_registration ) &&
            !is_null( $this->whatsapp_registration->antrian ) &&
            is_null( $this->whatsapp_registration->antrian->nama ) 
        ) {
            $this->whatsapp_registration->antrian->nama  = ucwords(strtolower($this->message));;
            $this->whatsapp_registration->antrian->save();
        } else if ( 
            isset( $this->whatsapp_registration ) &&
            !is_null( $this->whatsapp_registration->antrian ) &&
            is_null( $this->whatsapp_registration->antrian->tanggal_lahir ) 
        ) {
            $tanggal = $this->convertToPropperDate();
            if (!is_null( $tanggal )) {
                $this->whatsapp_registration->antrian->tanggal_lahir  = $tanggal;
                $this->whatsapp_registration->antrian->save();
            } else {
                $this->input_tidak_tepat = true;
            }
        } else if ( 
            isset( $this->whatsapp_registration ) &&
            !is_null( $this->whatsapp_registration->antrian ) &&
            !is_null( $this->whatsapp_registration->antrian->registrasi_pembayaran_id ) &&
            !is_null( $this->whatsapp_registration->antrian->nama ) &&
            !is_null( $this->whatsapp_registration->antrian->tanggal_lahir ) 
        ) {
            if (
                ( $this->message == 'lanjutkan' && $this->tenant->iphone_whatsapp_button_available )||
                ( $this->message == 'ulangi' && $this->tenant->iphone_whatsapp_button_available ) ||
                ( $this->message == '1' && !$this->tenant->iphone_whatsapp_button_available )||
                ( $this->message == '2' && !$this->tenant->iphone_whatsapp_button_available )
            ) {
                if (
                    ( $this->message == 'lanjutkan' && $this->tenant->iphone_whatsapp_button_available ) ||
                    ( $this->message == '1' && !$this->tenant->iphone_whatsapp_button_available )
                ) {
                    $this->whatsapp_registration_deleted = $this->whatsapp_registration->delete();
                }
                if (
                    ( $this->message == 'ulangi' && $this->tenant->iphone_whatsapp_button_available ) ||
                    ( $this->message == '2' && !$this->tenant->iphone_whatsapp_button_available )
                ) {
                    $this->ulangiRegistrasiWhatsapp();
                }
            } else {
                $input_tidak_tepat = true;
            }
        }

        if (
            isset( $this->whatsapp_registration ) &&
            !is_null($this->whatsapp_registration->antrian)
        ) {
            if (
                !is_null( $this->whatsapp_registration->antrian->nama ) ||
                !is_null( $this->whatsapp_registration->antrian->registrasi_pembayaran_id ) ||
                !is_null( $this->whatsapp_registration->antrian->tanggal_lahir )
            ) {
                $response .=  "*Uraian Pengisian Anda*";
                $response .= PHP_EOL;
                $response .= PHP_EOL;
            }
            if ( !is_null( $this->whatsapp_registration->antrian->nama ) ) {
                $response .= 'Nama Pasien: ' . ucwords($this->whatsapp_registration->antrian->nama)  ;
                $response .= PHP_EOL;
            }
            if ( !is_null( $this->whatsapp_registration->antrian->registrasi_pembayaran_id ) ) {
                $response .= 'Pembayaran : ';
                $response .= ucwords($this->whatsapp_registration->antrian->registrasiPembayaran->pembayaran);
                $response .= PHP_EOL;
            }
            if ( !is_null( $this->whatsapp_registration->antrian->tanggal_lahir ) ) {
                $response .= 'Tanggal Lahir : '.  Carbon::parse($this->whatsapp_registration->antrian->tanggal_lahir)->format('d M Y');;
                $response .= PHP_EOL;
            }
            if (
                !is_null( $this->whatsapp_registration->antrian->nama ) ||
                !is_null( $this->whatsapp_registration->antrian->registrasi_pembayaran_id) ||
                !is_null( $this->whatsapp_registration->antrian->tanggal_lahir )
            ) {
                $response .= "==================";
                $response .= PHP_EOL;
                $response .= PHP_EOL;
            }
        }

        if ( 
            !is_null($this->whatsapp_registration)
        ) {
            $payload   = $this->botKirim($this->whatsapp_registration)[0];
            $category = $payload['category'];
            $response .= $category == 'button' ? $payload['message']['content'] : $payload['message'];

            if (is_null($this->whatsapp_registration_deleted)) {
                /* $response .=  PHP_EOL; */
                /* $response .=  PHP_EOL; */
                /* $response .= "=============="; */
                /* $response .=  PHP_EOL; */
                /* $response .=  PHP_EOL; */
                /* $response .=  "Balas *ulang* apa bila ada kesalahan dan Anda akan mengulangi pertanyaan dari awal"; */
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
            try {
                $payload   = $this->botKirim($this->whatsapp_registration)[0];
            } catch (\Exception $e) {
                Log::info("botkirim null");
                Log::info("whatsapp_registration", $this->whatsapp_registration);
                Log::info('$this->message', $this->message);
                Log::info('$this->no_telp', $this->no_telp);
            }
            $category = $payload['category'];
            if ( $category == 'button' ) {
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
                /* $response .= $payload['message']; */
                /* Log::info('text'); */
                /* Log::info( $response ); */
                echo $response;
            }
        }



        // dokumentasikan kegagalan terapi

        // Cek kepuasan pelanggan

        if ( str_contains( $this->message, '(pqid' ) ) {
            $id = explode('pqid', $this->message)[1];
            $id = preg_replace('~\D~', '', $id);

            $recovery_index_id = $this->recoveryIndexConverter();
            $antrian = Antrian::find($id);
            if (
                !is_null($antrian) 
                && !$antrian->recovery_index_id
                && $antrian->no_telp == $this->no_telp
            ) {
                $antrian->recovery_index_id = $recovery_index_id;
                $antrian->save();


                // Jika pasien memilih gagal pengobatan, maka dapatkan informasi mengenai kondisi pasien
                //
                if ( $recovery_index_id == 1 ) {
                    $failed_therapy             = new FailedTherapy;
                    $failed_therapy->no_telp    = $antrian->no_telp;
                    $failed_therapy->antrian_id = $antrian->id;
                    $failed_therapy->save();

                    $message = "Mohon maaf atas ketidak nyamanannya .";
                    $message .= PHP_EOL;
                    $message .= "Bisa diinfokan kondisi saat ini?";
                    echo $message;
                    //
                // Jika pasien memilih mengalami perbaikan, ucapkan terima kasih
                //
                } else {
                    $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
                    $message .= PHP_EOL;
                    $message .= "Informasi ini akan menjadi bahan evaluasi kami";
                    echo $message;
                }
            }
        }

        // Jika pasien berada di antrian kasir
        if (
            $this->antrian &&
            $this->antrian->antriable_type == 'App\\Models\\AntrianKasir'             
        ){
            $this->saveNomorTeleponPasien();
            // kirimkan nomor rekening beserta dengan jumlah yang harus ditransfer
        } else if (
            $this->antrian &&
             $this->antrian->antriable_type == 'App\\Models\\Periksa' 
        ){
            $this->saveNomorTeleponPasien();
            // kirimkan umpan balik pelayanan
            // jika pasien sudah didaftarkan oleh admin
        } else if (
            $this->antrian &&
            $this->antrian->antriable_type != 'App\\Models\\Antrian'
        ){
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

	private function botKirim()
	{
        if (
            !is_null($this->whatsapp_registration) &&
             $this->whatsapp_registration->registering_confirmation < 1
        ) {
            $payload = [];
			$text = '*KLINIK JATI ELOK*' ;
			$text .= PHP_EOL;
			$text .= "==============";
			$text .= PHP_EOL;
			$text .= PHP_EOL;
			$text .= 'Fasilitas ini akan memproses antrian Untuk berkonsultasi ke *Dokter Umum*';
			$text .= PHP_EOL;
            if ( $this->tenant->iphone_whatsapp_button_available ) {
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
            } else {
                $text .= 'Balas *ya* untuk melanjutkan';

                $payload[] = [
                    'category' => 'text',
                    'message' => $text
                ];
            }
			return $payload;
		}
		if (
            !is_null($this->whatsapp_registration) &&
            !is_null($this->whatsapp_registration->antrian) &&
             is_null( $this->whatsapp_registration->antrian->registrasi_pembayaran_id ) 
        ) {
			$text = 'Bisa dibantu menggunakan pembayaran apa? ';
			$text = PHP_EOL;

            if ( $this->tenant->iphone_whatsapp_button_available ) {
                if (
                     $this->whatsapp_registration->antrian->jenis_antrian_id == 7 ||
                     $this->whatsapp_registration->antrian->jenis_antrian_id == 8
                ) {
                    $payment_options = [
                        'Biaya Pribadi',
                        'Lainnya'
                    ];
                } else {
                    $payment_options = [
                        'Biaya Pribadi',
                        'BPJS',
                        'Lainnya'
                    ];
                }
                $message = [
                    'buttons' => $payment_options,
                    'content' => $text,
                    'footer' => ''
                ];

                $payload[] = [
                    'category' => 'button',
                    'message' => $message
                ];
            } else {
                $text .= $this->messagePilihanPembayaran();

                $payload[] = [
                    'category' => 'text',
                    'message' => $text
                ];
            }

			return $payload;
		}
		if (
            !is_null($this->whatsapp_registration) &&
            !is_null($this->whatsapp_registration->antrian) &&
             is_null( $this->whatsapp_registration->antrian->register_previously_saved_patient ) 
        ) {
            $message = $this->pesanUntukPilihPasien();
            $payload[] = [
                'category' => 'text',
                'message'  => $message
            ];
			return $payload;
		}
		if (
            !is_null($this->whatsapp_registration) &&
            !is_null($this->whatsapp_registration->antrian) &&
             is_null( $this->whatsapp_registration->antrian->nama ) 
        ) {
            $payload[] = [
                'category' => 'text',
                'message' => 'Bisa dibantu *Nama Lengkap* pasien?'
            ];
			return $payload;
		}

		if (
            !is_null($this->whatsapp_registration) &&
            !is_null($this->whatsapp_registration->antrian) &&
             is_null( $this->whatsapp_registration->antrian->tanggal_lahir ) 
        ) {
			$text    = $this->tanyaTanggalLahirPasien();
            $message = $text;
            $payload[] = [
                'category' => 'text',
                'message'  => $message
            ];

			return $payload;
		}

		if (
            !is_null($this->whatsapp_registration) &&
            !is_null($this->whatsapp_registration->antrian) &&
            !is_null( $this->whatsapp_registration->antrian->tanggal_lahir ) &&
            is_null( $this->whatsapp_registration_deleted ) 
        ) {
            $text = 'Data anda sudah kami terima. Apakah anda ingin melanjutkan atau ulangi karena ada kesalahan input data?';
            $text .= PHP_EOL;

            if ( $this->tenant->iphone_whatsapp_button_available) {
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
            } else {
                $text .= PHP_EOL;
                $text .= '1. Lanjutkan';
                $text .= PHP_EOL;
                $text .= '2. Ulangi';
                $text .= PHP_EOL;
                $text .= PHP_EOL;
                $text .= "Balas dengan angka *1 atau 2* sesuai informasi di atas";

                $payload[] = [
                    'category' => 'text',
                    'message' => $text
                ];
            }
			return $payload;
		}

		if (
            !is_null( $this->whatsapp_registration_deleted ) 
        ) {
            $registeredWhatsapp = WhatsappRegistration::where('no_telp', $this->no_telp)
                ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
                ->get();

            $text = $this->pesanBalasanBilaTerdaftar( $this->antrian->nomor_antrian );
            if ( $registeredWhatsapp->count() ) {
                $registeredWhatsapp = $registeredWhatsapp->first();

                $text .= PHP_EOL;
                $text .= PHP_EOL;
                $text .= '=======================';
                $text .= PHP_EOL;
                $text .= 'Selanjutnya Anda akan memproses antrian ' . $registeredWhatsapp->antrian->nomor_antrian;
                $text .= PHP_EOL;
                $text .= PHP_EOL;


                if ( $this->tenant->iphone_whatsapp_button_available) {
                    $text .= 'Apakah Anda ingin melanjutkan?';
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
                } else {

                    $text .='Ketik *ya* untuk melanjutkan';
                    $payload[] = [
                        'category' => 'text',
                        'message' => $text
                    ];
                }

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
    private function satisfactionIndex($message){
        if ( $message == 3 ) {
            return 1;
        } else if ( $message == 2 ){
            return 2;
        } else if ( $message == 1 ){
            return 3;
        } else {
            return null;
        }
    }
    public function wablas2(){
        
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
    private function ulangiRegistrasiWhatsapp()
    {
        $this->whatsapp_registration->antrian->registrasi_pembayaran_id          = null;
        $this->whatsapp_registration->antrian->nama                              = null;
        $this->whatsapp_registration->antrian->tanggal_lahir                     = null;
        $this->whatsapp_registration->antrian->register_previously_saved_patient = null;
        $this->whatsapp_registration->antrian->pasien_id                         = null;
        $this->whatsapp_registration->antrian->save();
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
        $query .= "psn.nomor_asuransi_bpjs as nomor_asuransi_bpjs, ";
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
    /**
     * undocumented function
     *
     * @return void
     */
    private function recoveryIndexConverter()
    {
        if ( $this->message == '1' ) {
            return 3;
        } else if ( $this->message == '2' ){
            return 2;
        } else if ( $this->message == '3' ){
            return 1;
        }
    }
    
    private function uploadImage()
    {
        Log::info("===================");
        Log::info( Input::get('phone') );
        Log::info( Input::get('messageType') );
        Log::info( Input::get('file') );
        Log::info( Input::get('url') );
        Log::info( Input::get('message') );
        Log::info("===================");

        $url      = Input::get('url');
        $contents = file_get_contents($url);
        $name     = substr($url, strrpos($url, '/') + 1);
        Log::info("nama file yang akan disimpan");
        Log::info($name);
        $destination_path = 'image/whatsapp/';

        \Storage::disk('s3')->put($destination_path. $name, $contents);
        /* Storagel::disk('s3')->put($name, $contents); */
        /* $upload_cover = Input::get('file'); */
        /* $extension = $upload_cover->getClientOriginalExtension(); */

        /* $upload_cover = Image::make($upload_cover); */
        /* $upload_cover->resize(1000, null, function ($constraint) { */
        /* 	$constraint->aspectRatio(); */
        /* 	$constraint->upsize(); */
        /* }); */

        //membuat nama file random + extension
        /* $filename =	 'whatsapp' . '_' .  time().'.' . $extension; */

        //menyimpan bpjs_image ke folder public/img
        /* $destination_path = 'whatsapp_image'; */
        /* if (!str_ends_with('/', $destination_path)) { */
        /*     $destination_path =  $destination_path . '/'; */
        /* } */

        //destinasi s3
        //
        /* \Storage::disk('s3')->put($destination_path. $filename, file_get_contents($upload_cover)); */
        /* // Mengambil file yang di upload */

        /* /1* $upload_cover->save($destination_path . '/' . $filename); *1/ */
        
        //mengisi field bpjs_image di book dengan filename yang baru dibuat
        /* return $destination_path. $filename; */
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function registerWhatsappComplaint()
    {
        $this->whatsapp_complaint->antrian->complaint = $this->message;
        $this->whatsapp_complaint->antrian->save();
        $this->whatsapp_complaint->delete();

        $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
        $message .= PHP_EOL;
        $message .= "Keluhan atas pelayanan yang kakak rasakan akan segera kami tindak lanjuti.";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Kami berharap dapat melayani anda dengan lebih baik lagi.";
        echo $message;

    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function registerFailedTherapy()
    {
        $this->failed_therapy->antrian->informasi_terapi_gagal = $this->message;
        $this->failed_therapy->antrian->save();

        $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
        $message .= PHP_EOL;
        $message .= "Informasi ini akan menjadi bahan evaluasi kami";

        $this->failed_therapy->delete();
        echo $message;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function registerWhatsappSatisfactionSurvey()
    {
        if (
            $this->message == '1' ||
            $this->message == '2' ||
            $this->message == '3'
        ) {
            $satisfaction_index_ini = $this->satisfactionIndex( $this->message );
            $no_telp = $this->whatsapp_satisfaction_survey->antrian->no_telp;

            Antrian::where('no_telp', $no_telp)
                    ->where('created_at', 'like', $this->whatsapp_satisfaction_survey->created_at->format('Y-m-d') . '%')
                    ->update([
                        'satisfaction_index' => $satisfaction_index_ini
                    ]);
            
            if( $this->message == '1' ){
                echo $this->kirimkanLinkGoogleReview();
            } else if( $this->message == '3' ){

                $complaint             = new WhatsappComplaint;
                $complaint->no_telp    = $this->whatsapp_satisfaction_survey->antrian->no_telp;
                $complaint->antrian_id = $this->whatsapp_satisfaction_survey->antrian->id;
                $complaint->save();

                WhatsappRegistration::where('no_telp', $this->whatsapp_satisfaction_survey->antrian->no_telp)->delete();

                $message = "Mohon maaf atas ketidak nyamanan yang kakak alami.";
                $message .= PHP_EOL;
                $message .= "Bisa diinfokan kendala yang kakak alami?";
                echo $message;
            } else {
                $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
                $message .= PHP_EOL;
                $message .= "kami berharap dapat melayani anda dengan lebih baik lagi.";
                echo $message;
            }
            $this->whatsapp_satisfaction_survey->delete();
        } else {
            $message = "Balasan yang anda masukkan tidak dikenali";
            $message .= PHP_EOL;
            $message .= "1. Puas";
            $message .= PHP_EOL;
            $message .= "2. Biasa";
            $message .= PHP_EOL;
            $message .= "3. Tidak Puas";
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= "Mohon balas dengan angka *1,2 atau 3* sesuai urutan diatas";
            echo $message;
        }
    }
    private function registerWhatsappRecoveryIndex(){
        if (
            $this->message == '1' ||
            $this->message == '2' ||
            $this->message == '3'
        ) {
            $recovery_index_ini = $this->recoveryIndexConverter( $this->message );
            $this->whatsapp_recovery_index->antrian->recovery_index_id = $recovery_index_ini; 
            $this->whatsapp_recovery_index->antrian->save();
            
            if( $this->message == '3' ){

                $fail             = new FailedTherapy;
                $fail->no_telp    = $this->no_telp;
                $fail->antrian_id = $this->whatsapp_recovery_index->antrian->id;
                $fail->save();

                $message = "Mohon maaf atas ketidak nyamanan yang kakak alami.";
                $message .= PHP_EOL;
                $message .= "Bisa diinfokan kondisi pasien saat ini?";
                echo $message;
            } else {
                $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
                $message .= PHP_EOL;
                $message .= "Informasi ini akan menjadi bahan evaluasi kami";
                echo $message;
            }
            $this->whatsapp_recovery_index->delete();
        } else {
            $message = "Balasan yang anda masukkan tidak dikenali";
            $message .= PHP_EOL;
            $message .= "1. Sudah Sembuh";
            $message .= PHP_EOL;
            $message .= "2. Membaik";
            $message .= PHP_EOL;
            $message .= "3. Tidak ada perubahan";
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= "Mohon agar membalas dengan angka *1,2 atau 3* sesuai urutan diatas";
            echo $message;
        }
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function registerKuesionerMenungguObat()
    {
        if (
            $this->message == '1' ||
            $this->message == '2'
        ) {
            $menunggu_ini = $this->menungguConverter( $this->message );
            $this->kuesioner_menunggu_obat->antrian->menunggu = $menunggu_ini; 
            $this->kuesioner_menunggu_obat->antrian->save();
            $this->kuesioner_menunggu_obat->delete();
            
            $message = 'Kakak akan kami infokan apabila obat telah selesai diracik';
            $message .= PHP_EOL;
            $message .= 'Meracik obat membutuhkan ketekunan dan ketelitian yang tinggi';
            $message .= PHP_EOL;
            $message .= 'Terima kasih untuk tidak membuat kami terburu-buru dalam meracik obat';
            echo $message;
        } else {
            $message = "Balasan yang anda masukkan tidak dikenali";
            $message .= PHP_EOL;
            $message .= '1. Saya Menunggu saja  di klinik';
            $message .= PHP_EOL;
            $message .= '2. Saya pergi dulu nanti kabari saya kalau obat sudah selesai diracik';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= "Balas dengan angka *1 atau 2* sesuai informasi diatas";
            echo $message;
        }
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function menungguConverter($message)
    {
        if ( $message == '2' ) {
            return 0;
        }
        return $message;

    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function createWhatsappMainMenu(){

        if ( $this->pasienBelumSelesaiBerobat() ) {
            $message = '*Klinik Jati Elok*';
            $message .= PHP_EOL;
            $message .= '=====================================';
            $message .= PHP_EOL;
            $message .= 'Selamat Datang di Klinik Jati Elok';
            $message .= PHP_EOL;
            $message .= 'Pesan ini adalah pesan robot';
            $message .= PHP_EOL;
            $message .= $this->messageWhatsappMainMenu();

            WhatsappMainMenu::create([
                'no_telp' => $this->no_telp
            ]);

            echo $message;
        }
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function registerWhatsappMainMenu()
    {
        if (
            $this->message == '1'
        ) {
            if ( $this->message == '1' ) {
                WhatsappBpjsDentistRegistration::create([
                    'no_telp' => $this->no_telp
                ]);
                echo $this->pertanyaanPertamaWaBpjsDentistRegistration();
            }
        } else {
            $message = "Balasan yang kakak masukkan tidak dikenali";
            $message .= $this->messageWhatsappMainMenu();

            echo $message;
        }
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function messageWhatsappMainMenu()
    {
        $message = 'Beritahu kami apa yang dapat kami bantu';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= '1. Buat perjanjian Dokter Gigi';
        $message .= PHP_EOL;
        $message .= '2. Jadwal Dokter';
        $message .= PHP_EOL;
        $message .= '3. Jadwal Dokter Gigi';
        $message .= PHP_EOL;
        $message .= '4. Jadwal Bidan';
        $message .= PHP_EOL;
        $message .= '5. Saya ingin memberikan masukan dan saran';
        $message .= PHP_EOL;
        $message .= '6. Saya ingin berbicara dengan admin';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Balas dengan *1* sesuai dengan urutan di atas';
        return $message;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function messagePilihanPembayaran()
    {
        $text = PHP_EOL;
        $text .= "1. Biaya Pribadi";
        $text .= PHP_EOL;
        $text .= "2. BPJS";
        $text .= PHP_EOL;
        $text .= "3. Lainnya";
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= "Balas dengan angka *1,2 atau 3* sesuai informasi di atas";
        return $text;
    }
    
    /**
     * undocumented function
     *
     * @return void
     */
    private function registerWhatsappBpjsDentistRegistration()
    {

        if ( 
            is_null($this->whatsapp_bpjs_dentist_registrations->registrasi_pembayaran_id)
        ) {
            Log::info(1208);
            if (
                 $this->message == '1' ||
                 $this->message == '2' ||
                 $this->message == '3'
            ) {
                Log::info(1213);
                $this->whatsapp_bpjs_dentist_registrations->registrasi_pembayaran_id = $this->message;
                $this->whatsapp_bpjs_dentist_registrations->save();

                $message = $this->tanyaKetersediaanSlot();
                Log::info(1218);
                echo $message;
            } 
        } else if ( 
            is_null($this->whatsapp_bpjs_dentist_registrations->tanggal_booking)
        ) {
            Log::info(1222);
            $slots = $this->queryKetersediaanSlotBpjsSatuMingguKeDepan();
            if (
                $this->message > 0 &&
                $this->message <= count( $slots )
            ) {
                Log::info(1228);
                $this->whatsapp_bpjs_dentist_registrations->tanggal_booking = $slots[ $this->message -1 ]['tanggal'];

                $data = $this->queryPreviouslySavedPatientRegistry();
                if (count($data) < 1) {
                    $this->whatsapp_bpjs_dentist_registrations->register_previously_saved_patient = 0;
                    if ( $this->whatsapp_bpjs_dentist_registrations->registrasi_pembayaran_id == 2  ) {  // jika pasien BPJS
                        $message = $this->tanyaNomorBpjsPasien();
                    } else {
                        $this->whatsapp_bpjs_dentist_registrations->nomor_asuransi_bpjs == 0  ;
                        $message = $this->tanyaNamaLengkapPasien();
                    }
                } else {
                    $message = $this->pesanUntukPilihPasien();
                }

                $this->whatsapp_bpjs_dentist_registrations->save();
            } else {
                Log::info(1240);
                $message = 'Input yang kakak masukkan tidak dikenali';
                $message .= PHP_EOL;
                $message .= $this->tanyaKetersediaanSlot();
            }
            echo $message;
        } else if ( 
            is_null($this->whatsapp_bpjs_dentist_registrations->register_previously_saved_patient)
        ) {
            Log::info(1249);
            $data = $this->queryPreviouslySavedPatientRegistry();
            $dataCount = count($data);
            if ( (int)$this->message <= $dataCount && (int)$this->message > 0  ) {
                Log::info(1253);
                $this->whatsapp_bpjs_dentist_registrations->register_previously_saved_patient = $this->message;
                $this->whatsapp_bpjs_dentist_registrations->pasien_id                         = $data[ (int)$this->message -1 ]->pasien_id;
                $this->whatsapp_bpjs_dentist_registrations->nama                              = $data[ (int)$this->message -1 ]->nama;
                $this->whatsapp_bpjs_dentist_registrations->tanggal_lahir                     = $data[ (int)$this->message -1 ]->tanggal_lahir;
                if ( $this->whatsapp_bpjs_dentist_registrations->registrasi_pembayaran_id == 2 ) {
                    $this->whatsapp_bpjs_dentist_registrations->nomor_asuransi_bpjs = $data[ (int)$this->message -1 ]->nomor_asuransi_bpjs;
                } else {
                    $this->whatsapp_bpjs_dentist_registrations->nomor_asuransi_bpjs = 0;
                }
                echo $this->konfirmasiSebelumDisimpanDiAntrianPoli();
            } else {
                Log::info(1260);
                $this->whatsapp_bpjs_dentist_registrations->register_previously_saved_patient = $this->message;
                echo $this->tanyaNomorBpjsPasien();
            }
            $this->whatsapp_bpjs_dentist_registrations->save();
        } else if ( 
            is_null($this->whatsapp_bpjs_dentist_registrations->nomor_asuransi_bpjs)
        ) {
            $message = '';
            if (
                $this->nomorAsuransiBpjsValid( $this->message )
            ) {
                Log::info(1307);
                $this->whatsapp_bpjs_dentist_registrations->nomor_asuransi_bpjs  = $this->message;
                $pasien_bpjs =  Pasien::where('nomor_asuransi_bpjs', $this->message )->first() ;
                if ( $pasien_bpjs ) {
                    $this->whatsapp_bpjs_dentist_registrations->pasien_id     = $pasien_bpjs->id;
                    $this->whatsapp_bpjs_dentist_registrations->nama          = $pasien_bpjs->nama;
                    $this->whatsapp_bpjs_dentist_registrations->tanggal_lahir = $pasien_bpjs->tanggal_lahir;
                }
                $this->whatsapp_bpjs_dentist_registrations->save();
                $message .= $this->tanyaNamaLengkapPasien();
            } else {
                Log::info(1311);
                $message = 'Input yang kakak masukkan salah';
                $message .= PHP_EOL;
                $message = 'Nomor Asuransi BPJS terdiri dari 13 angka';
                $message .= PHP_EOL;
                $message .= PHP_EOL;
                $message .= $this->tanyaNomorBpjsPasien();
            }
            echo $message;
        } else if ( 
            is_null($this->whatsapp_bpjs_dentist_registrations->nama)
        ) {
            Log::info(1268);
            if (
                $this->namaLengkapValid($this->message)
            ) {
                Log::info(1272);
                $this->whatsapp_bpjs_dentist_registrations->nama  = ucwords($this->message);
                $this->whatsapp_bpjs_dentist_registrations->save();
                echo $this->tanyaTanggalLahirPasien();
            } else {
                Log::info(1277);
                $message = 'Input yang kakak masukkan tidak tepat';
                $message .= PHP_EOL;
                $message .= $this->tanyaNamaLengkapPasien();
                echo $message;
            }
        } else if ( 
            is_null($this->whatsapp_bpjs_dentist_registrations->tanggal_lahir)
        ) {
            Log::info(1286);
            $tanggal = $this->convertToPropperDate();
            $message = '';
            if (
                !is_null( $tanggal )
            ) {
                Log::info(1291);
                $this->whatsapp_bpjs_dentist_registrations->tanggal_lahir  = $tanggal;
                $this->whatsapp_bpjs_dentist_registrations->save();
                $message .= $this->konfirmasiSebelumDisimpanDiAntrianPoli();
            } else {
                Log::info(1295);
                $message .= 'Input yang kakak masukkan tidak dikenali';
                $message .= PHP_EOL;
                $message .= $this->tanyaTanggalLahirPasien();
            }
            echo $message;
        } else if ( 
            !$this->whatsapp_bpjs_dentist_registrations->data_konfirmation
        ) {
            Log::info(1324);
            if (
                $this->message == '1' ||
                $this->message == '2'
            ) {
                Log::info(1328);
                if (
                    $this->message == '1'
                ) {
                    Log::info(1332);
                    $this->whatsapp_bpjs_dentist_registrations->data_konfirmation  = 1;
                    $this->whatsapp_bpjs_dentist_registrations->save();
                    $this->masukkanDiAntrianPoli();
                } else if ( 
                    $this->message == '2'
                ) {
                    Log::info(1338);
                    WhatsappBpjsDentistRegistration::create([
                        'no_telp' => $this->no_telp
                    ]);
                    echo $this->pertanyaanPertamaWaBpjsDentistRegistration();
                }
            }

        }
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function pesanUntukPilihPasien()
    {
        $data = $this->queryPreviouslySavedPatientRegistry();
        $message = 'Pilih pasien yang akan didaftarkan';
        $message .= PHP_EOL;
        $message .= PHP_EOL;

        $balas_dengan = 'Balas dengan angka ';
        $balas_dengan .= '*';
        foreach ($data as $key => $d) {
            $number = $key + 1;
            $message .= $number . '. ' . ucwords($d->nama);
            $message .= PHP_EOL;

            $urutan = (string) $key + 1;
            if ($key == 0) {
                $balas_dengan .= $urutan;
            } else {
                $balas_dengan .= ', ' . $urutan;
            }
        }
        $nomor_lainnya = count($data) + 1;
        $message .= $nomor_lainnya. ". Lainnya ";

        $balas_dengan .= ' atau ' . $nomor_lainnya . '*'; 
        $balas_dengan .= ' sesuai urutan di atas';

        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= $balas_dengan;
        return $message;
    }

    /**
     * undocumented function
     *
     * @return void
     */
    private function tanyaNamaLengkapPasien()
    {
         return 'Bisa dibantu *Nama Lengkap* pasien?';
    }
    
    /**
     * undocumented function
     *
     * @return void
     */
    private function namaLengkapValid($string)
    {
        if (preg_match('~[0-9]+~', $string)) {
            return false;
        }
        return true;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function convertToPropperDate(){
        $tanggals = [];
        if ( str_contains( $this->message, "." ) ){
            $tanggals = explode(".", $this->message );
        } else if ( str_contains( $this->message, "_" ) ){
            $tanggals = explode("_", $this->message );
        } else if ( str_contains( $this->message, "/" ) ){
            $tanggals = explode("/", $this->message );
        } else if ( str_contains( $this->message, "-" ) ){
            $tanggals = explode("-", $this->message );
        } else {
            $tanggals = explode(" ", $this->message );
        }

        if ( $this->validateDate($this->message, $format = 'd-m-Y') ) {
            return Carbon::CreateFromFormat('d-m-Y',$this->message)->format('Y-m-d');
        } else if(
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
                 trim($bulan) == 'jan' ||
                 trim($bulan) == '01' ||
                 trim($bulan) == '1'
            ){
                $bulan = '01';
            } else if(
                trim($bulan) == 'februari' || 
                trim($bulan) == 'feb' ||
                trim($bulan) == '02' ||
                trim($bulan) == '2'
            ){
                $bulan = '02';
            } else if(
                trim($bulan) == 'maret' || 
                trim($bulan) == 'mar' ||
                trim($bulan) == '03' ||
                trim($bulan) == '3'
            ){
                $bulan = '03';
            } else if(
                trim($bulan) == 'april' || 
                trim($bulan) == 'apr' ||
                trim($bulan) == '04' ||
                trim($bulan) == '4'

            ){
                $bulan = '04';
            } else if( 
                trim($bulan) == 'mei'  ||
                trim($bulan) == '05' ||
                trim($bulan) == '5'

            ){
                $bulan = '05';
            } else if(
                trim($bulan) == 'juni' || 
                trim($bulan) == 'jun' ||
                trim($bulan) == '06' ||
                trim($bulan) == '6'

            ){
                $bulan = '06';
            } else if(
                trim($bulan) == 'juli' || 
                trim($bulan) == 'jul' ||
                trim($bulan) == '07' ||
                trim($bulan) == '7'

            ){
                $bulan = '07';
            } else if(
                trim($bulan) == 'agustus' ||
                trim($bulan) == '08' ||
                trim($bulan) == '8'
            ){
                $bulan = '08';
            } else if(
                trim($bulan) == 'september' || 
                trim($bulan) == 'sept' || 
                trim($bulan) == 'sep' ||
                trim($bulan) == '09' ||
                trim($bulan) == '9'

            ){
                $bulan = '09';
            } else if(
                 trim($bulan) == 'oktober' ||
                 trim($bulan) == 'okt' ||
                trim($bulan) == '10'

            ){
                $bulan = 10;
            } else if(
                trim($bulan) == 'november' || 
                trim($bulan) == 'nopember' || 
                trim($bulan) == 'nov' ||
                trim($bulan) == '11'

            ){
                $bulan = 11;
            } else if(
                trim($bulan) == 'desember' || 
                trim($bulan) == 'des' ||
                trim($bulan) == '12'

            ){
                $bulan = 12;
            }


            if ( (int) $tahun < 100 ) {
               if ( $tahun <= date('y') ) {
                   $tahun = '20' . $tahun;
               } else {
                   $tahun = '19' . $tahun;
               }
            }

            $databaseFriendlyDateFormat = $tahun . '-' . $bulan . '-' . $tanggal;
            try {
                Carbon::parse($databaseFriendlyDateFormat);
                return $databaseFriendlyDateFormat;
            } catch (\Exception $e) {
                return null;
            }
        } else {
            return null;
        }
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function tanyaTanggalLahirPasien()
    {
        return 'Bisa dibantu *Tanggal Lahir* pasien? ' . PHP_EOL . PHP_EOL . 'Contoh : 19-07-2003';
    }

    private function tanyaNomorBpjsPasien()
    {
        return 'Bisa dibantu *Nomor Asuransi BPJS* pasien?';
    }

    /**
     * undocumented function
     *
     * @return void
     */
    private function nomorAsuransiBpjsValid($nomor)
    {
        if(
            !preg_match('#[^0-9]#',$nomor) &&
            strlen($nomor) == 13
        ) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * undocumented function
     *
     * @return void
     */
    public function queryKetersediaanSlotBpjsSatuMingguKeDepan()
    {
        $hari_ini   = date('Y-m-d');
        $bulan_ini  = date('Y-m');
        $query      = "SELECT tanggal ";
        $query     .= "FROM antrian_polis ";
        $query     .= "WHERE tanggal like '{$bulan_ini}%' ";
        $query     .= "AND poli_id = 4;";
        $data       = DB::select($query);

        $result = [];
        $tenant = Tenant::find( 1 );
        foreach ($this->semingguKeDepan() as $hari) {
            if ( !isset( $result[$hari] )) {
                $result[$hari]['kuota'] = $tenant->kuota_gigi_bpjs;
                $result[$hari]['tanggal'] = $hari;
            }
            foreach ($data as $d) {
                if ($d->tanggal == $hari) {
                    $result[$hari]['kuota']--;
                }
            }
        }

        $data = [];
        foreach ($result as $r) {
            $data[] = $r;
        }
        return $data;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    public function semingguKeDepan()
    {
        $timestamp = strtotime('yesterday');
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = strftime('%Y-%m-%d', $timestamp);
            $timestamp = strtotime('+1 day', $timestamp);
        }
        return $days;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function tanyaKetersediaanSlot()
    {
        Log::info(1636);
        $slots = $this->queryKetersediaanSlotBpjsSatuMingguKeDepan();
        Log::info( $slots );
        $balas_dengan = 'Balas dengan angka ';
        $message = 'Pilih sesuai ketersediaan hari 1 minggu ke depan';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $nomor = 0;
        foreach ($slots as $k => $s) {
            $nomor++;
            if ($nomor == 1) {
                $balas_dengan .= $nomor;
            } else if ( $nomor == count($slots)  ){
                $balas_dengan .= ' atau '.$nomor;
            } else {
                $balas_dengan .= ', '.$nomor;
            }
            $message .= $nomor . '. ' . Carbon::parse($s['tanggal'])->format('d M Y') . ' (Masih tersedia untuk ' . $s['kuota'] . ' pasien)';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
        }
        $message .= $balas_dengan;
        Log::info(1657);
        return $message;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function pertanyaanPertamaWaBpjsDentistRegistration()
    {
        $message = 'Bisa dibantu menggunakan pembayaran apa?';
        $message .= PHP_EOL;
        $message .= $this->messagePilihanPembayaran();
        return $message;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function konfirmasiSebelumDisimpanDiAntrianPoli()
    {
        if (
            !is_null( $this->whatsapp_bpjs_dentist_registrations->nama ) ||
            !is_null( $this->whatsapp_bpjs_dentist_registrations->registrasi_pembayaran_id ) ||
            !is_null( $this->whatsapp_bpjs_dentist_registrations->nomor_asuransi_bpjs ) ||
            !is_null( $this->whatsapp_bpjs_dentist_registrations->tanggal_lahir )
        ) {
            $response =  "*Uraian Pengisian Anda*";
            $response .= PHP_EOL;
            $response .= PHP_EOL;
        }
        if ( !is_null( $this->whatsapp_bpjs_dentist_registrations->nama ) ) {
            $response .= 'Nama Pasien: ' . ucwords($this->whatsapp_bpjs_dentist_registrations->nama)  ;
            $response .= PHP_EOL;
        }
        if ( !is_null( $this->whatsapp_bpjs_dentist_registrations->registrasi_pembayaran_id ) ) {
            $response .= 'Pembayaran : ';
            $response .= ucwords($this->whatsapp_bpjs_dentist_registrations->registrasiPembayaran->pembayaran);
            $response .= PHP_EOL;
        }
        if ( $this->whatsapp_bpjs_dentist_registrations->nomor_asuransi_bpjs ) {
            $response .= 'Nomor BPJS : '.  $this->whatsapp_bpjs_dentist_registrations->nomor_asuransi_bpjs;
            $response .= PHP_EOL;
        }
        if ( !is_null( $this->whatsapp_bpjs_dentist_registrations->tanggal_lahir ) ) {
            $response .= 'Tanggal Lahir : '.  Carbon::parse($this->whatsapp_bpjs_dentist_registrations->tanggal_lahir)->format('d M Y');;
            $response .= PHP_EOL;
        }
        if (
            !is_null( $this->whatsapp_bpjs_dentist_registrations->nama ) ||
            !is_null( $this->whatsapp_bpjs_dentist_registrations->registrasi_pembayaran_id) ||
            !is_null( $this->whatsapp_bpjs_dentist_registrations->nomor_asuransi_bpjs ) ||
            !is_null( $this->whatsapp_bpjs_dentist_registrations->tanggal_lahir )
        ) {
            $response .= "==================";
            $response .= PHP_EOL;
            $response .= PHP_EOL;
        }

        $response .= "1. Lanjutkan";
        $response .= PHP_EOL;
        $response .= "2. Ulangi";
        $response .= PHP_EOL;
        $response .= PHP_EOL;
        $response .= "Mohon balas dengan angka *1 atau 2* sesuai urutan diatas";
        echo $response;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function masukkanDiAntrianPoli()
    {
        if ( !is_null( $this->whatsapp_bpjs_dentist_registrations->pasien_id ) ) {
            AntrianPoli::create([
                'pasien_id'        => $this->whatsapp_bpjs_dentist_registrations->pasien_id,
                'asuransi_id'      => $this->whatsapp_bpjs_dentist_registrations->asuransi_id,
                'poli_id'          => 4,
                'staf_id'          => null,
                'tanggal'          => $this->whatsapp_bpjs_dentist_registrations->tanggal_booking,
                'jam'              => $this->whatsapp_bpjs_dentist_registrations->updated_at,
                'kecelakaan_kerja' => 0,
                'self_register'    => 0,
                'bukan_peserta'    => 0,
                'submitted'        => 1,
                'tenant_id'        => 1
            ]);
        } else {
            DentistReservation::create([
                'no_telp'                  => $this->whatsapp_bpjs_dentist_registrations->no_telp,
                'registrasi_pembayaran_id' => $this->whatsapp_bpjs_dentist_registrations->registrasi_pembayaran_id,
                'tanggal_booking'          => $this->whatsapp_bpjs_dentist_registrations->tanggal_booking,
                'nama'                     => $this->whatsapp_bpjs_dentist_registrations->nama,
                'tanggal_lahir'            => $this->whatsapp_bpjs_dentist_registrations->tanggal_lahir,
                'nomor_asuransi_bpjs'      => $this->whatsapp_bpjs_dentist_registrations->nomor_asuransi_bpjs
            ]);
        }
        $this->whatsapp_bpjs_dentist_registrations->delete();
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function pasienBelumSelesaiBerobat()
    {
        return null;
    }
    
}
