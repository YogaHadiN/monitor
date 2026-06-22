<?php

namespace App\Services\SunatBot;

use App\Models\BotIntent;
use App\Models\BotSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tool-calling LLM agent — pengganti IntentClassifier + AI gate
 * sunat-relevance untuk fase free-form Q&A SunatBot. Booking flow +
 * harga flow state machine TETAP dipakai (lihat SunatBotEngine);
 * agent ini hanya jadi router/composer untuk percakapan informasi
 * (FAQ, promo, pengantar harga, redirect non-sunat).
 *
 * Kontrak reply: array {handled, replies: [{text, media}], signal}
 *   - signal: 'enter_harga' | 'enter_booking' | 'escalate' | null
 * SunatBotEngine yang interpret signal dan jalankan state machine
 * masing-masing. Agent tidak side-effect (no DB write, no notif).
 *
 * Conversation history disimpan rolling 6 turn di kolom
 * bot_sessions.agent_history (JSON array {role, content, tool_calls?,
 * tool_call_id?}). Booking/harga flow tidak persist via history —
 * mereka pakai expecting_field + collected_data seperti biasa.
 */
class SunatBotAgent
{
    private const MAX_TOOL_ITERATIONS = 4;
    private const HISTORY_MAX_TURNS   = 6;
    private const MODEL               = 'gpt-4o-mini';
    private const HTTP_TIMEOUT        = 20;

    private ?string $contextPhone = null;

    public function setContext(?string $noTelp): void
    {
        $this->contextPhone = $noTelp !== '' ? $noTelp : null;
    }

    /**
     * Proses pesan customer melalui agent loop.
     *
     * @return array{
     *   handled: bool,
     *   replies: array<array{text:string, media:?string}>,
     *   signal: ?string,
     *   escalate: bool,
     * }
     */
    public function reply(BotSession $session, string $userMessage): array
    {
        $this->setContext($session->no_telp ?? null);

        $apiKey = (string) env('OPENAI_API_KEY', '');
        if ($apiKey === '') {
            Log::warning('SUNAT_BOT_AGENT_NO_KEY', ['phone' => $session->no_telp ?? null]);
            return $this->fallbackReply();
        }

        $systemPrompt = $this->buildSystemPrompt();
        $history      = $this->loadHistory($session);

        // Append user turn — disimpan ke history setelah loop selesai.
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history,
            [['role' => 'user', 'content' => $userMessage]]
        );

        $tools = $this->toolDefinitions();

        $replies   = [];
        $signal    = null;
        $escalate  = false;
        $iter      = 0;

        while ($iter < self::MAX_TOOL_ITERATIONS) {
            $iter++;
            $result = $this->callOpenAI($apiKey, $messages, $tools, $iter);
            if ($result === null) {
                return $this->fallbackReply();
            }

            $assistantMsg = $result['message'] ?? [];
            $toolCalls    = $assistantMsg['tool_calls'] ?? [];
            $textContent  = trim((string) ($assistantMsg['content'] ?? ''));

            // Simpan assistant message ke loop messages (untuk konteks
            // call berikutnya kalau ada tool calls).
            $messages[] = $assistantMsg;

            if (empty($toolCalls)) {
                // Tidak ada tool call → agent jawab langsung dalam text.
                if ($textContent !== '') {
                    $replies = array_merge($replies, $this->splitToTextBubbles($textContent));
                }
                break;
            }

            // Eksekusi setiap tool call.
            foreach ($toolCalls as $call) {
                $toolName = (string) ($call['function']['name'] ?? '');
                $argsRaw  = (string) ($call['function']['arguments'] ?? '{}');
                $args     = json_decode($argsRaw, true) ?: [];
                $callId   = (string) ($call['id'] ?? '');

                [$toolResult, $sideEffect] = $this->executeTool($toolName, $args, $session);

                if (isset($sideEffect['replies'])) {
                    $replies = array_merge($replies, $sideEffect['replies']);
                }
                if (isset($sideEffect['signal']) && $sideEffect['signal'] !== null) {
                    $signal = $sideEffect['signal'];
                }
                if (!empty($sideEffect['escalate'])) {
                    $escalate = true;
                }

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $callId,
                    'content'      => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                ];

                // Tools yang trigger flow / escalate → exit loop, biar
                // engine ambil alih state machine. Replies dari tool
                // ini sudah cukup sebagai "lead-in".
                if ($signal !== null || $escalate) {
                    break 2;
                }
            }
        }

        // Persist history (system + tool roles di-strip; cuma user +
        // assistant final yang relevan untuk konteks turn berikutnya).
        $this->saveHistory($session, $history, $userMessage, $replies);

        return [
            'handled'  => $replies !== [] || $signal !== null,
            'replies'  => $replies,
            'signal'   => $signal,
            'escalate' => $escalate,
        ];
    }

    private function buildSystemPrompt(): string
    {
        $klinik = (string) config('sunatbot.alamat_klinik', '');
        $maps   = (string) config('sunatbot.link_maps', '');

        return <<<PROMPT
Kamu adalah AGENT WhatsApp untuk klinik sunat anak SunatBoy (Klinik Jati Elok, Tangerang).

PERAN:
- Jawab pertanyaan calon klien tentang sunat: lokasi, metode, jarum/bius, harga, durasi sembuh, fasilitas, promo, testimoni, dst.
- Pakai TOOL `lookup_knowledge` untuk cari intent yang cocok dari knowledge base, lalu TOOL `get_intent_response` untuk render template jawaban resmi.
- JANGAN improvisasi jawaban faktual (harga, metode, alamat) — selalu pakai template dari knowledge base via tool.

ATURAN PENTING SETELAH PANGGIL TOOL:
- Setelah `get_intent_response` SUKSES (bubble_count > 0): JANGAN tulis text apa pun lagi. Cukup balas string kosong. Bubble sudah dikirim ke customer otomatis dari tool result; kalau kamu ulang isinya akan dobel.
- Setelah `redirect_ke_admin`, `trigger_harga_flow`, atau `trigger_booking_flow`: JANGAN tulis text apa pun lagi. Balas string kosong.
- Boleh tulis text HANYA kalau lookup_knowledge tidak ketemu apa-apa DAN kamu mau probing pertanyaan lebih jelas — itu pun maks 1 kalimat singkat.

ROUTING KHUSUS (panggil tool, bukan jawab text):
- Client minta penawaran HARGA / paket / quote → tool `trigger_harga_flow` (engine akan minta nama/usia/BB step-by-step).
- Client mau BOOKING / daftar / jadwalkan → tool `trigger_booking_flow` (engine akan minta tanggal/jam).
- Pesan jelas BUKAN tentang sunat (mis. tanya gigi, infus, halo random) → tool `redirect_ke_admin` dengan alasan singkat.

GAYA (kalau memang harus tulis text):
- Bahasa Indonesia santai-sopan, panggil "kak". Boleh emoji secukupnya (🙏 🎉 ✓).
- Singkat. Maks 1-2 bubble.

KONTEKS KLINIK:
$klinik
Google Maps: $maps
PROMPT;
    }

    private function toolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'lookup_knowledge',
                    'description' => 'Cari intent yang relevan di knowledge base SunatBoy berdasarkan query bahasa natural. Return list of {slug, keywords, contoh}. Pakai ini DULU sebelum get_intent_response.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'kata kunci atau ringkasan topik yang dicari, mis. "promo paket grup", "lokasi klinik", "metode khitan"'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'get_intent_response',
                    'description' => 'Ambil jawaban template (sudah split jadi bubble + media) untuk intent slug tertentu. Reply bubble dikirim ke customer apa adanya — agent TIDAK perlu echo isinya.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'slug' => ['type' => 'string', 'description' => 'intent slug, mis. "pertanyaan_lokasi", "promo_paket_grup"'],
                        ],
                        'required' => ['slug'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'redirect_ke_admin',
                    'description' => 'Panggil ini saat pesan customer JELAS-JELAS bukan tentang sunat (mis. tanya gigi, daftar poli lain, halo random tanpa konteks). Bot akan kirim pesan redirect + nomor admin. Throttled 1x/hari per nomor (kalau sudah dikirim hari ini, jadi no-op).',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => ['type' => 'string', 'description' => 'alasan singkat (untuk log), mis. "tanya layanan gigi"'],
                        ],
                        'required' => ['reason'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'trigger_harga_flow',
                    'description' => 'Customer minta quote harga / paket / penawaran. Engine akan masuk ke harga flow (capture nama, domisili, usia/BB, dst step-by-step). Setelah panggil tool ini, JANGAN tambah text lain — engine yang lanjutkan.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'trigger_booking_flow',
                    'description' => 'Customer mau booking / daftar jadwal sunat. Engine akan masuk ke booking flow (tanggal, jam, nama anak, dst). Setelah panggil tool ini, JANGAN tambah text lain — engine yang lanjutkan.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{0:array, 1:array} tuple [tool_result_for_llm, side_effect_for_engine]
     */
    private function executeTool(string $name, array $args, BotSession $session): array
    {
        switch ($name) {
            case 'lookup_knowledge':
                return [$this->toolLookupKnowledge((string) ($args['query'] ?? '')), []];

            case 'get_intent_response':
                [$summary, $bubbles] = $this->toolGetIntentResponse((string) ($args['slug'] ?? ''));
                return [$summary, ['replies' => $bubbles]];

            case 'redirect_ke_admin':
                $reason = (string) ($args['reason'] ?? '');
                [$summary, $bubbles] = $this->toolRedirectKeAdmin($session, $reason);
                return [$summary, ['replies' => $bubbles, 'signal' => 'redirected']];

            case 'trigger_harga_flow':
                return [['ok' => true, 'note' => 'engine akan ambil alih flow harga'], ['signal' => 'enter_harga']];

            case 'trigger_booking_flow':
                return [['ok' => true, 'note' => 'engine akan ambil alih flow booking'], ['signal' => 'enter_booking']];

            default:
                return [['ok' => false, 'error' => "unknown tool: $name"], []];
        }
    }

    // ----- TOOLS -----------------------------------------------------

    private function toolLookupKnowledge(string $query): array
    {
        $q = trim(mb_strtolower($query));
        if ($q === '') return ['matches' => []];

        // Ambil semua intent active. Filter manual di PHP (set kecil ~25
        // row, lebih murah dari LIKE query). Match score: kata di query
        // ada di keywords atau pertanyaan_contoh.
        $intents = BotIntent::where('active', true)
            ->whereNotNull('keywords')
            ->where('keywords', '!=', '')
            ->whereNotIn('intent', ['trigger_sunat', 'fallback_unknown'])
            ->orderBy('urutan')
            ->get(['intent', 'keywords', 'pertanyaan_contoh']);

        $terms = array_values(array_filter(preg_split('/\s+/u', $q) ?: [], fn ($t) => mb_strlen($t) >= 3));

        $scored = [];
        foreach ($intents as $row) {
            $haystack = mb_strtolower(($row->keywords ?? '') . ' | ' . ($row->pertanyaan_contoh ?? ''));
            $score = 0;
            foreach ($terms as $t) {
                if (str_contains($haystack, $t)) $score++;
            }
            // Substring match keseluruhan query → bonus.
            if (str_contains($haystack, $q)) $score += 2;
            if ($score > 0) {
                $scored[] = [
                    'slug'     => $row->intent,
                    'keywords' => $row->keywords,
                    'contoh'   => $row->pertanyaan_contoh,
                    '_score'   => $score,
                ];
            }
        }

        usort($scored, fn ($a, $b) => $b['_score'] <=> $a['_score']);
        $top = array_slice($scored, 0, 6);

        // Strip _score sebelum return ke LLM.
        $top = array_map(fn ($r) => ['slug' => $r['slug'], 'keywords' => $r['keywords'], 'contoh' => $r['contoh']], $top);

        return ['matches' => $top];
    }

    /**
     * @return array{0:array, 1:array<array{text:string,media:?string}>}
     */
    private function toolGetIntentResponse(string $slug): array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return [['ok' => false, 'error' => 'slug kosong'], []];
        }

        $intent = BotIntent::where('intent', $slug)->where('active', true)->first();
        if ($intent === null) {
            return [['ok' => false, 'error' => "intent $slug tidak ditemukan / inactive"], []];
        }

        $template = (string) $intent->jawaban_template;
        if (trim($template) === '') {
            return [['ok' => false, 'error' => 'template kosong'], []];
        }

        // FAQ-only placeholders. Booking/harga flow placeholders
        // ({{tanggal}}, {{jam}}, {{nama_anak}}, dst) tetap di-handle
        // SunatBotEngine.substituteVariables — agent tidak render
        // template booking/harga.
        $template  = $this->substituteFaqPlaceholders($template);
        $sentences = $this->splitSentences($template);
        $media     = method_exists($intent, 'mediaList') ? $intent->mediaList() : [];

        $imagesFirst = [];
        $videosLast  = [];
        foreach ($media as $file) {
            $ext = strtolower(pathinfo((string) $file, PATHINFO_EXTENSION));
            $bubble = ['text' => '', 'media' => $file];
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $imagesFirst[] = $bubble;
            } else {
                $videosLast[] = $bubble;
            }
        }
        $textBubbles = array_map(fn ($s) => ['text' => $s, 'media' => null], $sentences);

        $bubbles = array_merge($imagesFirst, $textBubbles, $videosLast);

        return [['ok' => true, 'slug' => $slug, 'bubble_count' => count($bubbles)], $bubbles];
    }

    /**
     * Mirror behavior atika maybeSendRedirect (throttled 1x/hari).
     * Tool ini di-call agent kalau pesan jelas bukan sunat.
     *
     * @return array{0:array, 1:array<array{text:string,media:?string}>}
     */
    private function toolRedirectKeAdmin(BotSession $session, string $reason): array
    {
        // Throttle 1x/hari per nomor: pakai cache file (key by phone).
        $phone = $session->no_telp ?? '';
        $key   = 'sunatbot_agent_redirect:' . preg_replace('/\D+/', '', $phone);

        $alreadySent = \Cache::has($key);
        if ($alreadySent) {
            return [['ok' => true, 'note' => 'redirect skipped (already sent today)'], []];
        }
        \Cache::put($key, 1, now()->endOfDay());

        $adminPhone = (string) config('sunatbot.nomor_operator', '');
        $adminPhone = preg_replace('/\D+/', '', $adminPhone);
        $adminLink  = $adminPhone !== '' ? "https://wa.me/{$adminPhone}" : '';

        $text = "Maaf kak, mohon menghubungi admin kami untuk membantu pertanyaan kakak 🙏";
        if ($adminLink !== '') {
            $text .= "\n\nHubungi admin di sini: {$adminLink}";
        }
        $text .= "\n\nUntuk konsultasi *sunat anak*, silakan kirim kata *sunat* ke chat ini ya kak.";

        Log::info('SUNAT_BOT_AGENT_REDIRECT', ['phone' => $phone, 'reason' => $reason]);

        return [['ok' => true, 'redirected' => true], [['text' => $text, 'media' => null]]];
    }

    // ----- HISTORY ---------------------------------------------------

    private function loadHistory(BotSession $session): array
    {
        $raw = $session->agent_history ?? null;
        if ($raw === null) return [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
        } else {
            $decoded = $raw;
        }
        if (!is_array($decoded)) return [];

        // Cuma role user/assistant yang di-keep di history persistensi
        // (assistant tanpa tool_calls — final reply). Tool roles dan
        // assistant-with-tool-calls hidup di dalam 1 turn aja.
        $clean = [];
        foreach ($decoded as $m) {
            $role = (string) ($m['role'] ?? '');
            if ($role === 'user' || $role === 'assistant') {
                if (!empty($m['tool_calls'])) continue;
                $content = (string) ($m['content'] ?? '');
                if ($content === '') continue;
                $clean[] = ['role' => $role, 'content' => $content];
            }
        }
        return array_slice($clean, -self::HISTORY_MAX_TURNS * 2);
    }

    private function saveHistory(BotSession $session, array $oldHistory, string $userMessage, array $replies): void
    {
        $assistantText = trim(implode("\n", array_map(fn ($r) => (string) ($r['text'] ?? ''), $replies)));

        $newHistory   = $oldHistory;
        $newHistory[] = ['role' => 'user', 'content' => $userMessage];
        if ($assistantText !== '') {
            $newHistory[] = ['role' => 'assistant', 'content' => $assistantText];
        }

        $trimmed = array_slice($newHistory, -self::HISTORY_MAX_TURNS * 2);
        $session->agent_history = $trimmed;
        $session->save();
    }

    // ----- OPENAI ----------------------------------------------------

    private function callOpenAI(string $apiKey, array $messages, array $tools, int $iter): ?array
    {
        $payload = [
            'model'       => self::MODEL,
            'temperature' => 0.2,
            'max_tokens'  => 500,
            'messages'    => $messages,
            'tools'       => $tools,
            'tool_choice' => 'auto',
        ];
        $start = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(self::HTTP_TIMEOUT)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            $this->logCall('agent:iter' . $iter, $messages, $payload, $response, $start);

            if (!$response->ok()) {
                Log::warning('SUNAT_BOT_AGENT_HTTP_FAIL', ['status' => $response->status(), 'iter' => $iter]);
                return null;
            }

            $json    = $response->json() ?? [];
            $message = $json['choices'][0]['message'] ?? null;
            if (!is_array($message)) return null;

            return ['message' => $message, 'raw' => $json];
        } catch (\Throwable $e) {
            $this->logCall('agent:iter' . $iter, $messages, $payload, null, $start, $e->getMessage());
            Log::warning('SUNAT_BOT_AGENT_EXCEPTION', ['err' => $e->getMessage(), 'iter' => $iter]);
            return null;
        }
    }

    private function logCall(string $method, array $messages, array $payload, $response, float $startUs, ?string $errorMessage = null): void
    {
        try {
            $status    = null;
            $aiContent = null;
            $ok        = false;
            $inTok     = null;
            $outTok    = null;
            if ($response !== null) {
                try {
                    $status = $response->status();
                    $ok     = $response->ok();
                    $json   = $response->json() ?? [];
                    $usage  = $json['usage'] ?? [];
                    $inTok  = $usage['prompt_tokens']     ?? null;
                    $outTok = $usage['completion_tokens'] ?? null;
                    $msg    = $json['choices'][0]['message'] ?? [];
                    $aiContent = json_encode([
                        'content'    => $msg['content'] ?? null,
                        'tool_calls' => $msg['tool_calls'] ?? null,
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } catch (\Throwable $e) {}
            }
            // Promp ringkas: user message terakhir saja, supaya kolom prompt tidak meledak.
            $lastUser = '';
            foreach (array_reverse($messages) as $m) {
                if (($m['role'] ?? '') === 'user') {
                    $lastUser = (string) ($m['content'] ?? '');
                    break;
                }
            }
            \DB::table('openai_logs')->insert([
                'feature'       => 'sunatbot.' . mb_substr($method, 0, 64),
                'periksa_id'    => null,
                'no_telp'       => $this->contextPhone,
                'prompt'        => mb_substr($lastUser, 0, 65000),
                'response'      => $aiContent !== null ? mb_substr($aiContent, 0, 65000) : null,
                'success'       => $ok ? 1 : 0,
                'error'         => $errorMessage !== null ? mb_substr($errorMessage, 0, 2000) : null,
                'input_tokens'  => $inTok,
                'output_tokens' => $outTok,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('OPENAI_LOG_INSERT_FAIL', ['err' => $e->getMessage()]);
        }
    }

    // ----- HELPERS ---------------------------------------------------

    private function substituteFaqPlaceholders(string $template): string
    {
        $alamat = (string) config('sunatbot.alamat_klinik', '');
        $maps   = (string) config('sunatbot.link_maps', '');
        $rona   = (string) config('sunatbot.nomor_rona', '');

        return strtr($template, [
            '[NAMA]'          => 'kak',
            '[ALAMAT_KLINIK]' => $alamat,
            '[LINK_MAPS]'     => $maps,
            '[NOMOR_RONA]'    => $rona,
            '{{nama}}'        => 'kak',
        ]);
    }

    private function splitToTextBubbles(string $text): array
    {
        $sentences = $this->splitSentences($text);
        return array_map(fn ($s) => ['text' => $s, 'media' => null], $sentences);
    }

    /**
     * Pecahan ringan berdasarkan double-newline ATAU marker [BUBBLE].
     * Untuk FAQ template yang sudah short, biarkan utuh — tidak split
     * per kalimat (SunatBotEngine punya splitText yang lebih agresif
     * untuk template-driven; di sini kita konservatif).
     */
    private function splitSentences(string $text): array
    {
        $text = trim($text);
        if ($text === '') return [];

        if (str_contains($text, '[BUBBLE]')) {
            $parts = preg_split('/\[BUBBLE\]/u', $text) ?: [];
        } else {
            $parts = preg_split('/\n{2,}/u', $text) ?: [$text];
        }
        $clean = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') $clean[] = $p;
        }
        return $clean ?: [$text];
    }

    private function fallbackReply(): array
    {
        // OpenAI tidak available → biar engine downstream yang escalate
        // dengan fallback_unknown standar. Sinyal 'agent_unavailable'
        // bisa di-handle controller.
        return [
            'handled'  => false,
            'replies'  => [],
            'signal'   => null,
            'escalate' => false,
        ];
    }
}
