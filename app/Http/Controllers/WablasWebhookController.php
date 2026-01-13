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
        Log::info( Input::all() );
        Log::info("DIA DATANG LAGI");
        Log::info("=========================================================");
    }
}
