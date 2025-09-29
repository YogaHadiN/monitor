<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NoTelp;
use Input;
use Log;

class WablasWebhookController extends Controller
{
    /**
     * @param
     */
    public $no_telp;

    public function __construct()
    {
        $this->no_telp = Input::get('phone');
    }

    public function wablas(){
        if ( !is_null( $this->no_telp ) ) {
            $no_telp = NoTelp::firstOrCreate([
                'no_telp' => $this->no_telp,
            ]);
            $no_telp->room_id = $this->room_id;
            $no_telp->save();
            $no_telp->touch();
        }

        if ( $this->no_telp !== '6281381912803' ) {
            return;
        }
    }
}
