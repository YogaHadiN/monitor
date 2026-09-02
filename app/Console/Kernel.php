<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Process pending scheduled rujukan PDF sends (customer WA
        // sebelum jam 8 pagi → schedule ke jam 8 pagi). Runs setiap
        // 5 menit supaya delayed sends fire cepat setelah jam 8.
        // Per instruksi dr. Yoga 2026-09-02.
        $schedule->command('rujukan:send-pending-pdfs')
            ->everyFiveMinutes()
            ->withoutOverlapping(10)
            ->timezone('Asia/Jakarta');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
