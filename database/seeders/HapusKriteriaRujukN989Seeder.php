<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HapusKriteriaRujukN989Seeder extends Seeder
{
    public function run()
    {
        $icd10Id = DB::table('icd10s')->where('kode_icd10', 'N989')->value('id');

        if (is_null($icd10Id)) {
            $this->command->warn('ICD10 N989 tidak ditemukan, tidak ada yang dihapus.');
            return;
        }

        $deleted = DB::table('kriteria_rujuks')
            ->where('icd10_id', $icd10Id)
            ->delete();

        $this->command->info("Kriteria rujuk N989 (icd10_id={$icd10Id}) dihapus: {$deleted} row.");
    }
}
