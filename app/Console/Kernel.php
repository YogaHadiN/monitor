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
        // Auto-send PDF rujukan BPJS via Telegram tiap jam 7 pagi WIB
        // (dr. Yoga 2026-10-11). Replaces trigger lama "inbound WA
        // customer" dgn push schedule harian — pasien onboard TG dapat
        // rujukan tanpa harus chat admin dulu.
        $schedule->command('rujukan:auto-send-daily-telegram')
            ->dailyAt('07:00')
            ->withoutOverlapping(15)
            ->timezone('Asia/Jakarta');

        // Legacy: rujukan:send-pending-pdfs (WA scheduled fallback)
        // DIMATIKAN per instruksi dr. Yoga 2026-10-11. Command tetap
        // ada di repo untuk manual run kalau butuh backfill via WA.
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
