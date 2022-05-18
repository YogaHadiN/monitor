<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PasienController extends Controller
{
    //
    public function eksklusi($encrypted_id){
        $id     = $this->decrypt_string($encrypted_id);
        $pasien = Pasien::where('id', $id)->first();
        dd( $id, $encrypted_id );
        $pasien->jangan_disms = 1;
        $pasien->save();
        return view('pasien.eksklusi', compact(
            'pasien'
        ));
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
