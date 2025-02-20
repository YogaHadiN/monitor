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
        $messages = Input::get('entry')['changes'][0]['value']['messages'][0];
        Log::info($messages);
    }
    
    
    
}
