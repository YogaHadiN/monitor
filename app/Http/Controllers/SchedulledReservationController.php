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
        // Ambil data reservasi
        $schedulled_reservation = SchedulledReservation::findOrFail($schedulled_reservation_id);

        // Siapkan informasi tambahan
        $pasienNama  = $reservasi->nama ?? optional($reservasi->pasien)->nama;
        $dokterNama  = optional($reservasi->staf)->nama_dengan_gelar;

        $petugas_pemeriksa = PetugasPemeriksa::whereDate('tanggal', Carbon::now())
                                            ->where('staf_id', $reservasi->staf_id)
                                            ->where('tipe_konsultasi_id', $reservasi->tipe_konsultasi_id)
                                            ->where('ruangan_id', $reservasi->ruangan_id)
                                            ->first();

        if (!is_null( $petugas_pemeriksa )) {
            $jamMulaiStr = Carbon::parse($petugas_pemeriksa->jam_mulai_default)
                            ->timezone('Asia/Jakarta')
                            ->format('H:i');
        } else {
            return 'petugas tidak ditemukan';
        }


        $jam_reservasi_dihapus = Carbon::parse( $jamMulaiStr )
                                            ->subMinutes(15)
                                            ->timezone('Asia/Jakarta')
                                            ->format('H:i');

        // === Pilihan cara tampilkan QR ===
        // a) Kalau sudah ada path QR tersimpan di DB / Storage:
        $qrUrl = $reservasi->qrcode
            ? Storage::disk('s3')->url($reservasi->qrcode)
            : null;

        // b) Atau generate langsung dari data string (ENDROID)
        if (!$qrUrl) {
            return 'Qr Code tidak ditemukan';
        }

        return view('schedulled_reservations.qr-view', compact(
            'schedulled_reservation',
            'jam_reservasi_dihapus',
            'pasienNama',
            'dokterNama',
            'jamMulaiStr',
            'qrUrl'
        ));
    }
}
