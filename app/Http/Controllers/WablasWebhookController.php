<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NoTelp;
use Input;
use Log;

class WablasWebhookController extends Controller
{
    public $no_telp;

    public function __construct()
    {
    }

    public function wablas()
    {
        Log::info("=========================================================");
        Log::info("phone");
        Log::info( Input::get('phone') );
        Log::info("sender");
        Log::info( Input::get('sender') );
        Log::info("message");
        Log::info( Input::get('message') );
        Log::info("=========================================================");
    }
}
