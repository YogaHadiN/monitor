<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DiagnosaIcd10B373Seeder extends Seeder
{
    public function run()
    {
        $icd10Id = DB::table('icd10s')->where('kode_icd10', 'B373')->value('id');

        if (is_null($icd10Id)) {
            $this->command->error('ICD10 dengan kode_icd10 = B373 tidak ditemukan di tabel icd10s.');
            return;
        }

        $ids = [30, 816, 2219, 2648];

        $updated = DB::table('diagnosas')
            ->whereIn('id', $ids)
            ->update([
                'icd10_id'   => $icd10Id,
                'updated_at' => now(),
            ]);

        $found = DB::table('diagnosas')->whereIn('id', $ids)->pluck('id')->all();
        $missing = array_values(array_diff($ids, $found));

        $this->command->info("Diagnosas terupdate ke icd10_id={$icd10Id} (B373): {$updated} row.");

        if (!empty($missing)) {
            $this->command->warn('ID tidak ditemukan di tabel diagnosas: ' . implode(', ', $missing));
        }
    }
}
