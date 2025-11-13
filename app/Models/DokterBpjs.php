<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DokterBpjs extends Model
{
    use HasFactory;

    public function staf(){
        return $this->hasOne(Staf::class);
    }
}
