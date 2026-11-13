@php
    $u = Auth::user();
    $uname = $u->name ?: $u->username;
    $current = collect(config('plantline_menu'))->flatMap(fn ($g) => $g['items'])
                ->first(fn ($i) => request()->routeIs($i['match']));
    $pageLabel = $current['label'] ?? (request()->routeIs('profile.*') ? 'Profil' : 'Halaman');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageLabel }} · DRC Stock Control</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script>
        (function () {
            try {
                if (localStorage.getItem('drc_sb_mini') === '1') {
                    document.documentElement.classList.add('sb-collapsed');
                }
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{
        nav: false,
        mini: localStorage.getItem('drc_sb_mini') === '1',
        confirmOpen: false,
        confirmTitle: 'Konfirmasi Tindakan',
        confirmMessage: '',
        confirmForm: null,
        toggleMini() {
            this.mini = !this.mini;
            localStorage.setItem('drc_sb_mini', this.mini ? '1' : '0');
            document.documentElement.classList.toggle('sb-collapsed', this.mini);
        },
        askConfirm(formEl, message, title = 'Konfirmasi Sistem') {
            this.confirmForm = formEl;
            this.confirmMessage = message;
            this.confirmTitle = title;
            this.confirmOpen = true;
        },
        proceedConfirm() {
            if (this.confirmForm) {
                this.confirmForm.dataset.confirmed = '1';
                this.confirmForm.submit();
            }
            this.confirmOpen = false;
        }
    }"
    @keydown.escape.window="nav = false; confirmOpen = false"
    @open-confirm.window="askConfirm($event.detail.form, $event.detail.message, $event.detail.title || 'Konfirmasi Sistem')">

<div class="app-shell">
    <div x-show="nav" x-cloak @click="nav = false" class="fixed inset-0 z-30 bg-slate-950/50 lg:hidden"></div>

    <aside class="sidebar fixed inset-y-0 left-0 z-40 lg:static lg:translate-x-0"
           :class="nav ? 'translate-x-0' : '-translate-x-full'">
        <div class="sb-brand">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="sb-mark">D</span>
                <span class="sb-name">DRC Stock Control</span>
            </div>
            <button type="button"
                    @click="toggleMini()"
                    class="hidden lg:grid place-items-center w-7 h-7 rounded text-slate-300 hover:text-white hover:bg-white/10 flex-none"
                    :title="mini ? 'Perlebar Sidebar' : 'Perkecil Sidebar'">
                <x-icon name="chevron-left" class="w-4 h-4 transition-transform" ::class="mini ? 'rotate-180' : ''" />
            </button>
        </div>

        <nav class="sb-nav" aria-label="Menu utama">
            @include('layouts.navigation')
        </nav>

        <div class="sb-foot">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sb-item" title="Keluar">
                    <x-icon name="logout" />
                    <span>Keluar</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0">
        <header class="topbar">
            <button type="button" class="icon-btn lg:hidden" @click="nav = true" aria-label="Buka menu">
                <x-icon name="menu" />
            </button>

            <div class="crumb">
                <span>PT. Dian Raya Cipta</span><span>/</span><b>{{ $pageLabel }}</b>
            </div>

            <a href="{{ route('profile.edit') }}" class="user-chip" title="Profil Pengguna">
                <span class="avatar">{{ strtoupper(substr($uname, 0, 2)) }}</span>
                <span class="hidden sm:flex flex-col leading-tight">
                    <b class="text-[12.5px] font-semibold" style="color:var(--ink)">{{ $uname }}</b>
                    <small class="text-[11px]" style="color:var(--mut)">{{ $u->role }}</small>
                </span>
            </a>
        </header>

        <main class="flex-1 overflow-y-auto" id="main-scroll-area">
            <div class="page">
                @isset($header)
                    <div class="page-head">{{ $header }}</div>
                @endisset
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

{{-- Modal Konfirmasi Sistem --}}
<div x-show="confirmOpen" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/60 p-4 !mt-0" @click.self="confirmOpen = false">
    <div class="panel w-full max-w-md overflow-hidden shadow-lg">
        <div class="panel-head">
            <h3 class="panel-title" x-text="confirmTitle"></h3>
        </div>
        <div class="panel-body">
            <p class="text-xs text-slate-700 leading-relaxed" x-text="confirmMessage"></p>
        </div>
        <div class="panel-foot">
            <button type="button" class="btn" @click="confirmOpen = false">Batal</button>
            <button type="button" class="btn btn-primary" @click="proceedConfirm()">Ya, Lanjutkan</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let activePollController = null;

    window.addEventListener('beforeunload', function () {
        if (activePollController) activePollController.abort();
    });

    setInterval(function () {
        if (document.hidden) return;
        const activeEl = document.activeElement;
        const isTyping = activeEl && ['INPUT', 'SELECT', 'TEXTAREA'].includes(activeEl.tagName);
        if (isTyping) return;

        const openModals = document.querySelectorAll('.fixed.inset-0:not([style*="display: none"])');
        if (openModals.length > 0) return;

        if (activePollController) activePollController.abort();
        activePollController = new AbortController();

        fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: activePollController.signal
        })
        .then(function (r) { return r.ok ? r.text() : null; })
        .then(function (html) {
            if (!html) return;
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const newTables = doc.querySelectorAll('.tbl tbody');
            const curTables = document.querySelectorAll('.tbl tbody');
            if (newTables.length === curTables.length) {
                curTables.forEach(function (tb, idx) {
                    if (tb.innerHTML !== newTables[idx].innerHTML) {
                        tb.innerHTML = newTables[idx].innerHTML;
                        window.dispatchEvent(new CustomEvent('table-refreshed'));
                    }
                });
            }
        })
        .catch(function () {});
    }, 15000);
});
</script>
</body>
</html>