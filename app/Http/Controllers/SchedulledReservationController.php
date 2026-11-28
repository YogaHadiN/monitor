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
            // dr. Yoga 2026-11-28: waitlist belum punya QR → tampilkan penjelasan, bukan "Qr Code tidak ditemukan"
            $isWaitlist = (int) ($schedulled_reservation->waitlist_flag ?? 0) === 1;
            $judul = $isWaitlist ? 'Masih dalam WAITLIST' : 'QR code belum tersedia';
            $isi = $isWaitlist
                ? 'Pendaftaran <b>' . e($pasienNama ?: 'Pasien') . '</b> ke <b>' . e($dokterNama ?: 'dokter') . '</b> masih di <b>waitlist</b> karena kuota sudah penuh (kode waitlist: ' . (int) $schedulled_reservation->id . ').<br><br>'
                  . 'QR code baru dibuat kalau ada slot kosong dan Kakak membalas <b>ya</b> saat kami hubungi. '
                  . 'Waitlist <b>belum bisa</b> dipakai untuk ambil antrian di klinik.'
                : 'QR code untuk pendaftaran ini belum tersedia. Silakan hubungi petugas Klinik Jati Elok.';
            return response('<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<title>' . $judul . '</title></head><body style="font-family:sans-serif;background:#f3f6f5;margin:0;padding:24px">'
                . '<div style="max-width:480px;margin:40px auto;background:#fff;border-radius:14px;padding:24px;box-shadow:0 2px 10px rgba(0,0,0,.08)">'
                . '<h2 style="color:#3BAE85;margin-top:0">Klinik Jati Elok</h2><h3>' . $judul . '</h3><p style="font-size:16px;line-height:1.5">' . $isi . '</p>'
                . '</div></body></html>');
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
        $reservasi = SchedulledReservation::with([
            'pasien:id,nama',
            'staf:id,nama,titel_id,tenant_id',
            'staf.titel:id,singkatan',
        ])->findOrFail($id);

        $phone      = $reservasi->no_telp;
        $pasienNama = $reservasi->nama ?? optional($reservasi->pasien)->nama ?? 'Pasien';

        $dokterNamaObj = $reservasi->staf;
        $dokterNama = optional($dokterNamaObj)->nama_dengan_gelar
            ?? optional($dokterNamaObj)->nama
            ?? 'Dokter';

        $message  = "Reservasi Terjadwal Anda\n\n";
        $message .= "Nama : {$pasienNama}\n";
        $message .= "Dokter : {$dokterNama}\n\n";
        $message .= "Telah Anda batalkan secara mandiri.";

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

        // Marker deleted_via supaya qr-view-deleted bisa deteksi self-cancel
        if (\Schema::hasColumn($reservasi->getTable(), 'deleted_via')) {
            $reservasi->deleted_via = 'hapus_schedulled_reservation:web_qr';
            $reservasi->save();
        }

        $reservasi->delete();

        if (!empty($phone)) {
            try {
                (new WablasController)->sendSingle($phone, $message);
            } catch (\Throwable $e) {
                \Log::warning('Gagal kirim WA cancel schedulled_reservation', [
                    'reservasi_id' => $id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok'       => true,
            'message'  => 'Pendaftaran terjadwal berhasil dibatalkan.',
            'redirect' => url('/schedulled_booking/qr/' . encrypt_string($id)),
        ]);
    }
}
