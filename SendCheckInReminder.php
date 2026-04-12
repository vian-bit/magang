<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendCheckInReminder extends Command
{
    protected $signature   = 'attendance:send-checkin-reminder {--force : Kirim ke semua user yang belum check-in hari ini tanpa cek waktu shift}';
    protected $description = 'Kirim notifikasi WA ke user 10 menit sebelum shift mulai';

    public function handle(WhatsAppService $wa): int
    {
        $now    = Carbon::now('Asia/Jakarta');
        $target = $now->copy()->addMinutes(10);
        $force  = $this->option('force');

        $query = Schedule::with(['user', 'shift'])
            ->whereDate('date', today())
            ->whereDoesntHave('attendance', function ($q) {
                $q->whereNotNull('check_in');
            });

        if (!$force) {
            // Hanya shift yang mulai dalam ~10 menit (toleransi ±1 menit)
            $query->whereHas('shift', function ($q) use ($target) {
                $q->whereBetween('start_time', [
                    $target->copy()->subMinute()->format('H:i:s'),
                    $target->copy()->addMinute()->format('H:i:s'),
                ]);
            });
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            $this->line('Tidak ada reminder yang perlu dikirim.');
            return self::SUCCESS;
        }

        $targets = [];
        foreach ($schedules as $schedule) {
            $user = $schedule->user;
            if (empty($user->phone) || !$user->is_active) continue;

            $shiftStart = Carbon::createFromFormat('H:i:s', $schedule->shift->start_time)->format('H:i');

            $msg  = "🏨 *Grandhika Intern and Daily Worker Attendance*\n\n";
            $msg .= "Halo *{$user->name}*,\n\n";
            $msg .= "⏰ Shift kamu (*{$schedule->shift->name}*) dimulai pukul *{$shiftStart}* — 10 menit lagi!\n\n";
            $msg .= "Jangan lupa *Check In* ya. 😊";

            $targets[] = ['phone' => $user->phone, 'message' => $msg];
            $this->line("→ Reminder ke {$user->name} ({$user->phone}) shift {$shiftStart}");
        }

        if (!empty($targets)) {
            $wa->sendBulkPublic($targets);
            $this->info("✓ {$schedules->count()} reminder dikirim.");
        }

        return self::SUCCESS;
    }
}
