<?php

namespace App\Http\Controllers;

use App\Models\BotSession;
use App\Services\Bot\IntentDetector;
use App\Services\Bot\SunatBotFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SunatBotTesterController extends Controller
{
    private const HISTORY_KEY = 'sunat_test_history';
    private const FLOW_GUARD  = 20;

    public function show(Request $request)
    {
        return view('sunat_bot_test.chat', [
            'no_telp' => $this->testNoTelp(),
            'history' => session()->get(self::HISTORY_KEY, []),
            'session' => BotSession::activeFor($this->testNoTelp()),
        ]);
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $msg      = trim($request->message);
        $msgLower = mb_strtolower($msg);
        $noTelp   = $this->testNoTelp();

        $history   = session()->get(self::HISTORY_KEY, []);
        $history[] = ['role' => 'user', 'text' => $msg, 'image' => null];

        $session = BotSession::activeFor($noTelp);
        $replies = [];

        if ($session === null) {
            $intent = app(IntentDetector::class)->detect($msg);
            if ($intent !== 'sunat') {
                $history[] = [
                    'role'  => 'system',
                    'text'  => '(belum ada session aktif. Kirim pesan dengan kata "sunat" atau "khitan" untuk memulai)',
                    'image' => null,
                ];
                session()->put(self::HISTORY_KEY, $history);
                return redirect('/sunat-bot/test');
            }

            $session = BotSession::create([
                'no_telp'          => $noTelp,
                'flow_type'        => 'sunat',
                'current_step'     => 'greeting',
                'collected_data'   => [],
                'last_activity_at' => now(),
            ]);
            $replies = $this->runFlow($session, $msg);
        } elseif (in_array($msgLower, ['akhiri', 'ahiri', 'selesai'], true)) {
            $session->current_step     = 'done';
            $session->last_activity_at = now();
            $session->save();
            $replies[] = ['text' => 'Baik kak, chat diakhiri. Terima kasih 🙏 Ketik "sunat" lagi kalau butuh info.'];
        } elseif (in_array($msgLower, ['cs', 'admin', 'operator', 'manusia'], true)) {
            $session->escalated_to_human = true;
            $session->last_activity_at   = now();
            $session->save();
            $replies[] = ['text' => 'Baik kak, mohon ditunggu ya, admin kami akan membalas segera 🙏'];
        } else {
            $reactive = app(SunatBotFlow::class)->handleReactive($session, $msg);
            if ($reactive !== null) {
                $replies = $reactive;
                $session->last_activity_at = now();
                $session->save();
            } else {
                $replies = $this->runFlow($session, $msg);
            }
        }

        foreach ($replies as $r) {
            $history[] = [
                'role'  => 'bot',
                'text'  => (string) ($r['text'] ?? ''),
                'image' => (string) ($r['image_url'] ?? ''),
            ];
        }

        session()->put(self::HISTORY_KEY, $history);
        return redirect('/sunat-bot/test');
    }

    public function reset(Request $request)
    {
        $noTelp = $this->testNoTelp();
        BotSession::where('no_telp', $noTelp)->delete();
        Cache::forget('sunat_qnas_index_v1');
        session()->forget(self::HISTORY_KEY);
        return redirect('/sunat-bot/test');
    }

    private function runFlow(BotSession $session, ?string $userMessage): array
    {
        $flow    = app(SunatBotFlow::class);
        $replies = [];
        $guard   = 0;

        while ($guard++ < self::FLOW_GUARD) {
            $result  = $flow->handle($session, $userMessage);
            $replies = array_merge($replies, $result['replies']);

            $session->current_step     = $result['next_step'];
            $session->last_activity_at = now();
            $session->save();

            if (!$result['auto_continue']) break;
            $userMessage = null;
            if ($session->current_step === 'done') break;
        }

        return $replies;
    }

    private function testNoTelp(): string
    {
        return 'TEST_' . session()->getId();
    }
}
