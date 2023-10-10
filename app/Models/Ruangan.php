<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ruangan extends Model
{
    use HasFactory;
    public static function boot(){
        parent::boot();
        self::updating(function($ruangan){
            $antrian_id = $ruangan->antrian_id;
            Ruangan::where('antrian_id', $antrian_id)->update([
                'antrian_id' => null
            ]);
        });
    }
    
    public function antrian(){
        return $this->belongsTo(Antrian::class)
                    ->whereDate('created_at', date('Y-m-d'));
    }
}
