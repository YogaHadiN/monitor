<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GambarPeriksa extends Model
{
    use HasFactory;

    protected $guarded = [];
	public function gambarable(){
		return $this->morphTo();
	}
}
