<?php

namespace App\Http\Controllers;

use App\Services\KommoClient;
use App\Http\Controllers\WablasController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KommoWebhookController extends Controller
{
    public function handle(Request $request, KommoClient $kommo): JsonResponse
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
        // 3) PROSES EVENT: message.add[]
        // =========================
        $adds = data_get($payload, 'message.add', []);
        if (!is_array($adds)) {
            $adds = [];
        }

        foreach ($adds as $msg) {
            if (!is_array($msg)) {
                continue;
            }

            $this->processIncomingMessage($msg, $kommo);
        }

        // =========================
        // 4) SELALU BALAS 200 OK
        // =========================
        return response()->json(['ok' => true]);
    }

    /**
     * Proses 1 message.add item (text / image / unknown)
     */
    protected function processIncomingMessage(array $msg, KommoClient $kommo): void
    {
        $chatId    = (string) data_get($msg, 'chat_id', '');
        $talkId    = (string) data_get($msg, 'talk_id', '');
        $contactId = (string) data_get($msg, 'contact_id', '');
        $origin    = (string) data_get($msg, 'origin', '');
        $createdAt = (string) data_get($msg, 'created_at', '');
        $type      = (string) data_get($msg, 'type', ''); // incoming / outgoing
        $textRaw   = data_get($msg, 'text');              // bisa null
        $text      = is_string($textRaw) ? $textRaw : '';

        $author = (array) data_get($msg, 'author', []);
        $authorName = (string) data_get($author, 'name', '');
        $authorType = (string) data_get($author, 'type', '');

        $attachment = (array) data_get($msg, 'attachment', []);
        $attachmentType = (string) data_get($attachment, 'type', '');
        $attachmentLink = (string) data_get($attachment, 'link', '');
        $attachmentName = (string) data_get($attachment, 'file_name', '');

        $messageType = $this->kommoMessageType($msg);

        Log::info('KOMMO_INCOMING_MESSAGE', [
            'chat_id'          => $chatId,
            'talk_id'          => $talkId,
            'contact_id'       => $contactId,
            'origin'           => $origin,
            'type'             => $type,
            'created_at'       => $createdAt,
            'author_name'      => $authorName,
            'author_type'      => $authorType,
            'message_type'     => $messageType,
            'text'             => $text,
            'attachment_type'  => $attachmentType,
            'attachment_link'  => $attachmentLink,
            'attachment_name'  => $attachmentName,
        ]);

        // =========================
        // Ambil nomor HP dari Kommo contact (opsional)
        // =========================
        $phoneRaw = null;
        $phoneNormalized = null;

        if ($contactId !== '') {
            try {
                $phoneRaw = $kommo->getContactPrimaryPhone($contactId); // string|null
                if (is_string($phoneRaw) && $phoneRaw !== '') {
                    $phoneNormalized = $this->normalizePhone($phoneRaw);
                }
            } catch (\Throwable $e) {
                Log::error('KOMMO_GET_CONTACT_PHONE_FAILED', [
                    'contact_id' => $contactId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // =========================
        // Bentuk objek forward "wablas-like"
        // =========================
        $payload = (object) [
            'room_id'          => $chatId,
            'kommo_chat_id'    => $chatId,
            'talk_id'          => $talkId,
            'kommo_contact_id' => $contactId,

            'author_name'      => $authorName,
            'author_type'      => $authorType,
            'origin'           => $origin,

            'no_telp'          => $phoneNormalized,

            'message_type'     => $messageType,
            'message'          => $this->buildDefaultMessage($messageType, $text),
            'image_url'        => $messageType === 'image' ? $attachmentLink : null,
            'filename'         => $messageType === 'image' ? $attachmentName : null,

            'fonnte'           => false,

            'kommo_message_id' => (string) data_get($msg, 'id', ''),
            'created_at_unix'  => ctype_digit($createdAt) ? (int) $createdAt : null,
            'entity_type'      => (string) data_get($msg, 'entity_type', ''),
            'entity_id'        => (string) data_get($msg, 'entity_id', ''),
        ];

        Log::info('KOMMO_READY_FOR_FORWARD', [
            'contact_id'       => $contactId,
            'phone_raw'        => $phoneRaw,
            'phone_normalized' => $phoneNormalized,
            'message_type'     => $messageType,
            'text'             => $text,
            'image_url'        => $payload->image_url,
        ]);

        $wablasCtrl = new WablasController;

        $wablasCtrl->room_id       = null;
        $wablasCtrl->kommo_chat_id = $chatId;
        $wablasCtrl->no_telp       = $phoneNormalized;
        $wablasCtrl->message_type  = $messageType;
        $wablasCtrl->image_url     = $payload->image_url;  // ✅
        $wablasCtrl->message       = $payload->message;    // ✅
        $wablasCtrl->fonnte        = false;

        $wablasCtrl->webhook();

    }

    /**
     * Kommo bisa kirim x-www-form-urlencoded atau json.
     * Laravel biasanya sudah parse, tapi kita bikin robust.
     */
    protected function parseKommoPayload(Request $request, string $rawBody): array
    {
        $all = $request->all();
        if (is_array($all) && !empty($all)) {
            return $all;
        }

        // kalau json mentah
        $ct = (string) $request->header('Content-Type', '');
        if (str_contains($ct, 'application/json')) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // fallback querystring
        $parsed = [];
        if ($rawBody !== '') {
            parse_str($rawBody, $parsed);
        }

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Normalisasi nomor HP Indonesia -> format E.164 (+62...)
     * Sesuaikan jika dokter sudah punya helper yang lebih lengkap.
     */
    protected function normalizePhone(string $phone): string
    {
        $p = trim($phone);
        $p = preg_replace('/[^0-9\+]/', '', $p) ?? $p;

        // 08xxx -> +628xxx
        if (preg_match('/^0[0-9]+$/', $p)) {
            $p = '+62' . substr($p, 1);
        }

        // 62xxx -> +62xxx
        if (preg_match('/^62[0-9]+$/', $p)) {
            $p = '+' . $p;
        }

        if ($p !== '' && $p[0] !== '+') {
            $p = '+' . $p;
        }

        return $p;
    }

    /**
     * Deteksi tipe message dari payload Kommo.
     */
    private function kommoMessageType(array $event): string
    {
        $attType = (string) data_get($event, 'attachment.type', '');

        if ($attType === 'picture') {
            return 'image';
        }

        $text = data_get($event, 'text');
        if (is_string($text) && trim($text) !== '') {
            return 'text';
        }

        return 'unknown';
    }

    /**
     * Buat message default (kalau text null / kosong).
     */
    private function buildDefaultMessage(string $messageType, string $text): string
    {
        $t = trim($text);

        if ($t !== '') {
            return $t;
        }

        return match ($messageType) {
            'image'   => 'Halo dokter, kami menerima gambar dari Kommo.',
            'text'    => 'Halo dokter, kami menerima pesan dari Kommo.',
            default   => 'Halo dokter, kami menerima update dari Kommo.',
        };
    }
}
