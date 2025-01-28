<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(){
        $url_register  = base64_encode(  'fingerprint/register?user_id=1' );
        return view('home.index', [
         'url_register' => $url_register
        ]);
    }
}
