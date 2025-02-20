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
        Log::info('Post Request Botcake Berhasil');
        Log::info( Input::get('entry') );
    }
    
    
    
}
