<?php

namespace App\Services\Bot;

use App\Models\SunatQna;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SunatQnaService
{
    private const CACHE_KEY = 'sunat_qnas_index_v1';
    private const CACHE_TTL = 600; // 10 menit

    public function match(string $userMessage): ?SunatQna
    {
        $msg = trim($userMessage);
        if ($msg === '') {
            return null;
        }

        $candidates = $this->candidates();
        if ($candidates->isEmpty()) {
            return null;
        }

        $exact = $this->exactPatternMatch($msg, $candidates);
        if ($exact !== null) {
            return $exact;
        }

        $aiHit = $this->aiMatch($msg, $candidates);
        if ($aiHit !== null) {
            return $aiHit;
        }

        return $this->keywordMatch($msg, $candidates);
    }

    private function exactPatternMatch(string $msg, $candidates): ?SunatQna
    {
        $msgLower = mb_strtolower(trim($msg));
        foreach ($candidates as $qna) {
            foreach (($qna->patterns ?? []) as $pattern) {
                $p = mb_strtolower(trim((string) $pattern));
                if ($p !== '' && ($p === $msgLower || str_contains($msgLower, $p))) {
                    return $qna;
                }
            }
        }
        return null;
    }

    public function isClosing(SunatQna $qna): bool
    {
        $ans = strtolower($qna->answer);
        return str_contains($ans, 'sama-sama')
            || str_contains($ans, 'semoga harinya');
    }

    private function candidates()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return SunatQna::query()
                ->where('active', true)
                ->orderBy('urutan')
                ->get()
                ->reject(fn (SunatQna $q) => $this->isMetaTemplate($q->answer))
                ->values();
        });
    }

    private function isMetaTemplate(string $answer): bool
    {
        return (bool) preg_match('/^\s*\(\s*sistem/iu', $answer);
    }

    private function keywordMatch(string $msg, $candidates): ?SunatQna
    {
        $msgLower  = mb_strtolower($msg);
        $msgTokens = $this->tokenize($msgLower);
        if (empty($msgTokens)) {
            return null;
        }

        $bestQna   = null;
        $bestScore = 0.0;

        foreach ($candidates as $qna) {
            foreach (($qna->patterns ?? []) as $pattern) {
                $p = mb_strtolower(trim((string) $pattern));
                if ($p === '') continue;

                if ($p === $msgLower || str_contains($msgLower, $p)) {
                    return $qna;
                }

                $pTokens = $this->tokenize($p);
                if (empty($pTokens)) continue;

                $overlap = 0;
                foreach ($pTokens as $pt) {
                    foreach ($msgTokens as $mt) {
                        if ($pt === $mt) { $overlap++; break; }
                        if (mb_strlen($pt) >= 4 && mb_strlen($mt) >= 4
                            && (str_starts_with($pt, $mt) || str_starts_with($mt, $pt))) {
                            $overlap++;
                            break;
                        }
                    }
                }
                $score = $overlap / max(count($pTokens), 1);

                $minOverlap = min(2, count($pTokens));
                if ($overlap >= $minOverlap && $score >= 0.5 && $score > $bestScore) {
                    $bestScore = $score;
                    $bestQna   = $qna;
                }
            }
        }

        return $bestQna;
    }

    private function tokenize(string $text): array
    {
        $stop = [
            'ya','kak','kakak','dong','aja','dulu','sih','nih','ka','dan','atau','di','ke','dari',
            'yang','untuk','saja','tuh','iya','tidak','gak','ga','nggak','ada','bisa','boleh','mau',
            'berapa','apa','apakah','kapan','kenapa','mengapa','bagaimana','gimana','dimana','mana',
            'saya','aku','kami','kita','sini','situ','itu','ini','tuh','lah','kah','tanya','kalau',
            'pak','bu','dok','dokter','tolong','mohon','perlu','sudah','udah','belum','blm','ingin',
            'halo','hai','selamat','pagi','siang','sore','malam','assalamualaikum',
        ];
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '';
        $words = preg_split('/\s+/u', trim($clean)) ?: [];
        $words = array_filter($words, fn ($w) => mb_strlen($w) >= 3 && !in_array($w, $stop, true));
        return array_values(array_unique($words));
    }

    private function aiMatch(string $msg, $candidates): ?SunatQna
    {
        $apiKey = env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            return null;
        }

        $list = '';
        foreach ($candidates as $i => $qna) {
            $patterns = implode(' | ', array_slice($qna->patterns ?? [], 0, 4));
            $list .= ($i + 1) . '. ' . $patterns . "\n";
        }

        $prompt = "Anda klasifikator untuk WhatsApp bot klinik sunat anak.\n"
            . "Diberikan daftar kategori pertanyaan. Tentukan kategori mana yang PALING cocok dengan pesan user.\n"
            . "ATURAN:\n"
            . "- Balas 0 jika pesan adalah jawaban singkat (nama saja, angka saja, kota saja, 'iya', 'tidak'), bukan pertanyaan.\n"
            . "- Balas 0 jika tidak ada kategori yang benar-benar cocok.\n"
            . "- Balas hanya satu angka.\n\n"
            . "Daftar kategori:\n{$list}\n"
            . "Pesan user: \"{$msg}\"\n\n"
            . "Jawaban (hanya angka 0-" . $candidates->count() . "):";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => 'gpt-4o-mini',
                    'temperature' => 0,
                    'max_tokens'  => 5,
                    'messages'    => [
                        ['role' => 'system', 'content' => 'Kamu hanya membalas dengan satu angka.'],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                ]);

            if (!$response->ok()) {
                Log::warning('SUNAT_QNA_AI_HTTP_FAILED', ['status' => $response->status()]);
                return null;
            }

            $raw = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
            if (!preg_match('/(\d+)/u', $raw, $m)) {
                return null;
            }
            $idx = (int) $m[1];
            if ($idx <= 0 || $idx > $candidates->count()) {
                return null;
            }

            return $candidates[$idx - 1];
        } catch (\Throwable $e) {
            Log::warning('SUNAT_QNA_AI_EXCEPTION', ['err' => $e->getMessage()]);
            return null;
        }
    }
}
