<?php

namespace App\Console\Commands;

use App\Services\TelegramClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Auto-send PDF rujukan BPJS via Telegram setiap jam 7 pagi WIB.
 *
 * Replaces trigger lama (inbound WA message) per instruksi dr. Yoga
 * 2026-10-11: jangan tunggu pasien chat dulu, langsung push PDF ke
 * pasien yg onboard Telegram tiap pagi.
 *
 * Query:
 *   - rujukans dgn pdf_rujukan_bpjs_path NOT NULL (PDF ready)
 *   - pdf_sent_at IS NULL (belum pernah dikirim)
 *   - created_at < today midnight WIB (kalender-based H+1)
 *   - pasien.telegram_chat_id NOT NULL (onboard TG)
 *
 * Actions per rujukan:
 *   - Generate signed S3 URL (10 menit TTL)
 *   - sendDocument via TelegramClient (S3 URL - AWS tidak kena WAF
 *     geoblock kezia.id)
 *   - Set pdf_sent_at, pdf_scheduled_send_at=null
 *   - Log outbound ke messages (chat_admin=1, chat_sunat=0)
 *
 * Schedule di Kernel: ->dailyAt('07:00')->timezone('Asia/Jakarta')
 */
class RujukanAutoSendDailyTelegram extends Command
{
    protected $signature   = 'rujukan:auto-send-daily-telegram {--limit=200 : Max rujukan per run} {--max-age-days=7 : Hanya rujukan yang dibuat <= N hari terakhir}';
    protected $description = 'Auto-send PDF rujukan BPJS via Telegram tiap jam 7 pagi (pasien onboard TG).';

    public function handle(TelegramClient $tg): int
    {
        $limit  = (int) ($this->option('limit') ?: 200);
        $nowWIB = Carbon::now('Asia/Jakarta');
        $todayWIB = $nowWIB->copy()->startOfDay();

        $rujukans = DB::table('rujukans as r')
            ->join('periksas as p', 'p.id', '=', 'r.periksa_id')
            ->join('pasiens as ps', 'ps.id', '=', 'p.pasien_id')
            ->leftJoin('tujuan_rujuks as tr', 'tr.id', '=', 'r.tujuan_rujuk_id')
            ->leftJoin('rumah_sakits as rs', 'rs.id', '=', 'r.rumah_sakit_id')
            // Fallback match via NOMOR HP: satu nomor WA/Telegram sering
            // dipakai satu keluarga (mis. 6281293747131 = 5 pasien), tapi
            // telegram_chat_id hanya tersimpan di SATU pasien. Rujukan
            // anggota keluarga lain jadi tidak pernah terkirim (kasus
            // Iyom Maryamah, komplain 1821, 2026-10-17). Ambil chat_id
            // dari telegram_users berdasarkan r.no_telp / ps.no_telp.
            ->leftJoin('telegram_users as tu', function ($j) {
                $j->on(DB::raw("COALESCE(NULLIF(r.no_telp,''), ps.no_telp)"), '=', 'tu.no_telp')
                  ->whereNotNull('tu.no_telp');
            })
            ->whereNotNull('r.pdf_rujukan_bpjs_path')
            ->whereNull('r.pdf_sent_at')
            ->where('r.created_at', '<', $todayWIB->toDateTimeString())
            // Jangan kirim rujukan lama yang menumpuk (sudah ditangani manual).
            ->where('r.created_at', '>=', $todayWIB->copy()->subDays((int) ($this->option('max-age-days') ?: 7))->toDateTimeString())
            ->where(function ($q) {
                $q->where(function ($qq) {
                        $qq->whereNotNull('ps.telegram_chat_id')->where('ps.telegram_chat_id', '!=', '');
                    })
                  ->orWhereNotNull('tu.chat_id');
            })
            ->select(
                'r.id as rujukan_id',
                'r.no_telp',
                'r.pdf_rujukan_bpjs_path',
                'ps.id as pasien_id',
                'ps.nama as pasien_nama',
                DB::raw("COALESCE(NULLIF(ps.telegram_chat_id,''), tu.chat_id) as telegram_chat_id"),
                'tr.tujuan_rujuk as spesialisasi',
                'rs.nama as rumah_sakit'
            )
            ->orderBy('r.created_at', 'asc')
            ->limit($limit)
            ->get()
            ->unique('rujukan_id')
            ->values();

        if ($rujukans->isEmpty()) {
            $this->info('No pending rujukan PDFs (onboard Telegram).');
            return 0;
        }

        $this->info(sprintf('Processing %d rujukan via Telegram.', $rujukans->count()));
        Log::info('RUJUKAN_TG_BATCH_START', [
            'count' => $rujukans->count(),
            'now'   => (string) $nowWIB,
        ]);

        $ok = 0;
        $fail = 0;
        foreach ($rujukans as $r) {
            $result = $this->sendOne($tg, $r, $nowWIB);
            if ($result) {
                $ok++;
                $this->line("  ✓ Rujukan {$r->rujukan_id} → chat_id={$r->telegram_chat_id}");
            } else {
                $fail++;
                $this->line("  ✗ Rujukan {$r->rujukan_id} FAIL");
            }
        }

        $this->info("Done. ok={$ok} fail={$fail}");
        Log::info('RUJUKAN_TG_BATCH_END', ['ok' => $ok, 'fail' => $fail]);

        return 0;
    }

    private function sendOne(TelegramClient $tg, $r, Carbon $now): bool
    {
        try {
            $signedUrl = Storage::disk('s3')->temporaryUrl(
                $r->pdf_rujukan_bpjs_path,
                $now->copy()->addMinutes(10)
            );
        } catch (\Throwable $e) {
            Log::error('RUJUKAN_TG_S3_SIGNED_URL_FAIL', [
                'rujukan_id' => $r->rujukan_id,
                'err'        => $e->getMessage(),
            ]);
            return false;
        }

        $caption = "Halo Kak " . ($r->pasien_nama ?: '') . " 🙏\n\n"
                 . "Berikut rujukan BPJS Anda:\n\n"
                 . "📋 Spesialisasi : " . ($r->spesialisasi ?: '-') . "\n"
                 . "🏥 Rumah Sakit  : " . ($r->rumah_sakit ?: '-') . "\n\n"
                 . "PDF rujukan terlampir 📎\n\n"
                 . "Apabila ada kesalahan rujukan, mohon segera hubungi admin klinik. "
                 . "Terima kasih 🙏";

        $result = $tg->sendDocument(
            (int) $r->telegram_chat_id,
            $signedUrl,
            ['caption' => $caption]
        );

        $okFlag = (bool) ($result['ok'] ?? false);

        if ($okFlag) {
            DB::table('rujukans')->where('id', $r->rujukan_id)->update([
                'pdf_sent_at'           => $now,
                'pdf_scheduled_send_at' => null,
                'updated_at'            => $now,
            ]);
            Log::info('RUJUKAN_TG_SENT', [
                'rujukan_id'       => $r->rujukan_id,
                'telegram_chat_id' => $r->telegram_chat_id,
                'pasien_id'        => $r->pasien_id,
            ]);

            try {
                \App\Models\Message::create([
                    'no_telp'        => (string) ($r->no_telp ?? ''),
                    'message'        => $caption,
                    'tanggal'        => $now->toDateTimeString(),
                    'sending'        => 1,
                    'sudah_dibalas'  => 1,
                    'sudah_diproses' => 1,
                    'staf_id'        => null,
                    'tenant_id'      => 1,
                    'touched'        => 1,
                    'chat_admin'     => 1,
                    'chat_sunat'     => 0,
                    'document_url'   => $signedUrl,
                    'flagged_intent' => 'rujukan_pdf_auto_send_tg',
                ]);
            } catch (\Throwable $e) {
                Log::warning('RUJUKAN_TG_MESSAGE_LOG_FAIL', [
                    'rujukan_id' => $r->rujukan_id,
                    'err'        => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('RUJUKAN_TG_SEND_FAIL', [
                'rujukan_id'       => $r->rujukan_id,
                'telegram_chat_id' => $r->telegram_chat_id,
                'result'           => $result,
            ]);
        }

        return $okFlag;
    }
}
