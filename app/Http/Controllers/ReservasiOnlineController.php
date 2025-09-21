<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Route as RouteFacade;
use App\Models\ReservasiOnline;
use App\Models\PetugasPemeriksa;
use Carbon\Carbon;

// endroid/qr-code
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;

class ReservasiOnlineController extends Controller
{
    public function qr($reservasi_online_id)
    {
        $reservasi_online_id = decrypt_string( $reservasi_online_id );
        // Ambil data reservasi
        $reservasi = ReservasiOnline::findOrFail($reservasi_online_id);

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
            'reservasi',
            'jam_reservasi_dihapus',
            'pasienNama',
            'dokterNama',
            'jamMulaiStr',
            'qrUrl'
        ));
    }

    public function checkin($id)
    {
        // TODO: logic check-in
        return "Check-in reservasi #{$id}";
    }

    public function destroy($id)
    {
        $reservasi = ReservasiOnline::findOrFail($id);

        // Hapus QR di S3 jika ada
        if ($reservasi->qr_code_path_s3) {
            Storage::disk('s3')->delete($reservasi->qr_code_path_s3);
        }

        $reservasi->delete();

        // Siapkan informasi tambahan
        $pasienNama  = $reservasi->nama ?? optional($reservasi->pasien)->nama;
        $dokterNama  = optional($reservasi->staf)->nama_dengan_gelar;

        $message = 'Reservasi Anda ';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Nama : ' . $pasienNama;
        $message .= PHP_EOL;
        $message .= 'Dokter : ' . $dokterNama;
        $message .= PHP_EOL;
        $message .= 'Pada hari ini';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'Telah dibatalkan';

        $wa->sendSingle($reservasi->no_telp, $message)

        return response()->json([
            'ok'       => true,
            'message'  => 'Reservasi dibatalkan.',
            'redirect' => url('/'), // ubah jika ingin redirect ke halaman lain
        ]);
    }
}

