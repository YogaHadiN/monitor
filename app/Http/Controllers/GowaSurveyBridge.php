<?php

namespace App\Http\Controllers;

/**
 * Bridge WablasController state machine → WA Gowa kezia (dr. Yoga 2026-11-20).
 *
 * Dipakai HANYA untuk balasan survei kepuasan & keluhan lanjutan yang surveinya
 * dikirim lewat Gowa kezia. Balasan bot TIDAK dikirim langsung — dikumpulkan
 * lalu dikembalikan ke atika, yang meng-enqueue ke gowa_outbound_messages
 * (kaidah kirim wajar: mengetik → berhenti → kirim, jeda acak).
 */
class GowaSurveyBridge extends WablasController
{
    /** @var string[] */
    public array $replies = [];

    public function __construct(string $noTelp, string $text)
    {
        // Sengaja TIDAK panggil parent::__construct() (baca payload Wablas).
        session()->put('tenant_id', 1);
        $this->tenant = \App\Models\Tenant::find(1);

        $this->no_telp        = $noTelp;
        $this->message        = strtolower(trim($text));
        $this->message_type   = 'text';
        $this->message_reply  = '';
        $this->fonnte         = false;
        $this->provider       = 'gowa_kezia';
        $this->origin         = 'gowa_kezia';
        $this->channel        = 'wa';
        $this->room_id        = null;
        $this->image_url      = null;
        $this->video_url      = null;
        $this->audio_url      = null;
        $this->company_key    = null;
        $this->chats_users_id = null;
        $this->chats_bot_id   = null;
        $this->filename       = null;
        $this->message_id     = null;

        $this->whatsapp_bot = \App\Models\WhatsappBot::where('no_telp', $this->no_telp)
            ->whereRaw("DATE_ADD( updated_at, interval 1 hour ) > '" . date('Y-m-d H:i:s') . "'")
            ->first();
    }

    public function uploadImage()
    {
        return '';
    }

    public function autoReply(string $message): void
    {
        $text = trim($message);
        \Log::info('GOWA_SURVEY_BRIDGE_REPLY', [
            'no_telp'    => $this->no_telp,
            'inbound'    => mb_substr($this->message, 0, 80),
            'reply_prev' => mb_substr($text, 0, 100),
        ]);
        if ($text !== '') {
            $this->replies[] = $text;
        }
    }
}
