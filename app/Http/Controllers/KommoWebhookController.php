<?php

namespace App\Http\Controllers;

use App\Services\KommoClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KommoWebhookController extends Controller
{
    public function handle(Request $request, KommoClient $kommo): \Illuminate\Http\JsonResponse
    {
        // =========================
        // 1) LOG RAW
        // =========================
        $rawBody = (string) $request->getContent();

        Log::info('KOMMO_WEBHOOK_RAW', [
            'content_type' => $request->header('Content-Type'),
            'ip'           => $request->ip(),
            'raw'          => $rawBody,
        ]);

        // =========================
        // 2) PARSE PAYLOAD
        // =========================
        $payload = $this->parseKommoPayload($request, $rawBody);

        Log::info('KOMMO_WEBHOOK_PARSED', $payload);

        // =========================
        // 3) PROSES EVENT: message.add
        // =========================
        $adds = data_get($payload, 'message.add', []);
        if (!is_array($adds)) {
            $adds = [];
        }

        foreach ($adds as $msg) {
            if (!is_array($msg)) {
                continue;
            }

            $chatId    = (string) data_get($msg, 'chat_id', '');
            $talkId    = (string) data_get($msg, 'talk_id', '');
            $contactId = (string) data_get($msg, 'contact_id', '');
            $text      = (string) data_get($msg, 'text', '');
            $origin    = (string) data_get($msg, 'origin', '');
            $author    = (array)  data_get($msg, 'author', []);

            Log::info('KOMMO_INCOMING_MESSAGE', [
                'chat_id'     => $chatId,
                'talk_id'     => $talkId,
                'contact_id'  => $contactId,
                'text'        => $text,
                'origin'      => $origin,
                'author_name' => (string) data_get($author, 'name', ''),
                'author_type' => (string) data_get($author, 'type', ''),
            ]);

            // =========================
            // 4) AMBIL PHONE VIA API (BISA NULL)
            // =========================
            $phone = null;
            if ($contactId !== '') {
                try {
                    $phone = $kommo->getContactPrimaryPhone($contactId);
                } catch (\Throwable $e) {
                    Log::error('KOMMO_GET_CONTACT_PHONE_FAILED', [
                        'contact_id' => $contactId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            // =========================
            // 5) DISINI ANDA LANJUTKAN: SIMPAN KE DB / KIRIM KE WABLAS/FONNTE
            //    (Saya buat placeholder aman, tidak crash)
            // =========================
            try {
                $normalizedPhone = $phone ? $this->normalizePhone($phone) : null;

                // Contoh variabel yang dokter minta dulu:
                // $wablas->room_id      = null;
                // $wablas->no_telp      = $normalizedPhone;
                // $wablas->message_type = 'text';
                // $wablas->image_url    = null;
                // $wablas->message      = 'Halo dokter, kami menerima update dari Kommo.';
                // $wablas->fonnte       = true;
                // + simpan kommo_contact_id

                Log::info('KOMMO_READY_FOR_FORWARD', [
                    'contact_id'       => $contactId,
                    'phone_raw'        => $phone,
                    'phone_normalized' => $normalizedPhone,
                    'text'             => $text,
                ]);

            } catch (\Throwable $e) {
                // Penting: jangan bikin webhook gagal
                Log::error('KOMMO_PROCESSING_FAILED', [
                    'contact_id' => $contactId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // =========================
        // 6) SELALU BALAS 200 OK
        // =========================
        return response()->json(['ok' => true]);
    }

    /**
     * Kommo webhook sering mengirim x-www-form-urlencoded.
     * Laravel biasanya sudah parse ke $request->all(), tapi kita buat robust:
     */
    protected function parseKommoPayload(Request $request, string $rawBody): array
    {
        // Kalau Laravel sudah dapat array besar, pakai itu.
        $all = $request->all();
        if (is_array($all) && !empty($all)) {
            return $all;
        }

        // Fallback parse raw querystring
        $parsed = [];
        if ($rawBody !== '') {
            parse_str($rawBody, $parsed);
        }

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Normalisasi nomor HP Indonesia -> format E.164 (+62...)
     * Silakan sesuaikan dengan fungsi normalizePhone dokter yang sudah ada.
     */
    protected function normalizePhone(string $phone): string
    {
        $p = trim($phone);

        // Buang spasi, tanda -, ()
        $p = preg_replace('/[^0-9\+]/', '', $p) ?? $p;

        // 08xxx -> +628xxx
        if (preg_match('/^0[0-9]+$/', $p)) {
            $p = '+62' . substr($p, 1);
        }

        // 62xxx -> +62xxx
        if (preg_match('/^62[0-9]+$/', $p)) {
            $p = '+'.$p;
        }

        // Pastikan diawali +
        if ($p !== '' && $p[0] !== '+') {
            $p = '+' . $p;
        }

        return $p;
    }
}
