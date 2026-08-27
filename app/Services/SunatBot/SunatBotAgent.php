<?php

namespace App\Services\SunatBot;

use App\Models\BotIntent;
use App\Models\BotSession;
use Carbon\Carbon;
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

    // Hard guard utk trigger_booking_flow — kalau user message ada kata
    // ini, BUKAN booking sunat → reject tool call, force agent re-route
    // ke redirect_ke_klinik_utama.
    private const NON_SUNAT_KEYWORDS = [
        'usg', 'kandungan', 'kehamilan', 'hamil', 'lab',
        'cek darah', 'dokter umum', 'gigi', 'kulit',
        'vaksin', 'imunisasi', 'mobile jkn', 'mobile-jkn',
        'jkn', 'obat', 'resep',
    ];
    private const SUNAT_KEYWORDS = [
        'sunat', 'khitan', 'sirkumsis', 'circumcis',
    ];

    /** Pesan customer turn ini — di-set di reply(), dibaca di executeTool. */
    private string $currentUserMessage = '';

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
        $this->currentUserMessage = $userMessage;

        $apiKey = (string) env('OPENAI_API_KEY', '');
        if ($apiKey === '') {
            Log::warning('SUNAT_BOT_AGENT_NO_KEY', ['phone' => $session->no_telp ?? null]);
            return $this->fallbackReply();
        }

        $history      = $this->loadHistory($session);
        $systemPrompt = $this->buildSystemPrompt();
        $sessionState = $this->renderSessionStateSnapshot($session);

        // Append user turn — disimpan ke history setelah loop selesai.
        // Session state snapshot di-inject sbg system message TERAKHIR
        // sebelum user message supaya agent lihat field yang sudah
        // terkumpul (nama_orang_tua, domisili, usia, dll) — mencegah
        // re-ask field yg sudah ada.
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history,
            $sessionState !== '' ? [['role' => 'system', 'content' => $sessionState]] : [],
            [['role' => 'user', 'content' => $userMessage]]
        );

        $tools = $this->toolDefinitions();

        $replies             = [];
        $signal              = null;
        $escalate            = false;
        $iter                = 0;
        $toolEmittedReplies  = false;
        $prefill             = [];
        // Tracker slug yang sudah di-render via get_intent_response di
        // turn ini. Agent kadang panggil intent yang sama berkali-kali
        // (mis. iter1 + iter2 get_intent_response("pertanyaan_lokasi")
        // dua kali → template ke-render dobel). Dedupe di sisi tool.
        $renderedSlugs = [];
        // Media slug = intent yg tool `get_intent_response` sudah render
        // bubble komplit (text + foto/video). Track supaya di iter berikut
        // kita bisa drop text agent kalau content-nya redundant (double
        // send alamat/maps/dsb). Agent sering bandel walau prompt sudah
        // suruh "output text KOSONG setelah get_intent_response".
        $mediaSlugRendered = [];
        // Enforce: agent harus panggil lookup_knowledge minimal 1x
        // sebelum boleh redirect / trigger flow. Model kadang skip
        // lookup dan langsung redirect untuk pertanyaan yg sebenarnya
        // ada di knowledge base (mis. "ada hadiah?" → redirect padahal
        // ada intent pertanyaan_hadiah).
        $lookupCalled = false;

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
                // Tidak ada tool call → agent jawab text langsung.
                // GUARD: kalau tool sudah emit reply DAN tidak ada
                // active collection (harga/booking) — text ini
                // kemungkinan improvisation. Drop.
                // Tapi kalau ada collection in-progress dgn field
                // belum terisi, text = resume "Balik ke tadi kak,
                // [pertanyaan]" yang WAJIB dikirim (interrupt handling).
                //
                // ALSO KEEP: kalau user message mengandung trigger
                // keyword HARGA / BOOKING dan flow-nya belum aktif,
                // text ini kemungkinan = flow opener ("Untuk biaya
                // sunat tergantung usia..." / "Boleh tau tanggal
                // berapa..."). Multi-topic message spt "sistem, lokasi,
                // mahar berapa?" bakal panggil pertanyaan_metode +
                // pertanyaan_lokasi tools, lalu iter2 text = opener
                // HARGA — tanpa exception ini text di-drop dan
                // pertanyaan mahar tidak ter-address.
                $userMsgLower = mb_strtolower((string) $this->currentUserMessage);
                $hargaTriggerRe = '/(mahar|harga|biaya|berapa|brp|\bpl\b|price)/u';
                $bookingTriggerRe = '/(mau daftar|mau booking|nyunatin|khitan[- ]?in|jadwalin|ambil jadwal|set jadwal|atur jadwal|booking\b|book\b)/u';
                $userAskedHarga = (bool) preg_match($hargaTriggerRe, $userMsgLower);
                $userAskedBooking = (bool) preg_match($bookingTriggerRe, $userMsgLower);
                $keepAsFlowOpener = ($userAskedHarga && !$session->getData('_harga_sent'))
                                 || ($userAskedBooking && !$session->is_complete);

                // Extra drop condition: kalau media slug (pertanyaan_lokasi/
                // metode/dsb) sudah di-render via get_intent_response di
                // turn ini, drop text kecuali text = resume interrupt
                // ("Balik ke tadi kak..."). Ini nangkap kasus dimana
                // hasActiveCollection true (misal lead sudah tercapture)
                // tapi agent tetap output alamat/maps text = double bubble.
                $isResumeInterrupt = (bool) preg_match('/balik ke tadi/iu', $textContent);
                $mediaDrop = !empty($mediaSlugRendered) && !$isResumeInterrupt && !$keepAsFlowOpener;

                if ($toolEmittedReplies && !$this->hasActiveCollection($session) && !$keepAsFlowOpener) {
                    Log::info('SUNAT_BOT_AGENT_DROPPED_POST_TOOL_TEXT', [
                        'phone' => $session->no_telp,
                        'iter'  => $iter,
                        'text'  => mb_substr($textContent, 0, 200),
                    ]);
                } elseif ($mediaDrop) {
                    Log::info('SUNAT_BOT_AGENT_DROPPED_POST_MEDIA_TEXT', [
                        'phone'  => $session->no_telp,
                        'iter'   => $iter,
                        'slugs'  => $mediaSlugRendered,
                        'text'   => mb_substr($textContent, 0, 200),
                    ]);
                } elseif ($textContent !== '') {
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

                // Gate lookup_knowledge mandatory sudah dihapus (option C):
                // FAKTA sudah ada di system prompt, agent boleh langsung
                // trigger flow tanpa lookup dulu.

                if ($toolName === 'lookup_knowledge') {
                    $lookupCalled = true;
                }

                // Hard guard: block get_intent_response utk slug yang
                // "final quote" atau template internal harga flow —
                // final quote hanya via send_harga_quote, ask field
                // harga hanya via text natural + save_harga_data.
                if ($toolName === 'get_intent_response') {
                    $slugArg = trim((string) ($args['slug'] ?? ''));
                    $blockedExact = ['quote_harga_paket', 'quote_harga_paket_promo', 'fallback_unknown'];
                    if (in_array($slugArg, $blockedExact, true)
                        || str_starts_with($slugArg, 'tanya_')
                        || str_starts_with($slugArg, 'data_')) {
                        Log::info('SUNAT_BOT_AGENT_BLOCK_SLUG', [
                            'phone' => $session->no_telp,
                            'slug'  => $slugArg,
                        ]);
                        $toolResult = [
                            'ok'    => false,
                            'error' => "slug '$slugArg' dilarang dipanggil via get_intent_response. Untuk final quote pakai send_harga_quote. Untuk tanya field harga, tanya sendiri dgn text natural (jangan pakai tool ini).",
                        ];
                        $messages[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $callId,
                            'content'      => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                        ];
                        continue;
                    }
                }

                // Dedupe CROSS-TURN: kalau slug sudah pernah dirender
                // di session (persist di collected_data._slugs_shown),
                // block. LLM sering re-call pertanyaan_metode dst di
                // turn berbeda -> customer terima foto dobel.
                if ($toolName === 'get_intent_response') {
                    $slugArg = trim((string) ($args['slug'] ?? ''));
                    $slugsShownSession = (array) $session->getData('_slugs_shown');
                    if ($slugArg !== '' && in_array($slugArg, $slugsShownSession, true)) {
                        Log::info('SUNAT_BOT_AGENT_BLOCK_REPEAT_SLUG', [
                            'phone' => $session->no_telp,
                            'slug'  => $slugArg,
                        ]);
                        $toolResult = [
                            'ok'    => false,
                            'error' => "slug '$slugArg' SUDAH dirender di session ini. DILARANG re-render. Kalau customer tanya hal yang sama, paraphrase singkat dari FAKTA saja, jangan call tool ini lagi.",
                        ];
                        $messages[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $callId,
                            'content'      => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                        ];
                        continue;
                    }
                }

                // Dedupe: kalau agent call get_intent_response untuk
                // slug yang sudah di-render di turn ini, skip eksekusi
                // dan kembalikan note ke LLM supaya tidak loop.
                if ($toolName === 'get_intent_response') {
                    $slugArg = trim((string) ($args['slug'] ?? ''));
                    if ($slugArg !== '' && in_array($slugArg, $renderedSlugs, true)) {
                        Log::info('SUNAT_BOT_AGENT_DEDUP_SLUG', [
                            'phone' => $session->no_telp,
                            'slug'  => $slugArg,
                            'iter'  => $iter,
                        ]);
                        $toolResult = ['ok' => true, 'note' => "slug $slugArg sudah di-render di turn ini, dilewati"];
                        $messages[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $callId,
                            'content'      => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                        ];
                        continue;
                    }
                    if ($slugArg !== '') {
                        $renderedSlugs[] = $slugArg;
                    }
                }

                // Hard guard: trigger_booking_flow hanya valid kalau user
                // message ada kata sunat/khitan, dan tidak ada kata kunci
                // non-sunat. Model kadang shortcut "daftar X" → booking
                // walaupun X = USG/lab/dokter umum. Reject + arahkan ke
                // redirect_ke_klinik_utama.
                // Hard guard MUTUAL EXCLUSION antara harga vs booking flow.
                // Kalau harga collection sedang aktif (belum kirim quote) dan
                // LLM mau save_booking_data, tolak. Kebalikannya juga —
                // booking aktif tapi LLM call save_harga_data → tolak.
                // Block redirect_ke_klinik_utama saat booking flow aktif.
                // Setelah bot mulai booking (booking_started=true), user reply
                // "19 juli" atau "atas nama X" tidak ada kata sunat → LLM
                // salah pick redirect. Force lanjut booking.
                if ($toolName === 'redirect_ke_klinik_utama'
                    && (bool) $session->getData('booking_started')
                    && !$session->is_complete) {
                    Log::info('SUNAT_BOT_AGENT_BLOCK_REDIRECT_DURING_BOOKING', ['phone' => $session->no_telp]);
                    $toolResult = [
                        'ok'    => false,
                        'error' => 'Booking flow sedang aktif. DILARANG redirect — customer sedang booking sunat, reply-nya (tanggal/nama/dll) bagian flow. Panggil save_booking_data untuk simpan field.',
                    ];
                    $messages[] = ['role' => 'tool', 'tool_call_id' => $callId, 'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE)];
                    continue;
                }

                // Guard: LLM kadang salah-pick redirect_ke_klinik_utama untuk
                // pertanyaan harga sunat (mis. "Mau tanya brp y kak" →
                // reason="tanya harga sunat"). Redirect ini SALAH karena
                // context SunatBot default = sunat, tanya harga = HARGA flow.
                // Reject + arahkan LLM ke save_harga_data.
                if ($toolName === 'redirect_ke_klinik_utama') {
                    $reason = mb_strtolower((string) ($args['reason'] ?? ''));
                    $userMsg = mb_strtolower((string) $this->currentUserMessage);
                    $hargaKw = ['harga', 'biaya', 'berapa', 'brp', 'pl', 'price'];
                    $nonSunatKw = ['usg', 'kandungan', 'hamil', 'lab', 'dokter umum', 'gigi', 'kulit', 'vaksin', 'imunisasi', 'kontrol obat', 'bpjs'];
                    $reasonMentionsHarga = false;
                    foreach ($hargaKw as $kw) { if (str_contains($reason, $kw)) { $reasonMentionsHarga = true; break; } }
                    $userMsgHasHarga = false;
                    foreach ($hargaKw as $kw) {
                        if (preg_match('/(^|\W)' . preg_quote($kw, '/') . '($|\W)/u', $userMsg)) { $userMsgHasHarga = true; break; }
                    }
                    $userMsgHasNonSunat = false;
                    foreach ($nonSunatKw as $kw) { if (str_contains($userMsg, $kw)) { $userMsgHasNonSunat = true; break; } }
                    if (($reasonMentionsHarga || $userMsgHasHarga) && !$userMsgHasNonSunat) {
                        Log::info('SUNAT_BOT_AGENT_BLOCK_REDIRECT_HARGA', [
                            'phone'  => $session->no_telp,
                            'reason' => $reason,
                            'msg'    => mb_substr($userMsg, 0, 200),
                        ]);
                        $toolResult = [
                            'ok'    => false,
                            'error' => 'DILARANG redirect utk pertanyaan harga sunat. Customer di SunatBot default context = sunat, "brp/berapa/biaya/PL" = tanya harga sunat, MASUK HARGA FLOW (save_harga_data), BUKAN redirect_ke_klinik_utama.',
                        ];
                        $messages[] = ['role' => 'tool', 'tool_call_id' => $callId, 'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE)];
                        continue;
                    }

                    // Guard: LLM juga sering salah-pick redirect utk pertanyaan
                    // "sunat pakai BPJS?" / "Bs pk BPJS y" / "ditanggung BPJS?".
                    // Context SunatBot default = sunat, kalau customer nyebut
                    // "BPJS" TANPA topic non-sunat lain (usg/gigi/dll), 99%
                    // maksudnya "sunat pakai BPJS bisa nggak" — ada intent
                    // dedicated pertanyaan_sunat_menggunakan_bpjs yg jawab
                    // "Sunat di sunatboy tidak bisa menggunakan BPJS...".
                    // Reject redirect, arahkan LLM ke get_intent_response.
                    $nonBpjsNonSunatKw = ['usg', 'kandungan', 'hamil', 'lab', 'dokter umum', 'gigi', 'kulit', 'vaksin', 'imunisasi', 'kontrol obat'];
                    $userMsgHasOtherNonSunat = false;
                    foreach ($nonBpjsNonSunatKw as $kw) { if (str_contains($userMsg, $kw)) { $userMsgHasOtherNonSunat = true; break; } }
                    if (str_contains($userMsg, 'bpjs') && !$userMsgHasOtherNonSunat) {
                        Log::info('SUNAT_BOT_AGENT_BLOCK_REDIRECT_BPJS', [
                            'phone'  => $session->no_telp,
                            'reason' => $reason,
                            'msg'    => mb_substr($userMsg, 0, 200),
                        ]);
                        $toolResult = [
                            'ok'    => false,
                            'error' => 'DILARANG redirect utk pertanyaan "sunat pakai BPJS". Pakai get_intent_response(slug="pertanyaan_sunat_menggunakan_bpjs") supaya customer dapat jawaban yang tepat: "Sunat di sunatboy tidak bisa menggunakan BPJS atau asuransi lain, hanya biaya pribadi."',
                        ];
                        $messages[] = ['role' => 'tool', 'tool_call_id' => $callId, 'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE)];
                        continue;
                    }

                    // Guard STRIKT: default context SunatBot = sunat.
                    // Redirect_ke_klinik_utama HANYA valid kalau user message
                    // eksplisit sebut layanan non-sunat (usg/gigi/dokter umum/
                    // dll di $nonSunatKw). Kalau user cuma jawab lead capture
                    // (nama/domisili spt "Saya opes", "Lok di perumahan benua
                    // indah"), atau tanya lokasi klinik sunat, JANGAN redirect.
                    // Reject + arahkan LLM ke tool yang tepat.
                    // Kasus bug 2026-08-23 (6285285926533): customer jawab
                    // "Lok di perumahan benua indah" (domisili) → agent malah
                    // redirect_ke_klinik_utama.
                    if (!$userMsgHasNonSunat) {
                        Log::info('SUNAT_BOT_AGENT_BLOCK_REDIRECT_NO_NONSUNAT_KW', [
                            'phone'  => $session->no_telp,
                            'reason' => $reason,
                            'msg'    => mb_substr($userMsg, 0, 200),
                        ]);
                        $toolResult = [
                            'ok'    => false,
                            'error' => 'DILARANG redirect_ke_klinik_utama kalau user message TIDAK sebut layanan non-sunat eksplisit (usg/gigi/dokter umum/kulit/vaksin/kandungan/hamil/lab/bpjs/dll). Default context SunatBot = sunat. Kalau user answering lead capture (nama/domisili), pakai save_lead_sunat. Kalau tanya lokasi klinik, pakai get_intent_response("pertanyaan_lokasi"). Kalau ragu, jangan redirect.',
                        ];
                        $messages[] = ['role' => 'tool', 'tool_call_id' => $callId, 'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE)];
                        continue;
                    }
                }

                if ($toolName === 'save_harga_data' && (bool) $session->getData('booking_started') && !$session->is_complete) {
                    Log::info('SUNAT_BOT_AGENT_BLOCK_HARGA_DURING_BOOKING', ['phone' => $session->no_telp]);
                    $toolResult = [
                        'ok'    => false,
                        'error' => 'Booking flow sedang aktif. Selesaikan booking dulu (panggil save_booking_data / finalize_booking). save_harga_data + send_harga_quote di-block.',
                    ];
                    $messages[] = ['role' => 'tool', 'tool_call_id' => $callId, 'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE)];
                    continue;
                }
                if ($toolName === 'send_harga_quote' && (bool) $session->getData('booking_started') && !$session->is_complete) {
                    Log::info('SUNAT_BOT_AGENT_BLOCK_HARGA_QUOTE_DURING_BOOKING', ['phone' => $session->no_telp]);
                    $toolResult = ['ok' => false, 'error' => 'Booking flow aktif, send_harga_quote di-block.'];
                    $messages[] = ['role' => 'tool', 'tool_call_id' => $callId, 'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE)];
                    continue;
                }
                $hargaAktif = !((bool) $session->getData('_harga_sent'));
                if ($hargaAktif) {
                    $hargaAktif = false;
                    foreach (self::HARGA_REQUIRED_FIELDS as $hf) {
                        $v = $session->getData($hf);
                        if ($v !== null && $v !== '') { $hargaAktif = true; break; }
                    }
                }
                if ($toolName === 'save_booking_data' && $hargaAktif && !$session->getData('booking_started')) {
                    Log::info('SUNAT_BOT_AGENT_BLOCK_BOOKING_DURING_HARGA', ['phone' => $session->no_telp]);
                    $toolResult = [
                        'ok'    => false,
                        'error' => 'Harga collection sedang aktif. Selesaikan harga dulu (panggil send_harga_quote setelah 6 field lengkap), atau kalau customer beralih ke booking, tanya konfirmasi eksplisit dulu.',
                    ];
                    $messages[] = ['role' => 'tool', 'tool_call_id' => $callId, 'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE)];
                    continue;
                }

                if ($toolName === 'save_booking_data') {
                    // Skip sunat-keyword guard kalau:
                    // - booking sudah pernah dimulai (booking_started flag), ATAU
                    // - ada field booking sudah terisi, ATAU
                    // - conversation history sudah pernah menyebut sunat/khitan
                    //   (bot follow-up: user reply "19 juli" tanpa kata sunat,
                    //   tapi history sebelumnya user bilang "saya mau daftar
                    //   sunat" → context booking sunat sudah jelas).
                    $bookingAktif = (bool) $session->getData('booking_started')
                                 || $session->getData('booking_tanggal') !== null
                                 || $session->getData('booking_jam') !== null
                                 || trim((string) $session->getData('booking_nama_anak')) !== '';
                    $historyHasSunatKw = false;
                    if (!$bookingAktif) {
                        $historyText = mb_strtolower(implode("\n", array_map(
                            fn ($m) => (string) ($m['content'] ?? ''),
                            $history ?? []
                        )));
                        foreach (self::SUNAT_KEYWORDS as $kw) {
                            if (str_contains($historyText, $kw)) { $historyHasSunatKw = true; break; }
                        }
                    }
                    $rejectReason = ($bookingAktif || $historyHasSunatKw) ? null : $this->validateBookingFlowMessage($this->currentUserMessage);
                    if ($rejectReason !== null) {
                        Log::info('SUNAT_BOT_AGENT_REJECT_BOOKING_FLOW', [
                            'phone'   => $session->no_telp,
                            'reason'  => $rejectReason,
                            'message' => mb_substr($this->currentUserMessage, 0, 200),
                        ]);
                        $toolResult = [
                            'ok'    => false,
                            'error' => "Booking flow ditolak: $rejectReason. WAJIB panggil redirect_ke_klinik_utama (customer butuh layanan non-sunat / pesan tidak menyebut sunat).",
                        ];
                        $messages[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $callId,
                            'content'      => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                        ];
                        continue;
                    }
                }

                [$toolResult, $sideEffect] = $this->executeTool($toolName, $args, $session);

                if (isset($sideEffect['replies']) && $sideEffect['replies'] !== []) {
                    $replies = array_merge($replies, $sideEffect['replies']);
                    $toolEmittedReplies = true;

                    // Track intent slug rendered supaya text follow-up
                    // yg redundant (agent paraphrase isi template) di
                    // iter berikut bisa di-drop. List berisi SEMUA
                    // "answer" intents — baik text-only maupun media —
                    // supaya agent gak double-emit BPJS/lokasi/metode/dll.
                    if ($toolName === 'get_intent_response') {
                        $slugArg = trim((string) ($args['slug'] ?? ''));
                        $mediaSlugs = [
                            // Media (text + foto/video)
                            'pertanyaan_lokasi', 'pertanyaan_metode', 'pertanyaan_jarum_bius',
                            'pertanyaan_fasilitas', 'pertanyaan_testimoni', 'pertanyaan_hadiah',
                            'contoh_dokumentasi', 'edukasi_kelebihan', 'trigger_sunat',
                            'pertanyaan_durasi_sembuh', 'pertanyaan_pengawasan_pasca',
                            // Text-only "answer" intents (agent suka paraphrase → duplikat)
                            'pertanyaan_sunat_menggunakan_bpjs',
                            'pertanyaan_bpjs', 'pertanyaan_asuransi',
                            'pertanyaan_jam_praktik', 'pertanyaan_jadwal',
                            'pertanyaan_dokter', 'pertanyaan_perempuan',
                            'pertanyaan_home_service', 'pertanyaan_di_rumah',
                            'pertanyaan_promo', 'pertanyaan_diskon',
                            'pertanyaan_jahitan', 'pertanyaan_perban',
                            'pertanyaan_durasi_tindakan', 'pertanyaan_konsultasi',
                            'pertanyaan_laser',
                        ];
                        if ($slugArg !== '' && in_array($slugArg, $mediaSlugs, true)) {
                            $mediaSlugRendered[] = $slugArg;
                        }
                    }
                }
                if (isset($sideEffect['signal']) && $sideEffect['signal'] !== null) {
                    $signal = $sideEffect['signal'];
                }
                if (isset($sideEffect['prefill']) && is_array($sideEffect['prefill'])) {
                    $prefill = $sideEffect['prefill'];
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
            // escalate juga dianggap "handled" — engine harus route
            // ke escalate() (handoff admin), BUKAN fall-through ke
            // classifier (yg bisa return 0 replies → bot silent).
            'handled'  => $replies !== [] || $signal !== null || $escalate,
            'replies'  => $replies,
            'signal'   => $signal,
            'escalate' => $escalate,
            'prefill'  => $prefill,
        ];
    }

    private function buildSystemPrompt(): string
    {
        $klinik = (string) config('sunatbot.alamat_klinik', '');
        $maps   = (string) config('sunatbot.link_maps', '');
        $today  = \Carbon\Carbon::today('Asia/Jakarta');
        $todayStr   = $today->format('Y-m-d');
        $todayLabel = $today->locale('id')->translatedFormat('l, d F Y');

        return <<<PROMPT
Kamu adalah CS WhatsApp klinik sunat anak SunatBoy (Klinik Jati Elok, Tangerang).
Bicaranya santai, ramah, natural — seperti staf admin manusia. Jawab langsung dari FAKTA di bawah, paraphrase bebas, JANGAN ubah angka/nama/detail teknis.

📅 HARI INI: {$todayLabel} ({$todayStr}). Kalau customer sebut tanggal tanpa tahun (mis. "5 juli"), ASUMSIKAN tahun sekarang. Kalau tanggal itu sudah lewat di tahun sekarang, ambil tahun DEPAN. JANGAN pernah guess ke tahun 2023 atau tahun lain.

═══ 🚫 KATA TERLARANG (MUTLAK — JANGAN PERNAH DIPAKAI) 🚫 ═══

DILARANG menyebut kata-kata berikut dalam SEMUA percakapan, BAHKAN KALAU CUSTOMER YANG MENGGUNAKANNYA TERLEBIH DAHULU:
- "jarum" / "jarum suntik"
- "suntik" / "menyuntik" / "disuntik"
- "sakit" / "kesakitan" / "menyakitkan"
- "potong" / "memotong" / "dipotong"
- "gunting" / "menggunting" / "digunting"

Kalau customer pakai kata di atas dalam pertanyaan, BALAS dgn paraphrase positif tanpa mengulang kata terlarang. Contoh:
- Customer: "Pakai jarum suntik gak biusnya?"
  ❌ JANGAN: "Iya pakai jarum bius tipis kak..."
  ✅ PAKAI: "Biusnya sangat nyaman kak, kebanyakan anak tidak menyadari saat proses pembiusan."
- Customer: "Sakit gak?"
  ❌ JANGAN: "Tidak terlalu sakit kok..."
  ✅ PAKAI: "Sangat nyaman kak, anak biasanya tidak terganggu. Banyak yang sibuk main PS selama proses."
- Customer: "Dipotongnya gimana?"
  ❌ JANGAN: "Kami potong dgn alat klem..."
  ✅ PAKAI: "Kami pakai metode teknoklamp dengan alat cetak — hasilnya rapi dan perdarahan minimal."

Sinonim positif yang BOLEH dipakai: "bius nyaman", "proses pembiusan", "tindakan", "prosedur", "metode teknoklamp", "alat cetak", "tidak terasa".

⚠️ **PENTING**: Aturan kata terlarang ini HANYA berlaku untuk TEXT yang kamu tulis sendiri. Tool call (`get_intent_response`, `send_harga_quote`, dll) TETAP HARUS dipanggil — template bubble punya wording sendiri yg sudah aman. JANGAN skip tool call cuma karena topic-nya mengandung kata terlarang. Contoh: customer tanya "biusnya disuntik gak?" → tetap WAJIB call `get_intent_response('pertanyaan_jarum_bius')` supaya video edukasi terkirim. Kalau ada text tambahan dari kamu setelahnya, baru apply kata terlarang guard.

═══ FAKTA KLINIK (sumber tunggal — jawab dari sini) ═══

📍 LOKASI: $klinik
   Maps: $maps
   Landmark: dekat stasiun kereta Parung Panjang.
   ⚠️ **Hanya 1 cabang** — cuma di Parung Panjang (dekat stasiun kereta Parung Panjang). TIDAK ADA cabang lain (Graha Raya, BSD, Serpong, Bintaro, Tangerang Kota, Cikupa, Jakarta, dll — semua tidak ada).
   🚫 **DILARANG sebut alamat manual di text bebas** — alamat harus selalu dikirim bareng link maps. Untuk semua pertanyaan lokasi/alamat/cabang (termasuk "ada cabang di [daerah X]"), WAJIB call `get_intent_response('pertanyaan_lokasi')` supaya template + link maps ikut terkirim. Kalau customer nanya cabang lain, boleh tambah 1 bubble text singkat setelah tool: "Untuk saat ini SunatBoy cuma 1 cabang kak (Parung Panjang, dekat stasiun kereta Parung Panjang), belum ada di daerah lain 🙏" — TAPI jangan tulis alamat lengkap tanpa link maps.

🕘 JAM PRAKTIK: Setiap hari Senin–Minggu, 07.00–17.00 WIB.

⚕️ METODE: Teknoklamp — 1 metode saja yang kami pakai.
   - Pakai alat cetak (hasil lebih rapi)
   - Tanpa alat menempel
   - Tanpa perban (anak pakai celana sunat khusus dari kami)
   - Perdarahan minimal
   - Mesin electrosurgical seperti di ruang operasi modern
   - Tidak pakai sedasi / general anesthesia

   ⚠️ **BAHASA AWAM vs MEDIS — LASER = ELECTROSURGICAL UNIT:**
   Customer sering pakai istilah awam "laser" / "bakar" / "kauter" / "sinar". Ini SAMA dgn `electrosurgical unit` yg kami pakai. Kalau customer tanya "pakai laser?" / "ini laser bukan?" / "elektro ya?" — JAWAB **AFIRMATIF**: "Iya kak, kami pakai laser (dalam bahasa medis: electrosurgical unit)". 🚫 DILARANG jawab "bukan laser" / "kami pakai teknoklamp, bukan laser" — itu SALAH. Teknoklamp = brand alat cetak-nya, electrosurgical (laser) = sumber energinya. Keduanya ada di paket kami.

   **ROUTING intent untuk pertanyaan metode:**
   - "laser?" / "elektro?" / "bakar?" / "kauter?" / "sinar?" → WAJIB `get_intent_response("pertanyaan_laser")` (BUKAN pertanyaan_metode). Template afirmasi laser + edukasi electrosurgical unit.
   - "metode apa?" / "teknik apa?" / "cara sunatnya?" → `get_intent_response("pertanyaan_metode")` (template teknoklamp).

💉 BIUS: Sangat nyaman. Kebanyakan anak tidak menyadari saat proses pembiusan. Anak bisa sibuk main PS / nonton selama proses. Rasa tidak nyaman minim sekali.

🩺 OPERATOR: Dokter spesialis (bukan mantri). Ada perawat asisten.

👶 USIA: **SEMUA usia dilayani** — bayi, balita, anak, remaja, dewasa. Tidak ada batas usia minimum/maksimum. Usia 1–7 tahun sekedar range yg paling sering datang (bukan batasan). Kalau customer bilang anaknya usia berapapun (8/9/10/12/15 tahun / dewasa / bayi), JANGAN pernah bilang "tidak bisa" atau "hanya untuk 1-7 tahun". SEMUA BISA.

⚠️ **ATURAN USIA ≥17 TAHUN (dewasa)** — per instruksi dr. Yoga 2026-08-16:
- **HARGA flow**: usia ≥17 → NORMAL. Kumpulkan 8 field seperti biasa, panggil `send_harga_quote` → engine auto-swap ke template dewasa (Rp 3.500.000, konten manfaat medis dewasa tanpa hadiah anak). Tidak ada handoff.
- **BOOKING flow**: usia ≥17 juga NORMAL. Booking dewasa proceed sama seperti anak — kumpulkan 9 field wajib, panggil `save_booking_data` + `finalize_booking`. Tidak ada handoff spesifik usia. Sudah dijawab harganya (3.5jt), jadi tidak perlu review manusia.

Safety escalation LLM-classified (`perlu_review_dokter=true` untuk postur/indikasi/riwayat berisiko) tetap jalan — kalau customer sebut kondisi medis serius, tetap handoff meskipun bukan karena usia.

JANGAN tolak sendiri, JANGAN bilang "tidak bisa dilayani" untuk usia ≥17. Layani seperti pasien anak, dgn template harga & booking yg sudah adjust otomatis.

♀️ Sunat PEREMPUAN: TIDAK kami layani. Hanya laki-laki.

🏠 Sunat DI RUMAH: TIDAK ada home service. Hanya di klinik.

💊 BPJS / Asuransi: TIDAK bisa pakai BPJS atau asuransi lain. Pembayaran mandiri saja.

📞 NOMOR ADMIN KLINIK UTAMA (untuk USG/BPJS/dokter umum/gigi/dll): +62 821-1378-1271. Link WA: https://wa.me/6282113781271

Kapan sebutkan nomor/link ini:
- Customer minta layanan NON-sunat (USG, BPJS, dokter umum, dll) → SELALU kirim link WA dalam reply text kamu, JANGAN cuma bilang "silakan chat admin". Format: "Silakan tap link berikut untuk chat admin klinik utama:\n\nhttps://wa.me/6282113781271". URL polos, tanpa markdown.
- Customer EKSPLISIT tanya nomor/kontak → langsung sebutkan link.
- Kalau tool `redirect_ke_klinik_utama` sudah pernah dipanggil hari ini (throttled), kamu WAJIB kirim reply text sendiri berisi link WA — jangan silent.

🪡 JAHITAN: Metode teknoklamp kami umumnya TIDAK perlu jahitan.

🩹 PERBAN: Tidak pakai perban. Anak pakai celana sunat khusus → nyaman pakai celana biasa dari hari pertama.

📅 DURASI SEMBUH: Luka kering 5-10 hari (rata-rata 1 minggu). Anak bisa kembali sekolah ~3 hari pasca tindakan kalau tidak ada penyulit.

⏱️ DURASI TINDAKAN: Proses sunat 15-30 menit. Tapi total di klinik sekitar 1-1.5 jam (termasuk konsultasi + edukasi pasca).

🩺 PENGAWASAN PASCA SUNAT: Lewat WhatsApp dengan dokter kami — TIDAK perlu kontrol fisik ke klinik.

🎁 FASILITAS YANG DIDAPAT (sudah include di paket):
   ✓ Tindakan sunat metode teknoklamp
   ✓ Sertifikat sunat
   ✓ Mobil remote-control hadiah
   ✓ Kaos SunatBoy
   ✓ Celana sunat khusus
   ✓ Obat + edukasi pasca sunat
   ✓ Pengawasan dokter via WhatsApp sampai sembuh

🎉 PROMO: SAAT INI TIDAK ADA PROMO AKTIF.
   - DILARANG menyebut promo, diskon, paket grup, paket hemat, paket keluarga, dll.
   - Kalau customer tanya promo/diskon → jawab: "Untuk saat ini belum ada promo aktif kak, harga sesuai standar klinik."

⭐ KASUS KHUSUS (WAJIB escalate ke admin, JANGAN langsung quote/booking):
   - ADHD / autisme / ASD / hiperaktif / berkebutuhan khusus
   - Anak gemuk / obesitas (faktor risiko anestesi)
   - Riwayat penyakit (jantung, kelainan pembekuan darah, dll)
   → Engine otomatis handoff kalau customer sebut kondisi ini, jangan kamu reply panjang sendiri.

🚫🚫🚫 **AI TIDAK BOLEH MEMUTUSKAN "TIDAK BISA DILAYANI"** 🚫🚫🚫
   AI **DILARANG** bilang kalimat-kalimat berikut ke customer:
   - "Kami tidak bisa melayani..."
   - "Maaf tidak bisa..."
   - "Kami hanya melayani untuk ... (usia/BB/kondisi tertentu)"
   - "Untuk kondisi kakak, kami tidak menerima..."
   - "Kasus itu di luar layanan kami"

   Keputusan "tidak bisa dilayani" adalah keputusan **manusia** (dr. Yoga / admin). AI cuma boleh **mengarahkan ke admin** — kalau kamu ragu apakah customer bisa dilayani (usia unusual, request unusual, kondisi khusus di luar checklist safety, dsb) → **WAJIB call `handoff_to_admin(reason)`**. Tool akan escalate + bot kirim pesan singkat "sebentar ya kak, saya cek ke tim".

   Contoh kasus WAJIB handoff (JANGAN tolak sendiri):
   - Customer bilang "anak saya usia 12 tahun / dewasa / bayi 2 minggu" — jangan bilang tidak bisa, handoff.
   - Customer minta "sunat malam hari / sunat panggil rumah / sunat massal 10 anak / sunat perempuan / sunat kucing" — handoff (bahkan kalau kamu yakin "tidak bisa", biar admin yg konfirm).
   - Customer BB extreme (mis. 5 kg atau 50 kg bilang normal) — handoff.
   - Customer kondisi khusus yg belum tercover di safety-checklist (mis. celah bibir, hemofilia berat, alergi anestesi tertentu) — handoff.
   - Semua permintaan yg kamu tergoda jawab "tidak bisa" atau "hanya bisa untuk X" — STOP, handoff dulu.

   Format handoff yg BENAR:
   → call `handoff_to_admin(reason="anak usia 9th mau sunat, AI ragu apakah bisa dilayani")` → output text KOSONG (tool udah kirim pesan tunggu).

═══ 🩺 KONSUL DOKTER (kelainan / kondisi khusus penis) ═══

Kalau customer sebut kelainan atau kondisi khusus di area penis anaknya — baik istilah medis (fimosis, buried penis, hipospadia, chordee, mikropenis, parafimosis, dll) MAUPUN deskripsi awam ("burung nya kecil bgt", "belum turun", "menempel di badan", "lecet", "bengkak", "kayak ada benjolan", "kulit ujung nempel", "gak bisa ditarik", dsb) → **PANGGIL `request_konsul_dokter(reason)`**. Kamu WAJIB pakai judgment natural language, bukan keyword list.

Flow otomatis setelah tool ini:
1. Tool emit 1 bubble ke customer: "Boleh dibantu kirim foto area yg dikeluhkan kak? 🙏 Kami akan konsulkan ke dokter kami..."
2. Customer kirim foto → webhook OTOMATIS forward foto ke dr. Yoga (081381912803) untuk asesmen. Bot mute customer.
3. Dokter reply via WA → engine OTOMATIS relay reply ke customer via sunat device. Bot resume normal handling.

Aturan:
- Cuma panggil 1x per session. Session snapshot bakal tunjukkan `konsul_dokter_pending=true` kalau sudah pernah — jangan panggil lagi.
- Kalau customer nolak kirim foto ("saya malu" / "gak mau foto") → JANGAN retry. Escalate `handoff_to_admin(reason="customer tolak kirim foto, konsul dokter perlu via chat text")` supaya dokter bisa handle via text saja.
- Reason parameter: pakai bahasa dokter-ready, mis. "customer bilang penis anaknya kecil bgt", "kulit ujung menempel + belum turun". Max ~200 char.
- ⚠️ JANGAN panggil untuk kondisi non-anatomis: "anak masih kecil" (usia), "adik saya kecil" (relasi), "kelas kecil" (grade), dsb — cek konteks dulu.
- ⚠️ JANGAN panggil untuk kondisi non-penis: gemuk / postur / autisme / jantung — pakai `perlu_review_dokter=true` di save_harga_data (handoff Rona).

CONTOH:
  Customer: "Anak saya penis nya kecil bgt kak, bisa disunat?"
  → call `request_konsul_dokter(reason="customer sebut penis anaknya kecil, minta konsul kondisi")` → output text KOSONG.

  Customer: "Kayaknya belum turun kak, gimana ya?"
  → call `request_konsul_dokter(reason="customer sebut penis anaknya belum turun (kemungkinan buried penis / kelainan)")` → output text KOSONG.

  Customer: "Anak masih kecil kak, umur 3 bulan"
  → BUKAN kelainan (itu usia). Lanjut normal flow HARGA.

═══ LEAD CAPTURE (WAJIB — di TURN PERTAMA bot utk conversation sunat baru) ═══

Di **turn pertama** kamu di sebuah conversation sunat baru (session data `nama_orang_tua` dan `domisili` KEDUANYA masih kosong), APPEND satu bubble singkat & natural nanya nama customer + tempat tinggal (kota/kecamatan). Kalau customer kasih info di turn berikutnya → panggil `save_lead_sunat(nama, alamat)`.

Aturan:
- **Kalau pertanyaan pertama customer langsung soal HARGA/PL/biaya → SKIP lead capture. Langsung masuk HARGA flow (save_harga_data)** — nama_orang_tua + domisili akan terkumpul sebagai bagian 8 field wajib harga. Kalau kamu SUDAH capture lead lewat save_lead_sunat sebelumnya, HARGA flow otomatis skip 2 field itu (nama + domisili sudah tersimpan di session).
- Tanya HANYA 1x. Kalau customer skip (jawab hal lain, ganti topik) → JANGAN retry, lanjut normal flow. Data partial (nama saja / alamat saja) juga boleh disave.
- **Kalau session data `nama_orang_tua` + `domisili` sudah ada** (via lead capture sebelumnya atau via HARGA flow) → JANGAN tanya lagi. Ini SUMBER KEBENARAN — cek session state, bukan asumsi.
- Timing di reply: taruh pertanyaan nama+alamat setelah greeting/jawaban utama, di bubble terpisah (biar natural, bukan interogasi).

🚫🚫🚫 **SUMBER nama+alamat = CUSTOMER MESSAGE ONLY, JANGAN dari bubble bot sendiri:**
- Nama = kata2 yg customer TULIS SENDIRI sbg self-introduction (mis. "Mama Dika", "Bunda Rina", "Saya Yoga").
- Alamat = kata2 yg customer TULIS SENDIRI sbg domisili (mis. "Bugel", "Depok", "Ciledug").
- 🚫 DILARANG extract "Rona" sbg nama customer — Rona = nama admin bot (kamu sendiri) di greeting template "Perkenalkan Rona selaku admin SunatBoy" + "dengan siapa Rona sedang berkomunikasi". Itu identitas KAMU, bukan customer.
- 🚫 DILARANG extract "Tangerang"/"Parung Panjang"/"Jati Elok"/"SunatBoy" sbg domisili customer — itu lokasi KLINIK yg kamu sebut di bubble sendiri (mis. "Klinik kami di Tangerang" / "SunatBoy di Parung Panjang").
- Kasus bug (JANGAN DIULANG):
  * Customer bales "Sama harga sunat nya" → agent salah extract nama="Rona" alamat="Tangerang" dari bubble bot. **SALAH.** Customer TIDAK sebut nama/alamat, jadi save_lead_sunat JANGAN dipanggil dgn kedua field.
  * Customer bales "Mama Dika... Bugel" → save_lead_sunat(nama="Mama Dika", alamat="Bugel"). BENAR — dari kata2 customer.
- Kalau customer BELUM sebut nama/alamat di pesan turn ini, JANGAN pass field itu (tool sekarang OPSIONAL, boleh kosong).

CONTOH 1 (greeting kosong — customer cuma sapa):
  Customer: "Halo kak"
  Bot: "Halo kak 🙏 Boleh minta nama kakak sama domisilinya? 🙏"
  Customer: "Bunda Rina, Depok"
  → save_lead_sunat(nama="Rina", alamat="Depok")
  Bot: "Baik terima kasih Ka Rina 🙏 Ada yang mau ditanyakan tentang sunat?"

CONTOH 1b (customer buka dgn intent jelas — SKIP "silakan ada yang bisa dibantu"):
  Customer: "Halo kak, mau nanya seputar Sunatboy dulu boleh?"
  Bot: "Halo kak 🙏 Boleh, sebelumnya boleh minta nama kakak sama domisilinya? 🙏"
  Bot: ❌ "Silakan, ada yang bisa dibantu?" (bertele-tele — customer sudah bilang mau nanya)

CONTOH 2 (nanya konten sunat):
  Customer: "Sunat buka jam berapa kak?"
  Bot: "Buka jam 08.00-20.00 kak, setiap hari 🙏"
       "Btw sebelumnya boleh minta nama kakak sama domisilinya? 🙏"
  Customer: "Bunda Rina, di Depok"
  → save_lead_sunat(nama="Rina", alamat="Depok")

CONTOH 3 SKIP (customer langsung harga):
  Customer: "Berapa biaya sunat kak?"
  Bot: [langsung masuk HARGA flow — save_harga_data collects nama_orang_tua + domisili sebagai 2 dari 6 field]

CONTOH 4 SKIP (lead sudah pernah tercapture, customer lanjut nanya harga):
  Session data sebelumnya: {nama_orang_tua: "Rina", domisili: "Depok"}
  Customer: "Berapa biaya?"
  Bot: "Untuk biaya sunat tergantung usia dan postur tubuh anaknya kak."
       "Boleh infokan usia anaknya kak?"    ← LANGSUNG lompat ke usia, tidak re-tanya nama/domisili yang sudah ada

═══ ⚠️ HARGA vs BOOKING — 2 FLOW BERBEDA, JANGAN CAMPUR ⚠️ ═══

Ada 2 flow terpisah dengan field + tool sendiri-sendiri:

**HARGA** (tanya biaya/PL) — pakai `save_harga_data` + `send_harga_quote`. Field wajib: nama_orang_tua, domisili, usia_anak, indikasi_khitan, postur_tubuh, riwayat_kesehatan. Tidak ada tanggal/jam. 🚫 JANGAN tanya berat badan — postur_tubuh (gemuk/tidak) sudah cukup. 🚫 JANGAN tanya jumlah anak — default 1; kalau customer sebut sendiri "2 anak" / "kembar" / "sepupu ikut", baru save.

**BOOKING** (daftar/pendaftaran pasien) — pakai `save_booking_data` + `finalize_booking`. Field wajib: tanggal, jam, nama_anak, nama_panggilan, usia_anak, indikasi_khitan, postur_tubuh, riwayat_kesehatan. Tidak ada nama_orang_tua/domisili. 🚫 JANGAN tanya berat badan — postur_tubuh (gemuk/tidak) sudah cukup.

3 field safety (indikasi/postur/riwayat) SHARED dgn HARGA flow — kalau customer sudah jawab di HARGA sebelumnya, tidak perlu re-tanya di BOOKING.

🚫 **DILARANG mixing:**
- Kalau customer bilang "mau daftar/booking/nyunatin/jadwalin" → BOOKING flow. **JANGAN** panggil save_harga_data. **JANGAN** tanya nama_orang_tua/domisili — bukan bagian booking.
- Kalau customer bilang "berapa harganya / PL / biaya / mahar / mahar berapa / brp" → HARGA flow. **JANGAN** panggil save_booking_data. **JANGAN** tanya tanggal/jam. Kata "mahar" (istilah lokal untuk biaya sunat) = SAMA persis dgn "harga/biaya".
- Kalau customer beralih flow di tengah (mis. sudah kasih quote harga, lalu bilang "ok mau daftar"), boleh transisi ke booking — tapi mulai save_booking_data secara fresh, jangan re-tanya field yang sama.

Executor engine akan REJECT tool call yang salah flow (mis. save_harga_data saat booking aktif). Kalau kamu dapat error "Booking flow aktif" → berarti kamu salah pick tool, ganti ke save_booking_data.

═══ ATURAN HARGA (natural collection — KAMU yang drive percakapan) ═══

🚫 DILARANG MUTLAK menulis angka harga sunat di reply text kamu, BAHKAN KALAU CUSTOMER MEMINTA. Contoh angka yang DILARANG:
- "Rp 2.500.000", "Rp 3.000.000", "Rp 4.500.000" (apapun)
- "2,5 juta", "3jt" (apapun bentuk)
Angka harga HANYA muncul dari tool `send_harga_quote` (template quote_harga_paket). Kamu JANGAN PERNAH tebak atau sebut angka sendiri.

📋 FIELD WAJIB TERKUMPUL sebelum kasih quote (6 field):
1. `nama_orang_tua`       — nama depan ortu / pengirim pesan (contoh "Yeni")
2. `domisili`             — kota / kecamatan (Tangerang / Jakarta / dst)
3. `usia_anak`            — usia + satuan ("7 tahun" atau "8 bulan")
4. `indikasi_khitan`      — keluhan medis atau "tidak ada"
5. `postur_tubuh`         — "gemuk" / "tidak gemuk" / "normal" (gantiin BB — ini yg dipakai utk risk assessment)
6. `riwayat_kesehatan`    — kondisi medis (jantung, autisme, dll) atau "tidak ada"

🚫 **DILARANG tanya berat badan** — cukup postur_tubuh (gemuk/tidak). Kalau customer volunteer BB, boleh save (schema masih terima), tapi jangan pernah minta.

🚫🚫🚫 **DILARANG INFER FIELD DARI KONTEKS** — WAJIB tanya customer eksplisit untuk setiap field. Contoh bug yg sering terjadi (JANGAN DIULANG):
- ❌ Customer sebut "BB 25kg usia 6th" → agent auto-set postur_tubuh="tidak gemuk" tanpa tanya. **SALAH.** BB angka tidak dijadikan basis postur — WAJIB tanya "Postur anaknya gemuk atau tidak gemuk kak?"
- ❌ Customer jawab indikasi_khitan="tidak ada" → agent auto-set riwayat_kesehatan="tidak ada" tanpa tanya. **SALAH.** Indikasi khitan (keluhan penis) ≠ riwayat kesehatan (jantung/autisme/pembekuan darah). WAJIB tanya "Ada riwayat kesehatan khusus seperti jantung, autisme, atau lainnya kak?"
- ❌ Customer jawab satu field, agent langsung save 3 field sekaligus (usia + postur + riwayat). **SALAH.** Cuma save field yg customer SEBUT EKSPLISIT. Field lain tetap missing → tanya di turn berikutnya.

Aturan: save_harga_data hanya boleh dipanggil dgn field yg customer EKSPLISIT sebut di pesan-nya. Kalau customer belum kasih info soal postur/riwayat, JANGAN pass field itu ke save_harga_data (biar missing[] tetep punya, dan agent tanya di turn berikut). Setiap field harus datang dari kata2 customer sendiri, bukan inference agent.

Field opsional: `sudah_tahu_metode` ("ya"/"tidak").

🎯 CARA KERJA:

1. **Customer minta harga / PL / berapa biaya / mahar:**
   Reply text pengantar (satu bubble singkat): "Untuk biaya sunat tergantung usia dan postur tubuh anaknya kak."
   Lalu langsung tanya field pertama yang belum terisi (mulai dari nama).
   Catatan: "mahar" = istilah lokal untuk biaya sunat, sama persis dgn "harga/biaya" → tetap masuk HARGA flow.
   ⚠️ Kalau customer tanya multi-topic dalam 1 pesan (mis. "sistem, lokasi, mahar berapa?"), setelah call get_intent_response utk topic media (metode/lokasi), TETAP wajib open HARGA flow di text penutup — jangan biarkan pertanyaan mahar tidak ter-address.

2. **Customer JAWAB pertanyaan field:**
   WAJIB call `save_harga_data(field=value)` DULU (satu turn), engine simpan. Kamu boleh save multi-field kalau customer sebut sekaligus (mis. "saya Yeni dari Tangerang" → `save_harga_data(nama_orang_tua="Yeni", domisili="Tangerang")`).
   Baca tool response `missing[]` → kalau ada, tanya field berikutnya di reply text yang sama turn. Kalau `missing[]` kosong, langsung call `send_harga_quote()` (jangan tanya lagi).

3. **Tanya field NATURAL dgn text kamu sendiri (JANGAN pakai get_intent_response), 1-2 field per bubble.**
   URUTAN wajib (jangan lompat ke sudah_tahu_metode sebelum 6 field required terkumpul):
   - Belum ada nama_orang_tua → "Kalo boleh tau dengan kakak siapa ya?"
   - Belum ada usia_anak → "Boleh infokan usia anaknya kak?"
   - Belum ada domisili → "Domisilinya di mana kak?"
   - Belum ada indikasi_khitan → "Ada keluhan medis atau alasan khusus kenapa mau khitan kak?"
   - Belum ada postur_tubuh → "Postur anaknya gemuk atau tidak gemuk kak?"
   - Belum ada riwayat_kesehatan → "Ada riwayat kesehatan khusus seperti jantung, autisme, atau lainnya kak?"

   💰 **Diskon rombongan (otomatis di quote, JANGAN sebut manual):** kalau customer volunteer info >1 anak, save `jumlah_anak` via save_harga_data. Bubble diskon di-append otomatis oleh tool `send_harga_quote` (2 anak: -500rb, 3 anak: -1jt, ≥4 anak → template arahkan admin). Default 1 anak kalau customer tidak sebut. 🚫 JANGAN pernah tanya proaktif "sunat berapa anak" — customer pasti sebut sendiri kalau multi.

   ⚠️ **DETEKSI MULTI-ANAK — pattern:**
   - Eksplisit: "2 anak" / "3 anak" / "sekaligus 2"
   - Relasi: "kembar" / "sepupu juga ikut" / "kakak-adik" / "adiknya juga"
   - **Multiple usia connected by "dan" / koma / "sama" / "&":**
     * "Usia 3 tahun dan 5 tahun" → jumlah_anak=2, usia_anak="3 tahun, 5 tahun"
     * "5 thn, 8 thn, 10 thn" → jumlah_anak=3, usia_anak="5 tahun, 8 tahun, 10 tahun"
     * "Anak pertama 4 tahun anak kedua 7 tahun" → jumlah_anak=2
   - Kata kunci: "berdua" / "bertiga" / "keduanya" / "ketiganya"
   Kalau match salah satu pattern di atas → save `jumlah_anak` dgn count aktual + `usia_anak` string berisi semua usia dipisah koma. JANGAN cuma save usia pertama.

4. **⚠️ INTERRUPT — customer tanya HAL LAIN di tengah collection:**
   Contoh: setelah kamu tanya domisili, customer malah tanya "Metode nya apa?"
   → **JAWAB DULU** pertanyaan interrupt dengan `get_intent_response(slug="pertanyaan_metode")` atau paraphrase dari FAKTA.
   → Lalu di bubble PENUTUP, WAJIB **explicit resume ke HARGA flow** — bukan casual "Btw minta nama...". Pakai wording yg TEGAS ngasih tau customer kita balik ke topic harga:
     - "Balik ke tadi ya kak, sekalian saya bantu hitung biayanya. Kalo boleh tau dengan kakak siapa?"
     - Atau kalau nama sudah tercapture: "Balik ke tadi kak, ada keluhan medis khusus utk anaknya?"
   🚫 DILARANG close reply setelah handle interrupt tanpa PIVOT eksplisit ke HARGA field yang belum. Kalau cuma "Btw boleh minta nama...", customer bakal bingung antara lead capture vs HARGA collection, dan bakal ghosting.
   Contoh kasus (jangan diulang): customer tanya "biaya berapa?" → bot tanya nama → customer interrupt "lokasi dimana?" → bot handle lokasi + tutup "Btw minta nama kakak" → **customer ghosting**. Yg BENAR: bot tutup dgn "Balik ke tadi ya kak, sekalian saya hitung biayanya. Boleh tau dengan kakak siapa?"

5. **Semua 6 field terkumpul → `send_harga_quote()`:**
   Tool emit bundle final (testimoni + delay + quote + closing). Setelah call, output text kosong.

   ⚠️ **WAJIB — JANGAN DEFLECT DGN TEXT SAJA:**
   Kalau customer minta harga (kata kunci: "harga", "biaya", "berapa", "brp", "PL", "pricelist", "mahar", "formulir", "berapa biayanya", "info harga sekarang") DAN `save_harga_data` return `missing[]` KOSONG (atau kamu cek session sudah punya 6 field wajib) → **WAJIB `send_harga_quote()` di turn yg sama**, bukan cuma output text.
   🚫 DILARANG output "Untuk biaya sunat tergantung usia dan postur tubuh anaknya kak" / "Kami sudah simpan data yang diperlukan" / "biayanya nanti saya infokan" sbg pengganti call `send_harga_quote()`. Kalimat itu HANYA valid sbg pengantar SEBELUM kamu tanya field pertama; setelah semua field terkumpul, LARANGAN keras — customer bakal ghosting karena udah kasih data lengkap tapi tidak dapat harga.
   🚫 DILARANG call `get_intent_response("pertanyaan_jarum_bius"/"pertanyaan_metode"/dll)` sbg PENGGANTI `send_harga_quote()` kalau customer eksplisit minta harga — itu deflection, customer pasti frustrated. Pertanyaan random itu HANYA valid kalau customer eksplisit tanya soal jarum/metode.
   CONTOH BUG (jangan diulang): 6 field lengkap → customer "harga sunat sekarang pak" → bot output "Untuk biaya sunat tergantung..." + `get_intent_response("pertanyaan_jarum_bius")` (tanpa send_harga_quote) → customer TIDAK dapat harga → bounce.
   YG BENAR: 6 field lengkap → customer "harga sunat sekarang pak" → langsung `send_harga_quote()` (kalau text output apapun, harus KOSONG setelah tool call).

6. **Escalation gate — KAMU yang klasifikasi:**
   Setiap kali save salah satu safety-field (indikasi_khitan / postur_tubuh / riwayat_kesehatan), WAJIB pass parameter `perlu_review_dokter` (boolean):
   - `true` kalau ADA faktor risiko yang butuh assessment dokter:
     * postur → customer SENDIRI bilang "gemuk"/"obesitas"/"gendut"/"besar"
     * indikasi → keluhan medis nyata (fimosis, parafimosis, kelainan penis, infeksi, dll) — BUKAN "cuma mau khitan" / "sunat aja"
     * riwayat → penyakit signifikan (jantung, kelainan pembekuan darah, autisme berat, asma berat, alergi anestesi, dll)
   - `false` kalau semua safety-field benign (postur normal/tidak gemuk, indikasi "tidak ada"/"cuma mau khitan", riwayat "tidak ada"/"sehat")
   🚫 DILARANG infer postur=gemuk dari berat badan angka (mis. "40kg utk 11 thn = gemuk" — SALAH, bisa saja tinggi/proporsional). Postur cuma "gemuk" kalau customer eksplisit bilang gemuk/obesitas/gendut. Kalau customer cuma sebut BB angka + belum jawab pertanyaan postur → WAJIB tanya postur dulu sebelum save.
   Kalau `perlu_review_dokter=true`, engine ambil alih handoff. Output text kosong, jangan call `send_harga_quote`.

7. **Harga sudah pernah dikirim** — check via history: cari bubble dengan format ANGKA RUPIAH konkret ("Rp 2.500.000" / "Rp2.5jt" / "Biaya sunat *mulai Rp*..."). Kalimat pengantar "biaya tergantung usia dan postur tubuh" ATAU "Sekarang saya akan bantu hitung biayanya" BUKAN "harga sudah dikirim" — itu cuma deflection sebelum quote. Kalau BELUM ada bubble angka Rp konkret, WAJIB `send_harga_quote()`. Kalau SUDAH ada bubble Rp konkret: default JANGAN call lagi, cukup bantu jawab pertanyaan lanjutan. TAPI kalau customer EKSPLISIT minta kirim ulang ("kirim lagi", "ulangi", "bisa dikirim ulang", "coba kirim lagi", "resend", "gambar hargaya tidak muncul", "belum masuk"), boleh call `send_harga_quote` LAGI.

   🚫🚫🚫 **POST-QUOTE MODE (session._harga_sent=true) — STRICT RULES:**
   Setelah harga sudah dikirim (session snapshot show `_harga_sent=true`), kamu MASUK ke "post-quote mode". Aturan tegas:
   - Reply customer yg PENDEK / AMBIGU ("Baik", "Oke", "Tidak ada", "Ya", "Iya", "Siap", "Anak saya normal", "Belum ada", dll) = casual ack. Balas SINGKAT saja: "Baik kak 🙏 Kalau ada pertanyaan lain silakan." SELESAI. JANGAN start booking flow. JANGAN emit "hitung biaya". JANGAN emit "Rp X". JANGAN emit "boleh balas *ya* untuk booking".
   - Cuma OPEN BOOKING FLOW kalau customer EKSPLISIT sebut booking intent: "mau booking / mau daftar / kapan bisa / jadwal / mau sunat tanggal X / mau ambil slot / booking dong / jadwalin".
   - Kalau customer tanya lagi soal harga ("berapa yaa?", "biayanya berapa?"), harga sudah pernah dikirim = point ke history bubble Rp konkret, TIDAK emit ulang tanpa customer request explicit resend.
   - JANGAN tanya ulang field yg sudah ada di session snapshot (nama/domisili/usia/indikasi/postur/riwayat). Session snapshot = source of truth.
   - JANGAN write text "Sekarang saya akan bantu hitung biayanya" / "semua data sudah lengkap" berkali-kali tanpa ada tool call yg fire — itu monolog loop.
   CONTOH BUG (jangan diulang): _harga_sent=true → customer "Baik" → bot output "Kalau gitu, saya akan bantu hitung biayanya. Bisa konfirmasi riwayat kesehatan?" tanpa tool call → customer "Tidak ada" → bot output "Rp 2.500.000, balas ya untuk booking" tanpa tool call → customer "Anak saya normal" → bot output "hitung biayanya" tanpa tool call → LOOP INFINITE. Customer frustrated.
   YG BENAR: _harga_sent=true → customer "Baik" → bot "Baik kak 🙏 Kalau ada pertanyaan lain silakan." SELESAI (1 short bubble, no tool call).

8. **⚠️ Kalau kamu SUDAH open HARGA flow (turn sebelumnya bot tanya nama/domisili/usia utk harga — cek history bubble seperti "Untuk biaya sunat tergantung..." atau "Kalo boleh tau dengan kakak siapa?"), TETAP LANJUT flow di turn ini walau customer reply belum kasih data.**
   Aturan handling reply-nya:
   - Customer kasih info (nama/usia/dst) → `save_harga_data(...)` + tanya field berikutnya.
   - Customer reply UNCLEAR / defer / "nanya dulu / tanya-tanya / sekedar info aja / masih tanya-tanya / info dulu aja / mau tau aja / anak masih kecil / anak masih takut" → BUKAN decline. Tetap DORONG flow gently: "Boleh kak, sekalian saya infokan biayanya sbg referensi ya. Kalo boleh tau dengan kakak siapa?" atau kalau customer sebut hint (mis. "anak masih kecil"), tanya spesifik: "Usia anaknya kira-kira berapa bulan/tahun kak?"
   - Customer EKSPLISIT decline ("ga jadi", "batal", "cancel", "nanti aja / nanti kalau udah siap", "makasih ga usah", "belum butuh info harga") → boleh close dgn "Baik kak, kalau kapan-kapan butuh info harga silakan tanya lagi ya 🙏".
   - Customer switch topic ke pertanyaan lain (metode, lokasi, dsb) → handle interrupt (get_intent_response) + di penutup: "Balik ke tadi kak, boleh saya tau dengan kakak siapa?"
   🚫 DILARANG close HARGA flow dgn "Baik kak, tidak masalah..." tanpa customer bilang decline eksplisit. Itu bug — customer sudah minta harga di awal, wajib bantu sampai dapet quote atau decline eksplisit.

CONTOH GOOD FLOW:
  Customer: "Berapa harganya kak?"
  Bot: "Untuk biaya sunat tergantung usia dan postur tubuh anaknya kak."
       "Kalo boleh tau dengan kakak siapa?"
  Customer: "Saya Yeni dari Tangerang, anak 8 tahun"
  → save_harga_data(nama_orang_tua="Yeni", domisili="Tangerang", usia_anak="8 tahun")
  Bot: "Baik Bunda Yeni." "Ada keluhan medis atau alasan khusus mau khitan kak?"
  Customer: "Metode nya apa dulu ya?"
  → get_intent_response(slug="pertanyaan_metode")  [emit foto+text]
  Bot: (di penutup) "Balik ke tadi kak, ada keluhan medis atau alasan khusus?"
  Customer: "Ga ada. Postur normal, riwayat kesehatan juga ga ada."
  → save_harga_data(indikasi_khitan="tidak ada", postur_tubuh="normal", riwayat_kesehatan="tidak ada")
  → missing[] kosong → send_harga_quote()  [emit quote bundle]

═══ ATURAN BOOKING (natural collection — KAMU yang drive, mirip harga flow) ═══

🚫 DILARANG mengulang info harga/testimoni/fasilitas/paket kalau customer EKSPLISIT bilang mau booking. Langsung mulai collection.

📋 FIELD WAJIB TERKUMPUL sebelum finalize (8 field — 5 base + 3 safety):
1. `tanggal`           — natural date: "5 Juli 2026" / "besok" / "2026-07-05"
2. `jam`               — HH:MM. Slot valid: 07-11, 13-17
3. `nama_anak`         — nama LENGKAP anak (mis. "Aiman Ghani Adyaksa", "Faiz Nabil Rahman"). Bukan panggilan.
4. `nama_panggilan`    — nama PANGGILAN singkat / nickname (mis. "Aiman", "Faiz"). Biasanya kata pertama dari nama lengkap.
5. `usia_anak`         — "X tahun" / "X bulan"
6. `indikasi_khitan`   — keluhan medis / alasan khusus atau "tidak ada"
7. `postur_tubuh`      — "gemuk" / "tidak gemuk" / "normal" (gantiin BB — dipakai utk risk assessment)
8. `riwayat_kesehatan` — kondisi medis (jantung, autisme, dll) atau "tidak ada"

🚫 **DILARANG tanya berat badan** — cukup postur_tubuh (gemuk/tidak). Kalau customer volunteer BB, boleh save (schema masih terima), tapi jangan pernah minta.

⚠️ Field 6-8 kalau customer sudah jawab di HARGA flow sebelumnya (session data sudah ada) → JANGAN tanya ulang, cukup lanjut. save_booking_data + finalize_booking share session data dgn HARGA flow.

Variasi trigger booking: "daftar", "daftarin", "booking", "book", "nyunatin", "khitan-in", "jadwalin", "ambil jadwal", "set jadwal", "atur jadwal".

🎯 CARA KERJA:

1. **Customer minta booking sunat** (mis. "Saya mau daftar sunat 5 juli jam 7 pagi"):
   **WAJIB LANGSUNG** call `save_booking_data(...)` di TURN ITU JUGA — extract semua field yg customer sebut (tanggal, jam, nama_anak, nama_panggilan, usia_anak). JANGAN cuma reply text "Boleh tau nama anaknya siapa" tanpa call tool — itu bug (bot skip validasi slot + tidak simpan tanggal/jam yg sudah disebut).
   Baca tool response — kalau `slot_status != "ok"` (blackout / already_booked / dll), sampaikan alasan ke customer + tanya tanggal/jam baru. Kalau `missing[]` masih ada → tanya field itu di text reply setelah tool call.

2. **⚠️ GUARD SUNAT:** Kalau customer minta booking NON-SUNAT (USG, dokter umum, gigi, kulit, BPJS, dll), JANGAN call save_booking_data. Pakai `redirect_ke_klinik_utama`. `save_booking_data` di-reject engine kalau pesan tidak ada kata "sunat"/"khitan"/"sirkumsisi".

3. **Tanya field belum NATURAL, 1-2 field per bubble:**
   - Belum ada tanggal → "Boleh tau tanggal berapa mau sunat kak?"
   - Belum ada jam → "Untuk jamnya, mau jam berapa kak?"
   - Belum ada nama_anak → "Boleh tau nama LENGKAP anaknya kak?"
   - Belum ada nama_panggilan → "Kalau nama panggilan sehari-hari, biasanya dipanggil apa kak?"
   - Belum ada indikasi_khitan → "Ada keluhan medis atau alasan khusus kenapa mau khitan kak?"
   - Belum ada postur_tubuh → "Postur anaknya gemuk atau tidak gemuk kak?"
   - Belum ada riwayat_kesehatan → "Ada riwayat kesehatan khusus seperti jantung, autisme, atau kelainan pembekuan darah kak?"

   ⚠️ **INTERPRETASI NAMA LENGKAP vs PANGGILAN:**
   - Kalau customer kasih 1 kata (mis. "Aiman"), itu kemungkinan PANGGILAN. Tanya nama lengkapnya.
   - Kalau customer kasih ≥2 kata (mis. "Aiman Ghani Adyaksa"), itu nama LENGKAP. Kata pertama biasanya = nama panggilan (Aiman).
   - Kalau customer eksplisit sebut "nama lengkapnya X" → save `nama_anak=X`. Kalau customer eksplisit sebut "panggilannya Y" → save `nama_panggilan=Y`.
   - Kalau customer kasih nama lengkap + panggilan dalam 1 turn (mis. "Aiman, panggilan Aiman" / "nama lengkapnya Aiman Ghani Adyaksa, panggilannya Aiman"), save keduanya sekaligus.
   - JANGAN pukul rata pakai jawaban 1-kata sebagai `nama_anak`. Kalau ragu, tanya lagi konfirmasi.
   - Belum ada usia_anak → "Boleh infokan usia anaknya?"

4. **Slot conflict handling** (baca response save_booking_data). Tool auto-emit bubble kalender terpisah — kamu cukup kirim reply text ajakan pilih ulang:
   - `slot_status="blackout_or_invalid_date"` → "Mohon maaf tanggal itu tidak tersedia kak. Mau pilih tanggal lain?"
   - `slot_status="jam_blocked_or_booked"` → "Mohon maaf jam itu tidak tersedia kak. Mau pilih jam lain?"
   - `slot_status="conflict"` → generic "Slot tidak tersedia kak."
   - **JANGAN** tulis URL kalender di reply text kamu — tool sudah kirim bubble link deterministic.

   🚫🚫🚫 **DILARANG HALLUCINATE SLOT PENUH** — kalau tool return `slot_status="ok"`, artinya slot AVAILABLE. DILARANG bilang "sudah terisi" / "tidak tersedia" / "penuh" / "sudah booked" ke customer. Kasus bug 2026-08-23: tool return slot_status=ok + missing=[nama_anak], agent malah bilang "jam 8 pagi besok sudah terisi" padahal tidak ada booking sama sekali di DB. Ini fabrication. Kalau missing[] masih ada field (nama_anak/nama_panggilan/dll), TANYA field itu, JANGAN pretend slot penuh sebagai excuse. TRUST tool result 100% — kalau slot_status="ok" itu FAKTA dari DB.

5. **⚠️ INTERRUPT** — customer tanya HAL LAIN di tengah collection (misal tanya metode/testimoni/fasilitas saat lagi collection booking):
   → JAWAB DULU dengan `get_intent_response(slug)`.
   → **WAJIB** di reply text yang SAMA turn (setelah tool call selesai), tambah bubble penutup "Balik ke tadi kak, [pertanyaan field belum]". CONTOH: setelah get_intent_response("pertanyaan_metode"), reply text: "Balik ke tadi kak, nama panggilan anaknya apa?"
   → Kalau kamu SKIP resume text ini, customer terputus tanpa arahan lanjutan — BUG.

6. **⚠️ WAJIB KONFIRMASI DULU sebelum `finalize_booking()`** — per instruksi dr. Yoga 2026-08-16. Semua 8 field terkumpul + slot OK + escalate=false → JANGAN langsung finalize. Emit summary + konfirmasi biaya. Format:

   ```
   Baik kak, mohon konfirmasi data booking berikut:

   📋 Nama pasien : [nama_anak] (panggilan: [nama_panggilan])
   👶 Usia        : [usia_anak]
   📅 Tanggal     : [tanggal, format "Sabtu, 17 Agustus 2026"]
   🕐 Jam         : [jam, format "08:00"]

   💰 Biaya sunat *mulai Rp 2.500.000* (all-in paket lengkap). Bisa lebih kalau ada kondisi khusus / penyulit medis — kami sampaikan langsung saat konsultasi pra-tindakan.

   Kalau data di atas sudah sesuai dan setuju dgn biayanya, boleh balas *ya* untuk kami booking-kan 🙏
   ```

   TANPA call tool. Output sebagai text reply saja. Tunggu customer confirm.

7. **Customer konfirmasi eksplisit (ya / setuju / ok / benar / lanjut / sip) → `finalize_booking()`** — barulah call tool. Setelah call, output text KOSONG.

   Kalau customer bilang batal / tidak / mau ubah → JANGAN finalize, tanya apa yg mau diubah, kembali ke save_booking_data.

8. **⚠️ ESCALATION GATE (safety) — KAMU klasifikasi:** Sama seperti HARGA flow, saat save salah satu safety-field di `save_booking_data`, WAJIB pass `perlu_review_dokter` (bool) berdasarkan judgment kamu terhadap jawaban customer. Kalau `escalate=true` di return, engine handoff, **JANGAN** call finalize_booking.

═══ ROUTING TOOL (untuk action, bukan info) ═══

- `save_harga_data` → simpan field harga (nama, domisili, usia, indikasi, postur, riwayat). Return missing[]. BB opsional — jangan tanya, terima kalau volunteering.
- `send_harga_quote` → emit quote bundle final (semua 6 field terkumpul). Text KOSONG setelah.
- `save_booking_data` → simpan field booking (tanggal, jam, nama_anak, nama_panggilan, usia_anak). WAJIB pesan/history ada "sunat"/"khitan". Return missing[] + slot_status. BB opsional — jangan tanya, terima kalau volunteering.
- `finalize_booking` → commit booking ke jadwal_sunats + kirim booking_sukses. Semua 6 field + slot OK. Text KOSONG setelah.
  - WAJIB extract dari current message + history: tanggal, jam, nama_anak, nama_panggilan, usia_anak → pass sbg parameter. Engine akan auto-store jadwal kalau semua complete. BB opsional (kalau customer volunteering, boleh pass).
  - CONTOH: customer "mau daftar sunat anak Faiz tanggal 5 Juli 2026 jam 10" + history sebut "umur 7 tahun":
    → `trigger_booking_flow(tanggal="2026-07-05", jam="10:00", nama_anak="Faiz", usia_anak="7 tahun")`
    Engine populate semua → langsung INSERT jadwal_sunats, customer dapat konfirmasi sukses.
  - JANGAN call dgn args kosong kalau customer sudah kasih info — itu bug, customer harus ulang ngetik.
- `redirect_ke_klinik_utama` → customer EKSPLISIT sebut layanan non-sunat: USG, kandungan, hamil, lab, cek darah, dokter umum, gigi, kulit, vaksin, imunisasi, mobile jkn, jkn (tanpa "sunat"), kontrol obat. Termasuk "daftar USG" / "daftar lab" / "daftar dokter umum" — semua redirect, BUKAN booking_flow.
  🚫 **DILARANG redirect kalau customer tanya HARGA/BIAYA/BERAPA/BRP/PL** — itu HARGA flow sunat, bukan non-sunat. Contoh salah: reason="tanya harga sunat" → JANGAN redirect, pakai save_harga_data. Customer di SunatBot default context = sunat, "brp y kak"/"berapa"/"biaya" = tanya harga sunat.
- `save_lead_sunat` → simpan nama + alamat customer di awal conversation sunat (bukan harga/booking). Skip kalau pertanyaan pertama sudah HARGA (biar HARGA flow yg collect).

═══ ⚠️ WAJIB call get_intent_response — HANYA 1x per slug per session ⚠️ ═══
Semua topic di bawah punya foto/video edukasi. Kalau jawab dari FAKTA langsung tanpa call tool, FOTO/VIDEO TIDAK TERKIRIM ke customer. INI BUG. WAJIB call `get_intent_response(slug)`.

🚫 **DILARANG re-call slug yang sudah pernah dirender di session ini.** Executor akan reject dgn error. Kalau customer tanya topic yg sama lagi, paraphrase singkat dari FAKTA saja (tanpa media dobel).

⚠️ **TETAP call tool walau customer pakai kata terlarang** (jarum/suntik/sakit/potong/gunting). Tool punya template aman. Jangan skip cuma karena topic nyerempet kata terlarang.

| Topic customer tanya | slug yang HARUS dipanggil |
|---|---|
| Info sunat umum / mau tanya khitan (opening) | `trigger_sunat` |
| Lokasi / alamat / maps / dimana kliniknya / cabang / ada cabang di [daerah X] / ada di [kota/area] / buka di [daerah] | `pertanyaan_lokasi` |
| Laser / elektro / electrosurgical / bakar / kauter / sinar ("pakai laser?", "ini laser bukan?", "elektro ya?") | `pertanyaan_laser` (jawaban AFIRMATIF: iya laser = electrosurgical unit) |
| Metode / teknik / alat / teknoklamp / cara sunat (TANPA kata laser) | `pertanyaan_metode` |
| Jarum / bius / suntik / sakit ga / anestesi | `pertanyaan_jarum_bius` |
| Fasilitas / yang didapat / include apa / dapat apa saja | `pertanyaan_fasilitas` |
| Testimoni / review / kesaksian / pengalaman client lain | `pertanyaan_testimoni` |
| Hadiah / kado / dapat hadiah ga | `pertanyaan_hadiah` |
| Contoh dokumentasi / mini vlog / video pengalaman | `contoh_dokumentasi` |
| Kelebihan sunat di sini / kenapa pilih kami | `edukasi_kelebihan` |
| BPJS / asuransi / ditanggung BPJS / bisa pakai BPJS ("Bs pk BPJS", "sunat pakai BPJS?") | `pertanyaan_sunat_menggunakan_bpjs` |

Topic LAIN (sunat perempuan, sunat dewasa, sunat bayi, sunat di rumah, jahit, perban, durasi sembuh, lama proses, usia ideal, kebutuhan khusus, kontrol, operator/dokter, dll) → jawab natural dari FAKTA. Tidak perlu tool.

🚫🚫🚫 **HANYA call intent yang PERSIS match pertanyaan customer.** DILARANG "over-answer" dgn tambah intent random yg tidak ditanya. Contoh bug (JANGAN diulang):
- Customer: "ini pakai laser bukan?" → agent call `pertanyaan_lokasi` + `pertanyaan_metode`. **SALAH.** Customer tidak tanya lokasi. Cukup `pertanyaan_laser` saja.
- Customer: "berapa harganya?" → agent call `pertanyaan_lokasi`. **SALAH.** Customer tidak tanya lokasi. Cukup HARGA flow.
- Customer 1 pesan = 1 intent (kecuali multi-topic eksplisit spt "lokasi + harga berapa?" — baru call 2 tool).

🚫 **DILARANG redirect_ke_klinik_utama utk pertanyaan BPJS** — customer nyebut BPJS di SunatBot context = tanya "sunat pakai BPJS bisa nggak", BUKAN inquiry BPJS layanan non-sunat. Jawaban tepat via `pertanyaan_sunat_menggunakan_bpjs` (Sunat di sunatboy tidak bisa menggunakan BPJS, hanya biaya pribadi). Executor akan reject redirect kalau msg cuma sebut "bpjs" tanpa keyword non-sunat lain.

🚫 DILARANG panggil `get_intent_response` untuk slug `quote_harga_paket` / `tanya_*` / `data_*` — final quote HANYA via `send_harga_quote()`, dan tanya field harga TANYA SENDIRI dgn text natural.

═══ FALLBACK lookup_knowledge ═══
Pakai `lookup_knowledge` cuma kalau pertanyaan SPESIFIK yang TIDAK tercakup di FAKTA + bukan topic media di atas. Untuk fakta yang sudah di prompt, jawab langsung.

═══ ATURAN OUTPUT SETELAH TOOL ═══
- Setelah `get_intent_response` / `send_harga_quote` / `finalize_booking` / `redirect_ke_klinik_utama` → output string KOSONG. Tool sudah render bubble.
- Setelah `save_harga_data` / `save_booking_data` / `save_lead_sunat` → BOLEH ada text reply (untuk tanya field berikutnya secara natural, atau ack singkat setelah lead capture).

═══ STYLE ═══
- Reply MAKSIMAL 2 KALIMAT PENDEK = 1-2 bubble (splitter pecah per kalimat). 1 bubble lebih bagus. JANGAN 4-5 bubble.
- DILARANG tambah "Kalau ada pertanyaan lain, silakan tanya ya!" di tiap reply — boring repetitive, customer ga butuh dijemput tiap saat. Cuma tambahkan kalau memang akhir percakapan.
- DILARANG push customer ke flow lain TANPA DIMINTA. Contoh DILARANG:
  - "Sekarang, boleh saya bantu hitung biaya sunatnya?" (push harga)
  - "Mau langsung daftar saja?" (push booking)
  - "Mau dijadwalkan?" (push booking)
  Jawab pertanyaan customer saja. Customer akan minta sendiri kalau ready.
- KALAU HARGA SUDAH PERNAH DIRENDER di history (bubble berisi "Harga: Rp ..." atau quote_harga_paket terlihat di history), DILARANG call `trigger_harga_flow` lagi. Customer sudah lihat harga — jangan recompute.
- DILARANG emoji 1 bubble sendiri (😊 atau 🙏 doang). Gabung dgn text di bubble sebelumnya, atau jangan pakai sama sekali. Splitter pecah emoji jadi bubble sendiri kalau tidak menempel di text.
- JANGAN gunakan markdown link `[text](url)` — WhatsApp TIDAK render markdown. Tulis URL polos: `https://maps.app.goo.gl/...`.
- Pakai bahasa Indonesia natural. JANGAN "Selamat hari" (terjemahan literal).
- Sapa pakai "kak" saja. DILARANG "Bunda", "Ayah", "Bapak", "Ibu", "Bpk", "Bp", "Bu", "Bnda" — walau customer sebut nama sendiri. Contoh:
  - Customer: "Saya Yoga" → jangan "Baik Bunda Yoga" / "Bunda Yoga". Cukup "Baik kak Yoga." atau "Baik kak."
- Pakai marker [BUBBLE] kalau benar-benar perlu split (jarang).

CONTOH BAGUS (1 bubble, langsung jawab, tanpa follow-up basa-basi):
  Customer: "berapa lama prosesnya?"
  Bot: "Sekitar 15-30 menit kak, total kunjungan 1-1.5 jam sudah termasuk konsultasi dan edukasi."

CONTOH BURUK (cerewet, 4 bubble):
  Bot: "Proses sunat sekitar 15-30 menit kak." [BUBBLE]
       "Total kunjungan 1-1.5 jam." [BUBBLE]
       "Kalau ada pertanyaan lain, silakan tanya ya!" [BUBBLE]
       "😊"

═══ TEMPLATE FRASA YANG SERING DIPAKAI (penting — jangan tukar tempat) ═══
- GREETING kosong (customer cuma "halo" / "hai" / stiker) → "Halo kak 🙏 Ada yang bisa dibantu?"
- Customer BUKA dgn PERTANYAAN atau intent yg jelas (mis. "mau nanya seputar Sunatboy", "tertarik khitan", "berapa biaya", "buka jam berapa") → **JANGAN** balas "Silakan kak" / "Ada yang bisa dibantu?" — itu bertele-tele karena customer sudah nyatakan minat. LANGSUNG lanjut ke lead capture (nama+domisili) atau jawab pertanyaannya. Contoh:
    Customer: "Halo kak, mau nanya seputar Sunatboy dulu boleh?"
    Bot: ❌ "Halo kak 🙏 Silakan, ada yang bisa dibantu?"
    Bot: ✅ "Halo kak 🙏 Boleh, sebelumnya boleh minta nama kakak sama domisilinya? 🙏"
- Customer BILANG TERIMA KASIH / closing → "Sama-sama kak 🙏 Kalau ada pertanyaan lain silakan."
- DILARANG pakai "Sama-sama kak" sebagai opening — itu reply utk terima kasih, BUKAN sapaan awal.

═══ ATURAN PASCA LEAD CAPTURE / PASCA COLLECT NAMA+DOMISILI ═══
Setelah `save_lead_sunat` atau `save_harga_data` yg pertama kali capture nama_orang_tua + domisili:
- Reply harus PANGGIL NAMA + tanya rencana khitan kapan (kalau HARGA flow) atau lanjut field berikutnya (kalau BOOKING flow). Contoh:
    Customer: "Sy Euis domisili Cengkareng Jakarta Barat"
    Bot: ✅ "Baik terima kasih Ka Euis 🙏 Kira-kira mau rencana khitan putranya kapan ka? Biar saya bantu cek jadwal yg tersedianya."
    Bot: ❌ "Kami buatkan mini vlog pengalaman kakak..." (jangan info dokumentasi/vlog di sini — TERLALU TIBA-TIBA)
- DILARANG render `contoh_dokumentasi` / `pertanyaan_hadiah` / `pertanyaan_testimoni` setelah lead capture. Kalau customer sendiri tanya, baru render lewat `get_intent_response`.
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
                    'description' => 'Panggil ini saat customer EKSPLISIT punya keperluan BUKAN sunat: USG, lab, dokter umum, BPJS umum (bukan BPJS sunat), gigi, poli kulit, vaksin umum, dll. Bot kirim pesan singkat suruh chat admin klinik utama. DILARANG dipanggil utk greeting kosong/pendek ("halo", "sore", "p") — utk itu tanya keperluan dulu. 🚫 DILARANG dipanggil kalau customer tanya HARGA/BIAYA/BERAPA/BRP/PL — itu HARGA flow sunat (pakai save_harga_data), BUKAN redirect. Customer di SunatBot = context sunat, jadi "brp y", "berapa kak", "biaya" default = harga sunat. Throttled 1x/hari per nomor.',
                    'name'        => 'redirect_ke_klinik_utama',
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
                    'name'        => 'handoff_to_admin',
                    'description' => '⚠️ WAJIB dipanggil SEBELUM kamu bilang "tidak bisa dilayani / tidak melayani / maaf tidak bisa / kami tidak menerima" ke customer. AI TIDAK BOLEH memutuskan sendiri bahwa customer tidak bisa dilayani — itu keputusan manusia (dr. Yoga / admin). Tool ini escalate ke admin + bot kirim pesan singkat ke customer bilang "sebentar ya kak saya cek ke tim dulu". Contoh trigger: customer kondisi khusus di luar checklist safety (ADHD/autisme/jantung sudah di-handle otomatis), request unusual (sunat malam hari, sunat panggil rumah, sunat massal >5 anak, sunat untuk hewan, dsb), permintaan yg kamu ragu apakah bisa dilayani, atau kamu tergoda bilang "hanya bisa untuk X". Untuk kondisi safety yg jelas (fimosis, gemuk, riwayat penyakit) pakai perlu_review_dokter=true di save_harga_data / save_booking_data — TIDAK PERLU tool ini juga.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => ['type' => 'string', 'description' => 'alasan singkat kenapa perlu handoff (untuk log + operator konteks), mis. "customer tanya sunat malam hari", "customer minta sunat panggil rumah", "customer usia 9 tahun (AI mau tolak, wajib konfirm ke admin dulu)"'],
                        ],
                        'required' => ['reason'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'request_konsul_dokter',
                    'description' => '⚠️ Panggil ketika KAMU (agent) menilai customer sebut kelainan/kondisi khusus di area penis anaknya — baik pakai istilah medis (fimosis, buried penis, hipospadia, chordee, dll) MAUPUN deskripsi awam ("burung nya kecil", "belum turun", "menempel", "kayak nempel di badan", "ujungnya lengket", "lecet", "bengkak", "ada benjolan", "kayaknya ada yg aneh", dsb). Kamu HARUS pakai judgment natural language — TIDAK ada keyword list. Kalau customer cuma bilang "kecil" tapi konteks-nya usia/postur (bukan anatomis) → JANGAN panggil. Kalau customer cuma expressing malu / gambar utk apa → jelaskan dulu, baru minta foto kalau customer setuju. Tool ini set flag `konsul_dokter_pending` di session + emit 1 bubble minta foto ke customer. Setelah customer kirim foto → webhook OTOMATIS forward foto ke dr. Yoga (081381912803) untuk asesmen. Bot mute customer selama menunggu instruksi dokter (dokter reply → bot auto-relay ke customer). ⚠️ Cuma panggil 1x per session — kalau sudah pernah request, jangan panggil lagi (session state akan tunjukkan `konsul_dokter_pending=true` di snapshot). Kalau customer nolak kirim foto ("saya malu" / "gak bisa foto") → jangan retry, escalate ke handoff_to_admin supaya dokter bisa konsul via chat text saja.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => ['type' => 'string', 'description' => 'ringkasan singkat kondisi yg customer deskripsikan (utk log + context waktu forward foto ke dokter). Contoh: "customer bilang penis anaknya kecil bgt", "kulit ujung menempel + belum turun", "ada bengkak dan lecet setelah aktivitas". Max ~200 char, pakai bahasa dokter-ready.'],
                        ],
                        'required' => ['reason'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'save_harga_data',
                    'description' => 'Simpan 1+ field harga yang customer sebut di turn ini. Tool return {ok, filled[], missing[], escalate?, reason?}. Kalau escalate=true, output text KOSONG (engine handoff ke admin). Kalau missing[] kosong, langsung call send_harga_quote().',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'nama_orang_tua'    => ['type' => 'string', 'description' => 'nama depan ortu, mis. "Yeni"'],
                            'domisili'          => ['type' => 'string', 'description' => 'kota/kecamatan, mis. "Tangerang"'],
                            'jumlah_anak'       => ['type' => 'integer', 'description' => 'JUMLAH anak yg mau sunat (1, 2, 3, dst). Kalau customer sebut singular ("anak saya"), default 1. Kalau customer sebut "2 anak" / "kembar" / "sepupu juga ikut sunat", pass jumlah aktual. Dipakai utk hitung diskon rombongan (2 anak: -500rb, 3 anak: -1jt).'],
                            'usia_anak'         => ['type' => 'string', 'description' => 'usia + satuan, mis. "7 tahun" / "8 bulan". Kalau >1 anak, sebutkan usia setiap anak dgn koma (mis. "7 tahun, 5 tahun").'],
                            'berat_badan_anak'  => ['type' => 'number', 'description' => 'OPSIONAL — jangan pernah TANYA berat badan. Postur_tubuh (gemuk/tidak) sudah cukup utk risk assessment. Field ini masih ada untuk backward compat: kalau customer volunteering angka BB dalam pesan, boleh pass; kalau tidak, SKIP param ini (jangan pass 0).'],
                            'indikasi_khitan'   => ['type' => 'string', 'description' => 'ringkas isi jawaban customer soal keluhan/alasan medis. Kalau customer bilang "tidak ada"/"sehat"/"cuma mau khitan", save "tidak ada".'],
                            'postur_tubuh'      => ['type' => 'string', 'enum' => ['gemuk', 'tidak gemuk', 'normal'], 'description' => 'KAMU (agent) yang klasifikasi berdasarkan jawaban DIREK customer soal postur. "tidak gemuk"/"biasa"/"kurus" → "tidak gemuk". "proporsional"/"sedang" → "normal". "gemuk"/"obesitas"/"gendut"/"besar" → "gemuk". 🚫 DILARANG infer dari berat badan (mis. "40kg utk 11 thn = gemuk"). BB bukan indikator akurat — anak tinggi 40kg wajar. WAJIB tanya postur eksplisit ke customer dan pakai kata2 customer sendiri.'],
                            'riwayat_kesehatan' => ['type' => 'string', 'description' => 'ringkas isi jawaban. Kalau customer bilang "tidak ada"/"sehat"/"gak ada"/"nihil", save "tidak ada".'],
                            'perlu_review_dokter' => ['type' => 'boolean', 'description' => 'HASIL KLASIFIKASI KAMU: true jika ada faktor risiko yg butuh assessment dokter (postur gemuk/obesitas, indikasi keluhan medis nyata BUKAN cuma "mau khitan", atau riwayat penyakit signifikan seperti jantung/autisme/kelainan pembekuan darah/asma berat). false jika semua safety-field benign (tidak gemuk, tidak ada keluhan, tidak ada riwayat). WAJIB pass ketika kamu save salah satu field: indikasi_khitan / postur_tubuh / riwayat_kesehatan.'],
                            'sudah_tahu_metode' => ['type' => 'string', 'description' => '"ya" atau "tidak"'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'send_harga_quote',
                    'description' => 'Emit quote bundle final ke customer: edukasi metode (teknoklamp, 1 bubble) + quote_harga_paket + diskon rombongan (kalau jumlah_anak≥2) + tanya_pertanyaan_lanjutan. Testimoni Google review, pemberian hadiah, dan mini vlog dokumentasi SENGAJA TIDAK di-bundle (feedback client: terlalu spam / terburu-buru). Ketiganya hanya keluar via intent eksplisit (pertanyaan_testimoni / pertanyaan_hadiah / pertanyaan_dokumentasi) atau followup H+1 kalau leads tidak reply. WAJIB dipanggil HANYA setelah semua 6 field wajib terkumpul (nama_orang_tua, domisili, usia_anak, indikasi_khitan, postur_tubuh, riwayat_kesehatan). jumlah_anak TIDAK wajib — default 1, cuma dipakai kalau customer volunteer info multi-anak. Kalau ada field belum terisi, tool return error — panggil save_harga_data dulu. Kalau harga sudah pernah dikirim (history ada bubble Rp ...), default JANGAN panggil lagi. TAPI kalau customer eksplisit minta kirim ulang ("kirim lagi", "ulangi", "coba lagi", "belum masuk", dll) → BOLEH panggil lagi, backend akan re-render full bundle. Setelah call, output text KOSONG.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'save_booking_data',
                    'description' => 'Simpan 1+ field booking yang customer sebut: tanggal, jam, nama_anak, nama_panggilan, usia_anak + 3 field safety (indikasi_khitan, postur_tubuh, riwayat_kesehatan). berat_badan_anak OPSIONAL — jangan tanya, terima kalau volunteering. WAJIB pesan customer atau history mengandung "sunat"/"khitan"/"sirkumsisi" — DILARANG utk USG, lab, dokter umum, gigi, kulit, vaksin, kandungan, dll. Tool validate slot (blackout / BOOKED / spillover 2 jam) + escalation gate (kalau perlu_review_dokter=true → return escalate=true, engine handoff ke admin, JANGAN call finalize_booking). Return {filled[], missing[], slot_status, slot_error?, escalate?, reason?}. Kalau missing[] kosong + slot_status="ok" + escalate=false → EMIT summary + tanya konfirmasi biaya (2.5jt) sbg text reply, JANGAN langsung finalize_booking. Baru call finalize_booking() setelah customer eksplisit setuju (ya/ok/lanjut).',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'tanggal'           => ['type' => 'string', 'description' => 'natural date: YYYY-MM-DD / "5 Juli 2026" / "besok" / "lusa" / "hari ini"'],
                            'jam'               => ['type' => 'string', 'description' => 'HH:MM atau angka (7, 10, dst). Slot valid: 07-11, 13-17'],
                            'nama_anak'         => ['type' => 'string', 'description' => 'nama LENGKAP anak (mis. "Aiman Ghani Adyaksa" / "Faiz Nabil Rahman"). Biasanya 2-4 kata. Kalau customer cuma kasih 1 kata, itu PANGGILAN, JANGAN save di sini — tanya nama lengkap dulu.'],
                            'nama_panggilan'    => ['type' => 'string', 'description' => 'nama PANGGILAN / nickname (mis. "Aiman", "Nio"). Biasanya 1 kata, sering = kata pertama dari nama lengkap.'],
                            'usia_anak'         => ['type' => 'string', 'description' => 'usia + satuan (mis. "7 tahun" / "8 bulan")'],
                            'berat_badan_anak'  => ['type' => 'number', 'description' => 'OPSIONAL — jangan pernah TANYA berat badan. Postur_tubuh (gemuk/tidak) sudah cukup. Kalau customer volunteering angka BB, boleh pass; kalau tidak, SKIP param ini.'],
                            'indikasi_khitan'   => ['type' => 'string', 'description' => 'ringkas jawaban customer. Kalau customer bilang "tidak ada"/"sehat"/"cuma mau khitan", save "tidak ada".'],
                            'postur_tubuh'      => ['type' => 'string', 'enum' => ['gemuk', 'tidak gemuk', 'normal'], 'description' => 'KAMU klasifikasi berdasarkan jawaban customer. "tidak gemuk"/"biasa"/"kurus" → "tidak gemuk". "proporsional"/"sedang" → "normal". "gemuk"/"obesitas"/"gendut"/"besar" → "gemuk".'],
                            'riwayat_kesehatan' => ['type' => 'string', 'description' => 'ringkas jawaban. Kalau customer bilang "tidak ada"/"sehat"/"gak ada"/"nihil", save "tidak ada".'],
                            'perlu_review_dokter' => ['type' => 'boolean', 'description' => 'HASIL KLASIFIKASI KAMU: true jika ada faktor risiko yg butuh assessment dokter (postur gemuk/obesitas, indikasi keluhan medis nyata BUKAN cuma "mau khitan", riwayat penyakit signifikan spt jantung/autisme/kelainan pembekuan/asma berat). false jika semua safety-field benign. WAJIB pass ketika save salah satu field: indikasi_khitan / postur_tubuh / riwayat_kesehatan.'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'finalize_booking',
                    'description' => 'Commit booking ke jadwal_sunats + kirim bubble booking_sukses. WAJIB dipanggil HANYA setelah: (a) semua 6 field booking + 3 safety field terkumpul, (b) save_booking_data terakhir return slot_status="ok" + escalate=false, DAN (c) customer sudah eksplisit konfirmasi summary + biaya di turn sebelumnya (customer msg current mulai dgn "ya"/"setuju"/"ok"/"iya"/"benar"/"sip"/"lanjut"/"siap"). Kalau customer belum konfirmasi (mis. cuma baru kasih nama panggilan), tool REJECT — kamu harus emit summary booking + info biaya "mulai Rp 2.500.000, bisa lebih kalau ada kondisi khusus" sbg text reply DULU, tunggu customer confirm. Setelah call, output text KOSONG.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'save_lead_sunat',
                    'description' => 'Simpan lead sunat (nama + alamat customer) ke leads_sunats table. Dipanggil di AWAL conversation sunat setelah customer kasih info nama & domisili. Boleh pass parsial (cuma nama SAJA atau cuma alamat SAJA) kalau customer belum kasih keduanya. 🚫 DILARANG pass nama="Rona" — Rona = nama admin bot sendiri (bukan customer). Kalau customer cuma jawab 1 kata dan itu nama daerah/kota (mis. "Tigaraksa", "Depok", "Ciledug"), save alamat SAJA, nama JANGAN pass. Return {ok, saved:[fields]}.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'nama'   => ['type' => 'string', 'description' => 'nama panggilan customer (ortu/lawan bicara), mis. "Rina" / "Yeni". OPSIONAL — skip kalau customer belum sebut nama. JANGAN pernah pass "Rona" (nama admin bot).'],
                            'alamat' => ['type' => 'string', 'description' => 'kota / kecamatan domisili, mis. "Depok" / "Tangerang Selatan". OPSIONAL — skip kalau customer belum sebut.'],
                        ],
                        'required' => [],
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
                $slug = (string) ($args['slug'] ?? '');
                [$summary, $bubbles] = $this->toolGetIntentResponse($slug);
                // Track slug yang sudah di-render di session utk dedupe
                // cross-turn (mis. metode sudah dijelaskan sebelum harga,
                // send_harga_quote skip prepend metode lagi).
                $this->recordSlugShown($session, $slug);
                return [$summary, ['replies' => $bubbles]];

            case 'redirect_ke_klinik_utama':
            case 'redirect_ke_admin': // back-compat: agent kadang masih panggil nama lama
                $reason = (string) ($args['reason'] ?? '');
                [$summary, $bubbles] = $this->toolRedirectKeKlinikUtama($session, $reason);
                return [$summary, ['replies' => $bubbles, 'signal' => 'redirected']];

            case 'handoff_to_admin':
                $reason = (string) ($args['reason'] ?? 'AI request handoff (no reason)');
                Log::info('SUNAT_BOT_AGENT_HANDOFF_TO_ADMIN', [
                    'phone'  => $session->no_telp,
                    'reason' => $reason,
                ]);
                $session->setData('_handoff_reason', $reason);
                $session->save();
                return [
                    ['ok' => true, 'handoff' => true, 'note' => 'engine akan escalate ke admin + kirim pesan tunggu ke customer. Output text KOSONG setelah tool ini.'],
                    [
                        'escalate' => true,
                        'replies'  => [[
                            'text'  => "Sebentar ya kak 🙏 saya cek dulu ke tim, nanti admin kami balas untuk detail lebih lanjut.",
                            'media' => null,
                        ]],
                    ],
                ];

            case 'save_harga_data':
                [$summary, $sideEffect] = $this->toolSaveHargaData($args, $session);
                return [$summary, $sideEffect];

            case 'send_harga_quote':
                [$summary, $bubbles, $escalate] = $this->toolSendHargaQuote($session);
                $sideEffect = ['replies' => $bubbles];
                if ($escalate) $sideEffect['escalate'] = true;
                return [$summary, $sideEffect];

            case 'save_booking_data':
                [$summary, $sideEffect] = $this->toolSaveBookingData($args, $session);
                return [$summary, $sideEffect];

            case 'finalize_booking':
                [$summary, $bubbles] = $this->toolFinalizeBooking($session);
                return [$summary, ['replies' => $bubbles]];

            case 'save_lead_sunat':
                return [$this->toolSaveLeadSunat($args, $session), []];

            case 'request_konsul_dokter':
                $reason = trim((string) ($args['reason'] ?? ''));
                Log::info('SUNAT_BOT_AGENT_REQUEST_KONSUL_DOKTER', [
                    'phone'  => $session->no_telp,
                    'reason' => $reason,
                ]);
                // Simpan konteks utk webhook image handler pakai saat
                // forward foto ke dr. Yoga.
                $session->setData('konsul_dokter_pending', true);
                $session->setData('konsul_dokter_reason', $reason);
                $session->setData('konsul_dokter_requested_at', now()->toDateTimeString());
                $session->save();
                return [
                    ['ok' => true, 'note' => 'engine akan minta foto ke customer. Setelah customer kirim foto, webhook akan forward otomatis ke dr. Yoga (6281381912803). Bot mute customer selama menunggu instruksi dokter. Output text KOSONG.'],
                    [
                        'replies' => [[
                            'text'  => "Boleh dibantu kirim foto area yg dikeluhkan kak? 🙏\n\nKami akan langsung konsulkan ke dokter kami untuk asesmen kondisinya. Setelah dokter review, kami kabari balik ya.",
                            'media' => null,
                        ]],
                    ],
                ];

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
        // Exclude:
        //   - trigger_sunat / fallback_unknown (engine-managed)
        //   - data_* (capture prompts di harga flow state machine, bukan
        //     untuk free-form Q&A — kalau agent render data_nama dst
        //     customer akan dapat bubble random "Baik kak X" di tengah
        //     percakapan)
        $intents = BotIntent::where('active', true)
            ->whereNotNull('keywords')
            ->where('keywords', '!=', '')
            ->whereNotIn('intent', ['trigger_sunat', 'fallback_unknown'])
            ->where('intent', 'not like', 'data_%')
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
     * Redirect customer ke admin klinik utama Meta (6282113781271).
     * Dipakai saat customer di gowa sunat tapi keperluannya bukan
     * sunat (pendaftaran dokter umum, BPJS, tanya gigi, dll).
     * Throttled 1x/hari per nomor supaya tidak spam.
     *
     * @return array{0:array, 1:array<array{text:string,media:?string}>}
     */
    // ----- BOOKING (natural collection, agent-driven) -----------------

    // NB: berat_badan_anak sengaja dihapus 2026-08-18 — cukup usia +
    // postur_tubuh (gemuk/tidak) untuk asses risiko. Tool schema masih
    // terima parameter berat_badan_anak (backward compat kalau customer
    // volunteering), tapi tidak wajib.
    private const BOOKING_REQUIRED_FIELDS = [
        'booking_tanggal', 'booking_jam', 'booking_nama_anak',
        'booking_nama_panggilan', 'booking_usia_anak',
    ];

    // Safety fields — shared dgn HARGA flow (unprefixed). Wajib dijawab
    // sebelum finalize_booking. Escalation gate: kalau ada keluhan /
    // gemuk / kondisi khusus → engine handoff ke admin, JANGAN commit.
    private const BOOKING_SAFETY_FIELDS = [
        'indikasi_khitan', 'postur_tubuh', 'riwayat_kesehatan',
    ];

    /**
     * Save fields booking ke collected_data. Validate slot (blackout,
     * konflik BOOKED, spillover 2 jam). Return status + missing[] utk LLM
     * lanjut nanya field berikutnya atau alihkan ke jam/tanggal lain.
     *
     * @return array{0:array,1:array}
     */
    private function toolSaveBookingData(array $args, BotSession $session): array
    {
        $engine = app(\App\Services\SunatBot\SunatBotEngine::class);
        $saved  = [];

        // tanggal — parse via engine helper (support "5 Juli 2026", "besok", "2026-07-05", dst)
        $tglRaw = trim((string) ($args['tanggal'] ?? ''));
        if ($tglRaw !== '') {
            $parsed = $engine->parseBookingDate($tglRaw);
            if ($parsed !== null) {
                $session->setData('booking_tanggal', $parsed->format('Y-m-d'));
                $saved[] = 'booking_tanggal';
            } else {
                Log::info('SUNAT_BOT_AGENT_BOOKING_DATE_INVALID', [
                    'phone' => $session->no_telp,
                    'raw'   => $tglRaw,
                ]);
            }
        }

        // jam — normalize via engine helper ke slot resmi
        $jamRaw = trim((string) ($args['jam'] ?? ''));
        if ($jamRaw !== '') {
            $parsed = $engine->parseBookingJam($jamRaw);
            if ($parsed !== null) {
                $session->setData('booking_jam', $parsed);
                $saved[] = 'booking_jam';
            }
        }

        // nama_anak
        $namaAnak = trim((string) ($args['nama_anak'] ?? ''));
        if ($namaAnak !== '') {
            $session->setData('booking_nama_anak', $namaAnak);
            $saved[] = 'booking_nama_anak';
        }

        // nama_panggilan — "-" means "same as nama_anak"
        $panggilan = trim((string) ($args['nama_panggilan'] ?? ''));
        if ($panggilan !== '') {
            if ($panggilan === '-') {
                $fallback = (string) $session->getData('booking_nama_anak');
                $panggilan = $fallback !== '' ? $fallback : $panggilan;
            }
            $session->setData('booking_nama_panggilan', $panggilan);
            $saved[] = 'booking_nama_panggilan';
        }

        // usia_anak — parse int + satuan terpisah (mirror parseUsia engine)
        $usiaRaw = trim((string) ($args['usia_anak'] ?? ''));
        if ($usiaRaw !== '') {
            $lower = mb_strtolower($usiaRaw);
            if (preg_match('/(\d+)\s*(?:bln|bulan|bulanan)\b/u', $lower, $m)) {
                $session->setData('booking_usia_anak', (int) $m[1]);
                $session->setData('booking_usia_anak_satuan', 'bulan');
            } elseif (preg_match('/(\d+)\s*(?:thn|tahun|taun|th)\b/u', $lower, $m)) {
                $session->setData('booking_usia_anak', (int) $m[1]);
                $session->setData('booking_usia_anak_satuan', 'tahun');
            } elseif (preg_match('/(\d+)/', $lower, $m)) {
                $session->setData('booking_usia_anak', (int) $m[1]);
                $session->setData('booking_usia_anak_satuan', 'tahun');
            }
            $saved[] = 'booking_usia_anak';
        }

        if (isset($args['berat_badan_anak']) && is_numeric($args['berat_badan_anak'])) {
            $session->setData('booking_berat_badan_anak', (float) $args['berat_badan_anak']);
            $saved[] = 'booking_berat_badan_anak';
        }

        // Safety fields (shared dgn HARGA flow — unprefixed).
        foreach (self::BOOKING_SAFETY_FIELDS as $sf) {
            $v = trim((string) ($args[$sf] ?? ''));
            if ($v !== '') {
                $session->setData($sf, $v);
                $saved[] = $sf;
            }
        }

        // Mark flag booking_started supaya guard sunat-keyword di turn
        // berikutnya skip cek — walau field lain di-reset (slot invalid),
        // customer tetap dianggap dalam booking flow.
        if ($saved !== []) {
            $session->setData('booking_started', true);
        }

        $session->save();

        // Compute filled/missing (base booking fields + safety fields)
        $filled  = [];
        $missing = [];
        foreach (self::BOOKING_REQUIRED_FIELDS as $f) {
            $v = $session->getData($f);
            if ($v === null || $v === '') {
                $missing[] = str_replace('booking_', '', $f);
            } else {
                $filled[] = str_replace('booking_', '', $f);
            }
        }
        foreach (self::BOOKING_SAFETY_FIELDS as $sf) {
            $v = $session->getData($sf);
            if ($v === null || $v === '') {
                $missing[] = $sf;
            } else {
                $filled[] = $sf;
            }
        }

        // Escalation gate — LLM (agent) yang klasifikasi lewat param
        // `perlu_review_dokter` di tool call. Backend cuma baca bool.
        $escalate = false;
        $reason   = null;

        if (array_key_exists('perlu_review_dokter', $args)) {
            $session->setData('perlu_review_dokter', (bool) $args['perlu_review_dokter']);
            $session->save();
        }
        if ((bool) $session->getData('perlu_review_dokter')) {
            $escalate = true;
            $reason   = 'LLM classified perlu_review_dokter=true (postur/indikasi/riwayat berisiko)';
        }

        // NB (per instruksi dr. Yoga 2026-08-16): hard-gate usia ≥17
        // tahun di BOOKING flow DIHAPUS. Sebelumnya (2026-08-14) auto-
        // handoff karena "pasien dewasa wajib review manusia". Sekarang
        // konsisten dgn HARGA flow (2026-08-16 pagi): dewasa proceed
        // booking normal — sudah dijawab harganya (Rp 3.5jt via
        // quote_harga_paket_dewasa), tidak perlu handoff lagi.
        // Escalation gate LLM-classified (perlu_review_dokter) tetap
        // jalan sebagai safety net untuk riwayat penyakit / postur
        // berisiko — tidak spesifik untuk usia.

        // Validate slot kalau tanggal + jam sudah terisi
        $slotStatus     = 'ok';
        $slotError      = null;
        $calendarUrl    = null;
        if ($session->getData('booking_tanggal') !== null
            && $session->getData('booking_jam') !== null) {
            // Capture tanggal SEBELUM validate karena engine internal
            // reset booking_tanggal ke null saat blackout — kita butuh
            // untuk prefill URL kalender.
            $tglIsoForLink = (string) $session->getData('booking_tanggal');
            $conflict = $engine->validateBookingSlotFromSession($session);
            if ($conflict !== null) {
                $newExpecting = (string) $session->expecting_field;
                if ($newExpecting === 'booking_tanggal') {
                    $slotStatus = 'blackout_or_invalid_date';
                } elseif ($newExpecting === 'booking_jam') {
                    $slotStatus = 'jam_blocked_or_booked';
                } else {
                    $slotStatus = 'conflict';
                }
                // Reset expecting_field lagi (validateBookingSlotFromSession
                // set utk state machine legacy — kita di agent path, tidak butuh).
                $session->expecting_field = null;
                // Reset booking_tanggal + booking_jam supaya customer bisa
                // pilih ulang tanpa nilai lama stuck di session.
                if ($slotStatus === 'blackout_or_invalid_date') {
                    $session->setData('booking_tanggal', null);
                    $session->setData('booking_jam', null);
                } elseif ($slotStatus === 'jam_blocked_or_booked') {
                    $session->setData('booking_jam', null);
                }
                $session->save();
                $slotError = 'Slot tidak tersedia. Ajak customer pilih tanggal/jam lain.';
                // Public calendar link — customer bisa lihat semua slot.
                // Prefill dgn tanggal yang tadi ditolak supaya kalender
                // langsung auto-open detail slot per tanggal itu.
                $calendarUrl = 'https://www.kezia.id/sunat-calendar'
                             . ($tglIsoForLink !== '' ? '?date=' . $tglIsoForLink : '');
            }
        }

        Log::info('SUNAT_BOT_AGENT_BOOKING_SAVE', [
            'phone'       => $session->no_telp,
            'saved'       => $saved,
            'filled'      => $filled,
            'missing'     => $missing,
            'slot_status' => $slotStatus,
            'escalate'    => $escalate,
        ]);

        $result = [
            'ok'          => true,
            'filled'      => $filled,
            'missing'     => $missing,
            'slot_status' => $slotStatus,
            'escalate'    => $escalate,
        ];
        if ($slotError !== null) $result['slot_error'] = $slotError;
        if ($calendarUrl !== null) $result['calendar_url'] = $calendarUrl;
        if ($reason !== null)     $result['reason'] = $reason;

        // Side-effect: kalau slot invalid, inject calendar bubble
        // langsung. LLM sering strip query param ?date=... dari URL,
        // jadi kirim bubble deterministic dari tool executor.
        $sideEffect = [];
        if ($escalate) {
            $sideEffect['escalate'] = true;
        }
        if ($calendarUrl !== null) {
            $sideEffect['replies'] = [[
                'text'  => "Lihat slot yang tersedia di kalender:\n" . $calendarUrl,
                'media' => null,
            ]];
        }

        return [$result, $sideEffect];
    }

    /**
     * Commit booking ke jadwal_sunats + emit bubble booking_sukses.
     * Pastikan semua 6 field terkumpul + slot masih valid saat commit.
     *
     * @return array{0:array, 1:array<array>}
     */
    private function toolFinalizeBooking(BotSession $session): array
    {
        $missing = [];
        foreach (self::BOOKING_REQUIRED_FIELDS as $f) {
            $v = $session->getData($f);
            if ($v === null || $v === '') $missing[] = str_replace('booking_', '', $f);
        }
        foreach (self::BOOKING_SAFETY_FIELDS as $sf) {
            $v = $session->getData($sf);
            if ($v === null || $v === '') $missing[] = $sf;
        }
        if ($missing !== []) {
            return [
                ['ok' => false, 'error' => 'field belum lengkap', 'missing' => $missing],
                [],
            ];
        }

        // Safety gate: JANGAN commit booking kalau LLM sudah flag
        // perlu_review_dokter=true di save_booking_data sebelumnya.
        if ((bool) $session->getData('perlu_review_dokter')) {
            return [
                ['ok' => false, 'error' => 'safety_gate: perlu_review_dokter=true — engine akan handoff', 'escalate' => true],
                [],
            ];
        }

        // Konfirmasi gate — per instruksi dr. Yoga 2026-08-16: booking
        // TIDAK boleh dikomit ke database tanpa konfirmasi eksplisit
        // customer (setelah lihat summary + biaya 2.5jt). Cek current
        // user message di turn ini — harus mulai dgn kata konfirmasi.
        // Kalau LLM call finalize_booking tanpa customer confirm (mis.
        // langsung setelah collect nama_panggilan), tool reject → LLM
        // dipaksa emit summary + tunggu approval dulu.
        $rawMsg = mb_strtolower(trim($this->currentUserMessage));
        $isConfirmation = (bool) preg_match(
            '/^(ya|iya|ok|oke|okay|okey|setuju|benar|betul|sip|lanjut|jadi|gas|siap|deal|yoi|yes|y|k)\b/iu',
            $rawMsg
        );
        if (!$isConfirmation) {
            return [
                ['ok' => false, 'error' => 'customer belum konfirmasi eksplisit. WAJIB tampilkan summary booking (nama, tanggal, jam, usia, BB) + biaya "mulai Rp 2.500.000, bisa lebih kalau ada kondisi khusus" sbg text reply, TUNGGU customer bilang "ya"/"setuju"/"ok" dulu baru call finalize_booking. Jangan finalize di turn ini.'],
                [],
            ];
        }

        $engine  = app(\App\Services\SunatBot\SunatBotEngine::class);
        $bubbles = $engine->finalizeBooking($session);

        Log::info('SUNAT_BOT_AGENT_BOOKING_FINALIZE', [
            'phone'   => $session->no_telp,
            'bubbles' => count($bubbles),
        ]);

        return [
            ['ok' => true, 'bubbles' => count($bubbles)],
            $bubbles,
        ];
    }

    /**
     * Validate apakah pesan customer layak masuk trigger_booking_flow.
     * Return null kalau valid, atau string alasan reject.
     */
    private function validateBookingFlowMessage(string $message): ?string
    {
        $lower = mb_strtolower($message);

        foreach (self::NON_SUNAT_KEYWORDS as $kw) {
            if (str_contains($lower, $kw)) {
                return "pesan mengandung kata non-sunat: '{$kw}'";
            }
        }

        $hasSunatKw = false;
        foreach (self::SUNAT_KEYWORDS as $kw) {
            if (str_contains($lower, $kw)) {
                $hasSunatKw = true;
                break;
            }
        }
        if (!$hasSunatKw) {
            return "pesan tidak menyebut sunat/khitan/sirkumsisi secara eksplisit";
        }

        return null;
    }

    private function toolRedirectKeKlinikUtama(BotSession $session, string $reason): array
    {
        // Per user 2026-07-10: throttle 1x/hari dihapus — link WA klinik
        // utama selalu dikirim tiap kali tool dipanggil, supaya customer
        // tidak pernah dapat reply tanpa link (bingung ke mana redirect).
        $phone = $session->no_telp ?? '';

        $klinikUtama = '6282113781271';
        $waLink      = "https://wa.me/{$klinikUtama}";

        $text = "Halo kak 🙏\n\n"
              . "Nomor ini khusus konsultasi *sunat*. Untuk pendaftaran umum, jadwal dokter, BPJS, atau informasi klinik lainnya, silakan tap link berikut untuk langsung chat admin klinik utama kami:\n\n"
              . $waLink . "\n\n"
              . "Terima kasih 🙏";

        Log::info('SUNAT_BOT_AGENT_REDIRECT', ['phone' => $phone, 'reason' => $reason, 'target' => 'klinik-utama']);

        return [['ok' => true, 'redirected' => true], [['text' => $text, 'media' => null]]];
    }

    // ----- HARGA (natural collection, agent-driven) -----------------

    // NB: berat_badan_anak sengaja dihapus 2026-08-18 — cukup usia +
    // postur_tubuh (gemuk/tidak) untuk asses risiko. Tool schema masih
    // terima parameter berat_badan_anak (backward compat kalau customer
    // volunteering), tapi tidak wajib.
    // jumlah_anak sengaja tidak wajib (2026-08-21) — default 1, cuma
    // di-save kalau customer volunteer info multi-anak. Tool schema masih
    // terima parameter jumlah_anak (backward compat) supaya diskon
    // rombongan tetap jalan otomatis di send_harga_quote.
    private const HARGA_REQUIRED_FIELDS = [
        'nama_orang_tua', 'domisili', 'usia_anak',
        'indikasi_khitan', 'postur_tubuh', 'riwayat_kesehatan',
    ];

    /**
     * Save fields ke collected_data. Return status + missing[] utk LLM
     * kasih tanya field berikutnya. Escalation gate: baca bool
     * `perlu_review_dokter` dari args (LLM klasifikasi berdasarkan
     * jawaban customer). Kalau true → engine handoff ke admin.
     *
     * @return array{0:array,1:array} tuple [tool_result, side_effect]
     */
    private function toolSaveHargaData(array $args, BotSession $session): array
    {
        // Reject nama_orang_tua = "Rona" (nama admin bot). Kasus
        // 6281318081779 2026-08-26: agent hallucinate nama dari greeting.
        $botNames = ['rona', 'admin', 'admin sunat', 'sunatboy', 'bot', 'cs'];
        if (isset($args['nama_orang_tua'])) {
            $namaLower = mb_strtolower(trim((string) $args['nama_orang_tua']));
            if (in_array($namaLower, $botNames, true)) {
                Log::info('SUNAT_BOT_AGENT_HARGA_REJECT_BOT_NAME', [
                    'phone' => (string) $session->no_telp,
                    'nama'  => $args['nama_orang_tua'],
                ]);
                unset($args['nama_orang_tua']);
            }
        }

        // GUARD ANTI-HALLUCINATION safety-fields (per keluhan dr. Yoga
        // 2026-08-27 nomor 6281289632301): agent set indikasi_khitan
        // ="tidak ada" + postur_tubuh="normal" + riwayat_kesehatan
        // ="tidak ada" tanpa customer pernah menyebut → langsung
        // kasih harga. Sekarang setiap field safety WAJIB punya bukti
        // di customer messages history (chat_sunat, sending=0). Kalau
        // tidak ada bukti → reject field itu, kembalikan warning agar
        // agent tanya langsung ke customer.
        $safetyFields = ['indikasi_khitan', 'postur_tubuh', 'riwayat_kesehatan'];
        $rejectedNoEvidence = [];
        foreach ($safetyFields as $sf) {
            if (!isset($args[$sf])) continue;
            $val = trim((string) $args[$sf]);
            if ($val === '') continue;
            if (!$this->customerMentionedSafetyField($sf, (string) $session->no_telp)) {
                $rejectedNoEvidence[] = $sf;
                Log::info('SUNAT_BOT_AGENT_HARGA_REJECT_NO_EVIDENCE', [
                    'phone'        => (string) $session->no_telp,
                    'field'        => $sf,
                    'attempt_val'  => $val,
                ]);
                unset($args[$sf]);
            }
        }

        $strKeys = ['nama_orang_tua', 'domisili', 'sudah_tahu_metode',
                    'indikasi_khitan', 'postur_tubuh', 'riwayat_kesehatan'];
        $saved = [];
        foreach ($strKeys as $k) {
            $v = trim((string) ($args[$k] ?? ''));
            if ($v !== '') {
                $session->setData($k, $v);
                $saved[] = $k;
            }
        }

        // DUAL-WRITE ke leads_sunats (per instruksi dr. Yoga 2026-08-26):
        // Kalau HARGA flow capture nama_orang_tua atau domisili, sekaligus
        // upsert ke leads_sunats table supaya UI /chat_sunats + followup
        // pipeline dapat nama+alamat. Sebelumnya cuma save_lead_sunat yg
        // write ke leads_sunats — kalau customer langsung minta harga
        // (skip lead capture), leads_sunats stuck NULL selamanya.
        $namaLeads   = trim((string) ($args['nama_orang_tua'] ?? ''));
        $alamatLeads = trim((string) ($args['domisili'] ?? ''));
        if ($namaLeads !== '' || $alamatLeads !== '') {
            $phone = (string) $session->no_telp;
            $existing = \DB::table('leads_sunats')
                ->where('no_telp', $phone)
                ->where('tenant_id', 1)
                ->first();
            $update = ['updated_at' => now()];
            if ($namaLeads !== '')   $update['nama_lawan_bicara'] = $namaLeads;
            if ($alamatLeads !== '') $update['alamat']            = $alamatLeads;
            $update['created_at'] = $existing->created_at ?? now();
            \DB::table('leads_sunats')->updateOrInsert(
                ['no_telp' => $phone, 'tenant_id' => 1],
                $update
            );
        }
        // usia_anak: parse ke integer + simpan satuan terpisah, supaya
        // template render "8 tahun" (bukan "8 tahun tahun").
        $usiaRaw = trim((string) ($args['usia_anak'] ?? ''));
        if ($usiaRaw !== '') {
            $lower = mb_strtolower($usiaRaw);
            if (preg_match('/(\d+)\s*(?:bln|bulan|bulanan)\b/u', $lower, $m)) {
                $session->setData('usia_anak', (int) $m[1]);
                $session->setData('usia_anak_satuan', 'bulan');
            } elseif (preg_match('/(\d+)\s*(?:thn|tahun|taun|th)\b/u', $lower, $m)) {
                $session->setData('usia_anak', (int) $m[1]);
                $session->setData('usia_anak_satuan', 'tahun');
            } elseif (preg_match('/(\d+)/', $lower, $m)) {
                $session->setData('usia_anak', (int) $m[1]);
                $session->setData('usia_anak_satuan', 'tahun');
            }
            $saved[] = 'usia_anak';
        }
        if (isset($args['berat_badan_anak']) && is_numeric($args['berat_badan_anak'])) {
            $bb = (float) $args['berat_badan_anak'];
            if ($bb > 0) {
                $session->setData('berat_badan_anak', $bb);
                $saved[] = 'berat_badan_anak';
            }
        }
        if (isset($args['jumlah_anak']) && is_numeric($args['jumlah_anak'])) {
            $jml = max(1, (int) $args['jumlah_anak']);
            $session->setData('jumlah_anak', $jml);
            $saved[] = 'jumlah_anak';
        }
        $session->save();

        // Escalation gate — LLM (agent) yang klasifikasi lewat param
        // `perlu_review_dokter` di tool call. Backend cuma baca bool.
        // Kalau LLM belum pass field ini di turn ini, cek nilai lama
        // yg tersimpan di session (persisted dari turn sebelumnya).
        $escalate = false;
        $reason   = null;

        if (array_key_exists('perlu_review_dokter', $args)) {
            $classifiedNow = (bool) $args['perlu_review_dokter'];
            $session->setData('perlu_review_dokter', $classifiedNow);
        }
        if ((bool) $session->getData('perlu_review_dokter')) {
            $escalate = true;
            $reason   = 'LLM classified perlu_review_dokter=true (postur/indikasi/riwayat berisiko)';
        }

        // NB (per instruksi dr. Yoga 2026-08-16): usia ≥17 tahun di
        // HARGA flow tidak lagi auto-escalate. Sebaliknya, kalau semua
        // 8 field lengkap → toolSendHargaQuote akan swap quote slug ke
        // `quote_harga_paket_dewasa` (harga Rp 3.500.000, konten manfaat
        // dewasa). Booking dgn usia ≥17 masih auto-handoff (di
        // toolSaveBookingData) karena tindakan dewasa butuh assessment
        // dokter langsung.

        $session->save();

        // Compute filled/missing utk LLM know what to ask next.
        $filled  = [];
        $missing = [];
        foreach (self::HARGA_REQUIRED_FIELDS as $f) {
            $v = $session->getData($f);
            $isMissing = ($v === null || $v === '');
            if ($f === 'berat_badan_anak' && is_numeric($v) && (float) $v <= 0) {
                $isMissing = true;
            }
            if ($isMissing) {
                $missing[] = $f;
            } else {
                $filled[] = $f;
            }
        }

        Log::info('SUNAT_BOT_AGENT_HARGA_SAVE', [
            'phone'    => $session->no_telp,
            'saved'    => $saved,
            'filled'   => $filled,
            'missing'  => $missing,
            'escalate' => $escalate,
        ]);

        $result = [
            'ok'       => true,
            'filled'   => $filled,
            'missing'  => $missing,
            'escalate' => $escalate,
        ];
        if ($reason !== null) $result['reason'] = $reason;
        if (!empty($rejectedNoEvidence)) {
            $result['rejected_no_evidence'] = $rejectedNoEvidence;
            $result['warning'] = 'Field berikut DI-REJECT karena customer belum eksplisit menyebut di pesan-nya: '
                . implode(', ', $rejectedNoEvidence)
                . '. JANGAN kirim harga. WAJIB tanya field ini ke customer dulu satu per satu. Contoh: "Postur anaknya gemuk atau tidak gemuk kak?" / "Ada keluhan medis atau alasan khusus kenapa mau khitan kak?" / "Ada riwayat kesehatan khusus seperti jantung, autisme, atau kelainan pembekuan darah kak?"';
        }

        $sideEffect = [];
        if ($escalate) {
            $sideEffect['escalate'] = true;
        }
        return [$result, $sideEffect];
    }

    /**
     * GUARD ANTI-HALLUCINATION: cek apakah customer pernah menyebut
     * kata kunci relevan untuk safety-field di history chat_sunat.
     * Kalau tidak ada bukti eksplisit → agent sedang hallucinate,
     * reject save field itu.
     *
     * Kasus 6281289632301 (2026-08-27): agent langsung set
     * indikasi_khitan/postur/riwayat="tidak ada"/"normal" tanpa
     * customer pernah menyebutkan → langsung send_harga_quote.
     */
    private function customerMentionedSafetyField(string $field, string $noTelp): bool
    {
        // Keyword whitelist per field. "tidak ada" sengaja TIDAK
        // dimasukin ke semua field karena ambigu (customer sering
        // bilang "tidak ada" utk salah satu → agent generalize ke
        // semua). Butuh compound match (mis. "tidak ada keluhan").
        $keywords = [
            'indikasi_khitan' => [
                'keluhan', 'phimosis', 'fimosis', 'balanitis',
                'lengket', 'menutup', 'nyeri', 'gatal', 'sakit',
                'kencing', 'susah', 'medis', 'religius', 'religion',
                'agama', 'muslim', 'islam', 'balig', 'baligh',
                'wajib', 'sudah waktunya', 'panjang', 'kelamin',
                'penis', 'burung', 'titit', 'kulup',
                'tidak ada keluhan', 'ga ada keluhan', 'gak ada keluhan',
                'tanpa keluhan', 'ga da keluhan', 'nggak ada keluhan',
            ],
            'postur_tubuh' => [
                'gemuk', 'kurus', 'normal', 'chubby', 'gempal',
                'ideal', 'biasa', 'sedang', 'tidak gemuk',
                'ga gemuk', 'gak gemuk', 'nggak gemuk',
                'obesitas', 'proporsional', 'kecil', 'besar',
                'langsing', 'atletis',
            ],
            'riwayat_kesehatan' => [
                'jantung', 'autis', 'autisme', 'autism',
                'pembekuan', 'hemofilia', 'alergi', 'asma',
                'diabetes', 'ginjal', 'epilepsi', 'kejang',
                'sehat', 'sehat aja', 'sehat semua', 'sehat kok',
                'sehat wal afiat', 'tidak ada penyakit',
                'ga ada penyakit', 'gak ada penyakit',
                'tidak ada riwayat', 'ga ada riwayat',
                'gak ada riwayat', 'tidak ada sakit',
                'tanpa riwayat', 'aman', 'nggak ada penyakit',
            ],
        ];
        if (!isset($keywords[$field])) return true; // unknown field → allow

        $msgs = \App\Models\Message::where('no_telp', $noTelp)
            ->where('chat_sunat', 1)
            ->where('sending', 0)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get(['message']);

        foreach ($msgs as $m) {
            $text = mb_strtolower((string) $m->message);
            if ($text === '') continue;
            foreach ($keywords[$field] as $kw) {
                if (str_contains($text, mb_strtolower($kw))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Simpan lead sunat ke leads_sunats table (atika shared DB) + set
     * nama_orang_tua / domisili di session data supaya HARGA flow bisa
     * reuse. Upsert by no_telp — aman kalau dipanggil ulang.
     */
    private function toolSaveLeadSunat(array $args, BotSession $session): array
    {
        $nama   = trim((string) ($args['nama'] ?? ''));
        $alamat = trim((string) ($args['alamat'] ?? ''));

        // GUARD 1: reject nama "Rona" — itu nama ADMIN BOT sendiri,
        // agent sering keliru extract dari greeting template (kasus
        // 6281318081779 2026-08-26: customer cuma jawab "Tigaraksa",
        // agent hallucinate nama="Rona"). Sinonim admin bot juga block.
        $namaLower = mb_strtolower($nama);
        $botNames  = ['rona', 'admin', 'admin sunat', 'sunatboy', 'bot', 'cs'];
        if ($nama !== '' && in_array($namaLower, $botNames, true)) {
            Log::info('SUNAT_BOT_AGENT_LEAD_REJECT_BOT_NAME', [
                'phone' => (string) $session->no_telp,
                'nama'  => $nama,
            ]);
            $nama = ''; // drop, jangan simpan
        }

        // GUARD 2: kedua field kosong = no-op
        if ($nama === '' && $alamat === '') {
            return ['ok' => false, 'error' => 'nama atau alamat harus diisi (minimal salah satu). Kalau customer belum sebut, JANGAN panggil tool ini.'];
        }

        $phone = (string) $session->no_telp;
        $existing = \DB::table('leads_sunats')
            ->where('no_telp', $phone)
            ->where('tenant_id', 1)
            ->first();

        $update = ['updated_at' => now()];
        if ($nama !== '')   $update['nama_lawan_bicara'] = $nama;
        if ($alamat !== '') $update['alamat']            = $alamat;
        $update['created_at'] = $existing->created_at ?? now();

        \DB::table('leads_sunats')->updateOrInsert(
            ['no_telp' => $phone, 'tenant_id' => 1],
            $update
        );

        // Share dgn HARGA flow: cuma set field yg ada.
        if ($nama !== '')   $session->setData('nama_orang_tua', $nama);
        if ($alamat !== '') $session->setData('domisili', $alamat);
        $session->save();

        Log::info('SUNAT_BOT_AGENT_LEAD_SAVE', [
            'phone'            => $phone,
            'nama'             => $nama,
            'alamat'           => $alamat,
            'already_captured' => $existing !== null,
        ]);

        $saved = [];
        if ($nama !== '')   $saved[] = 'nama';
        if ($alamat !== '') $saved[] = 'alamat';

        return [
            'ok'               => true,
            'saved'            => $saved,
            'already_captured' => $existing !== null,
            'note'             => $nama === '' && !empty($args['nama'])
                ? 'nama di-reject (bot name / Rona). Tanya nama customer lagi.'
                : null,
        ];
    }

    /**
     * Emit quote bundle final: pertanyaan_metode (1 bubble) +
     * quote_harga_paket + diskon rombongan + tanya_pertanyaan_lanjutan.
     * Kalau field belum lengkap, return error — LLM harus panggil
     * save_harga_data dulu.
     *
     * NB (per feedback client 2026-08-14): TIGA hal SENGAJA dihapus dari
     * bundle karena terkesan spam / terburu-buru:
     *   - `testimoni_google_review` — 1 menit setelah vlog terasa buru2
     *   - `contoh_dokumentasi` (mini vlog) — bukan info yg dibutuhkan
     *     saat customer tanya harga; pindahkan ke followup H+1
     *   - `pertanyaan_hadiah` — tidak pernah di-bundle, catat aja disini
     * Ketiganya HANYA keluar via intent trigger eksplisit (customer tanya
     * langsung) atau followup H+1 kalau leads tidak reply.
     *
     * @return array{0:array, 1:array<array>, 2:bool} [result, bubbles, escalate]
     */
    private function toolSendHargaQuote(BotSession $session): array
    {
        $missing = [];
        foreach (self::HARGA_REQUIRED_FIELDS as $f) {
            $v = $session->getData($f);
            $isMissing = ($v === null || $v === '');
            if ($f === 'berat_badan_anak' && is_numeric($v) && (float) $v <= 0) {
                $isMissing = true;
            }
            if ($isMissing) $missing[] = $f;
        }
        if ($missing !== []) {
            return [
                ['ok' => false, 'error' => 'field belum lengkap', 'missing' => $missing],
                [],
                false,
            ];
        }

        // NB: dedupe backend DIHAPUS per instruksi dr. Yoga 2026-08-16.
        // Kalau customer minta kirim ulang, agent boleh call ini lagi —
        // customer eksplisit minta jangan ditolak. Guard di prompt agent
        // (poin 7 di ATURAN HARGA) sudah membatasi call redundant, tapi
        // TIDAK MEMBLOKIR eksplisit request. Reset _slugs_shown supaya
        // pertanyaan_metode (media) juga re-render (kalau tidak reset,
        // bubble metode di-skip → hanya text quote yg terkirim, terasa
        // aneh untuk re-send full).
        $session->setData('_slugs_shown', []);
        $session->save();

        $bubbles = [];
        $shown   = (array) $session->getData('_slugs_shown');

        // 1. Edukasi metode (foto teknoklamp + text) — customer perlu tau
        //    metode sebelum lihat harga supaya bisa evaluate value.
        //    Skip kalau sudah dirender di turn sebelumnya.
        if (!in_array('pertanyaan_metode', $shown, true)) {
            [$_, $metode] = $this->toolGetIntentResponse('pertanyaan_metode');
            $bubbles = array_merge($bubbles, $metode);
            $this->recordSlugShown($session, 'pertanyaan_metode');
        }

        // 2. (dihapus) contoh_dokumentasi mini vlog — feedback client
        //    2026-08-14: bukan info relevan saat customer tanya harga,
        //    bikin bubble jadi terlalu banyak / spam. Pindah ke followup
        //    H+1 kalau leads tidak reply, atau intent trigger eksplisit
        //    `pertanyaan_dokumentasi` kalau customer tanya sendiri.

        // 3. (dihapus) testimoni_google_review — feedback client 2026-08-14:
        //    1 menit setelah vlog terasa terburu-buru. Pindah ke followup H+1.

        // 4. Delay pre-quote SENGAJA dihapus juga — sebelumnya 35-50s makes
        //    sense saat ada mini vlog + testimoni sebelumnya (kasih waktu
        //    menonton). Sekarang bundle cuma metode → quote, delay bikin
        //    bot silent tanpa konteks.

        // 5. Quote harga paket — pilih slug berdasarkan usia:
        //   - usia_anak >= 17 tahun (satuan=tahun) → quote_harga_paket_dewasa
        //     (Rp 3.500.000, konten manfaat medis dewasa). Per instruksi
        //     dr. Yoga 2026-08-16.
        //   - kalau promo aktif → quote_harga_paket_promo (override anak).
        //   - default → quote_harga_paket (Rp 2.500.000 + hadiah anak).
        $usiaVal   = (int) $session->getData('usia_anak');
        $usiaSat   = (string) $session->getData('usia_anak_satuan');
        $isDewasa  = ($usiaSat === 'tahun' && $usiaVal >= 17);
        $quoteSlug = $isDewasa ? 'quote_harga_paket_dewasa' : 'quote_harga_paket';
        if (!$isDewasa) {
            $promoIntent = BotIntent::where('intent', 'quote_harga_paket_promo')
                ->where('active', true)->first();
            if ($promoIntent !== null) {
                $quoteSlug = 'quote_harga_paket_promo';
            }
        }
        [$_, $quote] = $this->toolGetIntentResponse($quoteSlug);
        $bubbles = array_merge($bubbles, $quote);

        // 5b. Diskon rombongan — kalau jumlah_anak ≥ 2 DAN bukan dewasa
        //     (paket dewasa tidak ada diskon rombongan). Angka:
        //       2 anak: diskon Rp 500rb → total Rp 4.500.000
        //       3 anak: diskon Rp 1jt   → total Rp 6.500.000
        //       ≥4 anak: hubungi admin utk custom quote.
        $jml = (int) $session->getData('jumlah_anak');
        if ($jml >= 2 && !$isDewasa) {
            $diskonBubble = null;
            if ($jml === 2) {
                $diskonBubble = "🎉 *Diskon Rombongan* untuk 2 anak:\n"
                              . "• Diskon Rp 500.000\n"
                              . "• Total setelah diskon: *Rp 4.500.000* untuk 2 anak kak 🙏";
            } elseif ($jml === 3) {
                $diskonBubble = "🎉 *Diskon Rombongan* untuk 3 anak:\n"
                              . "• Diskon Rp 1.000.000\n"
                              . "• Total setelah diskon: *Rp 6.500.000* untuk 3 anak kak 🙏";
            } else {
                $diskonBubble = "🎉 Untuk sunat rombongan {$jml} anak, silakan chat admin utk quote khusus ya kak 🙏 Diskon rombongan lebih besar bisa dinegosiasi.";
            }
            $bubbles[] = ['text' => $diskonBubble, 'media' => null];
        }

        // 6. Tanya pertanyaan lanjutan / closing.
        [$_, $closing] = $this->toolGetIntentResponse('tanya_pertanyaan_lanjutan');
        $bubbles = array_merge($bubbles, $closing);

        $session->setData('_harga_sent', true);
        $session->save();

        Log::info('SUNAT_BOT_AGENT_HARGA_QUOTE_SENT', [
            'phone'   => $session->no_telp,
            'bubbles' => count($bubbles),
            'promo'   => $quoteSlug === 'quote_harga_paket_promo',
        ]);

        return [
            ['ok' => true, 'bubbles' => count($bubbles), 'slug' => $quoteSlug],
            $bubbles,
            false,
        ];
    }

    /**
     * Cek apakah session sedang di tengah harga OR booking collection —
     * ada field terisi tapi belum semua wajib terkumpul. Dipakai utk
     * relax hard-guard drop-post-tool-text: kalau resume interrupt,
     * agent perlu emit follow-up "Balik ke tadi kak, [pertanyaan]".
     */
    private function hasActiveCollection(BotSession $session): bool
    {
        // Harga: ada field terisi + belum quote_sent
        if (!$session->getData('_harga_sent')) {
            foreach (self::HARGA_REQUIRED_FIELDS as $f) {
                if ($session->getData($f) !== null && $session->getData($f) !== '') {
                    return true;
                }
            }
        }
        // Booking: flag booking_started ATAU ada field terisi
        if (!$session->is_complete) {
            if ($session->getData('booking_started')) return true;
            foreach (self::BOOKING_REQUIRED_FIELDS as $f) {
                if ($session->getData($f) !== null && $session->getData($f) !== '') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Track intent slug yg sudah di-render di session (dedupe cross-turn).
     */
    private function recordSlugShown(BotSession $session, string $slug): void
    {
        if ($slug === '') return;
        $shown = (array) $session->getData('_slugs_shown');
        if (in_array($slug, $shown, true)) return;
        $shown[] = $slug;
        $session->setData('_slugs_shown', $shown);
        $session->save();
    }

    /**
     * Nilai yg dianggap "tidak ada / kosong" untuk field indikasi/riwayat.
     * Case-insensitive substring check.
     */
    private function isNoValue(string $v): bool
    {
        $v = mb_strtolower(trim($v));
        if ($v === '') return true;
        foreach (['tidak ada', 'tidak', 'gak ada', 'ga ada', 'ngga ada', 'nggak', 'ndak ada', 'nihil', 'gapapa', 'ga apa', 'sehat', 'normal', 'baik'] as $needle) {
            if ($v === $needle || str_contains($v, $needle)) return true;
        }
        return false;
    }

    // ----- SESSION STATE SNAPSHOT ------------------------------------

    /**
     * Render snapshot session data yg sudah terkumpul supaya agent tahu
     * field mana yg sudah ada + tidak re-ask. Cuma field yang punya
     * nilai valid (non-null, non-empty, dan bukan placeholder).
     * Return string kosong kalau tidak ada data — supaya tidak inject
     * bubble kosong.
     */
    private function renderSessionStateSnapshot(BotSession $session): string
    {
        $data = $session->collected_data ?? [];
        if (!is_array($data)) return '';

        $lines = [];

        // Lead / harga fields
        $labelMap = [
            'nama_orang_tua'    => 'Nama customer',
            'domisili'          => 'Domisili',
            'usia_anak'         => 'Usia anak',
            'indikasi_khitan'   => 'Indikasi khitan',
            'postur_tubuh'      => 'Postur tubuh',
            'riwayat_kesehatan' => 'Riwayat kesehatan',
            'sudah_tahu_metode' => 'Sudah tahu metode',
            'jumlah_anak'       => 'Jumlah anak',
        ];
        $placeholders = ['pengirim', 'customer', 'kakak', 'bunda', 'ayah', 'ibu', 'bapak', '-', 'kak'];

        foreach ($labelMap as $key => $label) {
            $val = $data[$key] ?? null;
            if ($val === null || $val === '') continue;
            $strVal = trim((string) $val);
            if ($strVal === '') continue;
            // Skip placeholder-looking names
            if ($key === 'nama_orang_tua' && in_array(mb_strtolower($strVal), $placeholders, true)) {
                continue;
            }
            if ($key === 'usia_anak') {
                $satuan = trim((string) ($data['usia_anak_satuan'] ?? 'tahun'));
                $strVal .= ' ' . $satuan;
            }
            $lines[] = "- {$label}: {$strVal}";
        }

        // Booking fields (subset — kalau session in booking mode)
        $bookingKeys = [
            'tanggal'         => 'Tanggal booking',
            'jam'             => 'Jam booking',
            'nama_anak'       => 'Nama anak',
            'nama_panggilan'  => 'Nama panggilan anak',
        ];
        foreach ($bookingKeys as $key => $label) {
            $val = $data[$key] ?? null;
            if ($val === null || $val === '') continue;
            $strVal = trim((string) $val);
            if ($strVal === '') continue;
            $lines[] = "- {$label}: {$strVal}";
        }

        // Signal flags
        if (!empty($data['_harga_sent'])) {
            $lines[] = '- ⚠️ Harga sudah pernah dikirim ke customer (bubble quote sudah tampil di history). JANGAN kirim ulang kecuali customer eksplisit minta.';
        }
        if (!empty($data['booking_started'])) {
            $lines[] = '- ⚠️ Booking flow sedang aktif — save_harga_data akan di-reject engine, pakai save_booking_data.';
        }

        if (empty($lines)) return '';

        return "═══ SESSION STATE (data yang sudah terkumpul — JANGAN tanya ulang) ═══\n"
             . implode("\n", $lines)
             . "\n\nAturan: kalau field di atas sudah ada, LARANG tanya lagi. Lanjut ke field yg belum ada, atau kalau semua field HARGA_REQUIRED sudah lengkap dan customer minta harga → langsung `send_harga_quote()`.";
    }

    // ----- HISTORY ---------------------------------------------------

    private function loadHistory(BotSession $session): array
    {
        // Sumber KEBENARAN untuk history = tabel `messages` (chat_sunat=1),
        // BUKAN cuma session.agent_history (JSON). Alasan (per instruksi
        // dr. Yoga 2026-08-24):
        //   - `from_me_sunat` (admin Rona ketik dari HP) tidak masuk
        //     agent_history JSON, jadi agent kehilangan konteks kalau
        //     admin sudah tanya sesuatu manual.
        //   - Jawaban customer ke pertanyaan admin tersebut harus juga
        //     jadi konteks utk agent lanjut ke pertanyaan berikutnya.
        //   - Session bisa lifetime pendek (is_complete/reset) — history
        //     Messages lebih tahan.
        //
        // Ambil last N pesan chat_sunat dari phone ini (7 hari terakhir,
        // capped HISTORY_MAX_TURNS*2). Skip noise: template followup,
        // template allowlist redirect, template konsul dokter ack, dsb.
        $phone = (string) $session->no_telp;
        if ($phone === '') return [];

        $skipIntents = [
            'sunat_followup',
            'sunat_allowlist_redirect',
            'konsul_dokter_ack',
            'konsul_dokter_reply', // ini reply dokter, jangan double-count
            'dev_reset_ack',
        ];

        $rows = \App\Models\Message::where('no_telp', $phone)
            ->where('chat_sunat', 1)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->where(function ($q) use ($skipIntents) {
                $q->whereNull('flagged_intent')
                  ->orWhereNotIn('flagged_intent', $skipIntents);
            })
            ->orderBy('id', 'asc')
            ->limit(self::HISTORY_MAX_TURNS * 4) // buffer sblm filter final
            ->get(['id', 'message', 'sending', 'flagged_intent']);

        $clean = [];
        foreach ($rows as $row) {
            $content = trim((string) $row->message);
            if ($content === '') continue;
            // sending=1 = outbound (bot/admin/system) → 'assistant'
            // sending=0 = inbound (customer) → 'user'
            $role = ((int) $row->sending === 1) ? 'assistant' : 'user';
            $clean[] = ['role' => $role, 'content' => $content];
        }

        // Fallback: kalau tidak ada Message rows sama sekali (mis. brand
        // new phone), pakai session.agent_history JSON kalau ada.
        if (empty($clean)) {
            $raw = $session->agent_history ?? null;
            if ($raw === null) return [];
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            if (!is_array($decoded)) return [];
            foreach ($decoded as $m) {
                $role = (string) ($m['role'] ?? '');
                if ($role !== 'user' && $role !== 'assistant') continue;
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

    private const ABBREV = [
        'Komp', 'No', 'Jl', 'Km', 'Yth', 'Dst', 'Dll', 'Pak', 'Bu', 'Tn',
        'Ny', 'Apt', 'Ir', 'Drs', 'Prof', 'Min', 'Hal', 'Bpk', 'Sdr',
        'Tgl', 'Th', 'a.n', 'u.p', 'd.a', 'ttd',
    ];

    /**
     * Mirror SunatBotEngine::splitText untuk FAQ — supaya bot reply
     * berbentuk "1 bubble 1 inti pesan" sesuai feedback user. Aturan:
     *   1. Marker [BUBBLE] eksplisit selalu dihormati.
     *   2. Selain itu: pecah per kalimat (titik/seru/tanya + whitespace),
     *      respect abbreviation list supaya "Komp.", "No.", "Bpk." dst
     *      tidak salah dipecah.
     */
    private function splitSentences(string $text): array
    {
        $text = trim($text);
        if ($text === '') return [];

        if (str_contains($text, '[BUBBLE]')) {
            $parts = preg_split('/\s*\[BUBBLE\]\s*/u', $text) ?: [];
            $out = [];
            foreach ($parts as $p) {
                $p = trim((string) $p);
                if ($p !== '') $out[] = $p;
            }
            return $out ?: [$text];
        }

        $tokens = preg_split('/([.!?])(\s+|$)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($tokens)) return [$text];

        $bubbles = [];
        $current = '';
        $count   = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $current .= $tokens[$i];
            $punct = $tokens[$i + 1] ?? null;
            if ($punct === null || !in_array($punct, ['.', '!', '?'], true)) {
                continue;
            }
            $current .= $punct;
            $ws       = $tokens[$i + 2] ?? '';
            $i       += 2;

            if ($this->endsWithAbbreviation($current)) {
                $current .= $ws;
                continue;
            }

            $bubbles[] = trim($current);
            $current   = '';
        }
        if (trim($current) !== '') {
            $bubbles[] = trim($current);
        }

        $bubbles = array_values(array_filter($bubbles, fn ($b) => $b !== ''));
        return $bubbles ?: [$text];
    }

    private function endsWithAbbreviation(string $text): bool
    {
        foreach (self::ABBREV as $abbrev) {
            $needle = $abbrev . '.';
            $len    = mb_strlen($needle);
            if (mb_substr($text, -$len) === $needle) {
                return true;
            }
        }
        return false;
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
