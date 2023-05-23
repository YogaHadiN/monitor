<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Poli extends Model
{
    use HasFactory;
    public static function konsultasiDokterUmum(){
        return Poli::where('poli', 'Poli Umum - Konsul Dokter')->first();
    }
}
