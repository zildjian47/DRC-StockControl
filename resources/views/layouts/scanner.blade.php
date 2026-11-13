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
        <a href="{{ $backUrl ?? route('dashboard') }}">
            <x-icon name="chevron-left" /> Kembali ke Desktop
        </a>
        <div class="ttl">{{ $header ?? 'Mobile Scanner' }}</div>
    </header>

    <main class="max-w-md mx-auto p-3.5 space-y-3.5">
        {{ $slot }}
    </main>

    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="toast toast-good" role="status">
            <span>{{ session('success') }}</span>
            <button type="button" @click="show = false" class="text-white/80 hover:text-white"><x-icon name="x" /></button>
        </div>
    @endif

    @if (session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="toast toast-crit" role="alert">
            <span>{{ session('error') }}</span>
            <button type="button" @click="show = false" class="text-white/80 hover:text-white"><x-icon name="x" /></button>
        </div>
    @endif

    @if ($errors->any())
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="toast toast-crit" role="alert">
            <span>{{ $errors->first() }}</span>
            <button type="button" @click="show = false" class="text-white/80 hover:text-white"><x-icon name="x" /></button>
        </div>
    @endif
</body>
</html>