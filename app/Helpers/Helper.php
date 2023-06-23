<?php

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
         if (!is_numeric($value) && !( strlen($value) == 13 ) ) {
             $pesan .= PHP_EOL;
         }
         if (!(strlen($value) == 13)) {
             $pesan .= '_Nomor BPJS harus terdiri dari 13 angka_';
         }
         return $pesan;
    }
}

if (!function_exists('datePrep')) {
     function datePrep($tanggal) {
        if ($tanggal == null) {
            return null;
        } else {
            $date = substr($tanggal, 6, 4) . "-" . substr($tanggal, 3, 2). "-" . substr($tanggal, 0, 2);
            return $date;
        }
    }
}

if (!function_exists('gagalFlash')) {
     function gagalFlash($text) {
        $temp = '<div class="text-left alert alert-danger">';
        $temp .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>GAGAL !! </strong>';
        $temp .= $text;
        $temp .= '</div>';
        return $temp;
    }
}


if (!function_exists('getFirstWord')) {
     function getFirstWord($text) {
        $arr = explode(' ',trim($text));
        return $arr[0]; // will print Test
    }
}

