<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;

class WablasWebhookController extends Controller
{
    public function wablas(Request $request)
    {
        // Wablas mengirim phone/message/messageType langsung di body — sudah dibaca
        // otomatis oleh WablasController::__construct() lewat Input::get(...).
        $wablas = new WablasController;

        // Pastikan provider tetap 'wablas' (constructor pakai Input::get('provider', 'wablas'),
        // jadi kalau Wablas tidak kirim field 'provider' tetap aman; ini cuma penegasan).
        $wablas->provider = 'wablas';
        $wablas->origin   = 'wablas';
        $wablas->fonnte   = false;

        try {
            $wablas->webhook();
        } catch (\Throwable $e) {
            Log::error('WABLAS_WEBHOOK_EXCEPTION', [
                'phone'   => $request->input('phone'),
                'message' => $request->input('message'),
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            return response()->json(['ok' => false], 500);
        }

        return response()->json(['ok' => true]);
    }
}
