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
    }

    /**
     * Override — kirim balasan via Telegram, bukan Wablas. Skip footer
     * antrian dan side effects lain untuk sementara.
     */
    public function autoReply(string $message): void
    {
        $text = trim($message);
        if ($text === '') return;

        // Kirim per-bubble kalau text sudah panjang (Telegram limit
        // 4096 char/message).
        if (mb_strlen($text) <= 4000) {
            $this->tg->sendMessage($this->chatId, $text);
            return;
        }

        // Split by paragraph, batch, send.
        $chunks = mb_str_split($text, 3800);
        foreach ($chunks as $chunk) {
            $this->tg->sendMessage($this->chatId, $chunk);
        }
    }

    public function getChatId(): int
    {
        return $this->chatId;
    }
}
