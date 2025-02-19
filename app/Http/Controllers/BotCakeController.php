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
        Log::info( Input::all() );
    }
    
}
