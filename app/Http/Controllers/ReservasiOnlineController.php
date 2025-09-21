<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Route as RouteFacade;
use App\Models\ReservasiOnline;
use App\Models\PetugasPemeriksa;
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


    public function destroy($id): JsonResponse
    {
        // Ambil reservasi + relasi yang diperlukan
        $reservasi = ReservasiOnline::with([
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
        $message .= "Telah dibatalkan";

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

        return response()->json([
            'ok'       => true,
            'message'  => 'Reservasi dibatalkan.',
            'redirect' => url('/'),
        ]);
    }
}

