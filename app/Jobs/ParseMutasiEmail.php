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
                $xlsBytes = $this->extractXlsAttachment($rawEmail);
                $parsed   = $this->parseKopraXls($xlsBytes);
                $email->parsed_meta = $parsed;
                Log::info('ParseMutasiEmail: parsed', [
                    'id'        => $email->id,
                    'meta'      => $parsed['meta'] ?? null,
                    'row_count' => count($parsed['rows'] ?? []),
                    'footer'    => $parsed['footer'] ?? null,
                ]);
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

    /**
     * Ekstrak attachment .xls/.xlsx dari raw MIME tanpa lib MIME parser
     * (struktur email Kopra konsisten: multipart/mixed dengan
     * application/vnd.ms-excel base64). Return binary bytes attachment.
     */
    protected function extractXlsAttachment(string $rawEmail): string
    {
        if (!preg_match(
            '/Content-Type:\s*application\/vnd\.(ms-excel|openxmlformats-officedocument\.spreadsheetml\.sheet)[^\r\n]*/i',
            $rawEmail, $m, PREG_OFFSET_CAPTURE
        )) {
            throw new \RuntimeException('Attachment XLS/XLSX tidak ditemukan di raw email');
        }
        $partStart = (int) $m[0][1];

        // Skip header part sampai blank line pertama.
        $bodyStart = strpos($rawEmail, "\r\n\r\n", $partStart);
        if ($bodyStart !== false) {
            $bodyStart += 4;
        } else {
            $bodyStart = strpos($rawEmail, "\n\n", $partStart);
            if ($bodyStart === false) {
                throw new \RuntimeException('Body attachment tidak ditemukan (blank line missing)');
            }
            $bodyStart += 2;
        }

        // Kumpulkan semua boundary yang muncul di header email (di atas
        // attachment) lalu cari posisi terdekat setelah bodyStart.
        $boundaries = [];
        if (preg_match_all('/boundary\s*=\s*"?([^";\r\n]+)"?/i', substr($rawEmail, 0, $partStart), $bm)) {
            foreach ($bm[1] as $b) {
                $b = trim($b);
                if ($b !== '') {
                    $boundaries[] = '--' . $b;
                }
            }
        }
        $bodyEnd = strlen($rawEmail);
        foreach (array_unique($boundaries) as $b) {
            $p = strpos($rawEmail, $b, $bodyStart);
            if ($p !== false && $p < $bodyEnd) {
                $bodyEnd = $p;
            }
        }

        $b64 = substr($rawEmail, $bodyStart, $bodyEnd - $bodyStart);
        $bin = base64_decode(preg_replace('/\s+/', '', $b64), true);
        if ($bin === false || strlen($bin) === 0) {
            throw new \RuntimeException('Decode base64 attachment gagal');
        }
        return $bin;
    }

    /**
     * Parse Account Statement Kopra Mandiri (sheet `transaction_inquiry`).
     * Layout (1-indexed):
     *   Metadata: E3=Account, E4=Period, E5=Currency, E6=Branch,
     *             E7=Opening Balance.
     *   Header transaksi (row 9): C=Posting Date, F=Remark,
     *             H=Reference No, I=Debit, K=Credit, L=Balance,
     *             M=Branch Code.
     *   Data: row 10+ sampai marker stop ("No record found" di kolom D
     *             atau "No of Debit"/"Total Amount"/"Closing Balance"
     *             di kolom C).
     *   Footer: di antara data dan akhir — angka di kolom G.
     */
    protected function parseKopraXls(string $xlsBytes): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mutasi_xls_');
        // PhpSpreadsheet butuh ekstensi .xls untuk pilih reader otomatis,
        // tapi kita sudah load Xls reader langsung jadi tidak rename.
        file_put_contents($tmp, $xlsBytes);

        try {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            $reader->setReadDataOnly(true);
            $spread = $reader->load($tmp);
            $sheet  = $spread->getSheetByName('transaction_inquiry') ?: $spread->getActiveSheet();

            $meta = [
                'account_no'      => trim((string) $sheet->getCell('E3')->getValue()),
                'period'          => trim((string) $sheet->getCell('E4')->getValue()),
                'currency'        => trim((string) $sheet->getCell('E5')->getValue()),
                'branch'          => trim((string) $sheet->getCell('E6')->getValue()),
                'opening_balance' => (float) $sheet->getCell('E7')->getValue(),
            ];

            $rows    = [];
            $footer  = [
                'no_of_debit'           => null,
                'total_amount_debited'  => null,
                'no_of_credit'          => null,
                'total_amount_credited' => null,
                'closing_balance'       => null,
            ];
            $maxRow  = $sheet->getHighestRow();
            $stopRow = $maxRow + 1;

            for ($r = 10; $r <= $maxRow; $r++) {
                $cellC = trim((string) $sheet->getCell('C'.$r)->getValue());
                $cellD = trim((string) $sheet->getCell('D'.$r)->getValue());

                if (preg_match('/^(No of Debit|No of Credit|Total Amount|Closing Balance)/i', $cellC)
                    || $cellD === 'No record found') {
                    $stopRow = $r;
                    break;
                }

                $postingDate = $sheet->getCell('C'.$r)->getValue();
                $remark      = trim((string) $sheet->getCell('F'.$r)->getValue());
                $referenceNo = trim((string) $sheet->getCell('H'.$r)->getValue());
                $debit       = (float) ($sheet->getCell('I'.$r)->getValue() ?: 0);
                $credit      = (float) ($sheet->getCell('K'.$r)->getValue() ?: 0);
                $balance     = (float) ($sheet->getCell('L'.$r)->getValue() ?: 0);
                $branchCode  = trim((string) $sheet->getCell('M'.$r)->getValue());

                if ($postingDate === null && $remark === '' && $referenceNo === ''
                    && $debit == 0 && $credit == 0 && $balance == 0) {
                    continue;
                }

                $rows[] = [
                    'posting_date' => is_string($postingDate)
                        ? $postingDate
                        : (string) $postingDate,
                    'remark'       => $remark,
                    'reference_no' => $referenceNo,
                    'debit'        => $debit,
                    'credit'       => $credit,
                    'balance'      => $balance,
                    'branch_code'  => $branchCode,
                ];
            }

            // Footer ringkasan: nilainya di kolom G; label di C.
            for ($r = $stopRow; $r <= $maxRow; $r++) {
                $label = trim((string) $sheet->getCell('C'.$r)->getValue());
                $val   = $sheet->getCell('G'.$r)->getValue();
                if (preg_match('/^No of Debit/i', $label))            $footer['no_of_debit'] = $val !== null ? (int) $val : null;
                if (preg_match('/^Total Amount Debited/i', $label))   $footer['total_amount_debited'] = $val !== null ? (float) $val : null;
                if (preg_match('/^No of Credit/i', $label))           $footer['no_of_credit'] = $val !== null ? (int) $val : null;
                if (preg_match('/^Total Amount Credited/i', $label))  $footer['total_amount_credited'] = $val !== null ? (float) $val : null;
                if (preg_match('/^Closing Balance/i', $label))        $footer['closing_balance'] = $val !== null ? (float) $val : null;
            }

            return [
                'meta'   => $meta,
                'rows'   => $rows,
                'footer' => $footer,
            ];
        } finally {
            @unlink($tmp);
        }
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
