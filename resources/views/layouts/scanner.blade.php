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
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
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
            gap: 6px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--side-ink);
            padding: 6px 10px;
            border-radius: var(--r);
            background: rgba(255, 255, 255, 0.06);
        }
        .scanner-header a:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.12);
        }
        .camera-box {
            position: relative;
            overflow: hidden;
            background: #000;
            border: 1px solid var(--line2);
            border-radius: var(--r);
        }
        #qr-reader {
            width: 100% !important;
            border: none !important;
        }
        #qr-reader__dashboard_section_csr span {
            color: #fff !important;
            font-size: 0.8rem;
        }
        #qr-reader__dashboard_section_csr button {
            background: var(--accent) !important;
            color: #fff !important;
            border: 0;
            border-radius: var(--r);
            padding: 0 16px;
            height: 38px;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        .toast {
            position: fixed;
            left: 12px;
            right: 12px;
            bottom: calc(16px + env(safe-area-inset-bottom));
            z-index: 80;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            max-width: 460px;
            margin: 0 auto;
            padding: 12px 14px;
            border-radius: var(--r);
            font-size: 13px;
            font-weight: 600;
            color: #fff;
            box-shadow: 0 8px 24px rgba(20, 32, 46, 0.22);
        }
        .toast-good { background: var(--good); }
        .toast-crit { background: var(--crit); }

        /* Modern Visual Stepper */
        .stepper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 10px 12px;
            background: var(--sunk);
            border: 1px solid var(--line);
            border-radius: var(--r);
            margin-bottom: 14px;
        }
        .step-item {
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
        }
        .step-circle {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font: 700 11.5px var(--font-mono);
            flex: none;
            transition: all 0.15s;
        }
        .step-circle-idle {
            background: #E2E8F0;
            color: #64748B;
        }
        .step-circle-active {
            background: var(--accent);
            color: #FFFFFF;
            box-shadow: 0 0 0 3px var(--ring);
        }
        .step-circle-done {
            background: var(--good);
            color: #FFFFFF;
        }
        .step-label {
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .step-label-idle { color: var(--mut); }
        .step-label-active { color: var(--ink); font-weight: 700; }
        .step-label-done { color: var(--good); }
        .step-line {
            flex: 1;
            height: 2px;
            margin: 0 8px;
            background: #CBD5E1;
            min-width: 14px;
        }
        .step-line-done {
            background: var(--good);
        }
    </style>
</head>
<body x-data="{
        runtimeToast: false,
        runtimeMsg: '',
        runtimeType: 'crit',
        notify(msg, type = 'crit') {
            this.runtimeMsg = msg;
            this.runtimeType = type;
            this.runtimeToast = true;
            clearTimeout(this._t);
            this._t = setTimeout(() => { this.runtimeToast = false; }, 4000);
        }
      }"
      @scan-notify.window="notify($event.detail.message, $event.detail.type || 'crit')">

    <header class="scanner-header">
        <div class="flex items-center gap-2">
            <span class="sb-mark" style="width:26px;height:26px;font-size:13px;">D</span>
            <div class="ttl">{{ $header ?? 'Scanner' }}</div>
        </div>
        <a href="{{ $backUrl ?? route('dashboard') }}">
            <x-icon name="chevron-left" class="w-4 h-4" />
            <span>Desktop</span>
        </a>
    </header>

    <main class="max-w-md mx-auto p-3.5 space-y-4 pb-20">
        {{ $slot }}
    </main>

    {{-- Toast Runtime (Validasi Langsung Saat Scan) --}}
    <div x-show="runtimeToast" x-cloak class="toast" :class="runtimeType === 'good' ? 'toast-good' : 'toast-crit'">
        <span x-text="runtimeMsg"></span>
        <button type="button" @click="runtimeToast = false" class="text-white/80 hover:text-white text-xs font-bold px-1.5">✕</button>
    </div>

    @if (session('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-cloak class="toast toast-good">
            <span>{{ session('success') }}</span>
            <button type="button" @click="show = false" class="text-white/80 hover:text-white text-xs font-bold px-1.5">✕</button>
        </div>
    @endif

    @if (session('error'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-cloak class="toast toast-crit">
            <span>{{ session('error') }}</span>
            <button type="button" @click="show = false" class="text-white/80 hover:text-white text-xs font-bold px-1.5">✕</button>
        </div>
    @endif

    @if ($errors->any())
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-cloak class="toast toast-crit">
            <span>{{ $errors->first() }}</span>
            <button type="button" @click="show = false" class="text-white/80 hover:text-white text-xs font-bold px-1.5">✕</button>
        </div>
    @endif
</body>
</html>