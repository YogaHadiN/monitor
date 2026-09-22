<?php

if (!function_exists('waCutoffActive')) {
    /**
     * Returns true kalau sekarang sudah lewat cutoff WA. Setelah cutoff
     * ini, SEMUA sender WA (Watzap, Wablas, Fonnte, Gowa) tolak kirim.
     * Nomor yg punya telegram_chat_id tetap terima via Telegram bot
     * (ChannelDispatcher handle di layer atas).
     *
     * Default cutoff: 2026-09-30 22:00:00 WIB (per instruksi dr. Yoga
     * 2026-09-22). Override via env WHATSAPP_CUTOFF_AT.
     */
    function waCutoffActive(): bool {
        $cutoff = env('WHATSAPP_CUTOFF_AT', '2026-09-30 22:00:00');
        if (empty($cutoff)) return false;
        try {
            return \Carbon\Carbon::now('Asia/Jakarta')->gte(
                \Carbon\Carbon::parse($cutoff, 'Asia/Jakarta')
            );
        } catch (\Throwable $e) {
            return false;
        }
    }
}

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
        // Retry per-delete on deadlock. Webhook concurrency dari phone
        // sama sering trigger 2 request paralel yg sama-sama delete row
        // di reservasi_onlines / whatsapp_registrations → SQLSTATE 40001.
        // Retry cepat 3x (10ms→30ms→90ms) sebelum bubble exception.
        $models = [
            \App\Models\WhatsappComplaint::class,
            \App\Models\WhatsappRecoveryIndex::class,
            \App\Models\WhatsappRegistration::class,
            \App\Models\WhatsappMainMenu::class,
            \App\Models\ReservasiOnline::class,
            \App\Models\WhatsappBot::class,
            \App\Models\WhatsappSatisfactionSurvey::class,
            \App\Models\FailedTherapy::class,
            \App\Models\KuesionerMenungguObat::class,
            \App\Models\WhatsappBpjsDentistRegistration::class,
            \App\Models\WhatsappJadwalKonsultasiInquiry::class,
        ];
        foreach ($models as $model) {
            $attempt = 0;
            $delay   = 10000; // 10ms
            while (true) {
                try {
                    $model::where('no_telp', $no_telp)->delete();
                    break;
                } catch (\Illuminate\Database\QueryException $e) {
                    // 1213 = deadlock, 1205 = lock wait timeout.
                    $code = (int) ($e->errorInfo[1] ?? 0);
                    if (($code === 1213 || $code === 1205) && $attempt < 2) {
                        usleep($delay);
                        $delay *= 3;
                        $attempt++;
                        continue;
                    }
                    \Log::warning('resetWA_delete_failed', [
                        'model'    => $model,
                        'no_telp'  => $no_telp,
                        'sqlstate' => $e->errorInfo[0] ?? null,
                        'code'     => $code,
                    ]);
                    break;
                }
            }
        }
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
             return '_Nomor BPJS tidak boleh mengandung huruf, hanya angka_';
         }

         if (!(strlen($value) == 13)) {
             return '_Nomor BPJS harus terdiri dari 13 angka_';
         }

    }
}

if (!function_exists('convertToWablasFriendlyFormat')) {
     function convertToWablasFriendlyFormat($no_telp) {
         if (
             !empty($no_telp) &&
              $no_telp[0] == 0
         ) {
             $no_telp = '62' . substr($no_telp, 1);
         }
         return $no_telp;
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


if (!function_exists('hitungUsia')) {
     function hitungUsia($birthDate) {
        if(empty($birthDate) || $birthDate == '0000-00-00'){
            return ' -- ';
        } else {
            $today = date('Y-m-d');
            return date_diff(date_create($birthDate), date_create( $today ))->y;
        }
    }
}
if (!function_exists('updateChat')) {
    function updateChat() {
		event(new \App\Events\ChatUpdated('yoyoyo'));
    }
}

if (!function_exists('clean')) {
    function clean($param) {
		if (empty( trim($param) ) && trim($param) != '0') {
			return null;
		}

        if ( str_contains($param, "<~ ") ) {
            $param = explode( "<~ ", $param)[1];
        }
		return strtolower( trim($param) );
    }
}
if (!function_exists('diffInMinutes')) {
    function diffInMinutes($from_time, $to_time){
        $to_time = strtotime($to_time);
        $from_time = strtotime($from_time);
        return floor(abs($to_time - $from_time) / 60.2);
    }
}
if (!function_exists('tambahkanGelar')) {
     function tambahkanGelar($titel, $nama) {
        if (
            !str_contains( strtolower($nama), 'dr.' ) &&
            !str_contains( strtolower($nama), 'drg' ) &&
            !str_contains( strtolower($nama), 'dr ' )
        ) {
            return $titel.'. ' . $nama;
        } else {
            return $nama;
        }
    }
}
if (!function_exists('encrypt_string')) {
    function encrypt_string($simple_string){
        // Store the cipher method
        $ciphering = env('CIPHERING');

        // Use OpenSSl Encryption method
        $iv_length = openssl_cipher_iv_length($ciphering);
        $options = 0;

        // Non-NULL Initialization Vector for encryption
        $encryption_iv = env('ENCRYPTION_IV');

        // Store the encryption key
        $encryption_key = env('ENCRYPTION_KEY');

        // Use openssl_encrypt() function to encrypt the data
        $encryption = openssl_encrypt($simple_string, $ciphering,
                    $encryption_key, $options, $encryption_iv);
        // return encrypt
        return base64_encode($encryption) ;
    }
}

if (!function_exists('decrypt_string')) {
    function decrypt_string($encryption){
        $encryption = base64_decode( $encryption );

        // Non-NULL Initialization Vector for decryption
        $decryption_iv = env('ENCRYPTION_IV');

        $options = 0;
        $ciphering = "AES-128-CTR";
        // Store the decryption key
        $decryption_key = env('ENCRYPTION_KEY');

        // Use openssl_decrypt() function to decrypt the data
        $decryption=openssl_decrypt ($encryption, $ciphering,
                $decryption_key, $options, $decryption_iv);

        // return decrypt
        return $decryption;
    }
}
if (!function_exists('endsWith')) {
    function endsWith( $haystack, $needle ) {
        $length = strlen( $needle );
        if( !$length ) {
            return true;
        }
        return substr( $haystack, -$length ) === $needle;
    }
}
