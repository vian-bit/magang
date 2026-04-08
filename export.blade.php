@extends('layouts.app')

@section('title', 'Export Attendance Report')

@section('content')
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-xl md:text-2xl font-bold mb-4 md:mb-6">Export Attendance Report</h1>

    <form method="GET" action="{{ route('attendances.export') }}" class="mb-4 md:mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-4">
            <div>
                <label class="block text-gray-700 mb-2 text-sm md:text-base">Start Date</label>
                <input type="date" name="start_date" value="{{ $startDate }}" 
                    class="w-full px-3 md:px-4 py-2 border rounded-lg text-sm md:text-base">
            </div>
            <div>
                <label class="block text-gray-700 mb-2 text-sm md:text-base">End Date</label>
                <input type="date" name="end_date" value="{{ $endDate }}" 
                    class="w-full px-3 md:px-4 py-2 border rounded-lg text-sm md:text-base">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-blue-600 text-white px-4 md:px-6 py-2 rounded-lg hover:bg-blue-700 text-sm md:text-base">
                    Filter
                </button>
                <a href="{{ route('attendances.export') }}" class="flex-1 bg-gray-300 text-gray-700 px-4 md:px-6 py-2 rounded-lg hover:bg-gray-400 text-center text-sm md:text-base">
                    Reset
                </a>
            </div>
        </div>
    </form>

    <div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-3 md:p-4">
        <h2 class="text-lg md:text-xl font-semibold mb-2">
            Report Period: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
            @if($startDate !== $endDate) — {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }} @endif
        </h2>
        <div class="flex flex-col md:flex-row gap-2 md:gap-3 mt-3 md:mt-4">
            <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-sm md:text-base">
                🖨️ Print / Save as PDF
            </button>
            <a href="{{ route('attendances.export', ['start_date' => $startDate, 'end_date' => $endDate, 'format' => 'xlsx']) }}" 
               class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-center text-sm md:text-base">
                📊 Download Excel (.xlsx)
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border text-sm md:text-base">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-2 md:px-4 py-2 border text-left">No</th>
                    <th class="px-2 md:px-4 py-2 border text-left">Date</th>
                    <th class="px-2 md:px-4 py-2 border text-left">Name</th>
                    <th class="px-2 md:px-4 py-2 border text-left hidden lg:table-cell">Department</th>
                    <th class="px-2 md:px-4 py-2 border text-left hidden md:table-cell">Type</th>
                    <th class="px-2 md:px-4 py-2 border text-left">Shift</th>
                    <th class="px-2 md:px-4 py-2 border text-left">Check In</th>
                    <th class="px-2 md:px-4 py-2 border text-left">Check Out</th>
                    <th class="px-2 md:px-4 py-2 border text-left hidden md:table-cell">Durasi</th>
                    <th class="px-2 md:px-4 py-2 border text-left">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendances as $index => $attendance)
                <tr class="border-b">
                    <td class="px-2 md:px-4 py-2 border">{{ $index + 1 }}</td>
                    <td class="px-2 md:px-4 py-2 border">{{ $attendance->date->format('d/m/Y') }}</td>
                    <td class="px-2 md:px-4 py-2 border">{{ $attendance->user->name }}</td>
                    <td class="px-2 md:px-4 py-2 border hidden lg:table-cell">{{ $attendance->user->department->name }}</td>
                    <td class="px-2 md:px-4 py-2 border hidden md:table-cell">
                        @if($attendance->user->user_type)
                            {{ $attendance->user->user_type === 'magang' ? 'Intern' : 'Daily Worker' }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-2 md:px-4 py-2 border">{{ $attendance->schedule->shift->name }}</td>
                    <td class="px-2 md:px-4 py-2 border">{{ $attendance->check_in ?? '-' }}</td>
                    <td class="px-2 md:px-4 py-2 border">{{ $attendance->check_out ?? '-' }}</td>
                    <td class="px-2 md:px-4 py-2 border hidden md:table-cell">
                        @if($attendance->check_in && $attendance->check_out)
                            @php
                                $in  = \Carbon\Carbon::createFromFormat('H:i:s', $attendance->check_in);
                                $out = \Carbon\Carbon::createFromFormat('H:i:s', $attendance->check_out);
                                $diff = $in->diff($out);
                            @endphp
                            {{ $diff->h }}j {{ $diff->i }}m
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-2 md:px-4 py-2 border">
                        @if($attendance->status == 'present')
                            <span class="text-green-600">Present</span>
                        @elseif($attendance->status == 'late')
                            <span class="text-yellow-600">Late</span>
                        @else
                            <span class="text-red-600">Absent</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-2 md:px-4 py-4 text-center text-gray-500">No data found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 md:mt-6 grid grid-cols-3 gap-3 md:gap-4">
        <div class="bg-green-100 p-3 md:p-4 rounded">
            <div class="text-xl md:text-2xl font-bold text-green-600">
                {{ $attendances->where('status', 'present')->count() }}
            </div>
            <div class="text-xs md:text-base text-gray-600">Present</div>
        </div>
        <div class="bg-yellow-100 p-3 md:p-4 rounded">
            <div class="text-xl md:text-2xl font-bold text-yellow-600">
                {{ $attendances->where('status', 'late')->count() }}
            </div>
            <div class="text-xs md:text-base text-gray-600">Late</div>
        </div>
        <div class="bg-red-100 p-3 md:p-4 rounded">
            <div class="text-xl md:text-2xl font-bold text-red-600">
                {{ $attendances->where('status', 'absent')->count() }}
            </div>
            <div class="text-xs md:text-base text-gray-600">Absent</div>
        </div>
    </div>

</div>

<style>
@media print {
    nav, aside, button, .no-print, form {
        display: none !important;
    }
    body {
        background: white;
    }
    .bg-blue-50 {
        background: white !important;
        border: none !important;
    }
}
</style>
@endsection
