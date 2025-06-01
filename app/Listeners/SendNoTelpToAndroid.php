<?php

namespace App\Listeners;

use App\Events\NoTelpCreated;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Log;

class SendNoTelpToAndroid
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

        $dummyToken = 'eP2c6cU-QOqO9OEjI4p5cG:APA91bHX3sWzQG9KsB8YhN9x9ABc1xxLQgTFKkPZCjT4fPZQmXrPje3HbN7HzkURP23VapdQ03zDqaL5V_lKxT_q5ljAQt7fJKDdWpyY9tEoCPv5z5lj7XcXeZrLg7yOv0GxjI_xxTestToken';
        $message = CloudMessage::withTarget('token', $dummyToken)
        /* $message = CloudMessage::withTarget('token', env('FCM_DEVICE_TOKEN')) */
            ->withNotification(Notification::create('Kontak Baru', 'Nomor: ' . $noTelp->no_telp))
            ->withData([
                    'name'  => (string) $noTelp->id,
                    'phone' => $noTelp->no_telp,
                ]);

        $messaging->send($message);
    }
}
