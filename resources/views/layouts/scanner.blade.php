@props(['backUrl' => null])
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
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

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

        .toast {
            position: fixed;
            left: 12px;
            right: 12px;
            bottom: calc(16px + env(safe-area-inset-bottom));
            z-index: 60;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            max-width: 480px;
            margin: 0 auto;
            padding: 11px 14px;
            border-radius: var(--r);
            font-size: 13px;
            font-weight: 600;
            color: #fff;
        }
        .toast-good { background: var(--good); }
        .toast-crit { background: var(--crit); }
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
                if (!el || typeof Html5Qrcode === 'undefined') return false;

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
                        onErrorMsg('Kamera tidak dapat diakses. Pastikan izin kamera browser diaktifkan atau gunakan input manual di bawah.');
                    }
                    return false;
                }
            }
        };
    </script>
</head>
<body class="min-h-screen pb-10">
    <header class="scanner-header">
        <a href="{{ $backUrl ?: url()->current() }}">
            <x-icon name="chevron-left" /> Kembali ke Desktop
        </a>
        <div class="ttl">{{ $header ?? 'Mobile Scanner' }}</div>
    </header>

    <main class="max-w-lg mx-auto p-3.5 space-y-3.5">
        {{ $slot }}
    </main>

    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="toast toast-good" role="status">
            <span class="inline-flex items-center gap-2 min-w-0">
                <x-icon name="check" />
                <span>{{ session('success') }}</span>
            </span>
            <button type="button" @click="show = false" class="text-white/80 hover:text-white"><x-icon name="x" /></button>
        </div>
    @endif

    @if (session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="toast toast-crit" role="alert">
            <span class="inline-flex items-center gap-2 min-w-0">
                <x-icon name="alert" />
                <span>{{ session('error') }}</span>
            </span>
            <button type="button" @click="show = false" class="text-white/80 hover:text-white"><x-icon name="x" /></button>
        </div>
    @endif

    @if ($errors->any())
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="toast toast-crit" role="alert">
            <span class="inline-flex items-center gap-2 min-w-0">
                <x-icon name="alert" />
                <span>{{ $errors->first() }}</span>
            </span>
            <button type="button" @click="show = false" class="text-white/80 hover:text-white"><x-icon name="x" /></button>
        </div>
    @endif

    {{-- GLOBAL SEARCHABLE DROPDOWN ENGINE (UNTUK SEMUA <SELECT> DI MOBILE SCANNER) --}}
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
                highlightIndex = 0;
                renderOptions();
                popover.style.display = 'flex';

                const rect = selectEl.getBoundingClientRect();
                const popW = Math.min(Math.max(rect.width, 240), window.innerWidth - 16);
                popover.style.width = popW + 'px';
                popover.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - popW - 8)) + 'px';

                const spaceBelow = window.innerHeight - rect.bottom;
                const popH = Math.min(250, popover.offsetHeight || 220);
                if (spaceBelow < popH && rect.top > popH) {
                    popover.style.top = Math.max(8, rect.top - popH - 4) + 'px';
                } else {
                    popover.style.top = (rect.bottom + 4) + 'px';
                }

                setTimeout(() => searchInput.focus(), 10);
            }

            document.addEventListener('mousedown', (e) => {
                const sel = e.target.closest('select');
                if (sel && !sel.disabled && !sel.hasAttribute('data-native-select')) {
                    e.preventDefault();
                    if (activeSelect === sel) closePopover();
                    else openForSelect(sel);
                    return;
                }
                if (activeSelect && !popover.contains(e.target)) {
                    closePopover();
                }
            });

            searchInput.addEventListener('input', () => {
                highlightIndex = 0;
                renderOptions();
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