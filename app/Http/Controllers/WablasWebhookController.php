<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Input;
use Log;

class WablasWebhookController extends Controller
{
    public function wablas(){
        Log::info('===============================');
        Log::info('NO TELPPP');
        Log::info( Input::all() );
        Log::info('===============================');
    }
}
