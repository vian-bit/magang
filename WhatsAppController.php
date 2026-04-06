<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\User;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsAppController extends Controller
{
    public function __construct(protected WhatsAppService $wa) {}

    /**
     * Cek status koneksi WA server
     */
    public function status()
    {
        $serverUrl = config('services.whatsapp.server_url', 'http://localhost:3000');
        try {
            $response = Http::timeout(5)->get("{$serverUrl}/status");
            $data = $response->json();
            return response()->json([
                'server_running' => true,
                'wa_connected'   => $data['connected'] ?? false,
                'has_qr'         => $data['hasQR'] ?? false,
                'qr_url'         => "{$serverUrl}/qr",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'server_running' => false,
                'wa_connected'   => false,
                'error'          => 'WA Server tidak berjalan. Jalankan START-WA-SERVER.bat',
            ]);
        }
    }

    /**
     * Kirim rekap manual dari dashboard
     */
    public function sendReport()
    {
        $user = auth()->user();

        \Illuminate\Support\Facades\Log::info('WA sendReport', [
            'user'  => $user->name,
            'phone' => $user->phone,
            'dept'  => $user->department_id,
        ]);

        if (empty($user->phone)) {
            return back()->with('error', 'Nomor WhatsApp kamu belum diisi. Edit profil di User Management.');
        }

        $deptId   = $user->isSuperuser() ? null : $user->department_id;
        $deptName = $user->isSuperuser() ? 'Semua Departemen' : ($user->department->name ?? 'Unknown');

        $message = $this->wa->buildPublicReportMessage($deptId, $deptName);
        $result  = $this->wa->sendMessagePublic($user->phone, $message);

        \Illuminate\Support\Facades\Log::info('WA sendReport result', ['result' => $result]);

        if ($result) {
            return back()->with('success', 'Rekap absensi berhasil dikirim ke WhatsApp kamu!');
        }
        return back()->with('error', 'Gagal kirim WA. Pastikan WA Server berjalan dan sudah scan QR.');
    }

    /**
     * Webhook - menerima pesan masuk dari WA server (Baileys)
     * Admin bisa kirim perintah via WA dan dapat balasan otomatis
     */
    public function webhook(Request $request)
    {
        // Verifikasi secret
        $secret = $request->header('x-wa-secret');
        if ($secret !== config('services.whatsapp.api_key')) {
            return response()->json(['reply' => null], 401);
        }

        $from  = $request->input('from');
        $text  = trim($request->input('text', ''));
        $textL = strtolower($text);

        $isLid = str_contains($from, '@lid');
        $rawId = str_replace(['@s.whatsapp.net', '@lid'], '', $from);

        // Konversi ke format phone 08xx
        $phone = $rawId;
        if (str_starts_with($phone, '62')) {
            $phone = '0' . substr($phone, 2);
        }

        // --- Cari user ---
        $user = User::where('phone', $phone)
            ->whereIn('role', ['admin', 'superuser'])
            ->where('is_active', true)
            ->first();

        if (!$user && $isLid) {
            $user = User::where('wa_lid', $rawId)
                ->whereIn('role', ['admin', 'superuser'])
                ->where('is_active', true)
                ->first();
        }

        // Auto-save wa_lid kalau sudah ketemu
        if ($user && $isLid && empty($user->wa_lid)) {
            $user->update(['wa_lid' => $rawId]);
        }

        // Nomor tidak terdaftar — coba alur link via perintah "link <email>"
        if (!$user) {
            \Illuminate\Support\Facades\Log::info("WA webhook: unregistered {$from}, text: {$textL}");

            // Jika pesan adalah "link <email>", coba daftarkan wa_lid
            if (str_starts_with($textL, 'link ')) {
                $email = trim(substr($textL, 5));
                $candidate = User::where('email', $email)
                    ->whereIn('role', ['admin', 'superuser'])
                    ->where('is_active', true)
                    ->first();

                if ($candidate) {
                    $candidate->update(['wa_lid' => $rawId]);
                    return response()->json([
                        'reply' => "✅ Berhasil!\n\nHalo *{$candidate->name}*, nomor kamu sudah terhubung.\nKetik *help* untuk melihat perintah."
                    ]);
                }

                return response()->json([
                    'reply' => "❌ Email tidak ditemukan atau bukan admin."
                ]);
            }

            // Pesan lain dari nomor tidak terdaftar — abaikan
            \Illuminate\Support\Facades\Log::info("WA webhook: ignored message from unregistered number {$from}");
            return response()->json(['reply' => null]);
        }

        // Perintah "link" dari user yang sudah terdaftar — konfirmasi sudah terdaftar
        if (str_starts_with($textL, 'link ')) {
            return response()->json([
                'reply' => "✅ Nomor kamu sudah terdaftar, *{$user->name}*!\nKetik *help* untuk melihat perintah."
            ]);
        }

        $reply = $this->processCommand($textL, $user);
        return response()->json(['reply' => $reply ?: null]);
    }

    /**
     * Proses perintah dari admin via WA
     */
    protected function processCommand(string $text, User $user): string|array|null
        {
            $deptId   = $user->isSuperuser() ? null : $user->department_id;
            $deptName = $user->isSuperuser() ? 'Semua Departemen' : $user->department->name;

            // Perintah: rekap / rekap YYYY-MM-DD
            if (str_starts_with($text, 'rekap')) {
                $parts = explode(' ', $text);
                $date  = isset($parts[1]) ? Carbon::parse($parts[1]) : today();
                return $this->buildRekapMessage($deptId, $deptName, $date);
            }

            // Perintah: export / export YYYY-MM-DD / export YYYY-MM-DD YYYY-MM-DD
            if (str_starts_with($text, 'export')) {
                $parts = explode(' ', $text);
                $start = isset($parts[1]) ? $parts[1] : today()->format('Y-m-d');
                $end   = isset($parts[2]) ? $parts[2] : $start;
                return $this->buildExportLink($deptId, $deptName, $start, $end);
            }

            // Perintah: absen (siapa yang belum absen hari ini)
            if ($text === 'absen' || $text === 'belum absen') {
                return $this->buildBelumAbsenMessage($deptId, $deptName);
            }

            // Perintah: terlambat
            if ($text === 'terlambat' || $text === 'late') {
                return $this->buildTerlambatMessage($deptId, $deptName);
            }

            // Perintah: help / bantuan
            if (in_array($text, ['help', 'bantuan', 'menu', '?'])) {
                return $this->buildHelpMessage();
            }

            return "❓ Perintah tidak dikenal.\n\nKetik *help* untuk melihat daftar perintah.";
        }

    protected function buildRekapMessage(?int $deptId, string $deptName, Carbon $date): string
    {
        $schedules = Schedule::with(['user', 'shift', 'attendance'])
            ->whereDate('date', $date)
            ->when($deptId, fn($q) => $q->whereHas('user', fn($u) => $u->where('department_id', $deptId)))
            ->get();

        $present = $schedules->filter(fn($s) => $s->attendance?->status === 'present')->count();
        $late    = $schedules->filter(fn($s) => $s->attendance?->status === 'late')->count();
        $absent  = $schedules->filter(fn($s) => !$s->attendance?->check_in)->count();
        $total   = $schedules->count();

        $line = str_repeat('─', 28);
        $msg  = "🏨 *REKAP ABSENSI*\n";
        $msg .= "📅 {$date->format('d/m/Y')}\n";
        $msg .= "🏢 {$deptName}\n";
        $msg .= "{$line}\n";
        $msg .= "✅ Hadir      : {$present}\n";
        $msg .= "⏰ Terlambat  : {$late}\n";
        $msg .= "❌ Tidak Hadir: {$absent}\n";
        $msg .= "📋 Total      : {$total}\n";

        if ($absent > 0) {
            $msg .= "{$line}\n⚠️ *Belum Absen:*\n";
            $schedules->filter(fn($s) => !$s->attendance?->check_in)
                ->each(fn($s) => $msg .= "  • {$s->user->name}\n");
        }

        return $msg;
    }

    protected function buildBelumAbsenMessage(?int $deptId, string $deptName): string
    {
        $schedules = Schedule::with(['user', 'shift', 'attendance'])
            ->whereDate('date', today())
            ->when($deptId, fn($q) => $q->whereHas('user', fn($u) => $u->where('department_id', $deptId)))
            ->get()
            ->filter(fn($s) => !$s->attendance?->check_in);

        if ($schedules->isEmpty()) {
            return "✅ Semua karyawan sudah absen hari ini!";
        }

        $msg = "⚠️ *BELUM ABSEN HARI INI*\n";
        $msg .= "🏢 {$deptName}\n";
        $msg .= str_repeat('─', 28) . "\n";
        foreach ($schedules as $s) {
            $msg .= "  • {$s->user->name} ({$s->shift->name})\n";
        }
        $msg .= "\nTotal: {$schedules->count()} orang";

        return $msg;
    }

    protected function buildTerlambatMessage(?int $deptId, string $deptName): string
    {
        $attendances = Attendance::with(['user', 'schedule.shift'])
            ->whereDate('date', today())
            ->where('status', 'late')
            ->when($deptId, fn($q) => $q->whereHas('user', fn($u) => $u->where('department_id', $deptId)))
            ->get();

        if ($attendances->isEmpty()) {
            return "✅ Tidak ada karyawan yang terlambat hari ini!";
        }

        $msg = "⏰ *TERLAMBAT HARI INI*\n";
        $msg .= "🏢 {$deptName}\n";
        $msg .= str_repeat('─', 28) . "\n";
        foreach ($attendances as $a) {
            $msg .= "  • {$a->user->name} (masuk: {$a->check_in})\n";
        }
        $msg .= "\nTotal: {$attendances->count()} orang";

        return $msg;
    }

    protected function buildExportLink(?int $deptId, string $deptName, string $start, string $end): array|string
    {
        try {
            $startDate = Carbon::parse($start)->format('Y-m-d');
            $endDate   = Carbon::parse($end)->format('Y-m-d');
        } catch (\Exception $e) {
            return "❌ Format tanggal salah.\n\nContoh:\n• *export* — hari ini\n• *export 2026-03-16* — tanggal tertentu\n• *export 2026-03-01 2026-03-16* — rentang tanggal";
        }

        $token = strtoupper(\Illuminate\Support\Str::random(8));
        \Illuminate\Support\Facades\Cache::put("wa_export:{$token}", [
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'department_id' => $deptId,
        ], now()->addMinutes(10));

        // Ambil IP lokal server agar bisa diakses dari device lain (HP, dll)
        $serverIp = $this->getServerLocalIp();
        $port     = parse_url(config('app.url'), PHP_URL_PORT) ?? 8000;
        $appUrl   = "http://{$serverIp}:{$port}";
        $url      = "{$appUrl}/dl/{$token}";

        $label = $startDate === $endDate
            ? Carbon::parse($startDate)->format('d/m/Y')
            : Carbon::parse($startDate)->format('d/m/Y') . ' s/d ' . Carbon::parse($endDate)->format('d/m/Y');

        return [
            "📊 *Export Absensi Excel*\n🏢 {$deptName}\n📅 {$label}\n⏳ _Link berlaku 10 menit_",
            $url,
        ];
    }

    /**
     * Ambil IP lokal server (192.168.x.x) agar link bisa diakses dari device lain
     */
    protected function getServerLocalIp(): string
    {
        // Coba resolve via stream connection (tidak butuh extension sockets)
        try {
            $sock = @stream_socket_client('udp://8.8.8.8:80', $errno, $errstr, 1);
            if ($sock) {
                $ip = stream_socket_get_name($sock, false);
                fclose($sock);
                $ip = explode(':', $ip)[0]; // hapus port
                if ($ip && $ip !== '0.0.0.0' && $ip !== '127.0.0.1') return $ip;
            }
        } catch (\Exception $e) {}

        // Fallback: pakai APP_URL jika sudah diset ke IP lokal
        $urlHost = parse_url(config('app.url'), PHP_URL_HOST);
        if ($urlHost && $urlHost !== 'localhost' && $urlHost !== '127.0.0.1') {
            return $urlHost;
        }

        // Last resort
        $hostname = gethostname();
        $ip = $hostname ? gethostbyname($hostname) : '127.0.0.1';
        return ($ip && $ip !== $hostname) ? $ip : '127.0.0.1';
    }

    protected function buildHelpMessage(): string
        {
            return "🏨 *Grandhika Intern and Daily Worker Attendance*\n"
                . "Daftar perintah yang tersedia:\n\n"
                . "📊 *rekap* — rekap absensi hari ini\n"
                . "📊 *rekap 2026-03-15* — rekap tanggal tertentu\n"
                . "📥 *export* — download Excel hari ini\n"
                . "📥 *export 2026-03-15* — download Excel tanggal tertentu\n"
                . "📥 *export 2026-03-01 2026-03-16* — download Excel rentang tanggal\n"
                . "⚠️ *absen* — siapa yang belum absen hari ini\n"
                . "⏰ *terlambat* — siapa yang terlambat hari ini\n"
                . "❓ *help* — tampilkan menu ini\n"
                . "🔗 *link email@kamu.com* — hubungkan nomor WA ke akun\n\n"
                . "_Hanya admin terdaftar yang dapat menggunakan bot ini._";
        }
}
