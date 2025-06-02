<?php

namespace App\Events;

use App\Models\NoTelp;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Log;

class NoTelpCreated
{
    use Dispatchable, SerializesModels;

    public $noTelp;

    public function __construct(NoTelp $noTelp)
    {
        $this->noTelp = $noTelp;
    }
}
