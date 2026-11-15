@php
    $u = Auth::user();
    $uname = $u->username ?: $u->name;
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
            function syncSidebarState() {
                try {
                    if (localStorage.getItem('drc_sb_collapsed') === '1') {
                        document.documentElement.classList.add('sb-collapsed');
                    } else {
                        document.documentElement.classList.remove('sb-collapsed');
                    }
                } catch (e) {}
            }

            syncSidebarState();

            // Menjamin saat menekan Alt + Left Arrow / Right Arrow (BFCache), kondisi sidebar tetap konsisten
            window.addEventListener('pageshow', function () {
                syncSidebarState();
                window.dispatchEvent(new CustomEvent('sb-sync'));
            });

            window.addEventListener('popstate', function () {
                syncSidebarState();
                window.dispatchEvent(new CustomEvent('sb-sync'));
            });

            window.addEventListener('storage', function (e) {
                if (e.key === 'drc_sb_collapsed') {
                    syncSidebarState();
                    window.dispatchEvent(new CustomEvent('sb-sync'));
                }
            });

            // Aktifkan animasi slide smooth setelah halaman selesai digambar
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    document.documentElement.classList.add('sb-ready');
                });
            });
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{
        nav: false,
        sbCollapsed: localStorage.getItem('drc_sb_collapsed') === '1',
        confirmModal: { open: false, title: '', message: '', form: null },
        toggleSidebar() {
            document.documentElement.classList.add('sb-ready');
            this.sbCollapsed = !this.sbCollapsed;
            if (this.sbCollapsed) {
                document.documentElement.classList.add('sb-collapsed');
                localStorage.setItem('drc_sb_collapsed', '1');
            } else {
                document.documentElement.classList.remove('sb-collapsed');
                localStorage.setItem('drc_sb_collapsed', '0');
            }
        },
        openConfirm(detail) {
            this.confirmModal.title = detail.title || 'Konfirmasi Tindakan';
            this.confirmModal.message = detail.message || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
            this.confirmModal.form = detail.form || null;
            this.confirmModal.open = true;
        },
        proceedConfirm() {
            if (this.confirmModal.form) {
                this.confirmModal.form.submit();
            }
            this.confirmModal.open = false;
        }
      }"
      @sb-sync.window="sbCollapsed = localStorage.getItem('drc_sb_collapsed') === '1'"
      @open-confirm.window="openConfirm($event.detail)"
      @keydown.escape.window="nav = false; confirmModal.open = false">

<div class="app-shell">
    <div x-show="nav" x-cloak @click="nav = false" class="fixed inset-0 z-30 bg-slate-950/50 lg:hidden"></div>

    <aside class="sidebar fixed inset-y-0 left-0 z-40 transition-transform lg:static lg:translate-x-0"
           :class="nav ? 'translate-x-0' : '-translate-x-full'">
        <div class="sb-brand">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="sb-mark">D</span>
                <span class="sb-name">DRC Stock Control</span>
            </div>
            <button type="button"
                    @click="toggleSidebar()"
                    class="hidden lg:grid place-items-center w-7 h-7 rounded text-slate-300 hover:text-white hover:bg-white/10 flex-none transition-colors"
                    :title="sbCollapsed ? 'Perlebar Sidebar' : 'Perkecil Sidebar'">
                <x-icon x-show="!sbCollapsed" name="chevron-left" />
                <x-icon x-show="sbCollapsed" x-cloak name="chevron-right" />
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

            <a href="{{ route('profile.edit') }}" class="user-chip" title="Profil saya">
                <span class="avatar">{{ strtoupper(substr($uname, 0, 2)) }}</span>
                <span class="hidden sm:flex flex-col leading-tight">
                    <b class="text-[12.5px] font-semibold" style="color:var(--ink)">{{ $uname }}</b>
                    <small class="text-[11px]" style="color:var(--mut)">{{ $u->role }}</small>
                </span>
            </a>
        </header>

        <main class="flex-1 overflow-y-auto" id="main-content">
            <div class="page">
                @isset($header)
                    <div class="page-head">{{ $header }}</div>
                @endisset
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

{{-- POP-UP KONFIRMASI SISTEM --}}
<div x-show="confirmModal.open" x-cloak
     class="modal-backdrop"
     @click.self="confirmModal.open = false">
    <div class="panel w-full max-w-md overflow-hidden">
        <div class="panel-head">
            <h3 class="panel-title" x-text="confirmModal.title"></h3>
            <button type="button" @click="confirmModal.open = false" class="act-btn" aria-label="Tutup">
                <x-icon name="x" />
            </button>
        </div>
        <div class="panel-body">
            <p class="text-xs text-slate-600 leading-relaxed" x-text="confirmModal.message"></p>
        </div>
        <div class="panel-foot">
            <button type="button" class="btn" @click="confirmModal.open = false">Batal</button>
            <button type="button" class="btn btn-primary" @click="proceedConfirm()">Ya, Lanjutkan</button>
        </div>
    </div>
</div>

{{-- GLOBAL SEARCHABLE DROPDOWN ENGINE (Mendukung semua <select> di dalam & luar form) --}}
<div id="drc-sselect-popover" class="sselect-popover" style="display: none;">
    <div class="sselect-search-wrap">
        <input type="text" id="drc-sselect-input" class="sselect-search" placeholder="Ketik untuk mencari..." autocomplete="off">
    </div>
    <div id="drc-sselect-list" class="sselect-list"></div>
</div>

<script>
    (function () {
        let activeSelect = null;
        const popover = document.getElementById('drc-sselect-popover');
        const searchInput = document.getElementById('drc-sselect-input');
        const listEl = document.getElementById('drc-sselect-list');

        function closePopover() {
            if (!activeSelect) return;
            popover.style.display = 'none';
            activeSelect = null;
        }

        function renderOptions(query) {
            if (!activeSelect) return;
            const q = (query || '').trim().toLowerCase();
            listEl.innerHTML = '';
            let count = 0;

            Array.from(activeSelect.options).forEach((opt, index) => {
                const text = (opt.textContent || opt.innerText || '').trim();
                if (q && !text.toLowerCase().includes(q)) return;

                const row = document.createElement('div');
                row.className = 'sselect-opt' + (activeSelect.selectedIndex === index ? ' selected' : '');
                row.textContent = text;
                row.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    activeSelect.selectedIndex = index;
                    activeSelect.value = opt.value;
                    activeSelect.dispatchEvent(new Event('input', { bubbles: true }));
                    activeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    closePopover();
                });
                listEl.appendChild(row);
                count++;
            });

            if (count === 0) {
                const empty = document.createElement('div');
                empty.className = 'sselect-empty';
                empty.textContent = 'Pilihan tidak ditemukan.';
                listEl.appendChild(empty);
            }
        }

        function openForSelect(sel) {
            if (sel.disabled) return;
            activeSelect = sel;
            const rect = sel.getBoundingClientRect();
            const minW = Math.max(rect.width, 220);
            const leftPos = Math.min(rect.left, window.innerWidth - minW - 12);
            const spaceBelow = window.innerHeight - rect.bottom;

            popover.style.width = minW + 'px';
            popover.style.left = Math.max(8, leftPos) + 'px';

            if (spaceBelow < 240 && rect.top > 250) {
                popover.style.top = 'auto';
                popover.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
            } else {
                popover.style.bottom = 'auto';
                popover.style.top = (rect.bottom + 4) + 'px';
            }

            popover.style.display = 'flex';
            searchInput.value = '';
            renderOptions('');
            setTimeout(() => searchInput.focus(), 10);
        }

        document.addEventListener('mousedown', function (e) {
            if (popover.contains(e.target)) return;
            const sel = e.target.closest('select');
            if (sel && !sel.hasAttribute('data-native-select')) {
                e.preventDefault();
                if (activeSelect === sel) {
                    closePopover();
                } else {
                    openForSelect(sel);
                }
            } else {
                closePopover();
            }
        }, true);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && activeSelect) {
                closePopover();
            }
        });

        searchInput.addEventListener('input', function () {
            renderOptions(this.value);
        });

        window.addEventListener('scroll', function (e) {
            if (activeSelect && !popover.contains(e.target)) {
                closePopover();
            }
        }, true);

        window.addEventListener('resize', closePopover);
    })();

    // Smart Auto-Update Saat Idle (15 Detik)
    (function () {
        let lastActivity = Date.now();
        ['mousemove', 'keydown', 'mousedown', 'touchstart', 'input'].forEach(ev => {
            window.addEventListener(ev, () => { lastActivity = Date.now(); }, { passive: true });
        });

        let fetchCtrl = null;
        window.addEventListener('beforeunload', () => {
            if (fetchCtrl) fetchCtrl.abort();
        });

        setInterval(async () => {
            if (Date.now() - lastActivity < 12000) return;
            const activeEl = document.activeElement;
            if (activeEl && ['INPUT', 'SELECT', 'TEXTAREA'].includes(activeEl.tagName)) return;
            if (document.getElementById('drc-sselect-popover')?.style.display === 'flex') return;

            const openModals = Array.from(document.querySelectorAll('.modal-backdrop')).some(el => el.offsetParent !== null);
            if (openModals) return;

            try {
                if (fetchCtrl) fetchCtrl.abort();
                fetchCtrl = new AbortController();
                const res = await fetch(window.location.href, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: fetchCtrl.signal
                });
                if (!res.ok) return;
                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newTbodies = doc.querySelectorAll('table.tbl tbody');
                const curTbodies = document.querySelectorAll('table.tbl tbody');
                if (newTbodies.length === curTbodies.length && newTbodies.length > 0) {
                    curTbodies.forEach((tb, idx) => {
                        if (tb.innerHTML !== newTbodies[idx].innerHTML) {
                            tb.innerHTML = newTbodies[idx].innerHTML;
                            window.dispatchEvent(new CustomEvent('table-refreshed'));
                        }
                    });
                }
            } catch (e) {}
        }, 15000);
    })();
</script>
</body>
</html>