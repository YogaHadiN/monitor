<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PasienController extends Controller
{
    //
    function eksklusi($id){
        $id     = decrypt_string($id);
        $pasien = Pasien::where('id', $id)->first();
        $pasien->jangan_disms = 1;
        $pasien->save();
        return view('pasien.eksklusi', compact(
            'pasien'
        ));
    }
}
