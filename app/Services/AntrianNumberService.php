<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AntrianNumberService
{
    /**
     * Ambil nomor berikutnya secara atomik untuk kombinasi (tenant, ruangan, tanggal).
     * - Menggunakan ON DUPLICATE KEY UPDATE + LAST_INSERT_ID → aman dari race condition
     * - Tanggal dipaksa Asia/Jakarta supaya tidak tergantung timezone server MySQL
     */
    public function next(int $tenantId, int $ruanganId): int
    {
        $today = now('Asia/Jakarta')->toDateString();

        // Atomic insert or update
        $affected = DB::affectingStatement("
            INSERT INTO antrian_counters (tenant_id, ruangan_id, tanggal, last_number, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                last_number = LAST_INSERT_ID(last_number + 1),
                updated_at  = VALUES(updated_at)
        ", [$tenantId, $ruanganId, $today]);

        if ($affected === 1) {
            // 1 baris = INSERT baru → nomor pertama
            return 1;
        }
        // UPDATE (biasanya 2 baris terhitung) → ambil nomor dari LAST_INSERT_ID
        $row = DB::selectOne("SELECT LAST_INSERT_ID() AS nomor");
        return (int) $row->nomor;
    }
}
