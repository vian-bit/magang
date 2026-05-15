<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'GranDhika Attendance')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Raleway:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ── Font ── */
        body { font-family: 'Raleway', sans-serif; }
        h1, h2, h3, .brand-font { font-family: 'Cinzel', serif; letter-spacing: 0.04em; }

        /* ── Color tokens ── */
        :root {
            --cream:   #F5F2EE;
            --warm-50: #FAF8F5;
            --warm-100:#F0EBE3;
            --warm-200:#DDD3C5;
            --brown-300:#C4A882;
            --brown-400:#A8845A;
            --brown-500:#8B6340;
            --brown-600:#6E4C2E;
            --brown-700:#52381F;
            --gray-mid: #9E9189;
            --gray-dark:#4A4039;
            --white:   #FFFFFF;
        }

        /* ── Sidebar ── */
        .sidebar-link {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; border-radius: 8px;
            color: var(--gray-dark); font-size: 0.875rem;
            transition: background 0.2s, color 0.2s, transform 0.15s;
        }
        .sidebar-link:hover {
            background: var(--warm-100);
            color: var(--brown-600);
            transform: translateX(3px);
        }
        .sidebar-link.active {
            background: var(--warm-200);
            color: var(--brown-700);
            font-weight: 600;
        }

        /* ── Buttons ── */
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 9px 20px; border-radius: 8px; font-size: 0.875rem;
            font-weight: 500; cursor: pointer; transition: all 0.2s;
            border: none; text-decoration: none;
        }
        .btn-primary {
            background: var(--brown-500); color: #fff;
            box-shadow: 0 2px 6px rgba(139,99,64,0.25);
        }
        .btn-primary:hover {
            background: var(--brown-600);
            box-shadow: 0 4px 12px rgba(139,99,64,0.35);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: var(--warm-100); color: var(--gray-dark);
            border: 1px solid var(--warm-200);
        }
        .btn-secondary:hover {
            background: var(--warm-200); color: var(--brown-700);
            transform: translateY(-1px);
        }
        .btn-success {
            background: #4a7c59; color: #fff;
            box-shadow: 0 2px 6px rgba(74,124,89,0.25);
        }
        .btn-success:hover {
            background: #3a6347;
            box-shadow: 0 4px 12px rgba(74,124,89,0.35);
            transform: translateY(-1px);
        }
        .btn-danger {
            background: transparent; color: #b91c1c;
            font-size: 0.8rem; padding: 4px 8px;
        }
        .btn-danger:hover { background: #fee2e2; border-radius: 6px; }
        .btn-edit {
            background: transparent; color: var(--brown-500);
            font-size: 0.8rem; padding: 4px 8px;
        }
        .btn-edit:hover { background: var(--warm-100); border-radius: 6px; }

        /* ── Table ── */
        .gh-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .gh-table thead tr { background: var(--warm-100); }
        .gh-table thead th {
            padding: 12px 16px; text-align: left;
            font-family: 'Cinzel', serif; font-size: 0.75rem;
            font-weight: 600; color: var(--brown-600);
            letter-spacing: 0.06em; text-transform: uppercase;
            border-bottom: 2px solid var(--warm-200);
        }
        .gh-table tbody tr {
            border-bottom: 1px solid var(--warm-100);
            transition: background 0.15s;
        }
        .gh-table tbody tr:hover { background: var(--warm-50); }
        .gh-table tbody td {
            padding: 12px 16px; color: var(--gray-dark);
            font-size: 0.875rem; vertical-align: middle;
        }

        /* ── Input / Select ── */
        .gh-input, .gh-select, .gh-textarea {
            width: 100%; padding: 10px 14px;
            border: 1px solid var(--warm-200); border-radius: 8px;
            background: var(--white); color: var(--gray-dark);
            font-family: 'Raleway', sans-serif; font-size: 0.875rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .gh-input:hover, .gh-select:hover, .gh-textarea:hover {
            border-color: var(--brown-300);
        }
        .gh-input:focus, .gh-select:focus, .gh-textarea:focus {
            border-color: var(--brown-400);
            box-shadow: 0 0 0 3px rgba(168,132,90,0.15);
        }

        /* ── Card ── */
        .gh-card {
            background: var(--white); border-radius: 12px;
            box-shadow: 0 2px 12px rgba(74,64,57,0.08);
            padding: 24px;
        }

        /* ── Badge ── */
        .badge {
            display: inline-block; padding: 3px 10px;
            border-radius: 20px; font-size: 0.72rem; font-weight: 600;
        }
        .badge-green  { background: #d1fae5; color: #065f46; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-red    { background: #fee2e2; color: #991b1b; }
        .badge-gray   { background: var(--warm-100); color: var(--gray-mid); }
        .badge-brown  { background: var(--warm-200); color: var(--brown-600); }

        /* ── Mobile menu ── */
        .mobile-menu { transform: translateX(-100%); transition: transform 0.3s ease; }
        .mobile-menu.active { transform: translateX(0); }
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 40; }
        .overlay.active { display: block; }

        /* ── Divider ── */
        .sidebar-divider { height: 1px; background: var(--warm-200); margin: 8px 0; }

        /* ── Alert ── */
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 0.875rem; margin-bottom: 16px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error   { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-info    { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; }
    </style>
</head>
<body style="background: var(--cream); min-height: 100vh;">
@auth

<!-- ── Top Navbar ── -->
<nav style="background: var(--brown-700); color: #fff; box-shadow: 0 2px 12px rgba(0,0,0,0.18);">
    <div class="px-4 lg:px-8">
        <div class="flex justify-between items-center py-3">
            <!-- Hamburger (mobile) -->
            <button onclick="toggleMenu()" class="lg:hidden p-2 rounded hover:bg-white/10 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <!-- Brand -->
            <div class="flex items-center gap-3">
                @if(file_exists(public_path('images/logo.png')))
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="w-8 h-8 object-contain brightness-0 invert opacity-90">
                @endif
                <div>
                    <div class="brand-font text-base lg:text-lg font-semibold tracking-widest" style="color: var(--brown-300);">GranDhika</div>
                    <div class="text-xs tracking-widest opacity-70" style="font-family:'Raleway',sans-serif; letter-spacing:0.15em;">ATTENDANCE SYSTEM</div>
                </div>
            </div>

            <!-- User info -->
            <div class="flex items-center gap-3">
                <a href="{{ route('profile.edit') }}" class="hidden sm:flex items-center gap-2 text-sm hover:text-amber-200 transition">
                    <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    {{ Auth::user()->name }}
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary text-xs px-3 py-1.5" style="background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.2);">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

<div class="overlay" id="overlay" onclick="toggleMenu()"></div>

<!-- ── Mobile Sidebar ── -->
<aside id="mobileMenu" class="mobile-menu fixed top-0 left-0 w-64 h-full z-50 lg:hidden overflow-y-auto"
    style="background: var(--white); box-shadow: 4px 0 20px rgba(0,0,0,0.12);">
    <div class="p-4 flex justify-between items-center" style="background: var(--brown-700);">
        <span class="brand-font text-sm tracking-widest" style="color: var(--brown-300);">MENU</span>
        <button onclick="toggleMenu()" class="p-1 rounded hover:bg-white/10 transition text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    <div class="p-3">
        @include('layouts._nav')
    </div>
</aside>

<!-- ── Main Layout ── -->
<div class="lg:container lg:mx-auto px-3 sm:px-4 py-5 lg:py-7">
    <div class="flex gap-5 lg:gap-7">

        <!-- Desktop Sidebar -->
        <aside class="hidden lg:block w-60 flex-shrink-0">
            <div class="gh-card sticky top-5" style="padding: 16px;">
                <!-- Role badge -->
                <div class="mb-4 px-2">
                    <div class="text-xs tracking-widest uppercase mb-1" style="color: var(--gray-mid); font-family:'Cinzel',serif;">
                        {{ ucfirst(Auth::user()->role) }}
                    </div>
                    <div class="text-sm font-semibold" style="color: var(--brown-700);">{{ Auth::user()->name }}</div>
                    @if(Auth::user()->department)
                    <div class="text-xs mt-0.5" style="color: var(--gray-mid);">{{ Auth::user()->department->name }}</div>
                    @endif
                </div>
                <div class="sidebar-divider"></div>
                @include('layouts._nav')
            </div>
        </aside>

        <!-- Content -->
        <main class="flex-1 min-w-0">
            @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if(session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

<script>
    function toggleMenu() {
        const menu = document.getElementById('mobileMenu');
        const overlay = document.getElementById('overlay');
        if (menu) menu.classList.toggle('active');
        if (overlay) overlay.classList.toggle('active');
    }
    document.addEventListener('DOMContentLoaded', () => {
        // Tutup menu saat link diklik
        document.querySelectorAll('#mobileMenu a').forEach(l => {
            l.addEventListener('click', () => {
                document.getElementById('mobileMenu')?.classList.remove('active');
                document.getElementById('overlay')?.classList.remove('active');
            });
        });
    });
</script>
@endauth

@guest
@yield('content')
@endguest
</body>
</html>
