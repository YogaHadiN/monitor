<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asuransi extends Model
{
    use HasFactory;
    public static function Bpjs(){
        return Asuransi::where('tipe_asuransi_id', 5)->first();
    }

    public static function BiayaPribadi(){
        return Asuransi::where('tipe_asuransi_id', 1)->first();
    }
}
