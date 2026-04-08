@extends('layouts.app')

@section('title', 'Attendance Data')

@section('content')
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-4 md:mb-6 gap-3">
        <h1 class="text-xl md:text-2xl font-bold">Attendance Data</h1>
        @if(Auth::user()->isSuperuser() || Auth::user()->isAdmin())
        <a href="{{ route('attendances.export') }}" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-center text-sm md:text-base">
            Export Report
        </a>
        @endif
    </div>

    <div class="mb-4">
        <form method="GET" class="flex flex-col md:flex-row gap-3 md:gap-4">
            <input type="date" name="date" value="{{ request('date') }}" 
                class="px-3 md:px-4 py-2 border rounded-lg text-sm md:text-base">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm md:text-base">
                Filter
            </button>
            <a href="{{ route('attendances.index') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 text-center text-sm md:text-base">
                Reset
            </a>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white text-sm md:text-base">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-2 md:px-4 py-2 text-left">Date</th>
                    <th class="px-2 md:px-4 py-2 text-left">User</th>
                    <th class="px-2 md:px-4 py-2 text-left hidden lg:table-cell">Shift</th>
                    <th class="px-2 md:px-4 py-2 text-left">Check In</th>
                    <th class="px-2 md:px-4 py-2 text-left">Check Out</th>
                    <th class="px-2 md:px-4 py-2 text-left hidden md:table-cell">Durasi</th>
                    <th class="px-2 md:px-4 py-2 text-left">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendances as $attendance)
                <tr class="border-b">
                    <td class="px-2 md:px-4 py-2">{{ $attendance->date->format('d/m/Y') }}</td>
                    <td class="px-2 md:px-4 py-2">{{ $attendance->user->name }}</td>
                    <td class="px-2 md:px-4 py-2 hidden lg:table-cell">{{ $attendance->schedule->shift->name }}</td>
                    <td class="px-2 md:px-4 py-2">{{ $attendance->check_in ?? '-' }}</td>
                    <td class="px-2 md:px-4 py-2">{{ $attendance->check_out ?? '-' }}</td>
                    <td class="px-2 md:px-4 py-2 hidden md:table-cell">
                        @if($attendance->check_in && $attendance->check_out)
                            @php
                                $diff = \Carbon\Carbon::createFromFormat('H:i:s', $attendance->check_in)
                                    ->diff(\Carbon\Carbon::createFromFormat('H:i:s', $attendance->check_out));
                            @endphp
                            {{ $diff->h }}j {{ $diff->i }}m
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-2 md:px-4 py-2">
                        <span class="px-2 py-1 rounded text-xs md:text-sm
                            @if($attendance->status == 'present') bg-green-100 text-green-800
                            @elseif($attendance->status == 'late') bg-yellow-100 text-yellow-800
                            @else bg-red-100 text-red-800 @endif">
                            {{ ucfirst($attendance->status) }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-2 md:px-4 py-4 text-center text-gray-500">No attendance data found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $attendances->links() }}
    </div>
</div>
@endsection
