<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * HMAC-SHA256 verify untuk endpoint internal dipanggil atika
 * (/api/internal/sunatbot-process). Re-use shared secret
 * PUSH_TRIGGER_SECRET — kedua app sudah punya nilai sama.
 */
class VerifySunatBotInternalSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('pwa.trigger_secret');
        if ($secret === '') {
            Log::error('VerifySunatBotInternalSignature: PUSH_TRIGGER_SECRET kosong');
            return response()->json(['message' => 'Server misconfigured'], 500);
        }

        $sigHeader = (string) $request->header('X-Signature', '');
        if (!str_starts_with($sigHeader, 'sha256=')) {
            Log::warning('VerifySunatBotInternalSignature: header X-Signature tidak ada / format salah', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $providedHex = substr($sigHeader, 7);
        $rawBody     = (string) $request->getContent();
        $expectedHex = hash_hmac('sha256', $rawBody, $secret);

        if (!hash_equals($expectedHex, $providedHex)) {
            Log::warning('VerifySunatBotInternalSignature: signature mismatch', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return $next($request);
    }
}
