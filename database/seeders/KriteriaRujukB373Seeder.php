<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KriteriaRujukB373Seeder extends Seeder
{
    public function run()
    {
        $icd10Id = DB::table('icd10s')->where('kode_icd10', 'B373')->value('id');

        if (is_null($icd10Id)) {
            $this->command->error('ICD10 B373 tidak ditemukan.');
            return;
        }

        $dnsId = DB::table('diagnosa_non_spesialistiks')
            ->where('icd10_id', $icd10Id)
            ->value('id');

        if (is_null($dnsId)) {
            $dnsId = DB::table('diagnosa_non_spesialistiks')->insertGetId([
                'icd10_id'           => $icd10Id,
                'level_kemampuan_4a' => 1,
                'ditanggung_bpjs'    => 1,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $this->command->info("Diagnosa non-spesialistik dibuat untuk B373 (id={$dnsId}).");
        }

        $peraturanId = 1; // KMK 1186
        $halaman     = '58';

        $kriterias = [
            'Tidak terdapat fasilitas pemeriksaan untuk pasangan',
            'Dibutuhkan pemeriksaan kultur kuman gonore',
            'Adanya arah kegagalan pengobatan',
        ];

        $inserted = 0;
        $skipped  = 0;

        foreach ($kriterias as $narasi) {
            $narasiId = DB::table('narasi_kriteria_rujuks')
                ->where('narasi_kriteria_rujuk', $narasi)
                ->where('peraturan_id', $peraturanId)
                ->value('id');

            if (is_null($narasiId)) {
                $narasiId = DB::table('narasi_kriteria_rujuks')->insertGetId([
                    'narasi_kriteria_rujuk'             => $narasi,
                    'previous_treatment_required'       => 0,
                    'previous_treatment_days_required'  => null,
                    'peraturan_id'                      => $peraturanId,
                    'halaman'                           => $halaman,
                    'created_at'                        => now(),
                    'updated_at'                        => now(),
                ]);
            }

            $exists = DB::table('kriteria_rujuks')
                ->where('icd10_id', $icd10Id)
                ->where('narasi_kriteria_rujuk_id', $narasiId)
                ->where('diagnosa_non_spesialistik_id', $dnsId)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            DB::table('kriteria_rujuks')->insert([
                'icd10_id'                     => $icd10Id,
                'narasi_kriteria_rujuk_id'     => $narasiId,
                'diagnosa_non_spesialistik_id' => $dnsId,
                'created_at'                   => now(),
                'updated_at'                   => now(),
            ]);
            $inserted++;
        }

        $this->command->info("Kriteria rujuk B373: {$inserted} ditambahkan, {$skipped} sudah ada.");
    }
}
