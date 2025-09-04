<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Slot extends Model
{
    use BelongsToTenant, HasFactory;

    protected $guarded = [];

    public function staf()
    {
        return $this->belongsTo(Staf::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
