<?php

namespace App\Http\Controllers;

use App\Models\NoTelp;
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
 * Onboarding flow:
 *  1. /start → welcome + minta no_telp via request_contact button
 *     (kecuali deep-link token, atau sudah pernah share).
 *  2. User tap button → Telegram kirim message.contact →
 *     kita simpan ke no_telps + telegram_users.
 *  3. Otomatis fire NoTelpCreated event → FCM push → device Android
 *     sync ke Google Contacts (sama seperti WA).
 */
class TelegramController extends Controller
{
    private TelegramClient $tg;

    public function __construct(TelegramClient $tg)
    {
        $this->tg = $tg;
    }

    public function webhook(Request $request)
    {
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

        $tgUser = $this->touchUser($chatId, $msg['from'] ?? []);

        // Prioritas: kalau user tap tombol "Kirim Nomor HP", Telegram
        // kirim update dgn message.contact — handle dulu.
        if (isset($msg['contact'])) {
            $this->handleContact($chatId, $msg['contact'], $msg['from'] ?? [], $tgUser);
            return;
        }

        $text = trim((string) ($msg['text'] ?? ''));

        // /start [payload]
        if (str_starts_with($text, '/start')) {
            $payload = trim(substr($text, 6));
            $this->handleStart($chatId, $payload, $tgUser);
            return;
        }

        // Kalau user belum share nomor & belum linked ke pasien →
        // gate semua interaksi lain dgn permintaan nomor HP. Bot tidak
        // bisa serve booking / info sebelum tau siapa pasien-nya.
        if (empty($tgUser->no_telp) && empty($tgUser->pasien_id)) {
            $this->requestPhone($chatId,
                "Sebelum lanjut, mohon share nomor HP Kakak dulu ya 🙏\n\n" .
                "Tap tombol *📱 Kirim Nomor HP* di bawah."
            );
            return;
        }

        // Delegate ke WablasController::webhook() lewat bridge —
        // full routing (daftar, cek antrian, batalkan, jadwal dokter,
        // chat admin, konfirmasi pembatalan, prosesAntrianOnline,
        // dst) langsung reusable. Sama persis dgn flow WA.
        $bridge = new TelegramWablasBridge(
            $this->tg,
            $chatId,
            $tgUser->no_telp,
            $text
        );

        try {
            $bridge->webhook();
        } catch (\Throwable $e) {
            \Log::error('TELEGRAM_BRIDGE_EXCEPTION', [
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'chat_id' => $chatId,
                'text'    => $text,
            ]);
            $this->tg->sendMessage($chatId,
                "❌ Ada gangguan sistem, silakan coba lagi atau ketik *batalkan* untuk reset.",
                ['parse_mode' => 'Markdown']
            );
        }
    }

    private function handleCallbackQuery(array $cbq): void
    {
        $callbackId = (string) ($cbq['id'] ?? '');
        if ($callbackId === '') return;

        $this->tg->answerCallbackQuery($callbackId);

        $chatId = (int) ($cbq['message']['chat']['id'] ?? 0);
        $data   = (string) ($cbq['data'] ?? '');

        Log::info('TELEGRAM_CALLBACK_QUERY', [
            'chat_id' => $chatId,
            'data'    => $data,
        ]);

        if ($chatId > 0 && $data !== '') {
            $this->tg->sendMessage($chatId, "Kamu pilih: {$data}\n\n(handler belum di-implement)");
        }
    }

    /**
     * /start [payload]
     * - Deep link t_xxx: consume link token, langsung link chat ↔ pasien.
     * - Tanpa payload: welcome + minta no_telp.
     */
    private function handleStart(int $chatId, string $payload, TelegramUser $tgUser): void
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

        // Kalau sudah pernah share nomor → welcome kembali, tidak minta lagi
        if (!empty($tgUser->no_telp)) {
            $this->tg->sendMessage($chatId,
                "Halo kak, senang jumpa lagi 👋\n\n" .
                "Ketik *daftar* untuk buat antrian online, atau *operator* untuk hubungi admin.",
                ['parse_mode' => 'Markdown']
            );
            return;
        }

        // First-time user → welcome + minta no_telp via request_contact
        $this->tg->sendMessage($chatId,
            "Halo kak! 👋\n\n" .
            "Selamat datang di bot Klinik Jati Elok.\n\n" .
            "Supaya bisa layani Kakak (notifikasi antrian, konfirmasi jadwal, " .
            "dll), kami butuh nomor HP Kakak dulu ya 🙏\n\n" .
            "Tap tombol *📱 Kirim Nomor HP* di bawah.",
            [
                'parse_mode'   => 'Markdown',
                'reply_markup' => $this->contactRequestKeyboard(),
            ]
        );
    }

    /**
     * Handler saat user tap tombol "Kirim Nomor HP" — Telegram kirim
     * update.message.contact berisi phone_number + user_id + first_name.
     */
    private function handleContact(int $chatId, array $contact, array $from, TelegramUser $tgUser): void
    {
        $contactUserId = (int) ($contact['user_id'] ?? 0);
        $fromId        = (int) ($from['id'] ?? 0);

        // Security: kalau contact.user_id != from.id, artinya user share
        // KONTAK ORANG LAIN, bukan nomor sendiri. Tolak.
        if ($contactUserId === 0 || $contactUserId !== $fromId) {
            $this->tg->sendMessage($chatId,
                "⚠️ Mohon share nomor HP *Kakak sendiri*, bukan kontak orang lain.\n\n" .
                "Tap ulang tombol *📱 Kirim Nomor HP* di bawah.",
                [
                    'parse_mode'   => 'Markdown',
                    'reply_markup' => $this->contactRequestKeyboard(),
                ]
            );
            return;
        }

        $rawPhone = (string) ($contact['phone_number'] ?? '');
        $noTelp   = $this->normalizePhone($rawPhone);

        if ($noTelp === '') {
            $this->tg->sendMessage($chatId, "❌ Nomor HP tidak valid, coba lagi ya kak.");
            return;
        }

        // 1) Update TelegramUser
        $tgUser->no_telp           = $noTelp;
        $tgUser->no_telp_shared_at = now();
        $tgUser->save();

        // 2) Upsert no_telps + set telegram_chat_id. Kalau row baru
        //    dibuat, NoTelpCreated event otomatis fire → FCM push →
        //    Android app sync ke Google Contacts.
        $noTelpRow = NoTelp::firstOrCreate(
            ['no_telp' => $noTelp],
            ['tenant_id' => 1]
        );
        $noTelpRow->telegram_chat_id = $chatId;
        $noTelpRow->last_received_message_time = now()->format('Y-m-d H:i:s');
        $noTelpRow->save();

        // 3) Auto-link ke pasien kalau nomor cocok. Ambil pasien
        //    terakhir yg pakai nomor ini (biasanya kepala keluarga).
        if (empty($tgUser->pasien_id)) {
            $pasien = Pasien::where('no_telp', $noTelp)
                ->orderByDesc('updated_at')
                ->first();
            if ($pasien) {
                $tgUser->pasien_id = $pasien->id;
                $tgUser->tenant_id = $pasien->tenant_id ?? 1;
                $tgUser->save();

                if (empty($pasien->telegram_chat_id)) {
                    $pasien->telegram_chat_id = $chatId;
                    $pasien->save();
                }
            }
        }

        // 4) Konfirmasi + main menu (langsung kasih opsi supaya user
        //    baru tidak harus datang ke klinik dulu — nomor + chat_id
        //    sudah tersimpan di no_telps, bisa langsung daftar). Per
        //    instruksi dr. Yoga 2026-09-22.
        $namaPasien = optional($tgUser->fresh('pasien')->pasien)->nama;
        $header = "✅ Nomor HP tersimpan: <b>{$noTelp}</b>\n";
        if ($namaPasien) {
            $header .= "Halo <b>" . e($namaPasien) . "</b>! Akun Telegram Kakak sudah terhubung.\n";
        }

        // Nomor urut disamakan persis dgn WA main menu
        // (prosesMainMenuInquiry di WablasController route by angka).
        // 1=Jadwal, 2=Daftar antrian online, 3=Kode Faskes,
        // 4=Komplain, 5=Chat Admin.
        $body  = "\n<b>Klinik Jati Elok</b>\n";
        $body .= "==================\n";
        $body .= "Selamat Datang di Klinik Jati Elok\n";
        $body .= "Beritahu kami apa yang dapat kami bantu\n\n";
        $body .= "1. Jadwal Pelayanan\n";
        $body .= "2. Daftar Antrian Online\n";
        $body .= "3. Kode Faskes Klinik Jati Elok\n";
        $body .= "4. Keluhan atas pelayanan\n";
        $body .= "5. Chat dengan Admin\n\n";
        $body .= "Balas dengan <b>1, 2, 3, 4, atau 5</b> sesuai dengan informasi di atas";

        // Register WhatsappMainMenu state supaya angka 1-5 selanjutnya
        // ke-route ke prosesMainMenuInquiry via bridge webhook().
        // Pakai firstOrCreate + touch supaya tenant scope + timestamp
        // sesuai dgn WablasController::createWhatsappMainMenu.
        try {
            session()->put('tenant_id', 1);
            \App\Models\WhatsappMainMenu::updateOrCreate(
                ['no_telp' => $noTelp],
                ['updated_at' => now()]
            );
        } catch (\Throwable $e) {
            \Log::warning('TELEGRAM_MAIN_MENU_REGISTER_FAIL', [
                'no_telp' => $noTelp,
                'error'   => $e->getMessage(),
            ]);
        }

        $this->tg->sendMessage($chatId, $header . $body, [
            'parse_mode'   => 'HTML',
            'reply_markup' => ['remove_keyboard' => true],
        ]);
    }

    /**
     * Send message with reply keyboard yang berisi tombol request_contact.
     */
    private function requestPhone(int $chatId, string $text): void
    {
        $this->tg->sendMessage($chatId, $text, [
            'parse_mode'   => 'Markdown',
            'reply_markup' => $this->contactRequestKeyboard(),
        ]);
    }

    private function contactRequestKeyboard(): array
    {
        return [
            'keyboard' => [[
                ['text' => '📱 Kirim Nomor HP', 'request_contact' => true],
            ]],
            'resize_keyboard'   => true,
            'one_time_keyboard' => true,
        ];
    }

    /**
     * Normalize Indonesian phone → format 62xxx (tanpa +, tanpa 0).
     * Sama semantik dgn helper convertToWablasFriendlyFormat + strip +.
     */
    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '' || $digits === null) return '';

        // 08xxx → 62xxx
        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }
        // Kalau sudah 62xxx atau international lain, keep.
        return $digits;
    }

    private function touchUser(int $chatId, array $from): TelegramUser
    {
        return TelegramUser::updateOrCreate(
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
