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
        Log::info('apaain sih ini');
        Log::info( Input::all());
        Log::info('id');
    }
    
}
