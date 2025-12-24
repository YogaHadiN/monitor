<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BarantumWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();

        $usersId = (string) data_get($payload, 'message_users_id', '');
        $textIn  = (string) data_get($payload, 'message_text', '');
        $channel = (string) data_get($payload, 'channel', 'wa');
        $msgId   = (string) data_get($payload, 'message_id', '');


        Log::info('BARANTUM_WEBHOOK_PAYLOAD', [
            $usersId,
            $textIn,
            $channel,
            $msgId,
        ]);

        /* if ($usersId === '') { */
        /*     Log::warning('BARANTUM_WEBHOOK_MISSING_USERS_ID', ['payload' => $payload]); */
        /*     return response()->json(['ok' => false, 'error' => 'missing message_users_id'], 422); */
        /* } */

        /* // contoh reply */
        /* $replyText = "Halo 👋 Kami terima pesan: {$textIn}"; */

        /* $sendUrl    = config('services.barantum.send_url'); */
        /* $companyKey = config('services.barantum.company_key'); */

        /* // body minimal yang terbukti jalan di akun Anda */
        /* $body = [ */
        /*     'chats_users_id'     => $usersId, */
        /*     'channel'            => $channel, */
        /*     'chats_message_text' => $replyText, */
        /*     'company_uuid'       => $companyKey, */
        /* ]; */

        /* // OPTIONAL: reply ke message tertentu */
        /* if ($msgId !== '') { */
        /*     $body['context'] = ['message_id' => $msgId]; */
        /* } */

        /* // chats_bot_id optional: pilih salah satu style: */
        /* // (A) tidak dikirim sama sekali -> paling bersih */
        /* // (B) kirim string kosong -> kalau Anda ingin selalu ada fieldnya */
        /* // $body['chats_bot_id'] = ""; */

        /* $resp = Http::withHeaders([ */
        /*         'Content-Type' => 'application/json', */
        /*         'Accept'       => 'application/json', */
        /*     ]) */
        /*     ->post($sendUrl, $body); */

        /* Log::info('BARANTUM_SEND_MESSAGE', [ */
        /*     'request_body' => $body, */
        /*     'status'       => $resp->status(), */
        /*     'ok'           => $resp->ok(), */
        /*     'response_raw' => $resp->body(), */
        /* ]); */

        return response()->json(['ok' => true]);
    }
}
