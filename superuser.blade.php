@extends('layouts.app')

@section('title', 'Superuser Dashboard')

@section('content')
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 md:mb-6 gap-1">
        <h1 class="text-xl md:text-2xl font-bold">Superuser Dashboard</h1>
        <p class="text-gray-500 text-sm">{{ \Carbon\Carbon::now('Asia/Jakarta')->translatedFormat('l, d F Y') }}</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 mb-4 md:mb-6">
        <div class="bg-blue-100 p-4 md:p-6 rounded-lg">
            <div class="text-2xl md:text-3xl font-bold text-blue-600">{{ $totalDepartments }}</div>
            <div class="text-xs md:text-base text-gray-600">Total Departments</div>
        </div>
        <div class="bg-green-100 p-4 md:p-6 rounded-lg">
            <div class="text-2xl md:text-3xl font-bold text-green-600">{{ $totalAdmins }}</div>
            <div class="text-xs md:text-base text-gray-600">Total Admins</div>
        </div>
        <div class="bg-yellow-100 p-4 md:p-6 rounded-lg">
            <div class="text-2xl md:text-3xl font-bold text-yellow-600">{{ $totalUsers }}</div>
            <div class="text-xs md:text-base text-gray-600">Total Users</div>
        </div>
        <div class="bg-purple-100 p-4 md:p-6 rounded-lg">
            <div class="text-2xl md:text-3xl font-bold text-purple-600">{{ $todayAttendances }}</div>
            <div class="text-xs md:text-base text-gray-600">Today's Attendance</div>
        </div>
    </div>

    <div class="mt-4 md:mt-6">
        <h2 class="text-lg md:text-xl font-semibold mb-3 md:mb-4">Quick Menu</h2>
        
        @if($pendingEarlyCheckouts > 0)
        <div class="mb-4 bg-yellow-50 border-2 border-yellow-400 rounded-lg p-3 md:p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-yellow-800">🔔 Pending Early Checkout Requests</p>
                    <p class="text-sm text-yellow-700">You have {{ $pendingEarlyCheckouts }} request(s) waiting for approval</p>
                </div>
                <a href="{{ route('early-checkout.index') }}" class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 text-sm whitespace-nowrap">
                    View Requests
                </a>
            </div>
        </div>
        @endif
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
            <a href="{{ route('departments.index') }}" class="bg-blue-500 text-white p-4 md:p-6 rounded-lg text-center hover:bg-blue-600 transition-colors">
                <div class="text-2xl md:text-3xl mb-2">🏢</div>
                <div class="text-sm md:text-base">Manage Departments</div>
            </a>
            <a href="{{ route('users.index') }}" class="bg-green-500 text-white p-4 md:p-6 rounded-lg text-center hover:bg-green-600 transition-colors">
                <div class="text-2xl md:text-3xl mb-2">👥</div>
                <div class="text-sm md:text-base">Manage Users</div>
            </a>
            <a href="{{ route('shifts.index') }}" class="bg-yellow-500 text-white p-4 md:p-6 rounded-lg text-center hover:bg-yellow-600 transition-colors">
                <div class="text-2xl md:text-3xl mb-2">⏰</div>
                <div class="text-sm md:text-base">Manage Shifts</div>
            </a>
            <a href="{{ route('attendances.index') }}" class="bg-purple-500 text-white p-4 md:p-6 rounded-lg text-center hover:bg-purple-600 transition-colors">
                <div class="text-2xl md:text-3xl mb-2">✅</div>
                <div class="text-sm md:text-base">View Attendance</div>
            </a>
        </div>
    </div>
</div>

<!-- WhatsApp Status Widget -->
<div class="bg-white rounded-lg shadow p-4 md:p-6 mt-4" id="wa-widget">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="text-2xl">📱</span>
            <div>
                <div class="font-semibold">WhatsApp Status</div>
                <div class="text-sm text-gray-500" id="wa-status-text">Mengecek koneksi...</div>
            </div>
            <span id="wa-badge" class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">...</span>
        </div>
        <div class="flex gap-2">
            <a href="#" id="wa-qr-link" target="_blank" class="hidden bg-blue-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-600">
                Scan QR
            </a>
            <form method="POST" action="{{ route('whatsapp.send-report') }}">
                @csrf
                <button type="submit" id="wa-send-btn"
                    class="bg-green-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    disabled>
                    📤 Kirim Rekap Sekarang
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
    <div class="mt-3 bg-green-100 border border-green-400 text-green-700 px-3 py-2 rounded text-sm">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mt-3 bg-red-100 border border-red-400 text-red-700 px-3 py-2 rounded text-sm">
        {{ session('error') }}
    </div>
    @endif
</div>

<script>
async function checkWAStatus() {
    try {
        const res  = await fetch('{{ route("whatsapp.status") }}');
        const data = await res.json();
        const badge  = document.getElementById('wa-badge');
        const text   = document.getElementById('wa-status-text');
        const btn    = document.getElementById('wa-send-btn');
        const qrLink = document.getElementById('wa-qr-link');

        if (!data.server_running) {
            badge.className = 'px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700';
            badge.textContent = 'Server Mati';
            text.textContent = 'Jalankan START-WA-SERVER.bat terlebih dahulu';
        } else if (data.wa_connected) {
            badge.className = 'px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700';
            badge.textContent = '● Terhubung';
            text.textContent = 'WhatsApp siap mengirim pesan';
            btn.disabled = false;
            qrLink.classList.add('hidden');
        } else if (data.has_qr) {
            badge.className = 'px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700';
            badge.textContent = 'Perlu Scan QR';
            text.textContent = 'Klik "Scan QR" untuk menghubungkan WhatsApp';
            qrLink.href = data.qr_url;
            qrLink.classList.remove('hidden');
        } else {
            badge.className = 'px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700';
            badge.textContent = 'Menghubungkan...';
            text.textContent = 'Sedang menghubungkan ke WhatsApp';
        }
    } catch(e) {
        document.getElementById('wa-status-text').textContent = 'Tidak dapat mengecek status';
    }
}

checkWAStatus();
setInterval(checkWAStatus, 10000);
</script>
@endsection
