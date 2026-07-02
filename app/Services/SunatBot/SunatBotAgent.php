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
                if ($toolName === 'save_booking_data') {
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
   - Tidak pakai sedasi / general anesthesia

💉 BIUS: Sangat nyaman. Kebanyakan anak tidak menyadari saat proses pembiusan. Anak bisa sibuk main PS / nonton selama proses. Rasa tidak nyaman minim sekali.

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

🎉 PROMO: SAAT INI TIDAK ADA PROMO AKTIF.
   - DILARANG menyebut promo, diskon, paket grup, paket hemat, paket keluarga, dll.
   - Kalau customer tanya promo/diskon → jawab: "Untuk saat ini belum ada promo aktif kak, harga sesuai standar klinik."

⭐ KASUS KHUSUS (WAJIB escalate ke admin, JANGAN langsung quote/booking):
   - ADHD / autisme / ASD / hiperaktif / berkebutuhan khusus
   - Anak gemuk / obesitas (faktor risiko anestesi)
   - Riwayat penyakit (jantung, kelainan pembekuan darah, dll)
   → Engine otomatis handoff kalau customer sebut kondisi ini, jangan kamu reply panjang sendiri.

═══ ATURAN HARGA (natural collection — KAMU yang drive percakapan) ═══

🚫 DILARANG MUTLAK menulis angka harga sunat di reply text kamu, BAHKAN KALAU CUSTOMER MEMINTA. Contoh angka yang DILARANG:
- "Rp 2.500.000", "Rp 3.000.000", "Rp 4.500.000" (apapun)
- "2,5 juta", "3jt" (apapun bentuk)
Angka harga HANYA muncul dari tool `send_harga_quote` (template quote_harga_paket). Kamu JANGAN PERNAH tebak atau sebut angka sendiri.

📋 FIELD WAJIB TERKUMPUL sebelum kasih quote (7 field):
1. `nama_orang_tua`       — nama depan ortu / pengirim pesan (contoh "Yeni")
2. `domisili`             — kota / kecamatan (Tangerang / Jakarta / dst)
3. `usia_anak`            — usia + satuan ("7 tahun" atau "8 bulan")
4. `berat_badan_anak`     — kg, angka (mis. 22)
5. `indikasi_khitan`      — keluhan medis atau "tidak ada"
6. `postur_tubuh`         — "gemuk" / "tidak gemuk" / "normal"
7. `riwayat_kesehatan`    — kondisi medis (jantung, autisme, dll) atau "tidak ada"

Field opsional: `sudah_tahu_metode` ("ya"/"tidak").

🎯 CARA KERJA:

1. **Customer minta harga / PL / berapa biaya:**
   Reply text pengantar (satu bubble singkat): "Untuk biaya sunat tergantung usia dan berat badan kak."
   Lalu langsung tanya field pertama yang belum terisi (mulai dari nama).

2. **Customer JAWAB pertanyaan field:**
   WAJIB call `save_harga_data(field=value)` DULU (satu turn), engine simpan. Kamu boleh save multi-field kalau customer sebut sekaligus (mis. "saya Yeni dari Tangerang" → `save_harga_data(nama_orang_tua="Yeni", domisili="Tangerang")`).
   Baca tool response `missing[]` → kalau ada, tanya field berikutnya di reply text yang sama turn. Kalau `missing[]` kosong, langsung call `send_harga_quote()` (jangan tanya lagi).

3. **Tanya field NATURAL dgn text kamu sendiri (JANGAN pakai get_intent_response), 1-2 field per bubble.**
   URUTAN wajib (jangan lompat ke sudah_tahu_metode sebelum 7 field required terkumpul):
   - Belum ada nama_orang_tua → "Kalo boleh tau dengan kakak siapa ya?"
   - Belum ada usia_anak + berat_badan_anak → "Boleh infokan usia dan berat badan anaknya kak?"
   - Belum ada domisili → "Domisilinya di mana kak?"
   - Belum ada indikasi_khitan → "Ada keluhan medis atau alasan khusus kenapa mau khitan kak?"
   - Belum ada postur_tubuh → "Postur anaknya bagaimana kak, proporsional atau ada kelebihan berat?"
   - Belum ada riwayat_kesehatan → "Ada riwayat kesehatan khusus seperti jantung, autisme, atau lainnya kak?"

4. **⚠️ INTERRUPT — customer tanya HAL LAIN di tengah collection:**
   Contoh: setelah kamu tanya domisili, customer malah tanya "Metode nya apa?"
   → **JAWAB DULU** pertanyaan interrupt dengan `get_intent_response(slug="pertanyaan_metode")` atau paraphrase dari FAKTA.
   → Lalu di bubble PENUTUP, kembali ke field yang belum: "Balik ke tadi kak, domisilinya di mana?"

5. **Semua 7 field terkumpul → `send_harga_quote()`:**
   Tool emit bundle final (testimoni + delay + quote + closing). Setelah call, output text kosong.

6. **Escalation gate:**
   Kalau `save_harga_data` return `escalate=true` (indikasi != "tidak ada" / postur = "gemuk" / riwayat > "tidak ada"), engine ambil alih untuk handoff ke admin. Kamu output kosong setelah call, jangan lanjut.

7. **Harga sudah pernah dikirim** — kalau history sudah ada bubble berisi angka "Rp ..." atau template quote_harga_paket, **DILARANG** call `send_harga_quote` lagi. Bantu jawab pertanyaan lanjutan saja.

8. **⚠️ Kalau customer sudah START harga collection (nama sudah kesave), JANGAN escalate/redirect walau pesan customer nampak unclear/pendek.** Tetap dorong flow: tanya field yang belum, atau (kalau customer minta) call `send_harga_quote` jika sudah komplit. Fallback ke admin hanya kalau escalate=true dari save_harga_data.

CONTOH GOOD FLOW:
  Customer: "Berapa harganya kak?"
  Bot: "Untuk biaya sunat tergantung usia dan berat badan kak."
       "Kalo boleh tau dengan kakak siapa?"
  Customer: "Saya Yeni dari Tangerang, anak 8 tahun 25 kg"
  → save_harga_data(nama_orang_tua="Yeni", domisili="Tangerang", usia_anak="8 tahun", berat_badan_anak=25)
  Bot: "Baik Bunda Yeni." "Ada keluhan medis atau alasan khusus mau khitan kak?"
  Customer: "Metode nya apa dulu ya?"
  → get_intent_response(slug="pertanyaan_metode")  [emit foto+text]
  Bot: (di penutup) "Balik ke tadi kak, ada keluhan medis atau alasan khusus?"
  Customer: "Ga ada. Postur normal, riwayat kesehatan juga ga ada."
  → save_harga_data(indikasi_khitan="tidak ada", postur_tubuh="normal", riwayat_kesehatan="tidak ada")
  → missing[] kosong → send_harga_quote()  [emit quote bundle]

═══ ATURAN BOOKING (natural collection — KAMU yang drive, mirip harga flow) ═══

🚫 DILARANG mengulang info harga/testimoni/fasilitas/paket kalau customer EKSPLISIT bilang mau booking. Langsung mulai collection.

📋 FIELD WAJIB TERKUMPUL sebelum finalize (6 field):
1. `tanggal`           — natural date: "5 Juli 2026" / "besok" / "2026-07-05"
2. `jam`               — HH:MM. Slot valid: 07-11, 13-17
3. `nama_anak`         — nama lengkap (mis. "Faiz Nabil")
4. `nama_panggilan`    — nama panggilan singkat (mis. "Faiz"). Kalau sama dgn nama, isi "-"
5. `usia_anak`         — "X tahun" / "X bulan"
6. `berat_badan_anak`  — kg (angka)

Variasi trigger booking: "daftar", "daftarin", "booking", "book", "nyunatin", "khitan-in", "jadwalin", "ambil jadwal", "set jadwal", "atur jadwal".

🎯 CARA KERJA:

1. **Customer minta booking sunat** (mis. "Saya mau daftar sunat 5 juli jam 7 pagi"):
   WAJIB extract semua field yang customer sebut di pesan (tanggal, jam, nama_anak, nama_panggilan, usia_anak, BB) → `save_booking_data(...)`.
   Baca tool response — kalau `slot_status != "ok"` (blackout / already_booked / dll), sampaikan alasan ke customer + tanya tanggal/jam baru. Kalau `missing[]` masih ada → tanya field itu.

2. **⚠️ GUARD SUNAT:** Kalau customer minta booking NON-SUNAT (USG, dokter umum, gigi, kulit, BPJS, dll), JANGAN call save_booking_data. Pakai `redirect_ke_klinik_utama`. `save_booking_data` di-reject engine kalau pesan tidak ada kata "sunat"/"khitan"/"sirkumsisi".

3. **Tanya field belum NATURAL, 1-2 field per bubble:**
   - Belum ada tanggal → "Boleh tau tanggal berapa mau sunat kak?"
   - Belum ada jam → "Untuk jamnya, mau jam berapa kak?"
   - Belum ada nama_anak → "Atas nama anak siapa booking-nya kak?"
   - Belum ada nama_panggilan → "Nama panggilan anaknya apa kak?"
   - Belum ada usia+BB → "Boleh infokan usia dan berat badan anaknya?"

4. **Slot conflict handling** (baca response save_booking_data):
   - `slot_status="blackout"` → "Mohon maaf tanggal itu klinik libur kak. Mau pilih tanggal lain?"
   - `slot_status="already_booked"` → "Mohon maaf jam itu sudah ada booking lain kak. Mau pilih jam lain?"
   - `slot_status="jam_blocked"` → "Jam tersebut tidak tersedia. Slot yang bisa: 07-11 dan 13-17."
   - `slot_status="invalid_date"` → "Tanggal itu sudah lewat kak. Mau pilih tanggal ke depan?"

5. **⚠️ INTERRUPT** — customer tanya HAL LAIN di tengah collection:
   Contoh: setelah kamu tanya jam, customer malah tanya "Metode nya apa?"
   → JAWAB DULU (get_intent_response atau paraphrase FAKTA) → bubble penutup: "Balik ke tadi kak, jamnya mau jam berapa?"

6. **Semua 6 field terkumpul + slot OK → `finalize_booking()`** (tool emit booking_sukses). Setelah call, output text KOSONG.

7. **Konfirmasi sebelum finalize (opsional):** Kalau kamu mau customer confirm dulu, boleh tanya "Konfirmasi tanggal X jam Y atas nama Z ya kak, benar?" Kalau customer bilang YA/OK → call finalize_booking().

═══ ROUTING TOOL (untuk action, bukan info) ═══

- `save_harga_data` → simpan field harga (nama, domisili, usia, BB, indikasi, postur, riwayat). Return missing[].
- `send_harga_quote` → emit quote bundle final (semua 7 field terkumpul). Text KOSONG setelah.
- `save_booking_data` → simpan field booking (tanggal, jam, nama_anak, nama_panggilan, usia_anak, BB). WAJIB pesan/history ada "sunat"/"khitan". Return missing[] + slot_status.
- `finalize_booking` → commit booking ke jadwal_sunats + kirim booking_sukses. Semua 6 field + slot OK. Text KOSONG setelah.
  - WAJIB extract dari current message + history: tanggal, jam, nama_anak, nama_panggilan, usia_anak, berat_badan_anak → pass sbg parameter. Engine akan auto-store jadwal kalau semua complete.
  - CONTOH: customer "mau daftar sunat anak Faiz BB 22 tanggal 5 Juli 2026 jam 10" + history sebut "umur 7 tahun":
    → `trigger_booking_flow(tanggal="2026-07-05", jam="10:00", nama_anak="Faiz", usia_anak="7 tahun", berat_badan_anak=22)`
    Engine populate semua → langsung INSERT jadwal_sunats, customer dapat konfirmasi sukses.
  - JANGAN call dgn args kosong kalau customer sudah kasih info — itu bug, customer harus ulang ngetik.
- `redirect_ke_klinik_utama` → customer EKSPLISIT sebut layanan non-sunat: USG, kandungan, hamil, lab, cek darah, dokter umum, gigi, kulit, vaksin, imunisasi, mobile jkn, jkn (tanpa "sunat"), kontrol obat. Termasuk "daftar USG" / "daftar lab" / "daftar dokter umum" — semua redirect, BUKAN booking_flow.

═══ ⚠️ WAJIB call get_intent_response — JANGAN paraphrase ⚠️ ═══
Untuk 7 topic di bawah, customer butuh LIHAT foto/video. Kalau jawab dari FAKTA langsung tanpa call tool, FOTO/VIDEO TIDAK TERKIRIM ke customer. INI BUG. WAJIB call `get_intent_response(slug)`:

| Topic customer tanya | slug yang HARUS dipanggil |
|---|---|
| Lokasi / alamat / maps / dimana kliniknya | `pertanyaan_lokasi` |
| Metode / teknik / alat / teknoklamp / cara sunat | `pertanyaan_metode` |
| Jarum / bius / suntik / sakit ga | `pertanyaan_jarum_bius` |
| Fasilitas / yang didapat / include apa / dapat apa saja | `pertanyaan_fasilitas` |
| Testimoni / review / kesaksian / pengalaman client lain | `pertanyaan_testimoni` |
| Hadiah / kado / dapat hadiah ga | `pertanyaan_hadiah` |
| Contoh dokumentasi / mini vlog / video pengalaman | `contoh_dokumentasi` |

Topic LAIN (BPJS, sunat perempuan, sunat dewasa, sunat bayi, sunat di rumah, jahit, perban, durasi sembuh, lama proses, usia ideal, kebutuhan khusus, kontrol, operator/dokter, dll) → jawab natural dari FAKTA. Tidak perlu tool.

🚫 DILARANG panggil `get_intent_response` untuk slug `quote_harga_paket` / `tanya_*` / `data_*` — final quote HANYA via `send_harga_quote()`, dan tanya field harga TANYA SENDIRI dgn text natural.

═══ FALLBACK lookup_knowledge ═══
Pakai `lookup_knowledge` cuma kalau pertanyaan SPESIFIK yang TIDAK tercakup di FAKTA + bukan topic media di atas. Untuk fakta yang sudah di prompt, jawab langsung.

═══ ATURAN OUTPUT SETELAH TOOL ═══
- Setelah `get_intent_response` / `send_harga_quote` / `finalize_booking` / `redirect_ke_klinik_utama` → output string KOSONG. Tool sudah render bubble.
- Setelah `save_harga_data` / `save_booking_data` → BOLEH ada text reply (untuk tanya field berikutnya secara natural).

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
                    'name'        => 'save_harga_data',
                    'description' => 'Simpan 1+ field harga yang customer sebut di turn ini (nama_orang_tua, domisili, usia_anak, berat_badan_anak, indikasi_khitan, postur_tubuh, riwayat_kesehatan, sudah_tahu_metode). Tool return {ok, filled[], missing[], escalate?, reason?}. Baca missing[] untuk tanya field berikutnya natural di reply text. Kalau escalate=true, output text KOSONG (engine ambil alih handoff ke admin). Kalau missing[] kosong, langsung call send_harga_quote().',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'nama_orang_tua'    => ['type' => 'string', 'description' => 'nama depan ortu, mis. "Yeni"'],
                            'domisili'          => ['type' => 'string', 'description' => 'kota/kecamatan, mis. "Tangerang"'],
                            'usia_anak'         => ['type' => 'string', 'description' => 'usia + satuan, mis. "7 tahun" / "8 bulan"'],
                            'berat_badan_anak'  => ['type' => 'number', 'description' => 'kg, mis. 22 atau 25.5'],
                            'indikasi_khitan'   => ['type' => 'string', 'description' => 'keluhan medis atau "tidak ada"'],
                            'postur_tubuh'      => ['type' => 'string', 'description' => '"gemuk" / "tidak gemuk" / "normal"'],
                            'riwayat_kesehatan' => ['type' => 'string', 'description' => 'kondisi medis (jantung, autisme dll) atau "tidak ada"'],
                            'sudah_tahu_metode' => ['type' => 'string', 'description' => '"ya" atau "tidak"'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'send_harga_quote',
                    'description' => 'Emit quote bundle final ke customer: testimoni google review + delay 40s + quote_harga_paket + tanya_pertanyaan_lanjutan. WAJIB dipanggil HANYA setelah semua 7 field wajib terkumpul (nama_orang_tua, domisili, usia_anak, berat_badan_anak, indikasi_khitan, postur_tubuh, riwayat_kesehatan). Kalau ada field belum terisi, tool return error — panggil save_harga_data dulu. Kalau harga sudah pernah dikirim (history ada bubble Rp ...), DILARANG panggil lagi. Setelah call, output text KOSONG.',
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
                    'description' => 'Simpan 1+ field booking yang customer sebut (tanggal, jam, nama_anak, nama_panggilan, usia_anak, berat_badan_anak). WAJIB pesan customer atau history mengandung "sunat"/"khitan"/"sirkumsisi" — DILARANG utk USG, lab, dokter umum, gigi, kulit, vaksin, kandungan, dll. Tool validate slot (blackout / BOOKED / spillover 2 jam). Return {filled[], missing[], slot_status: "ok"|"invalid_date"|"blackout"|"jam_blocked"|"already_booked", slot_error?}. Kalau slot_status != "ok", tanya customer ganti tanggal/jam. Kalau missing[] kosong + slot_status="ok" langsung call finalize_booking().',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'tanggal'           => ['type' => 'string', 'description' => 'natural date: YYYY-MM-DD / "5 Juli 2026" / "besok" / "lusa" / "hari ini"'],
                            'jam'               => ['type' => 'string', 'description' => 'HH:MM atau angka (7, 10, dst). Slot valid: 07-11, 13-17'],
                            'nama_anak'         => ['type' => 'string', 'description' => 'nama lengkap anak (mis. "Faiz Nabil")'],
                            'nama_panggilan'    => ['type' => 'string', 'description' => 'nama panggilan singkat (mis. "Nio"). Kalau customer bilang sama dgn nama, isi dgn "-".'],
                            'usia_anak'         => ['type' => 'string', 'description' => 'usia + satuan (mis. "7 tahun" / "8 bulan")'],
                            'berat_badan_anak'  => ['type' => 'number', 'description' => 'kg (mis. 22)'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'finalize_booking',
                    'description' => 'Commit booking ke jadwal_sunats + kirim bubble booking_sukses ke customer. WAJIB dipanggil HANYA setelah semua 6 field terkumpul (tanggal, jam, nama_anak, nama_panggilan, usia_anak, berat_badan_anak) dan save_booking_data terakhir return slot_status="ok". Kalau field belum lengkap atau slot terpakai, tool return error — panggil save_booking_data dulu. Setelah call, output text KOSONG.',
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

            case 'redirect_ke_klinik_utama':
            case 'redirect_ke_admin': // back-compat: agent kadang masih panggil nama lama
                $reason = (string) ($args['reason'] ?? '');
                [$summary, $bubbles] = $this->toolRedirectKeKlinikUtama($session, $reason);
                return [$summary, ['replies' => $bubbles, 'signal' => 'redirected']];

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

    private const BOOKING_REQUIRED_FIELDS = [
        'booking_tanggal', 'booking_jam', 'booking_nama_anak',
        'booking_nama_panggilan', 'booking_usia_anak', 'booking_berat_badan_anak',
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

        $session->save();

        // Compute filled/missing
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

        // Validate slot kalau tanggal + jam sudah terisi
        $slotStatus = 'ok';
        $slotError  = null;
        if ($session->getData('booking_tanggal') !== null
            && $session->getData('booking_jam') !== null) {
            $conflict = $engine->validateBookingSlotFromSession($session);
            if ($conflict !== null) {
                // Bubble content dari reAskWithFallback — pakai template
                // untuk classify status. Cek slug expecting_field yg baru
                // di-reset di dalam validateBookingSlotFromSession.
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
                $session->save();
                $slotError = 'Slot tidak tersedia. Ajak customer pilih tanggal/jam lain.';
            }
        }

        Log::info('SUNAT_BOT_AGENT_BOOKING_SAVE', [
            'phone'       => $session->no_telp,
            'saved'       => $saved,
            'filled'      => $filled,
            'missing'     => $missing,
            'slot_status' => $slotStatus,
        ]);

        $result = [
            'ok'          => true,
            'filled'      => $filled,
            'missing'     => $missing,
            'slot_status' => $slotStatus,
        ];
        if ($slotError !== null) $result['slot_error'] = $slotError;

        return [$result, []];
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
        if ($missing !== []) {
            return [
                ['ok' => false, 'error' => 'field belum lengkap', 'missing' => $missing],
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

    // ----- HARGA (natural collection, agent-driven) -----------------

    private const HARGA_REQUIRED_FIELDS = [
        'nama_orang_tua', 'domisili', 'usia_anak', 'berat_badan_anak',
        'indikasi_khitan', 'postur_tubuh', 'riwayat_kesehatan',
    ];

    /**
     * Save fields ke collected_data. Return status + missing[] utk LLM
     * kasih tanya field berikutnya. Cek escalation gate:
     *   - indikasi_khitan != "tidak ada" → escalate
     *   - postur_tubuh = "gemuk" / "obesitas" → escalate
     *   - riwayat_kesehatan positif (bukan "tidak ada"/"tidak"/kosong) → escalate
     *
     * @return array{0:array,1:array} tuple [tool_result, side_effect]
     */
    private function toolSaveHargaData(array $args, BotSession $session): array
    {
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
            $session->setData('berat_badan_anak', (float) $args['berat_badan_anak']);
            $saved[] = 'berat_badan_anak';
        }
        $session->save();

        // Escalation gates (medical safety — bot tidak boleh kasih quote
        // untuk kasus yg butuh assessment dokter).
        $escalate = false;
        $reason   = null;

        $indikasi = mb_strtolower(trim((string) $session->getData('indikasi_khitan')));
        if ($indikasi !== '' && !$this->isNoValue($indikasi)) {
            $escalate = true;
            $reason   = "indikasi_khitan: {$indikasi}";
        }
        $postur = mb_strtolower(trim((string) $session->getData('postur_tubuh')));
        if (!$escalate && str_contains($postur, 'gemuk') || str_contains($postur, 'obesitas')) {
            $escalate = true;
            $reason   = "postur_tubuh: {$postur}";
        }
        $riwayat = mb_strtolower(trim((string) $session->getData('riwayat_kesehatan')));
        if (!$escalate && $riwayat !== '' && !$this->isNoValue($riwayat)) {
            $escalate = true;
            $reason   = "riwayat_kesehatan: {$riwayat}";
        }

        // Compute filled/missing utk LLM know what to ask next.
        $filled  = [];
        $missing = [];
        foreach (self::HARGA_REQUIRED_FIELDS as $f) {
            $v = $session->getData($f);
            if ($v === null || $v === '') {
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

        $sideEffect = [];
        if ($escalate) {
            $sideEffect['escalate'] = true;
        }
        return [$result, $sideEffect];
    }

    /**
     * Emit quote bundle final: testimoni_google_review + delay 40s +
     * quote_harga_paket + tanya_pertanyaan_lanjutan. Kalau field belum
     * lengkap, return error — LLM harus panggil save_harga_data dulu.
     *
     * @return array{0:array, 1:array<array>, 2:bool} [result, bubbles, escalate]
     */
    private function toolSendHargaQuote(BotSession $session): array
    {
        $missing = [];
        foreach (self::HARGA_REQUIRED_FIELDS as $f) {
            $v = $session->getData($f);
            if ($v === null || $v === '') $missing[] = $f;
        }
        if ($missing !== []) {
            return [
                ['ok' => false, 'error' => 'field belum lengkap', 'missing' => $missing],
                [],
                false,
            ];
        }

        // Dedupe: kalau quote sudah pernah dikirim, jangan dobel.
        if ($session->getData('_harga_sent')) {
            return [
                ['ok' => false, 'error' => 'quote sudah pernah dikirim, jangan dobel'],
                [],
                false,
            ];
        }

        $bubbles = [];
        // 1. Testimoni Google review (media + text)
        [$_, $testimoni] = $this->toolGetIntentResponse('testimoni_google_review');
        $bubbles = array_merge($bubbles, $testimoni);

        // 2. Delay marker 40s (dispatcher sleep tanpa kirim).
        $bubbles[] = ['text' => '', 'media' => null, 'delay_seconds' => 40];

        // 3. Quote harga paket (respect promo slug swap kalau ada).
        $quoteSlug = 'quote_harga_paket';
        $promoIntent = BotIntent::where('intent', 'quote_harga_paket_promo')
            ->where('active', true)->first();
        if ($promoIntent !== null) {
            $quoteSlug = 'quote_harga_paket_promo';
        }
        [$_, $quote] = $this->toolGetIntentResponse($quoteSlug);
        $bubbles = array_merge($bubbles, $quote);

        // 4. Tanya pertanyaan lanjutan / closing.
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
