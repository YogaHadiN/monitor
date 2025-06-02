<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class TestNotifikasiController extends Controller
{
    public function form()
    {
        return view('test.send_notelp');
    }

    public function send(Request $request)
    {
        $request->validate([
            'id'    => 'required|string',
            'no_telp' => 'required|string',
            'token'   => 'required|string',
        ]);

        $factory = (new Factory)->withServiceAccount(storage_path('app/firebase/service-account.json'));
        $messaging = $factory->createMessaging();

        $message = CloudMessage::withTarget('token', $request->token)
            ->withData([
                    'name'  => (string) $request->id,
                    'phone' => $request->no_telp,
                ]);

        $messaging->send($message);

        return back()->with('success', 'Notifikasi berhasil dikirim!');
    }
}
