<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmPushController extends Controller
{
    public function kirim(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'phone' => 'required|string',
        ]);

        $token = env('FCM_DEVICE_TOKEN');

        $factory = (new Factory)
            ->withServiceAccount(storage_path('app/firebase/service-account.json'));

        $messaging = $factory->createMessaging();

        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(Notification::create('Kontak Baru', 'Simpan nomor ' . $validated['phone']))
            ->withData([
                'type' => 'new_contact',
                'contact' => [
                    'name'  => $validated['id'],
                    'phone' => $validated['phone'],
                ]
            ]);

        $response = $messaging->send($message);

        return response()->json([
            'message' => 'Push berhasil dikirim ke FCM',
            'fcm_id' => $response->name()
        ]);
    }
}
