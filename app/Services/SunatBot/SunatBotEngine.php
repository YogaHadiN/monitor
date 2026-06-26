<?php

namespace App\Services\SunatBot;

use App\Models\BotIntent;
use App\Models\BotSession;
use App\Models\JadwalSunat;
use App\Models\JadwalSunatBlackout;
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
        'usia_anak'           => 'tanya_usia_anak',           // 2.3a (split dari usia_bb)
        'berat_badan_anak'    => 'tanya_berat_badan_anak',    // 2.3b (split dari usia_bb)
        'indikasi_khitan'     => 'tanya_indikasi',            // 2.4
        'postur_tubuh'        => 'tanya_postur_tubuh',        // 2.4.5
        'riwayat_kesehatan'   => 'tanya_riwayat_kesehatan',   // 2.5 (escalation gate)
        'sudah_tahu_metode'   => 'tanya_sudah_tahu_metode',   // 2.6 (conditional render)
        'pengalaman_medis'    => 'tanya_pengalaman',          // 2.7 (emits 2.8 + contoh_dokumentasi)
    ];

    /**
     * Stage 2 closing (Step 2.10). Emitted when all HARGA_FLOW fields
     * are filled. After emitting, expecting_field is set to the sentinel
     * 'pertanyaan_lanjutan' so the next message goes through
     * handlePertanyaanLanjutan instead of advancing fields.
     */
    private const HARGA_CLOSING = [
        'testimoni_google_review',
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
    /**
     * Intent yang sebelumnya escalate ke admin — sekarang
     * `mau_booking_jadwal` masuk ke booking flow internal (lihat
     * enterBookingFlow + processBookingTurn). Const tetap ada supaya
     * gampang menambah intent escalation lain di masa depan.
     */
    private const ESCALATION_INTENTS = [
    ];

    /**
     * Slot jam booking sunat — mirror dari JadwalSunatController::slotJamList
     * di atika (single source: keduanya hardcoded sama agar bot tidak
     * butuh cross-app HTTP call). Update di SATU tempat kalau berubah.
     */
    private const BOOKING_JAM_SLOTS = [
        '07:00', '08:00', '09:00', '10:00', '11:00',
        '13:00', '14:00', '15:00', '16:00', '17:00',
    ];

    private const FIELD_DESCRIPTIONS = [
        'nama_orang_tua'     => 'nama depan orang tua / pengirim pesan, contoh "Yeni"',
        'domisili'           => 'kota / kecamatan domisili pasien (Tangerang/Jakarta/dst)',
        'usia_bb'            => 'usia anak (tahun) dan berat badan (kg) digabung apa adanya',
        'indikasi_khitan'    => 'alasan / keluhan medis yang menyebabkan ingin di-khitan, atau "tidak ada"',
        'postur_tubuh'       => 'postur tubuh anak: "gemuk" / "obesitas" kalau berat, atau "tidak gemuk" / "proporsional" / "kurus" kalau bukan',
        'riwayat_kesehatan'  => 'kondisi kesehatan khusus anak (gangguan pembekuan darah, jantung, autisme, dll) atau "tidak ada"',
        'sudah_tahu_metode'  => 'apakah pasien sudah tahu metode khitan kami: "ya"/"sudah" atau "belum"/"tidak"',
        'pengalaman_medis'   => 'pengalaman tindakan medis anak sebelumnya (trauma/sudah pernah disunat) atau "belum ada"',
    ];

    public function __construct(
        private IntentClassifier $classifier,
        private SunatBotAgent $agent,
    ) {
    }

    /**
     * Cek apakah nomor ini di-route ke SunatBotAgent (tool-calling LLM)
     * atau ke IntentClassifier lama. PR2 = allowlist nomor tertentu;
     * PR3 = default ON untuk semua (allowed_phones boleh dikosongkan).
     */
    private function shouldUseAgent(?string $noTelp): bool
    {
        if (!config('sunatbot.agent.enabled', false)) {
            return false;
        }
        $allowed = (array) config('sunatbot.agent.allowed_phones', []);
        if ($allowed === []) {
            return true; // Empty list = global rollout (PR3 behavior).
        }
        $clean = preg_replace('/\D+/', '', (string) $noTelp);
        return $clean !== '' && in_array($clean, $allowed, true);
    }

    /**
     * Entry helper untuk harga flow — di-share antara classifier path
     * (pertanyaan_harga di hargaTriggered block) dan agent path
     * (signal=enter_harga). Set expecting_field ke step pertama yang
     * belum terisi + render prompt-nya. Kalau semua sudah terisi,
     * langsung emit closing.
     *
     * Kalau $triggerMessage diisi, scan opportunistic untuk usia + bb
     * supaya step usia/BB di-skip kalau customer sudah sebut di pesan
     * yang sama dgn trigger (mis. "anak saya 8 th 30 kg mau tanya
     * harga"). Hemat 2 turn round-trip.
     */
    private function enterHargaFlow(BotSession $session, array $leadInReplies = [], ?string $triggerMessage = null, array $prefill = []): array
    {
        // PRE-FILL dari agent tool args (history-aware extraction).
        // Agent ekstrak field yg sudah disebut customer dari chat history,
        // engine populate session collected_data → skip step yang sudah ada.
        foreach ($prefill as $field => $value) {
            if ($field === 'usia_anak' && is_string($value)) {
                $parsed = $this->parseUsia('', $value);
                if ($parsed !== null) {
                    $session->setData('usia_anak', (int) $parsed[0]);
                    $session->setData('usia_anak_satuan', (string) $parsed[1]);
                }
            } elseif ($field === 'berat_badan_anak' && is_numeric($value)) {
                $session->setData('berat_badan_anak', (float) $value);
            } else {
                $session->setData($field, $value);
            }
        }

        if ($triggerMessage !== null && trim($triggerMessage) !== '') {
            $needUsia = $session->getData('usia_anak') === null;
            $needBB   = $session->getData('berat_badan_anak') === null;
            if ($needUsia || $needBB) {
                $usiaDesc = 'usia anak beserta satuannya apa adanya, mis. "7 bulan" atau "5 tahun" (bayi boleh dalam bulan). string kosong kalau tidak disebut.';
                $bbDesc   = 'berat badan anak dalam kg, hanya angka (boleh desimal). string kosong kalau tidak disebut.';
                $extracted = $this->classifier->extractFields([
                    'usia_anak'        => $usiaDesc,
                    'berat_badan_anak' => $bbDesc,
                ], $triggerMessage);
                $this->trySetUsiaBB(
                    $session,
                    $extracted['usia_anak'] ?? '',
                    $extracted['berat_badan_anak'] ?? '',
                    $triggerMessage
                );
            }
        }

        $next = $this->nextMissingHargaField($session);
        if ($next === null) {
            return array_merge($leadInReplies, $this->emitHargaClosing($session));
        }
        $session->expecting_field = $next;
        $session->save();
        return array_merge($leadInReplies, $this->renderIntent(self::HARGA_FLOW[$next], $session));
    }

    /**
     * Process an incoming WA message.
     * Returns ['handled' => bool, 'replies' => array<['text'=>string,'media'=>?string]>]
     */
    public function handle(string $noTelp, string $message): array
    {
        // Atribusi audit OpenAI ke percakapan: setiap classify /
        // extractFields / extractField yang dipanggil dalam turn ini
        // akan ter-log dengan no_telp ini di tabel open_ai_logs.
        $this->classifier->setContext($noTelp);

        $msg        = trim($message);
        $msgLower   = mb_strtolower($msg);
        $hasTrigger = $this->hasTrigger($msgLower);
        $session    = BotSession::where('no_telp', $noTelp)->first();

        // Admin reset: dari nomor operator, ketik "ulang sunat" → hapus
        // session + clear awaiting_human + re-trigger dari awal seakan
        // ketik "sunat" untuk pertama kali.
        $cleanPhone   = preg_replace('/\D+/', '', $noTelp);
        $operatorRaw  = (string) config('sunatbot.nomor_operator', '');
        $cleanOper    = preg_replace('/\D+/', '', $operatorRaw);
        $isOperator   = $cleanOper !== '' && $cleanPhone === $cleanOper;

        if ($isOperator && $msgLower === 'ulang sunat') {
            if ($session) {
                $session->delete();
            }
            \DB::table('sunat_chat_sessions')
                ->where('phone', $noTelp)
                ->update(['awaiting_human' => 0, 'updated_at' => now()]);
            Log::info('SUNAT_BOT_ADMIN_RESET', ['phone' => $noTelp]);
            // Re-handle dgn "sunat" supaya trigger_sunat fire fresh.
            return $this->handle($noTelp, 'sunat');
        }

        // Customer ketik "akhiri" untuk keluar dari handoff manusia.
        // Reset: hapus BotSession (kalau ada) + clear awaiting_human di
        // sunat_chat_sessions. Customer balik ke mode "bot otomatis".
        if ($msgLower === 'akhiri') {
            $hadHandoff = false;
            if ($session) {
                $hadHandoff = true;
                $session->delete();
            }
            $fuAffected = \DB::table('sunat_chat_sessions')
                ->where('phone', $noTelp)
                ->where('awaiting_human', 1)
                ->update(['awaiting_human' => 0, 'updated_at' => now()]);
            if ($fuAffected > 0) {
                $hadHandoff = true;
            }
            if ($hadHandoff) {
                Log::info('SUNAT_BOT_HANDOFF_ENDED_BY_USER', [
                    'phone'              => $noTelp,
                    'followup_cleared'   => $fuAffected,
                ]);
                return ['handled' => true, 'replies' => [[
                    'text'  => 'Sesi handoff diakhiri. Sekarang bot otomatis aktif kembali kak. Kirim *sunat* untuk konsultasi sunat, atau *daftar* untuk pendaftaran.',
                    'media' => null,
                ]]];
            }
            // tidak sedang handoff → lanjut ke logic biasa
        }

        if ($session === null && !$hasTrigger) {
            // Agent path (PR3 default ON): biarkan agent yang putuskan
            // mau jawab apa (greeting question, redirect, lookup, dst).
            // Kalau agent disabled, fallback ke perilaku lama (bail).
            if (!$this->shouldUseAgent($noTelp)) {
                return ['handled' => false, 'replies' => []];
            }
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

        // Booking / scheduling intent — masuk booking flow internal
        // (state machine 4 langkah: tanggal → jam → nama anak →
        // konfirmasi → INSERT jadwal_sunats). Looser phrasing via AI
        // ditangani classifyAndRespond.
        if ((!$session || !$session->is_complete) && $this->isBookingKeyword($msgLower)) {
            // Customer baru bisa pakai deep-link kalender — buat session dulu.
            if ($session === null) {
                $session = BotSession::create([
                    'no_telp'          => $noTelp,
                    'collected_data'   => [],
                    'last_activity_at' => Carbon::now(),
                ]);
            }
            $session->last_activity_at = Carbon::now();

            // Deep-link kalender: pesan mengandung tanggal+jam → skip step
            // tanggal & jam, langsung ke nama_anak. Format dari kalender:
            //   "Booking sunat tanggal 2026-06-19 jam 09:00"
            $deepLink = $this->tryDeepLinkBooking($session, $msg);
            if ($deepLink !== null) {
                return ['handled' => true, 'replies' => $deepLink];
            }

            $session->save();
            return [
                'handled' => true,
                'replies' => $this->enterBookingFlow($session, [], $msg),
            ];
        }

        if ($session && $session->is_complete) {
            // is_complete + new sunat trigger → drop the stale session
            // so the flow restarts. Tanpa trigger:
            //   - Agent enabled: drop juga, biar agent handle fresh
            //     (jawab greeting question dst).
            //   - Agent disabled: stay out, legacy paths (daftar, libur)
            //     handle.
            if ($hasTrigger || $this->shouldUseAgent($noTelp)) {
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
            // Skip auto trigger_sunat greeting kalau agent enabled — agent
            // punya logic greeting sendiri (tanya keperluan dulu). Tanpa
            // skip, customer kena 3 bubble template + 1 bubble agent greet
            // = dobel.
            if (!$this->shouldUseAgent($noTelp)) {
                $replies = array_merge($replies, $this->renderIntent('trigger_sunat', $session));
            }
            $justCreated = true;
        }

        if ($session->expecting_field !== null) {
            // Router: booking_* → state machine booking; selain itu →
            // harga flow yang sudah ada.
            if (str_starts_with((string) $session->expecting_field, 'booking_')) {
                $replies = array_merge($replies, $this->processBookingTurn($session, $msg));
            } else {
                $replies = array_merge($replies, $this->processHargaTurn($session, $msg));
            }
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
        // Fresh trigger "sunat" tanpa konten lain: greeting (trigger_sunat)
        // sudah di-render sebelumnya oleh handle(); tidak perlu classify
        // pesan kosong. Tanpa guard ini, agent path bisa fire dgn pesan
        // "sunat" doang dan generate sapaan extra dari system prompt
        // ("Halo kak 🙏 Mau konsultasi sunat?"), dobel dgn greeting.
        if ($skipFallback) {
            $stripped = trim(preg_replace('/\s+/u', ' ',
                preg_replace('/\b(sunat|khitan|sirkumsis|circumcis)\w*/iu', '', $message) ?? ''
            ));
            if (mb_strlen($stripped) < 4) {
                return [];
            }
        }

        // ---- AGENT PATH (PR2 allowlist) -----------------------------
        // Kalau nomor ini di-allowlist agent, route ke SunatBotAgent
        // (tool-calling LLM). Booking + harga state machine TETAP via
        // engine — agent cuma signal entry; engine yang execute.
        if ($this->shouldUseAgent($session->no_telp)) {
            $agentResult = $this->agent->reply($session, $message);

            // Agent unavailable (no API key / http fail) → fallback
            // ke classifier path lama supaya customer tetap dapat
            // jawaban (bukan silence).
            if (!($agentResult['handled'] ?? false) && empty($agentResult['signal'])) {
                Log::warning('SUNAT_BOT_AGENT_FALLBACK_TO_CLASSIFIER', [
                    'phone' => $session->no_telp,
                ]);
                // fall through ke classifier path di bawah.
            } else {
                $signal  = $agentResult['signal'] ?? null;
                $replies = $agentResult['replies'] ?? [];
                $prefill = (array) ($agentResult['prefill'] ?? []);

                if ($signal === 'enter_booking') {
                    return $this->enterBookingFlow($session, $prefill, $message);
                }
                if ($signal === 'enter_harga') {
                    return $this->enterHargaFlow($session, $replies, $message, $prefill);
                }
                if (!empty($agentResult['escalate'])) {
                    return $this->escalate($session, $replies !== [] ? $replies : null);
                }
                // signal 'redirected' atau null → kirim replies apa adanya.
                return $replies;
            }
        }

        // ---- CLASSIFIER PATH (legacy, default) ----------------------
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
            // AI could not classify the message → treat as out-of-scope
            // and hand over to admin. Use the operator-configured
            // fallback_unknown template as the goodbye bubble (it
            // already says "akan kami teruskan ke admin"); escalate()
            // flips requires_special_handling so the job-side flip
            // marks the inbound as sudah_dibalas=0 and the dispatcher
            // skips logOutgoing for these bubbles.
            return $this->escalate($session, $this->renderIntent('fallback_unknown', $session));
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
            if ($slug === 'mau_booking_jadwal') {
                // AI mendeteksi niat booking → masuk booking flow internal
                // (bukan escalate). Tidak render template intent ini
                // (acknowledgement-nya digantikan oleh booking_tanya_tanggal).
                return $this->enterBookingFlow($session, [], $message);
            }
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
            // enterHargaFlow ikut scan $message untuk usia/BB
            // opportunistic — kalau customer sebut umur/BB di pesan
            // trigger ("anak saya 8 th 30 kg mau tanya harga"),
            // step 2.3a + 2.3b langsung skip.
            $replies = $this->enterHargaFlow($session, $replies, $message);
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

        // Back-compat: session legacy dgn expecting_field='usia_bb'
        // (sebelum split) → arahkan ke step usia_anak dulu.
        if ($field === 'usia_bb') {
            $field = $session->getData('usia_anak') === null ? 'usia_anak' : 'berat_badan_anak';
            $session->expecting_field = $field;
        }

        // ---- Capture phase ----------------------------------------
        $capturedValue = $this->captureForField($session, $field, $message);

        // Step 2.3a validation: usia_anak — kalau tidak ke-capture /
        // out of range, $session->collected_data['usia_anak'] tetap null
        // (lihat captureForField di bawah). Re-ask via validasi template.
        // EXCEPTION: customer bilang "tidak tahu" / "lupa" → accept,
        // tandai usia_anak dengan sentinel kosong "unknown" supaya
        // nextMissingHargaField anggap field sudah terisi & advance.
        if ($field === 'usia_anak' && $session->getData('usia_anak') === null) {
            if ($this->isDontKnow($message)) {
                $session->setData('usia_anak', 'unknown');
            } else {
                return $this->renderIntent('validasi_ulang_usia_bb', $session);
            }
        }

        // Step 2.3b validation: berat_badan_anak — sama pola.
        if ($field === 'berat_badan_anak' && $session->getData('berat_badan_anak') === null) {
            if ($this->isDontKnow($message)) {
                $session->setData('berat_badan_anak', 'unknown');
            } else {
                return $this->renderIntent('validasi_ulang_usia_bb', $session);
            }
        }

        // Step 2.4 escalation gate — indikasi_khitan. Per request user:
        // kalau customer sebut keluhan medis apa pun (positive answer),
        // handoff ke admin. Negative = "tidak ada" / "normal" / "sehat"
        // → lanjut flow.
        if ($field === 'indikasi_khitan' && !$this->isNoComplaint($capturedValue)) {
            return $this->escalate($session);
        }

        // Step 2.5 escalation gate — riwayat_kesehatan. Pertama cek
        // keyword spesifik (jantung, pembekuan, autisme dll). Kalau
        // tidak ada keyword, tetap escalate kalau jawaban POSITIVE
        // (bukan "tidak ada") — customer mention kondisi medis yg
        // tidak tercover keyword.
        if ($field === 'riwayat_kesehatan') {
            if ($this->detectSpecialHandling($capturedValue) || !$this->isNoComplaint($capturedValue)) {
                return $this->escalate($session);
            }
        }

        // Step 2.4.5 escalation gate — postur tubuh gemuk butuh
        // konsultasi langsung dengan dokter (faktor risiko anestesi
        // & teknik prosedur). Begitu jawaban customer mengandung
        // sinyal "ya/gemuk/obesitas", langsung handoff ke admin.
        if ($field === 'postur_tubuh' && $this->isPosturGemuk($capturedValue)) {
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
            // contoh_dokumentasi (video kesaksian) ikut di-render sekalian
            // karena tahap "minta izin dokumentasi" sudah dihapus (per
            // permintaan operator: bubble "berkenan kami buatkan
            // dokumentasi?" diasumsikan default boleh, tidak perlu tanya).
            $extra = array_merge($extra, $this->renderIntent('edukasi_kelebihan', $session));
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
        // Opportunistic usia/BB scan. Selama salah satu field belum
        // ke-set, tiap step capture juga cari usia + bb di pesan yang
        // sama. Customer kadang sebut di pesan trigger ("Anak saya 8
        // thn 30 kg ada promo?"), atau di pesan step lain.
        $needUsia = $session->getData('usia_anak') === null;
        $needBB   = $session->getData('berat_badan_anak') === null;

        $usiaDesc = 'usia anak beserta satuannya apa adanya, mis. "7 bulan" atau "5 tahun" (bayi boleh dalam bulan). string kosong kalau tidak disebut.';
        $bbDesc   = 'berat badan anak dalam kg, hanya angka (boleh desimal). string kosong kalau tidak disebut.';

        // ---- usia_anak / berat_badan_anak sebagai primary field ----
        // Step 2.3a / 2.3b: tanya 1 field, tapi tetap accept kalau customer
        // sebut keduanya sekaligus.
        if ($field === 'usia_anak' || $field === 'berat_badan_anak') {
            $extracted = $this->classifier->extractFields([
                'usia_anak'        => $usiaDesc,
                'berat_badan_anak' => $bbDesc,
            ], $message);
            $this->trySetUsiaBB(
                $session,
                $extracted['usia_anak'] ?? '',
                $extracted['berat_badan_anak'] ?? '',
                $message
            );
            return $message;
        }

        // Build the field map for the AI: the primary field for this
        // step, plus opportunistic usia/BB kalau salah satu masih kosong.
        $fields = [
            $field => self::FIELD_DESCRIPTIONS[$field] ?? $field,
        ];
        if ($needUsia || $needBB) {
            $fields['usia_anak']        = $usiaDesc;
            $fields['berat_badan_anak'] = $bbDesc;
        }
        $extracted = $this->classifier->extractFields($fields, $message);

        $stored = $extracted[$field] !== '' ? $extracted[$field] : trim($message);

        // Bersihkan prefix umum kalau field-nya nama orang —
        // AI kadang ngembaliin "Saya Yoga" karena di-mirror dari input,
        // dan fallback raw message pasti ngandung prefix.
        if ($field === 'nama_orang_tua') {
            $stored = $this->cleanCapturedName($stored);
            // Reject kalau hasil bukan nama plausible (mis. customer
            // jawab "Usia 7th" / "18 kg" karena mishears pertanyaan).
            // Biarkan field tetap kosong supaya processHargaTurn
            // re-ask di turn berikutnya.
            if (!$this->isPlausibleName($stored)) {
                $stored = '';
            }
        }

        if ($stored !== '') {
            $session->setData($field, $stored);
        }

        if ($needUsia || $needBB) {
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
        $usiaMin      = (int)   config('sunatbot.usia_min', 1);
        $usiaMax      = (int)   config('sunatbot.usia_max', 18);
        $usiaBulanMin = (int)   config('sunatbot.usia_bulan_min', 1);
        $usiaBulanMax = (int)   config('sunatbot.usia_bulan_max', 36);
        $bbMin        = (float) config('sunatbot.berat_badan_min', 5);
        $bbMinBayi    = (float) config('sunatbot.berat_badan_min_bayi', 2.5);
        $bbMax        = (float) config('sunatbot.berat_badan_max', 100);

        // Usia bisa disebut dalam tahun ("5 thn") atau bulan ("7 bln").
        // Klinik melayani sunat bayi, jadi usia dalam bulan tetap valid.
        $usiaParsed = $this->parseUsia($usiaRaw, $rawMessage);
        $bb         = $this->parseBeratBadan($bbRaw, $rawMessage);

        // ---- Save usia kalau valid (independen dari BB) -------------
        $usiaStored = false;
        if ($usiaParsed !== null && $session->getData('usia_anak') === null) {
            [$usiaValue, $usiaUnit] = $usiaParsed;
            $usiaValid = $usiaUnit === 'bulan'
                ? ($usiaValue >= $usiaBulanMin && $usiaValue <= $usiaBulanMax)
                : ($usiaValue >= $usiaMin    && $usiaValue <= $usiaMax);
            if ($usiaValid) {
                $session->setData('usia_anak', $usiaValue);
                $session->setData('usia_anak_satuan', $usiaUnit);
                $usiaStored = true;
            }
        }

        // ---- Save bb kalau valid (independen dari usia) -------------
        $bbStored = false;
        if ($bb !== null && $session->getData('berat_badan_anak') === null) {
            $existingUsiaUnit = (string) ($session->getData('usia_anak_satuan') ?? '');
            $bbMinForUsia     = $existingUsiaUnit === 'bulan' ? $bbMinBayi : $bbMin;
            if ($bb >= $bbMinForUsia && $bb <= $bbMax) {
                $session->setData('berat_badan_anak', $bb);
                $bbStored = true;
            }
        }

        // ---- Sentinel usia_bb (back-compat) -------------------------
        // Set hanya kalau kedua-duanya sudah ada di session — bukan
        // sekedar di pesan ini. Karena step capture sekarang bisa
        // datang dari 2 turn berbeda, sentinel di-derive dari state.
        $haveBoth = $session->getData('usia_anak') !== null
                 && $session->getData('berat_badan_anak') !== null;
        if ($haveBoth && $session->getData('usia_bb') === null) {
            $u = $session->getData('usia_anak');
            $unit = $session->getData('usia_anak_satuan') ?? 'tahun';
            $b = $session->getData('berat_badan_anak');
            $session->setData('usia_bb', "{$u} {$unit}, {$b} kg");
        }
    }

    /**
     * Tentukan usia + satuan ('tahun' | 'bulan') dari teks. Mencari angka
     * yang diikuti satuan (mis. "7 bln", "5 tahun") pada nilai hasil
     * ekstraksi AI lebih dulu, lalu pesan mentah. Bila tidak ada satuan,
     * angka dianggap tahun (perilaku lama). Return null kalau tak ada angka.
     */
    private function parseUsia(string $usiaRaw, string $rawMessage): ?array
    {
        foreach ([$usiaRaw, $rawMessage] as $hay) {
            $hay = mb_strtolower(trim($hay));
            if ($hay === '') {
                continue;
            }
            if (preg_match('/(\d+)\s*(?:bln|bulan|bulanan)\b/u', $hay, $m)) {
                return [(int) $m[1], 'bulan'];
            }
            if (preg_match('/(\d+)\s*(?:thn|tahun|taun|th)\b/u', $hay, $m)) {
                return [(int) $m[1], 'tahun'];
            }
        }

        // Tidak ada satuan eksplisit — anggap angka sebagai tahun.
        $usia = $this->parseInt($usiaRaw);
        if ($usia !== null) {
            return [$usia, 'tahun'];
        }
        return null;
    }

    /**
     * Ambil berat badan (kg) dari hasil ekstraksi AI; bila kosong, coba
     * cari angka berunit "kg" pada pesan mentah (mis. "... 10 kg").
     */
    private function parseBeratBadan(string $bbRaw, string $rawMessage): ?float
    {
        $bb = $this->parseFloat($bbRaw);
        if ($bb !== null) {
            return $bb;
        }
        $hay = mb_strtolower(str_replace(',', '.', $rawMessage));
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:kg|kilo|kilogram)\b/u', $hay, $m)) {
            return (float) $m[1];
        }
        // Fallback: kalau pesan customer cuma angka (mis. "18"), accept
        // sebagai kg langsung. Customer biasanya jawab BB pertanyaan
        // dgn angka saja tanpa unit. Reject kalau ada keyword usia
        // supaya tidak salah interpret jawaban usia sebagai BB.
        $trimmed = trim($rawMessage);
        if (preg_match('/^\d+(?:[.,]\d+)?$/', $trimmed)) {
            return (float) str_replace(',', '.', $trimmed);
        }
        return null;
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
            $resolved = $this->resolveHargaSlug($slug);

            // Suspense delay: sebelum quote_harga_paket (atau varian
            // promo-nya), inject marker bubble dengan field `delay_seconds`
            // = 40. Dispatcher mendeteksi marker ini → sleep tanpa kirim
            // bubble, lalu lanjut. Tujuan: kasih testimoni Google review
            // (slot sebelumnya) waktu untuk ditonton + biar harga muncul
            // sebagai jawaban, bukan info yang di-spam.
            if (in_array($resolved, ['quote_harga_paket', 'quote_harga_paket_promo'], true)) {
                $replies[] = ['text' => '', 'media' => null, 'delay_seconds' => 40];
            }

            $replies = array_merge($replies, $this->renderIntent($resolved, $session));
        }
        return $replies;
    }

    // =====================================================================
    // BOOKING FLOW
    // State machine 4 langkah: booking_tanggal → booking_jam →
    // booking_nama_anak → booking_konfirmasi. Setelah konfirmasi YA,
    // baris jadwal_sunats di-INSERT (status=BOOKED) dan session di-mark
    // is_complete. Validasi mirror JadwalSunatController di atika
    // (slot resmi, blackout, konflik booking).
    // =====================================================================

    /**
     * Mulai booking flow. Kalau prefill ada (dari agent tool args),
     * populate dulu — engine skip step yang sudah ada datanya. Kalau
     * SEMUA field complete (tanggal+jam+nama+usia+BB) → auto-finalize.
     */
    private function enterBookingFlow(BotSession $session, array $prefill = [], ?string $triggerMessage = null): array
    {
        // FALLBACK extraction: agent kadang call trigger_booking_flow tanpa
        // pass params walaupun customer udah kasih info. Engine extract
        // sendiri via classifier kalau prefill kosong + ada triggerMessage.
        if (empty($prefill) && $triggerMessage !== null && trim($triggerMessage) !== '') {
            $extracted = $this->classifier->extractFields([
                'tanggal'           => 'tanggal booking format YYYY-MM-DD, atau format mentah customer ("5 Juli 2026", "5/7/2026", "besok"). string kosong kalau tidak disebut.',
                'jam'               => 'jam booking format HH:MM (24 jam). String kosong kalau tidak disebut.',
                'nama_anak'         => 'nama anak yang akan disunat. String kosong kalau tidak disebut.',
                'nama_panggilan'    => 'nama panggilan anak. String kosong kalau tidak disebut.',
                'usia_anak'         => 'usia anak beserta satuannya, mis. "7 tahun" atau "8 bulan". String kosong kalau tidak disebut.',
                'berat_badan_anak'  => 'berat badan anak dalam kg, angka saja. String kosong kalau tidak disebut.',
            ], $triggerMessage);
            foreach (['tanggal','jam','nama_anak','nama_panggilan','usia_anak'] as $k) {
                $v = trim((string) ($extracted[$k] ?? ''));
                if ($v !== '') $prefill[$k] = $v;
            }
            $bb = trim((string) ($extracted['berat_badan_anak'] ?? ''));
            if ($bb !== '' && is_numeric($bb)) $prefill['berat_badan_anak'] = (float) $bb;
        }

        // PRE-FILL dari agent (info yg sudah disebut customer di chat).
        // Tanggal — parse via parseBookingDate (support YYYY-MM-DD, "5 Juli 2026", dst).
        if (!empty($prefill['tanggal'])) {
            $tglParsed = $this->parseBookingDate((string) $prefill['tanggal']);
            if ($tglParsed !== null) {
                $session->setData('booking_tanggal', $tglParsed->format('Y-m-d'));
            }
        }
        // Jam — terima "10", "10:00", "10.00", "jam 10", normalize ke HH:MM.
        if (!empty($prefill['jam'])) {
            $jamRaw = (string) $prefill['jam'];
            if (preg_match('/(\d{1,2})[:.]?(\d{0,2})/', $jamRaw, $m)) {
                $hh = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $mm = $m[2] !== '' ? str_pad($m[2], 2, '0', STR_PAD_LEFT) : '00';
                $session->setData('booking_jam', "$hh:$mm");
            }
        }
        if (!empty($prefill['nama_anak']))
            $session->setData('booking_nama_anak', $prefill['nama_anak']);
        if (!empty($prefill['nama_panggilan']))
            $session->setData('booking_nama_panggilan', $prefill['nama_panggilan']);
        if (!empty($prefill['usia_anak']) && is_string($prefill['usia_anak'])) {
            $parsed = $this->parseUsia('', $prefill['usia_anak']);
            if ($parsed !== null) {
                $session->setData('booking_usia_anak', (int) $parsed[0]);
                $session->setData('booking_usia_anak_satuan', (string) $parsed[1]);
            }
        }
        if (isset($prefill['berat_badan_anak']) && is_numeric($prefill['berat_badan_anak']))
            $session->setData('booking_berat_badan_anak', (float) $prefill['berat_badan_anak']);

        Log::info('SUNAT_BOOKING_PREFILL', [
            'phone'   => $session->no_telp,
            'prefill' => $prefill,
            'set'     => [
                'tanggal' => $session->getData('booking_tanggal'),
                'jam'     => $session->getData('booking_jam'),
                'nama'    => $session->getData('booking_nama_anak'),
                'usia'    => $session->getData('booking_usia_anak'),
                'bb'      => $session->getData('booking_berat_badan_anak'),
            ],
        ]);

        // Cek apakah semua field lengkap → auto-finalize (tidak nanya apa-apa).
        $hasAll = $session->getData('booking_tanggal') !== null
               && $session->getData('booking_jam')     !== null
               && trim((string) $session->getData('booking_nama_anak')) !== ''
               && $session->getData('booking_usia_anak')        !== null
               && $session->getData('booking_berat_badan_anak') !== null;
        if ($hasAll) {
            return $this->finalizeBooking($session);
        }

        // Lanjut ke step pertama yang masih missing.
        $next = $this->nextMissingBookingField($session);
        $session->expecting_field = $next;
        $session->save();
        $intentMap = [
            'booking_tanggal'          => 'booking_tanya_tanggal',
            'booking_jam'              => 'booking_tanya_jam',
            'booking_nama_anak'        => 'booking_tanya_nama_anak',
            'booking_nama_panggilan'   => 'booking_tanya_nama_panggilan',
            'booking_usia'             => 'booking_tanya_usia',
            'booking_berat_badan'      => 'booking_tanya_berat_badan',
            'booking_konfirmasi'       => 'booking_konfirmasi',
        ];
        return $this->renderIntent($intentMap[$next] ?? 'booking_tanya_tanggal', $session);
    }

    /**
     * Cari step booking_* berikutnya yang datanya belum ada. Order:
     * tanggal → jam → nama_anak → nama_panggilan → usia → BB → konfirmasi.
     */
    private function nextMissingBookingField(BotSession $session): string
    {
        if ($session->getData('booking_tanggal') === null)        return 'booking_tanggal';
        if ($session->getData('booking_jam')     === null)        return 'booking_jam';
        if (trim((string) $session->getData('booking_nama_anak')) === '') return 'booking_nama_anak';
        if (trim((string) $session->getData('booking_nama_panggilan')) === '') return 'booking_nama_panggilan';
        if ($session->getData('booking_usia_anak') === null)      return 'booking_usia';
        if ($session->getData('booking_berat_badan_anak') === null) return 'booking_berat_badan';
        return 'booking_konfirmasi';
    }

    /**
     * Coba parse tanggal + jam dari pesan awal (mis. deep-link dari
     * kalender publik). Kalau dua-duanya ketemu dan valid, lewati step
     * tanggal & jam, langsung minta nama anak. Kalau gagal, return null
     * dan biarkan flow normal jalan.
     */
    private function tryDeepLinkBooking(BotSession $session, string $msg): ?array
    {
        $tglRaw = null;
        if (preg_match('/tanggal\s+(\d{4}-\d{2}-\d{2})/iu', $msg, $m)) {
            $tglRaw = $m[1];
        }
        $jamRaw = null;
        if (preg_match('/jam\s+(\d{1,2}[:.]?\d{0,2})/iu', $msg, $m)) {
            $jamRaw = $m[1];
        }
        if ($tglRaw === null || $jamRaw === null) {
            return null;
        }

        // Validasi tanggal lewat handler asli; sets booking_tanggal +
        // expecting_field=booking_jam kalau OK.
        $this->handleBookingTanggal($session, $tglRaw);
        if ($session->expecting_field !== 'booking_jam') {
            // Tanggal invalid → balik ke null biar flow normal jalan
            // (akan kirim 'booking_tanya_tanggal').
            return null;
        }

        // Validasi jam — kalau OK akan advance ke booking_nama_anak.
        $res = $this->handleBookingJam($session, $jamRaw);
        $session->save();
        return $res;
    }

    /**
     * Router state machine booking. Dispatch ke handler per step
     * berdasarkan expecting_field.
     */
    private function processBookingTurn(BotSession $session, string $message): array
    {
        $field = (string) $session->expecting_field;
        $msg   = trim($message);
        $low   = mb_strtolower($msg);

        // Customer batal di mana saja → tutup flow.
        if (in_array($low, ['batal', 'cancel', 'batalkan'], true)) {
            $session->expecting_field = null;
            $session->save();
            return $this->renderIntent('booking_dibatalkan', $session);
        }

        // Natural-language "ganti / pindah / ubah / ulang tanggal" dari
        // step manapun → reset ke step tanggal. Tanpa ini, customer yang
        // ketik bebas "Saya mau pindah tanggal" saat di step jam akan
        // ditolak sebagai "jam invalid".
        if (str_contains($low, 'tanggal')
            && preg_match('/\b(ganti|pindah|ubah|ulang|lain|baru)\b/u', $low)) {
            $session->setData('booking_tanggal', null);
            $session->setData('booking_jam', null);
            // Nama & usia/BB sengaja DIPERTAHANKAN supaya customer tidak
            // perlu ketik ulang — hanya pilihan tanggal yang berubah.
            $session->expecting_field = 'booking_tanggal';
            return $this->renderIntent('booking_tanya_tanggal', $session);
        }

        // Natural-language "ganti / pindah jam" dari step setelah jam
        // (nama_anak / usia_bb / konfirmasi) → kembali ke step jam.
        if ($field !== 'booking_tanggal' && $field !== 'booking_jam'
            && str_contains($low, 'jam')
            && preg_match('/\b(ganti|pindah|ubah|ulang|lain|baru)\b/u', $low)) {
            $session->setData('booking_jam', null);
            $session->expecting_field = 'booking_jam';
            return $this->renderIntent('booking_tanya_jam', $session);
        }

        switch ($field) {
            case 'booking_tanggal':    return $this->handleBookingTanggal($session, $msg);
            case 'booking_jam':        return $this->handleBookingJam($session, $msg);
            case 'booking_nama_anak':       return $this->handleBookingNamaAnak($session, $msg);
            case 'booking_nama_panggilan':  return $this->handleBookingNamaPanggilan($session, $msg);
            case 'booking_usia':           return $this->handleBookingUsia($session, $msg);
            case 'booking_berat_badan':    return $this->handleBookingBeratBadan($session, $msg);
            case 'booking_usia_bb':        return $this->handleBookingUsiaBb($session, $msg);
            case 'booking_konfirmasi': return $this->handleBookingKonfirmasi($session, $msg);
        }

        // Field tidak dikenali — fallback aman, escalate.
        return $this->escalate($session);
    }

    /**
     * Bubble fallback: pilihan WA admin sunat + Rona kalau customer
     * stuck di tengah booking flow (jawaban tidak ke-parse, jam tidak
     * tersedia, atau konfirmasi tidak dimengerti). Append ke tiap
     * re-ask supaya customer punya jalan keluar tanpa harus terus
     * menerus diuji oleh validator.
     */
    private function bookingFallbackBubble(): array
    {
        $sunatJid = '62882015192532';
        $autoText = rawurlencode('saya butuh bantuan booking sunat');

        $rona       = (string) config('sunatbot.nomor_rona', '0895-3692-69190');
        $ronaDigits = preg_replace('/\D+/', '', $rona) ?: $rona;
        if (str_starts_with($ronaDigits, '0'))       $ronaE164 = '62' . substr($ronaDigits, 1);
        elseif (str_starts_with($ronaDigits, '62'))  $ronaE164 = $ronaDigits;
        elseif (str_starts_with($ronaDigits, '8'))   $ronaE164 = '62' . $ronaDigits;
        else                                          $ronaE164 = $ronaDigits;

        return [
            'text'  => "Kalau ada kendala, kakak bisa langsung WA admin kami:\n\n"
                     . "• Admin Sunat: https://wa.me/{$sunatJid}?text={$autoText}\n"
                     . "• Rona: https://wa.me/{$ronaE164}",
            'media' => null,
        ];
    }

    /**
     * Wrapper renderIntent + append bubble fallback. Dipakai di setiap
     * re-ask path booking flow.
     */
    private function reAskWithFallback(string $slug, BotSession $session): array
    {
        return array_merge($this->renderIntent($slug, $session), [$this->bookingFallbackBubble()]);
    }

    private function handleBookingTanggal(BotSession $session, string $msg): array
    {
        $tanggal = $this->parseBookingDate($msg);
        if ($tanggal === null) {
            return $this->reAskWithFallback('booking_tanggal_invalid', $session);
        }

        // Tanggal harus hari ini atau di masa depan.
        if ($tanggal->lt(Carbon::today())) {
            return $this->reAskWithFallback('booking_tanggal_invalid', $session);
        }

        // Cek blackout full-day. Blackout partial (blocked_slots) dicek
        // saat pilih jam.
        $blackout = $this->blackoutForDate($tanggal->format('Y-m-d'));
        if ($blackout !== null && empty($blackout->blocked_slots)) {
            return $this->reAskWithFallback('booking_tanggal_invalid', $session);
        }

        $session->setData('booking_tanggal', $tanggal->format('Y-m-d'));
        $session->expecting_field = 'booking_jam';
        return $this->renderIntent('booking_tanya_jam', $session);
    }

    private function handleBookingJam(BotSession $session, string $msg): array
    {
        $jam = $this->parseBookingJam($msg);
        $tanggalStr = (string) $session->getData('booking_tanggal');

        if ($jam === null || $tanggalStr === '') {
            return $this->reAskWithFallback('booking_jam_tidak_tersedia', $session);
        }

        // Cek blackout partial untuk jam ini.
        $blackout = $this->blackoutForDate($tanggalStr);
        if ($blackout !== null && is_array($blackout->blocked_slots)
            && in_array($jam, $blackout->blocked_slots, true)) {
            return $this->reAskWithFallback('booking_jam_tidak_tersedia', $session);
        }

        // Konflik: sunat blok 2 jam FORWARD. Booking di jam X menabrak
        // existing di X (slot sama) atau X-1 (spillover masuk ke X).
        // TIDAK menabrak existing di X+1 — itu artinya X cuma blok 1
        // jam (X sendiri); slot X+1 sudah punya pasien lain. Contoh:
        // 08:00 booked, booking 07:00 di-allowed dgn blok 1 jam saja.
        $jamHour = (int) substr($jam, 0, 2);
        $taken   = JadwalSunat::where('tanggal', $tanggalStr)
            ->where('status', 'BOOKED')
            ->pluck('jam')
            ->first(function ($j) use ($jamHour) {
                $diff = $jamHour - (int) substr((string) $j, 0, 2);
                return $diff === 0 || $diff === 1;
            });
        if ($taken) {
            return $this->reAskWithFallback('booking_jam_tidak_tersedia', $session);
        }

        $session->setData('booking_jam', $jam);
        $session->expecting_field = 'booking_nama_anak';
        return $this->renderIntent('booking_tanya_nama_anak', $session);
    }

    private function handleBookingNamaAnak(BotSession $session, string $msg): array
    {
        $nama = $this->cleanCapturedName(trim($msg));
        if ($nama === '') {
            return $this->reAskWithFallback('booking_tanya_nama_anak', $session);
        }
        // Batasi panjang biar tidak masuk paragraf panjang.
        if (mb_strlen($nama) > 100) {
            $nama = mb_substr($nama, 0, 100);
        }

        $session->setData('booking_nama_anak', $nama);

        // Lanjut tanya nama panggilan (dipakai semua WA reminder/intro/followup).
        $session->expecting_field = 'booking_nama_panggilan';
        return $this->renderIntent('booking_tanya_nama_panggilan', $session);
    }

    private function handleBookingNamaPanggilan(BotSession $session, string $msg): array
    {
        $panggilan = trim($msg);
        // Customer boleh skip dgn "-" atau "sama" dst → pakai nama lengkap.
        if ($panggilan === '' || in_array(mb_strtolower($panggilan), ['-', 'tidak ada', 'sama', 'skip'], true)) {
            $panggilan = (string) $session->getData('booking_nama_anak');
        }
        $panggilan = $this->cleanCapturedName($panggilan);
        if (mb_strlen($panggilan) > 60) {
            $panggilan = mb_substr($panggilan, 0, 60);
        }
        $session->setData('booking_nama_panggilan', $panggilan);

        // Reuse usia & BB dari HARGA_FLOW kalau sudah terkumpul.
        $usia = $session->getData('usia_anak');
        $bb   = $session->getData('berat_badan_anak');
        if ($usia !== null && $bb !== null) {
            $session->setData('booking_usia_anak', $usia);
            $session->setData('booking_usia_anak_satuan', $session->getData('usia_anak_satuan') ?? 'tahun');
            $session->setData('booking_berat_badan_anak', $bb);
            $session->expecting_field = 'booking_konfirmasi';
            return $this->renderIntent('booking_konfirmasi', $session);
        }

        $session->expecting_field = 'booking_usia';
        return $this->renderIntent('booking_tanya_usia', $session);
    }

    private function handleBookingUsia(BotSession $session, string $msg): array
    {
        $usiaParsed = $this->parseUsia('', $msg);
        if ($usiaParsed === null) {
            // Customer bilang "tidak tahu" / "lupa" → skip step, advance
            // ke BB tanpa simpan nilai (catatan booking akan kosong di
            // bagian usia, operator bisa konfirmasi later).
            if ($this->isDontKnow($msg)) {
                $session->expecting_field = 'booking_berat_badan';
                $session->save();
                return $this->renderIntent('booking_tanya_berat_badan', $session);
            }
            return $this->reAskWithFallback('booking_tanya_usia', $session);
        }
        [$usiaValue, $usiaUnit] = $usiaParsed;
        $session->setData('booking_usia_anak', $usiaValue);
        $session->setData('booking_usia_anak_satuan', $usiaUnit);
        $session->setData('usia_anak', $usiaValue);
        $session->setData('usia_anak_satuan', $usiaUnit);

        $session->expecting_field = 'booking_berat_badan';
        return $this->renderIntent('booking_tanya_berat_badan', $session);
    }

    private function handleBookingBeratBadan(BotSession $session, string $msg): array
    {
        $bb = $this->parseBeratBadan('', $msg);
        if ($bb === null) {
            // Customer bilang "tidak tahu" / "belum ditimbang" → skip
            // step, advance ke konfirmasi tanpa simpan BB.
            if ($this->isDontKnow($msg)) {
                $session->expecting_field = 'booking_konfirmasi';
                $session->save();
                return $this->renderIntent('booking_konfirmasi', $session);
            }
            return $this->reAskWithFallback('booking_tanya_berat_badan', $session);
        }
        $session->setData('booking_berat_badan_anak', $bb);
        $session->setData('berat_badan_anak', $bb);

        $session->expecting_field = 'booking_konfirmasi';
        return $this->renderIntent('booking_konfirmasi', $session);
    }

    /**
     * Detect kalau customer bilang "tidak tahu" / "lupa" / sejenisnya
     * untuk pertanyaan usia atau BB. Fast-path local pattern match;
     * kalau pesannya pendek dan tidak match, fallback ke classifier
     * AI. Pakai di booking + harga flow capture supaya bot tidak
     * loop nanya yang sama.
     */
    private function isDontKnow(string $message): bool
    {
        $low = mb_strtolower(trim($message));
        if ($low === '') return false;

        $patterns = [
            'tidak tahu', 'tdk tahu', 'gak tahu', 'ga tahu', 'nggak tahu', 'ngga tahu',
            'tidak tau',  'tdk tau',  'gak tau',  'ga tau',  'nggak tau',  'ngga tau',
            'gatau', 'gatahu',
            'belum tahu', 'belum tau', 'blm tau', 'blm tahu',
            'lupa', 'kelupaan',
            'tidak ingat', 'gak ingat', 'ga ingat', 'nggak ingat',
            'belum ditimbang', 'belum diukur', 'belum ditimbang kak',
            'belum tau kak', 'belum tahu kak',
        ];
        foreach ($patterns as $p) {
            if (str_contains($low, $p)) return true;
        }

        // AI fallback hanya untuk pesan pendek (di bawah 60 char) supaya
        // tidak mahal — pesan panjang biasanya jelas-jelas isi nilai.
        if (mb_strlen($low) > 60) return false;

        $res = $this->classifier->extractFields([
            'is_dont_know' => "balas 'ya' kalau pesan menunjukkan customer TIDAK TAHU / LUPA / BELUM TAHU / BELUM DITIMBANG. Selain itu balas 'tidak'.",
        ], $message);
        return mb_strtolower(trim($res['is_dont_know'] ?? '')) === 'ya';
    }

    /**
     * Legacy combo handler — masih dipakai kalau session lama
     * masih punya expecting_field='booking_usia_bb'. Tetap accept
     * format "5 thn 18 kg" lalu pindah ke konfirmasi.
     */
    private function handleBookingUsiaBb(BotSession $session, string $msg): array
    {
        // Reuse parser HARGA_FLOW — terima "7 bulan 10 kg", "5 thn 18 kg", dll.
        $usiaParsed = $this->parseUsia('', $msg);
        $bb         = $this->parseBeratBadan('', $msg);

        if ($usiaParsed === null || $bb === null) {
            return $this->reAskWithFallback('booking_tanya_usia_bb', $session);
        }

        [$usiaValue, $usiaUnit] = $usiaParsed;
        $session->setData('booking_usia_anak', $usiaValue);
        $session->setData('booking_usia_anak_satuan', $usiaUnit);
        $session->setData('booking_berat_badan_anak', $bb);
        // Simpan juga ke field HARGA_FLOW supaya konsisten dengan
        // percakapan utama (nilai juga jadi tersedia untuk template lain).
        $session->setData('usia_anak', $usiaValue);
        $session->setData('usia_anak_satuan', $usiaUnit);
        $session->setData('berat_badan_anak', $bb);

        $session->expecting_field = 'booking_konfirmasi';
        return $this->renderIntent('booking_konfirmasi', $session);
    }

    private function handleBookingKonfirmasi(BotSession $session, string $msg): array
    {
        $lower = mb_strtolower(trim($msg));

        if (in_array($lower, ['ya', 'iya', 'iy', 'y', 'oke', 'ok', 'konfirmasi', 'lanjutkan', 'lanjut'], true)) {
            return $this->finalizeBooking($session);
        }
        if (in_array($lower, ['tidak', 'tdk', 'no', 'gak', 'gakjadi', 'gak jadi', 'batal'], true)) {
            $session->expecting_field = null;
            $session->save();
            return $this->renderIntent('booking_dibatalkan', $session);
        }
        // Tidak dimengerti — ulang konfirmasi + tawarkan WA admin.
        return $this->reAskWithFallback('booking_konfirmasi', $session);
    }

    /**
     * INSERT jadwal_sunats + kirim bubble sukses. Validasi konflik
     * terakhir (race-condition safety) sebelum benar-benar insert.
     */
    private function finalizeBooking(BotSession $session): array
    {
        $tanggalStr    = (string) $session->getData('booking_tanggal');
        $jam           = (string) $session->getData('booking_jam');
        $namaAnak      = (string) $session->getData('booking_nama_anak');
        $namaPanggilan = trim((string) $session->getData('booking_nama_panggilan'));

        if ($tanggalStr === '' || $jam === '' || $namaAnak === '') {
            // Data tidak lengkap — escalate biar admin handle.
            $session->expecting_field = null;
            return $this->escalate($session);
        }

        // Catatan = sumber + usia/BB supaya operator + UI staf langsung lihat.
        $catatan = 'Booking via SunatBot';
        $usia    = $session->getData('booking_usia_anak');
        $unit    = $session->getData('booking_usia_anak_satuan') ?? 'tahun';
        $bbVal   = $session->getData('booking_berat_badan_anak');
        if ($usia !== null && $bbVal !== null) {
            $bbDisplay = (fmod((float) $bbVal, 1.0) == 0) ? (int) $bbVal : $bbVal;
            $catatan  .= ' | Usia: ' . $usia . ' ' . $unit . ', BB: ' . $bbDisplay . ' kg';
        }

        // Race-safety + handle slot bekas CANCELLED. Unique constraint
        // `(tenant_id, tanggal, jam)` tidak melihat status, jadi row
        // CANCELLED lama bisa block insert baru. Pola: kalau ada row di
        // slot sama → status BOOKED berarti slot sudah dipakai (suruh
        // ganti jam); status lain (CANCELLED/EXPIRED) reactivate jadi
        // BOOKED dengan data baru.
        $existing = JadwalSunat::where('tenant_id', 1)
            ->where('tanggal', $tanggalStr)
            ->where('jam', $jam . ':00')
            ->first();

        if ($existing && $existing->status === 'BOOKED') {
            $session->setData('booking_jam', null);
            $session->expecting_field = 'booking_jam';
            $session->save();
            return $this->reAskWithFallback('booking_jam_tidak_tersedia', $session);
        }

        try {
            if ($existing) {
                $existing->update([
                    'pasien_id'      => null,
                    'status'         => 'BOOKED',
                    'nama_pasien'    => $namaAnak,
                    'nama_panggilan' => $namaPanggilan !== '' ? $namaPanggilan : null,
                    'no_telp'        => (string) $session->no_telp,
                    'catatan'        => $catatan,
                    'created_by'     => null,
                ]);
                $row = $existing->fresh();
            } else {
                $row = JadwalSunat::create([
                    'tenant_id'      => 1,
                    'pasien_id'      => null,
                    'tanggal'        => $tanggalStr,
                    'jam'            => $jam . ':00',
                    'status'         => 'BOOKED',
                    'nama_pasien'    => $namaAnak,
                    'nama_panggilan' => $namaPanggilan !== '' ? $namaPanggilan : null,
                    'no_telp'        => (string) $session->no_telp,
                    'catatan'        => $catatan,
                    'created_by'     => null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('SUNAT_BOOKING_INSERT_FAILED', [
                'no_telp'  => $session->no_telp,
                'tanggal'  => $tanggalStr,
                'jam'      => $jam,
                'error'    => $e->getMessage(),
            ]);
            $session->expecting_field = null;
            return $this->escalate($session);
        }

        // Sukses — tutup booking flow & session.
        $session->expecting_field  = null;
        $session->is_complete      = true;
        $session->last_activity_at = Carbon::now();
        $session->save();

        Log::info('SUNAT_BOOKING_CREATED', [
            'no_telp' => $session->no_telp,
            'tanggal' => $tanggalStr,
            'jam'     => $jam,
            'nama'    => $namaAnak,
        ]);

        // Notif WA ke operator/admin (nomor terpisah dari pasien). Pesan
        // ke pasien sendiri di-handle oleh bubble booking_sukses yang
        // di-return ke caller.
        $this->notifyOperatorBooking($row);

        return $this->renderIntent('booking_sukses', $session);
    }

    /**
     * Kirim notifikasi ke admin/operator + Rona saat booking baru dibuat
     * lewat sunat bot — mirror perilaku JadwalSunatController::store di
     * atika. Gagalnya kirim DIAM (log warning only) supaya tidak
     * rollback booking yang sudah berhasil.
     */
    private function notifyOperatorBooking(JadwalSunat $row): void
    {
        try {
            $idMonths = [
                1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember',
            ];
            $tgl       = Carbon::parse($row->tanggal);
            $tglFormat = $tgl->day . ' ' . $idMonths[(int) $tgl->month] . ' ' . $tgl->year;

            $msg = implode("\n", [
                '📌 *BOOKING SUNAT BARU (SunatBot)*',
                '',
                'Nama Pasien : ' . $row->nama_pasien,
                'Tanggal     : ' . $tglFormat,
                'Jam         : ' . substr((string) $row->jam, 0, 5),
                'No. HP      : ' . $row->no_telp,
                '',
                'Catatan:',
                $row->catatan ?: '-',
                '',
                '🧑‍⚕️ Mohon dipersiapkan tindakan.',
            ]);

            $recipients = [
                'operator' => (string) config('sunatbot.nomor_operator', '6281381912803'),
                'rona'     => (string) config('sunatbot.nomor_rona', ''),
            ];

            foreach ($recipients as $label => $phone) {
                $phone = preg_replace('/\D+/', '', $phone);
                if (str_starts_with($phone, '0')) {
                    $phone = '62' . substr($phone, 1);
                } elseif (str_starts_with($phone, '8')) {
                    $phone = '62' . $phone;
                }
                if ($phone === '') continue;

                \App\Services\GowaSunatNotifier::notifyStaff($phone, $msg, 'booking_notif_' . $label);
            }
        } catch (\Throwable $e) {
            Log::warning('SUNAT_BOOKING_OPERATOR_NOTIFY_FAIL', [
                'jadwal_id' => $row->id ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parse berbagai format tanggal yang biasa diketik customer:
     *   - "15 Juni 2026" (Indonesian)
     *   - "15/06/2026" atau "15-06-2026"
     *   - "2026-06-15"
     *   - "besok", "lusa" (kata waktu sederhana)
     * Return Carbon (start of day) atau null bila tidak dikenali.
     */
    private function parseBookingDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        $lower = mb_strtolower($raw);
        if ($lower === 'hari ini' || $lower === 'today') return Carbon::today();
        if ($lower === 'besok'    || $lower === 'tomorrow') return Carbon::tomorrow();
        if ($lower === 'lusa')                              return Carbon::today()->addDays(2);

        // Replace nama bulan Indonesia (full word, case-insensitive) → English.
        // Pakai word-boundary regex supaya abbreviation tidak korup hasil
        // (mis. "Juni" jadi "June" lalu "Jun" → "Junee"). Carbon::parse
        // sudah handle abbreviation English ("Jun", "Jul", dst) natif.
        $idMonths = [
            'januari' => 'January',  'februari' => 'February', 'maret'    => 'March',
            'april'   => 'April',    'mei'      => 'May',      'juni'     => 'June',
            'juli'    => 'July',     'agustus'  => 'August',   'september'=> 'September',
            'oktober' => 'October',  'november' => 'November', 'desember' => 'December',
        ];
        $pattern    = '/\b(' . implode('|', array_keys($idMonths)) . ')\b/iu';
        $normalized = preg_replace_callback($pattern, function ($m) use ($idMonths) {
            return $idMonths[mb_strtolower($m[1])] ?? $m[0];
        }, $raw);

        try {
            return Carbon::parse((string) $normalized)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Parse pilihan jam: terima "07:00"–"17:00", "1"–"10" (urutan
     * dari BOOKING_JAM_SLOTS), atau "07"/"7" (single hour).
     * Return string "HH:MM" yang ada di slot resmi, atau null.
     */
    private function parseBookingJam(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        // Angka 1-10 → index.
        if (preg_match('/^([1-9]|10)$/', $raw, $m)) {
            $idx = (int) $m[1] - 1;
            return self::BOOKING_JAM_SLOTS[$idx] ?? null;
        }
        // "07:00" atau "07.00" / "07" / "7".
        if (preg_match('/^(\d{1,2})[:.]?(\d{0,2})$/', $raw, $m)) {
            $h = str_pad((string) ((int) $m[1]), 2, '0', STR_PAD_LEFT);
            $candidate = $h . ':00';
            if (in_array($candidate, self::BOOKING_JAM_SLOTS, true)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Build daftar jam dengan marker visual untuk slot yang tidak
     * tersedia (sudah BOOKED atau blackout). WhatsApp markdown
     * "~text~" = strikethrough. Format:
     *   1. 07:00
     *   2. ~08:00~ (terisi)
     *   ...
     * Bila $tanggalStr kosong (placeholder dipakai di luar konteks
     * booking) — return list polos tanpa marker.
     */
    private function buildJamList(string $tanggalStr): string
    {
        $taken         = [];
        $blackoutSlots = [];
        $fullBlackout  = false;

        if ($tanggalStr !== '') {
            // Sunat blok 2 jam: tiap booking jam X menandai X dan X+1
            // sebagai 'terisi' di slot grid.
            $bookedJam = JadwalSunat::where('tanggal', $tanggalStr)
                ->where('status', 'BOOKED')
                ->pluck('jam')
                ->map(fn ($j) => substr((string) $j, 0, 5))
                ->all();
            $takenMap = [];
            foreach ($bookedJam as $j) {
                $takenMap[$j] = true;
                $next = sprintf('%02d:00', ((int) substr($j, 0, 2)) + 1);
                if (in_array($next, self::BOOKING_JAM_SLOTS, true)) {
                    $takenMap[$next] = true;
                }
            }
            $taken = array_keys($takenMap);

            $blackout = $this->blackoutForDate($tanggalStr);
            if ($blackout !== null) {
                if (is_array($blackout->blocked_slots) && !empty($blackout->blocked_slots)) {
                    $blackoutSlots = $blackout->blocked_slots;
                } else {
                    $fullBlackout = true;
                }
            }
        }

        $lines = [];
        foreach (self::BOOKING_JAM_SLOTS as $i => $jam) {
            $no    = $i + 1;
            $label = null;
            // Prioritas label: blackout > taken (kalau seandainya sama).
            if ($fullBlackout || in_array($jam, $blackoutSlots, true)) {
                $label = 'libur';
            } elseif (in_array($jam, $taken, true)) {
                $label = 'terisi';
            }
            if ($label !== null) {
                $lines[] = $no . '. ~' . $jam . '~ (' . $label . ')';
            } else {
                $lines[] = $no . '. ' . $jam;
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Ambil blackout aktif untuk tanggal tertentu (atika
     * JadwalSunatController::blackoutForDate equivalent).
     */
    private function blackoutForDate(string $tanggal): ?JadwalSunatBlackout
    {
        return JadwalSunatBlackout::where('tenant_id', 1)
            ->whereDate('start_date', '<=', $tanggal)
            ->whereDate('end_date',   '>=', $tanggal)
            ->latest('id')
            ->first();
    }

    /**
     * Swap quote_harga_paket → quote_harga_paket_promo bila intent
     * promo aktif (active=1) di tabel bot_intents. Toggle promo via
     * `php artisan sunatbot:promo on|off` di sisi monitor.
     */
    private function resolveHargaSlug(string $slug): string
    {
        if ($slug !== 'quote_harga_paket') {
            return $slug;
        }
        $promoActive = \App\Models\BotIntent::where('intent', 'quote_harga_paket_promo')
            ->where('active', 1)
            ->exists();
        return $promoActive ? 'quote_harga_paket_promo' : $slug;
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
     * Mark the session for human handover, close it, and emit a
     * goodbye bubble. The actual sudah_dibalas=0 flip on the inbound
     * messages that triggered escalation lives in
     * ProcessPendingSunatBotMessages so it can scope to the current
     * buffer flush precisely (id-based cutoffs in the engine miss the
     * trigger message when the dispatcher is mid-stream).
     *
     * Callers can override the bubble payload (e.g. fallback_unknown
     * rendered template) by passing $replies; otherwise the
     * sunatbot.handover_message config string is used.
     */
    private function escalate(BotSession $session, ?array $replies = null): array
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

        if ($replies !== null) {
            return $replies;
        }
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
        // Hard guard: kalau pesan ada kata kunci non-sunat (usg, lab,
        // dokter umum, dll), JANGAN match sebagai booking sunat. Engine
        // shortcut ini designed utk "saya mau daftar" → booking flow,
        // tapi customer kadang bilang "saya mau daftar USG" — bukan
        // sunat. Biarkan agent yang handle (route ke redirect).
        $nonSunatKw = ['usg', 'kandungan', 'kehamilan', 'hamil', 'lab',
            'cek darah', 'dokter umum', 'gigi', 'kulit', 'vaksin',
            'imunisasi', 'mobile jkn', 'mobile-jkn', 'obat', 'resep'];
        foreach ($nonSunatKw as $kw) {
            if (str_contains($msgLower, $kw)) {
                return false;
            }
        }

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

    /**
     * Deteksi jawaban "tidak ada keluhan / kondisi medis". Return true
     * kalau customer effectively bilang TIDAK ADA — bot bisa lanjut
     * flow. Return false kalau ada hint positif → caller escalate.
     *
     * Negatif markers: "tidak ada", "ga ada", "ngga", "engga", "belum",
     * "normal", "sehat", "biasa", "tidak", "-", "none", string kosong.
     */
    private function isNoComplaint(string $value): bool
    {
        $v = mb_strtolower(trim($value));
        if ($v === '' || $v === '-') return true;

        // Single-word "no" markers (case sensitive after lower).
        $singletons = [
            'tidak', 'gak', 'nggak', 'engga', 'enggak', 'ngga',
            'belum', 'bukan', 'sehat', 'normal', 'biasa', 'fine',
            'oke', 'baik', 'none', 'no', 'nope', 'aman',
        ];
        if (in_array($v, $singletons, true)) return true;

        // Phrase patterns yg jelas berarti "tidak ada keluhan".
        $negPatterns = [
            '/\b(tidak|ga|gak|nggak|engga|enggak|ngga|belum)\s+ada\b/u',
            '/\btidak\s+(ada|punya)\b/u',
            '/\bbiasa\s+saja\b/u',
            '/\bnggak\s+ada\b/u',
            '/\bbelum\s+ada\b/u',
            '/\btidak\s+ada\s+keluhan\b/u',
            '/\bsehat[\s-]*sehat\b/u',
        ];
        foreach ($negPatterns as $p) {
            if (preg_match($p, $v)) return true;
        }

        return false;
    }

    /**
     * Deteksi jawaban postur_tubuh yang berarti "anak gemuk/obesitas".
     * Sinyal positif (ya/gemuk/obesitas/...) → escalate ke admin karena
     * butuh konsultasi langsung dokter (faktor risiko anestesi).
     * Negatif (tidak gemuk/proporsional/kurus/normal) → lanjut flow.
     */
    private function isPosturGemuk(string $value): bool
    {
        $v = mb_strtolower(trim($value));
        if ($v === '') return false;
        // Negation prefix → bukan gemuk. "tidak gemuk", "ga gemuk" dst.
        if (preg_match('/\b(tidak|gak|nggak|engga|enggak|belum|bukan|kurang)\b/u', $v)) {
            return false;
        }
        $positive = ['gemuk', 'obesitas', 'obese', 'bongsor', 'overweight', 'ya', 'iya', 'iyaa', 'iyaaa', 'iyah', 'iyaa', 'betul', 'benar', 'memang gemuk'];
        foreach ($positive as $kw) {
            if ($v === $kw) return true;
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/u', $v)) return true;
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
                // Kata pendek yang lebih sering = "boleh dijelaskan" /
                // "mau dijelaskan" daripada "boleh saya udah tahu".
                // Pertanyaan punya 2 opsi (sudah tahu vs perlu jelaskan)
                // dan opsi kedua lebih dekat ke kata kerja, jadi default
                // ke arah penjelasan. Lebih aman: ngasih info > skip info.
                'boleh', 'mau', 'silakan', 'silahkan', 'monggo', 'gas',
            ];
            foreach ($explainPatterns as $p) {
                if (str_contains($v, $p)) return false;
            }
        }

        // Negation prefix anywhere → not yes.
        if (preg_match('/\b(tidak|gak|nggak|belum|bukan)\b/u', $v)) {
            return false;
        }

        // Untuk sudah_tahu_metode, batasi YES ke kata yang eksplisit
        // berarti "saya sudah tahu" — "ya"/"iya"/"ok" generik tidak
        // cukup informatif untuk pertanyaan dua-opsi ini.
        if ($field === 'sudah_tahu_metode') {
            $yesStrict = ['sudah', 'sudah tahu', 'sudah paham', 'paham', 'tahu', 'tau', 'ngerti', 'mengerti'];
            foreach ($yesStrict as $kw) {
                if ($v === $kw) return true;
                if (preg_match('/\b' . preg_quote($kw, '/') . '\b/u', $v)) return true;
            }
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
        $sentences = $this->splitText($text, $slug);
        $media     = $intent->mediaList();

        // Ordering: GAMBAR di atas teks (untuk header / context image
        // seperti promo banner, klinik photo). VIDEO setelah teks
        // (penjelasan dulu, baru bukti video). Klasifikasi via ekstensi
        // file — sama dengan SunatBotReplyDispatcher::dispatch.
        $imagesFirst = [];
        $videosLast  = [];
        foreach ($media as $file) {
            $ext = strtolower(pathinfo((string) $file, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $imagesFirst[] = ['text' => '', 'media' => $file];
            } else {
                $videosLast[] = ['text' => '', 'media' => $file];
            }
        }

        $textBubbles = array_map(
            fn ($s) => ['text' => $s, 'media' => null],
            $sentences
        );

        return array_merge($imagesFirst, $textBubbles, $videosLast);
    }

    private const ABBREV = [
        'Komp', 'No', 'Jl', 'Km', 'Yth', 'Dst', 'Dll', 'Pak', 'Bu', 'Tn',
        'Ny', 'Apt', 'Ir', 'Drs', 'Prof', 'Min', 'Hal', 'Bpk', 'Sdr',
        'Tgl', 'Th', 'a.n', 'u.p', 'd.a', 'ttd',
    ];

    /**
     * Aturan pemecahan bubble:
     *   1. Marker eksplisit `[BUBBLE]` di template selalu dihormati dan
     *      memecah teks di titik itu, terlepas dari context apa pun.
     *   2. Booking flow (slug `booking_*`) → SATU bubble per template.
     *      Konfirmasi multi-baris, list jam bernomor "1. 07:00", dan
     *      bubble booking_sukses harus utuh — admin sudah konfirmasi
     *      lewat feedback "rapihkan, jangan dobel2 gini".
     *   3. Selain itu (consultation / harga / info) → pecah per inti
     *      kalimat: setiap tanda akhir kalimat `.!?` diikuti whitespace
     *      jadi pemisah bubble. Abbreviation list dihormati supaya
     *      "Komp.", "No.", "Bpk.", dst. tidak salah dipecah.
     */
    private function splitText(string $text, ?string $slug = null): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        if (str_contains($text, '[BUBBLE]')) {
            $parts = preg_split('/\s*\[BUBBLE\]\s*/u', $text);
            $out = [];
            foreach ((array) $parts as $p) {
                $p = trim((string) $p);
                if ($p !== '') $out[] = $p;
            }
            return $out ?: [$text];
        }

        if ($slug !== null && str_starts_with($slug, 'booking_')) {
            return [$text];
        }

        return $this->splitBySentence($text);
    }

    /**
     * Pecah teks menjadi list kalimat. Pemisah = `.!?` + whitespace
     * (atau akhir teks), kecuali kalau token sebelum pemisah adalah
     * abbreviation yang terdaftar di self::ABBREV.
     */
    private function splitBySentence(string $text): array
    {
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

    private function substituteVariables(string $template, BotSession $session): string
    {
        $alamat = (string) config('sunatbot.alamat_klinik', '');
        $maps   = (string) config('sunatbot.link_maps', '');
        $rona   = (string) config('sunatbot.nomor_rona', '');
        $nama   = (string) ($session->getData('nama_orang_tua') ?? $session->getData('nama') ?? '');

        // Booking placeholders. Tanggal di-format ke Bahasa Indonesia
        // (mis. "15 Juni 2026") biar enak dibaca customer.
        $bookingTanggalRaw = (string) ($session->getData('booking_tanggal') ?? '');
        $bookingTanggalDisplay = '';
        if ($bookingTanggalRaw !== '') {
            try {
                $dt = Carbon::parse($bookingTanggalRaw);
                $idMonths = [
                    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                    7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember',
                ];
                $bookingTanggalDisplay = $dt->day . ' ' . $idMonths[(int) $dt->month] . ' ' . $dt->year;
            } catch (\Throwable $e) {
                $bookingTanggalDisplay = $bookingTanggalRaw;
            }
        }
        $bookingJam      = (string) ($session->getData('booking_jam') ?? '');
        $bookingNamaAnak = (string) ($session->getData('booking_nama_anak') ?? '');

        // {{usia_bb}} — format "<usia> <satuan>, <bb> kg" dari session
        // booking_* keys; kalau belum ada, kosong (template tidak akan
        // pernah render konfirmasi sebelum step usia_bb selesai).
        $bookingUsiaBb = '';
        $bUsia = $session->getData('booking_usia_anak');
        $bUnit = $session->getData('booking_usia_anak_satuan') ?? 'tahun';
        $bBb   = $session->getData('booking_berat_badan_anak');
        if ($bUsia !== null && $bBb !== null) {
            $bbDisplay     = (fmod((float) $bBb, 1.0) == 0) ? (int) $bBb : $bBb;
            $bookingUsiaBb = $bUsia . ' ' . $bUnit . ', ' . $bbDisplay . ' kg';
        }

        $replacements = [
            '[NAMA]'          => $nama !== '' ? ucwords($nama) : 'kak',
            '[ALAMAT_KLINIK]' => $alamat,
            '[LINK_MAPS]'     => $maps,
            '[NOMOR_RONA]'    => $rona,
            '{{nama}}'        => $nama !== '' ? ucwords($nama) : 'kak',
            '{{tanggal}}'     => $bookingTanggalDisplay,
            '{{tanggal_iso}}' => $bookingTanggalRaw,
            '{{jam}}'         => $bookingJam,
            '{{nama_anak}}'   => $bookingNamaAnak !== '' ? ucwords($bookingNamaAnak) : '',
            '{{usia_bb}}'     => $bookingUsiaBb,
            '{{jam_list}}'    => $this->buildJamList($bookingTanggalRaw),
            '{{nomor_admin}}' => $this->buildNomorAdminSunat(),
        ];

        return strtr($template, $replacements);
    }

    /**
     * List nomor WA admin sunat (users.flagging_admin_sunat=1) sebagai
     * bullet list `- Nama: wa.me/<no>`. Dipakai di booking_sukses dan
     * template lain yang minta customer hubungi admin manusia kalau
     * ada kendala. Kalau tidak ada admin yang ditandai, kembali ke
     * teks fallback "klinik" supaya kalimat tetap natural.
     */
    private function buildNomorAdminSunat(): string
    {
        try {
            $rows = \DB::table('users')
                ->where('users.flagging_admin_sunat', 1)
                ->join('stafs', 'users.staf_id', '=', 'stafs.id')
                ->select('stafs.nama', 'stafs.no_hp', 'stafs.no_telp')
                ->orderBy('stafs.nama')
                ->get();
        } catch (\Throwable $e) {
            return 'klinik';
        }

        $lines = [];
        foreach ($rows as $r) {
            $raw = trim((string) ($r->no_hp ?: $r->no_telp));
            if ($raw === '') continue;
            $e164 = $this->normalizePhoneToE164($raw);
            if ($e164 === '') continue;
            $lines[] = '- ' . ucwords(mb_strtolower((string) $r->nama)) . ': wa.me/' . $e164;
        }

        if ($lines === []) return 'klinik';
        return implode("\n", $lines);
    }

    /**
     * Bersihkan prefix umum di jawaban nama (orang tua / anak). AI
     * kadang ngembaliin echo "Saya Yoga" persis seperti input customer;
     * fallback raw message juga pasti ada prefiks. Kita strip case-
     * insensitive, satu kali per pattern, supaya "Saya Yoga" → "Yoga"
     * tapi nama beneran ("Sayatama Putri") tidak terpotong (regex pakai
     * \s+ supaya butuh whitespace setelah kata kunci).
     */
    private function cleanCapturedName(string $value): string
    {
        $v = trim($value);
        if ($v === '') return $v;
        $patterns = [
            '/^nama\s+saya\s+(adalah\s+)?/iu',
            '/^nama\s+orang\s+tua\s+(adalah\s+)?/iu',
            '/^atas\s+nama\s+/iu',
            '/^panggil\s+(saja\s+|aja\s+)?/iu',
            '/^dengan\s+/iu',
            '/^saya\s+/iu',
            '/^aku\s+/iu',
            '/^this\s+is\s+/iu',
            '/^i\s+am\s+/iu',
            '/^my\s+name\s+is\s+/iu',
        ];
        foreach ($patterns as $p) {
            $v = (string) preg_replace($p, '', $v, 1);
        }
        // Trailing prefiks ramah ("kak", "pak", "bu") di akhir kalimat —
        // bukan bagian dari nama, hapus juga.
        $v = (string) preg_replace('/\s+(kak|kakak|pak|bu|ya)\.?$/iu', '', $v);
        return trim($v);
    }

    /**
     * Cek apakah string layak jadi nama orang tua. Reject:
     *  - terlalu pendek (< 2 char)
     *  - berisi indicator usia / BB (customer mishears step)
     *  - mostly digit (>50% digit)
     */
    private function isPlausibleName(string $value): bool
    {
        $v = trim($value);
        if (mb_strlen($v) < 2) return false;

        // Indicator customer salah jawab pertanyaan usia/BB di step nama.
        if (preg_match('/\b(usia|umur|tahun|thn|th|bulan|bln|kg|kilo|kilogram|berat|bb)\b/iu', $v)) {
            return false;
        }

        // Reject kalau mostly digits (lebih dari 50% karakter adalah angka).
        $digitCount = strlen(preg_replace('/\D/', '', $v) ?? '');
        $totalLen   = mb_strlen($v);
        if ($totalLen > 0 && $digitCount / $totalLen > 0.5) {
            return false;
        }

        return true;
    }

    /**
     * "081..." / "+62..." / "62..." → "62..." (digits only). Kembali
     * string kosong kalau tidak bisa di-normalize.
     */
    private function normalizePhoneToE164(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '' || $digits === null) return '';
        if (str_starts_with($digits, '0'))   return '62' . substr($digits, 1);
        if (str_starts_with($digits, '62'))  return $digits;
        if (str_starts_with($digits, '8'))   return '62' . $digits;
        return $digits;
    }
}
