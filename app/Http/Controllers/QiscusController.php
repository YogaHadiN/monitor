<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;
use Input;

class QiscusController extends Controller
{
    public function webhookGet(){
        Log::info("===================="); 
        Log::info("QISCUS"); 
        Log::info(Input::all()); 
        Log::info("===================="); 
    }
    public function webhook(){
        Log::info("===================="); 
        Log::info("QISCUS"); 
        Log::info(Input::all()); 
        Log::info("===================="); 
    }
    
}
