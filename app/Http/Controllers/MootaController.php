<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;
use Input;

class MootaController extends Controller
{
    public function webhook(){
        Log::info('====================================');
        Log::info('Ada uang masuk nih');
        Log::info('====================================');
        Log::info('Waktu ' . date('Y-m-d H:i:s'));
        Log::info('====================================');
        Log::info('ditemukan berapa array?');
        Log::info( count( Input::all() ));
        Log::info('====================================');
        Log::info('Isi dari array tersebut adalah');
        Log::info( Input::all());
    }
}
