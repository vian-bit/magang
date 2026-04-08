<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\EarlyCheckoutRequest;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $attendances = Attendance::with(['user', 'schedule.shift'])
            ->when($user->isAdmin(), function($q) use ($user) {
                $q->whereHas('user', function($query) use ($user) {
                    $query->where('department_id', $user->department_id);
                });
            })
            ->when($user->isUser(), function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->when($request->date, function($q) use ($request) {
                $q->whereDate('date', $request->date);
            })
            ->orderBy('date', 'desc')
            ->paginate(20);

        return view('attendances.index', compact('attendances'));
    }

    public function checkIn(Request $request)
    {
        $user = Auth::user();
        $today = today();
        
        $schedule = Schedule::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->with('shift')
            ->first();

        if (!$schedule) {
            return back()->with('error', 'You have no schedule today');
        }

        $existingAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($existingAttendance && $existingAttendance->check_in) {
            return back()->with('error', 'You have already checked in today');
        }

        $checkInTime = Carbon::now('Asia/Jakarta');
        $shiftStart = Carbon::createFromFormat('H:i:s', $schedule->shift->start_time, 'Asia/Jakarta');
        $shiftStart->setDate($checkInTime->year, $checkInTime->month, $checkInTime->day);

        $tolerance = $schedule->shift->tolerance_minutes;
        
        $status = $checkInTime->greaterThan($shiftStart->copy()->addMinutes($tolerance)) ? 'late' : 'present';

        Attendance::updateOrCreate(
            [
                'user_id' => $user->id,
                'schedule_id' => $schedule->id,
                'date' => $today,
            ],
            [
                'check_in' => $checkInTime->format('H:i:s'),
                'status' => $status,
            ]
        );

        return back()->with('success', 'Check-in successful at ' . $checkInTime->format('H:i:s'));
    }

    public function checkOut(Request $request)
    {
        $user = Auth::user();
        $today = today();
        
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->with('schedule.shift')
            ->first();

        if (!$attendance || !$attendance->check_in) {
            return back()->with('error', 'You have not checked in yet');
        }

        if ($attendance->check_out) {
            return back()->with('error', 'You have already checked out today');
        }

        $checkOutTime = Carbon::now('Asia/Jakarta');
        $shiftEnd = Carbon::createFromFormat('H:i:s', $attendance->schedule->shift->end_time, 'Asia/Jakarta');
        $shiftEnd->setDate($checkOutTime->year, $checkOutTime->month, $checkOutTime->day);

        // Cek apakah checkout sebelum jam shift selesai
        if ($checkOutTime->lessThan($shiftEnd)) {
            // Early checkout - butuh verifikasi admin
            
            // Cek apakah sudah ada request pending
            $existingRequest = EarlyCheckoutRequest::where('attendance_id', $attendance->id)
                ->whereIn('status', ['pending', 'rejected'])
                ->latest()
                ->first();

            if ($existingRequest) {
                if ($existingRequest->status === 'pending') {
                    return back()->with('info', 'Your early checkout request is waiting for admin approval');
                } elseif ($existingRequest->status === 'rejected') {
                    return back()->with('error', 'Your early checkout request was rejected. Please wait until shift ends at ' . $attendance->schedule->shift->end_time);
                }
            }

            // Buat request baru
            EarlyCheckoutRequest::create([
                'attendance_id' => $attendance->id,
                'user_id' => $user->id,
                'requested_checkout_time' => $checkOutTime->format('H:i:s'),
                'shift_end_time' => $attendance->schedule->shift->end_time,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            return back()->with('success', 'Early checkout request submitted. Waiting for admin approval.');
        }

        // Checkout normal (sudah lewat jam shift)
        $attendance->update([
            'check_out' => $checkOutTime->format('H:i:s'),
        ]);

        return back()->with('success', 'Check-out successful at ' . $checkOutTime->format('H:i:s'));
    }

    public function exportViaToken(Request $request, string $token)
    {
        $data = \Illuminate\Support\Facades\Cache::get("wa_export:{$token}");

        if (!$data) {
            abort(410, 'Link sudah kadaluarsa. Minta link baru via WhatsApp dengan perintah *export*.');
        }

        // Hapus token setelah dipakai (single-use)
        \Illuminate\Support\Facades\Cache::forget("wa_export:{$token}");

        $query = Attendance::with(['user.department', 'schedule.shift'])
            ->when($data['department_id'], fn($q) => $q->whereHas('user', fn($u) => $u->where('department_id', $data['department_id'])))
            ->whereDate('date', '>=', $data['start_date'])
            ->whereDate('date', '<=', $data['end_date'])
            ->orderBy('date', 'desc');

        return $this->exportXlsx($query->get(), $data['start_date'], $data['end_date']);
    }

    public function export(Request $request)
    {
        $user = Auth::user();

        // Default ke hari ini kalau tidak ada filter
        $startDate = $request->start_date ?? today()->format('Y-m-d');
        $endDate   = $request->end_date   ?? today()->format('Y-m-d');

        $query = Attendance::with(['user.department', 'schedule.shift'])
            ->when($user->isAdmin(), function($q) use ($user) {
                $q->whereHas('user', function($query) use ($user) {
                    $query->where('department_id', $user->department_id);
                });
            })
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date', 'desc');

        if ($request->format === 'xlsx') {
            return $this->exportXlsx($query->get(), $startDate, $endDate);
        }

        $attendances = $query->get();
        return view('attendances.export', compact('attendances', 'startDate', 'endDate'));
    }

    private function exportXlsx($attendances, string $startDate, string $endDate, ?string $deptName = null)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Absensi');

        // Judul
        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', 'LAPORAN ABSENSI KARYAWAN');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:L2');
        $label = $startDate === $endDate
            ? \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y')
            : \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') . ' s/d ' . \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y');
        $sheet->setCellValue('A2', 'Periode: ' . $label . ($deptName ? ' | Dept: ' . $deptName : ''));
        $sheet->getStyle('A2')->getFont()->setItalic(true);
        $sheet->getStyle('A2')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Header kolom baris 4
        $headers = ['No','Tanggal','Nama','Departemen','Tipe','Shift','Jam Shift','Check In','Check Out','Durasi','Status','Keterangan'];
        $cols    = ['A','B','C','D','E','F','G','H','I','J','K','L'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue($cols[$i] . '4', $h);
        }
        $sheet->getStyle('A4:L4')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(22);

        // Data mulai baris 5
        $row = 5;
        $no  = 1;
        foreach ($attendances as $attendance) {
            $status = match($attendance->status) {
                'present' => 'Hadir',
                'late'    => 'Terlambat',
                'absent'  => 'Tidak Hadir',
                default   => ucfirst($attendance->status),
            };
            $userType = match($attendance->user->user_type ?? '') {
                'magang'       => 'Magang',
                'daily_worker' => 'Daily Worker',
                default        => '-',
            };

            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $attendance->date->format('d/m/Y'));
            $sheet->setCellValue('C' . $row, $attendance->user->name);
            $sheet->setCellValue('D' . $row, $attendance->user->department->name ?? '-');
            $sheet->setCellValue('E' . $row, $userType);
            $sheet->setCellValue('F' . $row, $attendance->schedule->shift->name);
            $sheet->setCellValue('G' . $row, $attendance->schedule->shift->start_time . ' - ' . $attendance->schedule->shift->end_time);
            $sheet->setCellValue('H' . $row, $attendance->check_in ?? '-');
            $sheet->setCellValue('I' . $row, $attendance->check_out ?? '-');

            // Hitung durasi check-in
            $durasi = '-';
            if ($attendance->check_in && $attendance->check_out) {
                $in  = \Carbon\Carbon::createFromFormat('H:i:s', $attendance->check_in);
                $out = \Carbon\Carbon::createFromFormat('H:i:s', $attendance->check_out);
                $diff = $in->diff($out);
                $durasi = $diff->h . 'j ' . $diff->i . 'm';
            }
            $sheet->setCellValue('J' . $row, $durasi);
            $sheet->setCellValue('K' . $row, $status);
            $sheet->setCellValue('L' . $row, $attendance->notes ?? '-');

            // Warna baris
            $bgColor = match($attendance->status) {
                'late'   => 'FEF9C3',
                'absent' => 'FEE2E2',
                default  => ($no % 2 === 0) ? 'F8FAFC' : 'FFFFFF',
            };
            $sheet->getStyle("A{$row}:L{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB($bgColor);

            // Warna & bold kolom status
            $statusColor = match($attendance->status) {
                'present' => '16A34A',
                'late'    => 'D97706',
                'absent'  => 'DC2626',
                default   => '000000',
            };
            $sheet->getStyle("K{$row}")->getFont()->setBold(true)->getColor()->setRGB($statusColor);

            // Border
            $sheet->getStyle("A{$row}:L{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                ->getColor()->setRGB('E2E8F0');

            $row++;
        }

        // Ringkasan
        $row++;
        $present = $attendances->where('status', 'present')->count();
        $late    = $attendances->where('status', 'late')->count();
        $absent  = $attendances->where('status', 'absent')->count();

        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", 'RINGKASAN');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        foreach ([['Hadir', $present, '16A34A'], ['Terlambat', $late, 'D97706'], ['Tidak Hadir', $absent, 'DC2626']] as [$lbl, $val, $clr]) {
            $sheet->setCellValue("A{$row}", $lbl);
            $sheet->setCellValue("B{$row}", $val);
            $sheet->getStyle("B{$row}")->getFont()->getColor()->setRGB($clr);
            $row++;
        }
        $sheet->setCellValue("A{$row}", 'Total');
        $sheet->setCellValue("B{$row}", $attendances->count());
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);

        // Auto-width & freeze
        foreach ($cols as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A5');

        $filename = 'laporan-absensi-' . $startDate . '.xlsx';
        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function earlyCheckoutRequests()
    {
        $user = Auth::user();
        
        $requests = EarlyCheckoutRequest::with(['user', 'attendance.schedule.shift'])
            ->whereHas('user', function($q) use ($user) {
                if ($user->isAdmin()) {
                    $q->where('department_id', $user->department_id);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('attendances.early-checkout-requests', compact('requests'));
    }

    public function approveEarlyCheckout(Request $request, EarlyCheckoutRequest $earlyCheckoutRequest)
    {
        $user = Auth::user();

        // Cek apakah admin berhak approve (sama departemen)
        if ($user->isAdmin() && $earlyCheckoutRequest->user->department_id !== $user->department_id) {
            return back()->with('error', 'You are not authorized to approve this request');
        }

        $earlyCheckoutRequest->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'admin_notes' => $request->admin_notes,
        ]);

        // Update attendance dengan checkout time
        $earlyCheckoutRequest->attendance->update([
            'check_out' => $earlyCheckoutRequest->requested_checkout_time,
        ]);

        return back()->with('success', 'Early checkout request approved');
    }

    public function rejectEarlyCheckout(Request $request, EarlyCheckoutRequest $earlyCheckoutRequest)
    {
        $user = Auth::user();

        // Cek apakah admin berhak reject (sama departemen)
        if ($user->isAdmin() && $earlyCheckoutRequest->user->department_id !== $user->department_id) {
            return back()->with('error', 'You are not authorized to reject this request');
        }

        $earlyCheckoutRequest->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'admin_notes' => $request->admin_notes,
        ]);

        return back()->with('success', 'Early checkout request rejected');
    }

    public function manualAttendance()
    {
        $user = Auth::user();
        
        // Get users based on role
        $users = User::where('role', 'user')
            ->when($user->isAdmin(), function($q) use ($user) {
                $q->where('department_id', $user->department_id);
            })
            ->where('is_active', true)
            ->with(['department', 'schedules' => function($q) {
                $q->whereDate('date', today())->with('shift');
            }])
            ->get();
        
        // Get today's attendances
        $attendances = Attendance::whereDate('date', today())
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->keyBy('user_id');
        
        return view('attendances.manual', compact('users', 'attendances'));
    }

    public function manualCheckIn(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'check_in_time' => 'required|date_format:H:i',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $today = today();
        
        // Check if user has schedule today
        $schedule = Schedule::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->with('shift')
            ->first();

        if (!$schedule) {
            return back()->with('error', 'User has no schedule today');
        }

        // Check if already checked in
        $existingAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($existingAttendance && $existingAttendance->check_in) {
            return back()->with('error', 'User has already checked in today');
        }

        // Calculate status
        $checkInTime = Carbon::createFromFormat('H:i', $validated['check_in_time'], 'Asia/Jakarta');
        $shiftStart = Carbon::createFromFormat('H:i:s', $schedule->shift->start_time, 'Asia/Jakarta');
        $tolerance = $schedule->shift->tolerance_minutes;
        
        $status = $checkInTime->greaterThan($shiftStart->addMinutes($tolerance)) ? 'late' : 'present';

        Attendance::updateOrCreate(
            [
                'user_id' => $user->id,
                'schedule_id' => $schedule->id,
                'date' => $today,
            ],
            [
                'check_in' => $validated['check_in_time'] . ':00',
                'status' => $status,
            ]
        );

        return back()->with('success', 'Manual check-in successful for ' . $user->name);
    }

    public function manualCheckOut(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'check_out_time' => 'required|date_format:H:i',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $today = today();
        
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (!$attendance || !$attendance->check_in) {
            return back()->with('error', 'User has not checked in yet');
        }

        if ($attendance->check_out) {
            return back()->with('error', 'User has already checked out today');
        }

        $attendance->update([
            'check_out' => $validated['check_out_time'] . ':00',
        ]);

        return back()->with('success', 'Manual check-out successful for ' . $user->name);
    }

    public function bulkCheckIn(Request $request)
    {
        $user = Auth::user();
        $today = today();
        $currentTime = Carbon::now('Asia/Jakarta')->format('H:i:s');
        
        // Get all users with schedule today
        $schedules = Schedule::whereDate('date', $today)
            ->whereHas('user', function($q) use ($user) {
                $q->where('role', 'user')
                  ->where('is_active', true);
                if ($user->isAdmin()) {
                    $q->where('department_id', $user->department_id);
                }
            })
            ->with(['user', 'shift'])
            ->get();

        $successCount = 0;
        $skipCount = 0;

        foreach ($schedules as $schedule) {
            // Check if already checked in
            $existingAttendance = Attendance::where('user_id', $schedule->user_id)
                ->whereDate('date', $today)
                ->first();

            if ($existingAttendance && $existingAttendance->check_in) {
                $skipCount++;
                continue;
            }

            // Calculate status
            $checkInTime = Carbon::now('Asia/Jakarta');
            $shiftStart = Carbon::createFromFormat('H:i:s', $schedule->shift->start_time, 'Asia/Jakarta');
            $shiftStart->setDate($checkInTime->year, $checkInTime->month, $checkInTime->day);
            $tolerance = $schedule->shift->tolerance_minutes;
            
            $status = $checkInTime->greaterThan($shiftStart->addMinutes($tolerance)) ? 'late' : 'present';

            Attendance::updateOrCreate(
                [
                    'user_id' => $schedule->user_id,
                    'schedule_id' => $schedule->id,
                    'date' => $today,
                ],
                [
                    'check_in' => $currentTime,
                    'status' => $status,
                ]
            );

            $successCount++;
        }

        return back()->with('success', "Bulk check-in completed: {$successCount} users checked in, {$skipCount} users skipped (already checked in)");
    }

    public function bulkCheckOut(Request $request)
    {
        $user = Auth::user();
        $today = today();
        $currentTime = Carbon::now('Asia/Jakarta')->format('H:i:s');
        
        // Get all attendances today that haven't checked out
        $attendances = Attendance::whereDate('date', $today)
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->whereHas('user', function($q) use ($user) {
                $q->where('role', 'user')
                  ->where('is_active', true);
                if ($user->isAdmin()) {
                    $q->where('department_id', $user->department_id);
                }
            })
            ->get();

        $successCount = $attendances->count();

        foreach ($attendances as $attendance) {
            $attendance->update([
                'check_out' => $currentTime,
            ]);
        }

        return back()->with('success', "Bulk check-out completed: {$successCount} users checked out");
    }
}
