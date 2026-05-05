<?php

namespace App\Services\SunatBot;

use App\Models\BotIntent;
use App\Models\BotSession;
use Illuminate\Support\Carbon;

class SunatBotEngine
{
    /**
     * Slugs whose templates are bot-driven prompts during the harga data gathering flow.
     * Order matters: each entry maps to the field collected from the user's NEXT reply.
     */
    private const HARGA_FLOW = [
        // expecting_field => slug to send when entering this step
        'nama'              => 'pertanyaan_harga',
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
            $replies = array_merge($replies, $this->advanceHargaFlow($session, $msg));
        } else {
            $replies = array_merge($replies, $this->classifyAndRespond($session, $msg, $justCreated));
        }

        $session->last_activity_at = Carbon::now();
        $session->save();

        return ['handled' => count($replies) > 0, 'replies' => $replies];
    }

    private function classifyAndRespond(BotSession $session, string $message, bool $skipFallback = false): array
    {
        $candidates = BotIntent::where('active', true)
            ->whereNotNull('keywords')
            ->where('keywords', '!=', '')
            ->whereNotIn('intent', ['trigger_sunat', 'fallback_unknown'])
            ->orderBy('urutan')
            ->pluck('intent')
            ->all();

        $intents = $this->classifier->classify($message, $candidates);

        if (empty($intents)) {
            if ($skipFallback) {
                return [];
            }
            $replies = $this->renderIntent('fallback_unknown', $session);
            $session->is_complete = true;
            return $replies;
        }

        // Move pertanyaan_harga and tanya_jadwal to end so simple answers go first.
        usort($intents, function ($a, $b) {
            $rank = fn ($x) => $x === 'pertanyaan_harga' ? 2 : ($x === 'tanya_jadwal' ? 1 : 0);
            return $rank($a) - $rank($b);
        });

        $replies = [];
        foreach ($intents as $slug) {
            if ($slug === 'pertanyaan_harga') {
                $replies = array_merge($replies, $this->renderIntent('pertanyaan_harga', $session));
                $session->expecting_field = 'nama';
                break;
            }
            if ($slug === 'tanya_jadwal') {
                $replies = array_merge($replies, $this->renderIntent('tanya_jadwal', $session));
                $session->is_complete = true;
                break;
            }
            $replies = array_merge($replies, $this->renderIntent($slug, $session));
        }

        return $replies;
    }

    private function advanceHargaFlow(BotSession $session, string $message): array
    {
        $field = $session->expecting_field;
        $description = self::FIELD_DESCRIPTIONS[$field] ?? $field;
        $value = $this->classifier->extractField($field, $description, $message) ?? trim($message);
        $session->setData($field, $value);

        $sequence = array_keys(self::HARGA_FLOW);
        $idx = array_search($field, $sequence, true);
        $nextField = $sequence[$idx + 1] ?? null;

        $replies = [];
        if ($nextField !== null) {
            $session->expecting_field = $nextField;
            $replies = $this->renderIntent(self::HARGA_FLOW[$nextField], $session);
        } else {
            $session->expecting_field = null;
            foreach (self::HARGA_CLOSING as $slug) {
                $replies = array_merge($replies, $this->renderIntent($slug, $session));
            }
            $session->is_complete = true;
        }

        return $replies;
    }

    private function renderIntent(string $slug, BotSession $session): array
    {
        $intent = BotIntent::where('intent', $slug)->where('active', true)->first();
        if ($intent === null) {
            return [];
        }

        $text = $this->substituteVariables($intent->jawaban_template, $session);
        return [[
            'text'  => $text,
            'media' => $intent->mediaList(),
        ]];
    }

    private function substituteVariables(string $template, BotSession $session): string
    {
        $vars = [
            '[NAMA]'           => $session->getData('nama', 'kak'),
            '[DOMISILI]'       => $session->getData('domisili', ''),
            '[USIA_BB]'        => $session->getData('usia_bb', ''),
            '[ALAMAT_KLINIK]'  => config('sunatbot.alamat_klinik', 'Klinik Jati Elok – SunatBoy. Komp. Bumi Jati Elok Blok A1 No. 4-5. Jl. Raya Legok–Parung Panjang Km.3, Malangnengah, Pagedangan, Tangerang, Banten 15330'),
            '[LINK_MAPS]'      => config('sunatbot.link_maps', 'https://maps.app.goo.gl/WDWMvex5F9YpgPaE9'),
            '[NOMOR_RONA]'     => config('sunatbot.nomor_rona', '0895-3692-69190'),
        ];
        return strtr($template, $vars);
    }
}
