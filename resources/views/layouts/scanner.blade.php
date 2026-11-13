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

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: var(--font-body);
            background: #E5E9F0;
            color: var(--ink);
            touch-action: manipulation;
            min-height: 100vh;
        }
        .scanner-shell {
            max-width: 460px;
            min-height: 100vh;
            margin: 0 auto;
            background: var(--bg);
            display: flex;
            flex-direction: column;
            box-shadow: 0 0 24px rgba(15, 30, 51, 0.12);
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
            font: 600 14px var(--font-head);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .scanner-header a {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            height: 30px;
            padding: 0 10px;
            border-radius: var(--r);
            background: rgba(255, 255, 255, 0.08);
            font-size: 12px;
            font-weight: 600;
            color: #fff;
        }
        .scanner-header a:hover {
            background: rgba(255, 255, 255, 0.16);
        }

        /* Visual Stepper Modern */
        .stepper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            background: var(--sunk);
            border: 1px solid var(--line);
            border-radius: var(--r);
            margin-bottom: 12px;
        }
        .step-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--mut);
            white-space: nowrap;
        }
        .step-badge {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font: 700 11px var(--font-mono);
            background: #DDE3EA;
            color: var(--ink2);
            flex: none;
        }
        .step-item.active {
            color: var(--accent);
        }
        .step-item.active .step-badge {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 0 0 3px var(--ring);
        }
        .step-item.done {
            color: var(--good);
        }
        .step-item.done .step-badge {
            background: var(--good);
            color: #fff;
        }
        .step-line {
            flex: 1;
            height: 2px;
            background: var(--line2);
            margin: 0 8px;
            min-width: 12px;
        }
        .step-line.done {
            background: var(--good);
        }

        /* Camera Viewport Bersih */
        .camera-box {
            position: relative;
            overflow: hidden;
            background: #0F172A;
            border: 1px solid var(--line2);
            border-radius: var(--r);
            min-height: 210px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #qr-reader {
            width: 100% !important;
            border: none !important;
        }
        #qr-reader video {
            width: 100% !important;
            height: 210px !important;
            object-fit: cover !important;
            display: block;
        }
        .scan-laser {
            position: absolute;
            left: 12%;
            right: 12%;
            height: 2px;
            background: #38BDF8;
            box-shadow: 0 0 8px #0284C7;
            pointer-events: none;
            animation: scanMove 2.2s infinite ease-in-out;
        }
        @keyframes scanMove {
            0%, 100% { top: 20%; opacity: 0.6; }
            50% { top: 80%; opacity: 1; }
        }

        .toast {
            position: fixed;
            left: 12px;
            right: 12px;
            bottom: 18px;
            z-index: 90;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            max-width: 430px;
            margin: 0 auto;
            padding: 11px 14px;
            border-radius: var(--r);
            font-size: 12.5px;
            font-weight: 600;
            color: #fff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.25);
        }
        .toast-good { background: var(--good); }
        .toast-crit { background: var(--crit); }
    </style>
</head>
<body>
<div class="scanner-shell" x-data="{ runtimeToast: null, runtimeType: 'crit', toastTimer: null }"
     @scanner-toast.window="
        runtimeToast = $event.detail.message;
        runtimeType = $event.detail.type || 'crit';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { runtimeToast = null }, 4000);
     ">

    <header class="scanner-header">
        <div class="ttl">
            <span class="w-6 h-6 rounded bg-[#0B5CAD] text-white grid place-items-center text-xs font-bold">D</span>
            <span>{{ $header ?? 'Mobile Scanner' }}</span>
        </div>
        <a href="{{ $backUrl ?? url()->current() }}">
            <x-icon name="chevron-left" class="w-3.5 h-3.5" />
            <span>Desktop</span>
        </a>
    </header>

    <main class="flex-1 p-3.5 space-y-3.5">
        {{ $slot }}
    </main>

    {{-- Toast Runtime (Validasi Instan Saat Scan) --}}
    <div x-show="runtimeToast" x-cloak class="toast" :class="runtimeType === 'good' ? 'toast-good' : 'toast-crit'">
        <span x-text="runtimeToast"></span>
        <button type="button" @click="runtimeToast = null" class="text-white/80 hover:text-white text-xs font-bold px-1">✕</button>
    </div>

    {{-- Toast Session Server --}}
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="toast toast-good">
            <span>{{ session('success') }}</span>
            <button type="button" @click="show = false" class="text-white/80 font-bold">✕</button>
        </div>
    @endif

    @if (session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="toast toast-crit">
            <span>{{ session('error') }}</span>
            <button type="button" @click="show = false" class="text-white/80 font-bold">✕</button>
        </div>
    @endif

    @if ($errors->any())
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="toast toast-crit">
            <span>{{ $errors->first() }}</span>
            <button type="button" @click="show = false" class="text-white/80 font-bold">✕</button>
        </div>
    @endif
</div>

<script>
    window.showScannerToast = function (message, type = 'crit') {
        window.dispatchEvent(new CustomEvent('scanner-toast', {
            detail: { message: message, type: type }
        }));
    };

    window.drcQrInstance = null;
    window.drcCameraActive = false;

    window.startDrcCamera = function (elementId, onDecodeSuccess, onStatusChange) {
        const el = document.getElementById(elementId);
        if (!el || typeof Html5Qrcode === 'undefined') {
            if (onStatusChange) onStatusChange(false);
            return;
        }

        window.stopDrcCamera().then(function () {
            try {
                window.drcQrInstance = new Html5Qrcode(elementId);
                window.drcQrInstance.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 210, height: 140 } },
                    function (decodedText) {
                        if (onDecodeSuccess) onDecodeSuccess(decodedText);
                    },
                    function () {}
                ).then(function () {
                    window.drcCameraActive = true;
                    if (onStatusChange) onStatusChange(true);
                }).catch(function () {
                    window.drcCameraActive = false;
                    if (onStatusChange) onStatusChange(false);
                });
            } catch (e) {
                window.drcCameraActive = false;
                if (onStatusChange) onStatusChange(false);
            }
        });
    };

    window.stopDrcCamera = function () {
        if (window.drcQrInstance && window.drcCameraActive) {
            return window.drcQrInstance.stop().then(function () {
                window.drcQrInstance.clear();
                window.drcCameraActive = false;
            }).catch(function () {
                window.drcCameraActive = false;
            });
        }
        return Promise.resolve();
    };
</script>
</body>
</html>