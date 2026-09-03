<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Mirror service atika. Dual mode by feature flag:
 *
 * - features.pool_antrian_enabled = false (legacy):
 *     Counter scope (tenant, ruangan, tanggal). Prefix ikut ruangan.
 *
 * - features.pool_antrian_enabled = true (pool):
 *     Counter scope (tenant, tipe_konsultasi, tanggal). Prefix ikut
 *     tipe. Semua kanal (walk-in, web, WA, Mobile JKN) share counter.
 *
 * Config `features.pool_antrian_enabled` dibaca dari .env
 * FEATURES_POOL_ANTRIAN — kedua codebase (atika + monitor) harus
 * set nilai yg SAMA supaya numbering konsisten.
 */
class AntrianNumberService
{
    public function next(int $tenantId, int $ruanganId, ?int $tipeKonsultasiId = null): int
    {
        if (config('features.pool_antrian_enabled') && $tipeKonsultasiId) {
            return $this->nextPool($tenantId, $tipeKonsultasiId);
        }
        return $this->nextLegacy($tenantId, $ruanganId);
    }

    private function nextLegacy(int $tenantId, int $ruanganId): int
    {
        $today = now('Asia/Jakarta')->toDateString();

        $affected = DB::affectingStatement("
            INSERT INTO antrian_counters (tenant_id, ruangan_id, tanggal, last_number, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                last_number = LAST_INSERT_ID(last_number + 1),
                updated_at  = VALUES(updated_at)
        ", [$tenantId, $ruanganId, $today]);

        if ($affected === 1) {
            return 1;
        }
        $row = DB::selectOne("SELECT LAST_INSERT_ID() AS nomor");
        return (int) $row->nomor;
    }

    private function nextPool(int $tenantId, int $tipeKonsultasiId): int
    {
        $today = now('Asia/Jakarta')->toDateString();

        $affected = DB::affectingStatement("
            INSERT INTO antrian_counters (tenant_id, ruangan_id, tipe_konsultasi_id, tanggal, last_number, created_at, updated_at)
            VALUES (?, NULL, ?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                last_number = LAST_INSERT_ID(last_number + 1),
                updated_at  = VALUES(updated_at)
        ", [$tenantId, $tipeKonsultasiId, $today]);

        if ($affected === 1) {
            return 1;
        }
        $row = DB::selectOne("SELECT LAST_INSERT_ID() AS nomor");
        return (int) $row->nomor;
    }
}
