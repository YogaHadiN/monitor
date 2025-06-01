<?php

namespace App\Listeners;

use App\Events\NoTelpCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Log;

class SendNoTelpToAndroid implements ShouldQueue
{
    public function handle(NoTelpCreated $event)
    {

        Log::info('=================');
        Log::info('created no_telp');
        Log::info('SendNoTelpToAndroid.php');
        Log::info('=================');
        $noTelp = $event->noTelp;

        $factory = (new Factory)->withServiceAccount(storage_path('app/firebase/service-account.json'));
        $messaging = $factory->createMessaging();

        $message = CloudMessage::withTarget('token', env('FCM_DEVICE_TOKEN'))
            ->withNotification(Notification::create('Kontak Baru', 'Nomor: ' . $noTelp->no_telp))
            ->withData([
                'type' => 'new_contact',
                'contact' => [
                    'name'  => (string) $noTelp->id,
                    'phone' => $noTelp->no_telp,
                ]
            ]);

        $messaging->send($message);
    }
}
