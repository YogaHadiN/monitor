<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use DB;
use Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\App\Services\WablasClient::class, function ($app) {
            return new \App\Services\WablasClient();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Transisi Pusher → Reverb (2026-10-25)
        \Illuminate\Support\Facades\Broadcast::extend('dual', function ($app, $config) {
            $mgr = $app->make(\Illuminate\Broadcasting\BroadcastManager::class);
            $conns = $app['config']['broadcasting.connections'];
            return new \App\Broadcasting\DualPusherBroadcaster(
                $mgr->pusher($conns[$config['primary']]),
                $mgr->pusher($conns[$config['secondary']])
            );
        });

        if(env('APP_ENV') === 'production') {
            \URL::forceScheme('https');
        }
        DB::listen(function ($query) {
            if ($query->time > 5000) { // lebih dari 5 detik
                Log::info("========================================================");
                Log::info("Slow query: {$query->sql} [{$query->time}ms]");
                Log::info("========================================================");
            }
        });
    }
}
