<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendCheckOutReminder extends Command
{
    protected $signature   = 'attendance:send-checkout-reminder {--force : Kirim ke semua user yang sudah check-in tapi belum check-out}';
    protected $description = 'Kirim notifikasi WA ke user 10 menit sebelum shift selesai';

    public function handle(WhatsAppService $wa): int
    {
        $now    = Carbon::now('Asia/Jakarta');
        $target = $now->copy()->addMinutes(10);
        $force  = $this->option('force');

        $query = Attendance::with(['user', 'schedule.shift'])
            ->whereDate('date', today())
            ->whereNotNull('check_in')
            ->whereNull('check_out');

        if (!$force) {
            // Hanya shift yang selesai dalam ~10 menit (toleransi ±1 menit)
            $query->whereHas('schedule.shift', function ($q) use ($target) {
                $q->whereBetween('end_time', [
                    $target->copy()->subMinute()->format('H:i:s'),
                    $target->copy()->addMinute()->format('H:i:s'),
                ]);
            });
        }

        $attendances = $query->get();

        if ($attendances->isEmpty()) {
            $this->line('Tidak ada reminder checkout yang perlu dikirim.');
            return self::SUCCESS;
        }

        $targets = [];
        foreach ($attendances as $attendance) {
            $user = $attendance->user;
            if (empty($user->phone) && empty($user->wa_lid)) continue;
            if (!$user->is_active) continue;

            $shiftEnd = Carbon::createFromFormat('H:i:s', $attendance->schedule->shift->end_time)->format('H:i');

            $msg  = "🏨 *Grandhika Intern and Daily Worker Attendance*\n\n";
            $msg .= "Halo *{$user->name}*,\n\n";
            $msg .= "⏰ Shift kamu (*{$attendance->schedule->shift->name}*) selesai pukul *{$shiftEnd}* — 10 menit lagi!\n\n";
            $msg .= "Jangan lupa *Check Out* ya. 😊";

            if (!empty($user->phone)) {
                $targets[] = ['phone' => $user->phone, 'message' => $msg];
            }
            $this->line("→ Reminder checkout ke {$user->name} ({$user->phone}) shift ends {$shiftEnd}");
        }

        if (!empty($targets)) {
            $wa->sendBulkPublic($targets);
            $this->info("✓ {$attendances->count()} reminder checkout dikirim.");
        }

        return self::SUCCESS;
    }
}
