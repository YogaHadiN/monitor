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

        // Append user turn — disimpan ke history setelah loop selesai.
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history,
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
                // HARD GUARD: kalau tool sudah pernah emit reply
                // (mis. get_intent_response sudah render template),
                // text ini pasti improvisation/hallucination — drop.
                // Tool result adalah otoritas; agent tidak boleh
                // nambahin info faktual sendiri.
                if ($toolEmittedReplies) {
                    Log::info('SUNAT_BOT_AGENT_DROPPED_POST_TOOL_TEXT', [
                        'phone' => $session->no_telp,
                        'iter'  => $iter,
                        'text'  => mb_substr($textContent, 0, 200),
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
                if ($toolName === 'trigger_booking_flow') {
                    $rejectReason = $this->validateBookingFlowMessage($this->currentUserMessage);
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
            'handled'  => $replies !== [] || $signal !== null,
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

        return <<<PROMPT
Kamu adalah CS WhatsApp klinik sunat anak SunatBoy (Klinik Jati Elok, Tangerang).
Bicaranya santai, ramah, natural — seperti staf admin manusia. Jawab langsung dari FAKTA di bawah, paraphrase bebas, JANGAN ubah angka/nama/detail teknis.

═══ FAKTA KLINIK (sumber tunggal — jawab dari sini) ═══

📍 LOKASI: $klinik
   Maps: $maps

🕘 JAM PRAKTIK: Setiap hari Senin–Minggu, 07.00–17.00 WIB.

⚕️ METODE: Teknoklamp — 1 metode saja yang kami pakai.
   - Pakai alat cetak (hasil lebih rapi)
   - Tanpa alat menempel
   - Tanpa perban (anak pakai celana sunat khusus dari kami)
   - Perdarahan minimal
   - Mesin electrosurgical seperti di ruang operasi modern
   - Pakai jarum bius tipis (bukan tanpa jarum), teknik nyaman — kebanyakan anak tidak menangis lama
   - Tidak pakai sedasi / general anesthesia

🩺 OPERATOR: Dokter spesialis (bukan mantri). Ada perawat asisten.

👶 USIA IDEAL: 1–7 tahun. Bayi & dewasa juga bisa dilayani.

♀️ Sunat PEREMPUAN: TIDAK kami layani. Hanya laki-laki.

🏠 Sunat DI RUMAH: TIDAK ada home service. Hanya di klinik.

💊 BPJS / Asuransi: TIDAK bisa pakai BPJS atau asuransi lain. Pembayaran mandiri saja.

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

🎉 PROMO PAKET GRUP:
   - 2 anak sekaligus: dapat diskon Rp 500.000
   - 3 anak sekaligus: dapat diskon Rp 1.000.000
   Cocok buat kakak-adik, sepupu, atau teman 1 angkatan.
   ⚠️ JANGAN sebut TOTAL harga akhir (Rp 4.5jt / Rp 6.5jt) — sebut diskon saja.

⭐ KASUS KHUSUS (WAJIB escalate ke admin, JANGAN langsung quote/booking):
   - ADHD / autisme / ASD / hiperaktif / berkebutuhan khusus
   - Anak gemuk / obesitas (faktor risiko anestesi)
   - Riwayat penyakit (jantung, kelainan pembekuan darah, dll)
   → Engine otomatis handoff kalau customer sebut kondisi ini, jangan kamu reply panjang sendiri.

═══ ATURAN HARGA (PENTING — JANGAN sebut total harga sembarangan) ═══

Untuk request harga / PL / price list / penawaran / "berapa biaya":
- JANGAN sebut angka total harga sunat (mis. "4.5 juta", "mulai 3 juta") — apapun angkanya. Total harga HANYA boleh keluar lewat engine quote flow (`trigger_harga_flow`), setelah customer melewati edukasi (metode, bius, sembuh, dll).
- WAJIB panggil tool `trigger_harga_flow` utk customer mau quote real. Engine akan: tanya nama → domisili → usia → BB → edukasi metode + bius + sembuh → baru quote total.
- BOLEH sebut PROMO DISKON paket grup (Rp 500rb utk 2 anak, Rp 1jt utk 3 anak) sebagai info — TANPA sebut total akhir.
- Kalau customer tanya "berapa harganya sih?" sebelum edukasi → jawab "Untuk biaya sunat tergantung usia dan berat badan anak kak. Boleh saya bantu hitungin? Akan kami tanya beberapa info dulu untuk dapat harga pasti." Lalu trigger_harga_flow.

═══ ROUTING TOOL (untuk action, bukan info) ═══

- `trigger_harga_flow` → request quote harga (PL/penawaran). Wajib utk angka real.
  - WAJIB extract dari current message + history: nama_orang_tua, domisili, usia_anak, berat_badan_anak, dll → pass sbg parameter. JANGAN call dgn args kosong kalau info ada.
- `trigger_booking_flow` → customer mau booking jadwal SUNAT (pesan WAJIB ada kata "sunat"/"khitan"/"sirkumsisi"). DILARANG utk non-sunat.
  - WAJIB extract dari current message + history: tanggal, jam, nama_anak, nama_panggilan, usia_anak, berat_badan_anak → pass sbg parameter. Engine akan auto-store jadwal kalau semua complete.
  - CONTOH: customer "mau daftar sunat anak Faiz BB 22 tanggal 5 Juli 2026 jam 10" + history sebut "umur 7 tahun":
    → `trigger_booking_flow(tanggal="2026-07-05", jam="10:00", nama_anak="Faiz", usia_anak="7 tahun", berat_badan_anak=22)`
    Engine populate semua → langsung INSERT jadwal_sunats, customer dapat konfirmasi sukses.
  - JANGAN call dgn args kosong kalau customer sudah kasih info — itu bug, customer harus ulang ngetik.
- `redirect_ke_klinik_utama` → customer EKSPLISIT sebut layanan non-sunat: USG, kandungan, hamil, lab, cek darah, dokter umum, gigi, kulit, vaksin, imunisasi, mobile jkn, jkn (tanpa "sunat"), kontrol obat. Termasuk "daftar USG" / "daftar lab" / "daftar dokter umum" — semua redirect, BUKAN booking_flow.

═══ ⚠️ WAJIB call get_intent_response — JANGAN paraphrase ⚠️ ═══
Untuk 8 topic di bawah, customer butuh LIHAT foto/video. Kalau jawab dari FAKTA langsung tanpa call tool, FOTO/VIDEO TIDAK TERKIRIM ke customer. INI BUG. WAJIB call `get_intent_response(slug)`:

| Topic customer tanya | slug yang HARUS dipanggil |
|---|---|
| Lokasi / alamat / maps / dimana kliniknya | `pertanyaan_lokasi` |
| Metode / teknik / alat / teknoklamp / cara sunat | `pertanyaan_metode` |
| Jarum / bius / suntik / sakit ga | `pertanyaan_jarum_bius` |
| Fasilitas / yang didapat / include apa / dapat apa saja | `pertanyaan_fasilitas` |
| Testimoni / review / kesaksian / pengalaman client lain | `pertanyaan_testimoni` |
| Hadiah / kado / dapat hadiah ga | `pertanyaan_hadiah` |
| Contoh dokumentasi / mini vlog / video pengalaman | `contoh_dokumentasi` |
| Closing harga / detail paket sunat | `quote_harga_paket` |

Topic LAIN (BPJS, sunat perempuan, sunat dewasa, sunat bayi, sunat di rumah, jahit, perban, durasi sembuh, lama proses, usia ideal, kebutuhan khusus, kontrol, operator/dokter, dll) → jawab natural dari FAKTA. Tidak perlu tool.

═══ FALLBACK lookup_knowledge ═══
Pakai `lookup_knowledge` cuma kalau pertanyaan SPESIFIK yang TIDAK tercakup di FAKTA + bukan topic media di atas. Untuk fakta yang sudah di prompt, jawab langsung.

═══ ATURAN OUTPUT SETELAH TOOL ═══
Setelah `get_intent_response` / `trigger_harga_flow` / `trigger_booking_flow` / `redirect_ke_klinik_utama` → output string KOSONG. Tool sudah render bubble.

═══ STYLE ═══
- Reply MAKSIMAL 2 KALIMAT PENDEK = 1-2 bubble (splitter pecah per kalimat). 1 bubble lebih bagus. JANGAN 4-5 bubble.
- DILARANG tambah "Kalau ada pertanyaan lain, silakan tanya ya!" di tiap reply — boring repetitive, customer ga butuh dijemput tiap saat. Cuma tambahkan kalau memang akhir percakapan.
- DILARANG emoji 1 bubble sendiri (😊 atau 🙏 doang). Gabung dgn text di bubble sebelumnya, atau jangan pakai sama sekali. Splitter pecah emoji jadi bubble sendiri kalau tidak menempel di text.
- JANGAN gunakan markdown link `[text](url)` — WhatsApp TIDAK render markdown. Tulis URL polos: `https://maps.app.goo.gl/...`.
- Pakai bahasa Indonesia natural. JANGAN "Selamat hari" (terjemahan literal).
- Sapa pakai "kak". Jangan "Bapak/Ibu" kecuali context formal.
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
- GREETING / pesan unclear / customer baru mulai chat → "Silakan kak 🙏 Ada yang bisa dibantu?"
- Customer BILANG TERIMA KASIH / closing → "Sama-sama kak 🙏 Kalau ada pertanyaan lain silakan."
- DILARANG pakai "Sama-sama kak" sebagai opening — itu reply utk terima kasih, BUKAN sapaan awal.
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
                    'description' => 'Panggil ini saat customer EKSPLISIT punya keperluan BUKAN sunat: USG, lab, dokter umum, BPJS umum (bukan BPJS sunat), gigi, poli kulit, vaksin umum, dll. Bot kirim pesan redirect ke admin klinik utama Meta (6282113781271) dgn wa.me link. DILARANG dipanggil utk greeting kosong/pendek ("halo", "sore", "p") — utk itu tanya keperluan dulu. Throttled 1x/hari per nomor.',
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
                    'name'        => 'trigger_harga_flow',
                    'description' => 'Customer minta quote harga / paket / penawaran. Engine masuk ke harga flow (capture nama, domisili, usia/BB, dst). KASIH parameter utk field yang SUDAH disebut customer di percakapan supaya engine tidak nanya ulang. Setelah call tool, output text KOSONG.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'nama_orang_tua'    => ['type' => 'string', 'description' => 'kalau sudah disebut, mis. "Ibu Yeni"'],
                            'domisili'          => ['type' => 'string', 'description' => 'kalau sudah disebut, mis. "Tangerang"'],
                            'usia_anak'         => ['type' => 'string', 'description' => 'angka usia dgn satuan, mis. "7 tahun" atau "8 bulan"'],
                            'berat_badan_anak'  => ['type' => 'number', 'description' => 'dalam kg, mis. 18 atau 25.5'],
                            'sudah_tahu_metode' => ['type' => 'string', 'description' => '"ya" atau "tidak" kalau customer udah bilang'],
                            'indikasi_khitan'   => ['type' => 'string', 'description' => 'keluhan medis atau "tidak ada"'],
                            'postur_tubuh'      => ['type' => 'string', 'description' => '"gemuk" atau "tidak gemuk" / "normal"'],
                            'riwayat_kesehatan' => ['type' => 'string', 'description' => 'kondisi medis (jantung, autisme dll) atau "tidak ada"'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'trigger_booking_flow',
                    'description' => 'HANYA untuk booking SUNAT — pesan customer WAJIB ada kata "sunat" / "khitan" / "sirkumsisi". DILARANG utk USG, lab, dokter umum, gigi, kulit, vaksin, kandungan, BPJS umum, dll → pakai redirect_ke_klinik_utama. KASIH parameter utk field yang sudah disebut customer di percakapan (tanggal, jam, nama_anak, usia, BB) supaya engine skip step itu. Kalau semua complete, engine auto-store jadwal_sunats. Output text KOSONG setelah call.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'tanggal'           => ['type' => 'string', 'description' => 'format YYYY-MM-DD, mis. "2026-07-15"'],
                            'jam'               => ['type' => 'string', 'description' => 'format HH:MM, mis. "10:00"'],
                            'nama_anak'         => ['type' => 'string', 'description' => 'nama lengkap anak'],
                            'nama_panggilan'    => ['type' => 'string', 'description' => 'nama panggilan, mis. "Nio"'],
                            'usia_anak'         => ['type' => 'string', 'description' => 'usia dgn satuan, mis. "7 tahun"'],
                            'berat_badan_anak'  => ['type' => 'number', 'description' => 'dalam kg'],
                        ],
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

            case 'redirect_ke_klinik_utama':
            case 'redirect_ke_admin': // back-compat: agent kadang masih panggil nama lama
                $reason = (string) ($args['reason'] ?? '');
                [$summary, $bubbles] = $this->toolRedirectKeKlinikUtama($session, $reason);
                return [$summary, ['replies' => $bubbles, 'signal' => 'redirected']];

            case 'trigger_harga_flow':
                return [['ok' => true, 'note' => 'engine akan ambil alih flow harga'], [
                    'signal'  => 'enter_harga',
                    'prefill' => $this->normalizeHargaArgs($args),
                ]];

            case 'trigger_booking_flow':
                return [['ok' => true, 'note' => 'engine akan ambil alih flow booking'], [
                    'signal'  => 'enter_booking',
                    'prefill' => $this->normalizeBookingArgs($args),
                ]];

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
    /**
     * Filter argument trigger_harga_flow yang valid (skip kosong/null).
     * Output: assoc array siap pakai utk pre-fill session collected_data.
     */
    private function normalizeHargaArgs(array $args): array
    {
        $out = [];
        $strKeys = ['nama_orang_tua', 'domisili', 'usia_anak', 'sudah_tahu_metode', 'indikasi_khitan', 'postur_tubuh', 'riwayat_kesehatan'];
        foreach ($strKeys as $k) {
            $v = trim((string) ($args[$k] ?? ''));
            if ($v !== '') $out[$k] = $v;
        }
        if (isset($args['berat_badan_anak']) && is_numeric($args['berat_badan_anak'])) {
            $out['berat_badan_anak'] = (float) $args['berat_badan_anak'];
        }
        return $out;
    }

    /**
     * Filter argument trigger_booking_flow yang valid. Pass-through string
     * untuk tanggal/jam — engine parser yang akan validate (parseBookingDate
     * support YYYY-MM-DD, DD/MM/YYYY, "5 Juli 2026", "besok", dll).
     */
    private function normalizeBookingArgs(array $args): array
    {
        $out = [];
        $strKeys = ['tanggal', 'jam', 'nama_anak', 'nama_panggilan', 'usia_anak'];
        foreach ($strKeys as $k) {
            $v = trim((string) ($args[$k] ?? ''));
            if ($v !== '') $out[$k] = $v;
        }
        if (isset($args['berat_badan_anak']) && is_numeric($args['berat_badan_anak'])) {
            $out['berat_badan_anak'] = (float) $args['berat_badan_anak'];
        }
        return $out;
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
        // Throttle 1x/hari per nomor: pakai cache (key by phone).
        $phone = $session->no_telp ?? '';
        $key   = 'sunatbot_agent_redirect:' . preg_replace('/\D+/', '', $phone);

        $alreadySent = \Cache::has($key);
        if ($alreadySent) {
            return [['ok' => true, 'note' => 'redirect skipped (already sent today)'], []];
        }
        \Cache::put($key, 1, now()->endOfDay());

        // Nomor klinik utama Meta — hardcoded per user spec. Bukan
        // pakai nomor_operator (Dr Yoga) yg dulu — operator adalah
        // staf internal, bukan jalur customer service umum.
        $klinikUtama = '6282113781271';
        $waLink      = "https://wa.me/{$klinikUtama}";

        $text = "Halo kak 🙏\n\n"
              . "Nomor ini khusus konsultasi *sunat*. Untuk pendaftaran umum, jadwal dokter, BPJS, atau informasi klinik lainnya, silakan langsung chat admin kami di nomor utama:\n\n"
              . $waLink . "\n\n"
              . "Tap link di atas untuk langsung membuka chat ya kak. Terima kasih.";

        Log::info('SUNAT_BOT_AGENT_REDIRECT', ['phone' => $phone, 'reason' => $reason, 'target' => 'klinik-utama']);

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
