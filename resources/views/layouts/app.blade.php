@php
    $u = Auth::user();
    $displayName = $u->name;
    $words = preg_split('/\s+/', trim((string) $displayName));
    $initials = count($words) >= 2
        ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1))
        : strtoupper(substr((string) $displayName, 0, 2));

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
            window.addEventListener('pageshow', function () {
                syncSidebarState();
                window.dispatchEvent(new CustomEvent('sb-sync'));
            });
            window.addEventListener('popstate', function () {
                syncSidebarState();
                window.dispatchEvent(new CustomEvent('sb-sync'));
            });
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{
        nav: false,
        collapsed: (() => { try { return localStorage.getItem('drc_sb_collapsed') === '1'; } catch (e) { return false; } })(),
        toggleSidebar() {
            document.documentElement.classList.add('sb-ready');
            this.collapsed = !this.collapsed;
            try { localStorage.setItem('drc_sb_collapsed', this.collapsed ? '1' : '0'); } catch (e) {}
            document.documentElement.classList.toggle('sb-collapsed', this.collapsed);
        },
        syncSidebar() {
            try { this.collapsed = localStorage.getItem('drc_sb_collapsed') === '1'; } catch (e) {}
        }
      }"
      x-init="$nextTick(() => document.documentElement.classList.add('sb-ready'))"
      @sb-sync.window="syncSidebar()"
      @keydown.escape.window="nav = false">
<div class="app-shell">

    <div x-show="nav" x-cloak @click="nav = false" class="fixed inset-0 z-30 bg-slate-950/50 lg:hidden"></div>

    {{-- SIDEBAR --}}
    <aside class="sidebar fixed inset-y-0 left-0 z-40 transition-transform lg:static lg:translate-x-0"
           :class="nav ? 'translate-x-0' : '-translate-x-full'">
        <div class="sb-brand">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="sb-mark">D</span>
                <span class="sb-name">DRC Stock Control</span>
            </div>
            <button type="button"
                    @click="toggleSidebar()"
                    class="hidden lg:grid place-items-center w-7 h-7 rounded text-slate-300 hover:text-white hover:bg-white/10 flex-none"
                    :title="collapsed ? 'Perlebar Sidebar' : 'Perkecil Sidebar'">
                <x-icon x-show="!collapsed" name="chevron-left" />
                <x-icon x-show="collapsed" x-cloak name="chevron-right" />
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

    {{-- AREA UTAMA --}}
    <div class="flex-1 flex flex-col min-w-0">
        <header class="topbar">
            <button type="button" class="icon-btn lg:hidden" @click="nav = true" aria-label="Buka menu">
                <x-icon name="menu" />
            </button>

            <div class="crumb">
                <span>PT. Dian Raya Cipta</span><span>/</span><b>{{ $pageLabel }}</b>
            </div>

            <a href="{{ route('profile.edit') }}" class="user-chip" title="Profil saya">
                <span class="avatar">{{ $initials }}</span>
                <span class="hidden sm:flex flex-col leading-tight text-left">
                    <b class="text-[12.5px] font-semibold" style="color:var(--ink)">{{ $displayName }}</b>
                    <small class="text-[11px] font-semibold uppercase" style="color:var(--mut)">{{ $u->role }}</small>
                </span>
            </a>
        </header>

        <main class="flex-1 overflow-y-auto">
            <div class="page">
                @isset($header)
                    <div class="page-head">{{ $header }}</div>
                @endisset
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

{{-- MODAL KONFIRMASI SISTEM GLOBAL --}}
<div x-data="{
        open: false,
        title: 'Konfirmasi Tindakan',
        message: 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
        formEl: null,
        show(detail) {
            this.title = detail?.title || 'Konfirmasi Tindakan';
            this.message = detail?.message || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
            this.formEl = detail?.form || (detail?.formId ? document.getElementById(detail.formId) : null);
            this.open = true;
        },
        confirm() {
            if (this.formEl) {
                this.formEl.submit();
            }
            this.open = false;
        }
    }"
    @open-confirm.window="show($event.detail)"
    @keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="modal-backdrop">
    <div class="panel w-full max-w-md" @click.away="open = false">
        <div class="panel-head">
            <h3 class="panel-title" x-text="title"></h3>
            <button type="button" class="act-btn" @click="open = false">
                <x-icon name="x" />
            </button>
        </div>
        <div class="panel-body">
            <p class="text-slate-700 text-xs leading-relaxed" x-text="message"></p>
        </div>
        <div class="panel-foot">
            <button type="button" class="btn" @click="open = false">Batal</button>
            <button type="button" class="btn btn-primary" @click="confirm()">Ya, Lanjutkan</button>
        </div>
    </div>
</div>

{{-- GLOBAL SEARCHABLE SELECT ENGINE (Ketik & Pilih untuk Seluruh Dropdown) --}}
<div x-data="globalSearchSelect()"
     x-init="initEngine()"
     @keydown.escape.window="close()"
     x-show="isOpen"
     x-cloak
     :style="popoverStyle"
     class="sselect-popover"
     @mousedown.outside="close()">
    <div class="sselect-search-wrap">
        <input type="text"
               x-ref="searchInput"
               x-model="q"
               @keydown.down.prevent="move(1)"
               @keydown.up.prevent="move(-1)"
               @keydown.enter.prevent="chooseActive()"
               placeholder="Ketik untuk mencari pilihan..."
               autocomplete="off"
               class="sselect-search">
    </div>
    <div class="sselect-list" x-ref="optList">
        <template x-for="(opt, idx) in filteredOptions" :key="idx">
            <div class="sselect-opt"
                 :class="{ 'active': idx === activeIndex, 'selected': String(opt.value) === String(currentValue) }"
                 @mouseenter="activeIndex = idx"
                 @mousedown.prevent="selectOption(opt)">
                <span x-text="opt.label" class="truncate"></span>
                <span x-show="String(opt.value) === String(currentValue)" class="text-accent font-bold text-xs">✓</span>
            </div>
        </template>
        <div x-show="filteredOptions.length === 0" class="sselect-empty">
            Pilihan tidak ditemukan.
        </div>
    </div>
</div>

<script>
    function globalSearchSelect() {
        return {
            isOpen: false,
            targetSelect: null,
            q: '',
            options: [],
            currentValue: '',
            activeIndex: 0,
            popoverStyle: '',

            initEngine() {
                document.addEventListener('mousedown', (e) => {
                    const sel = e.target.closest('select');
                    if (!sel || sel.disabled || sel.dataset.nativeSelect === '1') return;

                    e.preventDefault();
                    sel.focus();
                    this.openFor(sel);
                }, true);

                document.addEventListener('keydown', (e) => {
                    const sel = document.activeElement;
                    if (!sel || sel.tagName !== 'SELECT' || sel.disabled) return;
                    if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        this.openFor(sel);
                    }
                });

                window.addEventListener('scroll', (e) => {
                    if (!this.isOpen) return;
                    if (this.$el && this.$el.contains(e.target)) return;
                    this.updatePosition();
                }, true);

                window.addEventListener('resize', () => {
                    if (this.isOpen) this.updatePosition();
                });
            },

            openFor(sel) {
                if (this.isOpen && this.targetSelect === sel) {
                    this.close();
                    return;
                }
                this.targetSelect = sel;
                this.readOptions();
                this.q = '';
                this.activeIndex = Math.max(0, this.options.findIndex(o => String(o.value) === String(this.currentValue)));
                this.isOpen = true;
                this.updatePosition();

                this.$nextTick(() => {
                    if (this.$refs.searchInput) {
                        this.$refs.searchInput.focus();
                    }
                });
            },

            readOptions() {
                if (!this.targetSelect) return;
                this.currentValue = this.targetSelect.value;
                this.options = Array.from(this.targetSelect.options)
                    .filter(o => !o.disabled || o.value === '')
                    .map(o => ({
                        value: o.value,
                        label: (o.textContent || o.innerText || '').trim()
                    }));
            },

            updatePosition() {
                if (!this.targetSelect) return;
                const rect = this.targetSelect.getBoundingClientRect();
                const width = Math.max(rect.width, 230);
                const spaceBelow = window.innerHeight - rect.bottom;
                const top = spaceBelow < 260 && rect.top > 260
                    ? Math.max(8, rect.top - 256)
                    : rect.bottom + 4;
                const left = Math.min(rect.left, window.innerWidth - width - 8);

                this.popoverStyle = `top:${top}px; left:${Math.max(8, left)}px; width:${width}px;`;
            },

            get filteredOptions() {
                const kw = this.q.trim().toLowerCase();
                if (!kw) return this.options;
                return this.options.filter(o => o.label.toLowerCase().includes(kw));
            },

            move(step) {
                const max = this.filteredOptions.length - 1;
                if (max < 0) return;
                this.activeIndex = Math.min(max, Math.max(0, this.activeIndex + step));
            },

            chooseActive() {
                const opt = this.filteredOptions[this.activeIndex];
                if (opt) this.selectOption(opt);
            },

            selectOption(opt) {
                if (!this.targetSelect) return;
                this.targetSelect.value = opt.value;
                this.currentValue = opt.value;
                this.targetSelect.dispatchEvent(new Event('input', { bubbles: true }));
                this.targetSelect.dispatchEvent(new Event('change', { bubbles: true }));
                this.close();
            },

            close() {
                this.isOpen = false;
                this.targetSelect = null;
            }
        };
    }

    // Nonaktifkan saran ketikan / riwayat input browser di seluruh form & input
    (function () {
        function disableAutocomplete(root) {
            if (!root || !root.querySelectorAll) return;
            root.querySelectorAll('form').forEach(f => f.setAttribute('autocomplete', 'off'));
            root.querySelectorAll('input:not([type="hidden"]):not([type="password"]), textarea').forEach(el => {
                el.setAttribute('autocomplete', 'off');
                el.setAttribute('autocorrect', 'off');
                el.setAttribute('spellcheck', 'false');
            });
        }
        document.addEventListener('DOMContentLoaded', () => disableAutocomplete(document));
        document.addEventListener('focusin', (e) => {
            const el = e.target;
            if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') && el.type !== 'password') {
                el.setAttribute('autocomplete', 'off');
                if (el.form) el.form.setAttribute('autocomplete', 'off');
            }
        }, true);
    })();

    // Auto-Update Tabel Saat Idle (Setiap 15 Detik · Anti Kedip saat Filter Aktif)
    (function () {
        let lastActivity = Date.now();
        ['mousemove', 'keydown', 'mousedown', 'touchstart', 'input'].forEach(evt => {
            window.addEventListener(evt, () => { lastActivity = Date.now(); }, { passive: true });
        });

        let activeController = null;
        window.addEventListener('beforeunload', () => {
            if (activeController) activeController.abort();
        });

        function getServerTables(root) {
            return Array.from(root.querySelectorAll('main .tbl tbody')).filter(tb => {
                return !tb.querySelector('template[x-for]');
            });
        }

        function normalizeTbody(tb) {
            const clone = tb.cloneNode(true);
            clone.querySelectorAll('tr').forEach(tr => {
                if (tr.hasAttribute('x-show')) {
                    tr.removeAttribute('style');
                }
            });
            clone.querySelectorAll('[data-col-no]').forEach(el => {
                el.textContent = '';
            });
            clone.querySelectorAll('[x-cloak]').forEach(el => {
                el.removeAttribute('x-cloak');
            });
            return clone.innerHTML.replace(/\s+/g, ' ').trim();
        }

        setInterval(async () => {
            if (document.hidden) return;
            if (Date.now() - lastActivity < 6000) return;

            const activeEl = document.activeElement;
            if (activeEl && ['INPUT', 'SELECT', 'TEXTAREA'].includes(activeEl.tagName)) return;

            const openModal = Array.from(document.querySelectorAll('.modal-backdrop')).some(el => el.offsetParent !== null);
            if (openModal) return;

            const currentTables = getServerTables(document);
            if (currentTables.length === 0) return;

            try {
                if (activeController) activeController.abort();
                activeController = new AbortController();

                const res = await fetch(window.location.href, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: activeController.signal
                });
                if (!res.ok) return;

                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newTables = getServerTables(doc);

                if (newTables.length !== currentTables.length) return;

                let anyUpdated = false;
                currentTables.forEach((currentTbl, idx) => {
                    const newTbl = newTables[idx];
                    const currentNorm = currentTbl._drcNormHtml !== undefined
                        ? currentTbl._drcNormHtml
                        : normalizeTbody(currentTbl);
                    const newNorm = normalizeTbody(newTbl);

                    if (newNorm !== currentNorm) {
                        currentTbl.innerHTML = newTbl.innerHTML;
                        currentTbl._drcNormHtml = newNorm;
                        if (window.Alpine) {
                            window.Alpine.initTree(currentTbl);
                        }
                        anyUpdated = true;
                    } else if (currentTbl._drcNormHtml === undefined) {
                        currentTbl._drcNormHtml = newNorm;
                    }
                });

                if (anyUpdated) {
                    window.dispatchEvent(new CustomEvent('table-refreshed'));
                }
            } catch (e) {}
        }, 15000);
    })();
</script>
</body>
</html>