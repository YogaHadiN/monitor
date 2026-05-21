<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Classes\Yoga;

/**
 * Gate jam buka untuk flow pendaftaran online via website
 * (/daftar_online dan /daftar_online_by_phone/*). Pendaftaran
 * hanya diterima jam 07:00-21:59 WIB — sama dengan gate bot
 * WhatsApp di WablasController::registrasiAntrianOnline.
 *
 * Nomor di config('clinic.after_hours_whitelist_phones') boleh
 * lewat di luar jam (untuk QA). Phone diresolusi dari:
 *   - request input 'no_telp' (POST endpoints), atau
 *   - route param {no_telp} yang ter-encrypt (GET landing).
 */
class WebRegistrationWithinOpeningHours
{
    public function handle(Request $request, Closure $next)
    {
        $hour = (int) date('G');
        if ($hour >= 7 && $hour <= 21) {
            return $next($request);
        }

        $whitelist = (array) config('clinic.after_hours_whitelist_phones', []);
        $phone     = $this->resolvePhone($request);
        if ($phone !== null && in_array($phone, $whitelist, true)) {
            return $next($request);
        }

        $message  = 'Pendaftaran secara online sudah ditutup dan akan dibuka kembali jam 7 pagi.';
        $message .= PHP_EOL;
        $message .= 'Pendaftaran secara langsung terakhir jam 22.30';
        $message .= PHP_EOL;
        $message .= 'Pelayanan berakhir jam 23:00.';
        $message .= PHP_EOL;
        $message .= 'Untuk menghubungi silahkan telpon ke 0215977529';
        $message .= PHP_EOL . PHP_EOL;
        $message .= 'Mohon maaf atas ketidaknyamanannya';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status'  => false,
                'message' => $message,
            ], 423);
        }

        return redirect('/')->withPesan(Yoga::gagalFlash(nl2br(e($message))));
    }

    private function resolvePhone(Request $request): ?string
    {
        $input = $request->input('no_telp');
        if (!empty($input)) {
            return (string) $input;
        }

        $param = $request->route('no_telp');
        if (!empty($param) && function_exists('decrypt_string')) {
            try {
                return (string) decrypt_string($param);
            } catch (\Throwable $e) {
                // not a valid encrypted phone — fall through
            }
        }

        return null;
    }
}
