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
            echo $this->wb->konsultasiEstetikOnlineStart();
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
}
