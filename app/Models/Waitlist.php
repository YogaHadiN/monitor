<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Waitlist extends Model
{
    use BelongsToTenant, HasFactory;

    protected $guarded = [];

    public function pasien()
    {
        return $this->belongsTo(Pasien::class);
    }
}
