<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KommoWebhookController extends Controller
{
    public function handle(Request $request)
    {
        return 1;
        // Raw body (penting untuk debug form-urlencoded)
        Log::info('KOMMO_WEBHOOK_RAW', [
            'content_type' => $request->header('Content-Type'),
            'ip'           => $request->ip(),
            'raw'          => $request->getContent(),
        ]);

        // Parsed payload (Laravel akan parse form-urlencoded ke array)
        Log::info('KOMMO_WEBHOOK_PARSED', $request->all());

        // Kommo menganggap sukses kalau 100–299 :contentReference[oaicite:4]{index=4}
        return response()->json(['ok' => true], 200);
    }
}
