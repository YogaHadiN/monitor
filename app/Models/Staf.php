<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staf extends Model
{
    use HasFactory;
    public function titel(){
        return $this->belongsTo(Titel::class);
    }
    public function getNamaDenganGelarAttribute(){
        return tambahkanGelar( $this->titel->singkatan, substr($this->nama, 0, 15) );
    }
}
