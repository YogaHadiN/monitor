<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\NoTelp;
use App\Http\Controllers\WablasController;

class KommoWebhookController extends Controller
{
    /**
     * ENTRY POINT WEBHOOK KOMMO
     */
    public function handle(Request $request)
    {
        // ===============================
        // LOG RAW & PARSED (DEBUG AMAN)
        // ===============================
        Log::info('KOMMO_WEBHOOK_RAW', [
            'content_type' => $request->header('Content-Type'),
            'ip'           => $request->ip(),
            'raw'          => $request->getContent(),
        ]);

        $payload = $request->all();

        Log::info('KOMMO_WEBHOOK_PARSED', $payload);

        // ===============================
        // ROUTING EVENT
        // ===============================
        if (isset($payload['message']['add'])) {
            $this->handleIncomingMessage($payload);
        }

        // ⚠️ Kommo WAJIB dapat 2xx
        return response()->json(['ok' => true], 200);
    }

    /**
     * ===============================
     * HANDLE INCOMING CHAT MESSAGE
     * ===============================
     */
    protected function handleIncomingMessage(array $payload): void
    {
        $msg = data_get($payload, 'message.add.0');
        if (!$msg) return;

        // hanya pesan MASUK
        if (data_get($msg, 'type') !== 'incoming') return;

        $chatId    = data_get($msg, 'chat_id');
        $contactId = data_get($msg, 'contact_id');
        $text      = trim(data_get($msg, 'text', ''));

        if (!$contactId) return;

        Log::info('KOMMO_INCOMING_MESSAGE', [
            'chat_id'    => $chatId,
            'contact_id' => $contactId,
            'text'       => $text,
        ]);

        // ===============================
        // AMBIL PHONE DARI KOMMO CONTACT
        // ===============================
        $phone = $this->getContactPhone($contactId);
        if (!$phone) return;

        $normalizedPhone = $this->normalizePhone($phone);

        // ===============================
        // (OPSIONAL) FORWARD KE WABLAS
        // ===============================
        $this->forwardToWablas(
            roomId: $chatId,
            phone: $normalizedPhone,
            message: $text
        );
    }

    /**
     * ===============================
     * HANDLE LEAD UPDATE (OPSIONAL)
     * ===============================
     */
    protected function handleLeadUpdate(array $payload): void
    {
        $lead = data_get($payload, 'leads.update.0');
        if (!$lead) return;

        Log::info('KOMMO_LEAD_UPDATE', [
            'lead_id'    => data_get($lead, 'id'),
            'status_id'  => data_get($lead, 'status_id'),
            'pipeline_id'=> data_get($lead, 'pipeline_id'),
        ]);

        // 👉 logic lanjutan bisa ditaruh di sini
    }

    /**
     * ===============================
     * GET CONTACT PHONE FROM KOMMO
     * ===============================
     */
    protected function getContactPhone(string|int|null $contactId): ?string
    {
        if (empty($contactId)) {
            return null;
        }

        try {
            $contact = $this->kommoClient->getContactById($contactId);

            $fields = data_get($contact, 'custom_fields_values', []);
            foreach ($fields as $field) {
                if (($field['field_code'] ?? '') === 'PHONE') {
                    $val = $field['values'][0]['value'] ?? null;
                    return $val ? (string) $val : null;
                }
            }
        } catch (\Throwable $e) {
            \Log::error('KOMMO_GET_CONTACT_PHONE_FAILED', [
                'contact_id' => $contactId,
                'error'      => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * ===============================
     * NORMALIZE PHONE (WA SAFE)
     * ===============================
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '+62' . substr($phone, 1);
        }

        if (!str_starts_with($phone, '+')) {
            return '+' . $phone;
        }

        return $phone;
    }

    /**
     * ===============================
     * FORWARD TO WABLAS / FONNTE
     * ===============================
     */
    protected function forwardToWablas(string $roomId, string $phone, string $message): void
    {
        $wablas               = new WablasController;
        $wablas->room_id      = $roomId;
        $wablas->no_telp      = $phone;
        $wablas->message_type = 'text';
        $wablas->image_url    = null;
        $wablas->message      = $message;
        $wablas->fonnte       = true;

        $wablas->webhook();
    }
}
