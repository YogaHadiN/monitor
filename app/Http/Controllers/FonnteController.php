<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;

class FonnteController extends Controller
{
    public function getWebhook(){
        Log::info('fonnte');
        Log::info(10);
    }
    public function postWebhook(){
        Log::info('fonnte');
        Log::info(13);
    }
    public function getChaning(){
        Log::info('fonnte');
        Log::info(17);
    }
    public function postChaning(){
        Log::info('fonnte');
        Log::info(20);
    }
    public function getStatus(){
        Log::info('fonnte');
        Log::info(23);
    }
    public function postStatus(){
        Log::info('fonnte');
        Log::info(26);
    }
}
