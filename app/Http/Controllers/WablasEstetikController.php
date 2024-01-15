<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;

class WablasEstetikController extends Controller
{
    public function webhook(){
        Log::info(
            'bonjourno'
        );
        echo 'oke';
    }
}
