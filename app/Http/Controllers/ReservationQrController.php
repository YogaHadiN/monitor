<?php

// app/Http/Controllers/ReservationQrController.php
namespace App\Http\Controllers;

use App\Models\ReservasiOnline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ReservationQrController extends Controller
{
    /** Tampilkan <img> dari file QR (disk publik) */
    public function view(Request $request, ReservasiOnline $reservasi)
    {
        // ganti sesuai kolom kamu:
        $path = $reservasi->qr_path ?? $reservasi->qr_filename ?? null;
        if (!$path) abort(Response::HTTP_NOT_FOUND, 'QR belum tersedia.');

        // Sesuaikan disk (default 'public'). Jalankan: php artisan storage:link
        $disk  = config('filesystems.default', 'public');
        $qrUrl = Storage::disk($disk)->url($path);

        // Data pendukung (opsional)
        $pasienNama  = $reservasi->pasien->nama ?? ($reservasi->nama ?? '—');
        $dokterNama  = (method_exists($reservasi, 'staf') && $reservasi->staf) ? $reservasi->staf->nama : ($reservasi->nama_dokter ?? $reservasi->dokter_nama ?? '—');
        $jm          = $reservasi->jam_mulai;
        $jamMulaiStr = $jm instanceof \Carbon\Carbon ? $jm->format('H:i') : (is_string($jm) ? date('H:i', strtotime($jm)) : '—');

        return view('schedulled_reservations.qr-view', compact('reservasi','qrUrl','pasienNama','dokterNama','jamMulaiStr'));
    }

    /** Stream file QR (untuk disk private) */
    public function image(ReservasiOnline $reservasi)
    {
        $path = $reservasi->qr_path ?? $reservasi->qr_filename ?? null;
        if (!$path) abort(404);

        $disk = config('filesystems.default', 'local'); // ganti sesuai disk private kamu
        if (!Storage::disk($disk)->exists($path)) abort(404);

        $mime   = Storage::disk($disk)->mimeType($path) ?? 'image/png';
        $stream = Storage::disk($disk)->readStream($path);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
