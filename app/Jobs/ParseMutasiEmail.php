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
     *
     * Kolom Kopra bergeser antar versi export (mis. value metadata kadang
     * di kolom D, kadang di E; transaksi tabel pernah memakai I/K/L/M
     * lalu berubah jadi H/J/K/L). Daripada hardcode posisi, kita scan
     * row HEADER ("Posting Date", "Debit", "Credit", dst.) dan bangun
     * map `column letter → field` di runtime. Metadata + footer juga
     * dideteksi by-label: ambil value dari cell non-null pertama setelah
     * label di baris yang sama.
     */
    protected function parseKopraXls(string $xlsBytes): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mutasi_xls_');
        file_put_contents($tmp, $xlsBytes);

        try {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            $reader->setReadDataOnly(true);
            $spread = $reader->load($tmp);
            $sheet  = $spread->getSheetByName('transaction_inquiry') ?: $spread->getActiveSheet();

            $maxRow = $sheet->getHighestRow();
            $maxCol = $sheet->getHighestColumn();

            // Snapshot 2D array — lebih mudah di-scan ketimbang getCell
            // ribuan kali. Hemat I/O dan jadi single source of truth.
            $grid = $sheet->rangeToArray('A1:' . $maxCol . $maxRow, null, true, false, true);

            $headerRow = $this->detectHeaderRow($grid);
            if ($headerRow === null) {
                throw new \RuntimeException('Header transaksi (Posting Date) tidak ditemukan di sheet');
            }
            $colMap = $this->buildHeaderColumnMap($grid[$headerRow] ?? []);

            $meta = [
                'account_no'      => $this->lookupMetaLabel($grid, $headerRow, ['Account No', 'Account Number']),
                'period'          => $this->lookupMetaLabel($grid, $headerRow, ['Period', 'Statement Period']),
                'currency'        => $this->lookupMetaLabel($grid, $headerRow, ['Currency']),
                'branch'          => $this->lookupMetaLabel($grid, $headerRow, ['Branch']),
                'opening_balance' => (float) $this->parseAmount(
                    $this->lookupMetaLabel($grid, $headerRow, ['Opening Balance'])
                ),
            ];

            $rows    = [];
            $stopRow = $maxRow + 1;
            $postCol = $colMap['posting_date'] ?? null;
            $remCol  = $colMap['remark']       ?? null;
            $refCol  = $colMap['reference_no'] ?? null;
            $debCol  = $colMap['debit']        ?? null;
            $creCol  = $colMap['credit']       ?? null;
            $balCol  = $colMap['balance']      ?? null;
            $brnCol  = $colMap['branch_code']  ?? null;

            for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
                $line = $grid[$r] ?? [];

                // Footer marker: salah satu cell di baris ini berisi
                // label ringkasan → stop scanning transaksi.
                $isFooter = false;
                foreach ($line as $v) {
                    if (is_string($v) && preg_match('/^\s*(No of (Debit|Credit)|Total Amount (Debited|Credited)|Closing Balance|No record found)\b/i', $v)) {
                        $isFooter = true;
                        break;
                    }
                }
                if ($isFooter) { $stopRow = $r; break; }

                $postingDate = $postCol ? ($line[$postCol] ?? null) : null;
                $remark      = $remCol  ? trim((string) ($line[$remCol]  ?? '')) : '';
                $referenceNo = $refCol  ? trim((string) ($line[$refCol]  ?? '')) : '';
                $debit       = $debCol  ? $this->parseAmount((string) ($line[$debCol] ?? '')) : 0.0;
                $credit      = $creCol  ? $this->parseAmount((string) ($line[$creCol] ?? '')) : 0.0;
                $balance     = $balCol  ? $this->parseAmount((string) ($line[$balCol] ?? '')) : 0.0;
                $branchCode  = $brnCol  ? trim((string) ($line[$brnCol] ?? '')) : '';

                if (($postingDate === null || $postingDate === '') && $remark === '' && $referenceNo === ''
                    && $debit == 0 && $credit == 0 && $balance == 0) {
                    continue;
                }

                $rows[] = [
                    'posting_date' => is_string($postingDate) ? $postingDate : (string) $postingDate,
                    'remark'       => $remark,
                    'reference_no' => $referenceNo,
                    'debit'        => $debit,
                    'credit'       => $credit,
                    'balance'      => $balance,
                    'branch_code'  => $branchCode,
                ];
            }

            $footer = [
                'no_of_debit'           => $this->parseFooterInt(  $this->lookupMetaLabel($grid, $stopRow - 1, ['No of Debit'],   $maxRow)),
                'total_amount_debited'  => $this->parseFooterFloat($this->lookupMetaLabel($grid, $stopRow - 1, ['Total Amount Debited'],  $maxRow)),
                'no_of_credit'          => $this->parseFooterInt(  $this->lookupMetaLabel($grid, $stopRow - 1, ['No of Credit'],  $maxRow)),
                'total_amount_credited' => $this->parseFooterFloat($this->lookupMetaLabel($grid, $stopRow - 1, ['Total Amount Credited'], $maxRow)),
                'closing_balance'       => $this->parseFooterFloat($this->lookupMetaLabel($grid, $stopRow - 1, ['Closing Balance'], $maxRow)),
            ];

            return [
                'meta'   => $meta,
                'rows'   => $rows,
                'footer' => $footer,
            ];
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Cari row yang mengandung "Posting Date" — itu header tabel
     * transaksi. Scan sampai 30 baris pertama saja (header selalu di
     * atas data).
     */
    private function detectHeaderRow(array $grid): ?int
    {
        $limit = min(count($grid), 30);
        for ($i = 1; $i <= $limit; $i++) {
            $line = $grid[$i] ?? [];
            foreach ($line as $v) {
                if (is_string($v) && preg_match('/posting\s*date/i', $v)) {
                    return $i;
                }
            }
        }
        return null;
    }

    /**
     * Dari row header, bangun map `column letter → field name`. Kita
     * cocokkan label header dengan fuzzy regex agar tahan terhadap
     * varian penamaan (mis. "Reference No" vs "Reference Number").
     */
    private function buildHeaderColumnMap(array $headerLine): array
    {
        $patterns = [
            'posting_date' => '/posting\s*date/i',
            'remark'       => '/^\s*remark/i',
            'reference_no' => '/reference\s*(no|number)?/i',
            'debit'        => '/^\s*debit\b/i',
            'credit'       => '/^\s*credit\b/i',
            'balance'      => '/^\s*balance\b/i',
            'branch_code'  => '/branch\s*code/i',
        ];
        $map = [];
        foreach ($headerLine as $col => $val) {
            if (!is_string($val) || $val === '') continue;
            foreach ($patterns as $field => $regex) {
                if (isset($map[$field])) continue;
                if (preg_match($regex, $val)) {
                    $map[$field] = $col;
                    break;
                }
            }
        }
        return $map;
    }

    /**
     * Cari label `$labels` di kolom mana pun di row 1..$limitRow
     * (default = $headerRow-1, jadi metadata di atas tabel). Kembalikan
     * value cell non-null pertama setelah cell label di row yang sama.
     */
    private function lookupMetaLabel(array $grid, int $headerRow, array $labels, ?int $limitRow = null): string
    {
        $limit = $limitRow ?? ($headerRow - 1);
        for ($r = 1; $r <= $limit; $r++) {
            $line = $grid[$r] ?? [];
            foreach ($line as $col => $val) {
                if (!is_string($val)) continue;
                foreach ($labels as $label) {
                    if (preg_match('/^\s*' . preg_quote($label, '/') . '\s*$/i', $val)) {
                        // Take next non-null cell to the right.
                        $found = false;
                        foreach ($line as $col2 => $val2) {
                            if (!$found && $col2 === $col) { $found = true; continue; }
                            if ($found && $val2 !== null && $val2 !== '') {
                                return trim((string) $val2);
                            }
                        }
                        return '';
                    }
                }
            }
        }
        return '';
    }

    private function parseAmount(string $raw): float
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '-') return 0.0;
        // Format Kopra: "151,121,812.46" — comma ribuan, dot desimal.
        $clean = str_replace(',', '', $raw);
        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    private function parseFooterInt(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        $clean = str_replace(',', '', $raw);
        return is_numeric($clean) ? (int) $clean : null;
    }

    private function parseFooterFloat(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        $clean = str_replace(',', '', $raw);
        return is_numeric($clean) ? (float) $clean : null;
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
