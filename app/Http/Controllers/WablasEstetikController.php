<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;
use Input;
use App\Http\Controllers\WablasController;
use App\Models\WhatsappBot;
use App\Models\KonsultasiEstetikOnline;

class WablasEstetikController extends Controller
{
    /**
     * @param 
     */
    public $message;
    public $wb;
    public $whatsapp_bot;
    public function __construct()
    {
        $this->message = clean(Input::get('message'));
        $this->no_telp = Input::get('phone');
        $this->wb = new WablasController;
        $this->whatsapp_bot = WhatsappBot::where('no_telp', $this->no_telp)->first();
    }
    
    public function webhook(){

        if ( $this->message == 'estet' ) {
            echo $this->konsultasiEstetikOnlineStart();
        } elseif ( $this->wb->whatsappKonsultasiEstetikExists() ) {
            echo $this->wb->prosesKonsultasiEstetik();
        } else {
            $message = 'Terima kasih telah menghubungi Klinik Jati Elok';
            $message .= PHP_EOL;
            $message .= 'Saat ini nomor Wa Customer Service kami telah berubah';
            $message .= PHP_EOL;
            $message .= 'Untuk menghubungi kami silahkan whatsapp kami ke nomor';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= '082113781271';
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= 'Pesan anda sangat penting bagi kami';
            $message .= PHP_EOL;
            $message .= 'Dengan senang hati kami akan membantu kakak melalui nomor tersebut';
            $message .= PHP_EOL;
            $message .= 'Hormat kami, Tim Pelayanan CS Klinik Jati Elok';
            echo $message;
        }
    }
    public function konsultasiEstetikOnlineStart(){
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
        $message .= $this->wb->batalkan();
        return $message;
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
            echo 'Reservasi dibatalkan';
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
                $pasien = Pasien::find( $data[0]->pasien_id );
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
}
