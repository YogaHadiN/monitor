<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use App\Models\AntrianPoli;
use App\Models\Message;
use App\Models\KeluhanEstetik;
use App\Models\JadwalKonsultasi;
use App\Models\BlokirWa;
use App\Models\WhatsappInbox;
use App\Models\BpjsApiLog;
use App\Models\Complain;
use App\Models\SchedulledReservation;
use App\Models\Ruangan;
use App\Models\PetugasPemeriksa;
use App\Models\NoTelp;
use App\Models\DentistReservation;
use App\Events\FormSubmitted;
use App\Events\OnsiteRegisteredEvent;
use App\Events\RefreshDiscussion;
use App\Events\RefreshChat;
use App\Events\GambarSubmitted;
use App\Models\ReservasiOnline;
use App\Models\Antrian;
use App\Models\CekListDikerjakan;
use App\Models\Tenant;
use App\Models\TipeKonsultasi;
use App\Models\WhatsappRegistration;
use App\Models\KonsultasiEstetikOnline;
use App\Models\WhatsappBot;
use App\Models\Asuransi;
use App\Models\Poli;
use Storage;
use Http;
use Endroid\QrCode\Encoding\Encoding;
use App\Models\AntrianPeriksa;
use Endroid\QrCode\Builder\Builder;
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
use App\Models\OnsiteRegistration;
use App\Models\Pasien;
use App\Models\User;
use App\Http\Controllers\AntrianPoliController;
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
	public $input_registrasi_pembayaran_id;
	public $whatsapp_recovery_index;
	public $whatsapp_complaint;
	public $message_reply;
	public $tenant;
	public $pesan_error;
	public $failed_therapy;
	public $whatsapp_bot;
	public $attachment_id;
	public $random_string;
	public $no_telp;
	public $message;
	public $essage_type;
	public $image_url;
	public $fonnte;
    public $whatsapp_satisfaction_survey;
    public $whatsapp_bpjs_dentist_registrations;
    public $jadwalGigi;

	public function __construct(){
        $this->message_reply = '';
        $this->fonnte = false;
        $this->image_url = null;
        $tenant_id = 1;
        session()->put('tenant_id', $tenant_id);
        $this->tenant = Tenant::find( $tenant_id );


        if ( !is_null( Input::get('payload') ) ) {
            $this->room_id      = Input::get('payload')['room']['id'];
            $no_telp            = Input::get('payload')['from']['email'];
            $this->message_type = Input::get('payload')['message']['type'];
            $this->no_telp      = $no_telp;
            $this->image_url    = null;
            if (
                $this->message_type == 'file_attachment'
            ) {
                $this->message_type = 'image';
                $this->image_url = Input::get('payload')['message']['payload']['url'];
                if (isset(
                    Input::get('payload')['message']['payload']['caption']
                )) {
                    $this->message       = Input::get('payload')['message']['payload']['caption'];
                } else {
                    $this->message       = "";
                }
            } else if (
                $this->message_type == 'text'
            ) {
                $this->message = $this->clean(Input::get('payload')['message']['text']);
            } else {
                $this->message = null;
            }
            $this->message = strtolower( $this->message );

        } else {
            $this->room_id      = null;
            $this->no_telp      = Input::get('phone');
            $this->message_type = Input::get('messageType');
            $this->image_url    = Input::get('url');
            $this->message      = strtolower(Input::get('message'));
        }
	}


    public function libur(){
        $message  = "Sehubungan dengan cuti bersama dan Hari Raya Idul Fitri 1446 H";
        $message .= PHP_EOL;
        $message .= "KLINIK JATI ELOK ";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Tutup tanggal ";
        $message .= PHP_EOL;
        $message .= "29 Maret";
        $message .= PHP_EOL;
        $message .= "dan akan buka kembali pada ";
        $message .= PHP_EOL;
        $message .= "5 April Pukul 13:00 WIB ";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Selama waktu libur tersebut pelayanan pengobatan Peserta BPJS akan dialihkan tanpa harus pindah Kepesertaan ke:";
        $message .= PHP_EOL;
        $message .= 'Tanggal 29 Maret, 30 Maret, 2 April, 3 April, 4 April:';
        $message .= PHP_EOL;
        $message .= 'Klinik Hadyan (Jl. Kalasan Raya No.54, Bencongan, Kec. Klp. Dua, Kabupaten Tangerang, Banten 15811)';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Tanggal 31 Maret, 1 April:';
        $message .= PHP_EOL;
        $message .= 'Klinik Sumadi Sumiti (Jl. Raya STPI Curug No.44, Curug Wetan, Kec. Curug, Kabupaten Tangerang, Banten 15820)';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Selamat Hari Raya Idul Fitri 1445H. ";
        $message .= PHP_EOL;
        $message .= "Minal Aidzin Wal Faidzin.";
        $message .= PHP_EOL;
        $message .= "Mohon maaf lahir dan batin";
        return $message;
    }

	public function webhook(){
        if ( BlokirWa::where('no_telp', $this->no_telp)->exists() ) {
            return;
        }

        if ( !is_null( $this->no_telp ) ) {
            $no_telp = NoTelp::firstOrCreate([
                'no_telp' => $this->no_telp,
            ]);
            $no_telp->room_id = $this->room_id;
            $no_telp->save();
            $no_telp->touch();
        }



        $this->whatsapp_bot = WhatsappBot::where('no_telp', $this->no_telp)
                                 ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
                                 ->first();

        if (
            ( date('w') < 1 ||  date('w') > 5)
        ) {
            $this->estetika_buka = false;
        }
        $no_telp_table = NoTelp::where('no_telp', $this->no_telp)->first();
        if ( $this->fonnte ) {
            $no_telp_table->fonnte = 1;
        } else {
            $no_telp_table->fonnte = 0;
            $no_telp_table->updated_at_qiscus = date('Y-m-d H:i:s');
        }
        $no_telp_table->save();


        if (
            !is_null( $this->message ) &&
            !is_null( $this->no_telp )
            /* && $this->no_telp == '6281381912803' */
        ) {
            $this->chatBotLog(__LINE__);
            $date_now = date('Y-m-d H:i:s');
            if (
                strtotime ($date_now) < strtotime( '2025-04-05 00:00:00'  )
                /* || $this->no_telp == '6281381912803' */
            ) {
                $this->autoReply( $this->libur() );
            } else {
                $this->chatBotLog(__LINE__);
                if (
                    $this->message == 'daftar' ||
                    $this->message == 'daptar' ||
                    $this->message == 'mau berobat' ||
                    $this->message == 'mau daftar' ||
                    $this->message == 'berobat' ||
                    $this->message == 'brobat' ||
                    $this->message == 'mau brobat' ||
                    $this->message == 'mau berobat sus'
                ) {
                    $this->chatBotLog(__LINE__);
                    $this->autoReply($this->registrasiAntrianOnline() );
                    return false;
                } else if (
                     str_contains($this->message ,'klinik_jati_elok_registration_')
                ) {
                    $random_string = str_replace("klinik_jati_elok_registration_", "", $this->message);
                    $this->random_string = $random_string;
                    if (
                        OnsiteRegistration::where('random_string', $random_string)->exists()
                    ) {
                        $this->autoReply($this->prosesOnsiteRegistration());
                    } else {
                        $this->autoReply("Nomor Registrasi Antrian Tidak Dikenali");
                    }

                    $this->chatBotLog(__LINE__);
                    return false;
                } else if (
                     str_contains($this->message ,'akhiri') ||
                     str_contains($this->message ,'ahiri')
                ) {
                    $this->chatBotLog(__LINE__);
                    $this->autoReply($this->akhiriChatWithAdmin() );
                    return false;
                } else if (str_contains( $this->message ,'jadwal usg' )) {
                    $this->chatBotLog(__LINE__);
                    Message::create([
                        'no_telp'       => $this->no_telp,
                        'message'       => $this->message,
                        'tanggal'       => date("Y-m-d H:i:s"),
                        'sending'       => 0,
                        'sudah_dibalas' => 1,
                        'tenant_id'     => 1,
                        'touched'       => 0
                    ]);

                    $pesan = $this->queryJadwalKonsultasiByTipeKonsultasi(4);
                    Message::create([
                        'no_telp'       => $this->no_telp,
                        'message'       => $pesan,
                        'tanggal'       => date("Y-m-d H:i:s"),
                        'sending'       => 1,
                        'sudah_dibalas' => 1,
                        'tenant_id'     => 1,
                        'touched'       => 0
                    ]);
                    Message::where('no_telp', $this->no_telp)->update([
                        'sudah_dibalas' => 1
                    ]);
                    $this->autoReply($pesan );
                    return false;
                } else if (str_contains( $this->message ,'jadwal dokter gigi' )) {
                    $this->chatBotLog(__LINE__);
                    Message::create([
                        'no_telp'       => $this->no_telp,
                        'message'       => $this->message,
                        'tanggal'       => date("Y-m-d H:i:s"),
                        'sending'       => 0,
                        'sudah_dibalas' => 1,
                        'tenant_id'     => 1,
                        'touched'       => 0
                    ]);

                    $pesan = $this->queryJadwalKonsultasiByTipeKonsultasi(2);
                    Message::create([
                        'no_telp'       => $this->no_telp,
                        'message'       => $pesan,
                        'tanggal'       => date("Y-m-d H:i:s"),
                        'sending'       => 1,
                        'sudah_dibalas' => 1,
                        'tenant_id'     => 1,
                        'touched'       => 0
                    ]);

                    Message::where('no_telp', $this->no_telp)->update([
                        'sudah_dibalas' => 1
                    ]);

                    $this->autoReply($pesan );

                    return false;
                } else if ( $this->message == 'komplain' ) {
                    $this->chatBotLog(__LINE__);
                    $this->autoReply($this->autoReplyComplainMessage() );
                    return false;
                } else if (
                    !$this->noTelpDalamChatWithAdmin() &&
                    (
                        $this->message == 'chat admin' ||
                        str_contains($this->message, 'mau nanya') ||
                        str_contains($this->message, 'mau tanya') ||
                        endsWith($this->message, '?')
                    )
                ) {
                    $this->chatBotLog(__LINE__);
                    if ( $this->message == 'chat admin' ) {
                        resetWhatsappRegistration($this->no_telp);
                        $this->autoReply( $this->chatAdmin() );
                        return false;
                    } else {
                       $message = $this->registerChatAdmin();
                       if (!is_null( $message )) {
                           return $message;
                       }
                       return $this->createWhatsappChat(); // buat pesan message
                    }
                } else if (
                    $this->message == 'batalkan'
                ) {
                    $this->chatBotLog(__LINE__);
                    $this->autoReply($this->hapusAntrianWhatsappBotReservasiOnline() );
                } else {
                    if ($this->message_type == 'text') {
                        if ( !is_null(  $this->message  ) ) {
                            WhatsappInbox::create([
                                'message' => $this->message,
                                'no_telp' => $this->no_telp
                            ]);
                        }
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

                    $this->tenant = Tenant::find(1);

                    if (
                        !is_null( $this->no_telp )
                    ) {
                        if ( !is_null( $this->whatsapp_registration ) ) {
                            $this->chatBotLog(__LINE__);
                            return $this->proceedRegistering(); //register untuk pendaftaran pasien
                        } else if (!is_null( $this->whatsapp_complaint )){
                            $this->chatBotLog(__LINE__);
                            return $this->registerWhatsappComplaint(); //register untuk pendataan complain pasien
                        } else if (!is_null( $this->failed_therapy  )) {
                            $this->chatBotLog(__LINE__);
                            return $this->registerFailedTherapy(); //register untuk pendataan kegagalan terapi
                        } else if (!is_null( $this->whatsapp_satisfaction_survey  )) {
                            $this->chatBotLog(__LINE__);
                            return $this->registerWhatsappSatisfactionSurvey(); //register untuk survey kepuasan pasien
                        } else if (!is_null( $this->whatsapp_recovery_index  )) {
                            $this->chatBotLog(__LINE__);
                            return $this->registerWhatsappRecoveryIndex(); //register untuk survey kesembuhan pasien
                        } else if (!is_null( $this->kuesioner_menunggu_obat  )) {
                            $this->chatBotLog(__LINE__);
                            return $this->registerKuesionerMenungguObat(); //register untuk survey kesembuhan pasien
                        } else if (!is_null( $this->whatsapp_bpjs_dentist_registrations  )) {
                            $this->chatBotLog(__LINE__);
                            return $this->registerWhatsappBpjsDentistRegistration(); //register untuk survey kesembuhan pasien
                        } else if ( $this->pertanyaanKonfirmasiPilihanPenghapusan()  ) {
                            $this->chatBotLog(__LINE__);
                            return $this->konfirmasiPilihanPenghapusan(); //register untuk survey kesembuhan pasien
                        } else if ( $this->whatsappMainMenuExists() ) { // jika main menu ada
                            $this->chatBotLog(__LINE__);
                            return $this->prosesMainMenuInquiry(); // proses pertanyaan main menu
                        } else if ( $this->cekListBulananExists() ) { // Jika ada cek list bulanan
                            $this->chatBotLog(__LINE__);
                            return $this->prosesCekListBulanan(); // proses cek list bulanan
                        } else if ( $this->cekListBulananInputExists() ) { // Jika ada cek list bulanan
                            $this->chatBotLog(__LINE__);
                            return $this->prosesCekListBulananInput(); // proses cek list bulanan
                        } else if ( $this->cekListMingguanExists() ) { // Jika ada cek list bulanan
                            $this->chatBotLog(__LINE__);
                            return $this->prosesCekListMingguan(); // proses cek list bulanan
                        } else if ( $this->cekListMingguanInputExists() ) { // Jika ada cek list bulanan
                            $this->chatBotLog(__LINE__);
                            return $this->prosesCekListMingguanInput(); // proses cek list bulanan
                        } else if( $this->rekonfirmationWaitlistReservation() ) {
                            $this->chatBotLog(__LINE__);
                            $this->waitlistReservationConfirmation(); // buat main menu
                        } else if ( $this->cekListHarianExists() ) { // Jika ada cek list harian
                            $this->chatBotLog(__LINE__);
                            return $this->prosesCekListHarian(); // proses cek list harian
                        } else if ( $this->cekListHarianInputExists() ) { // Jika ada cek list harian
                            $this->chatBotLog(__LINE__);
                            return $this->prosesCekListHarianInput(); // proses cek list harian
                        } else if ( $this->whatsappJadwalKonsultasiInquiryExists() ) { //
                            $this->chatBotLog(__LINE__);
                            return $this->balasJadwalKonsultasi(); // proses pertanyaan jadwal konsulasi
                        } else if ( $this->whatsappKonsultasiEstetikExists() ) {
                            $this->chatBotLog(__LINE__);
                            return $this->prosesKonsultasiEstetik(); // buat main menu
                        } else if ( $this->bpjsNumberInfomationInquiryExists() ) {
                            $this->chatBotLog(__LINE__);
                            return $this->prosesBpjsNumberInquiry(); // buat main menu
                        } else if ( $this->whatsappAntrianOnlineExists() ) {
                            $this->chatBotLog(__LINE__);
                            return $this->prosesAntrianOnline(); // buat main menu
                        } else if ( $this->whatsappGambarPeriksaExists() ) {
                            $this->chatBotLog(__LINE__);
                            return $this->prosesGambarPeriksa(); // buat main menu
                        } else if ( $this->noTelpAdaDiAntrianPeriksa() ) {
                            $this->chatBotLog(__LINE__);
                            return $this->updateNotifikasPanggilanUntukAntrian(); // notifikasi untuk panggilan
                        } else if( $this->validasiWaktuPelayanan() ) {
                            $this->chatBotLog(__LINE__);
                            return $this->balasanKonfirmasiWaktuPelayanan();
                        } else if( $this->noTelpDalamChatWithAdmin() ) {
                            $this->chatBotLog(__LINE__);
                            $this->createWhatsappChat(); // buat main menu
                        } else if( $this->pasienTidakDalamAntrian() ) {
                            $this->chatBotLog(__LINE__);
                            return $this->createWhatsappMainMenu(); // buat main menu
                        }
                    }
                }
            }
        }
	}
    private function proceedRegistering()
    {
        if ( !is_null($this->whatsapp_registration) ) {
            $this->antrian = $this->whatsapp_registration->antrian;
        }

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
            if (
                is_numeric( $this->message ) &&
                (int)$this->message <= $dataCount &&
                (int)$this->message > 0
            ) {
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
                }
                $this->whatsapp_registration->antrian->save();
            } else if (
                is_numeric( $this->message ) &&
                (int)$this->message > $dataCount &&
                (int)$this->message > 0
            ){
                $this->whatsapp_registration->antrian->register_previously_saved_patient  = $this->message;
            } else {
                $this->input_tidak_tepat = true;
            }
            $this->whatsapp_registration->antrian->save();
        } else if (
            isset( $this->whatsapp_registration ) &&
            !is_null( $this->whatsapp_registration->antrian ) &&
            is_null( $this->whatsapp_registration->antrian->nama )
        ) {
            if (
                preg_match('~[0-9]+~', $this->message)
            ) { ///string mengndung angka
                $input_tidak_tepat = true;
                $this->pesan_error = '```Nama tidak boleh mengandung angka```';
            } else if(
                strstr( $this->message , PHP_EOL)
            ) { ///string mengndung line break, pasien mencoba mendaftarkan dua pasien sekaligus
                $input_tidak_tepat = true;
                $this->pesan_error = '```Masukkan data pasien satu pasien saja```';
                $this->pesan_error .= PHP_EOL;
                $this->pesan_error .= '```Pendaftaran pasien selanjutnya akan dilakukan setelah pendaftaran ini selesai dikerjakan```';
            } else {
                $this->whatsapp_registration->antrian->nama  = ucwords(strtolower($this->message));;
                $this->whatsapp_registration->antrian->save();
            }
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
            if ( $this->message_type == 'image' ) {
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
                    /* $this->langsungKeAntrianPoliBilaMemungkinkan( $this->whatsapp_registration->antrian ); */
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
                    if ($this->message_type == 'text') {
                        WhatsappInbox::create([
                            'message' => 'ERROR :' .$this->message,
                            'no_telp' => $this->no_telp
                        ]);
                    }
                    $response .=  PHP_EOL;
                    $response .=  PHP_EOL;
                    $response .=  $this->samaDengan();
                    $response .= PHP_EOL;
                    $response .= '```Input yang anda masukkan salah```';
                    if (!is_null( $this->pesan_error )) {
                        $response .= PHP_EOL;
                        $response .= $this->pesan_error;
                    }
                    $response .= PHP_EOL;
                    $response .= '```Mohon Diulangi```';
                    $response .= PHP_EOL;
                }
                $input_tidak_tepat = false;
            } else {
                $this->autoReply('Antrian kakak sudah berhasil kami daftarkan' );
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
                $this->autoReply($response );
            }
        }
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
                    $this->autoReply($message );
                    //
                // Jika pasien memilih mengalami perbaikan, ucapkan terima kasih
                //
                } else if (
                    $recovery_index_id == 2  ||
                    $recovery_index_id == 3
                ) {
                    $message = "Baik kak, terima kasih atas responnya. Kami turut senang apabila kondisi kakak baik dan pengobatan berjalan dengan lancar";
                    $this->autoReply($message );
                } else {
                    $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
                    $message .= PHP_EOL;
                    $message .= "Informasi ini akan menjadi bahan evaluasi kami";
                    $this->autoReply($message );
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
            $this->autoReply($this->pesanBalasanBilaTerdaftar( $this->antrian ) );
        }
    }

	private function clean($param)
	{
        if (
            is_array( $param )
        ) {
            Log::info("===================================");
            Log::info("ISI LOG ERROR ARRAY");
            Log::info("===================================");
            Log::info($param);
        } else {
            if (
                empty( trim($param) ) &&
                trim($param) != '0'
            ) {
                return null;
            }

            if ( str_contains($param, "<~ ") ) {
                $param = explode( "<~ ", $param)[1];
            }
            return strtolower( trim($param) );
        }
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
                $payment_options = [
                    'Biaya Pribadi',
                    'BPJS',
                    'Lainnya'
                ];
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
    /**
     * undocumented function
     *
     * @return void
     */
    private function ulangiRegistrasiWhatsapp($model)
    {
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
            if ( $antrian->tipe_konsultasi_id < 3 ) {
                $sisa_antrian  = $antrian->sisa_antrian;
                $response     .= "masih ada *{$sisa_antrian} antrian* lagi";
                $response     .= PHP_EOL;
                $waktu_tunggu  = $this->waktuTunggu( $antrian->sisa_antrian );
                $response     .= "perkiraan waktu tunggu *{$waktu_tunggu} menit*";
            }
        } else {
            $response .= PHP_EOL;
            $response .= "Anda akan menerima notifikasi setiap kali ada panggilan pasien.";
            $response .= PHP_EOL;
            $response .= "Silahkan menunggu untuk dilayani";
        }
        $response .= $this->footerAntrian();
        return $response;
    }

    public function footerAntrian(){
        $response = PHP_EOL;
        $response .= $this->samaDengan();
        $response .= PHP_EOL;
        $response .= 'Balas *cek antrian* untuk melihat antrian terakhir';
        $response .= PHP_EOL;
        $response .= 'Ketik *batalkan* untuk membatalkan';
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
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Simpan nomor ini di hape anda agar link di atas bisa aktif dan memudahkan anda mengklik";
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
        curl_setopt($curl, CURLOPT_URL,  "https://solo.wablas.com/api/v2/send-button");
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
    public function queryPreviouslySavedPatientRegistry()
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

        $tenant_id =  session()->get('tenant_id');

        $query .= "AND ant.tenant_id = $tenant_id ";
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
            /* && ( $this->message_type == 'text' ) */
            && ( !empty( $number ) )
            && ( $this->message[0] ==  $pembanding|| $number[0] ==  $pembanding);
    }


    private function uploadImage()
    {
        if (
            $this->tenant->image_bot_enabled
        ) {
            $this->chatBotLog(__LINE__);
            $contents         = file_get_contents($this->image_url);
            $name             = substr($this->image_url, strrpos($this->image_url, '/') + 1);
            $destination_path = 'image/whatsapp/';
            $name             = $destination_path . $name;

            \Storage::disk('s3')->put($name, $contents);
            return $name;
        } else {
            return '';
        }
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function registerWhatsappComplaint()
    {
        $tanggal_berobat = !is_null( $this->whatsapp_complaint->antrian )? $this->whatsapp_complaint->antrian->created_at->format('Y-m-d') : date('Y-m-d');
        $this->whatsapp_complaint->delete();
        if (!is_null( $this->message )) {

            $complain = Complain::create([
                'tanggal'   => $tanggal_berobat,
                'media'     => 'Whatsapp Bot',
                'no_telp'   => $this->no_telp,
                'tenant_id' => 1,
                'complain'  => $this->message
            ]);

            $messageToBoss = 'Complain dari no wa ' . $this->no_telp;
            $messageToBoss .=  PHP_EOL;
            $messageToBoss .=  PHP_EOL;
            $messageToBoss .=  $this->message;
            $this->sendSingle('6281381912803', $messageToBoss);

            $antrian = null;

            $carbon = Carbon::parse( $tanggal_berobat );
            $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
            $endOfDay = $carbon->endOfDay()->format('Y-m-d H:i:s');
            $antrians = Antrian::where('no_telp', $this->no_telp)
                ->whereBetween('created_at', [
                        $startOfDay,
                        $endOfDay
                    ])
                ->get();
            if (
                $this->lama()
            ) {
                if ( count( $antrians ) ) {

                }

                $message = 'Sebelumnya kami mohon maaf atas ketidaknyaman yang kakak rasakan. ';
                $message .= PHP_EOL;
                $message .= PHP_EOL;
                $message .= 'Antrian yang terlalu lama seringkali tidak dapat dihindari apabila antrian pasien sedang banyak atau sedang ada tindakan dokter di ruang UGD. ';
                $message .= PHP_EOL;
                $message .= 'Mengenai waktu tunggu pelayanan yg lama kakak dapat menggunakan fitur layanan daftar melalui whatsapp.';
                $message .= PHP_EOL;
                $message .= PHP_EOL;
                $message .= '- kakak bisa mendaftar dari rumah dan mendapat antrian dari rumah ';
                $message .= PHP_EOL;
                $message .= '- kakak akan dapat notifikasi setiap kali ada panggilan antrian ';
                $message .= PHP_EOL;
                $message .= '- kakak bisa datang ke klinik apabila sudah dekat waktu panggilan';
                $message .= PHP_EOL;
                $message .= '- sehingga kakak tidak perlu menunggu lama2 di Klinik.';
                $message .= PHP_EOL;
                $message .= PHP_EOL;
                $message .= 'Silahkan dimanfaatkan dengan whatsapp *"daftar"* kirim ke nomor whatsapp ini. ';
                $message .= PHP_EOL;
                $message .= 'Semoga dapat memberikan pengalaman berobat yang lebih baik bagi kakak dan keluarga ';
                $this->autoReply($message );

            } else if ($antrians->count()) {
                foreach ($antrians as $antrian) {
                    $antrian->complaint   = $this->message;
                    $antrian->complain_id = $complain->id;
                    $antrian->save();

                    $complain->tanggal_kejadian = $antrian->created_at;
                    $complain->nama_pasien      = $antrian->antriable->pasien?->nama;
                    $complain->save();
                }
            } else if(
                !$antrians->count()
            ) {
                WhatsappBot::create([
                    'no_telp' => $this->no_telp,
                    'whatsapp_bot_service_id' => 15, // tanyakan tanggal pelayana dan nama pasien
                ]);
                $this->autoReply($this->tanyaKapanKeluhanTerjadi() );
            } else if (
                $this->lama() &&
                !is_null( $antrian ) &&
                $antrian->antriable_type == 'App\Models\Periksa'
            ) {
                WhatsappBot::create([
                    'no_telp' => $this->no_telp,
                    'whatsapp_bot_service_id' => 14,
                ]);

                $antrian->complain_pelayanan_lama = 1;
                $antrian->save();

                $this->autoReply($this->tanyaValidasiWaktuPelayanan() );
            } else {
                $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
                if (
                    is_null( $antrian ) ||
                    (
                        !is_null( $antrian ) &&
                        $antrian->satisfaction_index == 1
                    )
                ) {
                    $message .= PHP_EOL;
                    $message .= "Keluhan atas pelayanan yang kakak rasakan akan segera kami tindak lanjuti.";
                    $message .= PHP_EOL;
                    $message .= PHP_EOL;
                    $message .= "Untuk respon cepat kakak dapat menghubungi 021-5977529";
                }
                $message .= PHP_EOL;
                $message .= "Kami berharap dapat melayani anda dengan lebih baik lagi.";
                $this->autoReply($message );
            }
            /* https://wa.me/6181381912803?text=hallo%20nama%20saya%20Yoga%20Hadi%20Nugroho */
        } else {

        }

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
            $no_telp = $this->no_telp;

            $carbon     = Carbon::parse( $this->whatsapp_satisfaction_survey->created_at->format('Y-m-d') );
            $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
            $endOfDay   = $carbon->endOfDay()->format('Y-m-d H:i:s');
            Antrian::where('no_telp', $no_telp)
                    ->whereBetween('created_at', [
                        $startOfDay, $endOfDay
                    ])
                    ->update([
                        'satisfaction_index' => $satisfaction_index_ini
                    ]);

            if(
                 $this->angkaPertama("1")  ||
                 $this->message == 'puas'
            ){
                $this->autoReply($this->kirimkanLinkGoogleReview() );
            } else if(
                 $this->angkaPertama("3")  ||
                 $this->message == 'tidak puas'
            ){
                $pesan = $this->autoReplyComplainMessage(
                    $this->whatsapp_satisfaction_survey->antrian->id
                );
                $this->autoReply( $pesan );
            } else if (
                 $this->angkaPertama("2")  ||
                 $this->message == 'biasa'
            ) {
                $complaint             = new WhatsappComplaint;
                $complaint->no_telp    = $this->whatsapp_satisfaction_survey->antrian->no_telp;
                $complaint->antrian_id = $this->whatsapp_satisfaction_survey->antrian->id;
                $complaint->tenant_id = $this->whatsapp_satisfaction_survey->antrian->tenant_id;
                $complaint->save();

                $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
                $message .= PHP_EOL;
                $message .= "Jika kakak berkenan memberikan saran, kira-kira apa yang bisa kami perbaiki agar pelayanan kami bisa lebih baik?";

                $this->autoReply($message );
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
            $this->autoReply($message );
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
                $this->autoReply($message );
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
            $this->autoReply($message );
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
            $this->autoReply($message );
        } else {
            $message = "Balasan yang anda masukkan tidak dikenali";
            $message .= PHP_EOL;
            $message .= '1. Saya Menunggu saja  di klinik';
            $message .= PHP_EOL;
            $message .= '2. Saya pergi dulu nanti kabari saya kalau obat sudah selesai diracik';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= "Balas dengan angka *1 atau 2* sesuai informasi diatas";
            $this->autoReply($message );
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

        $this->autoReply($message );
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
            $this->autoReply($this->pertanyaanPembayaranPasien() );
        } else {
            $message = "Balasan yang kakak masukkan tidak dikenali";
            $message .= $this->messageWhatsappMainMenu();

            $this->autoReply($message );
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
        $message .= PHP_EOL;
        $message .= '2. Daftar lewat Whatsapp';
        $message .= PHP_EOL;
        $message .= '3. Kode Faskes Klinik Jati Elok';
        $message .= PHP_EOL;
        $message .= '4. Keluhan atas pelayanan';
        $message .= PHP_EOL;
        $message .= '5. Chat dengan Admin';
        /* $message .= PHP_EOL; */
        /* $message .= '5. Saya ingin berbicara dengan admin'; */
        /* $message .= PHP_EOL; */
        /* $message .= '6. Konsultasi Estetika'; */
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        /* $message .= 'Balas dengan *1* sesuai dengan informasi di atas'; */
        /* $message .= 'Balas dengan *1 atau 2* sesuai dengan informasi di atas'; */
        $message .= 'Balas dengan *1, 2, 3, 4, atau 5* sesuai dengan informasi di atas';
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
                $this->autoReply($message );
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
            $this->autoReply($message );
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
                $this->autoReply($this->konfirmasiSebelumDisimpanDiAntrianPoli() );
            } else {
                $this->whatsapp_bpjs_dentist_registrations->register_previously_saved_patient = $this->message;
                $this->autoReply($this->tanyaNomorBpjsPasien() );
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
            $this->autoReply($message );
        } else if (
            is_null($this->whatsapp_bpjs_dentist_registrations->nama)
        ) {
            if (
                $this->namaLengkapValid($this->message)
            ) {
                $this->whatsapp_bpjs_dentist_registrations->nama  = ucwords($this->message);
                $this->whatsapp_bpjs_dentist_registrations->save();
                $this->autoReply($this->tanyaTanggalLahirPasien($this->whatsapp_bpjs_dentist_registrations->antrian) );
            } else {
                $message = 'Input yang kakak masukkan tidak tepat';
                $message .= PHP_EOL;
                $message .= $this->tanyaNamaLengkapPasien();
                $this->autoReply($message );
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
            $this->autoReply($message );
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
                    $this->autoReply($this->pertanyaanPembayaranPasien() );
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
        $query     .= "AND poli_id = 4 ";
        $tenant_id = session()->get('tenant_id');
        $query     .= "AND tenant_id = $tenant_id ";
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
    private function pertanyaanPembayaranPasien($reservasi_online = null)
    {
        $tz   = 'Asia/Jakarta';
        $now  = \Carbon\Carbon::now($tz);
        $msg  = '';

        if (!is_null($reservasi_online) && (int)$reservasi_online->tipe_konsultasi_id === 2) {
            $msg .= 'Jadwal ' . ucwords($reservasi_online->tipe_konsultasi->tipe_konsultasi) . ' hari ini:' . PHP_EOL;

            $petugas_pemeriksas = \App\Models\PetugasPemeriksa::query()
                ->with(['staf']) // sesuaikan kolom yg ada
                ->where('tipe_konsultasi_id', $reservasi_online->tipe_konsultasi_id)
                ->whereDate('tanggal', $now->toDateString())
                ->where('jam_mulai_default', '>',  $now->toTimeString())
                ->where('schedulled_booking_allowed', 1)
                ->orderBy('jam_mulai_default', 'asc')
                ->get();

            if ($petugas_pemeriksas->isEmpty()) {
                $msg .= '- Belum ada jadwal yang menerima pasien terjadwal hari ini.' . PHP_EOL . PHP_EOL;
            } else {
                foreach ($petugas_pemeriksas as $pp) {
                    $namaStaf  = $pp->staf->nama_dengan_gelar ?? $pp->staf->nama ?? 'Staf';
                    $jamMulai  = \Carbon\Carbon::parse($pp->jam_mulai_default, $tz)->format('H:i');
                    // window tutup pendaftaran 30 menit sebelum jam akhir default
                    $jamAkhirWindow = \Carbon\Carbon::parse($pp->jam_akhir_default, $tz)->subMinutes(30)->format('H:i');

                    $msg .= $namaStaf . PHP_EOL;
                    $msg .= '( ' . $jamMulai . ' - ' . $jamAkhirWindow . ' )' . PHP_EOL . PHP_EOL;
                }
            }
        }

        $msg .= 'Bisa dibantu menggunakan pembayaran apa?' . PHP_EOL;
        $msg .= $this->messagePilihanPembayaran();

        return $msg;
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
            $response =  "*Rangkuman Pengisian Anda*";
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
        $this->autoReply($response );
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
        $query .= "AND created_at like '{$today}%' ";
        $tenant_id = session()->get('tenant_id');
        $query .= "AND tenant_id = $tenant_id ";
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
            $this->autoReply($message );
        } else {
            $message = "Terima kasih atas kesediaan memberikan masukan terhadap pelayanan kami";
            $message .= PHP_EOL;
            $message .= "Informasi ini akan menjadi bahan evaluasi kami";
            $this->autoReply($message );
        }
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function noTelpAdaDiAntrianPeriksa()
    {

        $carbon = Carbon::now();
        $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay = $carbon->endOfDay()->format('Y-m-d H:i:s');
        $this->antrian = Antrian::where('no_telp', $this->no_telp)
                ->whereBetween('created_at', [
                        $startOfDay,
                        $endOfDay
                    ])
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

            $carbon = Carbon::now();
            $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
            $endOfDay = $carbon->endOfDay()->format('Y-m-d H:i:s');
            Antrian::where('no_telp', $this->no_telp)
                ->whereBetween('created_at', [
                        $startOfDay,
                        $endOfDay
                    ])
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
             str_contains($this->message, 'chat admin')
        ) {
            resetWhatsappRegistration($this->no_telp);
            $message = $this->chatAdmin();
        } else if (
             str_contains($this->message, 'batalkan') ||
             str_contains($this->message, 'btlkan') ||
             str_contains($this->message, 'batalkn') ||
             str_contains($this->message, 'batlkan')
        ) {
            $message = $this->konfirmasiPembatalan();
        } else if (
             str_contains($this->message, 'cek antrian') ||
             str_contains($this->message, 'cek antrin') ||
             str_contains($this->message, 'cek antri') ||
             str_contains($this->message, 'cek antran') ||
             str_contains($this->message, 'ck antrian') ||
             str_contains($this->message, 'ck antrin') ||
             str_contains($this->message, 'ck antri') ||
             str_contains($this->message, 'ck antran')
        ) {
            $this->autoReply($this->cekAntrian() );
        } else if (
             str_contains($this->message, 'kirim qr')
        ) {

            $carbon = Carbon::now();
            $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
            $endOfDay = $carbon->endOfDay()->format('Y-m-d H:i:s');
            $antrian = Antrian::where('no_telp', $this->no_telp)
                ->whereBetween('created_at', [
                    $startOfDay,
                    $endOfDay
                ])
                ->latest()->first();
            $message = $this->getQrCodeMessage($antrian);
            $message .= $this->templateFooter();

            $response = $this->pesanBalasanBilaTerdaftar( $antrian, true );
            $urlFile =  !is_null( $antrian->qr_code_path_s3 ) ? \Storage::disk('s3')->url($antrian->qr_code_path_s3) : null;
            $this->sendWhatsappImage( $this->no_telp, $urlFile, $response );

        } else if (
             str_contains($this->message, 'aktivkan') ||
             str_contains($this->message, 'aktipkan') ||
             str_contains($this->message, 'aktifkan')
        ) {
            $carbon = Carbon::now();
            $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
            $endOfDay = $carbon->endOfDay()->format('Y-m-d H:i:s');
            $antrian = Antrian::where('no_telp', $this->no_telp)
                ->whereBetween('created_at', [
                    $startOfDay,
                    $endOfDay
                ])
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
        if (isset($message)) {
            $this->autoReply($message );
        }
    }
    public function pasienTidakDalamAntrian(){
        $carbon = Carbon::now();
        $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay = $carbon->endOfDay()->format('Y-m-d H:i:s');
        $antrian = Antrian::where('no_telp', $this->no_telp)
            ->whereBetween('created_at', [
                $startOfDay,
                $endOfDay
            ])
        ->whereRaw("antriable_type not like 'App\\\\\\\Models\\\\\\\Periksa' ") // yang ini gagal
        ->get() ;

        return !Antrian::where('no_telp', $this->no_telp)
            ->whereBetween('created_at', [
                $startOfDay,
                $endOfDay
            ])
            ->whereRaw("antriable_type not like 'App\\\\\\\Models\\\\\\\Periksa' ") // yang ini gagal
            ->exists() ;
        /* && $this->no_telp = '6281381912803'; */
    }
    public function whatsappMainMenuExists(){
        $carbon     = Carbon::now();
        $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay   = $carbon->endOfDay()->format('Y-m-d H:i:s');
        $whatsappMainMenuExists = WhatsappMainMenu::where('no_telp', $this->no_telp)
            ->whereBetween('created_at', [
                $startOfDay,
                $endOfDay
            ])
            ->exists();
        return $whatsappMainMenuExists;
    }

    public function whatsappJadwalKonsultasiInquiryExists(){
        $carbon = Carbon::now();
        $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay = $carbon->endOfDay()->format('Y-m-d H:i:s');
        return WhatsappJadwalKonsultasiInquiry::where('no_telp', $this->no_telp)
            ->whereBetween('created_at', [
                $startOfDay,
                $endOfDay
            ])
            ->exists();
    }


    public function balasJadwalKonsultasi(){
        if (
             $this->message == '1'  ||
             $this->message == '2'  ||
             $this->message == '3'  ||
             $this->message == '4'
        ){
            $carbon = Carbon::now();
            $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
            $endOfDay = $carbon->endOfDay()->format('Y-m-d H:i:s');
            WhatsappJadwalKonsultasiInquiry::whereBetween('created_at', [
                $startOfDay,
                $endOfDay
            ])
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
            $message = $this->pertanyaanTipeKonsultasi();
            $message .= $this->templateUlangi();
            $this->autoReply($message );
        }
    }

    public function prosesMainMenuInquiry(){
        $message = '';
        if ( $this->message == 1 ) {
            WhatsappJadwalKonsultasiInquiry::create([
                'no_telp' => $this->no_telp
            ]);
            $this->autoReply($this->pertanyaanTipeKonsultasi() );
        } else if ( $this->message == 2 ) {
            $this->autoReply($this->registrasiAntrianOnline() );
        } else if ( $this->message == 3 ) {
            $this->autoReply($this->informasiNomorFaskesKJE() );
            /* echo $this->registrasiInformasiNomorKartuBpjs(); */
        } else if ( $this->message == 4 ) {
            $this->autoReply($this->autoReplyComplainMessage() );
        } else if ( $this->message == 5 ) {
            $this->autoReply($this->chatAdmin() );
        } else if ( $this->message == 6 ) {
            $this->autoReply($this->konsultasiEstetikOnlineStart() );
        } else if( isset( $this->whatsapp_bot ) && $this->whatsapp_bot->prevent_repetition == 0 ) {
            $this->whatsapp_bot->prevent_repetition = 1;
            $this->whatsapp_bot->save();
            $message = $this->messageWhatsappMainMenu();
            $message .= $this->pesanMintaKlienBalasUlang();
            $this->autoReply($message );
        } else {
            $message = $this->messageWhatsappMainMenu();
            $message .= $this->templateUlangi();
            $this->autoReply($message );
        }
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function templateUlangi()
    {
        $message = PHP_EOL;
        $message .= PHP_EOL;
        $message .= "_Balasan yang Anda masukkan tidak dikenali_";
        $message .= PHP_EOL;
        $message .= "_Mohon diulangi_";
        $message .= PHP_EOL;
        $message .= "_Atau ketik *batalkan* untuk mengulangi dari awal_";
        $message .= PHP_EOL;
        $message .= "_Atau ketik *chat admin* untuk bantuan admin_";
        return $message;
    }

    public function balasJadwalDokterUmum(){
        $this->autoReply($this->queryJadwalKonsultasiByTipeKonsultasi(1) );
    }
    public function balasJadwalDokterGigi(){
        $this->autoReply($this->queryJadwalKonsultasiByTipeKonsultasi(2) );
    }
    public function balasJadwalDokterBidan(){
        $query  = "SELECT * ";
        $query .= "FROM petugas_pemeriksas as ptp ";
        $query .= "JOIN stafs as stf on stf.id = ptp.staf_id ";
        $query .= "JOIN titels as ttl on ttl.id = stf.titel_id ";
        $query .= "WHERE ptp.tenant_id=". session()->get('tenant_id') . " ";
        $hari_ini = Carbon::now()->format('Y-m-d');
        $query .= "AND tanggal = '$hari_ini' ";
        $query .= "AND stf.titel_id = 4 ";
        $data = DB::select($query);

        $message = 'Jadwal Bidan Hari ini';
        $message .= PHP_EOL;
        $message .= "Klinik Jati Elok";
        $message .= PHP_EOL;

        foreach ($data as $k => $r) {
            $message .=  $this->tambahkanGelar($r->titel,ucwords($r->nama));
            $message .= PHP_EOL;
            $message .= ' ( ' . $r->jam_mulai . '-' . $r->jam_akhir.  ' )' ;
            $message .= PHP_EOL;
        }

        $this->autoReply($message );
    }
    public function balasJadwalDokterUsg(){
        $this->autoReply($this->queryJadwalKonsultasiByTipeKonsultasi(4) );
    }
    /**
     * undocumented function
     *
     * @return void
     */
    public function queryJadwalKonsultasiByTipeKonsultasi($param)
    {
        // ===== ambil jadwal (seperti semula) =====
        $query  = "SELECT ";
        $query .= "har.hari, ";
        $query .= "ttl.singkatan as titel, ";
        $query .= "TIME_FORMAT(jad.jam_mulai, '%H:%i') as jam_mulai, ";
        $query .= "TIME_FORMAT(jad.jam_akhir, '%H:%i') as jam_akhir, ";
        $query .= "tip.tipe_konsultasi, ";
        $query .= "sta.id as staf_id, "; // <— tambahkan id staf untuk cek izin
        $query .= "sta.nama ";
        $query .= "FROM jadwal_konsultasis as jad ";
        $query .= "JOIN stafs as sta on sta.id = jad.staf_id ";
        $query .= "JOIN titels as ttl on ttl.id = sta.titel_id ";
        $query .= "JOIN tipe_konsultasis as tip on tip.id = jad.tipe_konsultasi_id ";
        $query .= "JOIN haris as har on har.id = jad.hari_id ";
        $query .= "WHERE jad.tipe_konsultasi_id = {$param} ";
        $tenant_id = session()->get('tenant_id');
        $query .= "AND jad.tenant_id = $tenant_id ";
        $query .= "ORDER BY hari_id asc, jam_mulai asc";

        $rows = DB::select($query);
        if (!count($rows)) {
            return 'Fitur ini dalam pengembangan';
        }

        // ===== siapkan lookup petugas_pemeriksas untuk HARI INI =====
        // Hari ini: ambil roster (petugas_pemeriksas) — filter tenant & tipe konsultasi yang sama
        $today      = \Carbon\Carbon::now('Asia/Jakarta')->toDateString();
        $stafHariIni = \App\Models\PetugasPemeriksa::query()
            ->where('tanggal', $today)
            ->where('tenant_id', $tenant_id)
            ->where('tipe_konsultasi_id', $param)
            ->pluck('staf_id')
            ->all(); // array of staf_id aktif hari ini

        // Nama hari dalam DB tampaknya pakai Bahasa Indonesia: Senin, Selasa, dst.
        $hariIndo = [
            0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
            4 => 'Kamis',  5 => 'Jumat', 6 => 'Sabtu'
        ];
        $hariIniStr = $hariIndo[\Carbon\Carbon::now('Asia/Jakarta')->dayOfWeek]; // contoh: "Senin"

        // ===== group by hari untuk compose message =====
        $result = [];
        foreach ($rows as $q) {
            $result[$q->hari][] = [
                'staf_id'   => $q->staf_id,
                'nama'      => $q->nama,
                'titel'     => $q->titel,
                'jam_mulai' => $q->jam_mulai,
                'jam_akhir' => $q->jam_akhir,
            ];
        }

        $message  = '*Jadwal ' . ucwords(strtolower($rows[0]->tipe_konsultasi)) . '*';
        $message .= PHP_EOL;
        $message .= 'Klinik Jati Elok';
        $message .= PHP_EOL;

        foreach ($result as $hari => $r) {
            if (strtolower($hari) != 'senin') { $message .= PHP_EOL; }
            $message .= ucwords($hari) . ': ' . PHP_EOL;

            foreach ($r as $i => $d) {
                if (count($r) > 1) {
                    $nomor = $i + 1;
                    $message .= $nomor . '. ';
                }

                // Nama + gelar
                $message .= $this->tambahkanGelar($d['titel'], ucwords($d['nama'])) . PHP_EOL;

                // Jam praktik
                $message .= ' ( ' . $d['jam_mulai'] . '-' . $d['jam_akhir'] . ' )';

                // ===== Tambahkan (izin hari ini) bila:
                // - ini adalah baris untuk HARI INI, dan
                // - staf pada baris ini TIDAK ada di roster petugas_pemeriksas hari ini
                if (strcasecmp($hari, $hariIniStr) === 0) {
                    if (!in_array($d['staf_id'], $stafHariIni, true)) {
                        $message .= ' (izin hari ini)';
                    }
                }

                $message .= PHP_EOL;
            }
        }

        // Tambahan keterangan dokter umum yang sedang praktik (kode asli)
        if ($param == 1) {
            $staf = $this->lastStaf();
            if (!is_null($staf)) {
                $message .= PHP_EOL;
                $message .= 'Dokter umum yang saat ini praktik adalah ';
                foreach (\App\Models\PetugasPemeriksa::dokterSaatIni() as $k => $petugas) {
                    $message .= PHP_EOL;
                    $message .= $this->tambahkanGelar(
                        $petugas->staf->titel->singkatan,
                        ucwords(strtolower($petugas->staf->nama))
                    );
                }
                $message .= PHP_EOL . PHP_EOL;
                $message .= 'untuk memastikan silahkan hubungi 021-5977529' . PHP_EOL;
                $message .= 'Terima kasih';
            }
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
        $message .= '1. Dokter Umum ';
        $message .= PHP_EOL;
        $message .= '2. Dokter Gigi ';
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
        $query .= "and sta.titel_id = 2 ";
        $tenant_id = session()->get('tenant_id');
        $query .= "and prx.tenant_id = $tenant_id ";
        $query .= "ORDER BY prx.id desc ";
        $query .= "LIMIT 1";
        return isset( DB::select($query)[0] ) ?  DB::select($query)[0]  : null;
    }

    public function masihAdaYangBelumCekListHariIni(){
        $cek_list_ruangan_harian_ids  = CekListRuangan::where('frekuensi_cek_id', 1)->pluck('id');
        $today = Carbon::now()->format("Y-m-d");
        $cek_list_dikerjakan_hari_ini = CekListDikerjakan::whereDate('created_at', $today)
                                                        ->whereIn('cek_list_ruangan_id', $cek_list_ruangan_harian_ids)
                                                        ->whereNotNull('jumlah')
                                                        ->whereNotNull('image')
                                                        ->groupBy('cek_list_ruangan_id')
                                                        ->get();
        Log::info('masihAdaYangBelumCekListHariIni');
        Log::info([
            $cek_list_ruangan_harian_ids->count() ,  // ini hasilnya 87
            $cek_list_dikerjakan_hari_ini->count() // ini hasilnya 1
        ]);
        $result = !( $cek_list_ruangan_harian_ids->count() == $cek_list_dikerjakan_hari_ini->count() );
        Log::info("resutl");
        Log::info( $result ); // kenapa result hasilnya true?
        return $result;
    }
    public function cekListBelumDilakukan( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input ){
        $this->chatBotLog(__LINE__);
        $cek_list_ruangan_harians = CekListRuangan::with('cekList', 'ruangan')
                                    ->where('frekuensi_cek_id', $frekuensi_cek_id)
                                    ->orderBy('ruangan_id', 'asc')
                                    ->orderBy('cek_list_id', 'asc')
                                    ->get();
        $cek_list_ruangan_ids = [];
        foreach ($cek_list_ruangan_harians as $cek) {
            $cek_list_ruangan_ids[] = $cek->id;
        }

        $carbon = Carbon::now();
        $today = $carbon->format('Y-m-d');
        $cek_list_harians_dikerjakans = CekListDikerjakan::whereIn('cek_list_ruangan_id', $cek_list_ruangan_ids)
                                                        ->whereDate('created_at', $today)
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
        $today = Carbon::now()->format('Y-m-d');
        return CekListDikerjakan::where('cek_list_ruangan_id',  $cek_list_ruangan_id )
                            ->whereDate('created_at', $today)
                            ->firstOrCreate([
                                'staf_id' => $this->whatsapp_bot->staf_id,
                                'cek_list_ruangan_id' => $cek_list_ruangan_id,
                                'no_telp' => $this->whatsapp_bot->no_telp
                            ]);
    }

    public function pesanCekListHarianBerikutnya($cek){
        $this->message_reply .= "Silahkan cek ";
        $this->message_reply .= PHP_EOL;
        $this->message_reply .= PHP_EOL;
        $this->message_reply .= '*'.$cek->cekList->cek_list . '*';
        $this->message_reply .= PHP_EOL;
        $this->message_reply .= "jumlah " . $cek->limit->limit . ' ' . $cek->jumlah_normal;
        $this->message_reply .= PHP_EOL;
        $this->message_reply .= PHP_EOL;
        $this->message_reply .= "Di ruangan ";
        $this->message_reply .= PHP_EOL;
        $this->message_reply .= PHP_EOL;
        $this->message_reply .= "*" . $cek->ruangan->nama . '*';
        return $this->message_reply;
    }
    public function masukkanGambar($cek){
        $this->message_reply .= "Mohon masukkan gambar  ";
        $this->message_reply .= PHP_EOL;
        $this->message_reply .= PHP_EOL;
        $this->message_reply .= '*'.$cek->cekList->cek_list . '*';
        $this->message_reply .= PHP_EOL;
        $this->message_reply .= PHP_EOL;
        $this->message_reply .= "Di ruangan ";
        $this->message_reply .= PHP_EOL;
        $this->message_reply .= PHP_EOL;
        $this->message_reply .= "*" . $cek->ruangan->nama . '*';
        return $this->message_reply;
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
        Log::info('prosesCekListHarianInput');
        $masihAdaYangBelum =  $this->masihAdaYangBelumCekListHariIni() ;
        Log::info($masihAdaYangBelum);
        if ($masihAdaYangBelum) {
            $this->prosesCekListDikerjakanInput(1,1,2);
        } else {
            $message_done = 'Semua Cek List HARIAN sudah dikerjakan';
            Log::info($message_done);
            $this->autoReply($message_done);
        }
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
        $this->chatBotLog(__LINE__);
        $cek = $this->cekListBelumDilakukan( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input );
        if ($cek) {
            $this->chatBotLog(__LINE__);
            $message = $this->pesanCekListHarianBerikutnya( $cek );
        } else {
            $this->chatBotLog(__LINE__);
            WhatsappBot::where('no_telp', $this->no_telp)->whereIn('whatsapp_bot_service_id',[
                $whatsapp_bot_service_id,
                $whatsapp_bot_service_id_input
            ])->delete();
            $message = 'Cek List selesai';
        }
        $this->chatBotLog(__LINE__);
        $this->autoReply($message );
    }

    public function cekListPhoneNumberRegisteredForWhatsappBotService( $whatsapp_bot_service_id ){
        if (!is_null( $this->whatsapp_bot )) {
            $this->whatsapp_bot->touch();
        }
        $result = !is_null( $this->whatsapp_bot ) && $this->whatsapp_bot->whatsapp_bot_service_id == $whatsapp_bot_service_id;
        return $result;
    }

    public function prosesCekListDikerjakanInput( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input ){
        $cek_list_selesai = false;
        $cek = $this->cekListBelumDilakukan( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input );
        if (!is_null($cek)) {
            $cek_list_dikerjakan = $this->cekListDikerjakanUntukCekListRuanganIni( $cek->id );
            $whatsapp_bot = WhatsappBot::with('staf')
                ->where('no_telp', $this->no_telp)
                ->whereRaw('whatsapp_bot_service_id = ' . $whatsapp_bot_service_id. ' or whatsapp_bot_service_id = ' . $whatsapp_bot_service_id_input)
                ->first();

            if (
                !is_null( $whatsapp_bot ) &&
                is_null(  $cek_list_dikerjakan  )
            ) {
                if ( is_numeric( $this->message ) ) {
                    CekListDikerjakan::create([
                        'jumlah'              => $this->message,
                        'staf_id'             => $whatsapp_bot->staf_id,
                        'tenant_id'           => $whatsapp_bot->staf->tenant_id,
                        'cek_list_ruangan_id' => $cek->id
                    ]);
                } else {
                    $this->message_reply .= 'Balasan anda tidak dikenali. Mohon masukkan angka';
                    $this->message_reply .= PHP_EOL;
                    $this->message_reply .= PHP_EOL;
                }
            } else if (
                !is_null( $whatsapp_bot ) &&
                !is_null(  $cek_list_dikerjakan  ) &&
                is_null( $cek_list_dikerjakan->jumlah )
            ) {
                if ( is_numeric( $this->message ) ) {
                    $cek_list_dikerjakan->jumlah = $this->message;
                    $cek_list_dikerjakan->save();
                } else {
                    $this->message_reply .= 'Balasan anda tidak dikenali. Mohon masukkan angka';
                    $this->message_reply .= PHP_EOL;
                    $this->message_reply .= PHP_EOL;
                }
            } else if (
                !is_null(  $cek_list_dikerjakan  ) &&
                !is_null( $whatsapp_bot ) &&
                is_null( $cek_list_dikerjakan->image )
            ) {
                if ( $this->message_type == 'image' ) {
                    $cek_list_dikerjakan->image = $this->uploadImage();
                    $cek_list_dikerjakan->save();
                } else {
                    $this->message_reply .= 'Balasan anda tidak dikenali. Mohon masukkan gambar';
                    $this->message_reply .= PHP_EOL;
                    $this->message_reply .= PHP_EOL;
                }
            }

            // ================================================================
            // Setelah database terupdate / tidak terupdate, lanjut atur autoreply berdasarkan data yang tersimpan
            // ================================================================
            //
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
                        $this->autoReply($this->pesanCekListHarianBerikutnya( $cek ) );
                    } else if (
                        is_null($cek_list_dikerjakan->image)
                    ) {
                        $this->autoReply($this->masukkanGambar( $cek ) );
                    }
                } else {
                    $this->autoReply($this->pesanCekListHarianBerikutnya( $cek ) );
                }
            } else {
                $cek_list_selesai = true;
            }
        } else {
            $cek_list_selesai = true;
        }

        if ($cek_list_selesai) {
            $this->autoReply($this->cekListSelesai($whatsapp_bot_service_id,$whatsapp_bot_service_id_input) );
        }
    }
    /* public function prosesCekListDikerjakanInput( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input ){ */
    /*     $this->chatBotLog(__LINE__); */
    /*     $message = ''; */
    /*     $cek = $this->cekListBelumDilakukan( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input ); */
    /*     if (!is_null($cek)) { */
    /*         $this->chatBotLog(__LINE__); */
    /*         $cek_list_dikerjakan = $this->cekListDikerjakanUntukCekListRuanganIni( $cek->id ); */
    /*         $whatsapp_bot = WhatsappBot::with('staf') */
    /*             ->where('no_telp', $this->no_telp) */
    /*             ->whereRaw('whatsapp_bot_service_id = ' . $whatsapp_bot_service_id. ' or whatsapp_bot_service_id = ' . $whatsapp_bot_service_id_input) */
    /*             ->first(); */
    /*         if ( */
    /*             !is_null(  $cek_list_dikerjakan  ) && */
    /*             !is_null( $whatsapp_bot ) && */
    /*             is_null( $cek_list_dikerjakan->jumlah ) */
    /*         ) { */
    /*             $this->chatBotLog(__LINE__); */
    /*             if (is_numeric( $this->message )) { */
    /*                 $cek_list_dikerjakan->jumlah = $this->message; */
    /*                 $cek_list_dikerjakan->save(); */
    /*             } else { */
    /*                 $message .= 'Balasan anda tidak dikenali. Mohon masukkan angka'; */
    /*                 $message .= PHP_EOL; */
    /*                 $message .= PHP_EOL; */
    /*             } */
    /*         } else if ( */
    /*             !is_null(  $cek_list_dikerjakan  ) && */
    /*             !is_null( $whatsapp_bot ) && */
    /*             is_null( $cek_list_dikerjakan->image ) */
    /*         ) { */
    /*             $this->chatBotLog(__LINE__); */
    /*             if ( $this->message_type == 'image' ) { */
    /*                 $cek_list_dikerjakan->image = $this->uploadImage(); */
    /*                 $cek_list_dikerjakan->save(); */
    /*             } else { */
    /*                 $message .= 'Balasan anda tidak dikenali. Mohon masukkan gambar'; */
    /*                 $message .= PHP_EOL; */
    /*                 $message .= PHP_EOL; */
    /*             } */
    /*         } */

    /*         $cek = $this->cekListBelumDilakukan( $frekuensi_cek_id, $whatsapp_bot_service_id, $whatsapp_bot_service_id_input ); */

    /*         if (!is_null($cek)) { */
    /*             $this->chatBotLog(__LINE__); */
    /*             $cek_list_dikerjakan = $this->cekListDikerjakanUntukCekListRuanganIni( $cek->id ); */
    /*             if ( */
    /*                 is_null( $cek_list_dikerjakan->jumlah ) */
    /*             ) { */
    /*                 $this->chatBotLog(__LINE__); */
    /*                 $message .= $this->pesanCekListHarianBerikutnya( $cek ); */
    /*             } else if ( */
    /*                 is_null( $cek_list_dikerjakan->image ) */
    /*             ) { */
    /*                 $this->chatBotLog(__LINE__); */
    /*                 $message .= $this->masukkanGambar( $cek ); */
    /*             } */
    /*         } */
    /*         $this->autoReply( $message ); */
    /*     } else { */
    /*         $this->autoReply($this->cekListSelesai($whatsapp_bot_service_id,$whatsapp_bot_service_id_input) ); */
    /*     } */
    /* } */
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
        $tenant_id = session()->get('tenant_id');
        $query .= "AND cld.tenant_id = $tenant_id ";
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

    /* public function prosesAntrianOnline(){ */
    /*     echo "Antrian online saat ini sedang dalam proses pemeliharaan dan untuk sementara waktu tidak dapat digunakan. Kami mohon maaf atas ketidaknyamanannya."; */
    /* } */

    public function prosesAntrianOnline()
    {
        // ===== bootstrap & guards =====
        $this->chatBotLog(__LINE__);

        $tz     = 'Asia/Jakarta';
        $nowJkt = \Carbon\Carbon::now($tz);

        // konsisten tenant
        $tenant = $this->tenant ?? (function () {
            $tenant_id = session()->get('tenant_id');
            return $tenant_id ? \App\Models\Tenant::find($tenant_id) : null;
        })();

        // ===== jika klinik tutup → hentikan alur =====
        if ($tenant && $tenant->jam_buka && $tenant->jam_tutup) {
            $this->chatBotLog(__LINE__);
            // pakai tanggal hari ini + TZ supaya perbandingan akurat
            $jam_buka  = $this->todayTime(($tenant->jam_buka),  $tz, $nowJkt);
            // buffer 30 menit sebelum tutup
            $jam_tutup = $this->todayTime(($tenant->jam_tutup), $tz, $nowJkt)->subMinutes(30);

            if ($nowJkt->lt($jam_buka) || $nowJkt->gt($jam_tutup)) {
                $this->autoReply($this->clinicClosedMessage($jam_buka, $jam_tutup));
                return false; // IMPORTANT: stop di sini
            }
        }

        // ===== normalisasi pesan user =====
        $rawMsg    = is_string($this->message) ? trim($this->message) : '';
        $msg       = strtolower($rawMsg);
        $firstChar = (is_string($this->message) && strlen($this->message) > 0) ? $this->message[0] : null;

        // ===== ambil state reservasi =====
        $reservasi_online = \App\Models\ReservasiOnline::with('pasien')
            ->where('no_telp', $this->no_telp)
            ->where('reservasi_selesai', 0)
            ->when(isset($this->whatsapp_bot) && $this->whatsapp_bot?->id, function ($q) {
                $q->where('whatsapp_bot_id', $this->whatsapp_bot->id);
            })
            ->first();

        $message           = '';
        $input_tidak_tepat = false;

        // cache jadwal gigi hari ini utk fitur lain
        $this->jadwalGigi = $this->jamBukaDokterGigiHariIni();

        $this->chatBotLog(__LINE__);
        // ===== kalau state hilang: hentikan sesi =====
        if (is_null($reservasi_online)) {
            $this->chatBotLog(__LINE__);
            if ($this->whatsapp_bot) {
                $this->whatsapp_bot->delete();
            }
            return false;
        }

        // ===== pembatalan cepat =====
        if ($msg === 'batalkan') {
            $this->chatBotLog(__LINE__);
            $this->autoReply($this->konfirmasiPembatalan());
            return false;
        }

        $this->chatBotLog(__LINE__);
        // ===== pilih tipe konsultasi =====
        if (is_null($reservasi_online->tipe_konsultasi_id)) {
            $this->chatBotLog(__LINE__);

            // hanya terima '1' / '2' / '3' / '4'
            if (in_array($msg, ['1','2','3','4'], true)) {
                $this->chatBotLog(__LINE__);

                $tipeMsg   = (string)$msg;           // input user (string)
                $tipeDbInt = (int)$tipeMsg;          // default sama dg input
                // Map khusus: SpDV pada input '4' tapi di DB tipe 6
                if ($tipeMsg === '4') {
                    $tipeDbInt = 6;
                }

                // cek jadwal petugas hari ini & saat ini
                $anchorTime = $nowJkt->format('H:i:s');

                $petugas_pemeriksa_hari_ini = \App\Models\PetugasPemeriksa::query()
                    ->where('tipe_konsultasi_id', $tipeDbInt)
                    ->whereDate('tanggal', $nowJkt->toDateString())
                    ->orderBy('jam_mulai', 'asc')
                    ->get();

                $petugas_pemeriksa_sekarang = \App\Models\PetugasPemeriksa::query()
                    ->where('tipe_konsultasi_id', $tipeDbInt)
                    ->whereDate('tanggal', $nowJkt->toDateString())
                    ->where('jam_mulai', '<=', $anchorTime)
                    ->where('jam_akhir', '>=', $anchorTime)
                    ->get();

                $tipe_konsultasi = \App\Models\TipeKonsultasi::find($tipeDbInt);

                if ($petugas_pemeriksa_hari_ini->isEmpty()) {
                    $labelTipe = $tipe_konsultasi->tipe_konsultasi ?? 'pemeriksa';
                    $message   = 'Hari ini tidak ada pelayanan ' . $labelTipe . '.';
                    $message  .= PHP_EOL.PHP_EOL.'Mohon maaf atas ketidaknyamanannya.';
                    $message  .= PHP_EOL.PHP_EOL.$this->hapusAntrianWhatsappBotReservasiOnline();
                    $this->autoReply($message);
                    return false;
                }

                // === GIGI ===
                if ($tipeMsg === '2') {
                    $this->chatBotLog(__LINE__);
                    $err = $this->validasiDokterPengambilanAntrianDokterGigi();
                    if (!is_null($err)) {
                        $err .= PHP_EOL.PHP_EOL.$this->hapusAntrianWhatsappBotReservasiOnline();
                        $this->autoReply($err);
                        return false;
                    }
                    $reservasi_online->tipe_konsultasi_id = $tipeDbInt;
                    $reservasi_online->save();

                // === USG ===
                } elseif ($tipeMsg === '3') {
                    $jadwal_usg = \App\Models\JadwalKonsultasi::where('hari_id', $nowJkt->isoWeekday())
                        ->where('tipe_konsultasi_id', $tipeDbInt)
                        ->first();

                    if ($jadwal_usg) {
                        if ($tenant && !$tenant->usg_available) {
                            $message  = 'Karena satu dan lain hal, pelayanan USG hari ini ditiadakan.';
                            $message .= PHP_EOL.PHP_EOL.'Kakak dapat mendaftar kembali saat jadwal USG kehamilan tersedia.';
                            $message .= PHP_EOL.'Untuk info jadwal, ketik "Jadwal USG".';
                            $message .= PHP_EOL.'Mohon maaf atas ketidaknyamanannya.';
                            $message .= PHP_EOL.PHP_EOL.$this->hapusAntrianWhatsappBotReservasiOnline();
                            $this->autoReply($message);
                            return false;
                        }

                        $jamAkhir     = \Carbon\Carbon::parse($jadwal_usg->jam_akhir, $tz);
                        $cutoffDaftar = $jamAkhir->copy()->subHour();

                        if ($nowJkt->gt($jamAkhir)) {
                            $message  = 'Pelayanan USG kehamilan hari ini telah selesai pada pukul '.$jamAkhir->format('H:i').'.';
                            $message .= PHP_EOL.'Untuk info jadwal, ketik "Jadwal USG".';
                            $message .= PHP_EOL.'Mohon maaf atas ketidaknyamanannya.';
                            $message .= PHP_EOL.PHP_EOL.$this->hapusAntrianWhatsappBotReservasiOnline();
                            $this->autoReply($message);
                            return false;
                        } elseif ($nowJkt->gt($cutoffDaftar)) {
                            $message  = 'Pendaftaran USG kehamilan *secara online* hari ini telah ditutup.';
                            $message .= PHP_EOL.'Kakak masih dapat mendaftar *langsung* di klinik hingga pukul '.$jamAkhir->format('H:i').' atau telepon 021-5977529.';
                            $message .= PHP_EOL.PHP_EOL.'Mohon maaf atas ketidaknyamanannya.';
                            $message .= PHP_EOL.PHP_EOL.$this->hapusAntrianWhatsappBotReservasiOnline();
                            $this->autoReply($message);
                            return false;
                        } else {
                            $reservasi_online->tipe_konsultasi_id = $tipeDbInt;
                            $reservasi_online->save();
                        }
                    } else {
                        $message  = 'Hari ini tidak tersedia jadwal USG kehamilan. Mohon mendaftar saat jadwal tersedia.';
                        $message .= PHP_EOL.'Untuk info jadwal, ketik "Jadwal USG".';
                        $message .= PHP_EOL.PHP_EOL.'Mohon maaf atas ketidaknyamanannya.';
                        $message .= PHP_EOL.PHP_EOL.$this->hapusAntrianWhatsappBotReservasiOnline();
                        $this->autoReply($message);
                        return false;
                    }

                // === SpDV (input '4' → DB tipe 6) ===
                } elseif ($tipeMsg === '4') {
                    $jadwal_spdv  = \App\Models\JadwalKonsultasi::where('hari_id', $nowJkt->isoWeekday())
                        ->where('tipe_konsultasi_id', 6)
                        ->first();
                    $petugas_spdv = \App\Models\PetugasPemeriksa::whereDate('tanggal', $nowJkt->toDateString())
                        ->where('tipe_konsultasi_id', 6)
                        ->first();

                    if (is_null($jadwal_spdv) && is_null($petugas_spdv)) {
                        $message  = 'Hari ini tidak tersedia jadwal Dokter Spesialis Kulit. Mohon mendaftar saat jadwal tersedia.';
                        $message .= PHP_EOL.PHP_EOL.'Mohon maaf atas ketidaknyamanannya.';
                        $message .= PHP_EOL.PHP_EOL.$this->hapusAntrianWhatsappBotReservasiOnline();
                        $this->autoReply($message);
                        return false;
                    } elseif (!is_null($jadwal_spdv) && is_null($petugas_spdv)) {
                        $message  = 'Hari ini Dokter Spesialis Kulit berhalangan. Mohon mendaftar kembali saat jadwal tersedia.';
                        $message .= PHP_EOL.PHP_EOL.'Mohon maaf atas ketidaknyamanannya.';
                        $message .= PHP_EOL.PHP_EOL.$this->hapusAntrianWhatsappBotReservasiOnline();
                        $this->autoReply($message);
                        return false;
                    } else {
                        // jadwal & petugas ada → lanjut
                        $reservasi_online->tipe_konsultasi_id = 6;
                        $reservasi_online->save();
                    }

                // === default tipe lain ===
                } else {
                    $this->chatBotLog(__LINE__);
                    if (!$this->sudahAdaAntrianUntukTipeKonsultasi($tipeDbInt)) {
                        $this->chatBotLog(__LINE__);
                        $labelTipe = $tipe_konsultasi->tipe_konsultasi ?? 'pemeriksa';
                        $message   = 'Saat ini tidak ada antrian di ' . $labelTipe . '. Silakan datang dan ambil antrian langsung.';
                        $message  .= PHP_EOL.PHP_EOL.$this->hapusAntrianWhatsappBotReservasiOnline();
                        $this->autoReply($message);
                        return false;
                    }

                    $reservasi_online->tipe_konsultasi_id = $tipeDbInt;
                    $reservasi_online->save();

                    $petugas_pemeriksa = $this->petugas_pemeriksa_sekarang($reservasi_online);
                    // jika hanya ada satu petugas pemeriksa disana
                    if ($petugas_pemeriksa->count() === 1) {
                        $this->chatBotLog(__LINE__);
                        $reservasi_online->staf_id              = $petugas_pemeriksa->first()->staf_id;
                        $reservasi_online->petugas_pemeriksa_id = $petugas_pemeriksa->first()->id;
                        $reservasi_online->ruangan_id           = $petugas_pemeriksa->first()->ruangan_id;
                        $reservasi_online->save();
                    }
                }
            } else {
                $input_tidak_tepat = true;
            }

        // ===== pilih metode pembayaran =====
        } elseif (!is_null($reservasi_online->tipe_konsultasi_id) && is_null($reservasi_online->registrasi_pembayaran_id)) {
            $this->chatBotLog(__LINE__);
            if ($this->validasiRegistrasiPembayaran()) {
                $reservasi_online = $this->lanjutkanRegistrasiPembayaran($reservasi_online);
                $data = $this->queryPreviouslySavedPatientRegistry();

                if ((int)$reservasi_online->registrasi_pembayaran_id === 1) {
                    $reservasi_online->kartu_asuransi_image = '';
                }
                if ((int)$reservasi_online->registrasi_pembayaran_id !== 2) {
                    $reservasi_online->nomor_asuransi_bpjs = '';
                }
                if (!count($data)) {
                    $reservasi_online->register_previously_saved_patient = 0;
                }
                $reservasi_online->save();
            } else {
                $input_tidak_tepat = true;
            }

        // ===== pilih pasien tersimpan / input baru =====
        } elseif (!is_null($reservasi_online->tipe_konsultasi_id) && !is_null($reservasi_online->registrasi_pembayaran_id) && is_null($reservasi_online->register_previously_saved_patient)) {
            $this->chatBotLog(__LINE__);
            $data      = $this->queryPreviouslySavedPatientRegistry();
            $dataCount = count($data);

            if (ctype_digit($msg) && (int)$msg > 0 && (int)$msg <= $dataCount) {
                $pasien = $data[(int)$msg - 1];
                $pasien = \App\Models\Pasien::find($pasien->pasien_id);

                if ((int)$reservasi_online->registrasi_pembayaran_id === 2) { // BPJS
                    if (!empty(trim($pasien->nomor_asuransi_bpjs)) && empty(pesanErrorValidateNomorAsuransiBpjs($pasien->nomor_asuransi_bpjs))) {
                        try {
                            $bpjs     = new \App\Http\Controllers\BpjsApiController;
                            $response = $bpjs->pencarianNoKartuValid($pasien->nomor_asuransi_bpjs, true);
                        } catch (\Throwable $e) {
                            // fallback offline bila API error
                            $response = ['code' => 599, 'message' => 'BPJS API unreachable'];
                        }

                        $code    = $response['code'] ?? null;
                        $respMsg = $response['response'] ?? null;

                        $reservasi_online->data_bpjs_cocok = $this->nomorKartuBpjsDitemukanDiPcareDanDataKonsisten($response, $pasien);

                        if ($code === 204) {
                            // tidak ditemukan—paksa update data lokal, kosongkan kartu pasien
                            $reservasi_online->pasien_id                         = $pasien->id;
                            $reservasi_online->nama                              = $pasien->nama;
                            $reservasi_online->tanggal_lahir                     = $pasien->tanggal_lahir;
                            $reservasi_online->alamat                            = $pasien->alamat;
                            $reservasi_online->register_previously_saved_patient = (int)$msg;

                            $pasien->nomor_asuransi_bpjs = null;
                            $pasien->save();

                        } elseif ($code >= 200 && $code <= 299) {
                            if (!is_null($respMsg) && ($respMsg['aktif'] ?? false) && ($respMsg['kdProviderPst']['kdProvider'] ?? null) === '0221B119') {
                                $reservasi_online->pasien_id                         = $pasien->id;
                                $reservasi_online->nama                              = $pasien->nama;
                                $reservasi_online->tanggal_lahir                     = $pasien->tanggal_lahir;
                                $reservasi_online->alamat                            = $pasien->alamat;
                                $reservasi_online->register_previously_saved_patient = (int)$msg;

                                if (!empty($pasien->bpjs_image)) {
                                    $reservasi_online->kartu_asuransi_image = $pasien->bpjs_image;
                                }
                                if (!empty($pasien->nomor_asuransi_bpjs)) {
                                    $reservasi_online->nomor_asuransi_bpjs = $pasien->nomor_asuransi_bpjs;
                                    $reservasi_online->verifikasi_bpjs     = 1;
                                }
                            } elseif (!is_null($respMsg) && !($respMsg['aktif'] ?? true)) {
                                $input_tidak_tepat = true;
                                $this->pesan_error = 'Kartu tidak aktif karena :' . PHP_EOL . ($respMsg['ketAktif'] ?? '-');
                            } elseif (!is_null($respMsg) && ($respMsg['kdProviderPst']['kdProvider'] ?? null) !== '0221B119') {
                                $input_tidak_tepat = true;
                                $this->pesan_error = $this->validasiBpjsProviderSalah($pasien->nomor_asuransi_bpjs, $respMsg);
                            } else {
                                $reservasi_online->nomor_asuransi_bpjs = $rawMsg;
                                $log = new \App\Models\BpjsApiLog;
                                $log->nomor_bpjs    = $rawMsg;
                                $log->error_message = $response['message'] ?? 'unknown';
                                $log->save();
                            }
                        } else {
                            // fallback
                            $reservasi_online->pasien_id                         = $pasien->id;
                            $reservasi_online->nama                              = $pasien->nama;
                            $reservasi_online->tanggal_lahir                     = $pasien->tanggal_lahir;
                            $reservasi_online->alamat                            = $pasien->alamat;
                            $reservasi_online->nomor_asuransi_bpjs               = $pasien->nomor_asuransi_bpjs;
                            $reservasi_online->register_previously_saved_patient = (int)$msg;
                            $reservasi_online->verifikasi_bpjs                   = 0;
                        }
                    } else {
                        // tidak ada/invalid no BPJS di pasien tersimpan
                        $reservasi_online->pasien_id                         = $pasien->id;
                        $reservasi_online->nama                              = $pasien->nama;
                        $reservasi_online->tanggal_lahir                     = $pasien->tanggal_lahir;
                        $reservasi_online->alamat                            = $pasien->alamat;
                        $reservasi_online->data_bpjs_cocok                   = 0;
                        $reservasi_online->register_previously_saved_patient = (int)$msg;
                    }
                } else {
                    // Non-BPJS
                    $reservasi_online->pasien_id                         = $pasien->id;
                    $reservasi_online->nama                              = $pasien->nama;
                    $reservasi_online->tanggal_lahir                     = $pasien->tanggal_lahir;
                    $reservasi_online->alamat                            = $pasien->alamat;
                    $reservasi_online->register_previously_saved_patient = (int)$msg;
                }
            } else {
                // user memilih "buat baru" / input manual (bukan angka valid)
                $reservasi_online->register_previously_saved_patient = $rawMsg;
                $reservasi_online->registering_confirmation          = 0;
            }
            $reservasi_online->save();

        // ===== alur input BPJS (jika dipilih BPJS) =====
        } elseif (
            !is_null($reservasi_online->tipe_konsultasi_id)
            && !is_null($reservasi_online->registrasi_pembayaran_id)
            && !is_null($reservasi_online->register_previously_saved_patient)
            && is_null($reservasi_online->nomor_asuransi_bpjs))
        {
            $this->chatBotLog(__LINE__);

            $this->pesan_error = pesanErrorValidateNomorAsuransiBpjs($rawMsg);
            if (empty($this->pesan_error)) {
                try {
                    $bpjs     = new \App\Http\Controllers\BpjsApiController;
                    $response = $bpjs->pencarianNoKartuValid($rawMsg, true);
                } catch (\Throwable $e) {
                    $response = ['code' => 599, 'message' => 'BPJS API unreachable'];
                }

                $respMsg = $response['response'] ?? null;
                $code    = $response['code'] ?? null;

                if ($code === 204) {
                    $input_tidak_tepat = true;
                    $this->pesan_error = $response['message'] ?? 'Nomor BPJS tidak ditemukan di sistem BPJS';
                } elseif ($code >= 200 && $code <= 299) {
                    $pasien = \App\Models\Pasien::where('nomor_asuransi_bpjs', $rawMsg)->first();
                    if ($pasien) {
                        $reservasi_online->data_bpjs_cocok = $this->nomorKartuBpjsDitemukanDiPcareDanDataKonsisten($response, $pasien);
                    }

                    if (!is_null($respMsg) && ($respMsg['aktif'] ?? false)
                        && isset($respMsg['kdProviderPst']) && ($respMsg['kdProviderPst']['kdProvider'] ?? null) === '0221B119'
                        && $reservasi_online->data_bpjs_cocok) {

                        if ($pasien) {
                            $reservasi_online->nomor_asuransi_bpjs = $rawMsg;
                            $reservasi_online->pasien_id           = $pasien->id;
                            $reservasi_online->nama                = $pasien->nama;
                            $reservasi_online->alamat              = $pasien->alamat;
                            $reservasi_online->tanggal_lahir       = $pasien->tanggal_lahir;
                            if ($tenant && $tenant->image_bot_enabled) {
                                $reservasi_online->kartu_asuransi_image = $pasien->bpjs_image;
                            }
                            $reservasi_online->verifikasi_bpjs = 1;
                        } else {
                            $reservasi_online->nama                 = $respMsg['nama'] ?? $reservasi_online->nama;
                            $reservasi_online->nomor_asuransi_bpjs  = $rawMsg;
                            if (!empty($respMsg['tglLahir'] ?? '')) {
                                $reservasi_online->tanggal_lahir = \Carbon\Carbon::createFromFormat('d-m-Y', $respMsg['tglLahir'])->format('Y-m-d');
                            }
                        }
                    } elseif (!is_null($respMsg) && !($respMsg['aktif'] ?? true)) {
                        $input_tidak_tepat = true;
                        $this->pesan_error = 'Kartu tidak aktif karena :' . PHP_EOL . ($respMsg['ketAktif'] ?? '-');
                    } elseif (!is_null($respMsg) && ($respMsg['kdProviderPst']['kdProvider'] ?? null) !== '0221B119') {
                        $input_tidak_tepat = true;
                        $this->pesan_error = $this->validasiBpjsProviderSalah($rawMsg, $respMsg);
                    } elseif (!is_null($respMsg) && !$reservasi_online->data_bpjs_cocok) {
                        $reservasi_online->nomor_asuransi_bpjs = $rawMsg;
                        $reservasi_online->verifikasi_bpjs     = 0;
                    } else {
                        $reservasi_online->nomor_asuransi_bpjs = $rawMsg;
                        $log = new \App\Models\BpjsApiLog;
                        $log->nomor_bpjs    = $rawMsg;
                        $log->error_message = json_encode($response);
                        $log->save();
                    }
                } else {
                    // fallback offline
                    $pasien = \App\Models\Pasien::where('nomor_asuransi_bpjs', $rawMsg)->first();
                    $reservasi_online->nomor_asuransi_bpjs = $rawMsg;
                    if ($pasien) {
                        $reservasi_online->pasien_id     = $pasien->id;
                        $reservasi_online->nama          = $pasien->nama;
                        $reservasi_online->alamat        = $pasien->alamat;
                        $reservasi_online->tanggal_lahir = $pasien->tanggal_lahir;
                        if ($tenant && $tenant->image_bot_enabled) {
                            $reservasi_online->kartu_asuransi_image = $pasien->bpjs_image;
                        }
                        $reservasi_online->verifikasi_bpjs = 0;
                    }
                }
            } else {
                $input_tidak_tepat = true;
            }
            $reservasi_online->save();

        // ===== input identitas + upload kartu + pilih petugas =====
        } elseif (
            !is_null($reservasi_online->tipe_konsultasi_id)
            && !is_null($reservasi_online->registrasi_pembayaran_id)
            && !is_null($reservasi_online->register_previously_saved_patient)
            && is_null($reservasi_online->nama))
        {
            $this->chatBotLog(__LINE__);
            if (validateName($rawMsg)) {
                $reservasi_online->nama = ucwords(strtolower($rawMsg));
                $reservasi_online->save();
            } else {
                $input_tidak_tepat = true;
            }

        } elseif (
            !is_null($reservasi_online->nomor_asuransi_bpjs)
            && !is_null($reservasi_online->nama)
            && is_null($reservasi_online->tanggal_lahir))
        {
            $this->chatBotLog(__LINE__);
            $tanggal = $this->convertToPropperDate();
            if (!is_null($tanggal)) {
                $reservasi_online->tanggal_lahir = $tanggal;
                $reservasi_online->save();
            } else {
                $input_tidak_tepat = true;
            }

        } elseif (
            !is_null($reservasi_online->tanggal_lahir)
            && is_null($reservasi_online->alamat))
        {
            $this->chatBotLog(__LINE__);
            $reservasi_online->alamat = $rawMsg;
            $reservasi_online->save();

        } elseif (
            !is_null($reservasi_online->alamat)
            && is_null($reservasi_online->kartu_asuransi_image))
        {

            $this->chatBotLog(__LINE__);
            if ($this->isPicture()) {
                $reservasi_online->kartu_asuransi_image = $this->uploadImage();
                $reservasi_online->save();
            } else {
                $input_tidak_tepat = true;
            }

        // ===== pilih staf =====
        } elseif (
            !is_null($reservasi_online->kartu_asuransi_image)
            && is_null($reservasi_online->staf_id))
        {
            $this->chatBotLog(__LINE__);

            $petugas = $this->petugas_pemeriksa_sekarang($reservasi_online);
            if (ctype_digit($msg) && (int)$msg > 0 && (int)$msg <= $petugas->count()) {
                $idx = (int)$msg - 1;
                $pp  = $petugas->get($idx); // PetugasPemeriksa

                $this->chatBotLog(__LINE__);

                // jika sudah ada reservasi_online dengan pasien yang sama dan staf yang sama di hari yang sama
                // hapus reservasi sebelumnya
                $schedulled_reservation_existing = SchedulledReservation::query()
                    ->whereDate('created_at', $nowJkt->format('Y-m-d'))
                    ->where('staf_id', $pp->staf_id)
                    ->where('pasien_id', $reservasi_online->pasien_id)
                    ->where('tenant_id', $reservasi_online->tenant_id)
                    ->first();


                if (
                    $schedulled_reservation_existing
                ) {
                    $this->chatBotLog(__LINE__);
                    //hapus reservasi yang dibuat saat ini
                    //kembalikan reservasi yang ada untuk dikirimkan qr code
                    $message = "Pendaftaran untuk {$schedulled_reservation_existing->nama} ke {$schedulled_reservation_existing->staf->nama_dengan_gelar} hari ini sudah dibuat. ";
                    $message .= PHP_EOL;
                    $message .= PHP_EOL;
                    $message .= 'Sistem akan mengirimkan reservasi yang lama';
                    $message .= PHP_EOL;
                    $message .= PHP_EOL;

                    $message .= $this->balasanReservasiTerjadwalDibuat( $schedulled_reservation_existing );
                    $this->autoReply( $message );
                    $reservasi_online->delete();
                    return;
                }
                // set staf & ruangan
                $reservasi_online->staf_id              = $pp->staf_id;
                $reservasi_online->petugas_pemeriksa_id = $pp->id;
                $reservasi_online->ruangan_id           = $pp->ruangan_id;
                $reservasi_online->schedulled_booking   = $pp->schedulled_booking_allowed;

                // ===== window booking terjadwal =====
                $isSchedulingAllowed = (int)($pp->schedulled_booking_allowed ?? 0) === 1;
                $hasOpenTime         = !empty( $this->tenant->jam_buka );
                $reservasi_online->save();
            } else {
                $this->chatBotLog(__LINE__);
                $input_tidak_tepat = true;
            }
            $this->chatBotLog(__LINE__);


        // ===== konfirmasi akhir: lanjutkan/ulangi =====
        } elseif (!$reservasi_online->reservasi_selesai) {
            $this->chatBotLog(__LINE__);
            if (!$reservasi_online->reservasi_selesai) {
                $this->chatBotLog(__LINE__);
                $lanjutByButton = ($msg === 'lanjutkan' && $tenant && $tenant->iphone_whatsapp_button_available);
                $ulangByButton  = ($msg === 'ulangi'    && $tenant && $tenant->iphone_whatsapp_button_available);
                $lanjutByText   = ($firstChar === '1'   && $tenant && !$tenant->iphone_whatsapp_button_available);
                $ulangByText    = ($firstChar === '2'   && $tenant && !$tenant->iphone_whatsapp_button_available);

                if ($lanjutByButton || $lanjutByText) {
                    $reservasi_online->reservasi_selesai = 1;
                    $this->chatBotLog(__LINE__);
                    \DB::transaction(function () use ($reservasi_online, $tz) {
                        $this->chatBotLog(__LINE__);
                        if ((int)($reservasi_online->schedulled_booking ?? 0) === 1) {
                            $this->chatBotLog(__LINE__);
                            // Booking terjadwal: lock kuota saat finalisasi
                            $ppFinal = $reservasi_online->petugas_pemeriksa;

                            if ($ppFinal && !$ppFinal->slot_pendaftaran_available) {
                                $this->chatBotLog(__LINE__);
                                $reservasi_online->waitlist_flag      = 0;
                                $reservasi_online->schedulled_booking = 2;
                                $reservasi_online->save();

                                $this->autoReply($this->pesanKuotaPenuhPerPetugasDenganWaitlist($ppFinal));
                            } else {
                                // generate QR booking (tanpa membuat antrian tapi buat schedulled_reservations)
                                $reservasi_online->save();
                                $data = $reservasi_online->toArray();
                                unset($data['id']);
                                unset($data['pasien']);
                                unset($data['petugas_pemeriksa']);
                                unset($data['schedulled_reservation']);
                                $this->chatBotLog("====================");
                                $this->chatBotLog("DAT");
                                $this->chatBotLog( $data );
                                $this->chatBotLog("====================");
                                $schedulled_reservation         = SchedulledReservation::create($data);

                                $schedulled_reservation->qrcode = $this->generateQrCodeForOnlineReservation('B', $schedulled_reservation);
                                $schedulled_reservation->save();

                                // hapus ketika schedulled_reservation dibuat
                                $reservasi_online->delete();
                                $message = $this->balasanReservasiTerjadwalDibuat( $schedulled_reservation );
                                $this->autoReply( $message );
                            }
                        } else {

                            $this->chatBotLog(__LINE__);

                            // === Non-schedulled: buat antrian ===
                            $this->input_nomor_bpjs              = $reservasi_online->nomor_asuransi_bpjs;
                            $this->input_registrasi_pembayaran_id = $reservasi_online->registrasi_pembayaran_id;

                            $antrian                        = $this->antrianPost($reservasi_online->ruangan_id);
                            $antrian->nama                  = $reservasi_online->nama;
                            $antrian->nomor_bpjs            = $reservasi_online->nomor_asuransi_bpjs;
                            $antrian->no_telp               = $reservasi_online->no_telp;
                            $antrian->tanggal_lahir         = $reservasi_online->tanggal_lahir;
                            $antrian->alamat                = $reservasi_online->alamat;
                            $antrian->pasien_id             = $reservasi_online->pasien_id;
                            $antrian->verifikasi_bpjs       = $reservasi_online->verifikasi_bpjs;
                            $antrian->ruangan_id            = $reservasi_online->ruangan_id;
                            $antrian->tipe_konsultasi_id    = $reservasi_online->tipe_konsultasi_id;
                            $antrian->staf_id               = $reservasi_online->staf_id;
                            $antrian->petugas_pemeriksa_id  = $reservasi_online->petugas_pemeriksa_id;
                            $antrian->kartu_asuransi_image  = $reservasi_online->kartu_asuransi_image;
                            $antrian->data_bpjs_cocok       = $reservasi_online->data_bpjs_cocok;
                            $antrian->reservasi_online      = 1;
                            $antrian->sudah_hadir_di_klinik = 0;
                            $antrian->qr_code_path_s3       = $this->generateQrCodeForOnlineReservation('A', $antrian);
                            $antrian->save();
                            $antrian->antriable_id          = $antrian->id;
                            $antrian->save();

                            $reservasi_online->reservasi_selesai = 1;
                            $reservasi_online->save();

                            $this->autoReply($this->getQrCodeMessage($antrian));
                            resetWhatsappRegistration( $this->no_telp );
                        }
                    });

                    return false;

                } elseif ($ulangByButton || $ulangByText) {
                    $this->chatBotLog(__LINE__);
                    $this->whatsapp_bot   = $reservasi_online->whatsappBot;
                    $reservasi_online     = $this->createReservasiOnline(true);
                } else {
                    $this->chatBotLog(__LINE__);
                    $input_tidak_tepat = true;
                }
            }
        // ===== waitlist (ketika kuota penuh) =====
        } elseif ($reservasi_online->schedulled_booking === 2 && $reservasi_online->waitlist_flag == 0) {
            $this->chatBotLog(__LINE__);
            if (in_array($msg, ['ya','y','1'], true)) {
                $this->chatBotLog(__LINE__);
                $reservasi_online->waitlist_flag = 1; // setuju waitlist
                $reservasi_online->save();

                $data                 = $reservasi_online->toArray();
                unset($data['id']);
                unset($data['pasien']);
                unset($data['petugas_pemeriksa']);
                unset($data['schedulled_reservation']);
                $schedulled_reservation = SchedulledReservation::create($data);
            } else {
                $this->chatBotLog(__LINE__);
                $input_tidak_tepat = true;
            }
        }

        // ===== pesan tindak lanjut (prompt berikutnya) =====
        if (
            (int)($reservasi_online->schedulled_booking ?? 0) === 2 &&
            is_null($reservasi_online->waitlist_flag)
        ) {
            $this->chatBotLog(__LINE__);
            $message = $this->tanyaGabungWaitlist();

        } elseif (is_null($reservasi_online->tipe_konsultasi_id)) {
            $this->chatBotLog(__LINE__);
            $message = $this->pertanyaanPoliYangDituju();

        } elseif ( is_null($reservasi_online->registrasi_pembayaran_id)) {
            $this->chatBotLog(__LINE__);
            $message = $this->pertanyaanPembayaranPasien($reservasi_online);

        } elseif ( !is_null($reservasi_online->registrasi_pembayaran_id) && is_null($reservasi_online->register_previously_saved_patient)) {
            $this->chatBotLog(__LINE__);
            $message = $this->pesanUntukPilihPasien();

        } elseif ( !is_null($reservasi_online->register_previously_saved_patient) && is_null($reservasi_online->nomor_asuransi_bpjs)) {
            $this->chatBotLog(__LINE__);
            $message = $this->tanyaNomorBpjsPasien();

        } elseif ( !is_null($reservasi_online->nomor_asuransi_bpjs) && is_null($reservasi_online->nama)) {
            $this->chatBotLog(__LINE__);
            $message = $this->tanyaNamaLengkapPasien();

        } elseif ( !is_null($reservasi_online->nama) && is_null($reservasi_online->tanggal_lahir)) {
            $this->chatBotLog(__LINE__);
            $message = $this->tanyaTanggalLahirPasien();

        } elseif ( !is_null($reservasi_online->tanggal_lahir) && is_null($reservasi_online->alamat)) {
            $this->chatBotLog(__LINE__);
            $message = $this->tanyaAlamatLengkapPasien();

        } elseif ( !is_null($reservasi_online->alamat) && is_null($reservasi_online->kartu_asuransi_image)) {
            $this->chatBotLog(__LINE__);
            $message = $this->tanyaKartuAsuransiImage($reservasi_online);

        } elseif ( !is_null($reservasi_online->kartu_asuransi_image) && is_null($reservasi_online->staf_id)) {
            $this->chatBotLog(__LINE__);
            $message = $this->tanyaSiapaPetugasPemeriksa($reservasi_online);

        } elseif ( !$reservasi_online->reservasi_selesai) {
            $this->chatBotLog(__LINE__);
            $message = $this->tanyaLanjutkanAtauUlangi($reservasi_online);
        } elseif (
            $reservasi_online->schedulled_booking == 2 &&
            $reservasi_online->waitlist_flag == 1
        ) {
            $this->chatBotLog(__LINE__);
            $message = $this->pesanWaitlistTercatat( $reservasi_online );
            \App\Models\WhatsappBot::where('no_telp', $this->no_telp)->delete();
        }

        if (!empty(trim($message ?? ''))) {
            $message .= PHP_EOL . $this->samaDengan() . $this->batalkan();
            if ($input_tidak_tepat) {
                $message .= $this->pesanMintaKlienBalasUlang();
            }
            $this->autoReply($message);
        }
    }

    /**
     * Gabungkan waktu (HH:mm/HH:mm:ss atau full datetime) menjadi Carbon di tanggal hari ini & TZ.
     */
    private function todayTime(string $timeStr, string $tz, \Carbon\Carbon $today): \Carbon\Carbon
    {
        $trim = trim($timeStr);
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $trim)) {
            return \Carbon\Carbon::parse($today->toDateString() . ' ' . $trim, $tz)->seconds(0);
        }
        return \Carbon\Carbon::parse($trim, $tz)->seconds(0);
    }

    /**
     * Pesan standar klinik tutup (dengan buffer 30 menit sebelum tutup).
     */
    private function clinicClosedMessage(\Carbon\Carbon $jam_buka, \Carbon\Carbon $jam_tutup): string
    {
        $buka  = $jam_buka->format('H:i');
        $tutup = $jam_tutup->format('H:i');

        $msg  = "Klinik *buka* pukul {$buka}";
        $msg .= PHP_EOL . "Klinik *tutup* pukul {$tutup}";
        $msg .= PHP_EOL . PHP_EOL . "Mohon ulangi kembali saat klinik buka.";
        $msg .= PHP_EOL . "Terima kasih 🙏";
        return $msg;
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
            $model->kartu_asuransi_image     = '';
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
        $message .= $this->samaDengan();
        $message .= PHP_EOL;
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
        if (
            isset( $model->nomor_antrian ) &&
            !is_null( $model->nomor_antrian )
        ) {
            $response .= 'Nomor Antrian: ' . $model->nomor_antrian  ;
            $response .= PHP_EOL;
        }
        if ( !is_null( $model->nama ) ) {
            $response .= 'Nama Pasien: ' . ucwords($model->nama)  ;
            $response .= PHP_EOL;
        }
        if ( !is_null( $model->registrasi_pembayaran_id ) ) {
            $response .= 'Pembayaran : ';
            $response .= ucwords($model->registrasi_pembayaran->pembayaran);
            $response .= PHP_EOL;
        }
        if ( !is_null( $model->tanggal_lahir ) ) {
            $response .= 'Tanggal Lahir : '.  Carbon::parse($model->tanggal_lahir)->format('d M Y');
            $response .= PHP_EOL;
        }
        if ( !is_null( $model->alamat ) ) {
            $response .= 'Alamat  : '.  $model->alamat;
            $response .= PHP_EOL;
        }
        if ( !is_null( $model->staf_id ) ) {
            $response .= 'Pemeriksa  : '.  $model->staf->nama_dengan_gelar;
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
            $response .= $this->tanyaSyaratdanKetentuan($model);
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
        $message = 'Mohon jawab pertanyaan berikut untuk mendaftar ONLINE';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Bisa dibantu poli yang dituju?';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $jumlah_antrian = $this->jumlahAntrian();
        $message .= '1. Dokter Umum (ada ' . $jumlah_antrian[1]. ' antrian)';
        $message .= PHP_EOL;
        $message .= '2. Dokter Gigi';
        $message .= PHP_EOL;
        $message .= '3. USG Kehamilan';
        $message .= PHP_EOL;
        $message .= '4. Dokter Spesialis Kulit dan Kelamin';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Balas dengan angka *1, 2 atau 3* sesuai dengan informasi di atas';
        return $message;
    }
    public function jamBukaDokterGigiHariIni(){
        $dayNameNumber = date('w');
        $jadwal = PetugasPemeriksa::where('tanggal', date('Y-m-d'))
                                ->where('tipe_konsultasi_id', 2)
                                ->first();
        if (is_null($jadwal)) {
            return false;
        } else {
            $jam_mulai = Carbon::parse( $jadwal->jam_mulai )->format("H:i");
            $jam_akhir = Carbon::parse($jadwal->jam_akhir)->format("H:i");
            return  compact('jam_mulai', 'jam_akhir');
        }
    }


	public function antrianPost($id){
        $carbon     = Carbon::now();
        $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay   = $carbon->endOfDay()->format('Y-m-d H:i:s');

        $antrian = new Antrian;
        $ruangan = Ruangan::find($id) ;
        if ( !is_null($ruangan) ) {
            $tipe_konsultasi_id = $ruangan->default_tipe_konsultasi_id;
        }

        $antrian = Antrian::create([
            'tenant_id'                => 1 ,
            'ruangan_id'               => $id ,
            'tipe_konsultasi_id'       => $tipe_konsultasi_id ,
            'registrasi_pembayaran_id' => $this->input_registrasi_pembayaran_id,
            'kode_unik'                => $this->kodeUnik(),
            'antriable_type'           => 'App\\Models\\Antrian',
        ]);

		$antrian->antriable_id             = $antrian->id;
		$antrian->save();

		$this->updateJumlahAntrian(false, null);
		return $antrian;
	}

    private function kodeUnik()
    {
        $kode_unik = substr(str_shuffle(MD5(microtime())), 0, 5);

        $carbon = Carbon::now();
        $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay = $carbon->endOfDay()->format('Y-m-d H:i:s');

        while (Antrian::whereBetween('created_at', [
                                    $startOfDay,
                                    $endOfDay
                                ])->where('kode_unik', $kode_unik)->exists()) {
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
    public function isPicture(){
        if ( $this->message_type == 'image' ) {
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
        return "Bisa dibantu jelaskan keluhan yang dialami? (kirim dalam 1 pesan)";
    }
    public function tanyaNamaLengkapAtauPilihPasien($konsultasi_estetik_online){
        $data = $this->queryPreviouslySavedPatientRegistry();
        if (count($data)) {
            $message = $this->pesanUntukPilihPasien();
        } else {
            $konsultasi_estetik_online->register_previously_saved_patient = 0;
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

    public function validasiWaktuPelayanan(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(14);
    }
    public function simpanBalasanValidasiWaktuPelayanan(){
        $complain = Complain::find( $this->whatsapp_bot->complain_id );
        $complain->complain = $complain->complain . '<br><br>' . $this->message;
        $complain->save();
    }
    public function whatsappGambarPeriksaExists(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(7);
    }
    public function prosesGambarPeriksa(){
        $antrian_periksa = AntrianPeriksa::where('whatsapp_bot_id', $this->whatsapp_bot->id)->first();
        if ( is_null( $antrian_periksa ) ) {
            $this->whatsapp_bot->delete();
            $this->autoReply("Pemeriksaan telah seleai, Gambar tidak dimasukkan ke pemeriksaan" );

        } else if ( $this->isPicture() ) {
            $filename = $this->uploadImage();
            $antrian_periksa->gambarPeriksa()->create([
                'nama' => $filename,
                'keterangan' => 'Gambar Periksa '
            ]);

            event(new GambarSubmitted($antrian_periksa->id));
            $this->autoReply($this->nextPicturePlease() );

        } else if ( $this->message == 'selesai' ) {
            $this->whatsapp_bot->delete();
            $this->autoReply("Terima kasih atas inputnya. Pesan kakak akan dibalas ketika dokter estetik sedang berpraktik" );
        } else  {
            $this->autoReply("Balasan yang kakak buat bukan gambar. Mohon masukkan gambar" );
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
    private function jumlahAntrian(){
        $tipe_konsultasis = TipeKonsultasi::all();
        $result = [];
        foreach ($tipe_konsultasis as $tipe) {
            $result[ $tipe->id ] = $tipe->sisa_antrian;
        }
        return $result;
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
        curl_setopt($curl, CURLOPT_URL,  "https://solo.wablas.com/api/send-image");
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
    public function generateQrCodeForOnlineReservation($prefix, $antrian){

        $text = $prefix . $antrian->id;
        $filename = $text. '.png';
        $qr_code = QrCode::create($text)
                         ->setSize(600)
                         ->setMargin(40)
                         ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh);

        $writer = new PngWriter;

        $result = $writer->write($qr_code);

        // Output the QR code image to the browser
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

    private function tanyaSiapaPetugasPemeriksa(ReservasiOnline $reservasi_online): string
    {
        $nowJkt = Carbon::now('Asia/Jakarta');

        /** @var \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $list */
        $list = collect($this->petugas_pemeriksa_sekarang($reservasi_online));

        // Hindari N+1 saat akses $petugas->staf (jika Eloquent collection)
        if (method_exists($list, 'loadMissing')) {
            $list->loadMissing('staf');
        }

        // === Formatter default: pakai sisa_antrian ===
        $formatDefault = function (Collection $list, int $tipe_konsultasi_id): string
        {
            if ($list->count() < 1) {
                $tipe = TipeKonsultasi::find($tipe_konsultasi_id)?->tipe_konsultasi ?? 'pemeriksa';
                return 'Tidak ada petugas ' . ucwords($tipe) . ' yang bertugas saat ini';
            }

            $message = 'Silahkan pilih Dokter pemeriksa.' . PHP_EOL;

            foreach ($list as $k => $petugas) {
                $no   = $k + 1;
                $nama = $petugas->staf->nama_dengan_gelar
                    ?? $petugas->staf->nama
                    ?? 'Dokter';
                $sisa = (int) ($petugas->sisa_antrian ?? 0);

                $message .= PHP_EOL
                          . $no . '. ' . $nama . PHP_EOL
                          . '(' . $sisa . ' Antrian)' . PHP_EOL;
            }

            $ops = $this->joinOpsi($list->count());
            $message .= PHP_EOL . 'Balas dengan angka *' . $this->sanitizeWhatsApp($ops) . '* sesuai dengan pilihan di atas';
            return $message;
        };

        // === Formatter gigi: tampilkan window waktu (jam_mulai-30)–(jam_akhir-60) ===
        $formatGigi = function (Collection $list): string
        {
            if ($list->count() < 1) {
                return 'Tidak ada petugas Dokter Gigi yang bertugas saat ini';
            }

            $message = 'Silakan pilih Dokter pemeriksa.' . PHP_EOL;

            foreach ($list as $k => $petugas) {
                $no   = $k + 1;
                $nama = $petugas->staf->nama_dengan_gelar
                    ?? $petugas->staf->nama
                    ?? 'Dokter Gigi';

                $mulaiRaw = $petugas->jam_mulai ?? null;
                $akhirRaw = $petugas->jam_akhir ?? null;

                $mulai = $mulaiRaw ? Carbon::parse($mulaiRaw, 'Asia/Jakarta') : null;
                $akhir = $akhirRaw ? Carbon::parse($akhirRaw, 'Asia/Jakarta')->subHour() : null;

                $mulaiStr = $mulai ? $mulai->format('H:i') : '-';
                $akhirStr = $akhir ? $akhir->format('H:i') : '-';

                // Jika range terbalik, fallback ke jam asli bila ada
                if ($mulai && $akhir && $akhir->lessThan($mulai) && $mulaiRaw && $akhirRaw) {
                    $mulaiStr = Carbon::parse($mulaiRaw, 'Asia/Jakarta')->format('H:i');
                    $akhirStr = Carbon::parse($akhirRaw, 'Asia/Jakarta')->format('H:i');
                }

                $message .= PHP_EOL
                          . $no . '. ' . $nama . PHP_EOL
                          . '(' . $mulaiStr . ' - ' . $akhirStr . ')' . PHP_EOL
                          . 'Pengambilan antrian online wajib melakukan scan QR CODE di klinik sebelum pukul '
                          . ($mulaiRaw
                                ? Carbon::parse($mulaiRaw, 'Asia/Jakarta')->subMinutes(15)->format('H:i')
                                : '-')
                          . '. atau reservasi dihapus oleh sistem.'
                          . PHP_EOL;
            }

            $ops = $this->joinOpsi($list->count());
            $message .= PHP_EOL . 'Balas dengan angka *' . $this->sanitizeWhatsApp($ops) . '* sesuai dengan pilihan di atas';
            return $message;
        };

        // === Routing per tipe ===
        $tipeId = (int) ($reservasi_online->tipe_konsultasi_id ?? 0);

        if ($tipeId === 1) {
            return $formatDefault($list, 1);
        }

        if ($tipeId === 2) {
            return $formatGigi($list);
        }

        return $formatDefault($list, $tipeId);
    }

    /** Helper: rangkai opsi "1 atau 2" / "1, 2, 3 atau 4" */
    private function joinOpsi(int $n): string
    {
        if ($n <= 1) return '1';
        if ($n === 2) return '1 atau 2';
        return implode(', ', range(1, $n - 1)) . ' atau ' . $n;
    }

    /** Opsional: cegah karakter spesial WA di dalam teks angka opsinya */
    private function sanitizeWhatsApp(string $text): string
    {
        return str_replace(['*', '_', '~', '`'], ['＊', '＿', '～', '｀'], $text);
    }

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
        $to   = date('Y-m-d 23:59:59');
        if ( $this->message == 1 ) {
            $this->autoReply($this->hapusAntrianWhatsappBotReservasiOnline() );
        } else if(
             $this->message == 2
        ) {
            WhatsappBot::whereRaw("created_at between '{$from}' and '{$to}'")
                ->where('no_telp', $this->no_telp)
                ->delete();
            $this->autoReply('Reservasi Online Dilanjutkan' );
        } else {
            $message = $this->tanyaApakahMauMembatalkanReservasiOnline();
            $message .= $this->samaDengan();
            $message .= PHP_EOL;
            $message .= "_Balasan yang anda masukkan tidak dikenali. Mohon diulangi_";
            $this->autoReply($message );
        }
    }

    /**
     * undocumented function
     *
     * @return void
     */
    private function tanyaApakahMauMembatalkanReservasiOnline(){
        $carbon = Carbon::now();
        $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay = $carbon->endOfDay()->format('Y-m-d H:i:s');
        $antrians = Antrian::where('no_telp', $this->no_telp)
                        ->whereBetween('created_at', [
                                    $startOfDay,
                                    $endOfDay
                                ])
                        ->get();

        $nomor_antrians = [];

        foreach ($antrians as $antrian) {
            $nomor_antrians[] = $antrian->nomor_antrian;
        }

        $text = '';

        foreach ($nomor_antrians as $k => $nomor) {
            if ($k) {
                $text .= ',' . $nomor;
            } else if(
                count( $nomor_antrians ) == 1 ||
                $k == count($nomor_antrians) -1
            ) {
                $text .= $nomor;
            } else {
                $text .= ' dan ' . $nomor;
            }
        }

        $message = 'Anda akan membatalkan antrian ';
        if (!empty( trim(  $text  ) )) {
            $message .= '*' . $text . '*';
        }
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
        $message .= PHP_EOL;
        $message .= 'Ketik *chat admin* untuk mendapatkan bantuan dari admin';
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

    private function tanyaSyaratdanKetentuan($reservasi_online): string
    {
        $this->chatBotLog(__LINE__);
        $tz      = 'Asia/Jakarta';
        $now     = \Carbon\Carbon::now($tz);
        $tipe_id = (int) ($reservasi_online->tipe_konsultasi_id ?? 0);

        // Label tipe: override khusus USG Kehamilan
        $tipe_konsultasi =
            $tipe_id === 3
                ? 'USG Kehamilan'
                : (\App\Models\TipeKonsultasi::query()->find($tipe_id)->tipe_konsultasi ?? 'konsultasi');

        $lines   = [];
        $lines[] = '*Dengan melanjutkan berarti Anda setuju dengan ketentuan berikut:*';

        $lines[] = '- Jika antrean terlewat, silakan mengambil antrean kembali.';
        if ($tipe_id === 1) {
            // Poli umum
            $lines[] = '- Pastikan hadir dan melakukan *scan QR* di klinik *30 menit* sebelum antrean Anda dipanggil.';
        } elseif ($tipe_id === 2) {
            // Dokter gigi
            // Ambil jam mulai dari jadwal gigi bila tersedia (contoh `$this->jadwalGigi['jam_mulai'] = "17:00"`)
            $jamMulaiGigiStr = $reservasi_online->petugas_pemeriksa->jam_mulai_default;

            if (!empty($jamMulaiGigiStr)) {
                $jamMulaiGigi   = $this->parseTodayTime($jamMulaiGigiStr, $tz, $now);
                $jamTerakhirQR  = $jamMulaiGigi->copy()->subMinutes(15)->format('H:i');

                $lines[] = "- Melakukan *scan QR* di klinik *sebelum pukul {$jamTerakhirQR}* (15 menit sebelum jam praktik dimulai) atau reservasi ini dihapus oleh sistem";
                $lines[] = "- Nomor Antrian diberikan setelah Scan QR Code dan urutan nomor antrian berdasarkan urutan Scan QR Code";
                $lines[] = "- Pendaftaran dokter gigi secara langsung dimulai jam {$jamMulaiGigi->format('H:i')} hanya bila slot pendaftaran masih tersedia";
            } else {
                // Fallback bila jadwal gigi belum di-set
                $lines[] = '- Melakukan *scan QR* di klinik *paling lambat 15 menit* sebelum jam mulai pemeriksaan gigi.';
                $lines[] = '- Antrean akan *dibatalkan* apabila terlambat melakukan scan.';
            }
        } elseif ($tipe_id === 3) {
            // USG Kehamilan
            $lines[] = '';

            // Cari jam terakhir penerimaan pasien USG utk hari ini (N: 1=Senin ... 7=Minggu)
            $hariId = (int) $now->format('N');

            $rowUsg = \App\Models\JadwalKonsultasi::query()
                ->where('tipe_konsultasi_id', 3) // konsisten: USG = 3
                ->where('hari_id', $hariId)
                ->first();

            if ($rowUsg && !empty($rowUsg->jam_akhir)) {
                $jamAkhir = $this->parseTodayTime($rowUsg->jam_akhir, $tz, $now)->format('H:i');
                $lines[]  = "*Terakhir penerimaan pasien: pukul {$jamAkhir}*.";
            } else {
                $lines[]  = '*Terakhir penerimaan pasien mengikuti jam akhir layanan USG pada hari ini.*';
            }

            $lines[] = '';
            $lines[] = 'Syarat USG dengan Asuransi BPJS:';
            $lines[] = '- Membawa Buku KIA.';
            $lines[] = '- Pemeriksaan kehamilan *pertama* pada usia kehamilan 4–12 minggu; *atau*';
            $lines[] = '- Pemeriksaan kehamilan *kelima* pada usia kehamilan di atas 28 minggu.';
        }
        $lines[] = '';
        $lines[] = '';
        return implode(PHP_EOL, $lines);
    }

/**
 * Parse string jam (mis. "17:00" atau "17:00:00") menjadi Carbon di tanggal hari ini (timezone tertentu).
 * Jika string sudah berupa datetime, tetap dikembalikan dalam TZ yang diinginkan.
 */
private function parseTodayTime(string $timeStr, string $tz, \Carbon\Carbon $today): \Carbon\Carbon
{
    // Normalisasi: jika hanya "HH:mm" / "HH:mm:ss", gabungkan dengan tanggal hari ini
    $trim = trim($timeStr);
    if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $trim)) {
        return \Carbon\Carbon::parse($today->toDateString() . ' ' . $trim, $tz);
    }

    // Jika sudah lengkap (punya tanggal), tetap parse lalu set timezone
    return \Carbon\Carbon::parse($trim, $tz);
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
    /**
     * undocumented function
     *
     * @return void
     */
    private function getReservasiOnlineBelumHadirHariIni()
    {
        $tz    = 'Asia/Jakarta';
        $today = \Carbon\Carbon::now($tz)->toDateString();

        // Ambil semua antrean hari ini utk no_telp ini, belum hadir
        // OPSIONAL: batasi hanya yang bersumber dari ReservasiOnline/SchedulledReservation
        $antrians = \App\Models\Antrian::with(['staf.titel', 'ruangan', 'antriable'])
            ->whereDate('created_at', $today)
            ->where('no_telp', $this->no_telp)
            ->where('sudah_hadir_di_klinik', 0)
            // ->whereIn('antriable_type', [\App\Models\ReservasiOnline::class, \App\Models\SchedulledReservation::class]) // aktifkan jika ingin exclude walk-in
            ->get();

        $schedulled = \App\Models\SchedulledReservation::with(['staf.titel', 'ruangan']) // load seragam
            ->whereDate('created_at', $today)
            ->where('no_telp', $this->no_telp)
            ->get();

        // Gabungkan & urutkan
        return $antrians
            ->concat($schedulled)
            ->sortBy(fn($it) => optional($it->created_at)->getTimestamp() ?? 0)
            ->values();
    }

    private function opsiAngkaText(int $n): string
    {
        if ($n <= 1) return '*1*';
        $nums = range(1, $n);
        $last = array_pop($nums);
        return '*' . implode(', ', $nums) . ' atau ' . $last . '*';
    }


    private function hapusAntrianWhatsappBotReservasiOnline(bool $hapus_antrian = true)
    {
        $items = $this->getReservasiOnlineBelumHadirHariIni();
        // Reset state WA registration (tetap lakukan meski tidak ada item)
        resetWhatsappRegistration($this->no_telp);

        if ($items->isEmpty()) {
            return 'Reservasi antrian dan semua fitur dibatalkan. Mohon dapat mengulangi kembali jika dibutuhkan.';
        }

        if ($items->count() === 1) {
            /** @var mixed $item */
            $item = $items->first();

            // Pastikan relasi staf.titel termuat tanpa query ulang
            if (method_exists($item, 'load')) {
                $item->load('staf.titel');
            }

            $nama        = $item->nama ?? '-';
            $nama_dokter = optional($item->staf)->nama_dengan_gelar ?? optional($item->staf)->nama ?? '-';

            // Kirim notifikasi dulu
            $this->autoReply("Reservasi $nama antrian ke $nama_dokter dan semua fitur dibatalkan. Mohon dapat mengulangi kembali jika dibutuhkan.");

            DB::transaction(function () use ($item) {
                $item->delete();
            });

            return 'OK';
        }

        // Jika lebih dari satu, bangun pesan daftar pilihan (nomori & jelaskan tipenya)
        $lines = [];
        foreach ($items as $idx => $it) {
            $tipe  = $it instanceof Antrian ? 'Antrian' : ($it instanceof SchedulledReservation ? 'Reservasi Terjadwal' : 'Item');
            $dokter = optional($it->staf)->nama_dengan_gelar ?? optional($it->staf)->nama ?? '-';
            $jam    = optional($it->created_at)->timezone('Asia/Jakarta')->format('H:i');
            $lines[] = ($idx + 1) . ". [$tipe] {$it->nama} ke $dokter (dibuat $jam)";
        }

        $pesan = "Terdapat beberapa reservasi/antrean aktif untuk nomor ini hari ini:\n"
            . implode("\n", $lines)
            . "\n\nBalas dengan angka yg ingin dibatalkan (contoh: 1), atau ketik ‘semua’ untuk membatalkan semuanya.";

        $this->autoReply($pesan);

        WhatsappBot::create([
            'whatsapp_bot_service_id' => 16,
            'staf_id'                 => 0,
            'no_telp'                 => $this->no_telp,
            'prevent_repetition'      => 0,
        ]);
        return 'NEED_SELECTION';
    }

    public function sudahAdaAntrianUntukTipeKonsultasi($tipe_konsultasi_id){
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
                                ->where('tipe_konsultasi_id', $tipe_konsultasi_id)
                                ->count();
    }
    public function registrasiAntrianOnline(){
        $this->chatBotLog(__LINE__);
        if (
            (  date('G') <= 21 && // pendaftaran online tutup jam 21.59
            date('G') >= 7 )
        ) {
            if (
                Antrian::where('no_telp', $this->no_telp)
                    ->whereDate('created_at', date('Y-m-d'))
                    ->whereRaw("
                            (
                                antriable_type = 'App\\\Models\\\Antrian' or
                                antriable_type = 'App\\\Models\\\AntrianPoli' or
                                antriable_type = 'App\\\Models\\\AntrianPeriksa'
                            )
                        ")
                        ->exists()
            ) {
                $this->chatBotLog(__LINE__);

                $message =  'Untuk mendaftarkan pasien selanjutnya silahkan klik link di bawah ini : ';
                $message .= PHP_EOL;
                $message .= PHP_EOL;
                $message .= 'https://www.klinikjatielok.com/daftar_online/' . encrypt_string( $this->no_telp );
                return $message;
            } else {
                $this->whatsapp_bot = WhatsappBot::create([
                    'no_telp' => $this->no_telp,
                    'whatsapp_bot_service_id' => 6 //registrasi online
                ]);
                $reservasi_online = $this->createReservasiOnline();

                return $this->pertanyaanPoliYangDituju();
            }
        } else {
            $message =  'Pendaftaran secara online sudah ditutup dan akan dibuka kembali jam 7 pagi.';
            $message .= PHP_EOL;
            $message .=  'Pendaftaran secara langsung terakhir jam 22.30';
            $message .= PHP_EOL;
            $message .=  'Pelayanan berakhir jam 23:00. ';
            $message .= PHP_EOL;
            $message .=  'Untuk menghubungi silahkan telpon ke 0215977529';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .=  'Mohon maaf atas ketidaknyamanannya';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= $this->hapusAntrianWhatsappBotReservasiOnline();
            return $message;
        }
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
                ( !empty( trim(  $pasien->nomor_ktp  ) ) && !is_null( $pasien->nomor_ktp ) ) && // data nomor ktp terekam
                ( !empty( trim($response['noKTP']) ) && !is_null($response['noKTP']) ) // data nomor ktp ada di pcare
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

    public function informasiNomorFaskesKJE(){
        WhatsappBot::where('no_telp', $this->no_telp)->delete();
        WhatsappMainMenu::where('no_telp', $this->no_telp)->delete();
        $message = 'Nomor Faskes Klinik Jati Elok';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= '*0221b119*';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Gunakan informasi tersebut untuk pindah peserta';
        $message .= PHP_EOL;
        $message .= 'ke Klinik Jati Elok';
        return $message;
    }

    public function registrasiInformasiNomorKartuBpjs(){
        WhatsappBot::create([
            'no_telp' => $this->no_telp,
            'whatsapp_bot_service_id' => 11
        ]);
        $this->autoReply($this->tanyaNomorBpjsPasien() );
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
                $this->autoReply($this->pesanErrorBpjsInformationInquiry() );
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
                    $this->autoReply($text );
                } else {
                    $this->autoReply("Data tidak ditemukan" );
                }
                WhatsappBot::where('no_telp', $this->no_telp)->delete();
            } else {
                $this->autoReply('_Server BPJS saat ini sedang gangguan. Silahkan coba beberapa saat lagi_' );
                WhatsappBot::where('no_telp', $this->no_telp)->delete();
            }
        } else {
            $this->autoReply($this->pesanErrorBpjsInformationInquiry() );
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
        $text = '_Kartu BPJS dengan nomor ' . $nomor_asuransi_bpjs. ' tidak dapat digunakan_';
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= '_Jika menurut Anda ini adalah kesalahan silahkan mengambil antrian secara langsung_';
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= '_Mohon maaf atas ketidaknyamanannya._';
        return $text;
    }
    public function validasiBpjsProviderSalah($nomor_asuransi_bpjs, $message){
        $text = '_Kartu BPJS dengan nomor ' . $nomor_asuransi_bpjs. ' tidak dapat digunakan_';
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= '_Karena saat ini nomor tersebut terdaftar di '.trim( $message['kdProviderPst']['nmProvider'] ).'_';
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= '_Jika menurut Anda ini adalah kesalahan silahkan mengambil antrian secara langsung_';
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= '_Mohon maaf atas ketidaknyamanannya._';
        return $text;
    }
    public function validasiBpjsDataTidakCocok($nomor_asuransi_bpjs, $message){
        $text = '_Kartu BPJS dengan nomor ' . $nomor_asuransi_bpjs. ' tidak dapat digunakan_';
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= '_Karena saat ini nomor tersebut terdaftar atas nama '.trim( $message['nama'] ).'_';
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= '_Jika menurut Anda ini adalah kesalahan silahkan mengambil antrian secara langsung_';
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= '_Mohon maaf atas ketidaknyamanannya._';
        return $text;
    }
    public function autoReplyComplainMessage($antrian_id = null){
        $no_telp = $this->no_telp;
        $complaint             = new WhatsappComplaint;
        $complaint->no_telp    = $no_telp;
        $complaint->antrian_id = $antrian_id;
        $complaint->tenant_id = 1;
        $complaint->save();

        WhatsappRegistration::where('no_telp', $no_telp)->delete();

        $message = "Mohon maaf atas ketidak nyamanan yang kakak alami.";
        $message .= PHP_EOL;
        $message .= "Bisa diinfokan kendala yang kakak alami?";
        return $message;
    }

    public function sendSingle($phone, $message){

        $fonnte = new FonnteController;
        $reply = [
            'message' => $message,
            'url' => null,
            'filename' => null
        ];
        $fonnte->sendFonnte( $phone, $reply );
        /* if ( */
        /*     NoTelp::whereRaw('updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)') */
        /*         ->where('no_telp', $phone) */
        /*         ->exists() */
        /* ) { */
        /*     $url      = 'https://botcake.io/api/public_api/v1/pages/waba_620223831163704/flows/send_content'; */
        /*     $data = [ */
        /*            "psid" => "wa_" . $phone, */
        /*            "payload" => [], */
        /*            "data" => [ */
        /*                     "version" => "v2", */
        /*                     "content" => [ */
        /*                        "messages" => [ */
        /*                           [ */
        /*                              "type" => "text", */
        /*                              "buttons" => [], */
        /*                              "text" => $message */
        /*                           ] */
        /*                        ] */
        /*                     ] */
        /*                  ] */
        /*         ]; */
        /*     $response = Http::withToken(env('BOTCAKE_TOKEN'))->post($url, $data); */
        /* } else { */
        /*     Log::info('Tidak bisa kirim ke yang belum kirim hari ini ' . $phone); */
        /* } */
    }

    /* public function sendSingleWablas($phone, $message){ */
    /*     $curl = curl_init(); */
    /*     $token = env('WABLAS_TOKEN'); */
    /*     $data = [ */
    /*     'phone' => $phone, */
    /*     'message' => $message, */
    /*     ]; */
    /*     curl_setopt($curl, CURLOPT_HTTPHEADER, */
    /*         array( */
    /*             "Authorization: $token", */
    /*         ) */
    /*     ); */
    /*     curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST"); */
    /*     curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); */
    /*     curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data)); */
    /*     curl_setopt($curl, CURLOPT_URL,  "https://solo.wablas.com/api/send-message"); */
    /*     curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); */
    /*     curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); */
    /*     $result = curl_exec($curl); */
    /*     curl_close($curl); */
    /*     return $result; */
    /* } */

    public function noTelpDalamChatWithAdmin(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(12);
    }
    public function createWhatsappChat(){
        if ( !is_null( $this->message ) ) {

            $message_hari_ini = Message::where('no_telp', $this->no_telp)
                                ->where('created_at', '>', $this->whatsapp_bot->created_at)
                                ->first();
            Message::create([
                'no_telp'       => $this->no_telp,
                'message'       => $this->message,
                'tanggal'       => date("Y-m-d H:i:s"),
                'image_url'     => $this->image_url,
                'sending'       => 0,
                'sudah_dibalas' => 0,
                'tenant_id'     => 1,
                'touched'       => 0
            ]);

            event(new RefreshDiscussion( $this->no_telp ));
            event(new RefreshChat());

            if (is_null( $message_hari_ini )) {
                $message = 'Kakak dalam antrian customer service.';
                $message .= PHP_EOL;
                $message .= 'Perkiraan balasan sekitar 15 - 30 menit';
                $message .= PHP_EOL;
                $message .= 'Untuk respon cepat mohon dapat menghubungi 021-5977529';
                $message .= PHP_EOL;
                $message .= 'Balas *akhiri* untuk mengakhiri percakapan';
                $this->autoReply($message );
            }
        }
    }
    public function akhiriChatWithAdmin(){
        resetWhatsappRegistration( $this->no_telp );
        Message::where('no_telp', $this->no_telp)
            ->update([
                'sudah_dibalas' => 1
            ]);
        return 'Fitur dihentikan. Silahkan ulangi apabila ada yang ingin disampaikan';
    }
    public function prosesKonsultasiEstetik(){
        $now = Carbon::now();
        $konsultasi_estetik_online = KonsultasiEstetikOnline::where('no_telp', $this->no_telp)
             ->where('whatsapp_bot_id', $this->whatsapp_bot->id)
             ->whereBetween('created_at',[$now->copy()->startOfDay(), $now->copy()->endOfDay()])
             ->first();
        $message = '';
        $input_tidak_tepat = false;

        if( is_null( $konsultasi_estetik_online ) ){
            $this->whatsapp_bot->delete();
        } else {
        }
        if (
            !is_null( $konsultasi_estetik_online ) &&
            $this->message == 'batalkan'
        ) {
            $konsultasi_estetik_online->delete();
            $this->whatsapp_bot->delete();
            $this->autoReply('Reservasi dibatalkan' );
        } else if (
            !is_null( $konsultasi_estetik_online )
        ) {
            if (
                $this->message == 'ya' ||
                $this->message == 'iy' ||
                $this->message == 'iya' ||
                $this->message == 'y'
            ) {
                $konsultasi_estetik_online->save();
                $message = $this->tanyaNamaLengkapAtauPilihPasien( $konsultasi_estetik_online );
            }
        } else if (
            !is_null( $konsultasi_estetik_online ) &&
            is_null( $konsultasi_estetik_online->register_previously_saved_patient )
       ) {
            $data = $this->queryPreviouslySavedPatientRegistry();
            $dataCount = count($data);
            if ( (int)$this->message <= $dataCount && (int)$this->message > 0  ) {
                $pasien = Pasien::find( $data[0]->pasien_id );
                $konsultasi_estetik_online->register_previously_saved_patient = $this->message;
                $konsultasi_estetik_online->pasien_id                         = $pasien->pasien_id;
                $konsultasi_estetik_online->nama                              = $pasien->nama;
                $konsultasi_estetik_online->alamat                            = $pasien->alamat;
                $konsultasi_estetik_online->sex                            = $pasien->sex;
                if ($pasien->sex == 1) {
                    $konsultasi_estetik_online->hamil                            = 0;
                }
                $konsultasi_estetik_online->tanggal_lahir                     = $pasien->tanggal_lahir;
                $message                                                      = $this->tanyaLanjutkanAtauUlangi($konsultasi_estetik_online);
            } else {
                $konsultasi_estetik_online->register_previously_saved_patient = $this->message;
                $message = $this->tanyaNamaLengkapPasien();
            }
            $konsultasi_estetik_online->save();
        } else if (
            !is_null( $konsultasi_estetik_online ) &&
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
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            is_null( $konsultasi_estetik_online->alamat )
        ) {
            $konsultasi_estetik_online->alamat  = $this->message;
            $konsultasi_estetik_online->save();
            $message = $this->tanyaJenisKelamin();
        } else if (
            !is_null( $konsultasi_estetik_online ) &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            is_null( $konsultasi_estetik_online->sex )
        ) {
            if (
                $this->message == 1
            ) {
                $konsultasi_estetik_online->sex = 1;
                $konsultasi_estetik_online->hamil = 0;
                $message = $this->tanyaLanjutkanAtauUlangi( $konsultasi_estetik_online );
            } else if (
                $this->message == 2
            ) {
                $konsultasi_estetik_online->sex = 0;
                $message = $this->tanyaHamil();
            } else {
                $message = $this->tanyaJenisKelamin();
            }
            $konsultasi_estetik_online->save();
        } else if (
            !is_null( $konsultasi_estetik_online ) &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            !is_null( $konsultasi_estetik_online->sex ) &&
            is_null( $konsultasi_estetik_online->hamil )
        ) {
            if (
                $this->message == 1
            ) {
                $konsultasi_estetik_online->hamil = 1;
                $message = $this->tanyaLanjutkanAtauUlangi( $konsultasi_estetik_online );
            } else if (
                $this->message == 2
            ) {
                $konsultasi_estetik_online->hamil = 0;
                $message = $this->tanyaLanjutkanAtauUlangi( $konsultasi_estetik_online );
            } else {
                $message = $this->tanyaHamil();
            }
            $konsultasi_estetik_online->save();
        } else if (
            !is_null( $konsultasi_estetik_online ) &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            !is_null( $konsultasi_estetik_online->sex ) &&
            !is_null( $konsultasi_estetik_online->hamil ) &&
            is_null( $konsultasi_estetik_online->registering_confirmation )
        ) {
            if (
                $this->message == '1' ||
                $this->message == '2'
            ) {
                if ( $this->message == '1' ) {
                    $konsultasi_estetik_online->registering_confirmation = $this->message;
                    $konsultasi_estetik_online->save();
                    $message = $this->tanyaKeluhanEstetikId();
                } else if ( $this->message == '2' ){
                    $konsultasi_estetik_online = KonsultasiEstetikOnline::create([
                        'whatsapp_bot_id' => $this->whatsapp_bot->id
                    ]);
                    $message = $this->tanyaNamaLengkapAtauPilihPasien( $konsultasi_estetik_online );
                }
            } else {
                $message = $this->tanyaLanjutkanAtauUlangi( $konsultasi_estetik_online );
                $message .= $this->pesanMintaKlienBalasUlang();
                $message = $this->tanyaKeluhanUtama();
            }
        } else if (
            !is_null( $konsultasi_estetik_online ) &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            !is_null( $konsultasi_estetik_online->sex ) &&
            !is_null( $konsultasi_estetik_online->hamil ) &&
            !is_null( $konsultasi_estetik_online->registering_confirmation ) &&
            is_null( $konsultasi_estetik_online->keluhan_estetik_id )
        ) {
            if (
                !is_null( KeluhanEstetik::find( $this->message ))
            ) {
                $konsultasi_estetik_online->keluhan_estetik_id = $this->message;
                $konsultasi_estetik_online->save();
                $message = $this->tanyaKeluhanUtama();
            } else {
                $message = $this->tanyaLanjutkanAtauUlangi( $konsultasi_estetik_online );
                $message .= $this->pesanMintaKlienBalasUlang();
            }
        } else if (
            !is_null( $konsultasi_estetik_online ) &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            !is_null( $konsultasi_estetik_online->sex ) &&
            !is_null( $konsultasi_estetik_online->hamil ) &&
            !is_null( $konsultasi_estetik_online->registering_confirmation ) &&
            is_null( $konsultasi_estetik_online->keluhan_utama )
        ) {
            $konsultasi_estetik_online->keluhan_utama = $this->message;
            $konsultasi_estetik_online->save();

            $message = $this->tanyaPeriodeKeluhanUtama();
        } else if (
            !is_null( $konsultasi_estetik_online ) &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            !is_null( $konsultasi_estetik_online->sex ) &&
            !is_null( $konsultasi_estetik_online->hamil ) &&
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
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            !is_null( $konsultasi_estetik_online->sex ) &&
            !is_null( $konsultasi_estetik_online->hamil ) &&
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
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            !is_null( $konsultasi_estetik_online->sex ) &&
            !is_null( $konsultasi_estetik_online->hamil ) &&
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
                $this->autoReply($text );
                /* echo "Terima kasih atas inputnya. Pesan kakak akan dibalas ketika dokter estetik sedang berpraktik"; */
            } else {
                $message = $this->pertanyaanJenisKulit();
                $message .= $this->pesanMintaKlienBalasUlang();
            }
        } else if (
            !is_null( $konsultasi_estetik_online ) &&
            !is_null( $konsultasi_estetik_online->register_previously_saved_patient ) &&
            !is_null( $konsultasi_estetik_online->nama ) &&
            !is_null( $konsultasi_estetik_online->tanggal_lahir ) &&
            !is_null( $konsultasi_estetik_online->alamat ) &&
            !is_null( $konsultasi_estetik_online->sex ) &&
            !is_null( $konsultasi_estetik_online->hamil ) &&
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
                $antrian_template = [
                    'tipe_konsultasi_id'                                  => 5,
                    'url'                                               => null,
                    'nomor'                                             => Antrian::nomorAntrian(5),
                    'antriable_id'                                      => null,
                    'antriable_type'                                    => 'App\Models\Antrian',
                    'dipanggil'                                         => 0,
                    'no_telp'                                           => $this->no_telp,
                    'nama'                                              => $konsultasi_estetik_online->nama,
                    'tanggal_lahir'                                     => $konsultasi_estetik_online->tanggal_lahir,
                    'tenant_id'                                         => 1,
                    'kode_unik'                                         => Antrian::kodeUnik(),
                    'registrasi_pembayaran_id'                          => 1,
                    'nomor_bpjs'                                        => null,
                    'satisfaction_index'                                => null,
                    'complaint'                                         => '',
                    'register_previously_saved_patient'                 => $konsultasi_estetik_online->register_previously_saved_patient,
                    'pasien_id'                                         => $konsultasi_estetik_online->pasien_id,
                    'recovery_index_id'                                 => null,
                    'informasi_terapi_gagal'                            => null,
                    'menunggu'                                          => 0,
                    'notifikasi_panggilan_aktif'                        => 1,
                    'alamat'                                            => $konsultasi_estetik_online->alamat,
                    'notifikasi_resep_terkirim'                         => 0,
                    'jam_panggil_pasien_di_pendaftaran'                 => null,
                    'jam_selesai_didaftarkan_dan_status_didapatkan'     => null,
                    'jam_mulai_pemeriksaan_fisik'                       => null,
                    'jam_selesai_pemeriksaan_fisik'                     => null,
                    'kartu_asuransi_image'                              => null,
                    'existing_antrian_ids'                              => '[]',
                    'complain_id'                                       => null,
                    'qr_code_path_s3'                                   => null,
                    'reservasi_online'                                  => 0,
                    'sudah_hadir_di_klinik'                             => 0,
                    'data_bpjs_cocok'                                   => 0,
                    'jam_pasien_mulai_mengantri'                        => Carbon::now()->format('Y-m-d H:i:s'),
                    'customer_satisfaction_survey_sent'                 => 0,
                    'terakhir_dipanggil'                                => 0,
                    'menunggu_darurat'                                  => 0,
                    'dipanggil_pemeriksa'                               => 1,
                    'daftarkan_sekarang'                                => 1,
                    'jam_janji_datang'                                  => null,
                    'konfirmasi_waktu_pelayanan'                        => null,
                    'konfirmasi_informasi_waktu_pelayanan_obat_racikan' => null,
                    'verifikasi_bpjs'                                   => 0,
                    'image_penolakan_pcare'                             => null,
                    'complain_pelayanan_lama'                           => 0,
                    'minta_rujukan'                                     => 0
                ];
                $antrian = Antrian::create($antrian_template);
                $antrian->antriable_id = $antrian->id;
                $antrian->save();

                $konsultasi_estetik_online->antrian_id = $antrian->id;
                $konsultasi_estetik_online->save();

                $this->whatsapp_bot->delete();
                $this->autoReply("Terima kasih atas inputnya. Pesan kakak akan dibalas ketika dokter estetik sedang berpraktik" );
            } else  {
                $this->autoReply("Balasan yang kakak buat bukan gambar. Mohon masukkan gambar" );
            }
        }

        if (!empty(trim($message))) {
            $message .= PHP_EOL;
            $message .= $this->samaDengan();
            $message .= $this->batalkan();
            if ( $input_tidak_tepat ) {
                $message .= $this->pesanMintaKlienBalasUlang();
            }
            $this->autoReply($message );
        } else {
        }
    }

    public function konsultasiEstetikOnlineStart(){
        $this->whatsapp_bot = WhatsappBot::create([
            'no_telp' => $this->no_telp,
            'whatsapp_bot_service_id' => 5
        ]);
        KonsultasiEstetikOnline::create([
            'no_telp'         => $this->no_telp,
            'whatsapp_bot_id' => $this->whatsapp_bot->id
        ]);
        $message = 'Kakak akan melakukan registrasi untuk konsultasi kulit dan kecantikan secara online';
        $message .= PHP_EOL;
        $message .= 'Semua biaya yang akan dibebankan setelahnya hanya dapat menggunakan Biaya Pribadi dan tidak dapat menggunakan asuransi apapun';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Apabila setuju dengan ketentuan di atas,';
        $message .= PHP_EOL;
        $message .= 'Ketik *ya* untuk melanjutkan ';
        $message .= $this->batalkan();
        return $message;
    }

    public function lama(){
        return
            str_contains( $this->message, 'lama ') ||
            str_contains( $this->message, 'lambat ') ||
            str_contains( $this->message, 'lama') ||
            str_contains( $this->message, 'lambat') ||
            str_contains( $this->message, ' lambat') ||
            str_contains( $this->message, 'cepat') ||
            str_contains( $this->message, ' lama');
    }
    public function tanyaValidasiWaktuPelayanan(){
        $tanggal_berobat = !is_null( $this->whatsapp_complaint->antrian )? $this->whatsapp_complaint->antrian->created_at->format('Y-m-d 00:00:00') : date('Y-m-d 00:00:00');
        $antrians = Antrian::with('antriable')
            ->where('no_telp', $this->no_telp)
            ->where('created_at', '>=', $tanggal_berobat)
            ->get();

        $message = 'Menurut catatan dalam sistem kami. Waktu pelayanan adalah sebagai berikut:';
        $message .= PHP_EOL;
        $message .= PHP_EOL;

        foreach ($antrians as $k => $antrian) {
            if ( $antrian->antriable_type == 'App\Models\Periksa' ) {
                $pasien_id = $antrian->antriable->pasien_id;
            } else if (
                 $antrian->antriable_type == 'App\Models\AntrianApotek' ||
                 $antrian->antriable_type == 'App\Models\AntrianKasir'
            ) {
                $pasien_id = $antrian->antriable->periksa->pasien_id;
            }
            $periksa = Periksa::where('pasien_id', $pasien_id)
                                ->where('tanggal', $tanggal_berobat)
                                ->first();

            $waktu_tunggu_dokter = Carbon::parse( $periksa->jam_pasien_mulai_mengantri )->format('H:i'). ' - ' . Carbon::parse($periksa->jam_pasien_dipanggil_ke_ruang_periksa)->format('H:i') . '(' . diffInMinutes( $periksa->jam_pasien_mulai_mengantri , $periksa->jam_pasien_dipanggil_ke_ruang_periksa  ) .' menit )';

            $jam_penyerahan_obat = !is_null( $periksa->jam_penyerahan_obat ) ? $periksa->jam_penyerahan_obat : Carbon::now()->format('Y-m-d H:i:s');
            $jam_pasien_selesai_diperiksa = Carbon::parse($periksa->jam_pasien_selesai_diperiksa)->format('H:i');

            $waktu_tunggu_obat = $jam_pasien_selesai_diperiksa. ' - ' . Carbon::parse($jam_penyerahan_obat)->format('H:i') ;
            $waktu_tunggu_obat .= '(' . diffInMinutes( $periksa->jam_pasien_selesai_diperiksa , $jam_penyerahan_obat  ) .' menit )';


            $message .= $k + 1 . '. ';
            $message .= $periksa->pasien->nama;
            $message .= PHP_EOL;
            $message .= 'Waktu Tunggu Dokter : ';
            $message .= PHP_EOL;
            $message .= $waktu_tunggu_dokter;

            if (
                $antrian->antriable->terapi !== '[]'
            ) {
                $message .= PHP_EOL;
                $message .= 'Waktu Tunggu Obat : ';
                $message .= PHP_EOL;
                $message .= $waktu_tunggu_obat;
            }
            $message .= PHP_EOL;
            $message .= PHP_EOL;
        }
        $message .= 'Apakah informasi tersebut benar?';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= '1. Benar';
        $message .= PHP_EOL;
        $message .= '2. Salah';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Balas dengan angka *1 atau 2* sesuai informasi di atas';
        return $message;
    }
    public function chatAdmin(){
        if (
            (
                date('G') < 23 &&
                date('G') >= 7
            ) ||
            $this->no_telp == '6281381912803'
        ) {
            $message = 'Halo.';
            $message .= PHP_EOL;
            $message .= 'Ada yang bisa kami bantu?';

            $this->registerChatAdmin();
            return $message;
        } else {

            return 'Fitur chat admin sudah ditutup dan dibuka kembali jam 7 pagi. Silahkan hubungi melalui telepon 0215977529 mulai jam 7 pagi. Mohon maaf atas ketidaknyamanannya';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= $this->hapusAntrianWhatsappBotReservasiOnline();
        }
    }


    public function balasanKonfirmasiWaktuPelayanan(){
        $carbon = Carbon::now();
        $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay = $carbon->endOfDay()->format('Y-m-d H:i:s');
        $antrian = Antrian::where('no_telp', $this->no_telp)
                        ->whereBetween('created_at', [
                                    $startOfDay,
                                    $endOfDay
                                ])
                        ->first();
        if ( !is_null( $antrian ) ) {
            $message = '';
            if ( is_null(  $antrian->konfirmasi_waktu_pelayanan  ) ) {
                if (
                    $antrian->antriable->resep_racikan &&
                    $antrian->antriable->terapi !== '[]'
                ) {
                    if (
                         $this->message == '1' ||
                         $this->message == '2'
                    ) {
                        $antrian->konfirmasi_waktu_pelayanan = $this->message == '1' ? 1 : 0;
                        $antrian->save();

                        $message = "Apakah sudah diinfokan oleh petugas kami bahwa ";
                        $message .= PHP_EOL;
                        $message .= "Waktu tunggu pelayanan obat racikan antara 30 - 45 menit?";
                        $message .= PHP_EOL;
                        $message .= PHP_EOL;
                        $message .= '1. Sudah diinfokan';
                        $message .= PHP_EOL;
                        $message .= '2. Saya tidak diberitahu sebelumnya';
                        $message .= PHP_EOL;
                        $message .= PHP_EOL;
                        $message .= 'Balas dengan angka *1 atau 2* sesuai informasi di atas';

                    } else {
                        $message = $this->pesanMintaKlienBalasUlang();
                    }
                } else {
                    $antrian->konfirmasi_waktu_pelayanan = 1;
                    $antrian->save();
                    $message = $this->endKonfirmasiWaktuPelanan();
                }
            } else if (
                 !is_null(  $antrian->konfirmasi_waktu_pelayanan  ) &&
                 is_null(  $antrian->konfirmasi_informasi_waktu_pelayanan_obat_racikan  )
            ) {
                if (
                     $this->message == '1' ||
                     $this->message == '2'
                ) {
                    $antrian->konfirmasi_informasi_waktu_pelayanan_obat_racikan = $this->message == '1' ? 1 : 0;
                    $antrian->save();

                    $message = $this->endKonfirmasiWaktuPelanan();
                } else {
                    $message = $this->pesanMintaKlienBalasUlang();
                }
            } else if (
                 !is_null(  $antrian->konfirmasi_waktu_pelayanan  ) &&
                 !is_null(  $antrian->konfirmasi_informasi_waktu_pelayanan_obat_racikan  )
            ) {
                $message = $this->endKonfirmasiWaktuPelanan();
            }
            $this->autoReply($message );
        }
    }

    public function endKonfirmasiWaktuPelanan(){
        $message = 'Terima kasih atas informasi yang sudah kakak berikan';
        $message .= PHP_EOL;
        $message .= 'Informasi ini akan kami gunakan untuk memperbaiki pelayanan kami.';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Kami berharap dapat melayani dengan lebih baik lagi';

        $this->whatsapp_bot->delete();
        WhatsappBot::where('no_telp', $this->no_telp)->delete();
        return $message;
    }
    public function langsungKeAntrianPoliBilaMemungkinkan($antrian){
        if (
            $antrian->pasien_id
        ) {
            if (
                $antrian->registrasi_pembayaran_id == 1 ||
                (
                    $antrian->registrasi_pembayaran_id == 2 && // jika bpjs
                    $antrian->verifikasi_bpjs == 1 // dan sudah verifikasi BPJS
                )
            ) {
                $ap                             = new AntrianPoliController;
                $ap->input_pasien_id            = $antrian->pasien_id;
                $ap->input_asuransi_id          = Asuransi::BiayaPribadi()->id;
                $ap->input_poli_id              = null;
                $ap->input_staf_id              = null;
                $ap->jam_pasien_mulai_mengantri = $antrian->jam_pasien_mulai_mengantri;
                $ap->input_tanggal              = $antrian->created_at->format('Y-m-d');
                $ap->input_bukan_peserta        = 0;
                $ap->input_tenant_id                  = 1;
                $antrian_poli                   = $ap->inputDataAntrianPoli();

                $antrian->antriable_type = 'App\\Models\\AntrianPoli';
                $antrian->antriable_id   = $antrian_poli->id;
                $antrian->save();
            }
        }
    }

    public function tanyaKeluhanEstetikId(){
        $message = "Bisa dibantu keluhan yang dialami?";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        foreach (KeluhanEstetik::all() as $k => $keluhan) {
            $nomor = $k + 1;
            $message .= $nomor . '. ' .$keluhan->keluhan_estetik;
            $message .= PHP_EOL;
        }
        return $message;
    }

    public function tanyaJenisKelamin(){
         $message = 'Bisa dibantu *Jenis Kelamin* pasien?';
         $message .= PHP_EOL;
         $message .= PHP_EOL;
         $message .= '1. Laki-laki';
         $message .= PHP_EOL;
         $message .= '2. Wanita';
         $message .= PHP_EOL;
         $message .= PHP_EOL;
         $message .= 'Balas dengan angka *1 atau 2* sesuai dengan informasi di atas';
         return $message;
    }
    public function tanyaHamil(){
         $message = 'Apakah pasien dalam kondisi hamil?';
         $message .= PHP_EOL;
         $message .= PHP_EOL;
         $message .= '1. Hamil';
         $message .= PHP_EOL;
         $message .= '2. Tidak hamil';
         $message .= PHP_EOL;
         $message .= PHP_EOL;
         $message .= 'Balas dengan angka *1 atau 2* sesuai dengan informasi di atas';
         return $message;
    }


    public function tanyaKapanKeluhanTerjadi(){
        $message = 'Mohon dapat diinfokan tanggal keluhan tersebut terjadi';
        $message .= PHP_EOL;
        $message .= 'Agar kami dapat menindak lanjuti keluhan kakak.';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= '(Contoh : 23-04-2024)';

        return $message;
    }
    public function templateFooter(){
        $message = '==================';
        $message .= PHP_EOL;
        $message .= 'Balas *daftar* untuk mendaftarkan pasien berikutnya';
        $message .= PHP_EOL;
        $message .= 'Balas *batalkan* untuk membatalkan antrian ini';
        $message .= PHP_EOL;
        $message .= 'Balas *cek antrian* untuk melihat antrian terakhir';
        $message .= PHP_EOL;
        $message .= 'Balas *kirim qr* untuk mengirim ulang qr code';
        return $message;
    }

    public function batalkanSemuaFitur(){
    }

    public function petugas_pemeriksa_sekarang($reservasi_online)
    {
        $nowJkt  = Carbon::now('Asia/Jakarta');
        $today   = $nowJkt->toDateString();
        $nowTime = $nowJkt->format('H:i:s');

        // === KASUS KHUSUS: dokter gigi & antrian online dimatikan ===
        if (
            $reservasi_online->tipe_konsultasi_id == 2 &&
            (int) ($this->tenant->dentist_queue_enabled ?? 0) === 1
        ) {

            $tenantId = $reservasi_online->tenant_id ?? ($this->tenant->id ?? 1);
            $tigaPuluhMenitKeDepan = $nowJkt->copy()->addMinutes(30);

            $query = \App\Models\PetugasPemeriksa::with('staf.titel') // eager load
                ->whereDate('tanggal', $today)
                ->where('tipe_konsultasi_id', 2)
                ->where('schedulled_booking_allowed', 1)
                ->where('jam_mulai_default', '>', $tigaPuluhMenitKeDepan->toTimeString() )
                ->where('tenant_id', $tenantId)
                ->whereHas('staf', fn($q) => $q->where('tenant_id', $tenantId))
                ->orderBy('jam_mulai', 'asc');


            // === eksekusi ===
            return $query->get()
                ->unique('id')
                ->values();
        }

        // === BEHAVIOR EXISTING (tetap model) ===
        $base = \App\Models\PetugasPemeriksa::query()
            ->with('staf.titel')
            ->whereDate('petugas_pemeriksas.tanggal', $today)
            ->where('petugas_pemeriksas.tipe_konsultasi_id', $reservasi_online->tipe_konsultasi_id);

        if (!empty($reservasi_online->tenant_id)) {
            $base->where('petugas_pemeriksas.tenant_id', $reservasi_online->tenant_id);
        } elseif (!empty($this->tenant?->id)) {
            $base->where('petugas_pemeriksas.tenant_id', $this->tenant->id);
        }

        $petugas_pemeriksas = (clone $base)
            ->where(function($q) use ($nowTime) {
                $q->where(function($qq) use ($nowTime) {
                    $qq->where('petugas_pemeriksas.jam_mulai', '<=', $nowTime)
                       ->where('petugas_pemeriksas.jam_akhir', '>=', $nowTime);
                })
                ->orWhere(function($qq) use ($nowTime) {
                    $qq->where('petugas_pemeriksas.schedulled_booking_allowed', 1)
                       ->where('petugas_pemeriksas.jam_akhir', '>=', $nowTime)
                       ->where(function($qc) use ($nowTime) {
                           $qc->where(function($q1) use ($nowTime) {
                               $q1->whereRaw("TIME(?) <= TIME(DATE_SUB(petugas_pemeriksas.jam_mulai, INTERVAL 30 MINUTE))", [$nowTime]);
                           })
                           ->orWhere(function($q2) use ($nowTime) {
                               $q2->where('petugas_pemeriksas.jam_mulai', '<=', $nowTime);
                           });
                       });
                });
            })
            ->orderBy('petugas_pemeriksas.jam_mulai', 'asc')
            ->get()
            ->unique('id')
            ->values();

        return $petugas_pemeriksas;
    }
    public function getQrCodeMessage($antrian){
        $message = 'Nomor antrian Anda :';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= '_' . $antrian->nomor_antrian . '_';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Saat ini nomor antrian terpanggil = " . $antrian->nomor_antrian_dipanggil;
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= '_*Scan QR CODE di klinik untuk mengkonfirmasikan kehadiran anda*_';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= $this->aktifkan_notifikasi_otomatis_text();
        $message .= $this->footerAntrian();
        return $message;
    }
    public function cekAntrian(){
        $carbon = Carbon::now();
        $startOfDay = $carbon->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay = $carbon->endOfDay()->format('Y-m-d H:i:s');
        $ant = Antrian::where('no_telp', $this->no_telp)
            ->whereBetween('created_at', [
                        $startOfDay,
                        $endOfDay
                    ])
            ->latest()->first();

        $message  = 'Nomor Antrian ';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= '*' . $ant->nomor_antrian_dipanggil . '*';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        if ( !is_null( $ant ) && !is_null( $ant->antrian_dipanggil ) && $ant->id == $ant->antrian_dipanggil->id ) {
            $message .= 'Dipanggil. Silahkan menuju ruang periksa';
        } else {
            $message .= 'Dipanggil ke ruang periksa.';
            $message .= PHP_EOL;
            $message .= 'Nomor antrian Anda adalah';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= '*'.$ant->nomor_antrian.'*';
            $message .= PHP_EOL;
            if ( $ant->tipe_konsultasi_id == 1 ) {
                $message .= PHP_EOL;
                $sisa_antrian =$ant->sisa_antrian;
                /* $message .= "masih ada *{$sisa_antrian} antrian* lagi"; */
                /* $message .= PHP_EOL; */
                /* $waktu_tunggu = $this->waktuTunggu( $ant->sisa_antrian ); */
                /* $message .= "perkiraan waktu tunggu *{$waktu_tunggu} menit*"; */
                /* $message .= PHP_EOL; */
                /* $message .= PHP_EOL; */
                $message .= $this->aktifkan_notifikasi_otomatis_text();
                if (
                    $ant->reservasi_online &&
                    $sisa_antrian < 11 &&
                    $ant->sudah_hadir_di_klinik == 0
                ) {
                    $message .= PHP_EOL;
                    $message .= 'Mohon kehadirannya di klinik 30 menit sebelum perkiraan panggilan';
                    $message .= PHP_EOL;
                    $message .= PHP_EOL;
                    $message .= 'Jangan lupa *Scan QR CODE* saat sudah tiba di klinik';
                    $message .= PHP_EOL;
                    $message .= 'Apabila antrian Anda terlewat secara otomatis antrian akan terapus oleh sistem';
                    $message .= PHP_EOL;

                } else if (
                    $ant->reservasi_online &&
                    $ant->sudah_hadir_di_klinik == 0
                ) {
                    $message .= PHP_EOL;
                    $message .= 'Balas *stop* untuk berhenti menerima notifikasi ini';
                }
            }
            $message .= PHP_EOL;
            $message .= $this->footerAntrian();
        }
        return $message;
    }
    public function aktifkan_notifikasi_otomatis_text(){
        $message = PHP_EOL;
        $message .= '_Untuk mengakses QR CODE_';
        $message .= PHP_EOL;
        $message .= '_Untuk mendaftarkan pasien selanjutnya_';
        $message .= PHP_EOL;
        $message .= 'Klik link berikut :';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'https://www.klinikjatielok.com/daftar_online/' . encrypt_string( $this->no_telp );
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Anda harus menyimpan nomor whatsapp ini agar dapat mengaktifkan link diatas';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        return $message;
    }

    //PANCAKE
    /* public function autoReply($message){ */
    /*     if ( */
    /*         NoTelp::whereRaw('updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)') */
    /*             ->where('no_telp', $this->no_telp) */
    /*             ->exists() */
    /*     ) { */
    /*         $url      = 'https://botcake.io/api/public_api/v1/pages/waba_620223831163704/flows/send_content'; */
    /*         $data = [ */
    /*                "psid" => "wa_" . $this->no_telp, */
    /*                "payload" => [], */
    /*                "data" => [ */
    /*                         "version" => "v2", */
    /*                         "content" => [ */
    /*                            "messages" => [ */
    /*                               [ */
    /*                                  "type" => "text", */
    /*                                  "buttons" => [], */
    /*                                  "text" => $message */
    /*                               ] */
    /*                            ] */
    /*                         ] */
    /*                      ] */
    /*             ]; */
    /*         $response = Http::withToken(env('BOTCAKE_TOKEN'))->post($url, $data); */
    /*     } else { */
    /*         Log::info('Tidak bisa kirim ke yang belum kirim hari ini ' . $this->no_telp); */
    /*     } */
    /* } */

    // QISCUS
    public function autoReply($message){

        $fonnte = new FonnteController;
        $reply = [
            'message' => $message,
            'url' => null,
            'filename' => null
        ];
        $fonnte->sendFonnte( $this->no_telp, $reply );

        /* if (!is_null( $this->room_id )) { */
        /*     $app_id   = env('QISCUS_APP_ID'); */
        /*     $url      = "https://omnichannel.qiscus.com/$app_id/bot"; */
        /*     $agent_id = $app_id . '_admin@qismo.com'; */
        /*      $data = [ */
        /*        "sender_email" => $agent_id, */
        /*        "message"      => $message, */
        /*        "type"         => "text", */
        /*        "room_id"      => $this->room_id */
        /*     ]; */

        /*     $response = Http::withHeaders([ */
        /*                     'QISCUS_SDK_SECRET' => env('QISCUS_SDK_SECRET'), */
        /*                 ])->post( $url, $data); */
        /* } else if ( */
        /*     $this->fonnte */
        /* ) { */
        /*     $fonnte = new FonnteController; */
        /*     $reply = [ */
        /*         'message' => $message, */
        /*         'url' => null, */
        /*         'filename' => null */
        /*     ]; */
        /*     $fonnte->sendFonnte( $this->no_telp, $reply ); */
        /* } else { */
        /*     echo $message; */
        /* } */
    }

    public function createReservasiOnline($konfirmasi_sdk = false){
        return ReservasiOnline::create([
            'no_telp'              => $this->no_telp,
            'kartu_asuransi_image' => $this->tenant->image_bot_enabled ? null : '',
            'whatsapp_bot_id'      => $this->whatsapp_bot->id,
        ]);

    }
    public function registerChatAdmin(){
        if (
            (
                date('G') < 23 &&
                date('G') >= 7
            ) ||
            $this->no_telp == '6281381912803'
        ) {
            resetWhatsappRegistration( $this->no_telp );
            $this->whatsapp_bot = WhatsappBot::create([
                'no_telp' => $this->no_telp,
                'whatsapp_bot_service_id' => 12 // chat dengan admin
            ]);
        } else {

            return 'Fitur chat admin sudah ditutup dan dibuka kembali jam 7 pagi. Silahkan hubungi melalui telepon 0215977529 mulai jam 7 pagi. Mohon maaf atas ketidaknyamanannya';
        }
    }

    public function chatBotLog($line){
        if (
            $this->tenant->chatbot_log_enabled &&
            $this->no_telp == '6281381912803'
        ) {
            Log::info( $line );
        }
    }
    public function prosesOnsiteRegistration(){
        $onsite_registration          = OnsiteRegistration::where('random_string', $this->random_string)->first();
        $onsite_registration->no_telp = $this->no_telp;
        $onsite_registration->save();

        // jika ada antrian , maka tampilkan halaman kelima
        //

        //kirim kekurangan informasi yang perlu diterima
        //
        //
        //

		event(new OnsiteRegisteredEvent());



        /* $this->whatsapp_registration = WhatsappRegistration::create([ */
        /*     'no_telp'    => $this->no_telp, */
        /*     'antrian_id' => $antrian->id, */
        /*     'registering_confirmation' => 1, */
        /*     'registrasi_pembayaran_id' => $onsite_registration->registrasi_pembayaran_id */
        /* ]); */
        /* $this->proceedRegistering(); */
    }

    public function validasiDokterPengambilanAntrianDokterGigi()
    {
        $nowJkt = \Carbon\Carbon::now('Asia/Jakarta');
        $tigaPuluhMenitKeDepan = $nowJkt->copy()->addMinutes(30);
        // 1) Jika antrian gigi dimatikan (kecuali nomor whitelist)
        if (
            $this->tenant &&
            (int) $this->tenant->dentist_queue_enabled === 0
        ) {
            $message  = $this->pesanAntrolDokterGigiNonAktif();
            $message .= PHP_EOL . PHP_EOL . $this->hapusAntrianWhatsappBotReservasiOnline();
            return $message;
        }

        $query = \App\Models\PetugasPemeriksa::query()
            ->with(['staf','tipe_konsultasi'])
            ->where('tipe_konsultasi_id', 2)
            ->whereDate('tanggal', $nowJkt->toDateString())
            ->where('schedulled_booking_allowed', 1)
            ->orderBy('jam_mulai_default', 'asc');

        $petugas_pemeriksa_terjadwal = $query->get();

        $dokter_gigi_q = \App\Models\PetugasPemeriksa::query()
            ->where('tipe_konsultasi_id', 2)
            ->whereDate('tanggal', $nowJkt->toDateString());

        if ($petugas_pemeriksa_terjadwal->isNotEmpty()) {       // ← perbaikan penting
            $petugas_pemeriksa_terjadwal_awal = $petugas_pemeriksa_terjadwal->first();
            if ( $this->tenant->jam_buka ) {
                $jam_buka = \Carbon\Carbon::parse( $this->tenant->jam_buka, 'Asia/Jakarta');
                if ($nowJkt->lt($jam_buka)) {
                    $message  = 'Pendaftaran online baru dimulai pada ' . $jam_buka->format('H:i');
                    $message .= PHP_EOL . 'Silakan mendaftar kembali setelah jam tersebut.';
                    $message .= PHP_EOL . 'Mohon maaf atas ketidaknyamanannya.';
                    return $message;
                }
            }

            $petugas_pemeriksa_terjadwal_akhir = $petugas_pemeriksa_terjadwal->last();

            if (!empty($petugas_pemeriksa_terjadwal_akhir->jam_mulai_default)) {
                $deadline = \Carbon\Carbon::parse($petugas_pemeriksa_terjadwal_akhir->jam_mulai_default, 'Asia/Jakarta')->subMinutes(30);
                $tipe_konsultasi = ucwords(optional($petugas_pemeriksa_terjadwal_akhir->tipe_konsultasi)->tipe_konsultasi ?? 'Dokter Gigi');

                if ($nowJkt->gte($deadline)) {
                    $message  = "Pendaftaran online terjadwal {$tipe_konsultasi} berakhir pukul {$deadline->format('H:i')}.";
                    $message .= PHP_EOL . PHP_EOL . 'Jadwal Dokter Gigi hari ini :';

                    $petugas_pemeriksa_hari_ini = \App\Models\PetugasPemeriksa::query()
                        ->with('staf')
                        ->where('tipe_konsultasi_id', $petugas_pemeriksa_terjadwal_akhir->tipe_konsultasi_id)
                        ->whereDate('tanggal', $nowJkt->toDateString())
                        ->orderBy('jam_mulai_default')
                        ->get();

                    foreach ($petugas_pemeriksa_hari_ini as $petugas_hari_ini) {
                        $mulai = \Carbon\Carbon::parse($petugas_hari_ini->jam_mulai_default, 'Asia/Jakarta')->format('H:i');
                        $akhir = \Carbon\Carbon::parse($petugas_hari_ini->jam_akhir_default, 'Asia/Jakarta')->subMinutes(30)->format('H:i');
                        $nama  = optional($petugas_hari_ini->staf)->nama_dengan_gelar ?? 'Dokter';

                        $message .= PHP_EOL . "{$nama}: {$mulai}-{$akhir}";
                        if ((int) $petugas_hari_ini->max_booking > 0) {
                            $message .= " (tersisa {$petugas_hari_ini->slot_pendaftaran} slot)"; // ← perbaikan variabel
                            $message .= PHP_EOL;
                            $message .= "Silahkan daftar di klinik jika masih ada slot";
                        }
                    }

                    $message .= PHP_EOL . PHP_EOL . 'Ketik "Jadwal ' . $tipe_konsultasi . '" untuk melihat jadwal di hari lain';
                    return $message;
                }
            }
        } elseif ($dokter_gigi_q->exists()) {
            $this->chatBotLog(__LINE__);
            $dokter_gigi_awal  = (clone $dokter_gigi_q)->orderBy('jam_mulai', 'asc')->first();
            $dokter_gigi_akhir = (clone $dokter_gigi_q)->orderBy('jam_akhir', 'desc')->first();

            $jam_mulai_registrasi_langsung = \Carbon\Carbon::parse($dokter_gigi_awal->jam_mulai, 'Asia/Jakarta');
            $jam_akhir_registrasi_langsung = \Carbon\Carbon::parse($dokter_gigi_akhir->jam_akhir, 'Asia/Jakarta')->subHour(); // (jam_akhir - 1h)

            // Selesai jika sekarang ≥ (jam_akhir - 1 jam)  ← perbaikan: JANGAN subHour() lagi
            if ($nowJkt->gte($jam_akhir_registrasi_langsung)) {
                $this->chatBotLog(__LINE__);
                return $this->reservasiDokterGigiSudahSelesai();
            }

            // Belum mulai registrasi langsung
            if ($nowJkt->lt($jam_mulai_registrasi_langsung)) {
                $this->chatBotLog(__LINE__);
                $message  = 'Pendaftaran online dokter gigi tidak tersedia saat ini.';
                $message .= PHP_EOL . 'Registrasi secara langsung dibuka jam ' . $jam_mulai_registrasi_langsung->format('H:i') . '.';
                $message .= PHP_EOL . "Untuk melihat jadwal yang tersedia ketik 'Jadwal Dokter Gigi'.";
                return $message;
            }
            // Sedang jam praktik normal tanpa scheduled booking → lanjut (tidak return)
        } else {
            // Tidak ada jadwal sama sekali hari ini
            return $this->pelayananPoliGigiLibur();
        }


        // 4) Fallback berbasis jadwal harian (opsional)
        $tz         = 'Asia/Jakarta';
        $now        = \Carbon\Carbon::now($tz);
        $jadwalGigi = $this->jadwalGigi ?? null;

        if (!$jadwalGigi || !(bool) ($this->tenant->dentist_available ?? false)) {
            return $this->pelayananPoliGigiLibur();
        }

        if ((bool) ($this->tenant->dokter_gigi_stop_pelayanan_hari_ini ?? false)) {
            return $this->reservasiDokterGigiSudahSelesai();
        }

        // Masih dibuka → lanjut proses (return null)
        return;
    }

    /**
     * Cek apakah kuota booking terjadwal untuk petugas/shift ini sudah penuh.
     * Menghitung jumlah ReservasiOnline "schedulled_booking = 1" untuk staf & tanggal tsb.
     * max_booking null/0 = tanpa batas.
     */

    protected function kuotaBookingPetugasPenuh($pp, ?int $tipe_konsultasi_id = null): bool
    {
        $max = (int)($pp->max_booking ?? 0);
        if ($max <= 0) return false; // tanpa batas

        $tenantId = optional($this->tenant)->id;

        // Tentukan window booking shift ini
        $startJkt = Carbon::parse( $this->tenant->jam_buka )->seconds(0);
        $endJkt   = Carbon::parse($pp->tanggal.' '.$pp->jam_mulai, 'Asia/Jakarta')->seconds(0);

        // Jika DB Anda simpan UTC, konversi ke UTC:
        // $startDb = $startJkt->copy()->setTimezone('UTC');
        // $endDb   = $endJkt->copy()->setTimezone('UTC');
        // Kalau DB sudah Asia/Jakarta, cukup pakai $startJkt/$endJkt langsung.
        $startDb = $startJkt;
        $endDb   = $endJkt;

        $q = \App\Models\ReservasiOnline::query()
            ->where('schedulled_booking', 1)           // hanya booking terjadwal (bukan waitlist/now)
            ->where('staf_id', $pp->staf_id)
            ->where('ruangan_id', $pp->ruangan_id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when($tipe_konsultasi_id, fn($q) => $q->where('tipe_konsultasi_id', $tipe_konsultasi_id));

        // Hitung di dalam window booking shift ini; jika start == end, jatuhkan ke filter tanggal harian
        if ($startDb->lt($endDb)) {
            $q->whereBetween('created_at', [$startDb->toDateTimeString(), $endDb->toDateTimeString()]);
        } else {
            // fallback: amankan minimal per tanggal shift
            $q->whereDate('created_at', $pp->tanggal);
        }

        $count = $q->count();
        return $count >= $max;
    }

    /** Pesan kuota penuh (per petugas) + tawarkan waitlist */
    protected function pesanKuotaPenuhPerPetugasDenganWaitlist($pp): string
    {
        $mulai = substr((string)$pp->jam_mulai, 0, 5);
        $akhir = substr((string)$pp->jam_akhir, 0, 5);
        $kuota = (int)($pp->max_booking ?? 0);

        return
            "Maaf, kuota *booking terjadwal* untuk jadwal ini sudah *penuh*.\n".
            "• Jam pelayanan: *{$mulai}–{$akhir}*\n".
            /* "• Kuota terjadwal: *".($kuota > 0 ? $kuota : 'tanpa batas')."*\n\n". */
            "Apakah Kakak mau masuk *waitlist* (daftar tunggu)?\n".
            "Balas *ya* untuk masuk\n";
            "Balas *batalkan* untuk membatalkan reservasi";
    }

    /** Prompt ulang jika user salah input di state waitlist */
    protected function tanyaGabungWaitlist(): string
    {
        return "Kuota booking terjadwal sudah *penuh*.\nBalas *1/ya* untuk masuk *waitlist*, atau *2/tidak*.";
    }

    protected function pesanWaitlistTercatat($reservasi_online): string
    {
        return "Siap, Kakak sudah kami masukkan ke *waitlist* untuk jadwal ini. Jika ada slot batal, kami hubungi secara *berurutan*. 🙏. Kode waitlist : " . $reservasi_online->id;
    }

    protected function pesanTolakWaitlistTawarkanDaftarSekarang(): string
    {
        return "Baik, tidak masuk waitlist. Kakak kami persilahkan untuk mendaftar kembali di hari lain. untuk informasi detil silahkan ketik 'Jadwal Dokter Gigi'. Mohon maaf atas ketidaknyamanannya.";
    }
    public function rekonfirmationWaitlistReservation(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(15);
    }

    public function waitlistReservationConfirmation()
    {
        $tz  = 'Asia/Jakarta';
        $now = \Carbon\Carbon::now($tz);

        // --- Normalisasi pesan & token matching yang lebih longgar ---
        $raw = is_string($this->message) ? mb_strtolower(trim($this->message)) : '';
        // hapus tanda baca agar "ya, dok" tetap match
        $msg = preg_replace('/[^\pL\pN\s]+/u', '', $raw);

        $yesTokens = ['ya','y','ok','oke','iya','siap','setuju','yes','yaa','ambil','ready','lanjut'];
        $noTokens  = ['tidak','ga','nggak','gak','no','batal','batalkan','t','skip','n'];

        $matchesAny = function (string $text, array $tokens): bool {
            foreach ($tokens as $t) {
                // cocokkan sebagai kata utuh agar "t" tidak nyangkut di "tadi"
                $pattern = '/\b' . preg_quote($t, '/') . '\b/u';
                if (preg_match($pattern, $text)) return true;
            }
            return false;
        };

        $startToday = $now->copy()->startOfDay();
        $endToday   = $now->copy()->endOfDay();

        // Helper: ambil waitlist kandidat hari ini untuk nomor ini
        $getTodayWaitlist = function () use ($startToday, $endToday) {
            // Jika ada kolom sent_at gunakan itu; kalau belum, fallback ke updated_at
            $query = \App\Models\SchedulledReservation::query()
                        ->with(['staf','petugas_pemeriksa'])
                        ->where('waitlist_flag', 1)
                        ->where('waitlist_reservation_inquiry_sent', 1)
                        ->where('no_telp', $this->no_telp)
                        ->lockForUpdate()
                        ->whereBetween('created_at', [$startToday, $endToday])
                        ->orderByDesc('created_at');

            return $query->first();
        };

        // ====== KONFIRMASI "YA" ======
        if ($matchesAny($msg, $yesTokens)) {
            try {
                \DB::beginTransaction();

                $waitlist = $getTodayWaitlist();

                if (!$waitlist) {
                    \DB::rollBack();
                    $this->autoReply("Data waitlist tidak ditemukan atau sudah tidak berlaku.");
                    return;
                }

                // Pastikan relasi ada dan cek kuota
                $pp = $waitlist->petugas_pemeriksa ?? null;
                if (!$pp->slot_pendaftaran_available) {
                    // JANGAN rollback dulu, kita mau update flag agar tidak diproses ulang
                    $waitlist->waitlist_reservation_inquiry_sent = 0;
                    $waitlist->save();

                    \DB::commit();
                    $this->autoReply(
                        "Mohon maaf, kuota untuk saat ini *penuh*.\n".
                        "Kakak tetap kami simpan di *waitlist*. Jika ada pembukaan slot, kami akan menghubungi kembali."
                    );
                    \App\Models\WhatsappBot::where('no_telp', $this->no_telp)->delete();
                    return;
                }

                // Konversi ke booking aktif (idempoten)
                $waitlist->waitlist_flag                     = 0;
                $waitlist->schedulled_booking                = 1;
                $waitlist->reservasi_selesai                 = 1; // tetap aktif sampai visit selesai
                $waitlist->waitlist_reservation_inquiry_sent = 0; // agar tidak diproses ulang

                // Generate QR & simpan expiry
                $waitlist->qrcode        = $this->generateQrCodeForOnlineReservation('B', $waitlist);
                $waitlist->save();


                \DB::commit();

                // Kirim balasan
                $stafNama = optional($waitlist->staf)->nama_dengan_gelar
                            ?? (optional($waitlist->staf)->nama ?? 'dokter');

                $batasScan = Carbon::parse( $waitlist->petugas_pemeriksa->jam_mulai )->subMinutes(15)->format('H:i');
                // Pastikan rute ada; kalau tidak ada, fallback ke placeholder


                $qrLink = 'https://www.klinikjatielok.com/schedulled_booking/qr/' . encrypt_string( $waitlist->id );


                $this->autoReply(
                    "Halo {$waitlist->nama},\n\n".
                    "Konfirmasi *waitlist* Kakak berhasil ✅\n\n".
                    "Reservasi untuk *{$stafNama}* sudah didaftarkan.\n\n".
                    "Jangan lupa *scan QR Code* di klinik sebelum pukul *{$batasScan}* (15 menit sebelum jadwal praktik dimulai).\n\n".
                    "Akses QR Code (simpan nomor WA ini agar tautan bisa diklik):\n{$qrLink}\n\n".
                    "Terima kasih 🙏"
                );
                WhatsappBot::where('no_telp', $this->no_telp )->delete();
                return;

            } catch (\Throwable $e) {
                \DB::rollBack();

                // simpan ke laravel.log
                \Log::error("Waitlist Confirmation gagal", [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                ]);
                $this->autoReply("Terjadi kendala saat konfirmasi. Coba beberapa saat lagi ya, Kak.");
                return;
            }
        }
        // ====== TOLAK / BATAL ======
        if ($matchesAny($msg, $noTokens)) {
            try {
                // Kalau ingin aman, batasi ke hari ini juga
                \DB::transaction(function () use ($startToday, $endToday) {
                    \App\Models\SchedulledReservation::query()
                        ->where('no_telp', $this->no_telp)
                        ->whereDate('created_at', $now)
                        ->delete();
                });
            } catch (\Throwable $e) { /* ignore */ }

            if (function_exists('resetWhatsappRegistration')) {
                resetWhatsappRegistration($this->no_telp);
            }
            $this->autoReply("Baik, kami batalkan permintaan waitlist Kakak. Terima kasih.");
            return;
        }

        // ====== INPUT TIDAK DIPAHAMI ======
        $this->autoReply("Balasan kurang jelas. Ketik *YA* untuk konfirmasi waitlist, atau *TIDAK* untuk batal.");
    }

    public function errorValidasiSchedulledBooking(){
        $nowJkt  = Carbon::now('Asia/Jakarta');
        return PetugasPemeriksa::where('tipe_konsultasi_id', 2) // dokter gigi
            ->whereDate('tanggal', $nowJkt) // hari ini
            ->where('schedulled_booking_allowed', 1) // masih ada
            ->exists();
    }
    public function pesanAntrolDokterGigiNonAktif(){
        $message = 'Pelayanan Antrian Online Dokter Gigi saat ini dalam maintenance';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Mohon maaf atas ketidaknyamanannya';
        return $message;
    }
    public function reservasiDokterGigiSudahSelesai(){
        $message = 'Reservasi dokter gigi hari ini sudah berakhir';
        $message .= PHP_EOL;
        $message .= 'untuk melihat jadwal di hari lain ketik "Jadwal Dokter Gigi "';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Mohon maaf atas ketidaknyamanannya";
        return $message;
    }
    public function pelayananPoliGigiLibur(){
        $message  = 'Hari ini pelayanan poli gigi libur' . PHP_EOL . PHP_EOL;
        $message .= 'Silakan mendaftar kembali saat Poli Gigi tersedia' . PHP_EOL;
        $message .= "Untuk informasi tersebut silakan ketik 'Jadwal Dokter Gigi'" . PHP_EOL . PHP_EOL;
        $message .= 'Mohon maaf atas ketidaknyamanannya';
        return $message;
    }
    public function konfirmasiPilihanPenghapusan()
    {
        $tz  = 'Asia/Jakarta';
        $raw = is_string($this->message) ? mb_strtolower(trim($this->message)) : '';

        // dukung "semua"/"all" atau angka (0 = semua)
        if (preg_match('/\b(semua|all)\b/u', $raw)) {
            $choice = 0;
        } elseif (preg_match('/\b(\d+)\b/u', $raw, $m)) {
            $choice = (int) $m[1];
        } else {
            return $this->autoReply("Format tidak dikenali. Balas dengan angka (misal: 2) atau 0/‘semua’ untuk membatalkan semua.");
        }

        // Ambil daftar yang sama urutnya dengan daftar yang pernah kamu kirim (urutkan by created_at ASC)
        $items = $this->getReservasiOnlineBelumHadirHariIni();
        if (!$items instanceof \Illuminate\Support\Collection) {
            $items = collect($items);
        }
        $items = $items->sortBy(function ($it) {
            return optional($it->created_at)->getTimestamp() ?? 0;
        })->values();

        $count = $items->count();
        if ($count === 0) {
            resetWhatsappRegistration($this->no_telp);
            return $this->autoReply("Tidak ada antrean/reservasi yang bisa dibatalkan. Silakan kirim perintah pembatalan lagi untuk memuat daftar terbaru.");
        }

        // helper: cek kolom ada lalu set dibatalkan_pasien=1
        $flagBatal = function ($model) {
            // gunakan array_key_exists agar nilai NULL tetap terdeteksi sebagai ada
            if (method_exists($model, 'getAttributes') && array_key_exists('dibatalkan_pasien', $model->getAttributes())) {
                $model->dibatalkan_pasien = 1;
                $model->save();
            }
        };

        // helper: deskripsikan item untuk pesan WA (sebelum dihapus)
        $describe = function ($it) use ($tz) {
            $tipe  = $it instanceof \App\Models\Antrian ? 'Antrean'
                  : ($it instanceof \App\Models\SchedulledReservation ? 'Reservasi Terjadwal'
                  : ($it instanceof \App\Models\ReservasiOnline ? 'Reservasi Online' : 'Item'));

            // pastikan relasi siap dipakai tanpa N+1 berlebihan
            if (method_exists($it, 'loadMissing')) {
                $it->loadMissing('staf.titel', 'antriable');
            }

            $jam   = optional($it->created_at)->setTimezone($tz)->format('H:i');
            $nama  = $it->nama ?? optional($it->antriable)->nama ?? 'pasien';
            $dokter = optional($it->staf)->nama_dengan_gelar ?? optional($it->staf)->nama ?? '-';

            return [$tipe, $nama, $dokter, $jam];
        };

        // === 0: Hapus semua ===
        if ($choice === 0) {
            $ringkasan = [];

            \DB::transaction(function () use ($items, $flagBatal, $describe, &$ringkasan, $tz) {
                $today = \Carbon\Carbon::now($tz)->toDateString();

                foreach ($items as $it) {
                    [$tipe, $nama, $dokter, $jam] = $describe($it);
                    $ringkasan[] = "- [$tipe] $nama ke $dokter (dibuat $jam WIB)";

                    if ($it instanceof \App\Models\Antrian) {
                        $flagBatal($it);
                        // kalau ada sumber reservasi, hapus juga
                        if ($it->antriable) {
                            $it->antriable->delete();
                        }
                        $it->delete();

                    } elseif ($it instanceof \App\Models\ReservasiOnline) {
                        // hapus turunannya (antrean) yang belum hadir
                        \App\Models\Antrian::where('antriable_type', \App\Models\ReservasiOnline::class)
                            ->where('antriable_id', $it->id)
                            ->where('sudah_hadir_di_klinik', 0)
                            ->get()
                            ->each(function ($a) use ($flagBatal) {
                                $flagBatal($a);
                                $a->delete();
                            });
                        $it->delete();

                    } elseif ($it instanceof \App\Models\SchedulledReservation) {
                        // jika kamu punya relasi turunannya, hapus di sini juga (opsional)
                        $flagBatal($it);
                        $it->delete();

                    } else {
                        // fallback aman
                        $it->delete();
                    }
                }
            });

            resetWhatsappRegistration($this->no_telp);
            return $this->autoReply(
                "Semua antrean/reservasi berikut telah dibatalkan:\n" .
                implode("\n", $ringkasan) .
                "\n\nSemua fitur WhatsApp telah di-reset."
            );
        }

        // === 1..N: Hapus sesuai pilihan ===
        if ($choice < 1 || $choice > $count) {
            return $this->autoReply("Nomor pilihan tidak valid. Balas dengan angka antara 1 dan {$count}, atau 0 untuk semua.");
        }

        $target = $items->get($choice - 1);
        if (!$target) {
            return $this->autoReply("Pilihan tidak ditemukan. Coba kirim perintah pembatalan lagi untuk memuat daftar terbaru.");
        }

        // ambil info SEBELUM delete
        [$tipe, $nm, $dokter, $jam] = $describe($target);

        \DB::transaction(function () use ($target, $flagBatal, $tz) {
            if ($target instanceof \App\Models\Antrian) {
                $flagBatal($target);
                if ($target->antriable) {
                    $target->antriable->delete();
                }
                $target->delete();

            } elseif ($target instanceof \App\Models\ReservasiOnline) {
                \App\Models\Antrian::where('antriable_type', \App\Models\ReservasiOnline::class)
                    ->where('antriable_id', $target->id)
                    ->where('sudah_hadir_di_klinik', 0)
                    ->get()
                    ->each(function ($a) use ($flagBatal) {
                        $flagBatal($a);
                        $a->delete();
                    });
                $target->delete();

            } elseif ($target instanceof \App\Models\SchedulledReservation) {
                $flagBatal($target);
                $target->delete();

            } else {
                $target->delete();
            }
        });

        resetWhatsappRegistration($this->no_telp);

        return $this->autoReply(
            "Reservasi/antrean atas nama {$nm} ke {$dokter} (dibuat sekitar {$jam} WIB) telah dibatalkan.\n" .
            "Semua fitur WhatsApp telah di-reset. Jika diperlukan, Anda bisa melakukan pendaftaran kembali."
        );
    }

    public function pertanyaanKonfirmasiPilihanPenghapusan(){
        return $this->cekListPhoneNumberRegisteredForWhatsappBotService(16);
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function balasanReservasiTerjadwalDibuat($reservasi_online)
    {
        $message = "Booking terjadwal sudah tercatat.\nSilakan gunakan QR saat datang.";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Klik link berikut untuk melihat qr code :';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'https://www.klinikjatielok.com/schedulled_booking/qr/' . encrypt_string( $reservasi_online->id );
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Anda harus menyimpan nomor whatsapp ini agar dapat mengaktifkan link diatas';
        $message .= PHP_EOL;
        $message .= $this->batalkan();
        $message .= PHP_EOL;
        $message .= 'Ketik *daftar* untuk mendaftarkan pasien berikutnya';
        WhatsappBot::where('no_telp', $this->no_telp)->delete();
        return $message;
    }

}
