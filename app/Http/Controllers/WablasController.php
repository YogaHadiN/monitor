<?php 
namespace App\Http\Controllers; 
use Illuminate\Http\Request; 
use App\Models\AntrianPoli;
use App\Models\WhatsappInbox;
use App\Models\Complain;
use App\Models\DentistReservation;
use App\Events\FormSubmitted;
use App\Events\GambarSubmitted;
use App\Models\ReservasiOnline;
use App\Models\Antrian;
use App\Models\CekListDikerjakan;
use App\Models\Tenant;
use App\Models\JenisAntrian;
use App\Models\WhatsappRegistration;
use App\Models\KonsultasiEstetikOnline;
use App\Models\WhatsappBot;
use App\Models\Asuransi;
use App\Models\Poli;
use App\Models\AntrianPeriksa;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Alignment\LabelAlignmentLeft;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Label\Label;

use App\Models\WhatsappBotService;
use App\Models\WhatsappSatisfactionSurvey;
use App\Models\WhatsappRecoveryIndex;
use App\Models\KuesionerMenungguObat;
use App\Models\WhatsappMainMenu;
use App\Models\WhatsappJadwalKonsultasiInquiry;
use App\Models\WhatsappBpjsDentistRegistration;
use App\Models\WhatsappComplaint;
use App\Models\CekListRuangan;
use App\Models\CekListHarian;
use App\Models\CekListMingguan;
use App\Models\FailedTherapy;
use App\Models\Periksa;
use App\Models\Pasien;
use App\Models\User;
use App\Http\Controllers\AntrianPolisController;
use App\Http\Controllers\BpjsApiController;
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
	public $pesan_error;
	public $failed_therapy;
	public $whatsapp_bot;
	public $no_telp;
	public $message;
    public $whatsapp_satisfaction_survey;
    public $whatsapp_bpjs_dentist_registrations;

	public function __construct()
	{
		if (
            !is_null(Input::get('phone')) &&
            !Input::get('isFromMe') 
		 /* !is_null(Input::get('message')) */
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
    public function webhook2(){
        /**
         * for auto reply or bot with multiple message. currently only supports text and images
         */
        $content = json_decode(file_get_contents('php://input'), true);

        // reply with image message
        $payload[] = [
            'category' => 'image',
            'caption' => 'caption image',
            'urlFile' => \Storage::disk('s3')->url('image/online_reservation/qr_code/A167.png')
        ];

        $response = json_encode(['data' => $payload]);

        return response()->json([
            'status' => true,
            'data'   => $payload
        ])->header('Content-Type', 'application/json');
    }
    
	
	public function webhook(){
        header('Content-Type: application/json');
        if ( $this->message == 'daftar' ) {
            echo $this->registrasiAntrianOnline();
            return false;
        } else if (
            !is_null( $this->message ) &&
            $this->message            !== '1' &&
            $this->message            !== '2' &&
            $this->message            !== '3' &&
            $this->message            !== '4' &&
            $this->message            !== '5' &&
            $this->message            !== 'ya' &&
            $this->message            !== 'batalkan' &&
            Input::get('messageType') == 'text'
        ) {
            WhatsappInbox::create([
                'message' => $this->message,
                'no_telp' => $this->no_telp
            ]);
        }

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

        $this->whatsapp_bot = WhatsappBot::where('no_telp', $this->no_telp)
                                 ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
                                 ->first();
        $this->tenant = Tenant::find(1);

        if (
            !is_null( $this->no_telp ) &&
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
            } else if (!is_null( $this->whatsapp_bpjs_dentist_registrations  )) {
                return $this->registerWhatsappBpjsDentistRegistration(); //register untuk survey kesembuhan pasien
            } else if ( $this->whatsappMainMenuExists() ) { // jika main menu ada
                return $this->prosesMainMenuInquiry(); // proses pertanyaan main menu
            } else if ( $this->cekListBulananExists() ) { // Jika ada cek list bulanan
                return $this->prosesCekListBulanan(); // proses cek list bulanan
            } else if ( $this->cekListBulananInputExists() ) { // Jika ada cek list bulanan
                return $this->prosesCekListBulananInput(); // proses cek list bulanan
            } else if ( $this->cekListMingguanExists() ) { // Jika ada cek list bulanan
                return $this->prosesCekListMingguan(); // proses cek list bulanan
            } else if ( $this->cekListMingguanInputExists() ) { // Jika ada cek list bulanan
                return $this->prosesCekListMingguanInput(); // proses cek list bulanan
            } else if ( $this->cekListHarianExists() ) { // Jika ada cek list harian
                return $this->prosesCekListHarian(); // proses cek list harian
            } else if ( $this->cekListHarianInputExists() ) { // Jika ada cek list harian
                return $this->prosesCekListHarianInput(); // proses cek list harian
            } else if ( $this->whatsappJadwalKonsultasiInquiryExists() ) { //
                return $this->balasJadwalKonsultasi(); // proses pertanyaan jadwal konsulasi
            } else if ( $this->whatsappKonsultasiEstetikExists() ) {
                return $this->prosesKonsultasiEstetik(); // buat main menu
            } else if ( $this->batalkanAntrianExists() ) {
                return $this->batalkanAntrian(); // buat main menu
            } else if ( $this->bpjsNumberInfomationInquiryExists() ) {
                return $this->prosesBpjsNumberInquiry(); // buat main menu
            } else if ( $this->whatsappAntrianOnlineExists() ) {
                return $this->prosesAntrianOnline(); // buat main menu
            } else if ( $this->whatsappGambarPeriksaExists() ) {
                return $this->prosesGambarPeriksa(); // buat main menu
            } else if ( $this->noTelpAdaDiAntrianPeriksa() ) {
                return $this->updateNotifikasPanggilanUntukAntrian(); // notifikasi untuk panggilan
            } else if( $this->pasienTidakDalamAntrian() ) {
                return $this->createWhatsappMainMenu(); // buat main menu
            }
        }   
	}
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
            $this->ulangiRegistrasiWhatsapp($this->whatsapp_registration->antrian);
        } else if ( 
            isset( $this->whatsapp_registration ) &&
            $this->whatsapp_registration->registering_confirmation < 1
        ){
            if (
                ( $this->message == 'lanjutkan' && $this->tenant->iphone_whatsapp_button_available ) ||
                ( $this->message == 'ya' && !$this->tenant->iphone_whatsapp_button_available ) ||
                ( $this->message == 'iya' && !$this->tenant->iphone_whatsapp_button_available ) ||
                ( $this->message == 'iy' && !$this->tenant->iphone_whatsapp_button_available ) ||
                ( $this->message == 'y' && !$this->tenant->iphone_whatsapp_button_available )
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
                $this->validasiRegistrasiPembayaran()
            ) {
                $this->lanjutkanRegistrasiPembayaran($this->whatsapp_registration->antrian);
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
                Log::info("================");
                Log::info(295);
                Log::info( $this->message );
                Log::info("================");
                $pasien = Pasien::find( $data[ (int) $this->message -1 ]->pasien_id );
                $this->whatsapp_registration->antrian->register_previously_saved_patient  = $this->message;
                $this->whatsapp_registration->antrian->pasien_id                          = $pasien->id;
                $this->whatsapp_registration->antrian->nama                               = $pasien->nama;
                $this->whatsapp_registration->antrian->tanggal_lahir                      = $pasien->tanggal_lahir;

                if (
                    $this->whatsapp_registration->antrian->registrasi_pembayaran_id == 2 && // jika pembayaran BPJS
                    !empty($pasien->bpjs_image)  // dan gambar kartu BPJS tidak kosong
                ) {
                    $this->whatsapp_registration->antrian->kartu_asuransi_image = $pasien->bpjs_image;
                    $this->whatsapp_registration->antrian->nomor_bpjs           = $pasien->nomor_asuransi_bpjs;
                } else if ( 
                    $this->whatsapp_registration->antrian->registrasi_pembayaran_id == 1
                ) {
                    /* $ap = new AntrianPolisController; */
                    /* $ap->input_pasien_id           = $pasien->id; */
                    /* $ap->input_asuransi_id         = Asuransi::BiayaPribadi()->id; */
                    /* $ap->input_poli_id             = Poli::konsultasiDokterUmum()->id; */
                    /* $ap->input_staf_id             = null; */
                    /* $ap->input_tanggal             = $this->whatsapp_registration->antrian->created_at; */
                    /* $ap->input_bukan_peserta       = 0; */
                    /* $ap->processData(); */
                }
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
            !is_null( $this->whatsapp_registration->antrian->tanggal_lahir ) &&
            is_null( $this->whatsapp_registration->antrian->kartu_asuransi_image )
        ) {
            if ( Input::get('messageType') == 'image' ) {
                $this->whatsapp_registration->antrian->kartu_asuransi_image = $this->uploadImage(); 
                $this->whatsapp_registration->antrian->save();
            } else {
                $this->input_tidak_tepat = true;
            }

        } else if ( 
            isset( $this->whatsapp_registration ) &&
            !is_null( $this->whatsapp_registration->antrian ) &&
            !is_null( $this->whatsapp_registration->antrian->registrasi_pembayaran_id ) &&
            !is_null( $this->whatsapp_registration->antrian->nama ) &&
            !is_null( $this->whatsapp_registration->antrian->tanggal_lahir ) &&
            !is_null( $this->whatsapp_registration->antrian->kartu_asuransi_image )
        ) {
            if (
                ( $this->message == 'lanjutkan' && $this->tenant->iphone_whatsapp_button_available )||
                ( $this->message == 'ulangi' && $this->tenant->iphone_whatsapp_button_available ) ||
                (  !is_null( $this->message ) && $this->message[0] == '1' && !$this->tenant->iphone_whatsapp_button_available )||
                (  !is_null( $this->message ) && $this->message[0] == '2' && !$this->tenant->iphone_whatsapp_button_available )
            ) {
                if (
                    ( $this->message == 'lanjutkan' && $this->tenant->iphone_whatsapp_button_available ) ||
                    (  !is_null( $this->message ) && $this->message[0] == '1' && !$this->tenant->iphone_whatsapp_button_available )
                ) {
                    $whatsapp_registration_id = $this->whatsapp_registration->id;
                    $this->whatsapp_registration_deleted = $this->whatsapp_registration->delete();
                    //jika pasien_id tidak kosong, maka pasien langsung masuk saja ke antrianpoli
                    //==========================================================================
                }
                if (
                    ( $this->message == 'ulangi' && $this->tenant->iphone_whatsapp_button_available ) ||
                    (  !is_null( $this->message ) && $this->message[0] == '2' && !$this->tenant->iphone_whatsapp_button_available )
                ) {
                    $this->ulangiRegistrasiWhatsapp($this->whatsapp_registration->antrian);
                }
            } else {
                $input_tidak_tepat = true;
            }
        }

        if (
            isset( $this->whatsapp_registration ) &&
            !is_null($this->whatsapp_registration->antrian)
        ) {
            $response .= $this->uraianPengisian($this->whatsapp_registration->antrian);
        }

        if ( 
            !is_null($this->whatsapp_registration)
        ) {
            $botKirim = $this->botKirim($this->whatsapp_registration);
            if (!is_null( $botKirim )) {
                $payload   = $botKirim[0];
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
                    $response .=  $this->samaDengan();
                    $response .= PHP_EOL;
                    $response .= '```Input yang anda masukkan salah```';
                    $response .= PHP_EOL;
                    $response .= '```Mohon Diulangi```';
                    $response .= PHP_EOL;
                }
                $input_tidak_tepat = false;
            } else {
                echo 'Antrian kakak sudah berhasil kami daftarkan';
            }
        }

        if (!empty($response)) {
            try {
                $payload   = $this->botKirim($this->whatsapp_registration)[0];
            } catch (\Exception $e) {
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
            echo $this->pesanBalasanBilaTerdaftar( $this->antrian );
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
            !$this->whatsapp_registration_deleted &&
             $this->whatsapp_registration->registering_confirmation < 1
        ) {
            $payload = [];
			$text = '*KLINIK JATI ELOK*' ;
			$text .= PHP_EOL;
			$text .= $this->samaDengan();
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
            !$this->whatsapp_registration_deleted &&
            !is_null($this->whatsapp_registration->antrian) &&
             is_null( $this->whatsapp_registration->antrian->registrasi_pembayaran_id ) 
        ) {
			$text = 'Bisa dibantu menggunakan pembayaran apa ';
            $text .=  PHP_EOL;
            $text .=  'untuk nomor antrian *' . $this->whatsapp_registration->antrian->nomor_antrian . '* ?';
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
                $text .= $this->pertanyaanPembayaranPasien();
                $payload[] = [
                    'category' => 'text',
                    'message' => $text
                ];
            }
			return $payload;
		}
		if (
            !is_null($this->whatsapp_registration) &&
            !$this->whatsapp_registration_deleted &&
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
            !$this->whatsapp_registration_deleted &&
            !is_null($this->whatsapp_registration->antrian) &&
             is_null( $this->whatsapp_registration->antrian->nama ) 
        ) {
            $message =  $this->tanyaNamaLengkapPasien(false);
            $message .=  'untuk nomor antrian *' . $this->whatsapp_registration->antrian->nomor_antrian . '* ?';
            $payload[] = [
                'category' => 'text',
                'message' => $message
            ];
			return $payload;
		}

		if (
            !is_null($this->whatsapp_registration) &&
            !$this->whatsapp_registration_deleted &&
            !is_null($this->whatsapp_registration->antrian) &&
             is_null( $this->whatsapp_registration->antrian->tanggal_lahir ) 
        ) {
			$text    = $this->tanyaTanggalLahirPasien($this->whatsapp_registration->antrian);
            $message = $text;
            $message .=  PHP_EOL;
            $payload[] = [
                'category' => 'text',
                'message'  => $message
            ];
			return $payload;
		}

		if (
            !is_null($this->whatsapp_registration) &&
            !$this->whatsapp_registration_deleted &&
            !is_null($this->whatsapp_registration->antrian) &&
            !is_null($this->whatsapp_registration->antrian->tanggal_lahir) &&
            is_null($this->whatsapp_registration->antrian->kartu_asuransi_image)
        ) {
            $message = $this->tanyaKartuAsuransiImage($this->whatsapp_registration->antrian);
            $message .=  PHP_EOL;
            $message .= 'Untuk nomor antrian *' .  $this->whatsapp_registration->antrian->nomor_antrian . '* ?';
            $message .=  PHP_EOL;
            $payload[] = [
                'category' => 'text',
                'message'  => $message
            ];
			return $payload;
		}
		if (
            !is_null($this->whatsapp_registration) &&
            !$this->whatsapp_registration_deleted &&
            !is_null($this->whatsapp_registration->antrian) &&
            !is_null($this->whatsapp_registration->antrian->tanggal_lahir) &&
            !is_null($this->whatsapp_registration->antrian->kartu_asuransi_image)
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
                $text .= $this->lanjutAtauUlangi();
                $payload[] = [
                    'category' => 'text',
                    'message' => $text
                ];
            }
			return $payload;
		}

		if ($this->whatsapp_registration_deleted) {
            $registeredWhatsapp = WhatsappRegistration::where('no_telp', $this->no_telp)
                ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
                ->get();

            $text = $this->pesanBalasanBilaTerdaftar( $this->antrian );
            if ( $registeredWhatsapp->count() ) {
                $registeredWhatsapp = $registeredWhatsapp->first();

                $text .= PHP_EOL;
                $text .= PHP_EOL;
                $text .= $this->samaDengan();
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
    private function ulangiRegistrasiWhatsapp($model)
    {
        $model->registrasi_pembayaran_id          = null;
        $model->nama                              = null;
        $model->tanggal_lahir                     = null;
        $model->register_previously_saved_patient = null;
        $model->pasien_id                         = null;
        $model->save();
        return $model;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function pesanBalasanBilaTerdaftar($antrian, $online = false)
    {
        $response = "Anda telah terdaftar dengan Nomor Antrian";
        $response .= PHP_EOL;
        $response .= PHP_EOL;
        $response .= "```" . $antrian->nomor_antrian . "```" ;
        $response .= PHP_EOL;
        if ($online) {
            $response .= PHP_EOL;
            $response     .= "Silahkan scan *QRCODE* ini ";
            $response     .= PHP_EOL;
            $response     .= "di mesin antrian untuk segera mengkonfirmasikan kehadiran anda";
            $response     .= PHP_EOL;
            $response     .= PHP_EOL;
            $sisa_antrian  = $antrian->sisa_antrian;
            $response     .= "masih ada *{$sisa_antrian} antrian* lagi";
            $response     .= PHP_EOL;
            $waktu_tunggu  = $this->waktuTunggu( $antrian->sisa_antrian );
            $response     .= "perkiraan waktu tunggu *{$waktu_tunggu} menit*";
            if ( $antrian->jenis_antrian_id == 2 ) {
                $response .= PHP_EOL;
                $response .= "Panggilan akan dimulai pukul 15.00 saat dokter gigi mulai praktek";
                $response .= PHP_EOL;
            }
        } else {
            $response .= PHP_EOL;
            $response .= "Anda akan menerima notifikasi setiap kali ada panggilan pasien.";
            $response .= PHP_EOL;
            $response .= "Silahkan menunggu untuk dilayani";
        }
        $response .= PHP_EOL;
        $response .= $this->samaDengan();
        $response .= $this->batalkan();
        $response .= " reservasi";
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
        $query .= "psn.alamat as alamat, ";
        $query .= "psn.nama as nama, ";
        $query .= "psn.nomor_asuransi_bpjs as nomor_asuransi_bpjs, ";
        $query .= "psn.tanggal_lahir as tanggal_lahir, ";
        $query .= "psn.bpjs_image as bpjs_image, ";
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
        if (
             $this->angkaPertama('1') ||
             $this->message  == 'sudah sembuh'
        ) {
            return 3;
        } else if (
             $this->angkaPertama('2') ||
             str_contains(strtolower( $this->message ), 'alhamdulillah') ||
             str_contains(strtolower( $this->message ), 'alhamdulilah') ||
             str_contains(strtolower( $this->message ), 'alhmdulilah') ||
             getFirstWord( $this->message )  == 'membaik'
        ){
            return 2;
        } else if (
             $this->angkaPertama('3') ||
             $this->message  == 'tidak ada perubahan'
        ){
            return 1;
        }
    }

    /**
     * undocumented function
     *
     * @return void
     */
    private function angkaPertama($pembanding) {
        $number = preg_replace("/[^0-9]/", "", $this->message);
        return !empty( $this->message ) 
            && ( Input::get('messageType') == 'text' )
            && ( !empty( $number ) )
            && ( $this->message[0] ==  $pembanding|| $number[0] ==  $pembanding);
    }
    
    
    private function uploadImage()
    {
        $url      = Input::get('url');
        $contents = file_get_contents($url);
        $name     = substr($url, strrpos($url, '/') + 1);
        $destination_path = 'image/whatsapp/';
        $name = $destination_path . $name;

        \Storage::disk('s3')->put($name, $contents);
        return $name;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function registerWhatsappComplaint()
    {
        $tanggal_berobat = $this->whatsapp_complaint->antrian->created_at->format('Y-m-d');
        $this->whatsapp_complaint->delete();

        /* $complain = Complain::create([ */
        /*     'tanggal'  => $tanggal_berobat, */
        /*     'media'    => 'Whatsapp Bot', */
        /*     'complain' => $this->message */
        /* ]); */
        Antrian::where('no_telp', $this->no_telp)
            ->where('created_at', 'like', $tanggal_berobat . '%')
            ->update([
                'complaint'   => $this->message,
                /* 'complain_id' => $complain->id */
            ]);


        $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Keluhan atas pelayanan yang kakak rasakan akan segera kami tindak lanjuti.";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Untuk respon cepat kakak dapat menghubungi 021-5977529";
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
        $this->failed_therapy->delete();
        $this->pesanUntukPasienSelanjutnya();
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function registerWhatsappSatisfactionSurvey(){
        if (
            $this->angkaPertama("1") ||
            $this->message == 'puas' ||
            $this->angkaPertama("2") ||
            $this->message == 'biasa' ||
            $this->angkaPertama("3") ||
            $this->message == 'tidak puas'
        ) {
            $satisfaction_index_ini = $this->satisfactionIndex( $this->message );
            $no_telp = $this->whatsapp_satisfaction_survey->antrian->no_telp;

            Antrian::where('no_telp', $no_telp)
                    ->where('created_at', 'like', $this->whatsapp_satisfaction_survey->created_at->format('Y-m-d') . '%')
                    ->update([
                        'satisfaction_index' => $satisfaction_index_ini
                    ]);
            
            if(
                 $this->angkaPertama("1")  ||
                 $this->message == 'puas'
            ){
                echo $this->kirimkanLinkGoogleReview();
            } else if(
                 $this->angkaPertama("3")  ||
                 $this->message == 'tidak puas'
            ){

                $complaint             = new WhatsappComplaint;
                $complaint->no_telp    = $this->whatsapp_satisfaction_survey->antrian->no_telp;
                $complaint->antrian_id = $this->whatsapp_satisfaction_survey->antrian->id;
                $complaint->save();

                WhatsappRegistration::where('no_telp', $this->whatsapp_satisfaction_survey->antrian->no_telp)->delete();

                $message = "Mohon maaf atas ketidak nyamanan yang kakak alami.";
                $message .= PHP_EOL;
                $message .= "Bisa diinfokan kendala yang kakak alami?";
                echo $message;
            } else if (
                 $this->angkaPertama("2")  ||
                 $this->message == 'biasa'
            ) {
                $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
                $message .= PHP_EOL;
                $message .= "kami berharap dapat melayani anda dengan lebih baik lagi.";
                echo $message;
            }
            $this->whatsapp_satisfaction_survey->delete();
        } else {
            $message = "Balasan yang anda masukkan tidak dikenali";
            $message .= PHP_EOL;
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
            $this->angkaPertama("1") ||
            $this->message == 'sudah sembuh' ||
            $this->angkaPertama("2") ||
            $this->message == 'membaik' ||
            $this->angkaPertama("3") ||
            $this->message == 'tidak ada perubahan'
        ) {
            $recovery_index_ini = $this->recoveryIndexConverter( $this->message );
            $this->whatsapp_recovery_index->antrian->recovery_index_id = $recovery_index_ini; 
            $this->whatsapp_recovery_index->antrian->save();
            $nama = ucwords($this->whatsapp_recovery_index->antrian->antriable->pasien->nama);

            if(
                 $this->angkaPertama("3") ||
                 $this->message == 'tidak ada perubahan' 
            ){
                $fail             = new FailedTherapy;
                $fail->no_telp    = $this->no_telp;
                $fail->antrian_id = $this->whatsapp_recovery_index->antrian->id;
                $fail->save();

                $message = "Mohon maaf atas ketidak nyamanan yang kakak alami.";
                $message .= PHP_EOL;
                $message .= "Bisa diinfokan kondisi pasien saat ini?";
                $this->whatsapp_recovery_index->delete();
                echo $message;
            } else {
                $this->whatsapp_recovery_index->delete();
                $this->pesanUntukPasienSelanjutnya();
            }
        } else {
            $message = "Balasan yang anda masukkan tidak dikenali";
            $message .= PHP_EOL;
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
            $this->angkaPertama("1") ||
            ( !is_null( $this->message ) && $this->message[0] == '2' )
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
        $message = '*Klinik Jati Elok*';
        $message .= PHP_EOL;
        $message .= $this->samaDengan();
        $message .= PHP_EOL;
        $message .= 'Selamat Datang di Klinik Jati Elok';
        $message .= PHP_EOL;
        $message .= $this->messageWhatsappMainMenu();


        WhatsappMainMenu::create([
            'no_telp' => $this->no_telp
        ]);
        echo $message;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function registerWhatsappMainMenu()
    {
        if (
            $this->angkaPertama("1")
        ) {
            WhatsappBpjsDentistRegistration::create([
                'no_telp' => $this->no_telp
            ]);
            echo $this->pertanyaanPembayaranPasien();
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
        $message .= '1. Jadwal Pelayanan';
        /* $message .= PHP_EOL; */
        /* $message .= '2. Daftar Konsultasi'; */
        /* $message .= PHP_EOL; */
        /* $message .= '3. Cek status kepesertaan BPJS'; */
        /* $message .= PHP_EOL; */
        /* $message .= '4. Konsultasi Estetika'; */
        /* $message .= PHP_EOL; */
        /* $message .= '5. Saya ingin berbicara dengan admin'; */
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Balas dengan *1* sesuai dengan informasi di atas';
        /* $message .= 'Balas dengan *1 atau 2* sesuai dengan informasi di atas'; */
        /* $message .= 'Balas dengan *1, 2 atau 3* sesuai dengan informasi di atas'; */
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
            if (
                 $this->angkaPertama("1") ||
                 ( !is_null( $this->message ) && $this->message[0] == '2' ) ||
                 ( !is_null( $this->message ) && $this->message[0] == '3' )
            ) {
                $this->whatsapp_bpjs_dentist_registrations->registrasi_pembayaran_id = $this->message;
                $this->whatsapp_bpjs_dentist_registrations->save();

                $message = $this->tanyaKetersediaanSlot();
                echo $message;
            } 
        } else if ( 
            is_null($this->whatsapp_bpjs_dentist_registrations->tanggal_booking)
        ) {
            $slots = $this->queryKetersediaanSlotBpjsSatuMingguKeDepan();
            if (
                $this->message > 0 &&
                $this->message <= count( $slots )
            ) {
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
                $message = 'Input yang kakak masukkan tidak dikenali';
                $message .= PHP_EOL;
                $message .= $this->tanyaKetersediaanSlot();
            }
            echo $message;
        } else if ( 
            is_null($this->whatsapp_bpjs_dentist_registrations->register_previously_saved_patient)
        ) {
            $data = $this->queryPreviouslySavedPatientRegistry();
            $dataCount = count($data);
            if ( (int)$this->message <= $dataCount && (int)$this->message > 0  ) {
                $this->whatsapp_bpjs_dentist_registrations->register_previously_saved_patient = $this->message;
                $this->whatsapp_bpjs_dentist_registrations->pasien_id                         = $pasien->pasien_id;
                $this->whatsapp_bpjs_dentist_registrations->nama                              = $pasien->nama;
                $this->whatsapp_bpjs_dentist_registrations->tanggal_lahir                     = $pasien->tanggal_lahir;
                if ( $this->whatsapp_bpjs_dentist_registrations->registrasi_pembayaran_id == 2 ) {
                    $this->whatsapp_bpjs_dentist_registrations->nomor_asuransi_bpjs = $pasien->nomor_asuransi_bpjs;
                } else {
                    $this->whatsapp_bpjs_dentist_registrations->nomor_asuransi_bpjs = 0;
                }
                echo $this->konfirmasiSebelumDisimpanDiAntrianPoli();
            } else {
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
            if (
                $this->namaLengkapValid($this->message)
            ) {
                $this->whatsapp_bpjs_dentist_registrations->nama  = ucwords($this->message);
                $this->whatsapp_bpjs_dentist_registrations->save();
                echo $this->tanyaTanggalLahirPasien($this->whatsapp_bpjs_dentist_registrations->antrian);
            } else {
                $message = 'Input yang kakak masukkan tidak tepat';
                $message .= PHP_EOL;
                $message .= $this->tanyaNamaLengkapPasien();
                echo $message;
            }
        } else if ( 
            is_null($this->whatsapp_bpjs_dentist_registrations->tanggal_lahir)
        ) {
            $tanggal = $this->convertToPropperDate();
            $message = '';
            if (
                !is_null( $tanggal )
            ) {
                $this->whatsapp_bpjs_dentist_registrations->tanggal_lahir  = $tanggal;
                $this->whatsapp_bpjs_dentist_registrations->save();
                $message .= $this->konfirmasiSebelumDisimpanDiAntrianPoli();
            } else {
                $message .= 'Input yang kakak masukkan tidak dikenali';
                $message .= PHP_EOL;
                $message .= $this->tanyaTanggalLahirPasien($this->whatsapp_bpjs_dentist_registrations->antrian);
            }
            echo $message;
        } else if ( 
            !$this->whatsapp_bpjs_dentist_registrations->data_konfirmation
        ) {
            if (
                $this->message == '1' ||
                $this->message == '2'
            ) {
                if (
                    $this->message == '1'
                ) {
                    $this->whatsapp_bpjs_dentist_registrations->data_konfirmation  = 1;
                    $this->whatsapp_bpjs_dentist_registrations->save();
                    $this->masukkanDiAntrianPoli();
                } else if ( 
                    $this->message == '2'
                ) {
                    WhatsappBpjsDentistRegistration::create([
                        'no_telp' => $this->no_telp
                    ]);
                    echo $this->pertanyaanPembayaranPasien();
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
    private function tanyaNamaLengkapPasien($question = true) {
        $tanda_tanya = $question ? '?' : '';
        return 'Bisa dibantu *Nama Lengkap* pasien ' . $tanda_tanya;
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
    private function tanyaTanggalLahirPasien($antrian = null)
    {
        $message  = 'Bisa dibantu *Tanggal Lahir* pasien';
        if (!is_null( $antrian )) {
            $message .= 'Untuk nomor antrian *' . $antrian->nomor_antrian . '* ?';
        } else {
            $message .='?';
        }
        $message .=  PHP_EOL . 'Contoh : 19-07-2003';
        return $message;
    }

    private function tanyaNomorBpjsPasien()
    {
        $message =  'Bisa dibantu *Nomor Asuransi BPJS* pasien?';
        $message .= PHP_EOL;
        $message .=   '_Nomor BPJS harus terdiri dari 13 angka_';

        return $message;
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
        $slots = $this->queryKetersediaanSlotBpjsSatuMingguKeDepan();
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
        return $message;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function pertanyaanPembayaranPasien()
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
            $response .= $this->samaDengan();
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
    private function pasienTidakSedangBerobat()
    {
        $today = date('Y-m-d');
        $no_telp = $this->no_telp;
        $query  = "SELECT * ";
        $query .= "FROM antrians ";
        $query .= "WHERE no_telp = '{$no_telp}' ";
        $query .= "AND ";
        $query .= "(";
        $query .= "antriable_type = 'App\\\Models\\\AntrianPoli' ";
        $query .= "or antriable_type = 'App\\\Models\\\AntrianPeriksa' ";
        $query .= "or antriable_type = 'App\\\Models\\\AntrianFarmasi' ";
        $query .= "or antriable_type = 'App\\\Models\\\AntrianApotek' ";
        $query .= "or antriable_type = 'App\\\Models\\\Antrian' ";
        $query .= ") ";
        $query .= "AND created_at like '{$today}%'";
        $data = DB::select($query);

        return count($data) == 0;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function pesanUntukPasienSelanjutnya() {
        $this->whatsapp_recovery_index = WhatsappRecoveryIndex::where('no_telp', $this->no_telp)
                                ->whereRaw("DATE_ADD( updated_at, interval 23 hour ) > '" . date('Y-m-d H:i:s') . "'")
                                ->first();
        if ( !is_null( $this->whatsapp_recovery_index ) ) {
            $nama         = ucwords( strtolower( $this->whatsapp_recovery_index->antrian->antriable->pasien->nama ) );
            $dua_hari_yl  = $this->whatsapp_recovery_index->antrian->created_at;
            $message      = 'Terima kasih. Mohon dibantu kabar pasien selanjutnya atas nama : ';
            $message     .= PHP_EOL;
            $message     .= PHP_EOL;
            $message     .= '*' . $nama . '*';
            $message     .= PHP_EOL;
            $message     .= PHP_EOL;
            $message     .= 'Setelah berobat tanggal ' . $dua_hari_yl->format('d M Y'). '. Bagaimana kabarnya setelah pengobatan kemarin?';
            $message     .= PHP_EOL;
            $message     .= PHP_EOL;
            $message     .= '1. Sudah Sembuh';
            $message     .= PHP_EOL;
            $message     .= '2. Membaik';
            $message     .= PHP_EOL;
            $message     .= '3. Tidak ada perubahan';
            $message     .= PHP_EOL;
            $message     .= PHP_EOL;
            $message     .= 'Mohon balas dengan angka *1,2 atau 3* sesuai dengan informasi di atas';
            echo $message;
        } else {
            $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
            $message .= PHP_EOL;
            $message .= "Informasi ini akan menjadi bahan evaluasi kami";
            echo $message;
        }
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function noTelpAdaDiAntrianPeriksa()
    {
        $this->antrian = Antrian::where('no_telp', $this->no_telp)
                ->where('created_at', 'like', date('Y-m-d') . '%')
                ->whereRaw(
                    "(
                        antriable_type = 'App\\\Models\\\Antrian' or
                        antriable_type = 'App\\\Models\\\AntrianPeriksa' or
                        antriable_type = 'App\\\Models\\\AntrianPoli'
                    )"
                )
                ->first();
        return !is_null( $this->antrian );
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function updateNotifikasPanggilanUntukAntrian()
    {
        if (
             str_contains( $this->message,'stop' ) ||
             str_contains( $this->message,'setop' )
        ) {
            Antrian::where('no_telp', $this->no_telp)
                ->where('created_at', 'like', date('Y-m-d') . '%')
                ->update([
                    'notifikasi_panggilan_aktif' => 0
                ]);
            $message = 'Klinik Jati Elok';
            $message .= PHP_EOL;
            $message .= $this->samaDengan();
            $message .= PHP_EOL;
            $message .= 'Notifikasi Panggilan *dinonaktifkan*';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= 'Balas *aktifkan* untuk mengaktifkan kembali notifikasi panggilan';
        } else if (
             str_contains($this->message, 'batalkan') ||
             str_contains($this->message, 'btlkan') ||
             str_contains($this->message, 'batalkn') ||
             str_contains($this->message, 'batlkan')
        ) {
            $message = $this->konfirmasiPembatalan();
        } else if (
             str_contains($this->message, 'aktivkan') ||
             str_contains($this->message, 'aktipkan') ||
             str_contains($this->message, 'aktifkan')
        ) {
            Antrian::where('no_telp', $this->no_telp)
                ->where('created_at', 'like', date('Y-m-d') . '%')
                ->update([
                    'notifikasi_panggilan_aktif' => 1
                ]);
            $message = 'Klinik Jati Elok';
            $message .= PHP_EOL;
            $message .= $this->samaDengan();
            $message .= PHP_EOL;
            $message .= 'Notifikasi Panggilan *diaktifkan*.';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= 'Balas *stop* untuk berhenti menerima notifikasi panggilan';
        } else {
            $message = 'Balasan yang anda masukkan tidak dikenali';
            if ( $this->antrian->notifikasi_panggilan_aktif ) {
                $message .= PHP_EOL;
                $message .= 'Notifikasi Panggilan *diaktifkan*';
                $message .= PHP_EOL;
                $message .= PHP_EOL;
                $message .= 'Balas *stop* untuk berhenti menerima notifikasi panggilan';
            } else {
                $message .= PHP_EOL;
                $message .= 'Notifikasi Panggilan *dinonaktifkan*';
                $message .= PHP_EOL;
                $message .= PHP_EOL;
                $message .= 'Balas *aktifkan* untuk mengaktifkan kembali notifikasi panggilan';
            }
        }
        echo $message;
    }
    public function pasienTidakDalamAntrian(){
        return !Antrian::where('no_telp', $this->no_telp)
            ->where('created_at', 'like', date('Y-m-d') . '%')
            ->whereRaw("antriable_type not like 'App\\\Models\\\Periksa' ")
            ->exists() ;
        /* && $this->no_telp = '6281381912803'; */
    }
    public function whatsappMainMenuExists(){
        return WhatsappMainMenu::where('no_telp', $this->no_telp)
            ->where('created_at', 'like', date('Y-m-d') . '%')
            ->exists();
    }

    public function whatsappJadwalKonsultasiInquiryExists(){
        return WhatsappJadwalKonsultasiInquiry::where('no_telp', $this->no_telp)
            ->where('created_at', 'like', date('Y-m-d') . '%')
            ->exists();
    }


    public function balasJadwalKonsultasi(){
        if ( 
             $this->message == '1'  ||
             $this->message == '2'  ||
             $this->message == '3'  ||
             $this->message == '4' 
        ){
            WhatsappJadwalKonsultasiInquiry::where('created_at', 'like', date('Y-m-d') . '%')
                ->where('no_telp', $this->no_telp)
                ->delete();
            if ( $this->message == '1' ) {
                $this->balasJadwalDokterUmum();
            } else if ( $this->message == '2' ){
                $this->balasJadwalDokterGigi();
            } else if ( $this->message == '3' ){
                $this->balasJadwalDokterBidan();
            } else if ( $this->message == '4' ){
                $this->balasJadwalDokterUsg();
            }
        } else {
            $message = 'Balasan yang anda masukkan tidak dikenali';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= $this->pertanyaanTipeKonsultasi();
            echo $message;
        }
    }

    public function prosesMainMenuInquiry(){
        $message = '';
        if ( $this->message == 1 ) {
            WhatsappJadwalKonsultasiInquiry::create([
                'no_telp' => $this->no_telp
            ]);
            echo $this->pertanyaanTipeKonsultasi();
        } else if ( $this->message == 2 ) {
            echo $this->registrasiAntrianOnline();
        } else if ( $this->message == 3 ) {
            echo $this->registrasiInformasiNomorKartuBpjs();
        } else if ( $this->message == 4 ) {
            $whatsapp_bot = WhatsappBot::create([
                'no_telp' => $this->no_telp,
                'whatsapp_bot_service_id' => 5
            ]);

            KonsultasiEstetikOnline::create([
                'no_telp'         => $this->no_telp,
                'whatsapp_bot_id' => $whatsapp_bot->id
            ]);

            $message = 'Kakak akan melakukan registrasi untuk konsultasi estetis secara online';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= 'Ketik *ya* untuk melanjutkan ';
            $message .= $this->batalkan();
            echo $message;
        } else {
            $message = $this->messageWhatsappMainMenu();
            $message .= $this->pesanMintaKlienBalasUlang();
            echo $message;
        }
    }
    public function balasJadwalDokterUmum(){
        echo $this->queryJadwalKonsultasiByTipeKonsultasi(1);
    }
    public function balasJadwalDokterGigi(){
        echo $this->queryJadwalKonsultasiByTipeKonsultasi(2);
    }
    public function balasJadwalDokterBidan(){
        echo $this->queryJadwalKonsultasiByTipeKonsultasi(3);
    }
    public function balasJadwalDokterUsg(){
        echo $this->queryJadwalKonsultasiByTipeKonsultasi(4);
    }
    /**
     * undocumented function
     *
     * @return void
     */
    public function queryJadwalKonsultasiByTipeKonsultasi($param)
    {
        $query  = "SELECT ";
        $query .= "har.hari, ";
        $query .= "ttl.singkatan as titel, ";
        $query .= "TIME_FORMAT(jad.jam_mulai, '%H:%i') as jam_mulai, ";
        $query .= "TIME_FORMAT(jad.jam_akhir, '%H:%i') as jam_akhir, ";
        $query .= "tip.tipe_konsultasi, ";
        $query .= "sta.nama ";
        $query .= "FROM jadwal_konsultasis as jad ";
        $query .= "JOIN stafs as sta on sta.id = jad.staf_id ";
        $query .= "JOIN titels as ttl on ttl.id = sta.titel_id ";
        $query .= "JOIN tipe_konsultasis as tip on tip.id = jad.tipe_konsultasi_id ";
        $query .= "JOIN haris as har on har.id = jad.hari_id ";
        $query .= "WHERE jad.tipe_konsultasi_id = {$param} ";
        $query .= "ORDER BY hari_id asc, jam_mulai asc";

        $query = DB::select($query);
        if (count($query)) {
            $result = [];

            foreach ($query as $q) {
                $result[$q->hari][] = [
                    'nama' => $q->nama,
                    'titel' => $q->titel,
                    'jam_mulai' => $q->jam_mulai,
                    'jam_akhir' => $q->jam_akhir,
                ];
            }
            $message = '*Jadwal ' . ucwords(strtolower($query[0]->tipe_konsultasi)) . '*';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            foreach ($result as $k => $r) {
                if ( strtolower( $k ) != 'senin' ) {
                    $message .= PHP_EOL;
                }
                $message .= ucwords($k) . ': ';
                $message .= PHP_EOL;
                foreach ($r as $i => $d) {
                    if (count($r) > 1) {
                        $nomor    = $i + 1;
                        $message .= $nomor . '. ';
                    }
                    $message .=  $this->tambahkanGelar($d['titel'],ucwords($d['nama']));
                    $message .= PHP_EOL;
                    $message .= ' ( ' . $d['jam_mulai'] . '-' . $d['jam_akhir'].  ' )' ;
                    $message .= PHP_EOL;
                }
            }
            if ( $param == 1 ) {
                $staf = $this->lastStaf();
                $message .= PHP_EOL;
                $message .= 'Dokter umum yang saat ini praktik adalah ' . $this->tambahkanGelar( $staf->titel, ucwords( strtolower($staf->nama) ) );
                $message .= PHP_EOL;
                $message .= 'untuk memastikan silahkan hubungi 021-5977529';
                $message .= PHP_EOL;
                $message .= 'Terima kasih';
            }
        } else {
            $message = 'Fitur ini dalam pengembangan';

        }
        return $message;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function pertanyaanTipeKonsultasi(){
        $message = 'Bisa dibantu infokan Jadwal Konsultasi yang ingin diketahui?';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $jumlah_antrian = $this->jumlahAntrian();
        $message .= '1. Dokter Umum (ada ' . $jumlah_antrian['dokter_umum']. ' antrian)';
        $message .= PHP_EOL;
        $message .= '2. Dokter Gigi (ada ' . $jumlah_antrian['dokter_gigi']. ' antrian)';
        $message .= PHP_EOL;
        $message .= '3. Bidan';
        $message .= PHP_EOL;
        $message .= '4. USG Kehamilan';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Balas dengan angka *1,2,3 atau 4* sesuai informasi di atas';
        return $message;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function tambahkanGelar($titel,$nama)
    {
        if (
            !str_contains( strtolower($nama), 'dr.' ) &&
            !str_contains( strtolower($nama), 'drg' ) &&
            !str_contains( strtolower($nama), 'dr ' )
        ) {
            return $titel.'. ' . $nama;
        } else {
            return $nama;
        }
    }
    public function lastStaf(){
        $hari_ini = date('Y-m-d');
        $kemarin = date('Y-m-d',strtotime("-1 days"));
        $query  = "SELECT ";
        $query .= "sta.nama as nama, ";
        $query .= "ttl.singkatan as titel ";
        $query .= "FROM periksas as prx ";
        $query .= "JOIN stafs as sta on sta.id = prx.staf_id ";
        $query .= "JOIN titels as ttl on ttl.id = sta.titel_id ";
        $query .= "WHERE (prx.tanggal = '{$hari_ini}' ";
        $query .= "or prx.tanggal = '{$kemarin}') ";
        $query .= "and prx.tenant_id = 1 ";
        $query .= "and sta.tenant_id = 1 ";
        $query .= "ORDER BY prx.id desc ";
        $query .= "LIMIT 1";
        return DB::select($query)[0];
    }

    public function masihAdaYangBelumCekListHariIni(){
        $cek_list_ruangan_harian_ids  = CekListRuangan::where('frekuensi_cek_id', 1)->pluck('id');
        $cek_list_dikerjakan_hari_ini = CekListDikerjakan::where('created_at', 'like', date('Y-m-d') . '%')
                                                        ->whereIn('cek_list_ruangan_id', $cek_list_ruangan_harian_ids)
                                                        ->groupBy('cek_list_ruangan_id')
                                                        ->get();
        return $cek_list_ruangan_harian_ids->count() == $cek_list_dikerjakan_hari_ini->count();
    }
    public function cekListBelumDilakukan( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input ){
        $cek_list_ruangan_harians = CekListRuangan::with('cekList', 'ruangan')
                                    ->where('frekuensi_cek_id', $frekuensi_cek_id)
                                    ->orderBy('ruangan_id', 'asc')
                                    ->orderBy('cek_list_id', 'asc')
                                    ->get();
        $cek_list_ruangan_ids = [];
        foreach ($cek_list_ruangan_harians as $cek) {
            $cek_list_ruangan_ids[] = $cek->id;
        }
        $cek_list_harians_dikerjakans = CekListDikerjakan::whereIn('cek_list_ruangan_id', $cek_list_ruangan_ids)
                                                        ->where('created_at', 'like', date('Y-m-d') . '%')
                                                        ->whereNotNull('image')
                                                        ->whereNotNull('jumlah')
                                                        ->groupBy('cek_list_ruangan_id')
                                                        ->get();
        if ( $cek_list_ruangan_harians->count() !== $cek_list_harians_dikerjakans->count()) {
            WhatsappBot::where('no_telp', $this->no_telp)->where('whatsapp_bot_service_id', $whatsapp_bot_service_id)->update([
                'whatsapp_bot_service_id' => $whatsapp_bot_service_id_input
            ]);
            foreach ($cek_list_ruangan_harians as $cek) {
                $cek_list_dikerjakan = $this->cekListDikerjakanUntukCekListRuanganIni( $cek->id );
                if ( $cek->id == 20 ) {
                }
                if ( 
                    is_null(  $cek_list_dikerjakan  ) ||
                    ( !is_null( $cek_list_dikerjakan ) && is_null(  $cek_list_dikerjakan->jumlah  ) ) ||
                    ( !is_null( $cek_list_dikerjakan ) && is_null(  $cek_list_dikerjakan->image  ) ) 
                ) {
                    return $cek;
                    break;
                }
            }
        }
        return null;
    }
    public function cekListDikerjakanUntukCekListRuanganIni( $cek_list_ruangan_id ){
        return CekListDikerjakan::where('cek_list_ruangan_id',  $cek_list_ruangan_id )
                            ->where('created_at', 'like', date('Y-m-d') . '%')
                            ->first();
    }

    public function pesanCekListHarianBerikutnya($cek){
        $message = "Silahkan cek ";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= '*'.$cek->cekList->cek_list . '*';
        $message .= PHP_EOL;
        $message .= "jumlah " . $cek->limit->limit . ' ' . $cek->jumlah_normal;
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Di ruangan ";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "*" . $cek->ruangan->nama . '*';
        return $message;
    }
    public function masukkanGambar($cek){
        $message = "Mohon masukkan gambar  ";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= '*'.$cek->cekList->cek_list . '*';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Di ruangan ";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "*" . $cek->ruangan->nama . '*';
        return $message;
    }

    //
    //Cek List Harian
    public function cekListHarianExists(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(1);
    }
    public function cekListHarianInputExists(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(2);
    }
    public function prosesCekListHarian(){
        return $this->prosesCekListDilakukan(1,1,2); // harian
    }
    public function prosesCekListHarianInput(){
        $this->prosesCekListDikerjakanInput(1,1,2);
    }
    //
    //Cek List Bulanan
    public function cekListBulananExists(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(3);
    }
    public function cekListBulananInputExists(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(4);
    }
    public function prosesCekListBulanan(){
        return $this->prosesCekListDilakukan(3,3,4); // bulanan
    }
    public function prosesCekListBulananInput(){
        $this->prosesCekListDikerjakanInput(3,3,4);
    }
    public function prosesCekListDilakukan( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input ){
        $cek = $this->cekListBelumDilakukan( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input );
        if ($cek) {
            $message = $this->pesanCekListHarianBerikutnya( $cek );
        } else {
            WhatsappBot::where('no_telp', $this->no_telp)->whereIn('whatsapp_bot_service_id',[
                $whatsapp_bot_service_id,
                $whatsapp_bot_service_id_input
            ])->delete();
            $message = 'Cek List selesai';
        }
        echo $message;
    }

    public function cekListPhoneNumberRegisteredForWhatsappBotService( $whatsapp_bot_service_id ){
        return !is_null( $this->whatsapp_bot ) && $this->whatsapp_bot->whatsapp_bot_service_id == $whatsapp_bot_service_id;
    }
    public function prosesCekListDikerjakanInput( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input ){
        $cek = $this->cekListBelumDilakukan( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input );
        if (!is_null($cek)) {
            $cek_list_dikerjakan = $this->cekListDikerjakanUntukCekListRuanganIni( $cek->id );
            $whatsapp_bot = WhatsappBot::with('staf')
                ->where('no_telp', $this->no_telp)
                ->whereRaw('whatsapp_bot_service_id = ' . $whatsapp_bot_service_id. ' or whatsapp_bot_service_id = ' . $whatsapp_bot_service_id_input)
                ->first();

            if ( 
                is_null(  $cek_list_dikerjakan  ) &&
                !is_null( $whatsapp_bot )
            ) {
                if ( is_numeric( $this->message ) ) {
                    CekListDikerjakan::create([
                        'jumlah'              => $this->message,
                        'staf_id'             => $whatsapp_bot->staf_id,
                        'tenant_id'           => $whatsapp_bot->staf->tenant_id,
                        'cek_list_ruangan_id' => $cek->id
                    ]);
                    echo $this->masukkanGambar($cek);
                } else {
                    $message = 'Balasan anda tidak dikenali. Mohon masukkan angka';
                    $message .= PHP_EOL;
                    $message .= PHP_EOL;
                    $cek = $this->cekListBelumDilakukan( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input );
                    $message .= $this->pesanCekListHarianBerikutnya( $cek );
                    echo $message;
                }
            }
            if ( 
                !is_null(  $cek_list_dikerjakan  ) &&
                is_null( $cek_list_dikerjakan->jumlah ) &&
                !is_null( $whatsapp_bot )
            ) {
                $cek_list_dikerjakan->jumlah = $this->message;
                $cek_list_dikerjakan->save();
                echo $this->masukkanGambar($cek);
            } else if ( 
                !is_null(  $cek_list_dikerjakan  ) &&
                !is_null( $whatsapp_bot ) &&
                is_null( $cek_list_dikerjakan->image )
            ) {
                if ( Input::get('messageType') == 'image' ) {
                    $cek_list_dikerjakan->image = $this->uploadImage();
                    $cek_list_dikerjakan->save();
                }
                $cek = $this->cekListBelumDilakukan( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input );
                if (
                    !is_null( $cek )
                ) {
                    $cek_list_dikerjakan = $this->cekListDikerjakanUntukCekListRuanganIni( $cek->id );
                    if ( 
                        !is_null($cek_list_dikerjakan)
                    ) {
                        if (  
                            is_null($cek_list_dikerjakan->jumlah)
                        ) {
                            echo $this->pesanCekListHarianBerikutnya( $cek );
                        } else if (
                            is_null($cek_list_dikerjakan->image)
                        ) {
                            echo $this->masukkanGambar( $cek );
                        }
                    } else {
                        echo $this->pesanCekListHarianBerikutnya( $cek );
                    }
                } else { 
                    echo $this->cekListSelesai($whatsapp_bot_service_id,$whatsapp_bot_service_id_input);
                }
            }
        } else {
            echo $this->cekListSelesai($whatsapp_bot_service_id,$whatsapp_bot_service_id_input);
        }
    }
    public function cekListSelesai($whatsapp_bot_service_id,$whatsapp_bot_service_id_input){
        $whatsapp_bot_service = WhatsappBot::whereIn("whatsapp_bot_service_id", [$whatsapp_bot_service_id,$whatsapp_bot_service_id_input])->where('no_telp', $this->no_telp)->first();

        if (
            $whatsapp_bot_service->whatsapp_bot_service_id == 1 || 
            $whatsapp_bot_service->whatsapp_bot_service_id == 2 // input harian
        ) {
            $frekuensi_cek_id = 1;
        } else if (
            $whatsapp_bot_service->whatsapp_bot_service_id == 8 || 
            $whatsapp_bot_service->whatsapp_bot_service_id == 9 // input mingguan
        ) {
            $frekuensi_cek_id = 2;
        } else if (
            $whatsapp_bot_service->whatsapp_bot_service_id == 3 || 
            $whatsapp_bot_service->whatsapp_bot_service_id == 4 // input bulanan
        ) {
            $frekuensi_cek_id = 3;
        }
        $whatsapp_bot_service->delete();

        $bulan_ini = date('Y-m-d'); 

        $query  = "SELECT ";
        $query .= "cls.cek_list as cek_list, ";
        $query .= "lmt.limit as patokan, ";
        $query .= "cld.jumlah as jumlah, ";
        $query .= "clr.jumlah_normal as jumlah_normal, ";
        $query .= "rgn.nama as ruangan ";
        $query .= "FROM cek_list_dikerjakans as cld ";
        $query .= "JOIN cek_list_ruangans as clr on cld.cek_list_ruangan_id = clr.id ";
        $query .= "JOIN ruangans as rgn on clr.ruangan_id = rgn.id ";
        $query .= "JOIN cek_lists as cls on clr.cek_list_id = cls.id ";
        $query .= "JOIN limits as lmt on cld.cek_list_ruangan_id = clr.id ";
        $query .= "WHERE clr.frekuensi_cek_id = {$frekuensi_cek_id} ";
        $query .= "AND cld.created_at like '{$bulan_ini}%' ";
        $query .= "AND ";
        $query .= "(";
        $query .= "limit_id = 1 and cld.jumlah < clr.jumlah_normal or "; // limit 1 minimal
        $query .= "limit_id = 2 and cld.jumlah > clr.jumlah_normal or "; // limit 2 maksimal
        $query .= "limit_id = 3 and cld.jumlah not like clr.jumlah_normal "; // limit 3 sama dengan
        $query .= ") ";
        $query .= "GROUP BY cld.id ";

        $data = DB::select($query);
        $message = "Cek List sudah selesai dikerjakan. Good Work!!!";

        if (count($data)) {
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            foreach ($data as $k => $d) {
                $nomor    = $k +1;
                $message .= $nomor . '. '. $d->cek_list . ' di ' . $d->ruangan;
                $message .= PHP_EOL;
                $message .= '*jumlah ' . $d->jumlah . '(' . $d->patokan. ' ' .$d->jumlah_normal. ')*';
                $message .= PHP_EOL;
                $message .= PHP_EOL;
            }
            $message .= PHP_EOL;
            $message .= 'harap segera ditindak lanjuti';
        }

        return $message;
    }
    public function whatsappAntrianOnlineExists(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(6);
    }
    public function prosesAntrianOnline(){

        $reservasi_online = ReservasiOnline::with('pasien')->where('no_telp', $this->no_telp)
             ->where('whatsapp_bot_id', $this->whatsapp_bot->id)
             ->first();

        $message           = '';
        $input_tidak_tepat = false;

        if( is_null( $reservasi_online ) ){
            $this->whatsapp_bot->delete();
        }
        if (
            !is_null( $reservasi_online ) &&
            $this->message == 'batalkan'
        ) {
            echo $this->konfirmasiPembatalan();
            return false;
        } else if (
            !is_null( $reservasi_online ) &&
            !$reservasi_online->konfirmasi_sdk
        ) {
            if ( 
                $this->message == 'ya' || 
                $this->message == 'iya' || 
                $this->message == 'iy' || 
                $this->message == 'y'
            ) {
                $reservasi_online->konfirmasi_sdk = 1;
                $reservasi_online->save();
            }
        } else if (
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            is_null( $reservasi_online->jenis_antrian_id )
        ) {
            if ( 
                $this->message == '1' || 
                $this->message == '2' || 
                $this->message == '3'
            ) {
                if (
                     $this->message == '2' && //antrian poli gigi
                     (
                         date('w') == 6 || // hari sabtu atau
                         date('w') == 0 // hari minggu
                     )
                ) {
                    $message = 'Haru sabtu dan hari minggu pelayanan poli gigi libur. Mohon maaf atas ketidaknyamanannya.';
                    $message .= PHP_EOL;
                    $message .= PHP_EOL;
                    $message .= $this->hapusAntrianWhatsappBotReservasiOnline();
                    echo $message;
                    return false;
                } else if ( $this->sudahAdaAntrianUntukJenisAntrian( $this->message ) ) {
                    $reservasi_online->jenis_antrian_id = $this->message;
                    $reservasi_online->save();
                } else {
                    $jenis_antrian = JenisAntrian::find( $this->message );
                    $message = 'Saat ini tidak ada antrian di ' . $jenis_antrian->jenis_antrian .'. Anda kami persilahkan untuk datang dan mengambil antrian secara langsung';
                    $message .= PHP_EOL;
                    $message .= PHP_EOL;
                    $message .= $this->hapusAntrianWhatsappBotReservasiOnline();
                    echo $message;
                    return false;
                }
            } else {
                $input_tidak_tepat = true;
            }
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            is_null( $reservasi_online->registrasi_pembayaran_id )
        ) {
            if ( $this->validasiRegistrasiPembayaran()) {
                $reservasi_online = $this->lanjutkanRegistrasiPembayaran($reservasi_online);
                $data = $this->queryPreviouslySavedPatientRegistry();

                if ( $reservasi_online->registrasi_pembayaran_id == 1 ) {
                    $reservasi_online->kartu_asuransi_image = '';
                }

                if ( $reservasi_online->registrasi_pembayaran_id !== 2 ) {
                    $reservasi_online->nomor_asuransi_bpjs = '';
                }

                if (!count($data)) {
                    $reservasi_online->register_previously_saved_patient = 0;
                }
                $reservasi_online->save();
            } else {
                $input_tidak_tepat = true;
            }
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id ) &&
            is_null( $reservasi_online->register_previously_saved_patient ) 
        ) {
            $data = $this->queryPreviouslySavedPatientRegistry();
            $dataCount = count($data);
            if ( (int)$this->message <= $dataCount && (int)$this->message > 0  ) {
                $pasien = $data[ (int)$this->message -1 ];
                $pasien = Pasien::find( $pasien->pasien_id );
                if ( $reservasi_online->registrasi_pembayaran_id ==2 ) { //bila BPJS
                    $bpjs                                                = new BpjsApiController;
                    $response                                            = $bpjs->pencarianNoKartuValid($pasien->nomor_asuransi_bpjs, true);
                    $reservasi_online->data_bpjs_cocok                   = $this->nomorKartuBpjsDitemukanDiPcareDanDataKonsisten($response, $pasien) ;
                    $code                                                = $response['code'];
                    $message                                             = $response['response'];

                    if ( $code == 204 ) {// jika tidak ditemukan
                        $reservasi_online->pasien_id                         = $pasien->id;
                        $reservasi_online->nama                              = $pasien->nama;
                        $reservasi_online->tanggal_lahir                     = $pasien->tanggal_lahir;
                        $reservasi_online->alamat                            = $pasien->alamat;
                        $reservasi_online->register_previously_saved_patient = $this->message;
                        $pasien->nomor_asuransi_bpjs = null;
                        $pasien->save();
                    } else if (
                        $code >= 200 &&
                        $code <= 299
                    ){
                        if ( 
                            !is_null($message) &&
                            $message['aktif'] &&
                            $message['kdProviderPst']['kdProvider'] == '0221B119'
                        ) {
                            $reservasi_online->pasien_id                         = $pasien->id;
                            $reservasi_online->nama                              = $pasien->nama;
                            $reservasi_online->tanggal_lahir                     = $pasien->tanggal_lahir;
                            $reservasi_online->alamat                            = $pasien->alamat;
                            $reservasi_online->register_previously_saved_patient = $this->message;
                            if (
                                 !is_null( $pasien->bpjs_image ) &&
                                 !empty( $pasien->bpjs_image )
                            ) {
                                $reservasi_online->kartu_asuransi_image = $pasien->bpjs_image;
                            }
                            if (
                                 !is_null( $pasien->nomor_asuransi_bpjs ) &&
                                 !empty( $pasien->nomor_asuransi_bpjs )
                            ) {
                                $reservasi_online->nomor_asuransi_bpjs = $pasien->nomor_asuransi_bpjs;
                            }
                        } else if(
                            !is_null($message) &&
                            !$message['aktif']
                        ) {
                            $input_tidak_tepat = true;
                            $this->pesan_error = 'Kartu tidak aktif karena :';
                            $this->pesan_error .= PHP_EOL;
                            $this->pesan_error .= $message['ketAktif'];
                        } else if(
                            !is_null($message) &&
                            $message['kdProviderPst']['kdProvider'] !== '0221B119'
                        ) {
                            $input_tidak_tepat = true;
                            $this->pesan_error = $this->validasiBpjsProviderSalah( $pasien->nomor_asuransi_bpjs, $message );
                        }
                    } else {
                        $reservasi_online->pasien_id                         = $pasien->id;
                        $reservasi_online->nama                              = $pasien->nama;
                        $reservasi_online->tanggal_lahir                     = $pasien->tanggal_lahir;
                        $reservasi_online->alamat                            = $pasien->alamat;
                        $reservasi_online->register_previously_saved_patient = $this->message;
                    }
                }
            } else {
                $reservasi_online->register_previously_saved_patient = $this->message;
                $reservasi_online->registering_confirmation = 0;
            }
            $reservasi_online->save();
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id ) &&
            !is_null( $reservasi_online->register_previously_saved_patient ) &&
            is_null( $reservasi_online->nomor_asuransi_bpjs ) 
        ) {
            $this->pesan_error =   pesanErrorValidateNomorAsuransiBpjs( $this->message )  ;
            if (empty( $this->pesan_error )) {
                $bpjs     = new BpjsApiController;
                $response = $bpjs->pencarianNoKartuValid( $this->message, true );
                $code     = $response['code'];
                $message = $response['response'];
                if (
                    $code == 204 // jika tidak ditemukan
                ) {
                    Log::info(2744);
                    $input_tidak_tepat = true;
                    $this->pesan_error = 'Nomor BPJS tidak ditemukan di sistem BPJS';
                } else if (
                    $code >=200 &&
                    $code <=299 // jika oke
                ) {
                    // bagi lagi apakah pasien kartunya aktif atau tidak aktif
                    Log::info(2752);
                    $pasien                            = Pasien::where('nomor_asuransi_bpjs', $this->message)->first();
                    if ( !is_null( $pasien ) ) {
                        Log::info(2755);
                        $reservasi_online->data_bpjs_cocok = $this->nomorKartuBpjsDitemukanDiPcareDanDataKonsisten($response, $pasien );
                    }
                    if (
                        !is_null( $message ) && 
                        $message['aktif'] &&
                        $message['kdProviderPst']['kdProvider'] == '0221B119' &&
                        $reservasi_online->data_bpjs_cocok
                    ) { // jika aktig
                        Log::info(2764);
                        $reservasi_online->nomor_asuransi_bpjs = $this->message;
                        if ( 
                            !is_null( $pasien ) && // jika pasien dengan nomor asuransi bpjs ditemukan
                            is_null( $reservasi_online->pasien ) // dan pasien tidak dalam pendaftaran previously registered
                        ) {
                            Log::info(2770);
                            // update sesuai dengan pasien yang ditemukan
                            $reservasi_online->pasien_id       = $pasien->id;
                            $reservasi_online->nama            = $pasien->nama;
                            $reservasi_online->alamat          = $pasien->alamat;
                            $reservasi_online->tanggal_lahir   = $pasien->tanggal_lahir;
                        } else if (
                             !is_null( $reservasi_online->pasien ) && // jika previously registered
                             $reservasi_online->data_bpjs_cocok  // dan data bpjs cocok
                        ) {
                            Log::info(2780);
                            // update nomor asuransi bpjs dengan yang baru
                            $reservasi_online->pasien->save();
                        } else if (
                            is_null( $pasien )  // jika pasien yang memiliki tidak ditemukan di atika namun ditemukan di pcare
                        ) {
                            Log::info(2786);
                            $reservasi_online->nama            = $message['nama'];
                            $reservasi_online->tanggal_lahir   = Carbon::createFromFormat("d-m-Y", $message['tglLahir'])->format("Y-m-d");
                        }
                    } else if(
                        !is_null( $message ) && 
                        !$message['aktif']
                    ) { // jika tidak aktif
                        Log::info(2794);
                        $input_tidak_tepat = true;
                        $this->pesan_error = 'Kartu tidak aktif karena :';
                        $this->pesan_error .= PHP_EOL;
                        $this->pesan_error .= $message['ketAktif'];
                    } else if(
                        !is_null( $message ) && 
                        $message['kdProviderPst']['kdProvider'] !== '0221B119'
                    ) { // jika tidak aktif
                        Log::info(2803);
                        $input_tidak_tepat = true;
                        $this->pesan_error = $this->validasiBpjsProviderSalah( $this->message, $message );
                    } else if(
                        !is_null( $message ) && 
                        !$reservasi_online->data_bpjs_cocok
                    ) { // jika error
                        Log::info(2810);
                        $input_tidak_tepat = true;
                        $this->pesan_error = $this->validasiBpjsDataTidakCocok( $this->message, $message );
                    }
                }
            } else {
                Log::info(2816);
                $input_tidak_tepat = true;
            }
            $reservasi_online->save();
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id ) &&
            !is_null( $reservasi_online->register_previously_saved_patient ) &&
            !is_null( $reservasi_online->nomor_asuransi_bpjs ) &&
            is_null( $reservasi_online->nama ) 
        ) {
            if ( validateName( $this->message ) ) {
                $reservasi_online->nama  = ucwords(strtolower($this->message));;
                $reservasi_online->save();
            } else {
                $input_tidak_tepat = true;
            }
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id ) &&
            !is_null( $reservasi_online->register_previously_saved_patient ) &&
            !is_null( $reservasi_online->nomor_asuransi_bpjs ) &&
            !is_null( $reservasi_online->nama ) &&
            is_null( $reservasi_online->tanggal_lahir ) 
        ) {
            $tanggal = $this->convertToPropperDate();
            if (!is_null( $tanggal )) {
                $reservasi_online->tanggal_lahir  = $tanggal;
                $reservasi_online->save();
            } else {
                $input_tidak_tepat = true;
            }
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id ) &&
            !is_null( $reservasi_online->register_previously_saved_patient ) &&
            !is_null( $reservasi_online->nomor_asuransi_bpjs ) &&
            !is_null( $reservasi_online->nama ) &&
            !is_null( $reservasi_online->tanggal_lahir ) &&
            is_null( $reservasi_online->alamat )
        ) {
            $reservasi_online->alamat  = $this->message;
            $reservasi_online->save();
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id )&&
            !is_null( $reservasi_online->register_previously_saved_patient ) &&
            !is_null( $reservasi_online->nomor_asuransi_bpjs ) &&
            !is_null( $reservasi_online->nama ) &&
            !is_null( $reservasi_online->tanggal_lahir ) &&
            !is_null( $reservasi_online->alamat ) &&
            is_null( $reservasi_online->kartu_asuransi_image )
        ) {
            if (
                $this->isPicture()
            ) {
                $reservasi_online->kartu_asuransi_image = $this->uploadImage(); 
                $reservasi_online->save();
            } else {
                $input_tidak_tepat = true;
            }
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id )&&
            !is_null( $reservasi_online->register_previously_saved_patient ) &&
            !is_null( $reservasi_online->nomor_asuransi_bpjs ) &&
            !is_null( $reservasi_online->nama ) &&
            !is_null( $reservasi_online->tanggal_lahir ) &&
            !is_null( $reservasi_online->alamat ) &&
            !is_null( $reservasi_online->kartu_asuransi_image )
        ) {
            Log::info(2834);
            if (
                ( $this->message == 'lanjutkan' && $this->tenant->iphone_whatsapp_button_available )||
                ( $this->message == 'ulangi' && $this->tenant->iphone_whatsapp_button_available ) ||
                ( !is_null( $this->message ) && $this->message[0] == '1' && !$this->tenant->iphone_whatsapp_button_available )||
                ( !is_null( $this->message ) && $this->message[0] == '2' && !$this->tenant->iphone_whatsapp_button_available )
            ) {
                if (
                    ( $this->message == 'lanjutkan' && $this->tenant->iphone_whatsapp_button_available ) ||
                    ( !is_null( $this->message ) && $this->message[0] == '1' && !$this->tenant->iphone_whatsapp_button_available )
                ) {
                    WhatsappBot::where('no_telp', $this->no_telp)
                        ->delete();
                    ReservasiOnline::where('no_telp', $this->no_telp)->delete();
                    $this->input_nomor_bpjs = $reservasi_online->nomor_asuransi_bpjs;
                    $antrian                = $this->antrianPost( $reservasi_online->jenis_antrian_id );

                    $antrian->nama                     = $reservasi_online->nama;
                    $antrian->nomor_bpjs               = $reservasi_online->nomor_asuransi_bpjs;
                    $antrian->no_telp                  = $reservasi_online->no_telp;
                    $antrian->tanggal_lahir            = $reservasi_online->tanggal_lahir;
                    $antrian->alamat                   = $reservasi_online->alamat;
                    $antrian->registrasi_pembayaran_id = $reservasi_online->registrasi_pembayaran_id;
                    $antrian->pasien_id                = $reservasi_online->pasien_id;
                    $antrian->kartu_asuransi_image     = $reservasi_online->kartu_asuransi_image;
                    $antrian->data_bpjs_cocok          = $reservasi_online->data_bpjs_cocok;
                    $antrian->reservasi_online         = 1;
                    $antrian->sudah_hadir_di_klinik    = 0;
                    $antrian->qr_code_path_s3          = $this->generateQrCodeForOnlineReservation($antrian);
                    $antrian->save();
                    $antrian->antriable_id             = $antrian->id;
                    $antrian->save();

                    Log::info(2858);
                    Log::info('data_bpjs_cocok =' . $antrian->data_bpjs_cocok);

                    $response = $this->pesanBalasanBilaTerdaftar( $antrian, true );

                    $urlFile =  \Storage::disk('s3')->url($antrian->qr_code_path_s3) ;
                    echo 'Mohon ditunggu sesaat lagi sistem akan mengirim qr code anda';
                    $this->sendWhatsappImage( $this->no_telp, $urlFile, $response );
                    return false;
                }
                if (
                    ( $this->message == 'ulangi' && $this->tenant->iphone_whatsapp_button_available ) ||
                    ( !is_null( $this->message ) && $this->message[0] == '2' && !$this->tenant->iphone_whatsapp_button_available )
                ) {
                    $whatsapp_bot_id = $reservasi_online->whatsapp_bot_id;
                    ReservasiOnline::create([
                        'no_telp'         => $this->no_telp,
                        'whatsapp_bot_id' => $whatsapp_bot_id,
                        'konfirmasi_sdk'  => 1,
                    ]);
                }
            } else {
                $input_tidak_tepat = true;
            }
        }

        if (
            !is_null( $reservasi_online ) &&
            !$reservasi_online->konfirmasi_sdk
        ) {
            Log::info(2960);
            $message = $this->tanyaSyaratdanKetentuan();
        } else if (
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            is_null( $reservasi_online->jenis_antrian_id )
        ) {
            Log::info(2967);
            $message = $this->pertanyaanPoliYangDituju();
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            is_null( $reservasi_online->registrasi_pembayaran_id )
        ) {
            Log::info(2975);
            $message = $this->pertanyaanPembayaranPasien();
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id ) &&
            is_null( $reservasi_online->register_previously_saved_patient ) 
        ) {
            Log::info(2984);
            $message = $this->pesanUntukPilihPasien();
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id ) &&
            !is_null( $reservasi_online->register_previously_saved_patient ) &&
            is_null( $reservasi_online->nomor_asuransi_bpjs ) 
        ) {
            Log::info(2994);
            $message = $this->tanyaNomorBpjsPasien();
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id ) &&
            !is_null( $reservasi_online->register_previously_saved_patient ) &&
            !is_null( $reservasi_online->nomor_asuransi_bpjs ) &&
            is_null( $reservasi_online->nama ) 
        ) {
            Log::info(3005);
            $message = $this->tanyaNamaLengkapPasien();
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id ) &&
            !is_null( $reservasi_online->register_previously_saved_patient ) &&
            !is_null( $reservasi_online->nomor_asuransi_bpjs ) &&
            !is_null( $reservasi_online->nama ) &&
            is_null( $reservasi_online->tanggal_lahir ) 
        ) {
            Log::info(3017);
            $message = $this->tanyaTanggalLahirPasien();
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id ) &&
            !is_null( $reservasi_online->register_previously_saved_patient ) &&
            !is_null( $reservasi_online->nomor_asuransi_bpjs ) &&
            !is_null( $reservasi_online->nama ) &&
            !is_null( $reservasi_online->tanggal_lahir ) &&
            is_null( $reservasi_online->alamat )
        ) {
            Log::info(3030);
            $message = $this->tanyaAlamatLengkapPasien();
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id )&&
            !is_null( $reservasi_online->register_previously_saved_patient ) &&
            !is_null( $reservasi_online->nomor_asuransi_bpjs ) &&
            !is_null( $reservasi_online->nama ) &&
            !is_null( $reservasi_online->tanggal_lahir ) &&
            !is_null( $reservasi_online->alamat ) &&
            is_null( $reservasi_online->kartu_asuransi_image )
        ) {
            Log::info(3044);
            $message = $this->tanyaKartuAsuransiImage($reservasi_online);
        } else if ( 
            !is_null( $reservasi_online ) &&
            $reservasi_online->konfirmasi_sdk &&
            !is_null( $reservasi_online->jenis_antrian_id ) &&
            !is_null( $reservasi_online->registrasi_pembayaran_id )&&
            !is_null( $reservasi_online->register_previously_saved_patient ) &&
            !is_null( $reservasi_online->nomor_asuransi_bpjs ) &&
            !is_null( $reservasi_online->nama ) &&
            !is_null( $reservasi_online->tanggal_lahir ) &&
            !is_null( $reservasi_online->alamat ) &&
            !is_null( $reservasi_online->kartu_asuransi_image )
        ) {
            Log::info(3058);
            $message = $this->tanyaLanjutkanAtauUlangi($reservasi_online);
        }

        if (!empty(trim($message))) {
            Log::info(2941);
            $message .= PHP_EOL;
            $message .= $this->samaDengan();
            $message .= $this->batalkan();
            $message .= " reservasi";
            $message .= PHP_EOL;
            if ( $input_tidak_tepat ) {
                $message .= $this->pesanMintaKlienBalasUlang();
            }
            Log::info(2950);
            echo $message;
        } else {
        }
    }
    public function pesanMintaKlienBalasUlang(){
        $message = PHP_EOL;
        $message .= PHP_EOL;
        if (
            is_null( $this->pesan_error ) ||
            empty( trim(  $this->pesan_error  ) )
        ) {
            $message .= '_Balasan yang kakak masukkan tidak dikenali_';
            $message .= PHP_EOL;
            $message .= '_Mohon ulangi_';
        } else {
            $message .= $this->pesan_error;
        }

        return $message;
    }
    public function lanjutkanRegistrasiPembayaran($model){
        if (
            ( $this->message == 'biaya pribadi' && $this->tenant->iphone_whatsapp_button_available ) ||
            ( !is_null( $this->message ) && $this->message[0] == '1' && !$this->tenant->iphone_whatsapp_button_available )
        ) {
            $model->registrasi_pembayaran_id = 1;
            $model->kartu_asuransi_image     = '0';
        }
        if (
            ( $this->message == 'bpjs' && $this->tenant->iphone_whatsapp_button_available ) ||
            ( !is_null( $this->message ) &&  $this->message[0] == '2' && !$this->tenant->iphone_whatsapp_button_available )
        ) {
            $model->registrasi_pembayaran_id  = 2;
        }
        if (
            ( $this->message == 'lainnya' && $this->tenant->iphone_whatsapp_button_available ) ||
            (  !is_null( $this->message ) && $this->message[0] == '3' && !$this->tenant->iphone_whatsapp_button_available )
        ) {
            $model->registrasi_pembayaran_id  = 3;
        }

        $data = $this->queryPreviouslySavedPatientRegistry();

        if (count($data) < 1) {
            $model->register_previously_saved_patient  = 0;
        }

        $model->save();
        return $model;
    }

    public function validasiRegistrasiPembayaran(){
        return
                ( $this->message    == 'biaya pribadi' && $this->tenant->iphone_whatsapp_button_available ) ||
                ( $this->message    == 'bpjs' && $this->tenant->iphone_whatsapp_button_available) ||
                ( $this->message    == 'lainnya' && $this->tenant->iphone_whatsapp_button_available ) ||
                (  !is_null( $this->message ) && $this->message[0] == '1' && !$this->tenant->iphone_whatsapp_button_available ) ||
                (  !is_null( $this->message ) && $this->message[0] == '2' && !$this->tenant->iphone_whatsapp_button_available) ||
                (  !is_null( $this->message ) && $this->message[0] == '3' && !$this->tenant->iphone_whatsapp_button_available );
    }

    public function tanyaLanjutkanAtauUlangi($model){
        $message = $this->uraianPengisian( $model );
        $message .= $this->lanjutAtauUlangi();
        return $message;
    }
    public function uraianPengisian($model){
        $response='';
        if (
            !is_null( $model->nama ) ||
            !is_null( $model->registrasi_pembayaran_id ) ||
            !is_null( $model->tanggal_lahir )
        ) {
            $response .=  "*Uraian Pengisian Anda*";
            $response .= PHP_EOL;
            $response .= PHP_EOL;
        }
        if ( !is_null( $model->nama ) ) {
            $response .= 'Nama Pasien: ' . ucwords($model->nama)  ;
            $response .= PHP_EOL;
        }
        if ( !is_null( $model->registrasi_pembayaran_id ) ) {
            $response .= 'Pembayaran : ';
            $response .= ucwords($model->registrasiPembayaran->pembayaran);
            $response .= PHP_EOL;
        }
        if ( !is_null( $model->tanggal_lahir ) ) {
            $response .= 'Tanggal Lahir : '.  Carbon::parse($model->tanggal_lahir)->format('d M Y');;
            $response .= PHP_EOL;
        }
        if (
            !is_null( $model->nama ) ||
            !is_null( $model->registrasi_pembayaran_id) ||
            !is_null( $model->tanggal_lahir )
        ) {
            $response .= $this->samaDengan();
            $response .= PHP_EOL;
            $response .= PHP_EOL;
        }
        return $response;
    }
    public function lanjutAtauUlangi(){
        $text = '1. Lanjutkan';
        $text .= PHP_EOL;
        $text .= '2. Ulangi';
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= "Balas dengan angka *1 atau 2* sesuai informasi di atas";
        return $text;
    }
    public function tanyaNamaAtauNomorBpjsPasien($model){
        
        if ( $model->registrasi_pembayaran_id !== 2 ) {
            $model->nomor_asuransi_bpjs = 0;
            $model->save();
            return $this->tanyaNamaLengkapPasien();
        } else {
            return $this->tanyaNomorBpjsPasien();
        }
    }

    public function tanyaAlamatLengkapPasien(){
         return 'Bisa dibantu *Alamat Lengkap* pasien?';
    }

    public function pertanyaanPoliYangDituju(){
        $message = 'Bisa dibantu poli yang dituju?';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $jumlah_antrian = $this->jumlahAntrian();
        $message .= '1. Dokter Umum (ada ' . $jumlah_antrian['dokter_umum']. ' antrian)';
        $message .= PHP_EOL;
        $message .= '2. Dokter Gigi (ada ' . $jumlah_antrian['dokter_gigi']. ' antrian)';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Balas dengan angka *1 atau 2* sesuai dengan informasi di atas';
        return $message;
    }

	public function antrianPost($id){
		$antrians = Antrian::with('jenis_antrian')->where('created_at', 'like', date('Y-m-d') . '%')
							->where('jenis_antrian_id',$id)
							->where('tenant_id', 1)
							->orderBy('nomor', 'desc')
							->first();

		if ( is_null( $antrians ) ) {
			$antrian                   = new Antrian;
			$antrian->nomor            = 1 ;
			$antrian->tenant_id        = 1 ;
			$antrian->nomor_bpjs       = $this->input_nomor_bpjs;
			$antrian->jenis_antrian_id = $id ;

		} else {
			$antrian_terakhir          = $antrians->nomor + 1;
			$antrian                   = new Antrian;
			$antrian->tenant_id        = 1 ;
			$antrian->nomor            = $antrian_terakhir ;
			$antrian->nomor_bpjs       = $this->input_nomor_bpjs;
			$antrian->jenis_antrian_id = $id ;
		}
		$antrian->antriable_id   = $antrian->id;
		$antrian->kode_unik      = $this->kodeUnik();
		$antrian->antriable_type = 'App\\Models\\Antrian';
		$antrian->save();

		$this->updateJumlahAntrian(false, null);
		return $antrian;
	}

    private function kodeUnik()
    {
        $kode_unik = substr(str_shuffle(MD5(microtime())), 0, 5);
        while (Antrian::where('created_at', 'like', date('Y-m-d') . '%')->where('kode_unik', $kode_unik)->exists()) {
            $kode_unik = substr(str_shuffle(MD5(microtime())), 0, 5);
        }
        return $kode_unik;
    }

	public function updateJumlahAntrian($panggil_pasien, $ruangan){
		event(new FormSubmitted($panggil_pasien, $ruangan));
	}
    public function samaDengan(){
        return '==================';
    }
    public function whatsappKonsultasiEstetikExists(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(5);
    }
    public function prosesKonsultasiEstetik(){
        $konsultasi_estetik_online = KonsultasiEstetikOnline::where('no_telp', $this->no_telp)
             ->where('whatsapp_bot_id', $this->whatsapp_bot->id)
             ->first();
        $message = '';
        $input_tidak_tepat = false;

        if( is_null( $konsultasi_estetik_online ) ){
            $this->whatsapp_bot->delete();
        }
        if (
            !is_null( $konsultasi_estetik_online ) &&
            $this->message == 'batalkan'
        ) {
            $konsultasi_estetik_online->delete();
            $this->whatsapp_bot->delete();
            echo 'Reservasi online dibatalkan';
        } else if (
            !is_null( $konsultasi_estetik_online ) &&
            !$konsultasi_estetik_online->konfirmasi_sdk
        ) {
            if ( 
                $this->message == 'ya' || 
                $this->message == 'iy' || 
                $this->message == 'iya' || 
                $this->message == 'y'
            ) {
                $konsultasi_estetik_online->konfirmasi_sdk = 1;
                $konsultasi_estetik_online->save();
                $message = $this->tanyaNamaLengkapAtauPilihPasien( $konsultasi_estetik_online );
            }
        } else if ( 
            !is_null( $konsultasi_estetik_online ) &&
            $konsultasi_estetik_online->konfirmasi_sdk &&
            is_null( $konsultasi_estetik_online->register_previously_saved_patient ) 
        ) {
            $data = $this->queryPreviouslySavedPatientRegistry();
            $dataCount = count($data);
            if ( (int)$this->message <= $dataCount && (int)$this->message > 0  ) {
                $konsultasi_estetik_online->register_previously_saved_patient = $this->message;
                $konsultasi_estetik_online->pasien_id                         = $pasien->pasien_id;
                $konsultasi_estetik_online->nama                              = $pasien->nama;
                $konsultasi_estetik_online->alamat                            = $pasien->alamat;
                $konsultasi_estetik_online->tanggal_lahir                     = $pasien->tanggal_lahir;
                $message                                                      = $this->tanyaLanjutkanAtauUlangi($konsultasi_estetik_online);
            } else {
                $konsultasi_estetik_online->register_previously_saved_patient = $this->message;
                $message = $this->tanyaNamaLengkapPasien();
            }
            $konsultasi_estetik_online->save();
        } else if ( 
            !is_null( $konsultasi_estetik_online ) &&
            $konsultasi_estetik_online->konfirmasi_sdk &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            is_null( $konsultasi_estetik_online->nama ) 
        ) {
            if ( validateName( $this->message ) ) {
                $konsultasi_estetik_online->nama  = ucwords(strtolower($this->message));;
                $konsultasi_estetik_online->save();
                $message = $this->tanyaTanggalLahirPasien();
            } else {
                $message =  $this->tanyaNamaLengkapPasien();
                $input_tidak_tepat = true;
            }
        } else if ( 
            !is_null( $konsultasi_estetik_online ) &&
            $konsultasi_estetik_online->konfirmasi_sdk &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            is_null( $konsultasi_estetik_online->tanggal_lahir ) 
        ) {
            $tanggal = $this->convertToPropperDate();
            if (!is_null( $tanggal )) {
                $konsultasi_estetik_online->tanggal_lahir  = $tanggal;
                $konsultasi_estetik_online->save();
                $message = $this->tanyaAlamatLengkapPasien();
            } else {
                $message =  $this->tanyaTanggalLahirPasien();
                $input_tidak_tepat = true;
            }
        } else if ( 
            !is_null( $konsultasi_estetik_online ) &&
            $konsultasi_estetik_online->konfirmasi_sdk &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            is_null( $konsultasi_estetik_online->alamat )
        ) {
            $konsultasi_estetik_online->alamat  = $this->message;
            $konsultasi_estetik_online->save();
            $message = $this->tanyaLanjutkanAtauUlangi( $konsultasi_estetik_online );
        } else if ( 
            !is_null( $konsultasi_estetik_online ) &&
            $konsultasi_estetik_online->konfirmasi_sdk &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            is_null( $konsultasi_estetik_online->registering_confirmation )
        ) {
            if (
                $this->message == '1' ||
                $this->message == '2'
            ) {
                if ( $this->message == '1' ) {
                    $konsultasi_estetik_online->registering_confirmation = $this->message;
                    $konsultasi_estetik_online->save();
                    $message = $this->tanyaKeluhanUtama();
                } else if ( $this->message == '2' ){
                    $konsultasi_estetik_online = KonsultasiEstetikOnline::create([
                        'konfirmasi_sdk' => 1,
                        'whatsapp_bot_id' => $this->whatsapp_bot->id
                    ]);
                    $message = $this->tanyaNamaLengkapAtauPilihPasien( $konsultasi_estetik_online );
                }
            } else {
                $message = $this->tanyaLanjutkanAtauUlangi( $konsultasi_estetik_online );
                $message .= $this->pesanMintaKlienBalasUlang();
            }
        } else if ( 
            !is_null( $konsultasi_estetik_online ) &&
            $konsultasi_estetik_online->konfirmasi_sdk &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            !is_null( $konsultasi_estetik_online->registering_confirmation ) &&
            is_null( $konsultasi_estetik_online->keluhan_utama )
        ) {
            $konsultasi_estetik_online->keluhan_utama = $this->message;
            $konsultasi_estetik_online->save();
            $message = $this->tanyaPeriodeKeluhanUtama();
        } else if ( 
            !is_null( $konsultasi_estetik_online ) &&
            $konsultasi_estetik_online->konfirmasi_sdk &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            !is_null( $konsultasi_estetik_online->registering_confirmation ) &&
            !is_null( $konsultasi_estetik_online->keluhan_utama ) &&
            is_null( $konsultasi_estetik_online->periode_keluhan_utama_id )
        ) {
            if (
                $this->message == '1' || 
                $this->message == '2' || 
                $this->message == '3' || 
                $this->message == '4' || 
                $this->message == '5'
            ) {
                $konsultasi_estetik_online->periode_keluhan_utama_id = $this->message;
                $konsultasi_estetik_online->save();

                $message = "Bisa dibantu infokan pengobatan yang sudah dijalani sebelumnya?";
            } else {
                $message = $this->tanyaPeriodeKeluhanUtama();
                $message .= $this->pesanMintaKlienBalasUlang();
            }
        } else if ( 
            !is_null( $konsultasi_estetik_online ) &&
            $konsultasi_estetik_online->konfirmasi_sdk &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            !is_null( $konsultasi_estetik_online->registering_confirmation ) &&
            !is_null( $konsultasi_estetik_online->keluhan_utama )&&
            !is_null( $konsultasi_estetik_online->periode_keluhan_utama_id )&&
            is_null( $konsultasi_estetik_online->pengobatan_sebelumnya )
        ) {
            $konsultasi_estetik_online->pengobatan_sebelumnya = $this->message;
            $konsultasi_estetik_online->save();
            $message = $this->pertanyaanJenisKulit();
        } else if ( 
            !is_null( $konsultasi_estetik_online ) &&
            $konsultasi_estetik_online->konfirmasi_sdk &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            !is_null( $konsultasi_estetik_online->registering_confirmation ) &&
            !is_null( $konsultasi_estetik_online->keluhan_utama )&&
            !is_null( $konsultasi_estetik_online->periode_keluhan_utama_id )&&
            !is_null( $konsultasi_estetik_online->pengobatan_sebelumnya )&&
            is_null( $konsultasi_estetik_online->jenis_kulit_id )
        ) {
            if (
                $this->message == '1' || 
                $this->message == '2' || 
                $this->message == '3' || 
                $this->message == '4' || 
                $this->message == '5'
            ) {
                $konsultasi_estetik_online->jenis_kulit_id = $this->message;
                $konsultasi_estetik_online->save();

                $text = "Silahkan difoto bagian kulit yang dikeluhkan";
                echo $text;
                /* echo "Terima kasih atas inputnya. Pesan kakak akan dibalas ketika dokter estetik sedang berpraktik"; */
            } else {
                $message = $this->pertanyaanJenisKulit();
                $message .= $this->pesanMintaKlienBalasUlang();
            }
        } else if ( 
            !is_null( $konsultasi_estetik_online ) &&
            $konsultasi_estetik_online->konfirmasi_sdk &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            !is_null( $konsultasi_estetik_online->registering_confirmation ) &&
            !is_null( $konsultasi_estetik_online->keluhan_utama )&&
            !is_null( $konsultasi_estetik_online->periode_keluhan_utama_id )&&
            !is_null( $konsultasi_estetik_online->pengobatan_sebelumnya )&&
            !is_null( $konsultasi_estetik_online->jenis_kulit_id )
        ) {
            if ( $this->isPicture() ) {
                $filename = $this->uploadImage();
                $konsultasi_estetik_online->gambarPeriksa()->create([
                    'nama' => $filename,
                    'keterangan' => 'konsultasi estetik online'
                ]);
                $message = $this->nextPicturePlease();
                $message .= $this->syaratFoto();
            } else if ( $this->message == 'selesai' ) {
                $this->whatsapp_bot->delete();
                echo "Terima kasih atas inputnya. Pesan kakak akan dibalas ketika dokter estetik sedang berpraktik";
            } else  {
                echo "Balasan yang kakak buat bukan gambar. Mohon masukkan gambar";
            }
        }

        if (!empty(trim($message))) {
            $message .= PHP_EOL;
            $message .= $this->samaDengan();
            $message .= $this->batalkan();
            if ( $input_tidak_tepat ) {
                $message .= $this->pesanMintaKlienBalasUlang();
            }
            echo $message;
        } else {
        }
    }
    public function isPicture(){
        if ( Input::get('messageType') == 'image' ) {
            return true;
        }
        return false;
    }
    

    public function tanyaPeriodeKeluhanUtama(){
        $message = "Sudah berapa lama keluhan tersebut dialami";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "1. Kurang dari seminggu";
        $message .= PHP_EOL;
        $message .= "2. 1 minggu - 1 bulan";
        $message .= PHP_EOL;
        $message .= "3. 1 bulan - 3 bulan";
        $message .= PHP_EOL;
        $message .= "4. 3 bulan - 1 tahun";
        $message .= PHP_EOL;
        $message .= "5. Lebih dari 1 tahun";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Balas dengan angka *1,2,3,4 atau 5* sesuai informasi di atas";
        $message .= PHP_EOL;
        return $message;
    }
    public function pertanyaanJenisKulit(){
        $message = 'Bisa dibantu jenis kulit kakak?';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= '1. Normal';
        $message .= PHP_EOL;
        $message .= '2. Sensitif';
        $message .= PHP_EOL;
        $message .= '3. Kering';
        $message .= PHP_EOL;
        $message .= '4. Berminyak';
        $message .= PHP_EOL;
        $message .= '5. Tidak Tahu';
        $message .= PHP_EOL;
        return $message;
    }
    public function tanyaKeluhanUtama(){
        return "Bisa dibantu sebutkan keluhan yang dialami?";
    }
    public function tanyaNamaLengkapAtauPilihPasien($konsultasi_estetik_online){
        $data = $this->queryPreviouslySavedPatientRegistry();
        if (count($data)) {
            $message = $this->pesanUntukPilihPasien();
        } else {
            $konsultasi_estetik_online->register_previously_saved_patient = 0;
            $konsultasi_estetik_online->registering_confirmation = 0;
            $konsultasi_estetik_online->save();
            $message = $this->tanyaNamaLengkapPasien();
        }
        return $message;
    }
    public function syaratFoto(){
        $text = PHP_EOL;
        $text .= PHP_EOL;
        $text .= "_Ambil gambar foto tampak kanan, kiri dan tampak depan_";
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= "_Ambil gambar satu per satu_";
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= "_Foto tanpa filter dan tanpa make up_";
        return $text;
    }

    public function whatsappGambarPeriksaExists(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(7);
    }
    public function prosesGambarPeriksa(){
        $antrian_periksa = AntrianPeriksa::where('whatsapp_bot_id', $this->whatsapp_bot->id)->first();
        if ( is_null( $antrian_periksa ) ) {
            $this->whatsapp_bot->delete();
            echo "Pemeriksaan telah seleai, Gambar tidak dimasukkan ke pemeriksaan";

        } else if ( $this->isPicture() ) {
            $filename = $this->uploadImage();
            $antrian_periksa->gambarPeriksa()->create([
                'nama' => $filename,
                'keterangan' => 'Gambar Periksa '
            ]);

            event(new GambarSubmitted($antrian_periksa->id));
            echo $this->nextPicturePlease();

        } else if ( $this->message == 'selesai' ) {
            $this->whatsapp_bot->delete();
            echo "Terima kasih atas inputnya. Pesan kakak akan dibalas ketika dokter estetik sedang berpraktik";
        } else  {
            echo "Balasan yang kakak buat bukan gambar. Mohon masukkan gambar";
        }
    }
    public function nextPicturePlease(){
        
        $message =  "Silahkan balas dengan gambar berikutnya";
        $message .=  PHP_EOL;
        $message .=  PHP_EOL;
        $message .=  "atau";
        $message .=  PHP_EOL;
        $message .=  PHP_EOL;
        $message .= 'ketik *selesai* untuk mengakhiri';
        return $message;
    }

    public function cekListMingguanExists(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(8);
    }
    public function cekListMingguanInputExists(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(9);
    }
    public function prosesCekListMingguan(){
        return $this->prosesCekListDilakukan(2,8,9); // bulanan
    }
    public function prosesCekListMingguanInput(){
        $this->prosesCekListDikerjakanInput(2,8,9);
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function jumlahAntrian()
    {
        $antrians = Antrian::whereRaw(
                                "(
                                    antriable_type = 'App\\\Models\\\AntrianPeriksa' or
                                    antriable_type = 'App\\\Models\\\Antrian' or
                                    antriable_type = 'App\\\Models\\\AntrianPoli'
                                )"
                                )
                                ->where('created_at', 'like', date('Y-m-d') . '%')
                                ->get();

        $jumlah_antrian_dokter_umum = 0;
        $jumlah_antrian_dokter_gigi = 0;
        foreach ($antrians as $antrian) {
            if ( $antrian->jenis_antrian_id == 1 ) {
                $jumlah_antrian_dokter_umum++;
            } else if (  $antrian->jenis_antrian_id == 2  ){
                $jumlah_antrian_dokter_gigi++;
            }
        }

        return [
            'dokter_umum' => $jumlah_antrian_dokter_umum,
            'dokter_gigi' => $jumlah_antrian_dokter_gigi
        ];
    }

    private function sendWhatsappImage($noTelp, $urlFile, $caption)
    {
        $curl = curl_init();
        $token = env('WABLAS_TOKEN');
        $data = [
            'phone'   => $noTelp,
            'image'   => $urlFile,
            'caption' => $caption,
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER,
            array(
                "Authorization: $token",
            )
        );
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_URL,  "https://pati.wablas.com/api/send-image");
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $result = curl_exec($curl);
        curl_close($curl);
    }
    /**
     * undocumented function
     *
     * @return void
     */
    public function generateQrCodeForOnlineReservation($antrian){

        $text = 'A' . $antrian->id;
        $filename = $text. '.png';
        $qr_code = QrCode::create($text)
                         ->setSize(600)
                         ->setMargin(40)
                         ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh);

        $writer = new PngWriter;

        $result = $writer->write($qr_code);

        // Output the QR code image to the browser
        /* header("Content-Type: " . $result->getMimeType()); */
        $destination_path = 'image/online_reservation/qr_code/';

        \Storage::disk('s3')->put($destination_path. $filename,  $result->getString() );

        return $destination_path.$filename;

        /* echo $result->getString(); */

        // Save the image to a file
        /* $result->saveToFile("qr-code.png"); */
    }

    /**
     * undocumented function
     *
     * @return void
     */
    private function tanyaKartuAsuransiImage($reservasi_online){
        $message = 'Bisa dibantu kirimkan';
        $message .=  PHP_EOL;
        $message .= $reservasi_online->registrasi_pembayaran_id == 2 ? '*Foto Kartu BPJS*' :  '*Foto Kartu Asuransi*';
        $message .= ' pasien';
        return $message;

    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function batalkanAntrianExists(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(10);
    }
    private function bpjsNumberInfomationInquiryExists(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(11);
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function batalkanAntrian(){
        $from = date('Y-m-d 00:00:00');
        $to = date('Y-m-d 23:59:59');
        if ( $this->message == 1 ) {
            echo $this->hapusAntrianWhatsappBotReservasiOnline();
        } else if(
             $this->message == 2
        ) {
            WhatsappBot::whereRaw("created_at between '{$from}' and '{$to}'")
                ->where('no_telp', $this->no_telp)
                ->delete();
            echo 'Reservasi Online Dilanjutkan';
        } else {
            $message = $this->tanyaApakahMauMembatalkanReservasiOnline();
            $message .= $this->samaDengan();
            $message .= "_Balasan yang anda masukkan tidak dikenali. Mohon diulangi_";
            echo $message;

        }
    }

    /**
     * undocumented function
     *
     * @return void
     */
    private function tanyaApakahMauMembatalkanReservasiOnline(){

        $antrians = Antrian::where('no_telp', $this->no_telp)
            ->where('created_at', 'like', date('Y-m-d') . '%')
            ->get();

        $nomor_antrians = [];

        foreach ($antrians as $antrian) {
            $nomor_antrians[] = $antrian->nomor_antrian;
        }

        $text = '';

        foreach ($nomor_antrians as $k => $nomor) {
            if ($k) {
                $text .= ',' . $nomor;
            } else {
                $text .= $nomor;
            }
        }

        $message = 'Anda akan membatalkan antrian ' . $text;
        $message .= PHP_EOL;
        $message .= 'Apakah anda ingin yakin ingin membatalkan antrian tersebut?';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= '1. Ya, batalkan ';
        $message .= PHP_EOL;
        $message .= '2. Tidak, jangan batalkan ';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Balas dengan angka *1 atau 2* sesuai informasi di atas';
        return $message;


    }
    public function batalkan(){
        $message = PHP_EOL;
        $message .= 'Ketik *batalkan* untuk membatalkan';
        return $message;
    }
    private function waktuTunggu($sisa_antrian)
    {
        $from = $sisa_antrian * 3;
        $to = $sisa_antrian * 10;
        return $from . ' - ' . $to;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function tanyaSyaratdanKetentuan()
    {
			$message = '*KLINIK JATI ELOK*' ;
			$message .= PHP_EOL;
			$message .= $this->samaDengan();
			$message .= PHP_EOL;
            $message .= 'Kakak akan melakukan registrasi secara online';
            $message .= PHP_EOL;
            $message .= 'Reservasi ini akan ';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= '*Dibatalkan secara otomatis*';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= 'Apabila antrian telah terlewat';
            $message .= PHP_EOL;
            $message .= $this->samaDengan();
            $message .= PHP_EOL;
            $message .= 'Pastikan kehadiran anda di klinik *30 menit* sebelum antrian anda dipanggil';
            $message .= PHP_EOL;
            $message .= 'Karena akan ada pemeriksaan fisik oleh perawat sebelum diperiksa oleh dokter';
            $message .= PHP_EOL;
            $message .= $this->samaDengan();
            $message .= PHP_EOL;
            $message .= 'Jika setuju balas *ya* untuk melanjutkan';
            $message .= $this->batalkan();
            return $message;
    }
    public function konfirmasiPembatalan(){
        $message = $this->tanyaApakahMauMembatalkanReservasiOnline();
        $whatsapp_bot = WhatsappBot::create([
            'no_telp' => $this->no_telp,
            'whatsapp_bot_service_id' => 10 //registrasi online
        ]);
        return $message;
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function hapusAntrianWhatsappBotReservasiOnline()
    {
        $from = date('Y-m-d 00:00:00');
        $to = date('Y-m-d 23:59:59');
        Antrian::whereRaw("created_at between '{$from}' and '{$to}'")
            ->where('no_telp', $this->no_telp)
            ->delete();
        ReservasiOnline::whereRaw("created_at between '{$from}' and '{$to}'")
            ->where('no_telp', $this->no_telp)
            ->delete();
        WhatsappBot::whereRaw("created_at between '{$from}' and '{$to}'")
            ->where('no_telp', $this->no_telp)
            ->delete();
        return 'Reservasi Online Dibatalkan';
    }

    public function sudahAdaAntrianUntukJenisAntrian($jenis_antrian_id){
        $from = date('Y-m-d 00:00:00');
        $to = date('Y-m-d 23:59:59');
        return Antrian::whereRaw(
                                "(
                                    antriable_type = 'App\\\Models\\\AntrianPeriksa' or
                                    antriable_type = 'App\\\Models\\\Antrian' or
                                    antriable_type = 'App\\\Models\\\AntrianPoli'
                                )"
                                )
                                ->whereRaw("created_at between '{$from}' and '{$to}'")
                                ->where('jenis_antrian_id', $jenis_antrian_id)
                                ->count();
    }
    public function registrasiAntrianOnline(){
        $whatsapp_bot = WhatsappBot::create([
            'no_telp' => $this->no_telp,
            'whatsapp_bot_service_id' => 6 //registrasi online
        ]);

        $reservasi_online = ReservasiOnline::create([
            'no_telp'         => $this->no_telp,
            'whatsapp_bot_id' => $whatsapp_bot->id
        ]);

        return $this->tanyaSyaratdanKetentuan();
    }
    public function nomorKartuBpjsDitemukanDiPcareDanDataKonsisten($response, $pasien){
        $code     = $response['code'];
        $response = $response['response'];
        $result   = 0;
        if (
            $code >= 200 &&
            $code <= 299 &&
            $code !== 204
        ) {
            $tanggal_lahir_cocok = $pasien->tanggal_lahir == Carbon::createFromFormat('d-m-Y', $response['tglLahir'])->format("Y-m-d");
            $ktp_oke = true;
            if (
                 hitungUsia( $pasien->tanggal_lahir ) > 16 && //jika usia 17 tahun keatas
                ( !empty( trim(  $pasien->nomor_ktp  ) ) && !is_null( $pasien->nomor_ktp ) ) // data nomor ktp terekam
            ) {
                $ktp_oke = 
                    ( !empty( trim(  $pasien->nomor_ktp  ) ) && !is_null( $pasien->nomor_ktp ) ) && // nomor ktp tidak null dan tidak empty
                    $pasien->nomor_ktp == $response['noKTP']; // nomor ktp di sistem dan di pcare sama
            } else if (
                 hitungUsia( $pasien->tanggal_lahir ) > 16 && //jika usia 17 tahun keatas
                ( empty( trim(  $pasien->nomor_ktp  ) ) || is_null( $pasien->nomor_ktp ) ) // data nomor ktp kosong
            ) {
                $ktp_oke = false;
            }
            if( $tanggal_lahir_cocok && $ktp_oke ){
                $result = 1;
            } 
        }
        return $result;
    }
    public function registrasiInformasiNomorKartuBpjs(){
        WhatsappBot::create([
            'no_telp' => $this->no_telp,
            'whatsapp_bot_service_id' => 11
        ]);
        echo $this->tanyaNomorBpjsPasien();
    }
    public function prosesBpjsNumberInquiry(){
        $bpjs = new BpjsApiController;
        $response = $bpjs->pencarianNoKartuValid( $this->message, true );
        $code = $response['code'];
        $message = $response['response'];
        $this->pesan_error = pesanErrorValidateNomorAsuransiBpjs( $this->message );
        if ( 
            is_null( $this->pesan_error ) ||
            empty( trim(  $this->pesan_error  ) )
        ) {
            if ( 
                $code == 204
            ) {
                $this->pesan_error =  'Nomor tersebut tidak ditemukan di sistem BPJS';
                echo $this->pesanErrorBpjsInformationInquiry();
            } else if (
                $code >= 200 &&
                $code <= 299
            ) {
                if ( !is_null( $message ) ) {
                    $text = $this->message;
                    $text .= PHP_EOL;
                    $text .= 'Nama Peserta : ' . $message['nama'];
                    $text .= PHP_EOL;
                    $text .= 'Nama Provider : ' . $message['kdProviderPst']['nmProvider'];
                    $text .= PHP_EOL;
                    if ( $message['aktif'] ) {
                        $text .= 'Kartu dalam keadaan AKTIF';
                    } else {
                        $text .= 'Kartu TIDAK AKTIF karena :';
                        $text .= PHP_EOL;
                        $text .= $message['ketAktif'];
                    }
                    echo $text;
                } else {
                    echo "Data tidak ditemukan";
                }
                WhatsappBot::where('no_telp', $this->no_telp)->delete();
            }
        } else {
            echo $this->pesanErrorBpjsInformationInquiry();
        }
    }
    public function pesanErrorBpjsInformationInquiry(){
        $message = $this->tanyaNomorBpjsPasien();
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= $this->pesan_error;
        return $message;
    }
    public function validasiProviderError($nomor_asuransi_bpjs){
        $text = '_Kartu BPJS dengan nomor ' . $nomor_asuransi_bpjs. ' tidak dapat digunakan di Klinik Jati Elok_';
        $text .= PHP_EOL;
        $text .= '_Jika menurut Anda ini adalah kesalahan silahkan mengambil antrian secara langsung_';
        $text .= PHP_EOL;
        $text .= '_Mohon maaf atas ketidaknyamanannya._';
        return $text;
    }
    public function validasiBpjsProviderSalah($nomor_asuransi_bpjs, $message){
        $text = '_Kartu BPJS dengan nomor ' . $nomor_asuransi_bpjs. ' tidak dapat digunakan di Klinik Jati Elok_';
        $text .= PHP_EOL;
        $text .= '_Karena saat ini nomor tersebut terdaftar di '.trim( $message['kdProviderPst']['nmProvider'] ).'_';
        $text .= PHP_EOL;
        $text .= '_Jika menurut Anda ini adalah kesalahan silahkan mengambil antrian secara langsung_';
        $text .= PHP_EOL;
        $text .= '_Mohon maaf atas ketidaknyamanannya._';
        return $text;
    }
    public function validasiBpjsDataTidakCocok($nomor_asuransi_bpjs, $message){
        $text = '_Kartu BPJS dengan nomor ' . $nomor_asuransi_bpjs. ' tidak dapat digunakan di Klinik Jati Elok_';
        $text .= PHP_EOL;
        $text .= '_Karena saat ini nomor tersebut terdaftar atas nama '.trim( $message['nama'] ).'_';
        $text .= PHP_EOL;
        $text .= '_Jika menurut Anda ini adalah kesalahan silahkan mengambil antrian secara langsung_';
        $text .= PHP_EOL;
        $text .= '_Mohon maaf atas ketidaknyamanannya._';
        return $text;
    }
}
