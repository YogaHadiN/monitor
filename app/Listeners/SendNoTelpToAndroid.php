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
        $noTelp = $event->noTelp;

        $factory = (new Factory)->withServiceAccount(storage_path('app/firebase/service-account.json'));
        $messaging = $factory->createMessaging();

        $message = CloudMessage::withTarget('token', env('FCM_DEVICE_TOKEN'))
            ->withData([
                    'name'  => (string) $noTelp->id,
                    'phone' => $noTelp->no_telp,
                ]);

        $messaging->send($message);
    }
}
