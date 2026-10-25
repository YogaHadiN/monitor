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

        // Detect + upload media (photo/video/voice/audio/document/sticker)
        // ke S3, populate url ke Message + set channel='telegram'.
        // Bridge di-inject dgn s3 path + message_type='image' supaya
        // WablasController::webhook branch photo (mis. kartu asuransi
        // upload di prosesAntrianOnline) jalan.
        $media = $this->extractMedia($msg);
        $mediaS3Path = null;
        if ($media !== null) {
            $mediaS3Path = $this->persistMediaMessage($chatId, $tgUser, $media, $msg);
            // Kalau ada caption, teruskan sbg text. Kalau tidak,
            // text jadi kosong tapi bridge tetap di-fire dgn context
            // image supaya flow state (mis. reservasi_online kartu
            // asuransi step) bisa lanjut.
            $text = trim((string) ($msg['caption'] ?? ''));
        } else {
            $text = trim((string) ($msg['text'] ?? ''));
        }

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
            // Fallback (dr. Yoga 2026-10-18): banyak user (terutama iPhone)
            // tidak melihat tombol reply-keyboard dan MENGETIK nomornya.
            // Terima nomor HP yang diketik (08xx / 62xx / +62xx, 9-15 digit)
            // seperti share-contact. Keamanan: user_id disamakan dgn
            // pengirim supaya lolos cek di handleContact.
            $digits = preg_replace('/\D+/', '', $text);
            if (preg_match('/^(0|62)8\d{7,13}$/', $digits)) {
                $this->handleContact($chatId, [
                    'phone_number' => $digits,
                    'user_id'      => (int) ($msg['from']['id'] ?? 0),
                    'first_name'   => (string) ($msg['from']['first_name'] ?? ''),
                ], $msg['from'] ?? [], $tgUser);
                return;
            }

            $this->requestPhone($chatId,
                "Sebelum lanjut, mohon share nomor HP Kakak dulu ya 🙏\n\n" .
                "Tap tombol *📱 Kirim Nomor HP* di bawah.\n" .
                "_Kalau tombolnya tidak terlihat, tekan ikon keyboard/kotak ⌨️ di sebelah kanan kolom ketik._\n\n" .
                "Atau *ketik langsung nomor HP Kakak*, contoh: 0812xxxxxxxx"
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

        // Kalau ini update dgn foto (mis. kartu asuransi), inject
        // s3 path yg sudah kita upload → bridge->uploadImage()
        // return path itu langsung tanpa double download-upload.
        if (!empty($mediaS3Path) && $media !== null) {
            $bridge->setPreUploadedMedia($media['kind'], $mediaS3Path);
        }

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

    /**
     * Tombol review rujukan di notifikasi RUJUKAN BARU (dr. Yoga 2026-10-25):
     * rjk_ok → approved = 1 (hijau), rjk_no → approved = -1 (merah),
     * rjk_done → info saja. Status bisa diganti (approve ↔ disapprove).
     */
    /**
     * Notif "Complain baru" → tombol ⭐ Kirim link Google Review (dr. Yoga
     * 2026-10-25). Dua langkah: cmp_rv → minta konfirmasi bahwa isi
     * komplain memang KEPUASAN; cmp_rvok → kirim link review ke pengirim
     * (Telegram kalau terhubung, kalau tidak via WA Gowa Kezia).
     */
    private function handleComplainReview(string $callbackId, int $chatId, int $messageId, string $aksi, int $complainId): void
    {
        $reviewer = (int) env('TELEGRAM_RUJUKAN_REVIEWER_CHAT_ID', 5231471981);
        if ($chatId !== $reviewer) {
            $this->tg->answerCallbackQuery($callbackId, 'Tidak diizinkan.', true);
            return;
        }
        $c = \DB::table('complains')->where('id', $complainId)->first(['id', 'no_telp', 'complain', 'review_link_sent_at']);
        if (!$c) {
            $this->tg->answerCallbackQuery($callbackId, 'Komplain tidak ditemukan.', true);
            return;
        }
        $setTombol = function (array $row) use ($chatId, $messageId) {
            if ($messageId > 0) $this->tg->editMessageReplyMarkup($chatId, $messageId, ['inline_keyboard' => [$row]]);
        };
        $sudah = fn () => [['text' => '⭐ Link review terkirim ' . \Carbon\Carbon::parse($c->review_link_sent_at ?? now())->format('d/m H:i'), 'callback_data' => 'cmp_rvdone:' . $complainId]];

        if ($c->review_link_sent_at) {
            $this->tg->answerCallbackQuery($callbackId, 'Link review sudah pernah dikirim.');
            $setTombol($sudah());
            return;
        }
        if ($aksi === 'rvdone') { $this->tg->answerCallbackQuery($callbackId); return; }
        if ($aksi === 'rvx') {
            $this->tg->answerCallbackQuery($callbackId, 'Dibatalkan');
            $setTombol([['text' => '⭐ Kirim link Google Review', 'callback_data' => 'cmp_rv:' . $complainId]]);
            return;
        }
        if ($aksi === 'rv') {
            $this->tg->answerCallbackQuery($callbackId, 'Pastikan isi komplain ini memang KEPUASAN/pujian.', true);
            $setTombol([
                ['text' => '✅ Ya, ini kepuasan — kirim', 'callback_data' => 'cmp_rvok:' . $complainId],
                ['text' => '✖️ Batal', 'callback_data' => 'cmp_rvx:' . $complainId],
            ]);
            return;
        }

        // cmp_rvok → kirim link
        $url  = (string) env('GOOGLE_REVIEW_JATIELOK_URL', 'https://www.klinikjatielok.com/review/klinikjatielok');
        $teks = "Terima kasih banyak atas masukan & doanya kak 🙏😊\n\n"
              . "Kalau berkenan, bantu kami dengan memberi ulasan di Google ya kak ⭐⭐⭐⭐⭐\n{$url}\n\n"
              . "Ulasan kakak sangat berarti untuk Klinik Jati Elok 💚";
        $hp   = preg_replace('/\D+/', '', (string) $c->no_telp);
        $alts = [$hp, str_starts_with($hp, '62') ? '0' . substr($hp, 2) : $hp];
        $tgId = \DB::table('no_telps')->whereIn('no_telp', $alts)->whereNotNull('telegram_chat_id')->where('telegram_chat_id', '<>', '')->value('telegram_chat_id')
             ?: \DB::table('pasiens')->whereIn('no_telp', $alts)->whereNotNull('telegram_chat_id')->where('telegram_chat_id', '<>', '')->value('telegram_chat_id');

        $via = null;
        if ($tgId) {
            $res = $this->tg->sendMessage((int) $tgId, $teks, ['disable_web_page_preview' => false]);
            if ($res['ok'] ?? false) $via = 'telegram';
        }
        if (!$via && $hp !== '') {
            \DB::table('gowa_outbound_messages')->insert([
                'kind' => 'complain_review_link', 'to_phone' => $hp, 'to_label' => 'complain #' . $complainId,
                'body' => $teks, 'device_id' => 'kezia', 'status' => 'pending', 'attempts' => 0, 'max_attempts' => 3,
                'scheduled_at' => now()->addSeconds(30), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $via = 'whatsapp';
        }
        if (!$via) {
            $this->tg->answerCallbackQuery($callbackId, 'Gagal: nomor pengirim tidak valid.', true);
            return;
        }
        \DB::table('complains')->where('id', $complainId)->update(['review_link_sent_at' => now()]);
        $c->review_link_sent_at = now();
        Log::info('COMPLAIN_REVIEW_LINK_SENT', ['complain_id' => $complainId, 'via' => $via]);
        $this->tg->answerCallbackQuery($callbackId, 'Link review terkirim via ' . ($via === 'telegram' ? 'Telegram' : 'WhatsApp') . ' ✅');
        $setTombol($sudah());
    }

    private function handleRujukanApprove(string $callbackId, int $chatId, int $messageId, string $aksi, int $rujukanId): void
    {
        $reviewer = (int) env('TELEGRAM_RUJUKAN_REVIEWER_CHAT_ID', 5231471981);
        if ($chatId !== $reviewer) {
            $this->tg->answerCallbackQuery($callbackId, 'Tidak diizinkan.', true);
            Log::warning('RUJUKAN_APPROVE_DITOLAK', ['chat_id' => $chatId, 'rujukan_id' => $rujukanId]);
            return;
        }

        $r = \DB::table('rujukans')->where('id', $rujukanId)->first(['id', 'approved', 'approved_at']);
        if (!$r) {
            $this->tg->answerCallbackQuery($callbackId, 'Rujukan tidak ditemukan.', true);
            return;
        }

        $status = (int) $r->approved;
        $jamAt  = $r->approved_at;
        if ($aksi === 'done') {
            $label = $status === 1 ? 'Sudah di-approve' : ($status === -1 ? 'Sudah di-disapprove' : 'Belum di-review');
            $this->tg->answerCallbackQuery($callbackId, $label);
        } else {
            $status = $aksi === 'ok' ? 1 : -1;
            $jamAt  = now();
            \DB::table('rujukans')->where('id', $rujukanId)->update([
                'approved'            => $status,
                'approved_at'         => $jamAt,
                'approved_by_chat_id' => $chatId,
            ]);
            Log::info('RUJUKAN_REVIEW', ['rujukan_id' => $rujukanId, 'status' => $status, 'chat_id' => $chatId]);
            $this->tg->answerCallbackQuery($callbackId, $status === 1 ? 'Rujukan disetujui ✅' : 'Rujukan ditolak ❌');
        }

        if ($messageId > 0) {
            $jam = $jamAt ? \Carbon\Carbon::parse($jamAt)->format('d/m H:i') : null;
            $this->tg->editMessageReplyMarkup($chatId, $messageId, ['inline_keyboard' => [[
                $status === 1
                    ? ['text' => '✅ APPROVED' . ($jam ? " {$jam}" : ''), 'callback_data' => 'rjk_done:' . $rujukanId]
                    : ['text' => '✅ Approve', 'callback_data' => 'rjk_ok:' . $rujukanId],
                $status === -1
                    ? ['text' => '❌ DISAPPROVED' . ($jam ? " {$jam}" : ''), 'callback_data' => 'rjk_done:' . $rujukanId]
                    : ['text' => '❌ Disapprove', 'callback_data' => 'rjk_no:' . $rujukanId],
            ]]]);
        }
    }

    private function handleCallbackQuery(array $cbq): void
    {
        $callbackId = (string) ($cbq['id'] ?? '');
        if ($callbackId === '') return;

        $chatId = (int) ($cbq['message']['chat']['id'] ?? 0);
        $data   = (string) ($cbq['data'] ?? '');

        // Approve rujukan dari notifikasi RUJUKAN BARU (dikirim kezia,
        // RujukanTelegramNotifier). Hanya chat reviewer yg boleh.
        if (preg_match('/^cmp_(rv|rvok|rvx|rvdone):(\d+)$/', $data, $m)) {
            $this->handleComplainReview($callbackId, $chatId, (int) ($cbq['message']['message_id'] ?? 0), $m[1], (int) $m[2]);
            return;
        }

        if (preg_match('/^rjk_(ok|no|done):(\d+)$/', $data, $m)) {
            $this->handleRujukanApprove($callbackId, $chatId, (int) ($cbq['message']['message_id'] ?? 0), $m[1], (int) $m[2]);
            return;
        }

        $this->tg->answerCallbackQuery($callbackId);

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

        // Kalau sudah pernah share nomor → langsung tampilkan main menu
        // (biar user tidak perlu ketik "daftar" — bisa pilih via angka).
        if (!empty($tgUser->no_telp)) {
            $this->sendMainMenu($chatId, $tgUser->no_telp, "Halo kak, senang jumpa lagi 👋\n");
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
        //    baru tidak harus datang ke klinik dulu). Per instruksi
        //    dr. Yoga 2026-09-22.
        $namaPasien = optional($tgUser->fresh('pasien')->pasien)->nama;
        $header = "✅ Nomor HP tersimpan: <b>{$noTelp}</b>\n";
        if ($namaPasien) {
            $header .= "Halo <b>" . e($namaPasien) . "</b>! Akun Telegram Kakak sudah terhubung.\n";
        }

        $this->sendMainMenu($chatId, $noTelp, $header, true);
    }

    /**
     * Send main menu block yg persis WA-style. Register row
     * WhatsappMainMenu supaya angka 1-5 selanjutnya ke-route ke
     * WablasController::prosesMainMenuInquiry via bridge webhook().
     *
     * Nomor urut sama persis WA:
     *   1=Jadwal, 2=Daftar antrian online, 3=Kode Faskes,
     *   4=Komplain, 5=Chat Admin.
     */
    private function sendMainMenu(int $chatId, string $noTelp, string $header = '', bool $removeKeyboard = false): void
    {
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

        $opts = ['parse_mode' => 'HTML'];
        if ($removeKeyboard) {
            $opts['reply_markup'] = ['remove_keyboard' => true];
        }

        $this->tg->sendMessage($chatId, $header . $body, $opts);
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

    /**
     * Detect media dari Telegram update. Return array
     * ['kind' => photo|video|voice|audio|document|sticker,
     *  'file_id' => ..., 'mime' => ?, 'file_name' => ?]
     * atau null kalau text-only / contact.
     */
    private function extractMedia(array $msg): ?array
    {
        if (isset($msg['photo'])) {
            // photo[] = array of sizes; largest = last.
            $largest = end($msg['photo']);
            return [
                'kind'      => 'photo',
                'file_id'   => (string) ($largest['file_id'] ?? ''),
                'mime'      => 'image/jpeg',
                'file_name' => null,
            ];
        }
        foreach (['video','voice','audio','document','sticker','video_note','animation'] as $kind) {
            if (isset($msg[$kind])) {
                return [
                    'kind'      => $kind,
                    'file_id'   => (string) ($msg[$kind]['file_id'] ?? ''),
                    'mime'      => $msg[$kind]['mime_type'] ?? null,
                    'file_name' => $msg[$kind]['file_name'] ?? null,
                ];
            }
        }
        return null;
    }

    /**
     * Download file dari Telegram, upload ke S3, log ke Message dgn
     * channel='telegram' + url yg tepat (image_url/video_url/audio_url).
     * Return s3 path yg baru di-upload (buat inject ke bridge), atau
     * null kalau gagal.
     */
    private function persistMediaMessage(int $chatId, TelegramUser $tgUser, array $media, array $msg): ?string
    {
        if (empty($media['file_id'])) return null;

        $downloadUrl = $this->tg->fileDownloadUrl($media['file_id']);
        if (empty($downloadUrl)) {
            Log::warning('TELEGRAM_MEDIA_DOWNLOAD_URL_FAILED', [
                'chat_id' => $chatId,
                'kind'    => $media['kind'],
                'file_id' => $media['file_id'],
            ]);
            return null;
        }

        try {
            $contents = @file_get_contents($downloadUrl);
            if ($contents === false) {
                Log::warning('TELEGRAM_MEDIA_FETCH_FAILED', [
                    'chat_id' => $chatId,
                    'url'     => $downloadUrl,
                ]);
                return null;
            }

            $ext = pathinfo(parse_url($downloadUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'bin';
            $baseName = 'tg_' . $chatId . '_' . time() . '_' . substr(md5($media['file_id']), 0, 8) . '.' . $ext;
            $s3Path = 'image/telegram/' . $baseName;
            \Storage::disk('s3')->put($s3Path, $contents);
        } catch (\Throwable $e) {
            Log::error('TELEGRAM_MEDIA_S3_UPLOAD_FAILED', [
                'chat_id' => $chatId,
                'kind'    => $media['kind'],
                'error'   => $e->getMessage(),
            ]);
            return null;
        }

        // messages.image_url disimpan sbg FULL URL (bukan relative
        // path S3) supaya <img src="{{ image_url }}"> di CRM inbox
        // bisa langsung load. Bedakan dgn reservasi_online.kartu_asuransi_image
        // yg pakai relative path (di-generate ulang via Storage::disk).
        $publicUrl = \Storage::disk('s3')->url($s3Path);
        $imageUrl = null;
        $videoUrl = null;
        $audioUrl = null;
        switch ($media['kind']) {
            case 'photo':
            case 'sticker':
            case 'animation':
                $imageUrl = $publicUrl;
                break;
            case 'video':
            case 'video_note':
                $videoUrl = $publicUrl;
                break;
            case 'voice':
            case 'audio':
                $audioUrl = $publicUrl;
                break;
            case 'document':
                $imageUrl = $publicUrl;
                break;
        }

        $caption = trim((string) ($msg['caption'] ?? ''));

        try {
            session()->put('tenant_id', 1);

            // Deteksi state chat admin: kalau user sudah dalam WhatsappBot
            // svc=12 (chat dgn admin), flag chat_admin=1 supaya media
            // muncul di panel /messages/{no_telp} yg filter chat_admin=1.
            // Kalau flag=0, media tersembunyi dari inbox admin.
            $noTelp = $tgUser->no_telp ?: (string) $chatId;
            $dalamChatAdmin = !empty($noTelp) && \App\Models\WhatsappBot::where('no_telp', $noTelp)
                ->where('whatsapp_bot_service_id', 12)
                ->whereRaw("DATE_ADD(updated_at, interval 1 hour) > '" . date('Y-m-d H:i:s') . "'")
                ->exists();

            \App\Models\Message::create([
                'no_telp'          => $noTelp,
                'message'          => $caption !== '' ? $caption : '[' . $media['kind'] . ']',
                'tanggal'          => date('Y-m-d H:i:s'),
                'image_url'        => $imageUrl,
                'video_url'        => $videoUrl,
                'audio_url'        => $audioUrl,
                'sending'          => 0,
                'sudah_dibalas'    => $dalamChatAdmin ? 0 : 1,
                'tenant_id'        => 1,
                'touched'          => 0,
                'chat_admin'       => $dalamChatAdmin ? 1 : 0,
                'chat_sunat'       => 0,
                'channel'          => 'telegram',
                'telegram_chat_id' => $chatId,
            ]);
        } catch (\Throwable $e) {
            Log::error('TELEGRAM_MEDIA_MESSAGE_LOG_FAILED', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);
        }

        return $s3Path;
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
