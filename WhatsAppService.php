<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $serverUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->serverUrl = rtrim(config('services.whatsapp.server_url', 'http://localhost:3000'), '/');
        $this->apiKey    = config('services.whatsapp.api_key', '');
    }

    /**
     * Expose untuk dipanggil controller langsung
     */
    public function buildPublicReportMessage(?int $departmentId, string $deptLabel): string
    {
        return $this->buildReportMessage($departmentId, $deptLabel);
    }

    public function sendMessagePublic(string $phone, string $message): bool
    {
        return $this->sendMessage($phone, $message);
    }

    public function sendBulkPublic(array $targets): void
    {
        $this->sendBulk($targets);
    }

    /**
     * Kirim rekap harian ke semua admin per departemen (dipanggil scheduler)
     */
    public function sendDailyReportToAllAdmins(): void
    {
        $targets = [];

        // Kumpulkan pesan per admin departemen
        $departments = Department::all();
        foreach ($departments as $dept) {
            $message = $this->buildReportMessage($dept->id, $dept->name);

            $admins = User::where('role', 'admin')
                ->where('department_id', $dept->id)
                ->whereNotNull('phone')
                ->where('is_active', true)
                ->get();

            foreach ($admins as $admin) {
                $targets[] = ['phone' => $admin->phone, 'message' => $message];
            }
        }

        // Rekap semua dept untuk superuser
        $allMessage = $this->buildReportMessage(null, 'Semua Departemen');
        $superusers = User::where('role', 'superuser')
            ->whereNotNull('phone')
            ->where('is_active', true)
            ->get();

        foreach ($superusers as $su) {
            $targets[] = ['phone' => $su->phone, 'message' => $allMessage];
        }

        if (empty($targets)) {
            Log::info('WA Report: Tidak ada admin/superuser dengan nomor WA terdaftar.');
            return;
        }

        $this->sendBulk($targets);
    }

    /**
     * Kirim rekap ke satu nomor (manual trigger dari controller)
     */
    public function sendDailyReport(?int $departmentId = null): bool
    {
        $deptName = $departmentId
            ? (Department::find($departmentId)?->name ?? 'Unknown')
            : 'Semua Departemen';

        $target = config('services.whatsapp.phone_number', '');
        if (empty($target)) {
            Log::warning('WA: WHATSAPP_PHONE_NUMBER belum diset di .env');
            return false;
        }

        return $this->sendMessage($target, $this->buildReportMessage($departmentId, $deptName));
    }

    /**
     * Notifikasi early checkout ke user
     */
    public function sendEarlyCheckoutNotification(User $user, string $status, ?string $adminNotes = null): bool
    {
        if (empty($user->phone)) return false;

        $icon   = $status === 'approved' ? '✅' : '❌';
        $label  = $status === 'approved' ? 'DISETUJUI' : 'DITOLAK';

        $msg  = "🏨 *Grandhika Intern and Daily Worker Attendance*\n\n";
        $msg .= "Halo {$user->name},\n\n";
        $msg .= "Request *Early Checkout* kamu {$icon} *{$label}*.\n";
        if ($adminNotes) $msg .= "📝 Catatan: {$adminNotes}\n";
        if ($status === 'rejected') $msg .= "\nSilakan tunggu hingga jam shift selesai.";

        return $this->sendMessage($user->phone, $msg);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    protected function buildReportMessage(?int $departmentId, string $deptLabel): string
    {
        $date      = today();
        $schedules = Schedule::with(['user', 'shift', 'attendance'])
            ->whereDate('date', $date)
            ->when($departmentId, fn($q) => $q->whereHas('user', fn($u) => $u->where('department_id', $departmentId)))
            ->get();

        $present = $schedules->filter(fn($s) => $s->attendance?->status === 'present')->count();
        $late    = $schedules->filter(fn($s) => $s->attendance?->status === 'late')->count();
        $absent  = $schedules->filter(fn($s) => !$s->attendance?->check_in)->count();
        $total   = $schedules->count();

        $line = str_repeat('─', 30);
        $msg  = "🏨 *REKAP ABSENSI HARIAN*\n";
        $msg .= "📅 {$date->translatedFormat('d F Y')}\n";
        $msg .= "🏢 Dept: {$deptLabel}\n";
        $msg .= "{$line}\n";
        $msg .= "✅ Hadir      : {$present} orang\n";
        $msg .= "⏰ Terlambat  : {$late} orang\n";
        $msg .= "❌ Tidak Hadir: {$absent} orang\n";
        $msg .= "📋 Total      : {$total} orang\n";

        if ($absent > 0) {
            $msg .= "{$line}\n⚠️ *Belum Absen:*\n";
            $schedules->filter(fn($s) => !$s->attendance?->check_in)
                ->each(fn($s) => $msg .= "  • {$s->user->name} ({$s->shift->name})\n");
        }

        if ($late > 0) {
            $msg .= "{$line}\n⏰ *Terlambat:*\n";
            $schedules->filter(fn($s) => $s->attendance?->status === 'late')
                ->each(fn($s) => $msg .= "  • {$s->user->name} (masuk: {$s->attendance->check_in})\n");
        }

        $msg .= "{$line}\n_Dikirim otomatis oleh Grandhika Intern and Daily Worker Attendance_";
        return $msg;
    }

    protected function sendMessage(string $phone, string $message): bool
    {
        try {
            $response = Http::withHeaders(['x-api-key' => $this->apiKey])
                ->timeout(10)
                ->post("{$this->serverUrl}/send", [
                    'phone'   => $phone,
                    'message' => $message,
                ]);

            $ok = $response->successful() && ($response->json('status') === true);
            if (!$ok) Log::warning('WA send failed', ['response' => $response->json()]);
            return $ok;

        } catch (\Exception $e) {
            Log::error('WA exception: ' . $e->getMessage());
            return false;
        }
    }

    protected function sendBulk(array $targets): void
    {
        try {
            $response = Http::withHeaders(['x-api-key' => $this->apiKey])
                ->timeout(60)
                ->post("{$this->serverUrl}/send-bulk", ['targets' => $targets]);

            Log::info('WA bulk send', ['results' => $response->json('results')]);
        } catch (\Exception $e) {
            Log::error('WA bulk exception: ' . $e->getMessage());
        }
    }
}
