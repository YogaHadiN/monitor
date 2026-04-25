<?php

namespace App\Services\Bot;

use App\Models\BotSession;
use App\Services\BarantumReplyService;
use Illuminate\Support\Facades\Log;

class BotEngine
{
    public function __construct(
        private IntentDetector $intents,
        private SunatBotFlow $sunatFlow,
        private BarantumReplyService $barantum,
    ) {}

    public function handle(string $noTelp, string $userMessage, array $ctx): bool
    {
        if ( $noTelp === '' || $userMessage === '' ) {
            return false;
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
            $this->runFlow($session, null, $ctx);
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
        $text     = (string) ($reply['text'] ?? '');
        $imageUrl = (string) ($reply['image_url'] ?? '');

        $payload = array_merge($ctx, [
            'image_url'    => $imageUrl,
            'message_type' => $imageUrl !== '' ? 'image' : 'text',
            'filename'     => '',
        ]);

        try {
            $res = $this->barantum->replyText($payload, $text);
            if ( !($res['ok'] ?? false) ) {
                Log::warning('BOT_ENGINE_SEND_FAILED', ['reason' => $res['reason'] ?? 'unknown', 'payload' => $payload]);
            }
        } catch (\Throwable $e) {
            Log::error('BOT_ENGINE_SEND_EXCEPTION', ['err' => $e->getMessage()]);
        }
    }
}
