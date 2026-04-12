<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Kirim rekap absensi ke semua admin setiap hari jam 17:00 WIB
        $schedule->command('attendance:send-daily-report')
            ->dailyAt('17:00')
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping();

        // Kirim reminder check-in ke user 10 menit sebelum shift
        $schedule->command('attendance:send-checkin-reminder')
            ->everyMinute()
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
