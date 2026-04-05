@extends('layouts.app')

@section('title', 'Dashboard User')

@section('content')
<div class="bg-white rounded-lg shadow p-4 sm:p-6">
    <h1 class="text-xl sm:text-2xl font-bold mb-4 sm:mb-6">Dashboard - {{ Auth::user()->name }}</h1>

    <!-- Early Checkout Request Notifications -->
    @if($earlyCheckoutRequest)
        @if($earlyCheckoutRequest->status === 'pending')
        <div class="mb-4 bg-yellow-50 border-2 border-yellow-400 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <div class="text-2xl">⏳</div>
                <div class="flex-1">
                    <p class="font-semibold text-yellow-800">Early Checkout Request Pending</p>
                    <p class="text-sm text-yellow-700 mt-1">
                        Your request to checkout at {{ $earlyCheckoutRequest->requested_checkout_time }} is waiting for admin approval.
                    </p>
                    @if($earlyCheckoutRequest->reason)
                    <p class="text-xs text-yellow-600 mt-2">
                        <span class="font-semibold">Your reason:</span> {{ $earlyCheckoutRequest->reason }}
                    </p>
                    @endif
                </div>
            </div>
        </div>
        @elseif($earlyCheckoutRequest->status === 'approved')
        <div class="mb-4 bg-green-50 border-2 border-green-400 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <div class="text-2xl">✅</div>
                <div class="flex-1">
                    <p class="font-semibold text-green-800">Early Checkout Request Approved</p>
                    <p class="text-sm text-green-700 mt-1">
                        Your early checkout at {{ $earlyCheckoutRequest->requested_checkout_time }} has been approved by {{ $earlyCheckoutRequest->approvedBy->name }}.
                    </p>
                    @if($earlyCheckoutRequest->admin_notes)
                    <p class="text-xs text-green-600 mt-2">
                        <span class="font-semibold">Admin notes:</span> {{ $earlyCheckoutRequest->admin_notes }}
                    </p>
                    @endif
                </div>
            </div>
        </div>
        @elseif($earlyCheckoutRequest->status === 'rejected')
        <div class="mb-4 bg-red-50 border-2 border-red-400 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <div class="text-2xl">❌</div>
                <div class="flex-1">
                    <p class="font-semibold text-red-800">Early Checkout Request Rejected</p>
                    <p class="text-sm text-red-700 mt-1">
                        Your request to checkout at {{ $earlyCheckoutRequest->requested_checkout_time }} has been rejected by {{ $earlyCheckoutRequest->approvedBy->name }}.
                    </p>
                    <p class="text-sm text-red-700 mt-1">
                        Please wait until your shift ends at {{ $earlyCheckoutRequest->shift_end_time }} to checkout.
                    </p>
                    @if($earlyCheckoutRequest->admin_notes)
                    <p class="text-xs text-red-600 mt-2 bg-red-100 p-2 rounded">
                        <span class="font-semibold">Reason for rejection:</span> {{ $earlyCheckoutRequest->admin_notes }}
                    </p>
                    @endif
                </div>
            </div>
        </div>
        @endif
    @endif

    @if($todaySchedule)
    <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4 sm:p-6 mb-4 sm:mb-6">
        <h2 class="text-lg sm:text-xl font-semibold mb-3 sm:mb-4">Today's Schedule</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div>
                <p class="text-gray-600 text-sm">Shift</p>
                <p class="font-semibold text-lg">{{ $todaySchedule->shift->name }}</p>
            </div>
            <div>
                <p class="text-gray-600 text-sm">Working Hours</p>
                <p class="font-semibold text-lg">{{ $todaySchedule->shift->start_time }} - {{ $todaySchedule->shift->end_time }}</p>
            </div>
        </div>

        <div class="mt-4 sm:mt-6 space-y-3">
            @if(!$todayAttendance || !$todayAttendance->check_in)
            @php
                $shiftStartCarbon = \Carbon\Carbon::createFromFormat('H:i:s', $todaySchedule->shift->start_time, 'Asia/Jakarta');
                $earliestCheckIn = $shiftStartCarbon->copy()->subMinutes(30);
                $now = \Carbon\Carbon::now('Asia/Jakarta');
                $canCheckIn = $now->greaterThanOrEqualTo($earliestCheckIn);
            @endphp

            @if($canCheckIn)
            <button onclick="confirmCheckIn()" 
                class="w-full bg-green-500 text-white px-6 py-4 rounded-lg hover:bg-green-600 font-semibold text-lg shadow-lg active:scale-95 transition">
                ✓ Check In Now
            </button>
            @else
            <button disabled
                class="w-full bg-gray-300 text-gray-500 px-6 py-4 rounded-lg font-semibold text-lg shadow cursor-not-allowed">
                ✓ Check In Now
            </button>
            <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-3 text-center">
                <p class="text-yellow-800 text-sm font-semibold">⏰ Check-in opens at {{ $earliestCheckIn->format('H:i') }}</p>
                <p class="text-yellow-700 text-xs mt-1">30 minutes before shift start ({{ $shiftStartCarbon->format('H:i') }})</p>
                <p class="text-yellow-600 text-sm mt-1">Available in: <span id="checkin-countdown" class="font-bold"></span></p>
            </div>
            @endif

            <div class="text-center text-gray-600">
                <span id="current-time" class="font-semibold text-lg"></span>
            </div>
            
            <form method="POST" action="{{ route('attendances.checkin') }}" id="checkinForm" class="hidden">
                @csrf
            </form>
            @elseif(!$todayAttendance->check_out)
            <div class="bg-green-100 border border-green-300 rounded-lg p-4 mb-3">
                <p class="text-green-800 font-semibold text-center">
                    ✓ Checked In: {{ $todayAttendance->check_in }}
                </p>
            </div>

            @if($earlyCheckoutRequest && $earlyCheckoutRequest->status === 'pending')
            {{-- Tombol disabled saat request pending --}}
            <button disabled
                class="w-full bg-yellow-400 text-yellow-900 px-6 py-4 rounded-lg font-semibold text-lg shadow cursor-not-allowed opacity-80">
                ⏳ On Request — Menunggu Persetujuan Admin
            </button>
            <p class="text-center text-xs text-yellow-700 mt-1">
                Request checkout jam {{ $earlyCheckoutRequest->requested_checkout_time }} sedang diproses
            </p>
            @else
            <button onclick="showCheckOutModal()" 
                class="w-full bg-red-500 text-white px-6 py-4 rounded-lg hover:bg-red-600 font-semibold text-lg shadow-lg active:scale-95 transition">
                ✓ Check Out Now
            </button>
            @endif

            <div class="text-center text-gray-600 mt-2">
                <span class="text-sm text-gray-500">Working Time: </span><span id="work-duration" class="font-semibold text-lg"></span>
            </div>
            
            <form method="POST" action="{{ route('attendances.checkout') }}" id="checkoutForm" class="hidden">
                @csrf
                <input type="hidden" name="reason" id="checkoutReason">
            </form>
            @else
            <div class="bg-gray-100 border border-gray-300 rounded-lg p-4 space-y-2">
                <p class="text-gray-700 font-semibold">✓ Check In: {{ $todayAttendance->check_in }}</p>
                <p class="text-gray-700 font-semibold">✓ Check Out: {{ $todayAttendance->check_out }}</p>
                <p class="text-green-600 font-semibold text-center mt-2">Attendance Complete!</p>
            </div>
            @endif
        </div>
    </div>
    @else
    <div class="bg-yellow-50 border-2 border-yellow-200 rounded-lg p-4 sm:p-6 mb-4 sm:mb-6">
        <p class="text-yellow-800 text-center font-semibold">You have no schedule today.</p>
    </div>
    @endif

    <div class="mt-4 sm:mt-6">
        <h2 class="text-lg sm:text-xl font-semibold mb-3 sm:mb-4">Recent Attendance History</h2>
        <div class="overflow-x-auto -mx-4 sm:mx-0">
            <div class="inline-block min-w-full align-middle">
                <table class="min-w-full bg-white text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Check In</th>
                            <th class="px-3 py-2 text-left">Check Out</th>
                            <th class="px-3 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentAttendances as $attendance)
                        <tr class="border-b">
                            <td class="px-3 py-2 whitespace-nowrap">{{ $attendance->date->format('d/m/Y') }}</td>
                            <td class="px-3 py-2">{{ $attendance->check_in ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $attendance->check_out ?? '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded text-xs font-semibold
                                    @if($attendance->status == 'present') bg-green-100 text-green-800
                                    @elseif($attendance->status == 'late') bg-yellow-100 text-yellow-800
                                    @else bg-red-100 text-red-800 @endif">
                                    {{ ucfirst($attendance->status) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-gray-500">No attendance history yet</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Early Checkout -->
<div id="checkoutModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg p-6 max-w-md w-full">
        <h3 class="text-xl font-bold mb-4">Check Out Confirmation</h3>
        
        <div id="earlyCheckoutWarning" class="hidden bg-yellow-50 border border-yellow-300 rounded p-3 mb-4">
            <p class="text-yellow-800 text-sm">
                ⚠️ You are checking out before shift end time. This requires admin approval.
            </p>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 mb-2">Reason (optional for early checkout):</label>
            <textarea id="reasonInput" rows="3" 
                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                placeholder="Enter reason if checking out early..."></textarea>
        </div>

        <div class="flex gap-3">
            <button onclick="submitCheckOut()" 
                class="flex-1 bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">
                Confirm Check Out
            </button>
            <button onclick="closeCheckOutModal()" 
                class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
@if($todayAttendance && $todayAttendance->check_in && !$todayAttendance->check_out)
const checkInTimestamp = {{ \Carbon\Carbon::parse($todayAttendance->date->format('Y-m-d') . ' ' . $todayAttendance->check_in)->timestamp }};
const shiftEndTimestamp = {{ \Carbon\Carbon::parse($todayAttendance->date->format('Y-m-d') . ' ' . $todayAttendance->schedule->shift->end_time)->timestamp }};
let autoCheckoutDone = false;
@endif

function updateTime() {
    const durationElement = document.getElementById('work-duration');
    if (durationElement && typeof checkInTimestamp !== 'undefined') {
        const nowSec = Math.floor(Date.now() / 1000);
        const diffSec = Math.max(0, nowSec - checkInTimestamp);
        const h = String(Math.floor(diffSec / 3600)).padStart(2, '0');
        const m = String(Math.floor((diffSec % 3600) / 60)).padStart(2, '0');
        const s = String(diffSec % 60).padStart(2, '0');
        durationElement.textContent = `${h}:${m}:${s}`;

        // Auto checkout: 1 menit setelah shift selesai
        if (!autoCheckoutDone && typeof shiftEndTimestamp !== 'undefined') {
            if (nowSec >= shiftEndTimestamp + 60) {
                autoCheckoutDone = true;
                document.getElementById('checkoutForm').submit();
            }
        }
    }
}

function confirmCheckIn() {
    if (confirm('Check in now?')) {
        document.getElementById('checkinForm').submit();
    }
}

function showCheckOutModal() {
    const modal = document.getElementById('checkoutModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Check if early checkout
    @if($todaySchedule)
    const now = new Date();
    const currentTime = now.getHours() * 60 + now.getMinutes();
    const shiftEndTime = '{{ $todaySchedule->shift->end_time }}';
    const [endHour, endMinute] = shiftEndTime.split(':').map(Number);
    const shiftEndMinutes = endHour * 60 + endMinute;
    
    if (currentTime < shiftEndMinutes) {
        document.getElementById('earlyCheckoutWarning').classList.remove('hidden');
    }
    @endif
}

function closeCheckOutModal() {
    const modal = document.getElementById('checkoutModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.getElementById('reasonInput').value = '';
    document.getElementById('earlyCheckoutWarning').classList.add('hidden');
}

function submitCheckOut() {
    const reason = document.getElementById('reasonInput').value;
    document.getElementById('checkoutReason').value = reason;
    document.getElementById('checkoutForm').submit();
}

// Update every second
setInterval(updateTime, 1000);
updateTime();

@if(!$todayAttendance || !$todayAttendance->check_in)
@php
    $earliestTs = isset($earliestCheckIn) ? $earliestCheckIn->timestamp : null;
@endphp
@if(isset($earliestCheckIn) && !$canCheckIn)
const earliestCheckInTs = {{ $earliestCheckIn->timestamp }};
function updateCountdown() {
    const countdownEl = document.getElementById('checkin-countdown');
    if (!countdownEl) return;
    const nowSec = Math.floor(Date.now() / 1000);
    const diff = earliestCheckInTs - nowSec;
    if (diff <= 0) {
        location.reload();
        return;
    }
    const m = String(Math.floor(diff / 60)).padStart(2, '0');
    const s = String(diff % 60).padStart(2, '0');
    countdownEl.textContent = `${m}:${s}`;
}
setInterval(updateCountdown, 1000);
updateCountdown();
@endif
@endif
</script>
@endsection
