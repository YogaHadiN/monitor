<?php

namespace App\Http\Controllers;

use App\Services\TelegramClient;

/**
 * Adapter yg mem-bridge WablasController state machine (WA bot flow)
 * ke Telegram bot. Extend WablasController supaya semua method-nya
 * (registrasiAntrianOnline, prosesAntrianOnline, dsb) langsung
 * reusable — cukup override autoReply() supaya kirim via Telegram
 * Bot API instead of Wablas/Watzap.
 *
 * State di-share via table ReservasiOnline + WhatsappBot (keyed by
 * no_telp), sehingga user Telegram punya state machine yg identik
 * dgn user WA. Per instruksi dr. Yoga 2026-09-22.
 */
class TelegramWablasBridge extends WablasController
{
    private TelegramClient $tg;
    private int $chatId;

    /**
     * Inject data dari Telegram webhook — bypass WablasController::__construct
     * karena parent baca dari Input::get() (Wablas payload) yg tidak
     * tersedia di context Telegram.
     */
    public function __construct(TelegramClient $tg, int $chatId, string $noTelp, string $text)
    {
        // Sengaja TIDAK panggil parent::__construct().

        session()->put('tenant_id', 1);
        $this->tenant = \App\Models\Tenant::find(1);

        $this->no_telp        = $noTelp;
        $this->message        = strtolower(trim($text));
        $this->message_type   = 'text';
        $this->message_reply  = '';
        $this->fonnte         = false;
        $this->provider       = 'telegram';
        $this->origin         = 'telegram';
        $this->channel        = 'tg';
        $this->room_id        = null;
        $this->image_url      = null;
        $this->video_url      = null;
        $this->audio_url      = null;
        $this->company_key    = null;
        $this->chats_users_id = null;
        $this->chats_bot_id   = null;
        $this->filename       = null;
        $this->message_id     = null;

        $this->tg     = $tg;
        $this->chatId = $chatId;

        // Load state marker WhatsappBot (dipakai
        // whatsappAntrianOnlineExists() + method lain untuk detect
        // state flow aktif). Sama query dgn WablasController::webhook.
        $this->whatsapp_bot = \App\Models\WhatsappBot::where('no_telp', $this->no_telp)
            ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
            ->first();
    }

    /**
     * Override — kirim balasan via Telegram, bukan Wablas. Convert
     * markdown WA (*bold* _italic_ ~strike~ `code` ```pre```) ke HTML
     * Telegram supaya tidak tampil literal `*`, `_`, dst.
     */
    public function autoReply(string $message): void
    {
        $text = trim($message);
        if ($text === '') return;

        $html = $this->waMarkdownToTelegramHtml($text);

        $opts = ['parse_mode' => 'HTML', 'disable_web_page_preview' => true];

        // Kirim per-bubble kalau text panjang (Telegram limit 4096
        // char/message). Split by newline supaya HTML tag utuh.
        if (mb_strlen($html) <= 4000) {
            $this->tg->sendMessage($this->chatId, $html, $opts);
            return;
        }

        $chunks = $this->chunkByLines($html, 3800);
        foreach ($chunks as $chunk) {
            $this->tg->sendMessage($this->chatId, $chunk, $opts);
        }
    }

    /**
     * Convert WhatsApp markdown → Telegram HTML.
     * WA:   *bold* _italic_ ~strike~ `code` ```pre```
     * TG:   <b>...</b> <i>...</i> <s>...</s> <code>...</code> <pre>...</pre>
     *
     * Semua non-alfanumerik di-HTML-escape dulu (<, >, & jadi
     * &lt; &gt; &amp;) supaya text customer / nama pasien yg
     * mengandung karakter itu tidak bikin parse error di TG.
     */
    private function waMarkdownToTelegramHtml(string $text): string
    {
        // 1. HTML-escape SEBELUM apply markdown → mencegah injeksi HTML
        //    dari isi pesan (mis. nama pasien "<script>").
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 2. Code block (```...```) — process dulu supaya isinya tidak
        //    kena substitusi bold/italic.
        $text = preg_replace('/```([\s\S]+?)```/', '<pre>$1</pre>', $text);

        // 3. Inline code `...`
        $text = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $text);

        // 4. Bold *...*
        $text = preg_replace('/\*([^*\n]+)\*/', '<b>$1</b>', $text);

        // 5. Strikethrough ~...~
        $text = preg_replace('/~([^~\n]+)~/', '<s>$1</s>', $text);

        // 6. Italic _..._ — only when bounded by whitespace/punctuation
        //    supaya snake_case / URL tidak salah convert.
        $text = preg_replace('/(^|[\s.,!?;:(\[])_([^_\n]+)_(?=[\s.,!?;:)\]]|$)/u', '$1<i>$2</i>', $text);

        return $text;
    }

    /**
     * Split text panjang ke chunk ≤ maxLen tanpa memotong di tengah
     * baris — jaga HTML tag tidak split.
     */
    private function chunkByLines(string $text, int $maxLen): array
    {
        $lines  = explode("\n", $text);
        $chunks = [];
        $buf    = '';
        foreach ($lines as $line) {
            $candidate = $buf === '' ? $line : ($buf . "\n" . $line);
            if (mb_strlen($candidate) > $maxLen && $buf !== '') {
                $chunks[] = $buf;
                $buf = $line;
            } else {
                $buf = $candidate;
            }
        }
        if ($buf !== '') $chunks[] = $buf;
        return $chunks;
    }

    public function getChatId(): int
    {
        return $this->chatId;
    }
}
