<?php

namespace Database\Seeders;

use App\Models\SunatQna;
use Illuminate\Database\Seeder;

class SunatQnaSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(
            file_get_contents(database_path('seeders/data/sunat_qnas.json')),
            true
        );

        foreach ($rows as $row) {
            SunatQna::updateOrCreate(
                ['urutan' => $row['urutan']],
                [
                    'patterns' => $row['patterns'],
                    'answer'   => $row['answer'],
                    'active'   => $row['active'],
                ]
            );
        }
    }
}
