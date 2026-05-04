<?php

namespace App\Services\Bot;

use App\Models\BotSession;

class SunatBotFlow
{
    public function __construct(
        private AiParserService $ai,
        private SunatQnaService $qna,
    ) {}

    private const SCHEDULING_CTA = 'Berkenan untuk kami jadwalkan minggu ini atau minggu depan kak?';

    public function asset(string $filename): string
    {
        return rtrim(config('app.url'), '/') . '/bot-assets/sunat/' . $filename;
    }

    public function initialReplies(): array
    {
        return [
            [
                'image_url' => $this->asset('kolase-keluarga.jpg'),
                'text'      => "Halo kak 🙏 Terima kasih sudah tertarik dengan *SunatBoy*.",
            ],
            [
                'text' => "Kami berkomitmen menciptakan pengalaman sunat yang tak terlupakan. Siap jadi anak hebat, bersama SunatBoy 💪",
            ],
            [
                'text' => "Untuk biaya sunat tergantung usia dan berat badan anak kak.",
            ],
            [
                'text' => "Kalau boleh tau, dengan kakak siapa ya?",
            ],
        ];
    }

    public function handle(BotSession $session, ?string $userMessage): array
    {
        $step = $session->current_step;

        switch ($step) {
            case 'greeting':
                return $this->result($this->initialReplies(), 'ask_name', false);

            case 'ask_name':
                $parsed = $this->ai->extract($userMessage ?? '', [
                    'nama' => 'nama depan orang tua / pengirim pesan (string, title case)',
                ]);
                $session->setData('nama', $parsed['nama'] ?: trim($userMessage ?? ''));
                $nama = $session->getData('nama', 'kak');
                return $this->result([
                    ['text' => "Baik kak {$nama} 🙏"],
                    ['text' => "Bisa dibantu infokan usia dan berat badan anaknya kak?"],
                ], 'ask_age_weight', false);

            case 'ask_age_weight':
                $parsed = $this->ai->extract($userMessage ?? '', [
                    'umur'        => 'usia anak dalam tahun (integer)',
                    'berat_badan' => 'berat badan anak dalam kilogram (angka)',
                ]);
                if ( $parsed['umur'] )        $session->setData('umur', (int) $parsed['umur']);
                if ( $parsed['berat_badan'] ) $session->setData('berat_badan', (float) $parsed['berat_badan']);

                if ( !$session->getData('umur') || !$session->getData('berat_badan') ) {
                    return $this->result([
                        ['text' => "Maaf kak, boleh diinfokan ulang usia (dalam tahun) dan berat badan anak (dalam kg) ya?"],
                        ['text' => "Contoh: *usia 6 tahun, berat 20 kg*"],
                    ], 'ask_age_weight', false);
                }

                return $this->result([
                    ['text' => "Baik kak."],
                    ['text' => "Untuk domisilinya dari *Tangerang* atau *Jakarta* kak?"],
                ], 'ask_domicile', false);

            case 'ask_domicile':
                $parsed = $this->ai->extract($userMessage ?? '', [
                    'domisili' => 'nama kota/kecamatan asal pengirim (string)',
                ]);
                $session->setData('domisili', $parsed['domisili'] ?: trim($userMessage ?? ''));
                return $this->result([
                    ['text' => "Baik kak, noted domisilinya ya 🙏"],
                    ['text' => "Apakah ada keluhan tertentu yang menyebabkan ingin dikhitan?"],
                ], 'ask_complaint', false);

            case 'ask_complaint':
                $session->setData('keluhan', trim($userMessage ?? ''));
                return $this->result([
                    ['text' => "Baik kak 🙏"],
                    ['text' => "Apakah anak memiliki kondisi kesehatan tertentu?"],
                    ['text' => "Misalnya ada riwayat kelainan pembekuan darah, jantung, atau termasuk dalam spektrum kebutuhan khusus / autisme?"],
                ], 'ask_health', false);

            case 'ask_health':
                $session->setData('riwayat_kesehatan', trim($userMessage ?? ''));
                return $this->result([
                    ['text' => "Baik kak 🙏"],
                    ['text' => "Saya ijin menanyakan beberapa hal terlebih dahulu ya supaya kami bisa memahami kebutuhan kakak."],
                    ['text' => "Sebelumnya, apakah kakak sudah mengetahui metode khitan dari kami, atau perlu kami jelaskan dulu?"],
                ], 'ask_method_known', false);

            case 'ask_method_known':
                $text = strtolower(trim($userMessage ?? ''));
                $sudahTahu = preg_match('/\b(sudah|udah|tau|tahu|paham|ngerti)\b/iu', $text)
                          && !preg_match('/\b(belum|blm|tidak|ga|gak|nggak)\b/iu', $text);
                $session->setData('sudah_tahu_metode', $sudahTahu);
                return $this->result([], 'edu_teknoklamp', true);

            case 'edu_teknoklamp':
                return $this->result([
                    [
                        'image_url' => $this->asset('teknoklamp.jpg'),
                        'text'      => "Kami menggunakan metode *Teknoklamp* kak.",
                    ],
                    ['text' => "• Menggunakan alat cetak sehingga hasil lebih rapi"],
                    ['text' => "• Tanpa alat menempel"],
                    ['text' => "• Tanpa dibalut"],
                    ['text' => "• Pendarahan minimal"],
                ], 'edu_bius', true);

            case 'edu_bius':
                return $this->result([
                    [
                        'image_url' => $this->asset('half-banner-bius.jpg'),
                        'text'      => "Kelebihan SunatBoy lainnya adalah *biusnya yang nyaman*.",
                    ],
                    ['text' => "Sebagian besar anak bahkan tidak menyadari saat proses bius dilakukan."],
                    [
                        'image_url' => $this->asset('kesaksian-bius.jpg'),
                        'text'      => "Berikut video kesaksian dari beberapa anak yang pernah kami tangani 🙏",
                    ],
                ], 'edu_room', true);

            case 'edu_room':
                return $this->result([
                    [
                        'image_url' => $this->asset('ruangan.jpg'),
                        'text'      => "Kami menggunakan ruangan yang nyaman dan ramah anak 🧸",
                    ],
                ], 'edu_content', true);

            case 'edu_content':
                return $this->result([
                    [
                        'image_url' => $this->asset('contoh-konten.jpg'),
                        'text'      => "Kami juga buatkan konten pengalaman anak selama sunat di SunatBoy.",
                    ],
                    ['text' => "Kakak bisa share di media sosial sebagai kenangan sunat yang menyenangkan 🎬"],
                ], 'edu_review', true);

            case 'edu_review':
                return $this->result([
                    [
                        'image_url' => $this->asset('review-carousel.jpg'),
                        'text'      => "Berikut beberapa kesaksian dari klien-klien kami setelah sunat di SunatBoy ⭐",
                    ],
                ], 'edu_gift', true);

            case 'edu_gift':
                return $this->result([
                    [
                        'image_url' => $this->asset('hadiah.jpg'),
                        'text'      => "Banyak hadiahnya kak 🎁",
                    ],
                    ['text' => "Kami memberikan *remote control*, kaos keren SunatBoy, dan lainnya."],
                ], 'edu_postcare', true);

            case 'edu_postcare':
                return $this->result([
                    [
                        'image_url' => $this->asset('obat-celana.jpg'),
                        'text'      => "Termasuk *celana sunat*, obat, dan pengawasan dokter kami sampai sembuh.",
                    ],
                    ['text' => "Kakak tidak perlu datang ke klinik, dokter akan mengawasi lewat WhatsApp 💬"],
                ], 'offer_price', true);

            case 'offer_price':
                return $this->result([
                    ['text' => "Kami memberikan harga *Rp 2.500.000* untuk semua benefit tersebut kak 🙏"],
                    ['text' => "Kakak berkenan untuk kami jadwalkan minggu ini atau minggu depan?"],
                ], 'ask_schedule', false);

            case 'ask_schedule':
                $session->setData('jadwal_keinginan', trim($userMessage ?? ''));
                return $this->result([
                    ['text' => "Siap kak, terima kasih 🙏"],
                    ['text' => "Tim kami akan bantu koordinasikan jadwalnya."],
                    ['text' => "Admin kami akan membalas segera untuk konfirmasi detail."],
                    ['text' => "Ketik *akhiri* kalau sudah cukup ya kak."],
                ], 'done', false);

            case 'done':
            default:
                return $this->result([], 'done', false);
        }
    }

    public function handleReactive(BotSession $session, string $userMessage): ?array
    {
        if ( !$this->looksLikeQuestion($userMessage) ) {
            return null;
        }

        $qna = $this->qna->match($userMessage);
        if ( $qna === null ) {
            return null;
        }

        $replies = [['text' => $qna->answer]];

        if ( !$this->qna->isClosing($qna) && !$session->getData('scheduling_cta_sent') ) {
            $replies[] = ['text' => self::SCHEDULING_CTA];
            $session->setData('scheduling_cta_sent', true);
        }

        return $replies;
    }

    private function looksLikeQuestion(string $msg): bool
    {
        if (str_contains($msg, '?')) return true;
        $words = preg_split('/\s+/u', trim($msg)) ?: [];
        if (count($words) >= 5) return true;
        return (bool) preg_match(
            '/\b(apa|apakah|kapan|berapa|bagaimana|gimana|kenapa|mengapa|dimana|di\s+mana|bisa|boleh|ada|mau\s+tanya|tanya|info|metode|paket|harga|biaya|tarif|jadwal|booking|daftar)\b/iu',
            $msg
        );
    }

    private function result(array $replies, string $nextStep, bool $autoContinue): array
    {
        return [
            'replies'       => $replies,
            'next_step'     => $nextStep,
            'auto_continue' => $autoContinue,
        ];
    }
}
