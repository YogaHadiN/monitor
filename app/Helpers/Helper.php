<?php
use Log;

if (!function_exists('flex_url')) {

    /**
     * description
     *
     * @param
     * @return
     */
    function flex_url($url)
    {
        if (request()->secure()) {
            secure_url($url);
        } else {
            return url($url);
        }

    }
}

if (!function_exists('resetWhatsappRegistration')) {
     function resetWhatsappRegistration($no_telp) {
        \App\Models\WhatsappComplaint::where('no_telp', $no_telp)->delete();
        \App\Models\WhatsappRecoveryIndex::where('no_telp', $no_telp)->delete();
        \App\Models\WhatsappMainMenu::where('no_telp', $no_telp)->delete();
        \App\Models\WhatsappBot::where('no_telp', $no_telp)->delete();
        \App\Models\WhatsappSatisfactionSurvey::where('no_telp', $no_telp)->delete();
        \App\Models\FailedTherapy::where('no_telp', $no_telp)->delete();
        \App\Models\KuesionerMenungguObat::where('no_telp', $no_telp)->delete();
        \App\Models\WhatsappBpjsDentistRegistration::where('no_telp', $no_telp)->delete();
        \App\Models\WhatsappJadwalKonsultasiInquiry::where('no_telp', $no_telp)->delete();
    }
}

if (!function_exists('validateName')) {
     function validateName($value) {
        return ctype_alpha(str_replace(' ', '', $value));
    }
}

if (!function_exists('pesanErrorValidateNomorAsuransiBpjs')) {
     function pesanErrorValidateNomorAsuransiBpjs($value) {
         $pesan = '';
         if (!is_numeric($value)) {
             $pesan .= '_Nomor BPJS harus semuanya angka_';
         }
         if (!is_numeric($value) && !strlen($value) == 13 ) {
             $pesan .= PHP_EOL;
         }
         Log::info('strlen($value)');
         Log::info(strlen($value));
         Log::info('strlen($value) == 13');
         Log::info(strlen($value) == '13');
         if (!strlen($value) == 13) {
             $pesan .= '_Nomor BPJS harus terdiri dari 13 angka_';
         }
         return $pesan;
    }
}

