<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoliBpjs extends Model
{
    use HasFactory;
    public function poli(){
        return $this->hasOne(Poli::class);
    }
    public function tipe_konsultasi(){
        return $this->hasOne(TipeKonsultasi::class);
    }
}
