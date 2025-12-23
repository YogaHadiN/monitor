<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class KommoReplyService
{
    public function __construct(
        protected KommoClient $kommo
    ) {}

    /**
     * Balas pesan text ke percakapan Kommo (Chats API - amojo origin/custom).
     *
     * @return array{ok:bool, reason?:string, response?:array}
     */
    public function replyText(array $ctx, string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'reason' => 'empty_text'];
        }

        $origin = (string)($ctx['origin'] ?? '');
        $phone  = (string)($ctx['phone'] ?? $ctx['phone_normalized'] ?? '');

        $conversationId = $this->buildConversationId($origin, $phone, $ctx);

        if ($conversationId === null) {
            Log::warning('KOMMO_REPLY_SKIP_NO_CONVERSATION_ID', [
                'origin' => $origin,
                'phone'  => $phone,
                'ctx'    => $this->safeCtx($ctx),
            ]);
            return ['ok' => false, 'reason' => 'no_conversation_id'];
        }

        Log::info('KOMMO_REPLY_TEXT_ATTEMPT', [
            'conversation_id' => $conversationId,
            'origin'          => $origin,
            'phone'           => $phone,
            'text_preview'    => mb_substr($text, 0, 80),
        ]);

        try {
            $res = $this->kommo->sendMessageToConversation($conversationId, $text);

            Log::info('KOMMO_REPLY_TEXT_OK', [
                'conversation_id' => $conversationId,
                'response'        => $this->truncateDeep($res),
            ]);

            return ['ok' => true, 'response' => $res];
        } catch (\Throwable $e) {
            Log::error('KOMMO_REPLY_TEXT_FAIL', [
                'conversation_id' => $conversationId,
                'error'           => $e->getMessage(),
            ]);
            return ['ok' => false, 'reason' => 'exception:' . $e->getMessage()];
        }
    }

    /**
     * Jika nanti dokter mau dukung reply image lewat Kommo channel custom.
     * (Banyak implementasi mewajibkan upload attachments dulu -> attachment_id)
     */
    public function replyImage(array $ctx, string $imageUrl, ?string $caption = null): array
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '') {
            return ['ok' => false, 'reason' => 'empty_image_url'];
        }

        $origin = (string)($ctx['origin'] ?? '');
        $phone  = (string)($ctx['phone'] ?? $ctx['phone_normalized'] ?? '');

        $conversationId = $this->buildConversationId($origin, $phone, $ctx);
        if ($conversationId === null) {
            return ['ok' => false, 'reason' => 'no_conversation_id'];
        }

        // NOTE: implementasi gambar sangat tergantung channel.
        // Untuk saat ini, fallback: kirim caption + link sebagai text.
        $text = trim((string)$caption);
        if ($text === '') {
            $text = 'Gambar: ' . $imageUrl;
        } else {
            $text = $text . "\n" . $imageUrl;
        }

        return $this->replyText($ctx, $text);
    }

    /**
     * Build conversation_id (kunci utama agar tidak 404).
     *
     * Strategy:
     * - Kalau ctx sudah punya conversation_id → pakai itu.
     * - Kalau origin=waba dan punya phone → pakai "external:whatsapp:{phone}"
     * - Kalau origin lain → bisa di-extend sesuai channel dokter.
     */
    protected function buildConversationId(string $origin, string $phone, array $ctx): ?string
    {
        $explicit = (string)($ctx['conversation_id'] ?? '');
        if ($explicit !== '') {
            return $explicit;
        }

        // normalisasi minimal: pastikan +62 dll sudah dilakukan sebelumnya
        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        // Kommo webhook dokter menunjukkan origin "waba" untuk WhatsApp
        if ($origin === 'waba') {
            return 'external:whatsapp:' . $phone;
        }

        // Extend mapping origin lain jika ada (telegram, ig, dll)
        // contoh:
        // if ($origin === 'telegram') return 'external:telegram:' . $phone;

        return null;
    }

    protected function safeCtx(array $ctx): array
    {
        // jangan log token/secret; batasi field
        return [
            'origin'   => $ctx['origin'] ?? null,
            'chat_id'  => $ctx['chat_id'] ?? $ctx['kommo_chat_id'] ?? null,
            'contact'  => $ctx['contact_id'] ?? $ctx['kommo_contact_id'] ?? null,
            'phone'    => $ctx['phone'] ?? $ctx['phone_normalized'] ?? null,
            'type'     => $ctx['message_type'] ?? null,
        ];
    }

    protected function truncateDeep($data, int $maxLen = 1200)
    {
        $json = json_encode($data);
        if (!is_string($json)) return $data;
        if (strlen($json) <= $maxLen) return $data;
        return substr($json, 0, $maxLen) . '...';
    }
}
