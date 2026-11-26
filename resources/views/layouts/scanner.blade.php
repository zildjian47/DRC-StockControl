@props(['backUrl' => null])
@php
    $scanUser          = Auth::user();
    $isPpicUser        = $scanUser && $scanUser->isPpic();
    $resolvedBackUrl   = $isPpicUser ? ($backUrl ?: route('dashboard')) : route('scanner.portal');
    $resolvedBackLabel = $isPpicUser ? 'Kembali ke Desktop' : 'Portal Scanner';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $header ?? 'Mobile Scanner' }} · DRC Stock Control</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}" type="text/javascript"></script>

    <style>
        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--ink);
            touch-action: manipulation;
        }
        .scanner-header {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 52px;
            padding: 0 14px;
            background: var(--side);
            color: #fff;
            border-bottom: 2px solid var(--accent);
        }
        .scanner-header .ttl {
            font: 600 14.5px var(--font-head);
            letter-spacing: -0.01em;
        }
        .scanner-header a {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--side-ink);
            padding: 6px 8px;
            border-radius: var(--r);
        }
        .scanner-header a:hover {
            background: var(--side-hover);
            color: #fff;
        }

        /* Area Kamera Persegi (1:1 Square Scope) Tanpa Animasi Laser Biru */
        .camera-box {
            position: relative;
            width: 100%;
            max-width: 300px;
            aspect-ratio: 1 / 1;
            margin: 0 auto;
            overflow: hidden;
            background: #0B1524;
            border: 1px solid var(--line2);
            border-radius: var(--r);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #qr-reader {
            width: 100% !important;
            height: 100% !important;
            border: none !important;
        }
        #qr-reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }
        #qr-reader__dashboard {
            display: none !important;
        }

        /* Bingkai Sudut Bidikan Persegi Statis (Tanpa Laser Bergerak) */
        .scan-scope-guide {
            position: absolute;
            inset: 28px;
            pointer-events: none;
            z-index: 10;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 4px;
        }
        .scan-scope-guide::before,
        .scan-scope-guide::after {
            content: '';
            position: absolute;
            inset: -1px;
            border: 2.5px solid #FFFFFF;
            border-radius: 4px;
            clip-path: polygon(
                0 0, 22px 0, 22px 4px, 4px 4px, 4px 22px, 0 22px,
                0 100%, 22px 100%, 22px calc(100% - 4px), 4px calc(100% - 4px), 4px calc(100% - 22px), 0 calc(100% - 22px),
                100% 100%, calc(100% - 22px) 100%, calc(100% - 22px) calc(100% - 4px), calc(100% - 4px) calc(100% - 4px), calc(100% - 4px) calc(100% - 22px), 100% calc(100% - 22px),
                100% 0, calc(100% - 22px) 0, calc(100% - 22px) 4px, calc(100% - 4px) 4px, calc(100% - 4px) 22px, 100% 22px
            );
        }

        /* Visual Stepper Modern */
        .scan-stepper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            padding: 8px 10px;
            background: var(--sunk);
            border: 1px solid var(--line);
            border-radius: var(--r);
        }
        .scan-step {
            display: flex;
            align-items: center;
            gap: 6px;
            flex: 1;
            min-width: 0;
            padding: 5px 8px;
            border-radius: 3px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--mut);
            background: transparent;
        }
        .scan-step-num {
            width: 20px;
            height: 20px;
            border-radius: 3px;
            display: grid;
            place-items: center;
            font-size: 11px;
            font-weight: 700;
            background: #E2E8F0;
            color: var(--ink2);
            flex: none;
        }
        .scan-step.active {
            background: var(--info-bg);
            color: var(--accent);
            border: 1px solid #C5D9EE;
        }
        .scan-step.active .scan-step-num {
            background: var(--accent);
            color: #fff;
        }
        .scan-step.done {
            background: var(--good-bg);
            color: var(--good);
        }
        .scan-step.done .scan-step-num {
            background: var(--good);
            color: #fff;
        }

        /* Floating Toast Pop-up Atas (Single-Slot, Anti-Menumpuk, Tanpa Menggeser Layout) */
        .drc-toast-host {
            position: fixed;
            top: calc(62px + env(safe-area-inset-top));
            left: 12px;
            right: 12px;
            z-index: 70;
            display: flex;
            justify-content: center;
            pointer-events: none;
        }
        .drc-toast {
            position: relative;
            width: 100%;
            max-width: 480px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 10px 12px 12px;
            border-radius: var(--r);
            color: #FFFFFF;
            pointer-events: auto;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(15, 30, 51, 0.28), 0 4px 10px -4px rgba(15, 30, 51, 0.18);
            opacity: 0;
            transform: translateY(-10px) scale(0.98);
            transition: opacity 0.18s ease, transform 0.2s cubic-bezier(0.2, 0.8, 0.2, 1);
            visibility: hidden;
        }
        .drc-toast.is-open {
            opacity: 1;
            transform: translateY(0) scale(1);
            visibility: visible;
        }
        .drc-toast.toast-good {
            background: var(--good);
        }
        .drc-toast.toast-crit {
            background: var(--crit);
        }
        .drc-toast.is-bump {
            animation: drcToastBump 0.24s ease;
        }
        @keyframes drcToastBump {
            0%   { transform: translateY(0) scale(1); }
            45%  { transform: translateY(-2px) scale(1.015); }
            100% { transform: translateY(0) scale(1); }
        }
        .drc-toast-ic {
            width: 24px;
            height: 24px;
            flex: 0 0 24px;
            border-radius: 3px;
            background: rgba(255, 255, 255, 0.16);
            display: grid;
            place-items: center;
        }
        .drc-toast-ic > .ic {
            width: 15px;
            height: 15px;
            flex: 0 0 15px;
        }
        .drc-toast-msg {
            flex: 1 1 auto;
            min-width: 0;
            font-size: 12.5px;
            font-weight: 600;
            line-height: 1.35;
            word-break: break-word;
        }
        .drc-toast-close {
            width: 28px;
            height: 28px;
            flex: 0 0 28px;
            border: 0;
            border-radius: 3px;
            background: transparent;
            color: rgba(255, 255, 255, 0.85);
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: background-color 0.12s ease, color 0.12s ease;
        }
        .drc-toast-close:hover {
            background: rgba(255, 255, 255, 0.16);
            color: #FFFFFF;
        }
        .drc-toast-close > .ic {
            width: 15px;
            height: 15px;
            flex: 0 0 15px;
        }
        .drc-toast-bar {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 3px;
            background: rgba(255, 255, 255, 0.45);
            transform-origin: left center;
            transform: scaleX(0);
        }
        @keyframes drcToastTimer {
            from { transform: scaleX(1); }
            to   { transform: scaleX(0); }
        }
    </style>

    <script>
        window.DRCScanner = {
            instance: null,
            isPermitted() {
                try {
                    return localStorage.getItem('drc_cam_permitted') === '1';
                } catch (e) {
                    return false;
                }
            },
            setPermitted() {
                try {
                    localStorage.setItem('drc_cam_permitted', '1');
                } catch (e) {}
            },
            async stop() {
                if (this.instance) {
                    try {
                        if (this.instance.isScanning) {
                            await this.instance.stop();
                        }
                        this.instance.clear();
                    } catch (e) {}
                    this.instance = null;
                }
            },
            async start(elementId, onDecode, onErrorMsg) {
                await this.stop();
                const el = document.getElementById(elementId);
                if (!el || typeof Html5Qrcode === 'undefined') {
                    if (typeof onErrorMsg === 'function') {
                        onErrorMsg('Pustaka kamera QR lokal belum termuat. Silakan ketik kode manual di bawah.');
                    }
                    return false;
                }

                try {
                    const qr = new Html5Qrcode(elementId);
                    this.instance = qr;
                    await qr.start(
                        { facingMode: 'environment' },
                        {
                            fps: 10,
                            qrbox: function (viewfinderWidth, viewfinderHeight) {
                                const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                                const boxSize = Math.max(160, Math.floor(minEdge * 0.78));
                                return { width: boxSize, height: boxSize };
                            },
                            aspectRatio: 1.0
                        },
                        (decodedText) => {
                            if (typeof onDecode === 'function') {
                                onDecode(decodedText);
                            }
                        },
                        () => {}
                    );
                    this.setPermitted();
                    return true;
                } catch (err) {
                    if (typeof onErrorMsg === 'function') {
                        onErrorMsg('Kamera tidak dapat diakses. Aktifkan izin kamera browser atau ketik kode di bawah.');
                    }
                    return false;
                }
            }
        };
    </script>
</head>
<body class="min-h-screen pb-10">
    <header class="scanner-header">
        <a href="{{ $resolvedBackUrl }}">
            <x-icon name="chevron-left" /> {{ $resolvedBackLabel }}
        </a>
        <div class="ttl">{{ $header ?? 'Mobile Scanner' }}</div>
    </header>

    {{-- SINGLE-SLOT FLOATING TOAST ATAS (WARNING & SUKSES) --}}
    <div class="drc-toast-host" aria-live="polite" aria-atomic="true">
        <div id="drc-toast" class="drc-toast toast-good" role="status">
            <span id="drc-toast-ic-good" class="drc-toast-ic" style="display: none;">
                <x-icon name="check" />
            </span>
            <span id="drc-toast-ic-crit" class="drc-toast-ic" style="display: none;">
                <x-icon name="alert" />
            </span>
            <span id="drc-toast-msg" class="drc-toast-msg"></span>
            <button type="button" id="drc-toast-close" class="drc-toast-close" aria-label="Tutup notifikasi">
                <x-icon name="x" />
            </button>
            <span id="drc-toast-bar" class="drc-toast-bar"></span>
        </div>
    </div>

    <main class="max-w-lg mx-auto p-3.5 space-y-3.5">
        {{ $slot }}
    </main>

    {{-- GLOBAL SEARCHABLE DROPDOWN ENGINE (UNTUK SEMUA <SELECT> DI MOBILE SCANNER) --}}
    <div id="drc-sselect-popover" class="sselect-popover" style="display: none;">
        <div class="sselect-search-wrap">
            <input type="text" id="drc-sselect-input" class="sselect-search" placeholder="Ketik untuk mencari..." autocomplete="off">
        </div>
        <div id="drc-sselect-list" class="sselect-list"></div>
    </div>

    <script>
        window.DRCToast = (function () {
            const box = document.getElementById('drc-toast');
            const icGood = document.getElementById('drc-toast-ic-good');
            const icCrit = document.getElementById('drc-toast-ic-crit');
            const msgEl = document.getElementById('drc-toast-msg');
            const closeBtn = document.getElementById('drc-toast-close');
            const barEl = document.getElementById('drc-toast-bar');

            let hideTimer = null;
            let activeMsg = '';
            let activeType = '';
            let isOpen = false;

            function restartProgress(ms) {
                if (!barEl) return;
                barEl.style.animation = 'none';
                void barEl.offsetWidth;
                barEl.style.animation = `drcToastTimer ${ms}ms linear forwards`;
            }

            function hide() {
                if (hideTimer) {
                    clearTimeout(hideTimer);
                    hideTimer = null;
                }
                isOpen = false;
                activeMsg = '';
                activeType = '';
                if (box) {
                    box.classList.remove('is-open', 'is-bump');
                }
                if (barEl) {
                    barEl.style.animation = 'none';
                }
            }

            function show(message, type = 'crit', duration = null) {
                const text = String(message || '').trim();
                if (!text || !box) return;

                const kind = type === 'good' ? 'good' : 'crit';
                const ms = Number(duration) > 0 ? Number(duration) : (kind === 'good' ? 4000 : 4500);
                const isSameActive = isOpen && activeMsg === text && activeType === kind;

                if (hideTimer) {
                    clearTimeout(hideTimer);
                    hideTimer = null;
                }

                if (isSameActive) {
                    box.classList.remove('is-bump');
                    void box.offsetWidth;
                    box.classList.add('is-bump');
                    restartProgress(ms);
                    hideTimer = setTimeout(hide, ms);
                    return;
                }

                activeMsg = text;
                activeType = kind;
                isOpen = true;

                box.classList.remove('toast-good', 'toast-crit', 'is-bump');
                box.classList.add(kind === 'good' ? 'toast-good' : 'toast-crit');
                box.setAttribute('role', kind === 'good' ? 'status' : 'alert');

                if (icGood) icGood.style.display = kind === 'good' ? 'grid' : 'none';
                if (icCrit) icCrit.style.display = kind === 'crit' ? 'grid' : 'none';
                if (msgEl) msgEl.textContent = text;

                box.classList.add('is-open');
                restartProgress(ms);
                hideTimer = setTimeout(hide, ms);

                try {
                    if (navigator.vibrate) {
                        navigator.vibrate(kind === 'crit' ? [45, 35, 45] : 30);
                    }
                } catch (e) {}
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', hide);
            }

            return { show, hide };
        })();

        @if (session('success'))
            window.DRCToast.show({{ Js::from(session('success')) }}, 'good');
        @elseif (session('error'))
            window.DRCToast.show({{ Js::from(session('error')) }}, 'crit');
        @elseif ($errors->any())
            window.DRCToast.show({{ Js::from($errors->first()) }}, 'crit');
        @endif

        (function () {
            const popover = document.getElementById('drc-sselect-popover');
            const searchInput = document.getElementById('drc-sselect-input');
            const listEl = document.getElementById('drc-sselect-list');
            let activeSelect = null;
            let filteredOptions = [];
            let highlightIndex = 0;

            function isNativeSelect(sel) {
                return sel.dataset.nativeSelect === '1' || sel.hasAttribute('data-native-select');
            }

            function closePopover() {
                if (!activeSelect) return;
                popover.style.display = 'none';
                activeSelect = null;
            }

            function updatePosition() {
                if (!activeSelect) return;
                if (!document.body.contains(activeSelect) || activeSelect.offsetParent === null) {
                    closePopover();
                    return;
                }
                const rect = activeSelect.getBoundingClientRect();
                const popW = Math.min(Math.max(rect.width, 240), window.innerWidth - 16);
                popover.style.width = popW + 'px';
                popover.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - popW - 8)) + 'px';

                const spaceBelow = window.innerHeight - rect.bottom;
                const popH = Math.min(260, popover.offsetHeight || 220);
                if (spaceBelow < popH && rect.top > popH) {
                    popover.style.top = Math.max(8, rect.top - popH - 4) + 'px';
                } else {
                    popover.style.top = Math.min(window.innerHeight - 60, rect.bottom + 4) + 'px';
                }
            }

            function renderOptions() {
                if (!activeSelect) return;
                const q = (searchInput.value || '').trim().toLowerCase();
                const allOpts = Array.from(activeSelect.options).filter(o => !o.disabled || o.value === '');

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
                    empty.textContent = 'Pilihan tidak ditemukan.';
                    listEl.appendChild(empty);
                    return;
                }

                filteredOptions.forEach((opt, idx) => {
                    const item = document.createElement('div');
                    const isSelected = String(opt.value) === String(activeSelect.value);
                    item.className = 'sselect-opt' + (idx === highlightIndex ? ' active' : '') + (isSelected ? ' selected' : '');

                    const lblSpan = document.createElement('span');
                    lblSpan.className = 'truncate';
                    lblSpan.textContent = (opt.text || '').trim();
                    item.appendChild(lblSpan);

                    if (isSelected) {
                        const chk = document.createElement('span');
                        chk.className = 'text-accent font-bold text-xs';
                        chk.textContent = '✓';
                        item.appendChild(chk);
                    }

                    item.addEventListener('mouseenter', () => {
                        highlightIndex = idx;
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
                const allOpts = Array.from(selectEl.options).filter(o => !o.disabled || o.value === '');
                highlightIndex = Math.max(0, allOpts.findIndex(o => String(o.value) === String(selectEl.value)));
                renderOptions();
                popover.style.display = 'flex';
                updatePosition();

                setTimeout(() => {
                    if (activeSelect) searchInput.focus();
                }, 10);
            }

            document.addEventListener('mousedown', (e) => {
                const sel = e.target.closest('select');
                if (sel && !sel.disabled && !isNativeSelect(sel)) {
                    e.preventDefault();
                    sel.focus();
                    if (activeSelect === sel) closePopover();
                    else openForSelect(sel);
                    return;
                }
                if (activeSelect && !popover.contains(e.target)) {
                    closePopover();
                }
            }, true);

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && activeSelect) {
                    closePopover();
                    return;
                }
                const sel = document.activeElement;
                if (!sel || sel.tagName !== 'SELECT' || sel.disabled || isNativeSelect(sel)) return;
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    openForSelect(sel);
                }
            });

            searchInput.addEventListener('input', () => {
                highlightIndex = 0;
                renderOptions();
                updatePosition();
            });

            searchInput.addEventListener('keydown', (e) => {
                if (!activeSelect) return;
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (filteredOptions.length > 0) {
                        highlightIndex = Math.min(filteredOptions.length - 1, highlightIndex + 1);
                        renderOptions();
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (filteredOptions.length > 0) {
                        highlightIndex = Math.max(0, highlightIndex - 1);
                        renderOptions();
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    const opt = filteredOptions[highlightIndex];
                    if (opt) selectOption(opt);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    closePopover();
                }
            });

            window.addEventListener('scroll', (e) => {
                if (!activeSelect) return;
                if (popover.contains(e.target)) return;
                updatePosition();
            }, true);

            window.addEventListener('resize', () => {
                if (activeSelect) updatePosition();
            });
        })();

        // Nonaktifkan saran ketikan / riwayat input browser di seluruh form & input scanner
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
    </script>
</body>
</html>