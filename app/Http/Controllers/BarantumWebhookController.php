<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BarantumWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $raw = $request->all();

        // Barantum webhook datang sebagai ARRAY [ { ... } ]
        $payload = is_array($raw) && isset($raw[0]) ? $raw[0] : $raw;

        /* =========================
         * EXTRACT SEMUA FIELD
         * ========================= */

        $data = [
            // kontak
            'contacts_uuid' => data_get($payload, 'contacts_uuid'),
            'contacts_name' => data_get($payload, 'contacts_name'),

            // room
            'room_id'            => data_get($payload, 'room_id'),
            'room_status'        => data_get($payload, 'room_status'),
            'room_date_created'  => data_get($payload, 'room_date_created'),

            // company (WEBHOOK UUID, bukan company key)
            'company_uuid_hook'  => data_get($payload, 'company_uuid'),

            // message
            'chats_users_id' => data_get($payload, 'message_users_id'),
            'message_id'     => data_get($payload, 'message_id'),
            'message_text'   => data_get($payload, 'message_text'),
            'message_type'   => data_get($payload, 'type_file'),

            // file
            'file_name'      => data_get($payload, 'file_name'),
            'file_url'       => data_get($payload, 'file_url'),
            'file_mime_type' => data_get($payload, 'file_mime_type'),

            // meta
            'owner'      => data_get($payload, 'owner'),
            'name_owner' => data_get($payload, 'name_owner'),

            // routing
            'channel' => data_get($payload, 'channel'),
            'bot_id'  => data_get($payload, 'bot_id'),
        ];


        $wablas               = new WablasController;
        $wablas->room_id      = data_get($payload, 'room_id');
        $wablas->no_telp      = data_get($payload, 'message_users_id');
        $wablas->message_type = data_get($payload, 'type_file');
        $wablas->image_url    = data_get($payload, 'file_url');
        $wablas->message      = data_get($payload, 'message_text');
        $wablas->fonnte       = false;
        /* $wablas->webhook(); */

        Log::info([
            $wablas->room_id,
            $wablas->no_telp,
            $wablas->message_type,
            $wablas->image_url,
            $wablas->message,
        ]);

        /* Log::info('BARANTUM_WEBHOOK_EXTRACTED', $data); */
        /* Log::info('BARANTUM_WABLAS', $wablas); */

        return response()->json(['ok' => true]);
    }
}
