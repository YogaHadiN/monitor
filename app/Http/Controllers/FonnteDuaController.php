<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;
use App\Http\Controllers\WablasController;
use Input;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;

class FonnteController extends Controller
{
    public function getWebhook(){
        Log::info('fonnte');
        Log::info('getWebhook');
        Log::info('yogggaaaa');
    }
    public function postWebhook(){
        $json      = file_get_contents('php://input');
        $data      = json_decode($json, true);
        if (!isset($data['sender']) || !isset($data['message'])) {
            Log::error('Invalid payload', $data);
            return;
        } else {
            $this->webhook();
        }
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
    private function webhook()
    {
        header('Content-Type: application/json; charset=utf-8');

        $json      = file_get_contents('php://input');
        $data      = json_decode($json, true);
        $device    = $data['device'];
        $sender    = $data['sender'];
        $message   = $data['message'];
        $text      = $data['text']; //button text
        $member    = $data['member']; //group member who send the message
        $name      = $data['name'];
        $location  = $data['location'];

        $url       = $data['url'];
        $filename  = $data['filename'];
        $extension = $data['extension'];

        $no_telp = NoTelp::where('no_telp', $sender)->first();
        if (
            $no_telp->no_kje_bot_berubah !== date('Y-m-d')
        ) {
            $no_telp->no_kje_bot_berubah = date('Y-m-d');
            $no_telp->save();

            $wablas               = new WablasController;
            $wablas->no_telp      = $sender;
            $wablas->autoReply('Nomor pelayanan Klinik Jati Elok berubah menjadi 0821-1378-1271. Silahkan hubungi nomor tersebut. Mohon maaf atas ketidaknyamanannya');
        }
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
}
