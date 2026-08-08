<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Route as RouteFacade;
use App\Models\ReservasiOnline;
use App\Models\PetugasPemeriksa;
use App\Models\SchedulledReservation;
use Carbon\Carbon;

use Illuminate\Support\Facades\Storage;
use App\Jobs\SendWhatsappMessageJob;
use App\Http\Controllers\WablasController;
use Illuminate\Http\JsonResponse;

// endroid/qr-code
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;

class SchedulledReservationController extends Controller
{
    public function qr($schedulled_reservation_id)
    {
        $schedulled_reservation_id = decrypt_string( $schedulled_reservation_id );

        // Ambil data reservasi — include trashed supaya kalau row sudah
        // soft-deleted (auto-hapus 15min sebelum praktik / self-cancel),
        // customer tetap dapat state page bermakna, bukan 404.
        $schedulled_reservation = SchedulledReservation::withTrashed()->find($schedulled_reservation_id);
        if (!$schedulled_reservation) {
            abort(404, 'Reservasi tidak ditemukan');
        }

        // Kalau sudah soft-deleted → render halaman "deleted state"
        // dengan info kapan + oleh siapa + arahan daftar manual.
        if ($schedulled_reservation->trashed()) {
            $deletedVia = (string) ($schedulled_reservation->deleted_via ?? '');
            $isSystem   = str_contains($deletedVia, 'console')
                       || str_contains($deletedVia, 'HapusReservasiScheduled');
            $isSelfCancel = str_contains($deletedVia, 'hapus_schedulled_reservation')
                       || str_contains($deletedVia, 'WebRegistration');
            $deletedByLabel = $isSystem
                ? 'sistem otomatis (karena telat scan QR — batas 15 menit sebelum praktik)'
                : ($isSelfCancel
                    ? 'Anda sendiri (via halaman pendaftaran)'
                    : ($schedulled_reservation->deleted_by
                        ? 'admin klinik'
                        : 'sistem'));

            return view('schedulled_reservations.qr-view-deleted', [
                'schedulled_reservation' => $schedulled_reservation,
                'pasienNama' => $schedulled_reservation->nama ?? optional($schedulled_reservation->pasien)->nama ?? 'Pasien',
                'dokterNama' => optional($schedulled_reservation->staf)->nama_dengan_gelar ?? 'Dokter',
                'deletedByLabel' => $deletedByLabel,
                'deletedAt'      => $schedulled_reservation->deleted_at,
                'isSystem'       => $isSystem,
                'isSelfCancel'   => $isSelfCancel,
            ]);
        }

        // Siapkan informasi tambahan
        $pasienNama  = $schedulled_reservation->nama ?? optional($schedulled_reservation->pasien)->nama;
        $dokterNama  = optional($schedulled_reservation->staf)->nama_dengan_gelar;

        $petugas_pemeriksa = PetugasPemeriksa::whereDate('tanggal', Carbon::now())
                                            ->where('staf_id', $schedulled_reservation->staf_id)
                                            ->where('tipe_konsultasi_id', $schedulled_reservation->tipe_konsultasi_id)
                                            ->where('ruangan_id', $schedulled_reservation->ruangan_id)
                                            ->first();

        if (!is_null( $petugas_pemeriksa )) {
            $jamMulaiStr = Carbon::parse($petugas_pemeriksa->jam_mulai_default)
                            ->timezone('Asia/Jakarta')
                            ->format('H:i');
        } else {
            return 'petugas tidak ditemukan';
        }


        $jam_schedulled_reservation_dihapus = Carbon::parse( $jamMulaiStr )
                                            ->subMinutes(15)
                                            ->timezone('Asia/Jakarta')
                                            ->format('H:i');

        // === Pilihan cara tampilkan QR ===
        // a) Kalau sudah ada path QR tersimpan di DB / Storage:
        $qrUrl = $schedulled_reservation->qrcode
            ? Storage::disk('s3')->url($schedulled_reservation->qrcode)
            : null;

        // b) Atau generate langsung dari data string (ENDROID)
        if (!$qrUrl) {
            return 'Qr Code tidak ditemukan';
        }

        return view('schedulled_reservations.qr-view', compact(
            'schedulled_reservation',
            'jam_schedulled_reservation_dihapus',
            'pasienNama',
            'dokterNama',
            'jamMulaiStr',
            'qrUrl'
        ));
    }

    public function destroy($id): JsonResponse
    {
        // Ambil reservasi + relasi yang diperlukan
        $reservasi = SchedulledReservation::with([
            'pasien:id,nama',
            'staf:id,nama,titel_id,tenant_id',
            'staf.titel:id,singkatan',
        ])->findOrFail($id);

        // Siapkan data lebih dulu (supaya aman jika delete dilakukan)
        $phone      = $reservasi->no_telp;
        $pasienNama = $reservasi->nama ?? optional($reservasi->pasien)->nama ?? 'Pasien';

        $dokterNamaObj = $reservasi->staf;
        $dokterNama = $dokterNamaObj->nama_dengan_gelar
            ?? $dokterNamaObj->nama
            ?? 'Dokter';

        // Susun pesan
        $message  = "Reservasi Anda \n\n";
        $message .= "Nama : {$pasienNama}\n";
        $message .= "Dokter : {$dokterNama}\n";
        $message .= "Pada hari ini\n\n";
        $message .= "Telah Anda dibatalkan secara mandiri";

        // Hapus QR di S3 (jika ada) - log kalau gagal, tapi lanjutkan
        try {
            if (!empty($reservasi->qrcode)) {
                Storage::disk('s3')->delete($reservasi->qrcode);
            }
        } catch (\Throwable $e) {
            \Log::warning('Gagal menghapus QR S3', [
                'reservasi_id' => $reservasi->id,
                'path'         => $reservasi->qrcode,
                'error'        => $e->getMessage(),
            ]);
        }

        // Hapus record
        $reservasi->delete();

        // Kirim WA via job (non-blocking)
        if (!empty($phone)) {
            $wa = new WablasController;
               $wa->sendSingle($phone, $message);
        }

        $pesan = Yoga::suksesFlash('Pendaftaran Terjadwal berhasil dihapus');
        return redirect('schedulled_reservations')->withPesan($pesan);
    }
}
