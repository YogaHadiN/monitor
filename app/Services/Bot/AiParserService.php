<?php

namespace App\Services\Bot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiParserService
{
    public function extract(string $userMessage, array $fields): array
    {
        $apiKey = env('OPENAI_API_KEY');
        if ( empty($apiKey) ) {
            return $this->regexFallback($userMessage, $fields);
        }

        $schemaLines = [];
        foreach ($fields as $name => $hint) {
            $schemaLines[] = "- {$name}: {$hint}";
        }
        $schema = implode("\n", $schemaLines);

        $prompt = "Dari pesan WhatsApp berikut, ekstrak field berikut. Balas HANYA JSON valid (tanpa markdown). "
            . "Jika suatu field tidak disebutkan, isi null.\n\n"
            . "Field:\n{$schema}\n\n"
            . "Pesan: \"{$userMessage}\"\n\n"
            . "JSON:";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => 'gpt-4o-mini',
                    'temperature' => 0,
                    'messages'    => [
                        ['role' => 'system', 'content' => 'Kamu adalah parser yang hanya membalas JSON valid.'],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                ]);

            if ( !$response->ok() ) {
                Log::warning('AI_PARSER_HTTP_FAILED', ['status' => $response->status()]);
                return $this->regexFallback($userMessage, $fields);
            }

            $raw = $response['choices'][0]['message']['content'] ?? '{}';
            $raw = trim($raw);
            $raw = preg_replace('/^```(?:json)?|```$/m', '', $raw);
            $parsed = json_decode(trim($raw), true);
            if ( !is_array($parsed) ) {
                return $this->regexFallback($userMessage, $fields);
            }

            $out = [];
            foreach ($fields as $name => $_) {
                $out[$name] = $parsed[$name] ?? null;
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('AI_PARSER_EXCEPTION', ['err' => $e->getMessage()]);
            return $this->regexFallback($userMessage, $fields);
        }
    }

    private function regexFallback(string $msg, array $fields): array
    {
        $out = array_fill_keys(array_keys($fields), null);

        if (array_key_exists('umur', $out) &&
            preg_match('/(\d+)\s*(tahun|thn|th|y)/iu', $msg, $m)) {
            $out['umur'] = (int) $m[1];
        }
        if (array_key_exists('berat_badan', $out) &&
            preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|kilo)/iu', $msg, $m)) {
            $out['berat_badan'] = (float) str_replace(',', '.', $m[1]);
        }
        if (array_key_exists('nama', $out) &&
            preg_match('/(?:saya|nama saya|panggil saya|aku)\s+([a-zA-Z\s]{2,30})/iu', $msg, $m)) {
            $out['nama'] = trim($m[1]);
        }
        if (array_key_exists('domisili', $out) &&
            preg_match('/(?:dari|domisili|di)\s+([a-zA-Z\s]{2,40})/iu', $msg, $m)) {
            $out['domisili'] = trim($m[1]);
        }
        return $out;
    }
}
