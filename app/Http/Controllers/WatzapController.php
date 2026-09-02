<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

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
    /**
     * @param
     */

    public function cek_api(){
        $response = Http::acceptJson()
            ->post('https://api.watzap.id/v1/checking_key', [
                'api_key' => env('WATZAP_TOKEN'),
            ]);

        Log::info('WATZAP_CHECKING_KEY', [
            'status' => $response->status(),
            'body'   => $response->json() ?? $response->body(),
        ]);

        dd( $response->json() );
    }
    public function get_webhook(){

        $response = Http::post('https://api.watzap.id/v1/get_webhook', [
            'api_key' => env('WATZAP_TOKEN'),
            'waba_id' => env('WATZAP_NUMBER_KEY'),
        ]);

        dd($response->json());
    }

    public function set_webhook(){

        /* $endpointUrl = 'https://webhook.site/yogahn89'; */
        $endpointUrl = 'https://www.klinikjatielok.com/api/watzap/incoming';

        $response = Http::acceptJson()
            ->post('https://api.watzap.id/v1/set_webhook', [
                'api_key'      => env('WATZAP_TOKEN'),
                'number_key'   => env('WATZAP_NUMBER_KEY'),
                'endpoint_url' => $endpointUrl,
            ]);

        Log::info('WATZAP_SET_WEBHOOK', [
            'endpoint_url' => $endpointUrl,
            'status'       => $response->status(),
            'body'         => $response->json() ?? $response->body(),
        ]);

        dd([
            'endpoint_url' => $endpointUrl,
            'status'       => $response->status(),
            'body'         => $response->json() ?? $response->body(),
        ]);
    }

    public function webhook(Request $request)
    {
        Log::info( '========================');
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

        // Per instruksi dr. Yoga 2026-09-02: kalau inbound phone match
        // rujukan pending PDF (H+1 kalender-based), auto-kirim PDF
        // rujukan BPJS. Jam ≥ 8 WIB → send now. Jam < 8 WIB → schedule
        // ke jam 8 pagi. WA bot reply biasa tetap jalan setelahnya
        // (proceeded ke WablasController::webhook di bawah).
        $this->maybeAutoSendRujukanPdf((string) $normalized['phone']);

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
     * Auto-send PDF rujukan BPJS kalau inbound phone match rujukan
     * pending. Per instruksi dr. Yoga 2026-09-02.
     *
     * Trigger conditions:
     *   1. Phone match periksa.pasien.no_telp (dari row rujukan)
     *   2. Rujukan.created_at < today midnight WIB (kalender-based H+1)
     *   3. Rujukan.pdf_rujukan_bpjs_path NOT NULL
     *   4. Rujukan.pdf_sent_at IS NULL (belum pernah dikirim)
     *
     * Actions:
     *   - Jam ≥ 8 WIB → sendDocument now + set pdf_sent_at
     *   - Jam < 8 WIB → set pdf_scheduled_send_at = today 08:00 WIB
     *     (Phase 3 command akan process saat waktu tiba)
     *
     * Runs BEFORE Wablas webhook processing → bot reply biasa tetap
     * jalan (menu daftar/jadwal/dll).
     */
    protected function maybeAutoSendRujukanPdf(string $phone): void
    {
        try {
            $phone = preg_replace('/\D+/', '', $phone);
            if ($phone === '') {
                return;
            }

            $now       = \Carbon\Carbon::now('Asia/Jakarta');
            $todayWIB  = $now->copy()->startOfDay();

            // Cari rujukan pending untuk phone ini
            $rujukan = \DB::table('rujukans as r')
                ->join('periksas as p', 'p.id', '=', 'r.periksa_id')
                ->join('pasiens as ps', 'ps.id', '=', 'p.pasien_id')
                ->where('ps.no_telp', $phone)
                ->whereNotNull('r.pdf_rujukan_bpjs_path')
                ->whereNull('r.pdf_sent_at')
                ->where('r.created_at', '<', $todayWIB->toDateTimeString())
                ->select(
                    'r.id as rujukan_id',
                    'r.pdf_rujukan_bpjs_path',
                    'r.pdf_scheduled_send_at',
                    'r.tujuan_rujuk_id',
                    'r.rumah_sakit_id',
                    'ps.nama as pasien_nama',
                    'ps.no_telp'
                )
                ->orderBy('r.created_at', 'asc')
                ->first();

            if (!$rujukan) {
                return;
            }

            $jam8WIB = $todayWIB->copy()->setTime(8, 0, 0);

            // Kalau jam < 8 → schedule saja, skip send now
            if ($now->lt($jam8WIB)) {
                if (empty($rujukan->pdf_scheduled_send_at)) {
                    \DB::table('rujukans')->where('id', $rujukan->rujukan_id)->update([
                        'pdf_scheduled_send_at' => $jam8WIB->toDateTimeString(),
                        'updated_at'            => $now,
                    ]);
                    \Log::info('RUJUKAN_PDF_SCHEDULED', [
                        'rujukan_id'  => $rujukan->rujukan_id,
                        'phone'       => $phone,
                        'scheduled'   => (string) $jam8WIB,
                    ]);
                }
                return;
            }

            // Jam ≥ 8 → send now
            $this->sendRujukanPdfNow((int) $rujukan->rujukan_id);
        } catch (\Throwable $e) {
            \Log::error('RUJUKAN_PDF_AUTO_SEND_EXCEPTION', [
                'phone' => $phone,
                'err'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Kirim PDF rujukan sekarang. Public karena juga dipanggil oleh
     * scheduled command Phase 3 (rujukan:send-pending-pdfs).
     */
    public function sendRujukanPdfNow(int $rujukanId): array
    {
        $rujukan = \DB::table('rujukans as r')
            ->join('periksas as p', 'p.id', '=', 'r.periksa_id')
            ->join('pasiens as ps', 'ps.id', '=', 'p.pasien_id')
            ->leftJoin('tujuan_rujuks as tr', 'tr.id', '=', 'r.tujuan_rujuk_id')
            ->leftJoin('rumah_sakits as rs', 'rs.id', '=', 'r.rumah_sakit_id')
            ->where('r.id', $rujukanId)
            ->select(
                'r.id',
                'r.pdf_rujukan_bpjs_path',
                'r.pdf_sent_at',
                'ps.nama as pasien_nama',
                'ps.no_telp',
                'tr.tujuan_rujuk as spesialisasi',
                'rs.nama_rumah_sakit as rumah_sakit'
            )
            ->first();

        if (!$rujukan) {
            \Log::warning('RUJUKAN_PDF_SEND_NOT_FOUND', ['rujukan_id' => $rujukanId]);
            return ['ok' => false, 'reason' => 'rujukan_not_found'];
        }
        if (!empty($rujukan->pdf_sent_at)) {
            \Log::info('RUJUKAN_PDF_SEND_ALREADY_SENT', ['rujukan_id' => $rujukanId]);
            return ['ok' => true, 'reason' => 'already_sent'];
        }
        if (empty($rujukan->pdf_rujukan_bpjs_path)) {
            \Log::warning('RUJUKAN_PDF_SEND_NO_PDF', ['rujukan_id' => $rujukanId]);
            return ['ok' => false, 'reason' => 'no_pdf_uploaded'];
        }

        // Generate signed URL S3 (10 menit TTL — Watzap fetch cepat)
        try {
            $signedUrl = \Storage::disk('s3')->temporaryUrl(
                $rujukan->pdf_rujukan_bpjs_path,
                \Carbon\Carbon::now()->addMinutes(10)
            );
        } catch (\Throwable $e) {
            \Log::error('RUJUKAN_PDF_S3_SIGNED_URL_FAIL', [
                'rujukan_id' => $rujukanId,
                'err'        => $e->getMessage(),
            ]);
            return ['ok' => false, 'reason' => 's3_signed_url_fail'];
        }

        $caption = "Halo Kak " . ($rujukan->pasien_nama ?: '') . " 🙏\n\n"
                 . "Berikut rujukan BPJS Anda:\n\n"
                 . "📋 Spesialisasi : " . ($rujukan->spesialisasi ?: '-') . "\n"
                 . "🏥 Rumah Sakit  : " . ($rujukan->rumah_sakit ?: '-') . "\n\n"
                 . "PDF rujukan terlampir 📎\n\n"
                 . "Apabila ada kesalahan rujukan, mohon segera hubungi admin klinik. "
                 . "Terima kasih 🙏";

        $filename = 'Rujukan_BPJS_' . $rujukan->id . '.pdf';

        $watzap = app(\App\Services\WatzapService::class);
        $result = $watzap->sendDocument((string) $rujukan->no_telp, $signedUrl, $caption, $filename);

        if ($result['ok'] ?? false) {
            \DB::table('rujukans')->where('id', $rujukanId)->update([
                'pdf_sent_at'           => \Carbon\Carbon::now(),
                'pdf_scheduled_send_at' => null,
                'updated_at'            => \Carbon\Carbon::now(),
            ]);
            \Log::info('RUJUKAN_PDF_SENT', [
                'rujukan_id' => $rujukanId,
                'phone'      => $rujukan->no_telp,
            ]);
        } else {
            \Log::warning('RUJUKAN_PDF_SEND_FAIL', [
                'rujukan_id' => $rujukanId,
                'phone'      => $rujukan->no_telp,
                'reason'     => $result['reason'] ?? 'unknown',
            ]);
        }

        return $result;
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
            'provider'    => 'watzap',
            'origin'      => 'watzap',
            'phone'       => $phone,
            'messageType' => $messageType,
            'message'     => $message,
            'url'         => $url,
            'room_id'     => $roomId,
            'channel'     => 'wa',
            'watzap_raw'  => $raw,
        ];
    }

    protected function resolvePhone(array $raw): ?string
    {
        $candidates = [
            // Watzap WABA payload (utama)
            data_get($raw, 'data.phone'),
            data_get($raw, 'data.message_raw.from'),
            data_get($raw, 'data.root_value.messages.0.from'),
            data_get($raw, 'data.root_value.contacts.0.wa_id'),

            // Bentuk umum lainnya
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
            // Watzap WABA payload (utama)
            data_get($raw, 'data.message_type')
            ?? data_get($raw, 'data.message_raw.type')
            ?? data_get($raw, 'data.root_value.messages.0.type')

            // Bentuk umum lainnya
            ?? data_get($raw, 'messageType')
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
        // For media bubbles, prefer the actual caption fields first.
        // data.message_text is "[Image]" / "[Video]" placeholder when
        // there is no caption — surfacing that literal string into the
        // messages.message column makes the chat UI render "[image]"
        // text instead of the actual image, so we skip it here.
        $isMedia = in_array($messageType, ['image', 'video', 'document', 'audio'], true);

        $captionCandidates = [
            data_get($raw, 'data.message_raw.image.caption'),
            data_get($raw, 'data.message_raw.video.caption'),
            data_get($raw, 'data.message_raw.document.caption'),
            data_get($raw, 'message.caption'),
            data_get($raw, 'caption'),
            data_get($raw, 'payload.message.caption'),
        ];

        $textCandidates = [
            data_get($raw, 'data.message_text'),
            data_get($raw, 'data.message_raw.text.body'),
            data_get($raw, 'data.root_value.messages.0.text.body'),
            // Template / interactive button taps: WhatsApp Cloud API
            // delivers the visible button label here. Without these
            // candidates a "1. Memuaskan" tap surfaces only as the raw
            // type ("interactive"/"button") and the survey reply
            // pipeline never sees the user's choice.
            data_get($raw, 'data.message_raw.button.text'),
            data_get($raw, 'data.message_raw.button.payload'),
            data_get($raw, 'data.message_raw.interactive.button_reply.title'),
            data_get($raw, 'data.message_raw.interactive.button_reply.id'),
            data_get($raw, 'data.root_value.messages.0.button.text'),
            data_get($raw, 'data.root_value.messages.0.interactive.button_reply.title'),
            data_get($raw, 'data.root_value.messages.0.interactive.button_reply.id'),
            data_get($raw, 'message'),
            data_get($raw, 'text'),
            data_get($raw, 'body'),
            data_get($raw, 'data.message'),
            data_get($raw, 'data.text'),
            data_get($raw, 'data.body'),
            data_get($raw, 'message.text'),
            data_get($raw, 'message.body'),
            data_get($raw, 'payload.message.text'),
        ];

        $candidates = $isMedia
            ? array_merge($captionCandidates, $textCandidates)
            : array_merge($textCandidates, $captionCandidates);

        // Skip the bracketed placeholder WatZap emits when an image /
        // video has no caption (e.g. "[Image]", "[Video]").
        $placeholderRe = '/^\s*\[\s*(image|video|document|audio|file|sticker)\s*\]\s*$/iu';

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) continue;
            $trim = trim($candidate);
            if ($trim === '') continue;
            if (preg_match($placeholderRe, $trim)) continue;
            return strtolower($trim);
        }

        // kalau image tanpa caption, jangan dipaksa string aneh
        if ($isMedia) {
            return '';
        }

        return null;
    }

    protected function resolveMediaUrl(array $raw, string $messageType): ?string
    {
        $candidates = [
            // Watzap WABA payload (utama) — cdn_url is what WatZap
            // actually emits for incoming image/video bubbles; the
            // others stayed in this list when an earlier schema was
            // assumed but never proven against live payload.
            data_get($raw, 'data.media_info.cdn_url'),
            data_get($raw, 'data.media_info.url'),
            data_get($raw, 'data.media_info.link'),
            data_get($raw, 'data.media_info.file_url'),

            // Raw WhatsApp Cloud API attachments — fallback if WatZap
            // omits its proxied cdn_url for whatever reason.
            data_get($raw, 'data.message_raw.image.url'),
            data_get($raw, 'data.message_raw.video.url'),
            data_get($raw, 'data.message_raw.document.url'),

            // Bentuk umum lainnya
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
        return true;
    }

}
