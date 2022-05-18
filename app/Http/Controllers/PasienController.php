<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pasien;

class PasienController extends Controller
{
    //
    public function eksklusi($encrypted_id){
        $id     = $this->decrypt_string($encrypted_id);
        $pasien = Pasien::where('id', $id)->first();
        $pasien->jangan_disms = 1;
        if ($pasien->save()) {
            return view('pasien.eksklusi', compact(
                'pasien'
            ));
        } else {
            return view('pasien.notFoundId');
        }
    }

    /**
     * undocumented function
     *
     * @return void
     */
    private function decrypt_string($encryption)
    {
        // Non-NULL Initialization Vector for decryption
        $decryption_iv = '1234567891011121';
          
        $options = 0;
        $ciphering = "AES-128-CTR";
        // Store the decryption key
        $decryption_key = "GeeksforGeeks";
          
        // Use openssl_decrypt() function to decrypt the data
        $decryption=openssl_decrypt ($encryption, $ciphering, 
                $decryption_key, $options, $decryption_iv);
        // return decrypt
        return $decryption;
    }
}
