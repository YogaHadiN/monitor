<?php

use App\Models\BotIntent;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed the mau_booking_jadwal intent. Engine special-cases this slug
 * (and SUNATBOT_BOOKING_KEYWORDS as a deterministic safety net) to
 * escalate to a human admin — the bot has no scheduling/payment scope,
 * so once the customer signals they want to book, hand off.
 */
return new class extends Migration
{
    public function up(): void
    {
        BotIntent::firstOrCreate(
            ['intent' => 'mau_booking_jadwal'],
            [
                'keywords'          => 'booking, daftar sunat, mau daftar, jadwalkan, dijadwalkan, ambil jadwal, mau booking, saya mau booking, ingin booking, booking sunat, ingin daftar, mau diatur jadwal',
                'pertanyaan_contoh' => "Saya mau booking sunat. Kapan bisa?\nMau daftar sunat dong kak.\nJadwalkan saya untuk minggu depan.",
                'jawaban_template'  => 'Baik kak. Untuk pengaturan jadwal sunat, admin kami akan segera menghubungi.',
                'next_action'       => null,
                'media'             => null,
                'catatan'           => 'Trigger escalation ke admin manusia. Engine special-cases this slug to set requires_special_handling=true; bot punya no scope untuk jadwal & pembayaran.',
                'urutan'            => 230,
                'active'            => true,
            ]
        );
    }

    public function down(): void
    {
        BotIntent::where('intent', 'mau_booking_jadwal')->delete();
    }
};
