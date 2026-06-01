<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifikasi HMAC-SHA256 untuk webhook mutasi bank dari Lambda
 * (AWS SES inbound). Lambda menandatangani RAW request body dengan
 * shared secret dan mengirim hasilnya pada header
 * `X-Signature: sha256=<hex>`. Middleware ini menghitung ulang HMAC
 * dari body yang sama dan membandingkan dengan hash_equals
 * (timing-safe) sebelum request mencapai controller.
 *
 * Wajib dibaca via $request->getContent() — bila body sudah di-parse
 * (mis. via $request->all()) signature bisa mismatch karena
 * re-serialisasi tidak menjamin byte-identik.
 */
class VerifyMutasiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('mutasi.webhook_secret');
        if ($secret === '') {
            Log::error('VerifyMutasiSignature: secret kosong di config(mutasi.webhook_secret)');
            return response()->json(['message' => 'Server misconfigured'], 500);
        }

        $sigHeader = (string) $request->header('X-Signature', '');
        if (!str_starts_with($sigHeader, 'sha256=')) {
            Log::warning('VerifyMutasiSignature: header X-Signature tidak ada / format salah', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $providedHex = substr($sigHeader, 7);

        // RAW body — JANGAN parse JSON dulu.
        $rawBody     = (string) $request->getContent();
        $expectedHex = hash_hmac('sha256', $rawBody, $secret);

        if (!hash_equals($expectedHex, $providedHex)) {
            Log::warning('VerifyMutasiSignature: signature mismatch', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
