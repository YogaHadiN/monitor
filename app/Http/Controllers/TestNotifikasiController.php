<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Models\NoTelp;

class TestNotifikasiController extends Controller
{
    public function form()
    {
        return view('test.send_notelp');
    }

    public function send(Request $request)
    {
        $request->validate([
            'no_telp' => 'required|string'
        ]);


        NoTelp::create([
            'no_telp'              => $request->no_telp,
            'tenant_id'            => 1,
            'saved_by_other'       => 0,
            'room_id'              => 'room_abc123',
            'updated_at_qiscus'    => now(),
            'fonnte'               => 0,
            'disimpan_di_android'  => 1,
        ]);

        return back()->with('success', 'Notifikasi berhasil dikirim!');
    }
}
