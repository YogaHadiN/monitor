<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WatzapController extends Controller
{
    /**
     * Webhook inbound dari WatZap.
     *
     * Strategi:
     * - terima payload WatZap mentah
     * - normalisasi ke format yang dipakai WablasController lama:
     *   phone, messageType, message, url
     * - inject ke request saat ini
     * - teruskan ke WablasController->webhook()
     */
    public function webhook(Request $request)
    {
        $raw = $request->all();

        Log::info('WATZAP_WEBHOOK_RAW', [
            'headers' => $request->headers->all(),
            'body'    => $raw,
        ]);

        // Optional:
        // aktifkan kalau WatZap memang mengirim signature / token tertentu
        // kalau belum yakin field/header-nya, biarkan dulu false
        if (!$this->passesWebhookAuth($request)) {
            Log::warning('WATZAP_WEBHOOK_UNAUTHORIZED', [
                'headers' => $request->headers->all(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Unauthorized webhook',
            ], 401);
        }

        $normalized = $this->normalizeIncomingPayload($raw);

        Log::info('WATZAP_WEBHOOK_NORMALIZED', $normalized);

        if (empty($normalized['phone'])) {
            return response()->json([
                'status'  => false,
                'message' => 'Nomor pengirim tidak ditemukan pada payload webhook',
                'data'    => $normalized,
            ], 422);
        }

        // Inject ke request aktif agar Input::get() di WablasController bisa membaca
        $request->merge($normalized);
        app()->instance('request', $request);

        /** @var \App\Http\Controllers\WablasController $wablas */
        $wablas = app(\App\Http\Controllers\WablasController::class);

        $response = $wablas->webhook();

        // Banyak method lama return null/void
        if (!is_null($response)) {
            return $response;
        }

        return response()->json([
            'status'  => true,
            'message' => 'Webhook diproses',
        ]);
    }

    /**
     * Normalisasi payload WatZap menjadi format lama:
     * - phone
     * - messageType
     * - message
     * - url
     *
     * Karena payload WatZap belum saya bisa baca penuh dari docs dynamic,
     * method ini dibuat toleran terhadap beberapa bentuk field umum.
     */
    protected function normalizeIncomingPayload(array $raw): array
    {
        $phone       = $this->resolvePhone($raw);
        $messageType = $this->resolveMessageType($raw);
        $message     = $this->resolveMessage($raw, $messageType);
        $url         = $this->resolveMediaUrl($raw, $messageType);
        $roomId      = $this->resolveRoomId($raw);

        return [
            // format fallback yang sudah didukung constructor WablasController
            'phone'       => $phone,
            'messageType' => $messageType,
            'message'     => $message,
            'url'         => $url,
            'room_id'     => $roomId,

            // simpan raw jika suatu saat ingin dipakai
            'watzap_raw'  => $raw,
        ];
    }

    protected function resolvePhone(array $raw): ?string
    {
        $candidates = [
            data_get($raw, 'phone'),
            data_get($raw, 'from'),
            data_get($raw, 'sender'),
            data_get($raw, 'sender_phone'),
            data_get($raw, 'customer.phone'),
            data_get($raw, 'customer.number'),
            data_get($raw, 'contact.phone'),
            data_get($raw, 'contact.number'),
            data_get($raw, 'message.from'),
            data_get($raw, 'data.from'),
            data_get($raw, 'data.phone'),
            data_get($raw, 'payload.from'),
            data_get($raw, 'payload.phone'),
        ];

        foreach ($candidates as $candidate) {
            $phone = $this->normalizePhone($candidate);
            if (!empty($phone)) {
                return $phone;
            }
        }

        return null;
    }

    protected function resolveMessageType(array $raw): string
    {
        $type = strtolower((string) (
            data_get($raw, 'messageType')
            ?? data_get($raw, 'message_type')
            ?? data_get($raw, 'type')
            ?? data_get($raw, 'message.type')
            ?? data_get($raw, 'data.type')
            ?? data_get($raw, 'payload.message.type')
            ?? 'text'
        ));

        // samakan ke istilah yang dipakai code lama
        if (in_array($type, ['image', 'photo', 'picture', 'file_attachment'])) {
            return 'image';
        }

        if (in_array($type, ['document', 'file', 'attachment'])) {
            // kalau Wablas lama hanya paham image/text,
            // sementara anggap sebagai image jika ada URL media
            if (!empty($this->resolveMediaUrl($raw, $type))) {
                return 'image';
            }
            return 'text';
        }

        if (in_array($type, ['video', 'audio', 'voice', 'ptt', 'sticker'])) {
            return $type;
        }

        return 'text';
    }

    protected function resolveMessage(array $raw, string $messageType): ?string
    {
        $candidates = [
            data_get($raw, 'message'),
            data_get($raw, 'text'),
            data_get($raw, 'body'),
            data_get($raw, 'caption'),
            data_get($raw, 'data.message'),
            data_get($raw, 'data.text'),
            data_get($raw, 'data.body'),
            data_get($raw, 'message.text'),
            data_get($raw, 'message.body'),
            data_get($raw, 'message.caption'),
            data_get($raw, 'payload.message.text'),
            data_get($raw, 'payload.message.caption'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return strtolower(trim($candidate));
            }
        }

        // kalau image tanpa caption, jangan dipaksa string aneh
        if ($messageType === 'image') {
            return '';
        }

        return null;
    }

    protected function resolveMediaUrl(array $raw, string $messageType): ?string
    {
        $candidates = [
            data_get($raw, 'url'),
            data_get($raw, 'media_url'),
            data_get($raw, 'file_url'),
            data_get($raw, 'attachment.url'),
            data_get($raw, 'image.url'),
            data_get($raw, 'document.url'),
            data_get($raw, 'message.url'),
            data_get($raw, 'message.media_url'),
            data_get($raw, 'message.image.url'),
            data_get($raw, 'message.document.url'),
            data_get($raw, 'data.url'),
            data_get($raw, 'data.media_url'),
            data_get($raw, 'payload.message.payload.url'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && filter_var($candidate, FILTER_VALIDATE_URL)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function resolveRoomId(array $raw): ?string
    {
        $candidates = [
            data_get($raw, 'room_id'),
            data_get($raw, 'room.id'),
            data_get($raw, 'chat_id'),
            data_get($raw, 'conversation_id'),
            data_get($raw, 'thread_id'),
            data_get($raw, 'data.chat_id'),
            data_get($raw, 'payload.room.id'),
        ];

        foreach ($candidates as $candidate) {
            if (!is_null($candidate) && $candidate !== '') {
                return (string) $candidate;
            }
        }

        return null;
    }

    protected function normalizePhone($phone): ?string
    {
        if (is_null($phone)) {
            return null;
        }

        $phone = (string) $phone;
        $phone = trim($phone);

        if ($phone === '') {
            return null;
        }

        // ambil digit saja
        $phone = preg_replace('/\D+/', '', $phone);

        if ($phone === '') {
            return null;
        }

        // beberapa provider kirim 08..., samakan ke 628...
        if (strpos($phone, '08') === 0) {
            $phone = '62' . substr($phone, 1);
        }

        // kalau 8xxxxxxxx tanpa 62
        if (strpos($phone, '8') === 0) {
            $phone = '62' . $phone;
        }

        return $phone;
    }

    /**
     * Placeholder verifikasi webhook.
     *
     * Ubah sesuai docs WatZap kalau nanti sudah pasti header/token-nya.
     */
    protected function passesWebhookAuth(Request $request): bool
    {
        $expectedToken = config('services.watzap.webhook_token');

        if (empty($expectedToken)) {
            return true;
        }

        $provided = $request->header('X-WatZap-Token')
            ?? $request->header('X-Webhook-Token')
            ?? $request->get('token');

        return hash_equals((string) $expectedToken, (string) $provided);
    }
}
