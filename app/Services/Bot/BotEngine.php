<?php

namespace App\Services\Bot;

use App\Models\BotSession;
use App\Models\Message;
use App\Services\WatzapService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BotEngine
{
    private const BUBBLE_DELAY_SECONDS = 3;

    /**
     * Numbers that trigger an automatic session reset whenever they send
     * a sunat/khitan keyword. Lets the operator re-test the greeting flow
     * without manually deleting bot sessions in atika.
     */
    private const AUTO_RESTART_NUMBERS = ['6281381912803'];

    private int $sentCount = 0;

    public function __construct(
        private IntentDetector $intents,
        private SunatBotFlow $sunatFlow,
        private WatzapService $watzap,
    ) {}

    public function handle(string $noTelp, string $userMessage, array $ctx): bool
    {
        if ( $noTelp === '' || $userMessage === '' ) {
            return false;
        }

        // Bubble delay membuat webhook lama dijalankan; pastikan PHP tidak di-kill
        // dan tetap proses meski Barantum sudah menutup koneksi inbound.
        @set_time_limit(0);
        @ignore_user_abort(true);
        $this->sentCount = 0;

        $ctx['no_telp'] = $noTelp;

        if ( in_array($noTelp, self::AUTO_RESTART_NUMBERS, true)
            && $this->intents->detect($userMessage) === 'sunat'
        ) {
            BotSession::where('no_telp', $noTelp)
                ->where('escalated_to_human', false)
                ->where('current_step', '!=', 'done')
                ->update(['current_step' => 'done', 'last_activity_at' => now()]);
        }

        $session = BotSession::activeFor($noTelp);
        $msgLower = strtolower(trim($userMessage));

        if ( !$session ) {
            $intent = $this->intents->detect($userMessage);
            if ( $intent !== 'sunat' ) {
                return false;
            }
            $session = BotSession::create([
                'no_telp'          => $noTelp,
                'flow_type'        => 'sunat',
                'current_step'     => 'greeting',
                'collected_data'   => [],
                'last_activity_at' => now(),
            ]);
            $this->runFlow($session, $userMessage, $ctx);
            return true;
        }

        if ( in_array($msgLower, ['akhiri', 'ahiri', 'selesai'], true) ) {
            $session->current_step = 'done';
            $session->last_activity_at = now();
            $session->save();
            $this->send($ctx, ['text' => 'Baik kak, chat diakhiri. Terima kasih 🙏 Ketik "sunat" lagi kalau butuh info.']);
            return true;
        }

        if ( in_array($msgLower, ['cs', 'admin', 'operator', 'manusia'], true) ) {
            $session->escalated_to_human = true;
            $session->last_activity_at = now();
            $session->save();
            $this->send($ctx, ['text' => 'Baik kak, mohon ditunggu ya, admin kami akan membalas segera 🙏']);
            return true;
        }

        $reactive = $this->sunatFlow->handleReactive($session, $userMessage);
        if ( $reactive !== null ) {
            foreach ($reactive as $reply) {
                $this->send($ctx, $reply);
            }
            $session->last_activity_at = now();
            $session->save();
            return true;
        }

        $this->runFlow($session, $userMessage, $ctx);
        return true;
    }

    private function runFlow(BotSession $session, ?string $userMessage, array $ctx): void
    {
        $guard = 0;
        while ($guard++ < 20) {
            $result = $this->sunatFlow->handle($session, $userMessage);
            foreach ($result['replies'] as $reply) {
                $this->send($ctx, $reply);
            }
            $session->current_step = $result['next_step'];
            $session->last_activity_at = now();
            $session->save();

            if ( !$result['auto_continue'] ) {
                break;
            }
            $userMessage = null;
            if ( $session->current_step === 'done' ) break;
        }
    }

    private function send(array $ctx, array $reply): void
    {
        if ( $this->sentCount > 0 && self::BUBBLE_DELAY_SECONDS > 0 ) {
            sleep(self::BUBBLE_DELAY_SECONDS);
        }
        $this->sentCount++;

        $text         = (string) ($reply['text'] ?? '');
        $imageUrl     = (string) ($reply['image_url'] ?? '');
        $noTelp       = (string) ($ctx['no_telp'] ?? '');
        $imageEnabled = (bool) ($ctx['image_bot_enabled'] ?? false);

        if ( !$imageEnabled ) {
            $imageUrl = '';
        }

        if ( $text === '' && $imageUrl === '' ) {
            return;
        }

        $sent = false;
        try {
            $res = $imageUrl !== ''
                ? $this->watzap->sendImage($noTelp, $imageUrl, $text)
                : $this->watzap->sendText($noTelp, $text);
            $sent = (bool) ($res['ok'] ?? false);
            if ( !$sent ) {
                Log::warning('BOT_ENGINE_SEND_FAILED', [
                    'reason'  => $res['reason'] ?? 'unknown',
                    'no_telp' => $noTelp,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('BOT_ENGINE_SEND_EXCEPTION', ['err' => $e->getMessage()]);
        }

        if ( $sent && $noTelp !== '' ) {
            $this->logOutgoing($noTelp, $text, $imageUrl);
        }
    }

    private function logOutgoing(string $noTelp, string $text, string $imageUrl): void
    {
        try {
            $row = [
                'no_telp'        => $noTelp,
                'message'        => $text,
                'tanggal'        => date('Y-m-d H:i:s'),
                'sending'        => 1,
                'touched'        => 1,
                'sudah_dibalas'  => 1,
                'sudah_diproses' => 1,
                'tenant_id'      => 1,
            ];
            if ( $imageUrl !== '' && Schema::hasColumn('messages', 'image_url') ) {
                $row['image_url'] = $imageUrl;
            }
            if ( Schema::hasColumn('messages', 'flagged_intent') ) {
                $row['flagged_intent'] = 'sunat_bot_reply';
            }
            Message::create($row);
        } catch (\Throwable $e) {
            Log::warning('BOT_ENGINE_LOG_OUTGOING_FAIL', ['err' => $e->getMessage()]);
        }
    }
}
