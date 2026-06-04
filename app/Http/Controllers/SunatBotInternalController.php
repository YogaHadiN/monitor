<?php

namespace App\Http\Controllers;

use App\Models\BotSession;
use App\Models\Tenant;
use App\Services\SunatBot\SunatBotEngine;
use App\Services\SunatBot\SunatBotReplyDispatcher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoint internal dipanggil atika ketika tombol "Tandai chat sunat"
 * ditekan — sistem proses pesan terakhir customer lewat sunat bot
 * engine seakan customer baru saja kirim. Bot reply otomatis ke
 * customer via Watzap (via autoReply yang sudah ada di WablasController).
 *
 * Signature HMAC sudah diverifikasi middleware verify.push.signature
 * (re-use shared PUSH_TRIGGER_SECRET di kedua app).
 */
class SunatBotInternalController extends Controller
{
    public function __construct(
        private SunatBotEngine $engine,
        private SunatBotReplyDispatcher $dispatcher,
    ) {}

    public function processMessage(Request $request): JsonResponse
    {
        $data = (array) $request->json()->all();

        $validator = Validator::make($data, [
            'no_telp' => 'required|string|max:32',
            'message' => 'required|string|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid payload',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $phone   = (string) $data['no_telp'];
        $message = (string) $data['message'];

        // Admin sengaja mengambil alih lewat tombol "Tandai chat sunat".
        // Pastikan ada BotSession aktif sebelum panggil engine — kalau
        // tidak ada dan pesan terakhir tidak mengandung trigger keyword,
        // engine bakal return kosong dan bot diam saja. Kalau session
        // lama is_complete, reset supaya flow mulai dari trigger_sunat.
        $session = BotSession::where('no_telp', $phone)->first();
        if ($session && $session->is_complete) {
            $session->delete();
            $session = null;
        }
        if ($session === null) {
            BotSession::create([
                'no_telp'          => $phone,
                'collected_data'   => [],
                'last_activity_at' => Carbon::now(),
            ]);
            // Engine deteksi session baru + pesan apapun → tetap masuk
            // classifyAndRespond, AI menjawab sesuai context. Untuk kasus
            // pesan terakhir TIDAK mengandung kata sunat sama sekali,
            // override message ke "sunat" supaya engine pasti masuk
            // trigger_sunat dan mulai flow dari awal.
            if (!$this->messageHasSunatTrigger($message)) {
                $message = 'sunat';
            }
        }

        try {
            $result = $this->engine->handle($phone, $message);
            $replies = (array) ($result['replies'] ?? []);

            if (empty($result['handled']) || $replies === []) {
                Log::info('SUNATBOT_INTERNAL_NO_REPLY', [
                    'phone'   => $phone,
                    'handled' => $result['handled'] ?? false,
                ]);
                return response()->json([
                    'ok'      => true,
                    'handled' => (bool) ($result['handled'] ?? false),
                    'replies' => 0,
                ]);
            }

            $tenant          = Tenant::find(1);
            $imageBotEnabled = $tenant && (bool) ($tenant->image_bot_enabled ?? false);
            $this->dispatcher->dispatch($phone, $replies, $imageBotEnabled, false);

            Log::info('SUNATBOT_INTERNAL_PROCESSED', [
                'phone'   => $phone,
                'handled' => $result['handled'] ?? false,
                'replies' => count($replies),
            ]);

            return response()->json([
                'ok'      => true,
                'handled' => true,
                'replies' => count($replies),
            ]);
        } catch (\Throwable $e) {
            Log::error('SUNATBOT_INTERNAL_EXCEPTION', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
            ]);
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function messageHasSunatTrigger(string $message): bool
    {
        $lower = mb_strtolower($message);
        return str_contains($lower, 'sunat') || str_contains($lower, 'khitan');
    }
}
