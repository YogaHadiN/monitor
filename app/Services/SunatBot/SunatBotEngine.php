<?php

namespace App\Services\SunatBot;

use App\Models\BotIntent;
use App\Models\BotSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SunatBotEngine
{
    /**
     * Stage 2 (Flow Konsultasi Harga). Each entry is field => intent slug
     * the bot renders to ASK for that field. Order is the visit order;
     * nextMissingHargaField walks this map and returns the first field
     * with no value in collected_data.
     *
     * Step 2.3 (usia_bb) is naturally skipped when step 2.1 already
     * captured both usia_anak and berat_badan_anak — captureForField
     * marks the usia_bb sentinel so this loop sees it as "filled".
     */
    private const HARGA_FLOW = [
        'nama_orang_tua'      => 'tanya_nama_orang_tua',     // 2.1
        'domisili'            => 'tanya_domisili',            // 2.2
        'usia_bb'             => 'tanya_usia_bb_konfirmasi',  // 2.3
        'indikasi_khitan'     => 'tanya_indikasi',            // 2.4
        'riwayat_kesehatan'   => 'tanya_riwayat_kesehatan',   // 2.5 (escalation gate)
        'sudah_tahu_metode'   => 'tanya_sudah_tahu_metode',   // 2.6 (conditional render)
        'pengalaman_medis'    => 'tanya_pengalaman',          // 2.7 (emits 2.8 after capture)
        'setuju_dokumentasi'  => 'tanya_setuju_dokumentasi',  // 2.9 (conditional render)
    ];

    /**
     * Stage 2 closing (Step 2.10). Emitted when all HARGA_FLOW fields
     * are filled. After emitting, expecting_field is set to the sentinel
     * 'pertanyaan_lanjutan' so the next message goes through
     * handlePertanyaanLanjutan instead of advancing fields.
     */
    private const HARGA_CLOSING = [
        'quote_harga_paket',
        'tanya_pertanyaan_lanjutan',
    ];

    /**
     * AI-classified intents that trigger an immediate escalation to a
     * human admin. Anything in this list short-circuits the normal
     * intent rendering: the slug's template is emitted as a brief
     * acknowledgement, then escalate() flips the session and appends
     * the handover bubble. Used for booking/scheduling intents that
     * the bot cannot fulfil.
     */
    private const ESCALATION_INTENTS = [
        'mau_booking_jadwal',
    ];

    private const FIELD_DESCRIPTIONS = [
        'nama_orang_tua'     => 'nama depan orang tua / pengirim pesan, contoh "Yeni"',
        'domisili'           => 'kota / kecamatan domisili pasien (Tangerang/Jakarta/dst)',
        'usia_bb'            => 'usia anak (tahun) dan berat badan (kg) digabung apa adanya',
        'indikasi_khitan'    => 'alasan / keluhan medis yang menyebabkan ingin di-khitan, atau "tidak ada"',
        'riwayat_kesehatan'  => 'kondisi kesehatan khusus anak (gangguan pembekuan darah, jantung, autisme, dll) atau "tidak ada"',
        'sudah_tahu_metode'  => 'apakah pasien sudah tahu metode khitan kami: "ya"/"sudah" atau "belum"/"tidak"',
        'pengalaman_medis'   => 'pengalaman tindakan medis anak sebelumnya (trauma/sudah pernah disunat) atau "belum ada"',
        'setuju_dokumentasi' => 'apakah pasien setuju dibuatkan dokumentasi gratis: "ya" atau "tidak"',
    ];

    public function __construct(private IntentClassifier $classifier)
    {
    }

    /**
     * Process an incoming WA message.
     * Returns ['handled' => bool, 'replies' => array<['text'=>string,'media'=>?string]>]
     */
    public function handle(string $noTelp, string $message): array
    {
        $msg        = trim($message);
        $msgLower   = mb_strtolower($msg);
        $hasTrigger = $this->hasTrigger($msgLower);
        $session    = BotSession::where('no_telp', $noTelp)->first();

        if ($session === null && !$hasTrigger) {
            return ['handled' => false, 'replies' => []];
        }

        // Gratitude exit keyword (terima kasih, makasih, etc.) — close
        // session politely and let the legacy Wablas paths handle the
        // next bubble.
        if ($session && !$session->is_complete && $this->isExitKeyword($msgLower)) {
            $session->is_complete      = true;
            $session->last_activity_at = Carbon::now();
            $session->save();
            return [
                'handled' => true,
                'replies' => [[
                    'text'  => (string) config('sunatbot.exit_message'),
                    'media' => null,
                ]],
            ];
        }

        // Explicit admin / CS request — escalate immediately regardless
        // of which flow step we are on. Whole-word match so we don't
        // false-trigger on incidental occurrences.
        if ($session && !$session->is_complete && $this->isAdminKeyword($msgLower)) {
            return [
                'handled' => true,
                'replies' => $this->escalate($session),
            ];
        }

        // Booking / scheduling intent — same deterministic short-circuit
        // as admin keyword. Customers ready to book go straight to the
        // admin queue; the bot does not handle scheduling or payments.
        // Looser phrasing still gets caught by the mau_booking_jadwal
        // AI intent inside classifyAndRespond as a fallback.
        if ($session && !$session->is_complete && $this->isBookingKeyword($msgLower)) {
            return [
                'handled' => true,
                'replies' => $this->escalate($session),
            ];
        }

        if ($session && $session->is_complete) {
            // is_complete + new sunat trigger → drop the stale session
            // so the flow restarts. Otherwise stay out and let legacy
            // paths handle (daftar, libur, etc.).
            if ($hasTrigger) {
                $session->delete();
                $session = null;
            } else {
                return ['handled' => false, 'replies' => []];
            }
        }

        $replies     = [];
        $justCreated = false;

        if ($session === null) {
            $session = BotSession::create([
                'no_telp'          => $noTelp,
                'collected_data'   => [],
                'last_activity_at' => Carbon::now(),
            ]);
            $replies = array_merge($replies, $this->renderIntent('trigger_sunat', $session));
            $justCreated = true;
        }

        if ($session->expecting_field !== null) {
            $replies = array_merge($replies, $this->processHargaTurn($session, $msg));
        } else {
            $replies = array_merge($replies, $this->classifyAndRespond($session, $msg, $justCreated));
        }

        $session->last_activity_at = Carbon::now();
        $session->save();

        return ['handled' => count($replies) > 0, 'replies' => $replies];
    }

    /**
     * Outside the harga flow: classify the message, render templates for
     * normal reply intents, and enter the harga flow if pertanyaan_harga
     * was detected.
     */
    private function classifyAndRespond(BotSession $session, string $message, bool $skipFallback = false): array
    {
        $candidates = $this->candidateSlugs();
        $intents    = $this->classifier->classify($message, $candidates);

        // Drop generic ack intents whenever the same message also matched
        // at least one specific intent — the catch-all ack would just
        // clutter the reply.
        $genericSlugs = (array) config('sunatbot.generic_intents', ['konsultasi']);
        if ($intents !== []) {
            $nonGeneric = array_values(array_filter(
                $intents,
                fn ($s) => !in_array($s, $genericSlugs, true)
            ));
            if ($nonGeneric !== []) {
                $intents = $nonGeneric;
            }
        }

        if (empty($intents)) {
            if ($skipFallback) {
                return [];
            }
            return $this->renderIntent('fallback_unknown', $session);
        }

        // Order: side answers first → harga trigger last.
        usort($intents, function ($a, $b) {
            $rank = function ($x) {
                if ($x === 'pertanyaan_harga') return 2;
                if ($x === 'tanya_jadwal')     return 1;
                return 0;
            };
            return $rank($a) - $rank($b);
        });

        $replies        = [];
        $hargaTriggered = false;
        foreach ($intents as $slug) {
            if (in_array($slug, self::ESCALATION_INTENTS, true)) {
                // AI matched a slug whose semantic is "ready to book /
                // register" — short-circuit the rest of the loop, emit
                // the slug's acknowledgement template, then escalate.
                $ack = $this->renderIntent($slug, $session);
                return array_merge($ack, $this->escalate($session));
            }
            if ($slug === 'pertanyaan_harga') {
                $hargaTriggered = true;
                continue;
            }
            if ($slug === 'tanya_jadwal') {
                $replies = array_merge($replies, $this->renderIntent('tanya_jadwal', $session));
                return $replies;
            }
            $replies = array_merge($replies, $this->renderIntent($slug, $session));
        }

        if ($hargaTriggered) {
            // pertanyaan_harga is used as a CLASSIFIER candidate (its
            // keywords let the AI detect price questions) but its
            // template is NOT rendered as a separate bubble — step 2.1
            // (tanya_nama_orang_tua) already opens with "Untuk biaya
            // sunat tergantung usia dan berat badan kak. ..." per
            // proposal §2.1, so rendering pertanyaan_harga here would
            // duplicate that line.
            $next = $this->nextMissingHargaField($session);
            if ($next === null) {
                $replies = array_merge($replies, $this->emitHargaClosing($session));
            } else {
                $session->expecting_field = $next;
                $replies = array_merge($replies, $this->renderIntent(self::HARGA_FLOW[$next], $session));
            }
        }

        return $replies;
    }

    /**
     * Inside the harga flow. Captures the value for the currently
     * expected field, runs per-step gates (validation, escalation,
     * conditional renders), then advances to the next missing field or
     * the closing.
     */
    private function processHargaTurn(BotSession $session, string $message): array
    {
        $field = $session->expecting_field;

        // After step 2.10 the customer is in the follow-up question loop.
        if ($field === 'pertanyaan_lanjutan') {
            return $this->handlePertanyaanLanjutan($session, $message);
        }

        // ---- Capture phase ----------------------------------------
        $capturedValue = $this->captureForField($session, $field, $message);

        // Step 2.3 validation: usia_bb out of range → re-ask, do not advance.
        if ($field === 'usia_bb' && !$this->hasValidUsiaBB($session)) {
            return $this->renderIntent('validasi_ulang_usia_bb', $session);
        }

        // Step 2.5 escalation gate.
        if ($field === 'riwayat_kesehatan' && $this->detectSpecialHandling($capturedValue)) {
            return $this->escalate($session);
        }

        // ---- Conditional renders before advancing -----------------
        $extra = [];
        if ($field === 'sudah_tahu_metode' && !$this->isYesValue($capturedValue, $field)) {
            // 2.6 — pasien belum tahu metode → kirim edukasi metode.
            $extra = array_merge($extra, $this->renderIntent('pertanyaan_metode', $session));
        }
        if ($field === 'pengalaman_medis') {
            // 2.8 — testimonial video + penjelasan kelebihan, selalu kirim.
            $extra = array_merge($extra, $this->renderIntent('edukasi_kelebihan', $session));
        }
        if ($field === 'setuju_dokumentasi' && $this->isYesValue($capturedValue, $field)) {
            // 2.9 — pasien setuju → kirim contoh konten dokumentasi.
            $extra = array_merge($extra, $this->renderIntent('contoh_dokumentasi', $session));
        }

        // ---- Advance ----------------------------------------------
        $next = $this->nextMissingHargaField($session);
        if ($next === null) {
            return array_merge($extra, $this->emitHargaClosing($session));
        }

        $session->expecting_field = $next;
        return array_merge($extra, $this->renderIntent(self::HARGA_FLOW[$next], $session));
    }

    /**
     * Capture the value for a single field. Returns the raw captured
     * string (used by the conditional-render gates above to decide yes/no
     * branching). Side-effects setData on the session.
     *
     * Special cases:
     * - nama_orang_tua (Step 2.1): also tries to extract usia + bb in
     *   the same AI call so a reply like "Saya Yeni, anak 6 tahun BB 20"
     *   skips Step 2.3 entirely.
     * - usia_bb (Step 2.3): parses + validates against the configured
     *   range. On invalid input the sentinel is NOT set, so the field
     *   stays missing and the caller re-asks.
     */
    private function captureForField(BotSession $session, string $field, string $message): string
    {
        // Opportunistic usia/BB scan. As long as the sentinel is unset,
        // every step's capture also looks for usia + berat badan in the
        // same AI call. Customer might volunteer the numbers at any
        // turn (a delayed "anak saya 8 thn 32 kg" after the buffer
        // window closes lands at the domisili step but should still
        // skip step 2.3). Single AI call per turn — no extra latency.
        $needUsiaBB = $session->getData('usia_bb') === null;

        $usiaDesc = 'usia anak dalam tahun, hanya angka integer (1-18). string kosong kalau tidak disebut.';
        $bbDesc   = 'berat badan anak dalam kg, hanya angka (boleh desimal). string kosong kalau tidak disebut.';

        if ($field === 'usia_bb') {
            $extracted = $this->classifier->extractFields([
                'usia_anak'        => $usiaDesc,
                'berat_badan_anak' => $bbDesc,
            ], $message);
            $this->trySetUsiaBB($session, $extracted['usia_anak'], $extracted['berat_badan_anak'], $message);
            return $message;
        }

        // Build the field map for the AI: the primary field for this
        // step, plus opportunistic usia/BB if still missing.
        $fields = [
            $field => self::FIELD_DESCRIPTIONS[$field] ?? $field,
        ];
        if ($needUsiaBB) {
            $fields['usia_anak']        = $usiaDesc;
            $fields['berat_badan_anak'] = $bbDesc;
        }
        $extracted = $this->classifier->extractFields($fields, $message);

        $stored = $extracted[$field] !== '' ? $extracted[$field] : trim($message);
        if ($stored !== '') {
            $session->setData($field, $stored);
        }

        if ($needUsiaBB) {
            $this->trySetUsiaBB(
                $session,
                $extracted['usia_anak'] ?? '',
                $extracted['berat_badan_anak'] ?? '',
                $message
            );
        }

        return $stored;
    }

    /**
     * Parse + validate usia/BB and persist to session.collected_data
     * (usia_anak, berat_badan_anak, and the usia_bb sentinel) only when
     * BOTH values are present and within the configured range. Invalid
     * input leaves usia_bb unset so the flow re-asks.
     */
    private function trySetUsiaBB(BotSession $session, string $usiaRaw, string $bbRaw, string $rawMessage): void
    {
        $usiaMin = (int)   config('sunatbot.usia_min', 1);
        $usiaMax = (int)   config('sunatbot.usia_max', 18);
        $bbMin   = (float) config('sunatbot.berat_badan_min', 5);
        $bbMax   = (float) config('sunatbot.berat_badan_max', 100);

        $usia = $this->parseInt($usiaRaw);
        $bb   = $this->parseFloat($bbRaw);

        if ($usia === null || $bb === null) {
            return; // missing — bot will ask in step 2.3
        }
        if ($usia < $usiaMin || $usia > $usiaMax || $bb < $bbMin || $bb > $bbMax) {
            return; // out of range — bot will re-ask via validasi_ulang_usia_bb
        }

        $session->setData('usia_anak', $usia);
        $session->setData('berat_badan_anak', $bb);
        $session->setData('usia_bb', trim($rawMessage) !== '' ? trim($rawMessage) : "{$usia} tahun, {$bb} kg");
    }

    private function hasValidUsiaBB(BotSession $session): bool
    {
        return $session->getData('usia_anak') !== null
            && $session->getData('berat_badan_anak') !== null
            && $session->getData('usia_bb') !== null;
    }

    private function parseInt(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        if (preg_match('/-?\d+/', $raw, $m)) {
            return (int) $m[0];
        }
        return null;
    }

    private function parseFloat(string $raw): ?float
    {
        $raw = trim(str_replace(',', '.', $raw));
        if ($raw === '') return null;
        if (preg_match('/-?\d+(?:\.\d+)?/', $raw, $m)) {
            return (float) $m[0];
        }
        return null;
    }

    /**
     * Step 2.10 — emit the price quote bundle and pivot the session into
     * the follow-up loop (expecting_field = 'pertanyaan_lanjutan'). Does
     * NOT mark is_complete; closure happens via handlePertanyaanLanjutan
     * once the customer signals they're done or escalation triggers.
     */
    private function emitHargaClosing(BotSession $session): array
    {
        $session->expecting_field = 'pertanyaan_lanjutan';
        $replies = [];
        foreach (self::HARGA_CLOSING as $slug) {
            $replies = array_merge($replies, $this->renderIntent($slug, $session));
        }
        return $replies;
    }

    /**
     * After step 2.10 the customer either has another question (loop) or
     * signals "tidak ada / sudah jelas" (escalate). Out-of-scope follow
     * ups also escalate so the admin can take over.
     */
    private function handlePertanyaanLanjutan(BotSession $session, string $message): array
    {
        $msgLower = mb_strtolower(trim($message));

        if ($this->matchesClosingPhrase($msgLower)) {
            return $this->escalate($session);
        }

        // Customer asked a follow-up — try to match a QnA intent. If no
        // match, escalate so the admin handles the off-script question.
        $replies = $this->classifyAndRespond($session, $message, true);
        if (empty($replies)) {
            return $this->escalate($session);
        }

        // Stay in the loop so subsequent messages still get this handler.
        return $replies;
    }

    /**
     * Mark the session for human handover, close it, and emit the
     * handover bubble. The actual sudah_dibalas=0 flip on the inbound
     * messages that triggered escalation lives in
     * ProcessPendingSunatBotMessages so it can scope to the current
     * buffer flush precisely (id-based cutoffs in the engine miss the
     * trigger message when the dispatcher is mid-stream).
     */
    private function escalate(BotSession $session): array
    {
        $session->requires_special_handling = true;
        $session->is_complete               = true;
        $session->last_activity_at          = Carbon::now();
        $session->save();

        Log::info('SUNAT_BOT_ESCALATED', [
            'phone'   => $session->no_telp,
            'session' => $session->id,
            'data'    => $session->collected_data,
        ]);

        return [[
            'text'  => (string) config('sunatbot.handover_message'),
            'media' => null,
        ]];
    }

    private function nextMissingHargaField(BotSession $session): ?string
    {
        foreach (array_keys(self::HARGA_FLOW) as $field) {
            $val = $session->getData($field);
            if ($val === null || $val === '') {
                return $field;
            }
        }
        return null;
    }

    private function candidateSlugs(): array
    {
        // Intents with keywords participate in classification. Bot-prompt
        // intents (tanya_*, edukasi_*, quote_*, validasi_*, contoh_*) have
        // empty keywords and are emitted by the engine, not chosen by AI.
        return BotIntent::where('active', true)
            ->whereNotNull('keywords')
            ->where('keywords', '!=', '')
            ->whereNotIn('intent', ['trigger_sunat', 'fallback_unknown'])
            ->orderBy('urutan')
            ->pluck('intent')
            ->all();
    }

    /**
     * Trigger detection — substring match for "sunat" / "khitan". The
     * proposal §7 mentions naive negation ("saya TIDAK mau sunat") as a
     * future concern; for now we accept the false-positive risk.
     */
    private function hasTrigger(string $msgLower): bool
    {
        return str_contains($msgLower, 'sunat') || str_contains($msgLower, 'khitan');
    }

    /**
     * Match the customer's message against gratitude phrases that signal
     * they want to wrap up. Defaults cover Indonesian/English thank-you
     * variants. Override via SUNATBOT_EXIT_KEYWORDS (comma-separated).
     * Exact-equality on trimmed message (leading "/" stripped).
     */
    private function isExitKeyword(string $msgLower): bool
    {
        $needle = ltrim(trim($msgLower), '/');
        if ($needle === '') return false;

        $defaults = [
            'terima kasih', 'terimakasih',
            'makasih', 'makasi', 'mksh', 'trims',
            'thanks', 'thank you', 'thx', 'tq',
        ];
        $configured = (string) env('SUNATBOT_EXIT_KEYWORDS', '');
        $list = $configured !== ''
            ? array_filter(array_map('trim', explode(',', $configured)), fn ($v) => $v !== '')
            : $defaults;

        foreach ($list as $kw) {
            if ($needle === mb_strtolower($kw)) return true;
        }
        return false;
    }

    /**
     * Whole-word match for "admin", "cs", "manusia", "petugas", "operator".
     * Whole-word so "administrasi" doesn't trigger on "admin". Used to
     * honour explicit handover requests.
     */
    private function isAdminKeyword(string $msgLower): bool
    {
        $keywords = (array) config('sunatbot.admin_keywords', []);
        foreach ($keywords as $kw) {
            $kw = trim((string) $kw);
            if ($kw === '') continue;
            if (preg_match('/\b' . preg_quote(mb_strtolower($kw), '/') . '\b/u', $msgLower)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Same word-boundary match as isAdminKeyword, but for explicit
     * booking / scheduling phrases ("mau booking", "saya mau daftar",
     * "jadwalkan saya"). The bot has no scheduling or payment scope
     * so customers ready to book are handed off to admin.
     */
    private function isBookingKeyword(string $msgLower): bool
    {
        $keywords = (array) config('sunatbot.booking_keywords', []);
        foreach ($keywords as $kw) {
            $kw = trim((string) $kw);
            if ($kw === '') continue;
            if (preg_match('/\b' . preg_quote(mb_strtolower($kw), '/') . '\b/u', $msgLower)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Substring match (case-insensitive) on the captured riwayat_kesehatan
     * value. A hit means the bot stops the flow and escalates to a human
     * — medical-complex cases are out of scope per proposal §2.5.
     */
    private function detectSpecialHandling(string $value): bool
    {
        $valueLower = mb_strtolower($value);
        $keywords   = (array) config('sunatbot.special_handling_keywords', []);
        foreach ($keywords as $kw) {
            $kw = trim((string) $kw);
            if ($kw === '') continue;
            if (str_contains($valueLower, mb_strtolower($kw))) {
                return true;
            }
        }
        return false;
    }

    private function matchesClosingPhrase(string $msgLower): bool
    {
        $phrases = (array) config('sunatbot.closing_phrases', []);
        foreach ($phrases as $p) {
            $p = trim((string) $p);
            if ($p === '') continue;
            if ($msgLower === mb_strtolower($p)) return true;
        }
        return false;
    }

    /**
     * Loose yes-detection on a captured boolean-ish value (sudah_tahu_metode,
     * setuju_dokumentasi). Returns true when the value clearly affirms,
     * false otherwise (including ambiguous answers — we prefer the
     * cautious "treat as no" path which renders the educational bubble).
     *
     * For the sudah_tahu_metode question specifically, the literal "iya"
     * is ambiguous: a customer who says "iya tolong jelaskan" is
     * acknowledging the bot then asking for explanation, not affirming
     * they already know. So when the question is sudah_tahu_metode and
     * the message contains an explanation-request pattern (jelaskan /
     * info / dijelaskan / kasih tau), treat the answer as NOT-yes so
     * the engine renders pertanyaan_metode.
     */
    private function isYesValue(string $value, string $field = ''): bool
    {
        $v = mb_strtolower(trim($value));
        if ($v === '') return false;

        // Field-specific override: explanation-request signals trump
        // any "iya"/"ok" in the same message for the sudah_tahu_metode
        // question. Conservative on purpose — favours showing the
        // explanation if the customer hinted they want one.
        if ($field === 'sudah_tahu_metode') {
            $explainPatterns = [
                'jelaskan', 'jelasin', 'dijelaskan', 'dijelasin',
                'kasih tau', 'kasi tau', 'kasi tahu', 'kasih tahu',
                'minta info', 'tolong info', 'infokan', 'minta penjelasan',
                'penjelasan', 'di info', 'diinfo',
            ];
            foreach ($explainPatterns as $p) {
                if (str_contains($v, $p)) return false;
            }
        }

        // Negation prefix anywhere → not yes.
        if (preg_match('/\b(tidak|gak|nggak|belum|bukan)\b/u', $v)) {
            return false;
        }

        $yes = ['ya', 'iya', 'sudah', 'sudah tahu', 'sudah paham', 'paham', 'tahu', 'oke', 'ok', 'siap', 'boleh', 'mau', 'setuju', 'yes', 'y'];
        foreach ($yes as $kw) {
            if ($v === $kw) return true;
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/u', $v)) return true;
        }
        return false;
    }

    private function renderIntent(string $slug, BotSession $session): array
    {
        $intent = BotIntent::where('intent', $slug)->where('active', true)->first();
        if ($intent === null) {
            return [];
        }

        $template = (string) $intent->jawaban_template;
        if (trim($template) === '') {
            return [];
        }

        $text      = $this->substituteVariables($template, $session);
        $sentences = $this->splitText($text);
        $media     = $intent->mediaList();

        $bubbles = [];
        foreach ($media as $file) {
            $bubbles[] = ['text' => '', 'media' => $file];
        }
        foreach ($sentences as $s) {
            $bubbles[] = ['text' => $s, 'media' => null];
        }
        return $bubbles;
    }

    private const ABBREV = [
        'Komp', 'No', 'Jl', 'Km', 'Yth', 'Dst', 'Dll', 'Pak', 'Bu', 'Tn',
        'Ny', 'Apt', 'Ir', 'Drs', 'Prof', 'Min', 'Hal', 'Bpk', 'Sdr',
        'Tgl', 'Th', 'a.n', 'u.p', 'd.a', 'ttd',
    ];

    private function splitText(string $text): array
    {
        $marker = "\x01DOT\x01";
        $masked = $text;
        foreach (self::ABBREV as $abr) {
            $masked = preg_replace('/\b' . preg_quote($abr, '/') . '\.(?=\s|$)/u', $abr . $marker, $masked);
        }

        $parts = preg_split('/(?<=[.!?])\s+(?=\S)|\n+/u', $masked);
        $out = [];
        foreach ($parts as $p) {
            $p = str_replace($marker, '.', (string) $p);
            $p = trim($p);
            if ($p !== '') $out[] = $p;
        }
        return $out;
    }

    private function substituteVariables(string $template, BotSession $session): string
    {
        $alamat = (string) config('sunatbot.alamat_klinik', '');
        $maps   = (string) config('sunatbot.link_maps', '');
        $rona   = (string) config('sunatbot.nomor_rona', '');
        $nama   = (string) ($session->getData('nama_orang_tua') ?? $session->getData('nama') ?? '');

        $replacements = [
            '[NAMA]'          => $nama !== '' ? ucwords($nama) : 'kak',
            '[ALAMAT_KLINIK]' => $alamat,
            '[LINK_MAPS]'     => $maps,
            '[NOMOR_RONA]'    => $rona,
            '{{nama}}'        => $nama !== '' ? ucwords($nama) : 'kak',
        ];

        return strtr($template, $replacements);
    }
}
