<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

/**
 * Masa transisi Pusher cloud → Reverb self-hosted (dr. Yoga 2026-10-25).
 * Setiap event dikirim ke DUA server: Pusher (primary, perilaku lama) dan
 * Reverb (secondary). Halaman yg masih terbuka dgn Pusher tetap jalan,
 * halaman baru (shim realtime_shim.blade.php) pakai Reverb.
 * Auth channel private/presence: client Reverb mengirim param rt=reverb.
 */
class DualPusherBroadcaster extends PusherBroadcaster
{
    protected PusherBroadcaster $secondary;

    public function __construct(Pusher $primary, Pusher $secondary)
    {
        parent::__construct($primary);
        $this->secondary = new PusherBroadcaster($secondary);
    }

    public function broadcast(array $channels, $event, array $payload = [])
    {
        try {
            $this->secondary->broadcast($channels, $event, $payload);
        } catch (\Throwable $e) {
            Log::warning('broadcast.reverb_fail', ['event' => $event, 'err' => $e->getMessage()]);
        }

        parent::broadcast($channels, $event, $payload);
    }

    public function validAuthenticationResponse($request, $result)
    {
        if ($request->input('rt') === 'reverb') {
            return $this->secondary->validAuthenticationResponse($request, $result);
        }

        return parent::validAuthenticationResponse($request, $result);
    }
}
