<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if ($user->isSuperuser()) {
            return $this->superuserDashboard();
        } elseif ($user->isAdmin()) {
            return $this->adminDashboard();
        } else {
            return $this->userDashboard();
        }
    }

    private function superuserDashboard()
    {
        $data = [
            'totalDepartments' => Department::count(),
            'totalAdmins' => User::where('role', 'admin')->count(),
            'totalUsers' => User::where('role', 'user')->count(),
            'todayAttendances' => Attendance::whereDate('date', today())->count(),
            'pendingEarlyCheckouts' => \App\Models\EarlyCheckoutRequest::where('status', 'pending')->count(),
        ];

        return view('dashboard.superuser', $data);
    }

    private function adminDashboard()
    {
        $user = Auth::user();
        
        $data = [
            'totalUsers' => User::where('department_id', $user->department_id)
                ->where('role', 'user')->count(),
            'todaySchedules' => Schedule::whereHas('user', function($q) use ($user) {
                $q->where('department_id', $user->department_id);
            })->whereDate('date', today())->count(),
            'todayPresent' => Attendance::whereHas('user', function($q) use ($user) {
                $q->where('department_id', $user->department_id);
            })->whereDate('date', today())->where('status', 'present')->count(),
            'todayLate' => Attendance::whereHas('user', function($q) use ($user) {
                $q->where('department_id', $user->department_id);
            })->whereDate('date', today())->where('status', 'late')->count(),
            'pendingEarlyCheckouts' => \App\Models\EarlyCheckoutRequest::whereHas('user', function($q) use ($user) {
                $q->where('department_id', $user->department_id);
            })->where('status', 'pending')->count(),
        ];

        return view('dashboard.admin', $data);
    }

    private function userDashboard()
    {
        $user = Auth::user()->load('department');
        
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', today())
            ->first();
        
        // Cek early checkout request untuk hari ini
        $earlyCheckoutRequest = null;
        if ($todayAttendance) {
            $earlyCheckoutRequest = \App\Models\EarlyCheckoutRequest::where('attendance_id', $todayAttendance->id)
                ->with('approvedBy')
                ->latest()
                ->first();
        }
        
        $data = [
            'todaySchedule' => Schedule::where('user_id', $user->id)
                ->whereDate('date', today())
                ->with('shift')
                ->first(),
            'todayAttendance' => $todayAttendance,
            'earlyCheckoutRequest' => $earlyCheckoutRequest,
            'recentAttendances' => Attendance::where('user_id', $user->id)
                ->orderBy('date', 'desc')
                ->limit(5)
                ->get(),
        ];

        return view('dashboard.user', $data);
    }
}
