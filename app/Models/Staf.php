<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use Log;

class Staf extends Model
{
    use BelongsToTenant,HasFactory;
    public function titel(){
        return $this->belongsTo(Titel::class);
    }
    public function getNamaDenganGelarAttribute(){
        $nama = $this->nama_panggilan ?? $this->nama;
        if (is_null(  $this->titel  )) {
            Log::info("==================================");
            Log::info("MODEL STAF");
            Log::info( $this->id );
            Log::info( $this->titel_id );
            Log::info( $this );
            Log::info("==================================");
        }
        return tambahkanGelar($this->titel->singkatan, substr($nama, 0, 15));
    }
}
