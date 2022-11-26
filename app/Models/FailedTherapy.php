<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedTherapy extends Model
{
    use HasFactory;

    protected $table = 'failed_therapies';
    public function antrian(){
        return $this->belongsTo(Antrian:class);
    }
}
