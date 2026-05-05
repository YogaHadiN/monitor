<?php

namespace App\Services\SunatBot;

use App\Models\BotIntent;
use App\Models\BotSession;
use Illuminate\Support\Carbon;

class SunatBotEngine
{
    /**
     * For the harga (price) flow we collect five fields. Each maps to the
     * bot-prompt intent that asks for it (rendered when the field is the
     * next missing one).
     */
    private const HARGA_FLOW = [
        'nama'              => 'tanya_nama_client',
        'domisili'          => 'tanya_domisili',
        'usia_bb'           => 'tanya_usia_bb',
        'keluhan'           => 'tanya_keluhan',
        'riwayat_kesehatan' => 'tanya_riwayat_kesehatan',
    ];

    private const HARGA_CLOSING = [
        'edukasi_metode_lengkap',
        'quote_harga',
        'tanya_jadwal',
    ];

    /**
     * Data-capture intents. The AI classifies the user message into zero or
     * more of these and the engine extracts the corresponding field value
     * into the session instead of replying with a template.
     */
    private const DATA_INTENT_FIELD = [
        'data_nama'              => 'nama',
        'data_domisili'          => 'domisili',
        'data_usia_bb'           => 'usia_bb',
        'data_keluhan'           => 'keluhan',
        'data_riwayat_kesehatan' => 'riwayat_kesehatan',
    ];

    private const FIELD_DESCRIPTIONS = [
        'nama'              => 'nama depan orang tua / pengirim pesan',
        'domisili'          => 'kota / kecamatan domisili',
        'usia_bb'           => 'usia anak (tahun) dan berat badan (kg) digabung dalam satu string',
        'keluhan'           => 'keluhan medis singkat atau "tidak ada"',
        'riwayat_kesehatan' => 'riwayat penyakit / kondisi khusus anak atau "tidak ada"',
    ];

    public function __construct(private IntentClassifier $classifier)
    {
    }

    /**
     * Process an incoming WA message.
     * Returns ['handled' => bool, 'replies' => array<['text'=>string,'media'=>string[]]>]
     */
    public function handle(string $noTelp, string $message): array
    {
        $msg       = trim($message);
        $msgLower  = mb_strtolower($msg);
        $hasTrigger = str_contains($msgLower, 'sunat') || str_contains($msgLower, 'khitan');
        $session   = BotSession::where('no_telp', $noTelp)->first();

        if ($session === null && !$hasTrigger) {
            return ['handled' => false, 'replies' => []];
        }

        if ($session && $session->is_complete) {
            return ['handled' => false, 'replies' => []];
        }

        $replies = [];
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
     * normal reply intents, capture data the user volunteered, and enter
     * the harga flow if pertanyaan_harga was detected.
     */
    private function classifyAndRespond(BotSession $session, string $message, bool $skipFallback = false): array
    {
        $candidates = $this->candidateSlugs();
        $intents    = $this->classifier->classify($message, $candidates);

        if (empty($intents)) {
            if ($skipFallback) {
                return [];
            }
            $replies = $this->renderIntent('fallback_unknown', $session);
            $session->is_complete = true;
            return $replies;
        }

        // Order: side answers first → data captures → harga trigger last.
        usort($intents, function ($a, $b) {
            $rank = function ($x) {
                if ($x === 'pertanyaan_harga') return 3;
                if ($x === 'tanya_jadwal')     return 2;
                if (isset(self::DATA_INTENT_FIELD[$x])) return 1;
                return 0;
            };
            return $rank($a) - $rank($b);
        });

        $replies        = [];
        $hargaTriggered = false;
        foreach ($intents as $slug) {
            if ($slug === 'pertanyaan_harga') {
                $hargaTriggered = true;
                continue;
            }
            if ($slug === 'tanya_jadwal') {
                $replies = array_merge($replies, $this->renderIntent('tanya_jadwal', $session));
                $session->is_complete = true;
                return $replies;
            }
            if (isset(self::DATA_INTENT_FIELD[$slug])) {
                $this->captureField($session, self::DATA_INTENT_FIELD[$slug], $message);
                continue;
            }
            $replies = array_merge($replies, $this->renderIntent($slug, $session));
        }

        if ($hargaTriggered) {
            $replies = array_merge($replies, $this->renderIntent('pertanyaan_harga', $session));
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
     * Inside the harga flow: classify the message, capture any data fields
     * mentioned, answer side questions via their templates, then either ask
     * the next missing field or emit the closing if everything is collected.
     */
    private function processHargaTurn(BotSession $session, string $message): array
    {
        $candidates = $this->candidateSlugs();
        $intents    = $this->classifier->classify($message, $candidates);

        $replies     = [];
        $capturedAny = false;
        $sideAnswered = false;

        foreach ($intents as $slug) {
            if ($slug === 'pertanyaan_harga' || $slug === 'tanya_jadwal') {
                continue;
            }
            if (isset(self::DATA_INTENT_FIELD[$slug])) {
                $this->captureField($session, self::DATA_INTENT_FIELD[$slug], $message);
                $capturedAny = true;
                continue;
            }
            $rendered = $this->renderIntent($slug, $session);
            if (!empty($rendered)) {
                $replies = array_merge($replies, $rendered);
                $sideAnswered = true;
            }
        }

        // Fallback: when the classifier returned nothing (or only ignored
        // intents) and we're still expecting a specific field, treat the raw
        // message as that field's value. This rescues short answers like
        // "Yeni" that the model occasionally misses. We DO NOT fall back when
        // a side answer was rendered — that means the user asked a question,
        // not provided a field value.
        $shouldFallback = !$capturedAny
            && !$sideAnswered
            && $session->expecting_field !== null
            && empty(array_diff($intents, ['pertanyaan_harga', 'tanya_jadwal']));

        if ($shouldFallback) {
            $field = $session->expecting_field;
            $description = self::FIELD_DESCRIPTIONS[$field] ?? $field;
            $value = $this->classifier->extractField($field, $description, $message) ?? trim($message);
            if (is_string($value) && trim($value) !== '') {
                $session->setData($field, trim($value));
            }
        }

        $next = $this->nextMissingHargaField($session);
        if ($next === null) {
            $replies = array_merge($replies, $this->emitHargaClosing($session));
        } else {
            $session->expecting_field = $next;
            $replies = array_merge($replies, $this->renderIntent(self::HARGA_FLOW[$next], $session));
        }

        return $replies;
    }

    private function captureField(BotSession $session, string $field, string $message): void
    {
        $description = self::FIELD_DESCRIPTIONS[$field] ?? $field;
        $value = $this->classifier->extractField($field, $description, $message);
        if (is_string($value) && trim($value) !== '') {
            $session->setData($field, trim($value));
        }
    }

    private function candidateSlugs(): array
    {
        // Only intents with keywords participate in classification.
        // Bot-prompt intents (tanya_*, edukasi_metode_lengkap, quote_harga)
        // have empty keywords and are emitted by the engine, not chosen by the AI.
        return BotIntent::where('active', true)
            ->whereNotNull('keywords')
            ->where('keywords', '!=', '')
            ->whereNotIn('intent', ['trigger_sunat', 'fallback_unknown'])
            ->orderBy('urutan')
            ->pluck('intent')
            ->all();
    }

    private function nextMissingHargaField(BotSession $session): ?string
    {
        foreach (array_keys(self::HARGA_FLOW) as $field) {
            if (!$session->getData($field)) {
                return $field;
            }
        }
        return null;
    }

    private function emitHargaClosing(BotSession $session): array
    {
        $session->expecting_field = null;
        $session->is_complete     = true;
        $replies = [];
        foreach (self::HARGA_CLOSING as $slug) {
            $replies = array_merge($replies, $this->renderIntent($slug, $session));
        }
        return $replies;
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

    /**
     * Common Indonesian abbreviations whose trailing period must NOT trigger a split.
     */
    private const ABBREV = [
        'Komp', 'No', 'Jl', 'Km', 'Yth', 'Dst', 'Dll', 'Pak', 'Bu', 'Tn',
        'Ny', 'Apt', 'Ir', 'Drs', 'Prof', 'Min', 'Hal', 'Bpk', 'Sdr',
        'Tgl', 'Th', 'a.n', 'u.p', 'd.a', 'ttd',
    ];

    /**
     * Split a template into one fragment per sentence or per line.
     * Newlines start a new fragment; sentence-ending punctuation followed
     * by whitespace splits; URLs and decimals stay intact because the
     * regex requires whitespace after the punctuation; known abbreviations
     * are masked before splitting so addresses are not broken.
     */
    private function splitText(string $text): array
    {
        $marker = "\x01DOT\x01";
        $masked = $text;
        foreach (self::ABBREV as $abr) {
            $masked = preg_replace('/\b' . preg_quote($abr, '/') . '\.(?=\s|$)/u', $abr . $marker, $masked);
        }

        $parts = preg_split('/(?<=[.!?])\s+|\n+/u', $masked) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim(str_replace($marker, '.', $part));
            if ($part !== '') $out[] = $part;
        }
        return $out;
    }

    private function substituteVariables(string $template, BotSession $session): string
    {
        $vars = [
            '[NAMA]'           => $session->getData('nama', 'kak'),
            '[DOMISILI]'       => $session->getData('domisili', ''),
            '[USIA_BB]'        => $session->getData('usia_bb', ''),
            '[ALAMAT_KLINIK]'  => config('sunatbot.alamat_klinik', 'Klinik Jati Elok – SunatBoy, Komp. Bumi Jati Elok Blok A1 No. 4-5, Jl. Raya Legok–Parung Panjang Km. 3, Malangnengah, Pagedangan, Tangerang, Banten 15330'),
            '[LINK_MAPS]'      => config('sunatbot.link_maps', 'https://maps.app.goo.gl/WDWMvex5F9YpgPaE9'),
            '[NOMOR_RONA]'     => config('sunatbot.nomor_rona', '0895-3692-69190'),
        ];
        return strtr($template, $vars);
    }
}
