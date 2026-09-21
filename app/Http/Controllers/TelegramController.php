<?php

namespace App\Http\Controllers;

use App\Models\Pasien;
use App\Models\TelegramLinkToken;
use App\Models\TelegramUser;
use App\Services\TelegramClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handler webhook Telegram Bot API. Endpoint publik di
 * POST /telegram/webhook, dipanggil Telegram tiap ada update
 * (message / callback_query). Design mirror WablasController tapi
 * jauh lebih sederhana krn Bot API sudah struktured.
 *
 * Phase 1 scope:
 *  - /start (dgn / tanpa payload deep-link)
 *  - trigger "daftar" — placeholder (echo dulu, port dari
 *    WablasController::registrasiAntrianOnline nanti)
 *  - default fallback: echo hint
 */
class TelegramController extends Controller
{
    private TelegramClient $tg;

    public function __construct(TelegramClient $tg)
    {
        $this->tg = $tg;
    }

    /**
     * Webhook entrypoint. Telegram bakal POST JSON body ke sini setiap
     * ada update. Response HTTP 200 kosong sudah cukup — response body
     * bisa juga berisi InputMessage utk instant reply, tapi lebih
     * eksplisit lewat call API terpisah.
     */
    public function webhook(Request $request)
    {
        // Verifikasi secret header kalau di-set. Telegram kirim header
        // "X-Telegram-Bot-Api-Secret-Token" berisi value yg kita set
        // saat setWebhook. Kalau tidak match → 401 (kemungkinan
        // penyerangan / config mismatch).
        $expectedSecret = (string) config('telegram.webhook_secret', '');
        if ($expectedSecret !== '') {
            $received = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
            if (!hash_equals($expectedSecret, $received)) {
                Log::warning('TELEGRAM_WEBHOOK_BAD_SECRET', [
                    'ip' => $request->ip(),
                ]);
                return response('unauthorized', 401);
            }
        }

        $update = $request->all();

        try {
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
            } else {
                Log::info('TELEGRAM_UPDATE_UNHANDLED', ['type' => array_keys($update)]);
            }
        } catch (\Throwable $e) {
            // Jangan pernah 500 ke Telegram — mereka bakal retry
            // aggressively. Log + ack 200.
            Log::error('TELEGRAM_HANDLER_EXCEPTION', [
                'error'  => $e->getMessage(),
                'file'   => $e->getFile(),
                'line'   => $e->getLine(),
                'update' => $update,
            ]);
        }

        return response('ok', 200);
    }

    private function handleMessage(array $msg): void
    {
        $chatId = (int) ($msg['chat']['id'] ?? 0);
        if ($chatId === 0) return;

        $this->touchUser($chatId, $msg['from'] ?? []);

        $text = trim((string) ($msg['text'] ?? ''));

        // /start [payload]
        if (str_starts_with($text, '/start')) {
            $payload = trim(substr($text, 6)); // "/start abc" → "abc"
            $this->handleStart($chatId, $payload);
            return;
        }

        // Trigger "daftar" mirror WA. Sementara echo — flow beneran
        // (registrasiAntrianOnline) di-port terpisah phase 2.
        $lower = mb_strtolower($text);
        $daftarTriggers = ['daftar', 'daptar', 'mau daftar', 'mau berobat', 'berobat'];
        if (in_array($lower, $daftarTriggers, true)) {
            $this->tg->sendMessage($chatId,
                "Halo kak! Fitur pendaftaran online via Telegram masih dalam pengembangan.\n\n" .
                "Untuk sementara, silakan daftar via WhatsApp di 0895369269190 atau datang langsung ke klinik."
            );
            return;
        }

        // Default fallback
        $this->tg->sendMessage($chatId,
            "Halo kak 👋\n\n" .
            "Bot Klinik Jati Elok. Coba ketik salah satu:\n" .
            "• /start — mulai\n" .
            "• daftar — daftar antrian online (coming soon)\n\n" .
            "Butuh bantuan operator? Ketik 'operator'."
        );
    }

    private function handleCallbackQuery(array $cbq): void
    {
        // Semua callback query harus di-answer supaya spinner di
        // tombol berhenti. Isi text opsional (jadi toast di client).
        $callbackId = (string) ($cbq['id'] ?? '');
        if ($callbackId === '') return;

        $this->tg->answerCallbackQuery($callbackId);

        $chatId = (int) ($cbq['message']['chat']['id'] ?? 0);
        $data   = (string) ($cbq['data'] ?? '');

        Log::info('TELEGRAM_CALLBACK_QUERY', [
            'chat_id' => $chatId,
            'data'    => $data,
        ]);

        // Phase 2: dispatch $data ke handler (mis. "poli:umum",
        // "bayar:bpjs", dll). Sekarang placeholder.
        if ($chatId > 0 && $data !== '') {
            $this->tg->sendMessage($chatId, "Kamu pilih: {$data}\n\n(handler belum di-implement)");
        }
    }

    /**
     * /start [payload]
     * - Tanpa payload: welcome + instruksi.
     * - Payload berbentuk "t_xxxxx": consume TelegramLinkToken,
     *   link chat_id ↔ pasien_id.
     */
    private function handleStart(int $chatId, string $payload): void
    {
        // Deep link onboarding pasien
        if ($payload !== '' && str_starts_with($payload, 't_')) {
            $token = TelegramLinkToken::where('token', $payload)->first();
            if (!$token || !$token->isValid()) {
                $this->tg->sendMessage($chatId,
                    "❌ Link tidak valid atau sudah kedaluwarsa.\n\n" .
                    "Silakan minta link baru ke petugas klinik."
                );
                return;
            }

            $pasien = Pasien::find($token->pasien_id);
            if (!$pasien) {
                $this->tg->sendMessage($chatId, "❌ Data pasien tidak ditemukan.");
                return;
            }

            // Link chat_id ↔ pasien_id
            $token->consume($chatId);

            $pasien->telegram_chat_id = $chatId;
            $pasien->save();

            TelegramUser::where('chat_id', $chatId)->update([
                'pasien_id'    => $pasien->id,
                'tenant_id'    => $token->tenant_id,
                'last_seen_at' => now(),
            ]);

            $this->tg->sendMessage($chatId,
                "✅ Berhasil terhubung, *" . ($pasien->nama ?? 'pasien') . "*!\n\n" .
                "Mulai sekarang kamu bakal terima notifikasi antrian, jadwal, dan " .
                "info lain dari Klinik Jati Elok via bot ini.\n\n" .
                "Ketik *daftar* untuk buat antrian online.",
                ['parse_mode' => 'Markdown']
            );
            return;
        }

        // Payload lain (nanti: 'daftar_umum', 'jadwal_dokter', dll)
        // untuk deep link shortcut. Sekarang default welcome.
        $this->tg->sendMessage($chatId,
            "Halo kak! 👋\n\n" .
            "Selamat datang di bot Klinik Jati Elok.\n\n" .
            "Untuk aktivasi penuh (terima notifikasi antrian, jadwal, dll), " .
            "silakan scan QR code di klinik atau minta link ke petugas.\n\n" .
            "Coba ketik:\n" .
            "• *daftar* — daftar antrian online\n" .
            "• *operator* — hubungi admin",
            ['parse_mode' => 'Markdown']
        );
    }

    /**
     * Insert / refresh row TelegramUser tiap ada aktivitas — semacam
     * "last_seen" tracking + auto-register user baru.
     */
    private function touchUser(int $chatId, array $from): void
    {
        TelegramUser::updateOrCreate(
            ['chat_id' => $chatId],
            [
                'username'      => $from['username']      ?? null,
                'first_name'    => $from['first_name']    ?? null,
                'last_name'     => $from['last_name']     ?? null,
                'language_code' => $from['language_code'] ?? null,
                'last_seen_at'  => now(),
            ]
        );
    }
}
