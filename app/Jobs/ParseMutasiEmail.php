<?php

namespace App\Jobs;

use App\Models\MutasiInboundEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Stub parsing email mutasi bank yang sudah didaratkan ke S3 oleh
 * Lambda (AWS SES inbound). Job hanya:
 *   1. Tarik raw email dari S3 berdasarkan record MutasiInboundEmail.
 *   2. Deteksi tipe lampiran (CSV / XLSX / PDF / HTML body).
 *   3. Sediakan TODO yang jelas untuk ekstraksi mutasi sebenarnya.
 *
 * Pipeline matcher (mencocokkan baris mutasi dengan invoice / asuransi /
 * BPJS kapitasi) sudah ada di atika:
 *   /Users/yogahadinugroho/Sites/atika/app/Console/Commands/cekMutasi19Terakhir.php
 * — atika & monitor berbagi DB jatielok, jadi rencana akhir: job ini
 * menulis baris mentah ke tabel `rekenings` (atau tabel staging baru),
 * lalu atika menjalankan `cek:mutasi` di cron untuk match. Logika parser
 * spesifik (mapping kolom Kopra / PhpSpreadsheet untuk .xls) belum
 * difinalisasi — diisi di cabang masing-masing di handle().
 */
class ParseMutasiEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry policy — eksternal S3 bisa intermiten. */
    public int $tries = 5;
    public $backoff = [30, 60, 120, 300, 600];
    public int $timeout = 120;

    public int $emailId;

    public function __construct(int $emailId)
    {
        $this->emailId = $emailId;
        // Connection & queue dipisah dari default (lihat config/mutasi.php).
        // Properti $connection/$queue diwariskan dari trait Queueable, jadi
        // cukup di-set, tidak perlu dideklarasikan ulang.
        $this->onConnection((string) config('mutasi.queue.connection', 'redis'));
        $this->onQueue((string) config('mutasi.queue.name', 'mutasi'));
    }

    public function handle(): void
    {
        $email = MutasiInboundEmail::find($this->emailId);
        if ($email === null) {
            Log::warning('ParseMutasiEmail: record tidak ditemukan', ['id' => $this->emailId]);
            return;
        }

        $email->status = 'processing';
        $email->save();

        Log::info('ParseMutasiEmail: start', [
            'id'         => $email->id,
            'message_id' => $email->message_id,
            's3_key'     => $email->s3_key,
        ]);

        $rawEmail = $this->fetchRawEmailFromS3($email);
        $branch   = $this->detectBranch($rawEmail);

        Log::info('ParseMutasiEmail: detected branch', [
            'id'     => $email->id,
            'branch' => $branch,
        ]);

        // -----------------------------------------------------------------
        // STUB PARSING — diisi belakangan setelah format Kopra final.
        // Setiap cabang harus menghasilkan kumpulan baris dengan minimal:
        //   tanggal_transaksi, keterangan, debit, kredit, saldo,
        //   referensi_bank (kalau ada), no_rekening tujuan
        // dan dipersist ke tabel rekenings/staging yang dibaca oleh
        // pipeline matcher di atika:
        //   ../atika/app/Console/Commands/cekMutasi19Terakhir.php
        // Selesai parsing → matcher akan dijalankan via cron atika
        // (atau dispatch event lintas-app bila perlu).
        // -----------------------------------------------------------------
        switch ($branch) {
            case 'csv':
                // TODO: parse CSV attachment.
                break;

            case 'xlsx':
            case 'xls':
                // TODO: pakai PhpSpreadsheet (sudah terpasang di atika
                // lewat KirimLaporanEditTransaksi & RekeningController);
                // kemungkinan butuh di-install juga di monitor:
                //   composer require phpoffice/phpspreadsheet
                break;

            case 'pdf':
                // TODO: extract teks PDF (smalot/pdfparser).
                break;

            case 'html':
            default:
                // TODO: parse body HTML email Kopra (tabel mutasi inline).
                break;
        }

        // -----------------------------------------------------------------
        // HOOK INTEGRITAS — TODO: validasi saldo berjalan. Bila inkonsisten
        // → throw, biar masuk failed() dan bisa diaudit.
        // -----------------------------------------------------------------

        $email->status       = 'parsed';
        $email->processed_at = now();
        $email->error        = null;
        $email->save();

        Log::info('ParseMutasiEmail: success', [
            'id'         => $email->id,
            'message_id' => $email->message_id,
        ]);
    }

    /**
     * Ambil raw email dari S3. Bucket disimpan di record untuk audit;
     * kalau berbeda dari bucket yang dikonfigurasi pada disk, throw
     * supaya tidak diam-diam ambil dari bucket lain.
     */
    protected function fetchRawEmailFromS3(MutasiInboundEmail $email): string
    {
        $disk          = (string) config('mutasi.s3_disk', 's3');
        $configuredBkt = (string) config('filesystems.disks.' . $disk . '.bucket', '');

        if ($configuredBkt !== '' && $email->s3_bucket !== $configuredBkt) {
            throw new \RuntimeException(sprintf(
                'Mismatch bucket: payload "%s" vs disk "%s" -> "%s". Sesuaikan MUTASI_S3_DISK / AWS_BUCKET.',
                $email->s3_bucket,
                $disk,
                $configuredBkt
            ));
        }

        $raw = Storage::disk($disk)->get($email->s3_key);
        if ($raw === null) {
            throw new \RuntimeException(sprintf(
                'Raw email tidak ditemukan di S3: bucket=%s key=%s',
                $email->s3_bucket,
                $email->s3_key
            ));
        }
        return $raw;
    }

    /**
     * Deteksi kasar tipe konten utama email. Sengaja sederhana —
     * pengembangan parser MIME yang benar (mis. zbateson/mail-mime-parser)
     * diserahkan ke implementasi cabang masing-masing nanti.
     */
    protected function detectBranch(string $raw): string
    {
        $lower = strtolower($raw);
        if (str_contains($lower, 'content-type: text/csv') ||
            preg_match('/filename="[^"]+\.csv"/i', $raw)) {
            return 'csv';
        }
        if (preg_match('/filename="[^"]+\.xlsx"/i', $raw) ||
            str_contains($lower, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
            return 'xlsx';
        }
        if (preg_match('/filename="[^"]+\.xls"/i', $raw) ||
            str_contains($lower, 'application/vnd.ms-excel')) {
            return 'xls';
        }
        if (str_contains($lower, 'content-type: application/pdf') ||
            preg_match('/filename="[^"]+\.pdf"/i', $raw)) {
            return 'pdf';
        }
        return 'html';
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ParseMutasiEmail: failed', [
            'emailId' => $this->emailId,
            'error'   => $e->getMessage(),
        ]);

        $email = MutasiInboundEmail::find($this->emailId);
        if ($email !== null) {
            $email->status = 'failed';
            $email->error  = mb_substr($e->getMessage(), 0, 65000);
            $email->save();
        }
    }
}
