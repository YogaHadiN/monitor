<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappSatisfactionSurvey extends Model
{
    use HasFactory;
    public function antrian(){
        return $this->belongsTo(Antrian::class);
    }
}
