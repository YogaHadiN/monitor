<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CekListRuangan extends Model
{
    use HasFactory;
    public function cekList(){
        return $this->belongsTo(CekList::class);
    }
    public function limit(){
        return $this->belongsTo(Limit::class);
    }
    public function ruangan(){
        return $this->belongsTo(Ruangan::class);
    }
}
