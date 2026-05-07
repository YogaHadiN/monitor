<?php

use App\Models\BotIntent;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed bot_intents rows for the Sunatboy harga flow v2 (proposal §3
 * Stage 2). Uses firstOrCreate keyed on the intent slug — existing rows
 * (e.g. tanya_domisili that may have been customised in the dashboard)
 * are left untouched. Only brand-new slugs introduced by the v2 flow
 * are inserted.
 *
 * Bot-prompt intents (rendered by the engine, not classified by AI)
 * have keywords=null so candidateSlugs() in SunatBotEngine excludes them
 * from the classifier candidate pool.
 *
 * Media references point to files already uploaded to public/bot_media/
 * on atika prod (URLs in the proposal v2 PDF). If a file is missing,
 * SunatBotReplyDispatcher logs SUNAT_BOT_MEDIA_FAIL and falls back to
 * the text bubble — non-fatal.
 */
return new class extends Migration
{
    /**
     * Slugs introduced by this migration. down() removes only these so
     * we don't accidentally drop intents the dashboard added later.
     */
    private array $newSlugs = [
        'tanya_nama_orang_tua',
        'tanya_usia_bb_konfirmasi',
        'tanya_indikasi',
        'tanya_sudah_tahu_metode',
        'tanya_pengalaman',
        'edukasi_kelebihan',
        'tanya_setuju_dokumentasi',
        'contoh_dokumentasi',
        'quote_harga_paket',
        'tanya_pertanyaan_lanjutan',
        'validasi_ulang_usia_bb',
    ];

    public function up(): void
    {
        $rows = [
            // Step 2.1 — Pembuka & tanya nama
            'tanya_nama_orang_tua' => [
                'jawaban_template' => 'Untuk biaya sunat tergantung usia dan berat badan kak. Kalo boleh tau dengan kakak siapa?',
                'urutan'           => 110,
            ],

            // Step 2.3 — Konfirmasi usia/BB (skip kalau 2.1 sudah)
            'tanya_usia_bb_konfirmasi' => [
                'jawaban_template' => 'Bisa dibantu infokan usia dan berat badan anaknya kak?',
                'urutan'           => 130,
            ],

            // Step 2.4 — Indikasi/keluhan
            'tanya_indikasi' => [
                'jawaban_template' => 'Apakah ada keluhan tertentu yang menyebabkan ingin di-khitan?',
                'urutan'           => 140,
            ],

            // Step 2.6 — Edukasi metode (conditional)
            'tanya_sudah_tahu_metode' => [
                'jawaban_template' => 'Sebelumnya apakah sudah mengetahui metode khitan dari kami atau perlu kami jelaskan terlebih dahulu?',
                'urutan'           => 160,
            ],

            // Step 2.7 — Pengalaman sunat sebelumnya
            'tanya_pengalaman' => [
                'jawaban_template' => 'Kak {{nama}}, anaknya pernah trauma tindakan medis atau belum pernah ada masalah sebelumnya?',
                'urutan'           => 170,
            ],

            // Step 2.8 — Video testimonial + penjelasan kelebihan
            'edukasi_kelebihan' => [
                'jawaban_template' => "Nah, kelebihan dari Sunatboy adalah biusnya yang nyaman kak. Sebagian besar anak bahkan tidak menyadari saat proses bius dilakukan. Proses bius yang nyaman sangat penting supaya menjaga proses sunat tetap menyenangkan untuk anak.\n\nBerikut adalah video kesaksian dari beberapa client yang telah kami tangani.",
                'media'            => 'VIDEO-2026-04-27-14-00-08_6501568e.mp4',
                'urutan'           => 180,
            ],

            // Step 2.9 — Penawaran dokumentasi
            'tanya_setuju_dokumentasi' => [
                'jawaban_template' => 'Selama proses sunat apakah kakak berkenan untuk kami buatkan dokumentasi? Tidak ada biaya tambahan untuk pembuatan dokumentasi.',
                'urutan'           => 190,
            ],

            // Step 2.9 — Contoh dokumentasi (conditional jika setuju)
            'contoh_dokumentasi' => [
                'jawaban_template' => 'Kami buatkan konten pengalaman kakak selama sunat di Sunatboy sebagai kenang-kenangan.',
                'media'            => 'VIDEO-2026-04-01-19-50-31_4756eae6.mp4',
                'urutan'           => 195,
            ],

            // Step 2.10 — Penawaran harga + paket
            'quote_harga_paket' => [
                'jawaban_template' => "Paket sunat di Sunatboy sudah termasuk:\n✓ Celana sunat\n✓ Obat-obatan\n✓ Pengawasan dokter sampai sembuh\n\nKakak tidak perlu datang ke klinik untuk kontrol — dokter kami akan mengawasi pemulihan lewat WhatsApp.\n\nHarga: Rp 2.500.000 (all-in untuk semua benefit di atas)",
                'media'            => 'PHOTO-2026-04-28-07-23-50_737cc261.jpg',
                'urutan'           => 200,
            ],

            // Step 2.10 — Closing pertanyaan lanjutan
            'tanya_pertanyaan_lanjutan' => [
                'jawaban_template' => 'Apakah ada yang belum jelas kak? Ada lagi yang ingin ditanyakan?',
                'urutan'           => 210,
            ],

            // Validation re-ask untuk usia/BB out-of-range
            'validasi_ulang_usia_bb' => [
                'jawaban_template' => 'Maaf kak, boleh diulang usia dan berat badan anaknya?',
                'urutan'           => 220,
            ],
        ];

        foreach ($rows as $slug => $attrs) {
            BotIntent::firstOrCreate(
                ['intent' => $slug],
                array_merge([
                    'keywords'          => null,
                    'pertanyaan_contoh' => null,
                    'media'             => null,
                    'next_action'       => null,
                    'catatan'           => 'Auto-seeded via harga flow v2 migration. Edit via dashboard kalau perlu.',
                    'active'            => true,
                ], $attrs)
            );
        }
    }

    public function down(): void
    {
        BotIntent::whereIn('intent', $this->newSlugs)->delete();
    }
};
