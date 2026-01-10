<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;
use Carbon\Carbon;
use App\Http\Controllers\WablasController;
use App\Models\NoTelp;
use App\Models\Staf;
use Input;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;

class FonnteController extends Controller
{
    public function getWebhook(){
        /* Log::info( env('FONNTE_TOKEN') ); */
        Log::info('fonnte');
        Log::info('getWebhook');
        Log::info('yogggaaaa');
        /* $this->webhook(); */
    }
    public function postWebhook()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true) ?: [];

        if (empty($data['sender']) || empty($data['message'])) {
            Log::error('Invalid payload', $data);
            return response()->json(['ok' => false, 'reason' => 'invalid_payload'], 400);
        }

        return $this->webhook($data);
    }
    public function getChaning(){
    }
    public function postChaning(){
    }
    public function getStatus(){
    }
    public function postStatus(){

    }
    public function getConnect(){
    }
    public function postConnect(){
    }

    /**
     * undocumented function
     *
     * @return void
     */
    private function webhook(array $data)
    {
        Log::info('WEBHOOK 54');

        $sender  = (string)($data['sender'] ?? '');
        $message = (string)($data['message'] ?? '');
        $text    = (string)($data['text'] ?? '');

        $senderNorm = preg_replace('/\D/', '', $sender);

        $no_telp_stafs = Staf::pluck('no_hp')
            ->map(fn($n) => $this->normalizePhone((string)$n))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // ambil / buat no_telp dulu (tanpa mengubah last_received_message_time)
        $no_telp = NoTelp::firstOrCreate([
            'tenant_id' => 1,
            'no_telp'   => $senderNorm,
        ]);


        $last = $no_telp->last_contacted_kje_bot_2; // datetime/timestamp nullable

        $shouldRedirect = (
            !in_array($senderNorm, $no_telp_stafs, true) &&
            (is_null($last) ||
                (
                    !is_null( $last ) && Carbon::parse($last)->lte(now()->subHours(24))
                )
            )
        );

        if ($shouldRedirect) {

            $msg  = 'Mohon maaf saat ini fasilitas whatsapp bot dialihkan ke nomor +62 821-1378-1271.';
            $msg .= PHP_EOL . 'Silahkan klik link di bawah ini untuk diarahkan ke nomor tersebut:';
            $msg .= PHP_EOL . PHP_EOL;
            $msg .= 'https://wa.me/6282113781271?text=' . urlencode('halo klinik jati elok');

            $this->replyFonnte($senderNorm, $msg);

            // Kalau Anda ingin “menandai” sudah dikontak sekarang:
            $no_telp->update(['last_contacted_kje_bot_2' => now()]);

            return response()->json(['ok' => true, 'redirected' => true]);
        }

        // Kalau staf, lanjutkan chatbot bila diaktifkan
        // $this->handleSunatboyChatbot($senderNorm, strtolower($message));

        return response()->json(['ok' => true]);
    }

    public function sendFonnte($target, $data) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://api.fonnte.com/send",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => array(
                'target'   => $target,
                'message'  => isset( $data['message'] ) ? $data['message'] : null,
                'url'      => isset( $data['url'] ) ? $data['url'] : null,
                'filename' => isset( $data['filename'] ) ? $data['filename'] : null,
            ),
          CURLOPT_HTTPHEADER => array(
            "Authorization: " . env('FONNTE_TOKEN')
          ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode !== 200) {
            Log::error('Fonnte API failed', [
                'response' => $response,
                'error' => curl_error($curl),
                'code' => $httpCode,
            ]);
        }
        curl_close($curl);

        return $response;
    }



    private function handleSunatboyChatbot($noWa, $message)
    {
        $stateKey = "sunatboy:$noWa:state";
        $state = Redis::get($stateKey) ?? 'ask_nama';

        // Jika user nanya sesuatu (ada tanda ? atau kata tanya)
        Log::info('=====================');
        Log::info('state');
        Log::info( $state );
        Log::info('=====================');

        if ($state === 'ask_nama' && $this->isQuestion($message)) {
            // Jawab dulu dengan GPT
            $this->replyFonnte($noWa, $this->jawabPakaiGPT($message));

            // Lalu lanjut ke alur tanya nama
            return $this->replyFonnte($noWa, "Halo kak 👋 Saya bantu informasinya ya.\nBoleh tahu nama kakak siapa?");
        }

        if ($state !== 'done' && $this->isQuestion($message)) {
            // Jawab dengan GPT (misal: tanya harga)
            $this->replyFonnte($noWa, $this->jawabPakaiGPT($message));

            // Lanjutkan alur dari awal
            Redis::set($stateKey, 'ask_nama');
            return $this->replyFonnte($noWa, "Boleh tahu nama kakak siapa? 😊");
        }

        switch ($state) {
            case 'ask_nama':
                Redis::set("sunatboy:$noWa:nama", $message);
                Redis::set($stateKey, 'ask_usia');
                return $this->replyFonnte($noWa, "Terima kasih kak 🙏\nAnaknya usia berapa tahun ya? 👦");

            case 'ask_usia':
                Redis::set("sunatboy:$noWa:usia", $message);
                Redis::set($stateKey, 'ask_berat');
                return $this->replyFonnte($noWa, "Berat badannya kira-kira berapa ya kak? ⚖️");

            case 'ask_berat':
                Redis::set("sunatboy:$noWa:berat", $message);
                Redis::set($stateKey, 'ask_domisili');
                return $this->replyFonnte($noWa, "Kakak domisili di mana ya? (kecamatan/kota) 🏡");

            case 'ask_domisili':
                Redis::set("sunatboy:$noWa:domisili", $message);
                Redis::set($stateKey, 'ask_kekhawatiran');
                return $this->replyFonnte($noWa, "Kalau boleh tahu kak, ada hal tertentu yang paling dikhawatirkan soal sunat ini? 😊");

            case 'ask_kekhawatiran':
                Redis::set("sunatboy:$noWa:kekhawatiran", $message);
                Redis::set($stateKey, 'ask_tanggal');
                return $this->replyFonnte($noWa, "Rencana sunatnya hari apa kak? 🗓️");

            case 'ask_tanggal':
                Redis::set("sunatboy:$noWa:tanggal", $message);
                Redis::set($stateKey, 'ask_medis');
                return $this->replyFonnte($noWa, "Apakah anak ada riwayat medis khusus? Misalnya alergi, fimosis, autis? 🧠");

            case 'ask_medis':
                Redis::set("sunatboy:$noWa:medis", $message);
                Redis::set($stateKey, 'done');

                $summary = [
                    '👦 Nama' => Redis::get("sunatboy:$noWa:nama"),
                    '🎂 Usia' => Redis::get("sunatboy:$noWa:usia"),
                    '⚖️ Berat' => Redis::get("sunatboy:$noWa:berat"),
                    '🏡 Domisili' => Redis::get("sunatboy:$noWa:domisili"),
                    '🧠 Kekhawatiran' => Redis::get("sunatboy:$noWa:kekhawatiran"),
                    '🗓️ Jadwal' => Redis::get("sunatboy:$noWa:tanggal"),
                    '📄 Riwayat Medis' => Redis::get("sunatboy:$noWa:medis"),
                ];

                $ringkasan = "✅ Berikut data anak kakak:\n\n" . collect($summary)->map(fn($v, $k) => "$k: $v")->implode("\n");

                return $this->replyFonnte($noWa, $ringkasan . "\n\nSaya bantu jadwalkan ya kak 🙏😊");

            case 'done':
                return $this->replyFonnte($noWa, "Terima kasih kak! Jika ingin mengubah data atau bertanya, tinggal balas ya 😊");

            default:
                Redis::set($stateKey, 'ask_nama');
                return $this->replyFonnte($noWa, "Halo kak 👋 Saya bantu informasinya ya.\nBoleh tahu nama kakak siapa?");
        }
    }

    private function isQuestion($text)
    {
        return (
            str_ends_with($text, '?') ||
            preg_match('/(berapa|bolehkah|gimana|apakah|aman|bisa|kapan)/i', $text)
        ) &&
        preg_match('/sunat|khitan/i', $text);
    }

    private function jawabPakaiGPT($text)
    {
        $response = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'Kamu adalah chatbot ramah dari klinik sunat anak SunatBoy. Jawablah dengan singkat, sopan, dan meyakinkan dalam Bahasa Indonesia.'],
                ['role' => 'user', 'content' => $text],
            ]
        ]);

        return $response['choices'][0]['message']['content'] ?? "Maaf kak, saya belum bisa jawab itu saat ini 🙏";
    }

    private function replyFonnte($target, $message)
    {
        return $this->sendFonnte($target, ['message' => $message]);
    }

    private function normalizePhone(string $no): string
    {
        $no = preg_replace('/\D/', '', $no); // buang +, spasi, -

        if (str_starts_with($no, '08')) {
            return '62' . substr($no, 1); // 08xxx → 628xxx
        }

        if (str_starts_with($no, '8')) {
            return '62' . $no; // 8xxx → 628xxx (jaga-jaga)
        }

        return $no; // sudah 62xxx
    }
}
