@php
    $words = preg_split('/\s+/', trim((string) $user->name));
    $initials = count($words) >= 2
        ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1))
        : strtoupper(substr((string) $user->name, 0, 2));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Portal Scanner · DRC Stock Control</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--ink);
            touch-action: manipulation;
        }
        .portal-header {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 54px;
            padding: 0 14px;
            background: var(--side);
            color: #fff;
            border-bottom: 2px solid var(--accent);
        }
        .portal-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--r);
            transition: border-color 0.14s ease, box-shadow 0.14s ease, transform 0.1s ease;
        }
        .portal-card:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 12px -4px rgba(15, 30, 51, 0.14);
        }
        .portal-card:active {
            transform: scale(0.99);
        }
        .portal-ic {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            border-radius: var(--r);
            background: var(--info-bg);
            color: var(--accent);
            border: 1px solid #C5D9EE;
            display: grid;
            place-items: center;
        }
        .portal-ic > .ic {
            width: 21px;
            height: 21px;
        }
    </style>
</head>
<body class="min-h-screen pb-10">
    <header class="portal-header">
        <div class="flex items-center gap-2.5 min-w-0">
            <span class="sb-mark">D</span>
            <div class="leading-tight min-w-0">
                <div class="text-[13.5px] font-semibold text-white truncate">Portal Scanner</div>
                <div class="text-[10.5px] text-slate-300 truncate">PT. Dian Raya Cipta</div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if ($user->isPpic())
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1.5 rounded bg-white/10 text-white hover:bg-white/20">
                    <x-icon name="grid" />
                    <span>Desktop</span>
                </a>
            @endif
            <a href="{{ route('profile.edit') }}"
               class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1.5 rounded bg-white/10 text-slate-100 hover:bg-white/20">
                <span>Profil</span>
            </a>
        </div>
    </header>

    <main class="max-w-lg mx-auto p-3.5 space-y-4">
        <x-flash />

        {{-- KARTU IDENTITAS PETUGAS --}}
        <section class="panel">
            <div class="panel-body flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="avatar" style="width:38px;height:38px;font-size:13px">{{ $initials }}</span>
                    <div class="min-w-0 leading-tight">
                        <div class="text-sm font-bold text-slate-900 truncate">{{ $user->name }}</div>
                        <div class="text-xs text-slate-500 truncate mt-0.5">
                            @if ($user->username)
                                <span class="num font-semibold text-slate-700">{{ '@' . $user->username }}</span> ·
                            @endif
                            <span>{{ $user->email }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-1 flex-none">
                    @if ($user->isPpic())
                        <span class="pill pill-info">PPIC</span>
                    @elseif ($user->isStaffMaterial())
                        <span class="pill pill-warn">STAFF MATERIAL</span>
                    @else
                        <span class="pill pill-good">STAFF FG</span>
                    @endif
                    <span class="text-[10.5px] font-semibold text-emerald-700">AKUN AKTIF</span>
                </div>
            </div>
        </section>

        {{-- DAFTAR MODUL SCANNER SESUAI ROLE --}}
        <section class="space-y-2.5">
            <div class="flex items-center justify-between px-0.5">
                <h1 class="text-xs font-bold uppercase tracking-wider text-slate-600">Pilih Modul Scan QR</h1>
                <span class="count-chip"><span>{{ count($modules) }}</span><span>MODUL</span></span>
            </div>

            <div class="grid grid-cols-1 gap-2.5">
                @foreach ($modules as $mod)
                    <a href="{{ $mod['url'] }}" class="portal-card">
                        <span class="portal-ic">
                            <x-icon :name="$mod['icon']" :flip="!empty($mod['flip'])" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-bold text-slate-900">{{ $mod['label'] }}</span>
                                <span class="pill {{ $mod['pill'] }}">{{ $mod['badge'] }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5 leading-snug">{{ $mod['desc'] }}</p>
                        </div>
                        <span class="text-slate-400 flex-none">
                            <x-icon name="chevron-right" />
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- AKSI AKUN BAWAH --}}
        <section class="panel">
            <div class="panel-body flex flex-wrap items-center justify-between gap-2.5">
                <a href="{{ route('profile.edit') }}" class="btn flex-1 justify-center">
                    <span>Profil &amp; Ganti Password</span>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <button type="submit" class="btn w-full justify-center" style="color:var(--crit);border-color:#FECACA;background:#FFF5F5">
                        <x-icon name="logout" />
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>