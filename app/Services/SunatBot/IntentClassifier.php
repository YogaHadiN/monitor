<?php

namespace App\Services\SunatBot;

use App\Models\BotIntent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntentClassifier
{
    /**
     * Context phone untuk audit trail — di-set sekali dari SunatBotEngine
     * sebelum tiap turn supaya logCall() bisa atribusi call ke percakapan
     * tertentu. Optional: kalau tidak di-set, log tetap masuk tanpa
     * no_telp.
     */
    private ?string $contextPhone = null;

    public function setContext(?string $noTelp): void
    {
        $this->contextPhone = $noTelp !== '' ? $noTelp : null;
    }

    /**
     * Classify a message into one or more intent slugs.
     * Returns an array of slugs (possibly empty) drawn from $candidates.
     */
    public function classify(string $message, array $candidateSlugs): array
    {
        $msg = trim($message);
        if ($msg === '' || empty($candidateSlugs)) {
            return [];
        }

        $apiKey = env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            Log::warning('SUNAT_BOT_INTENT_NO_KEY');
            return [];
        }

        $intents = BotIntent::whereIn('intent', $candidateSlugs)
            ->where('active', true)
            ->orderBy('urutan')
            ->get();

        $catalogue = '';
        foreach ($intents as $i) {
            $kw = trim((string) $i->keywords) === '' ? '(no keywords)' : $i->keywords;
            $contoh = trim((string) $i->pertanyaan_contoh) === '' ? '' : ' | contoh: ' . $i->pertanyaan_contoh;
            $catalogue .= "- {$i->intent}: {$kw}{$contoh}\n";
        }

        $prompt = "Anda klasifikator intent untuk WhatsApp bot klinik sunat anak.\n"
            . "Pesan client bisa mengandung lebih dari satu intent (misalnya tanya lokasi DAN tanya harga).\n"
            . "Pilih semua intent yang COCOK dari daftar berikut, urut dari yang paling relevan.\n"
            . "Balas HANYA JSON array string slug, tanpa penjelasan, contoh: [\"pertanyaan_lokasi\",\"pertanyaan_harga\"].\n"
            . "Kalau tidak ada yang cocok, balas: []\n\n"
            . "Daftar intent:\n{$catalogue}\n"
            . "Pesan client: \"{$msg}\"\n\n"
            . "JSON array:";

        $payload = [
            'model'       => 'gpt-4o-mini',
            'temperature' => 0,
            'max_tokens'  => 100,
            'response_format' => ['type' => 'json_object'],
            'messages'    => [
                ['role' => 'system', 'content' => 'Kamu klasifikator intent. Balas dengan JSON object berisi field "intents" berupa array string slug.'],
                ['role' => 'user',   'content' => $prompt],
            ],
        ];
        $start = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            $this->logCall('classify', $msg, $payload, $response, $start);

            if (!$response->ok()) {
                Log::warning('SUNAT_BOT_INTENT_HTTP_FAIL', ['status' => $response->status()]);
                return [];
            }

            $raw = (string) ($response['choices'][0]['message']['content'] ?? '');
            $decoded = json_decode($raw, true);
            $list = is_array($decoded) ? ($decoded['intents'] ?? $decoded) : [];

            return array_values(array_filter(
                array_map(fn ($s) => (string) $s, (array) $list),
                fn ($s) => in_array($s, $candidateSlugs, true)
            ));
        } catch (\Throwable $e) {
            $this->logCall('classify', $msg, $payload, null, $start, $e->getMessage());
            Log::warning('SUNAT_BOT_INTENT_EXCEPTION', ['err' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Extract multiple structured fields from a single user reply in one
     * AI call. Each entry of $fields is field_name => description.
     *
     * Returns an associative array keyed by the same field names. Missing
     * or unparseable fields are returned as empty strings (not null) so
     * callers can simply check `trim($v) !== ''`. Returns an array of
     * empty strings on AI/key failure.
     */
    public function extractFields(array $fields, string $message): array
    {
        $empty = array_fill_keys(array_keys($fields), '');
        $msg   = trim($message);
        if ($msg === '' || $fields === []) {
            return $empty;
        }

        $apiKey = env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            return $empty;
        }

        $lines = [];
        foreach ($fields as $name => $desc) {
            $lines[] = "- {$name}: {$desc}";
        }
        $catalogue = implode("\n", $lines);

        $prompt = "Ekstrak field berikut dari pesan client. Balas HANYA JSON object dengan key sesuai nama field.\n"
            . "Kalau field tidak ada di pesan, isi dengan string kosong \"\".\n\n"
            . "Field:\n{$catalogue}\n\n"
            . "Pesan: \"{$msg}\"";

        $payload = [
            'model'           => 'gpt-4o-mini',
            'temperature'     => 0,
            'max_tokens'      => 200,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => 'Kamu ekstraktor data multi-field. Balas JSON object dengan key sesuai daftar field; nilai string kosong kalau tidak ada.'],
                ['role' => 'user',   'content' => $prompt],
            ],
        ];
        $start = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            $this->logCall('extractFields', $msg, $payload, $response, $start);

            if (!$response->ok()) return $empty;

            $raw = (string) ($response['choices'][0]['message']['content'] ?? '');
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) return $empty;

            $out = $empty;
            foreach (array_keys($fields) as $name) {
                $v = $decoded[$name] ?? '';
                $out[$name] = is_string($v) ? trim($v) : (is_scalar($v) ? trim((string) $v) : '');
            }
            return $out;
        } catch (\Throwable $e) {
            $this->logCall('extractFields', $msg, $payload, null, $start, $e->getMessage());
            Log::warning('SUNAT_BOT_EXTRACT_FIELDS_EXCEPTION', ['err' => $e->getMessage()]);
            return $empty;
        }
    }

    /**
     * Extract a specific field value from a user reply.
     * Returns trimmed string or null if AI/key unavailable.
     */
    public function extractField(string $field, string $description, string $message): ?string
    {
        $msg = trim($message);
        if ($msg === '') {
            return null;
        }

        $apiKey = env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            return $msg;
        }

        $prompt = "Ekstrak nilai field \"{$field}\" ({$description}) dari pesan client.\n"
            . "Pesan: \"{$msg}\"\n"
            . "Balas HANYA JSON object dengan field \"value\" berisi string. Kalau tidak bisa, isi value dengan pesan apa adanya.";

        $payload = [
            'model'       => 'gpt-4o-mini',
            'temperature' => 0,
            'max_tokens'  => 100,
            'response_format' => ['type' => 'json_object'],
            'messages'    => [
                ['role' => 'system', 'content' => 'Kamu ekstraktor data. Balas JSON object dengan field "value".'],
                ['role' => 'user',   'content' => $prompt],
            ],
        ];
        $start = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            $this->logCall('extractField:' . $field, $msg, $payload, $response, $start);

            if (!$response->ok()) return $msg;

            $raw = (string) ($response['choices'][0]['message']['content'] ?? '');
            $decoded = json_decode($raw, true);
            $val = is_array($decoded) ? ($decoded['value'] ?? null) : null;
            return is_string($val) && trim($val) !== '' ? trim($val) : $msg;
        } catch (\Throwable $e) {
            $this->logCall('extractField:' . $field, $msg, $payload, null, $start, $e->getMessage());
            Log::warning('SUNAT_BOT_EXTRACT_EXCEPTION', ['err' => $e->getMessage()]);
            return $msg;
        }
    }

    /**
     * Persist tiap call OpenAI ke tabel `openai_logs` (shared dengan log
     * fitur klinik berkas_pemeriksaan / validate_rujukan) supaya admin
     * lihat satu dashboard untuk semua AI traffic. feature di-prefix
     * `sunatbot.` supaya gampang di-filter di UI. Failure logging-nya
     * sendiri di-swallow — gagal log tidak boleh ganggu reply bot.
     */
    private function logCall(string $method, string $prompt, array $payload, $response, float $startUs, ?string $errorMessage = null): void
    {
        try {
            $status   = null;
            $rawBody  = null;
            $ok       = false;
            $inTok    = null;
            $outTok   = null;
            if ($response !== null) {
                try {
                    $status  = $response->status();
                    $rawBody = (string) $response->body();
                    $ok      = $response->ok();
                    $usage   = $response->json('usage') ?? [];
                    $inTok   = $usage['prompt_tokens']     ?? null;
                    $outTok  = $usage['completion_tokens'] ?? null;
                } catch (\Throwable $e) {
                    // ignore — response object mungkin half-baked di exception path
                }
            }
            \DB::table('openai_logs')->insert([
                'feature'       => 'sunatbot.' . mb_substr($method, 0, 64),
                'periksa_id'    => null,
                'no_telp'       => $this->contextPhone,
                'prompt'        => mb_substr($prompt, 0, 65000),
                'response'      => $rawBody !== null ? mb_substr($rawBody, 0, 65000) : null,
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
}
