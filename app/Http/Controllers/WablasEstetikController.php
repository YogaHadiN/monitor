<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;

class WablasEstetikController extends Controller
{
    public function webhook(){
        $message = 'Terima kasih telah menghubungi Klinik Jati Elok';
        $message .= PHP_EOL;
        $message .= 'Saat ini nomor Wa Customer Service kami telah berubah';
        $message .= PHP_EOL;
        $message .= 'Untuk menghubungi kami silahkan whatsapp ke nomor';
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
