<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Kirim rekap absensi ke semua admin setiap hari jam 17:00 WIB
Schedule::command('attendance:send-daily-report')
    ->dailyAt('17:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Kirim reminder check-in ke user 10 menit sebelum shift
Schedule::command('attendance:send-checkin-reminder')
    ->everyMinute()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Kirim reminder check-out ke user 10 menit sebelum shift selesai
Schedule::command('attendance:send-checkout-reminder')
    ->everyMinute()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
