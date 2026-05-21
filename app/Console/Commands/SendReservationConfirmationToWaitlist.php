<?php

namespace App\Console\Commands;

use App\Http\Controllers\WablasController;
use App\Models\PetugasPemeriksa;
use App\Models\SchedulledReservation;
use App\Models\WhatsappBot;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;

/**
 * Mirror dari command yang sama di atika. Dipanggil oleh
 * SchedulledReservation::deleted listener (Artisan::call) saat
 * row schedulled_booking=1 dihapus dari sisi monitor (bot WA /
 * web form). Jika ada slot kosong + waitlist queue, kirim WA
 * inquiry ke pasien waitlist tertua per petugas_pemeriksa lalu
 * set waitlist_reservation_inquiry_sent=1.
 *
 * Beda dengan versi atika: kirim sync via WablasController->sendSingle
 * (tanpa SendWhatsappMessageJob) supaya tidak butuh queue worker di
 * monitor.
 */
class SendReservationConfirmationToWaitlist extends Command
{
    protected $signature   = 'reservasi:send-waitlist-inquiry';
    protected $description = 'Kirim WA inquiry ke pasien waitlist saat masih ada slot untuk petugas pemeriksa hari ini';

    public function handle(): int
    {
        Log::info($this->description);
        $nowJkt = Carbon::now('Asia/Jakarta');
        $end    = $nowJkt->copy()->addMinutes(30);

        $query = PetugasPemeriksa::query()
            ->where('schedulled_booking_allowed', 1)
            ->whereDate('tanggal', $nowJkt->toDateString())
            ->whereTime('jam_mulai_default', '>=', $end->format('H:i:s'))
            ->with(['schedulled_reservations' => function ($q) {
                $q->where('waitlist_flag', 1)
                  ->where('waitlist_reservation_inquiry_sent', 0)
                  ->orderBy('created_at', 'asc');
            }, 'staf'])
            ->orderBy('jam_mulai_default');

        Log::info('SQL', ['sql' => $query->toRawSql()]);
        $petugasList = $query->get();

        $wablas = new WablasController();

        foreach ($petugasList as $petugas) {
            if (!$petugas->slot_pendaftaran_available) {
                continue;
            }

            foreach ($petugas->schedulled_reservations as $reservasi) {
                if (!$reservasi->waitlist_flag) {
                    continue;
                }

                // Update atomik: set flag → mencegah duplikasi bila
                // command jalan paralel (mis. dari listener + scheduler).
                $affected = SchedulledReservation::where('id', $reservasi->id)
                    ->where('waitlist_reservation_inquiry_sent', 0)
                    ->update(['waitlist_reservation_inquiry_sent' => 1]);

                if ($affected === 0) {
                    continue;
                }

                $namaDokter = optional($reservasi->staf)->nama_dengan_gelar
                    ?? optional($petugas->staf)->nama_dengan_gelar
                    ?? 'Dokter';

                $pesan = sprintf(
                    "Saat ini ada slot kosong untuk pemeriksa yang Anda tunggu.\n".
                    "Dokter: *%s* jam pelayanan (%s - %s)\n".
                    "Apakah Kakak berminat untuk mengambil slot ini?\n\n".
                    "Balas *ya* untuk konfirmasi, atau abaikan pesan ini.",
                    $namaDokter,
                    Carbon::parse($petugas->jam_mulai)->format('H:i'),
                    Carbon::parse($petugas->jam_akhir)->format('H:i')
                );

                try {
                    $wablas->sendSingle($reservasi->no_telp, $pesan);
                } catch (\Throwable $e) {
                    Log::error('SendReservationConfirmationToWaitlist: sendSingle failed', [
                        'no_telp' => $reservasi->no_telp,
                        'error'   => $e->getMessage(),
                    ]);
                }

                WhatsappBot::create([
                    'whatsapp_bot_service_id' => 15,
                    'no_telp'                 => $reservasi->no_telp,
                    'staf_id'                 => $reservasi->staf_id,
                    'prevent_repetition'      => 0,
                ]);

                $this->info('Inquiry dikirim ke waitlist: '.$reservasi->no_telp.' ('.$reservasi->nama.')');
                break; // satu inquiry per petugas, baris berikutnya menunggu konfirmasi/timeout
            }
        }

        return self::SUCCESS;
    }
}
