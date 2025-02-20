<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Input;
use Log;

class BotCakeController extends Controller
{
    /**
     * @param 
     */
    public function __construct()
    {
    }

    public function webhook(){
        Log::info('Input Botcake Berhasil');
        Log::info( Input::all() );
    }

    public function webhookPost(){
        Log::info(Input::all());
        $messages = Input::get('entry')['changes'][0]['value']['messages'][0];
        $no_telp = $messages['from'];
        $message = $messages['text']['body'];
        Log::info($message);
    }
    
    
    
}
