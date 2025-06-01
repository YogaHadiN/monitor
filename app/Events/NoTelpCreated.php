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

        Log::info('=================');
        Log::info('created no_telp');
        Log::info('NoTelpCreated.php');
        Log::info('=================');
        $this->noTelp = $noTelp;
    }
}
