<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;

class QiscusController extends Controller
{
    public function webhookGet(){
        Log::info(Input::all()); 
    }
    public function webhook(){
        
        Log::info(Input::all()); 
    }
    
}
