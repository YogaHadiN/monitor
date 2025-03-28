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
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
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
