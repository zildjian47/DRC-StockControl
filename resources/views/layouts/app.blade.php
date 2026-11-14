@php
    $u = Auth::user();
    $uname = $u->name ?: ($u->username ?: 'User');
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
                if (localStorage.getItem('drc_sb_collapsed') === '1') {
                    document.documentElement.classList.add('sb-collapsed');
                }
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{
        nav: false,
        collapsed: document.documentElement.classList.contains('sb-collapsed'),
        confirmOpen: false,
        confirmTitle: 'Konfirmasi Tindakan',
        confirmMessage: 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
        confirmForm: null,
        toggleSidebar() {
            this.collapsed = !this.collapsed;
            document.documentElement.classList.toggle('sb-collapsed', this.collapsed);
            try {
                localStorage.setItem('drc_sb_collapsed', this.collapsed ? '1' : '0');
            } catch (e) {}
        },
        openConfirm(detail) {
            this.confirmForm = detail?.form || null;
            this.confirmTitle = detail?.title || 'Konfirmasi Tindakan';
            this.confirmMessage = detail?.message || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
            this.confirmOpen = true;
        },
        proceedConfirm() {
            if (this.confirmForm) {
                this.confirmOpen = false;
                this.confirmForm.submit();
            }
        }
      }"
      @keydown.escape.window="nav = false; confirmOpen = false"
      @open-confirm.window="openConfirm($event.detail)">

<div class="app-shell">
    {{-- Backdrop menu pada layar kecil --}}
    <div x-show="nav" x-cloak @click="nav = false" class="fixed inset-0 z-30 bg-slate-950/50 lg:hidden"></div>

    {{-- SIDEBAR UTAMA --}}
    <aside class="sidebar fixed inset-y-0 left-0 z-40 lg:static lg:translate-x-0"
           :class="nav ? 'translate-x-0' : '-translate-x-full'">
        <div class="sb-brand">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="sb-mark">D</span>
                <span class="sb-name">DRC Stock Control</span>
            </div>
            <button type="button"
                    @click="toggleSidebar()"
                    class="hidden lg:grid place-items-center w-7 h-7 rounded text-slate-400 hover:text-white hover:bg-white/10 flex-none"
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

{{-- POP-UP MODAL KONFIRMASI SISTEM GLOBAL --}}
<div x-show="confirmOpen" x-cloak
     class="modal-backdrop"
     @click.self="confirmOpen = false">
    <div class="panel w-full max-w-md overflow-hidden">
        <div class="panel-head">
            <h3 class="panel-title" x-text="confirmTitle"></h3>
            <button type="button" class="act-btn" @click="confirmOpen = false">
                <x-icon name="x" />
            </button>
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

{{-- GLOBAL SEARCHABLE DROPDOWN ENGINE (UNTUK SEMUA <SELECT> DI FORM & FILTER) --}}
<div id="drc-sselect-popover" class="sselect-popover" style="display: none;">
    <div class="sselect-search-wrap">
        <input type="text" id="drc-sselect-input" class="sselect-search" placeholder="Ketik untuk mencari..." autocomplete="off">
    </div>
    <div id="drc-sselect-list" class="sselect-list"></div>
</div>

<script>
    (function () {
        const popover = document.getElementById('drc-sselect-popover');
        const searchInput = document.getElementById('drc-sselect-input');
        const listEl = document.getElementById('drc-sselect-list');
        let activeSelect = null;
        let filteredOptions = [];
        let highlightIndex = 0;

        function closePopover() {
            if (!activeSelect) return;
            popover.style.display = 'none';
            activeSelect = null;
        }

        function renderOptions() {
            if (!activeSelect) return;
            const q = (searchInput.value || '').trim().toLowerCase();
            const allOpts = Array.from(activeSelect.options);

            filteredOptions = allOpts.filter(opt => {
                if (!q) return true;
                return (opt.text || '').toLowerCase().includes(q) || (opt.value || '').toLowerCase().includes(q);
            });

            if (highlightIndex >= filteredOptions.length) {
                highlightIndex = Math.max(0, filteredOptions.length - 1);
            }

            listEl.innerHTML = '';
            if (filteredOptions.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'sselect-empty';
                empty.textContent = 'Data tidak ditemukan';
                listEl.appendChild(empty);
                return;
            }

            filteredOptions.forEach((opt, idx) => {
                const item = document.createElement('div');
                const isSelected = String(opt.value) === String(activeSelect.value);
                item.className = 'sselect-opt' + (idx === highlightIndex ? ' active' : '') + (isSelected ? ' selected' : '');
                item.textContent = opt.text;
                item.addEventListener('mouseenter', () => {
                    highlightIndex = idx;
                    Array.from(listEl.children).forEach((el, i) => {
                        el.classList.toggle('active', i === highlightIndex);
                    });
                });
                item.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    selectOption(opt);
                });
                listEl.appendChild(item);
            });
        }

        function selectOption(opt) {
            if (!activeSelect || !opt) return;
            const target = activeSelect;
            target.value = opt.value;
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
            closePopover();
        }

        function openForSelect(selectEl) {
            if (selectEl.disabled) return;
            activeSelect = selectEl;
            searchInput.value = '';

            const currentIdx = Array.from(selectEl.options).findIndex(o => String(o.value) === String(selectEl.value));
            highlightIndex = currentIdx >= 0 ? currentIdx : 0;

            renderOptions();
            popover.style.display = 'flex';

            const rect = selectEl.getBoundingClientRect();
            const popW = Math.min(Math.max(rect.width, 220), Math.min(480, window.innerWidth - 16));
            popover.style.width = popW + 'px';

            let left = rect.left;
            if (left + popW > window.innerWidth - 8) {
                left = Math.max(8, window.innerWidth - popW - 8);
            }
            popover.style.left = left + 'px';

            const spaceBelow = window.innerHeight - rect.bottom;
            const popH = Math.min(265, popover.offsetHeight || 240);
            if (spaceBelow < popH && rect.top > popH) {
                popover.style.top = Math.max(8, rect.top - popH - 4) + 'px';
            } else {
                popover.style.top = (rect.bottom + 4) + 'px';
            }

            setTimeout(() => {
                searchInput.focus();
                const activeEl = listEl.querySelector('.sselect-opt.selected') || listEl.querySelector('.sselect-opt.active');
                if (activeEl) activeEl.scrollIntoView({ block: 'nearest' });
            }, 10);
        }

        document.addEventListener('mousedown', (e) => {
            const sel = e.target.closest('select');
            if (sel && !sel.disabled && !sel.hasAttribute('data-native-select')) {
                e.preventDefault();
                if (activeSelect === sel) {
                    closePopover();
                } else {
                    openForSelect(sel);
                }
                return;
            }
            if (activeSelect && !popover.contains(e.target)) {
                closePopover();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (!activeSelect) {
                const focused = document.activeElement;
                if (focused && focused.tagName === 'SELECT' && !focused.disabled && (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown')) {
                    e.preventDefault();
                    openForSelect(focused);
                }
                return;
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                closePopover();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (filteredOptions.length > 0) {
                    highlightIndex = (highlightIndex + 1) % filteredOptions.length;
                    renderOptions();
                    const activeEl = listEl.children[highlightIndex];
                    if (activeEl) activeEl.scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (filteredOptions.length > 0) {
                    highlightIndex = (highlightIndex - 1 + filteredOptions.length) % filteredOptions.length;
                    renderOptions();
                    const activeEl = listEl.children[highlightIndex];
                    if (activeEl) activeEl.scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (filteredOptions[highlightIndex]) {
                    selectOption(filteredOptions[highlightIndex]);
                }
            }
        });

        searchInput.addEventListener('input', () => {
            highlightIndex = 0;
            renderOptions();
        });

        window.addEventListener('scroll', (e) => {
            if (activeSelect && !popover.contains(e.target)) {
                closePopover();
            }
        }, true);

        window.addEventListener('resize', closePopover);
    })();
</script>

</body>
</html>