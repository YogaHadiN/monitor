<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Events\NoTelpCreated;
use Log;

class NoTelp extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected static function booted()
    {
        static::created(function ($noTelp) {
            $noTelp->tenant_id = 1;
            $noTelp->save();
            event(new NoTelpCreated($noTelp));
        });
    }
}
