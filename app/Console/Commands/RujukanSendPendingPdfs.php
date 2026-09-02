<?php

namespace App\Console\Commands;

use App\Http\Controllers\WatzapController;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 — Process pending scheduled rujukan PDF sends.
 *
 * Scenario: customer WA sebelum jam 8 pagi → PDF di-schedule ke jam
 * 8 pagi (row rujukans.pdf_scheduled_send_at). Command ini jalan
 * kontinu (via scheduler tiap 5 menit) untuk process yg due.
 *
 * Query: rujukans dgn
 *   - pdf_scheduled_send_at <= now
 *   - pdf_sent_at IS NULL
 *   - pdf_rujukan_bpjs_path IS NOT NULL
 *
 * Delegate ke WatzapController::sendRujukanPdfNow($id).
 *
 * Per instruksi dr. Yoga 2026-09-02.
 */
class RujukanSendPendingPdfs extends Command
{
    protected $signature   = 'rujukan:send-pending-pdfs {--limit=50 : Max rujukan per run}';
    protected $description = 'Send PDF rujukan BPJS yang scheduled sudah due (batch process).';

    public function handle(): int
    {
        $limit    = (int) ($this->option('limit') ?: 50);
        $nowWIB   = Carbon::now('Asia/Jakarta');

        $pending = DB::table('rujukans')
            ->whereNotNull('pdf_scheduled_send_at')
            ->whereNull('pdf_sent_at')
            ->whereNotNull('pdf_rujukan_bpjs_path')
            ->where('pdf_scheduled_send_at', '<=', $nowWIB->toDateTimeString())
            ->orderBy('pdf_scheduled_send_at', 'asc')
            ->limit($limit)
            ->pluck('id');

        if ($pending->isEmpty()) {
            $this->info('No pending rujukan PDFs due.');
            return 0;
        }

        $this->info(sprintf('Processing %d pending rujukan PDF(s).', $pending->count()));
        Log::info('RUJUKAN_PDF_BATCH_START', [
            'count' => $pending->count(),
            'now'   => (string) $nowWIB,
        ]);

        $ok = 0;
        $fail = 0;
        $watzapCtl = app(WatzapController::class);
        foreach ($pending as $id) {
            $result = $watzapCtl->sendRujukanPdfNow((int) $id);
            if ($result['ok'] ?? false) {
                $ok++;
                $this->line("  ✓ Rujukan {$id} sent");
            } else {
                $fail++;
                $reason = $result['reason'] ?? 'unknown';
                $this->line("  ✗ Rujukan {$id} FAIL: {$reason}");
            }
        }

        $this->info("Done. ok={$ok} fail={$fail}");
        Log::info('RUJUKAN_PDF_BATCH_END', ['ok' => $ok, 'fail' => $fail]);

        return 0;
    }
}
