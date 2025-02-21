<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Input;
use Log;
use Http;

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
        $no_telp = $messages['from'];
        $message_type = $messages['type'];
        if (
            $message_type == 'image'
        ) {
            $message = $messages['image'];
        } else if (
            $message_type == 'text'
        ) {
            $message = $messages['text']['body'];
        }

        Log::info([
            Input::all(),
            $message_type,
            $no_telp,
            $message
        ]);


    }
}
