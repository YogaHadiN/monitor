<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Reservation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $guarded = [];

    // Relasi
    public function pasien()
    {
        return $this->belongsTo(Pasien::class);
    }

    public function staf()
    {
        return $this->belongsTo(Staf::class);
    }

    public function slots()
    {
        return $this->hasMany(Slot::class);
    }

    public function scanLogs()
    {
        return $this->hasMany(ScanLog::class);
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }
}
