<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class SunatBoyChatbotController extends Controller
{
    public function handleFonnte(Request $request)
    {
        $noWa  = $request->input('number');
        $pesan = trim($request->input('message'));
        $stateKey = "sunatboy:$noWa:state";
        $state = Redis::get($stateKey) ?? 'ask_nama';

        // Cek jika user jawab di luar topik saat flow berlangsung
        if ($state !== 'done' && $this->isQuestion($pesan)) {
            $jawabanAI = $this->jawabPakaiGPT($pesan);
            return $this->fonnteReply($noWa, $jawabanAI);
        }

        switch ($state) {
            case 'ask_nama':
                Redis::set("sunatboy:$noWa:nama", $pesan);
                Redis::set($stateKey, 'ask_usia');
                return $this->fonnteReply($noWa, "Terima kasih kak 🙏\nAnaknya usia berapa tahun ya? 👦");

            case 'ask_usia':
                Redis::set("sunatboy:$noWa:usia", $pesan);
                Redis::set($stateKey, 'ask_berat');
                return $this->fonnteReply($noWa, "Berat badannya kira-kira berapa ya kak? ⚖️");

            case 'ask_berat':
                Redis::set("sunatboy:$noWa:berat", $pesan);
                Redis::set($stateKey, 'ask_domisili');
                return $this->fonnteReply($noWa, "Kakak domisili di mana ya? (kecamatan/kota) 🏡");

            case 'ask_domisili':
                Redis::set("sunatboy:$noWa:domisili", $pesan);
                Redis::set($stateKey, 'ask_kekhawatiran');
                return $this->fonnteReply($noWa, "Kalau boleh tahu kak, ada hal tertentu yang paling dikhawatirkan soal sunat ini? 😊");

            case 'ask_kekhawatiran':
                Redis::set("sunatboy:$noWa:kekhawatiran", $pesan);
                Redis::set($stateKey, 'ask_tanggal');
                return $this->fonnteReply($noWa, "Rencana sunatnya hari apa kak? 🗓️");

            case 'ask_tanggal':
                Redis::set("sunatboy:$noWa:tanggal", $pesan);
                Redis::set($stateKey, 'ask_medis');
                return $this->fonnteReply($noWa, "Apakah anak ada riwayat medis khusus? Misalnya alergi, fimosis, autis? 🧠");

            case 'ask_medis':
                Redis::set("sunatboy:$noWa:medis", $pesan);
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

                return $this->fonnteReply($noWa, $ringkasan . "\n\nSaya bantu jadwalkan ya kak 🙏😊");

            case 'done':
                return $this->fonnteReply($noWa, "Terima kasih kak! Jika ingin mengubah data atau bertanya, tinggal balas ya 😊");

            default:
                Redis::set($stateKey, 'ask_nama');
                return $this->fonnteReply($noWa, "Halo kak 👋 Saya bantu informasinya ya.\nBoleh tahu nama kakak siapa?");
        }
    }

    private function fonnteReply($to, $message)
    {
        return response()->json([
            'status' => 'ok',
            'to' => $to,
            'message' => $message
        ]);
    }

    private function isQuestion($pesan)
    {
        return str_ends_with($pesan, '?') || preg_match('/(berapa|bisa kah|bolehkah|gimana|apakah|aman)/i', $pesan);
    }

    private function jawabPakaiGPT($pesan)
    {
        $apiKey = env('OPENAI_API_KEY');

        $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Kamu adalah asisten layanan sunat anak bernama SunatBoy. Jawablah pertanyaan orang tua dengan ramah, sopan, dan meyakinkan. Gunakan bahasa Indonesia yang sederhana.'
                ],
                [
                    'role' => 'user',
                    'content' => $pesan
                ]
            ]
        ]);

        return $response['choices'][0]['message']['content'] ?? "Maaf kak, saya belum bisa jawab itu sekarang 🙏";
    }
}
