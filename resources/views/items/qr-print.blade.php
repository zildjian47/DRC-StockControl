<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Label QR Info Rak · DRC Stock Control</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('vendor/qrcode/qrcode.min.js') }}"></script>

    <style>
        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--ink);
            margin: 0;
        }
        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 40;
            background: var(--side);
            color: #fff;
            border-bottom: 2px solid var(--accent);
            padding: 10px 18px;
        }
        .sheet-wrap {
            max-width: 1120px;
            margin: 18px auto 36px;
            padding: 0 16px;
        }

        /* Grid A4 */
        .qr-grid {
            display: grid;
            gap: 12px;
        }
        .qr-grid.layout-a4_3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .qr-grid.layout-a4_4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        .qr-grid.layout-thermal {
            grid-template-columns: 1fr;
            max-width: 300px;
            margin: 0 auto;
        }

        .qr-card {
            background: #FFFFFF;
            border: 1.5px solid #CBD5E1;
            border-radius: 6px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .qr-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            padding-bottom: 6px;
            border-bottom: 1px solid #E2E8F0;
        }
        .qr-brand {
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #0F1E33;
        }
        .qr-body {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .layout-a4_4 .qr-body {
            flex-direction: column;
            text-align: center;
            gap: 6px;
        }
        .qr-svg-box {
            width: 96px;
            height: 96px;
            flex: 0 0 96px;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 3px;
            background: #FFFFFF;
            display: grid;
            place-items: center;
        }
        .layout-a4_4 .qr-svg-box {
            width: 108px;
            height: 108px;
            flex: 0 0 108px;
        }
        .layout-thermal .qr-svg-box {
            width: 112px;
            height: 112px;
            flex: 0 0 112px;
        }
        .qr-svg-box svg {
            width: 100%;
            height: 100%;
            display: block;
        }
        .qr-meta {
            min-width: 0;
            flex: 1;
        }
        .qr-partno {
            font-size: 11px;
            font-weight: 700;
            color: #1D4ED8;
            word-break: break-word;
            line-height: 1.2;
        }
        .qr-partname {
            font-size: 12px;
            font-weight: 700;
            color: #0F172A;
            word-break: break-word;
            line-height: 1.25;
            margin-top: 2px;
        }
        .qr-sub {
            font-size: 10px;
            font-weight: 600;
            color: #475569;
            margin-top: 4px;
        }
        .qr-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 9px;
            color: #64748B;
            padding-top: 5px;
            border-top: 1px dashed #CBD5E1;
        }

        @page {
            margin: 8mm;
        }

        @media print {
            body {
                background: #FFFFFF !important;
            }
            .no-print {
                display: none !important;
            }
            .sheet-wrap {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .qr-grid {
                gap: 6mm;
            }
            .qr-grid.layout-a4_3 {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .qr-grid.layout-a4_4 {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
            .qr-grid.layout-thermal {
                display: block;
                max-width: none;
            }
            .layout-thermal .qr-card {
                width: 68mm;
                margin: 0 auto 4mm;
                page-break-after: always;
                break-after: page;
            }
            .layout-thermal .qr-card:last-child {
                page-break-after: auto;
                break-after: auto;
            }
            .qr-card {
                border: 1px solid #94A3B8;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body x-data="{ layout: {{ Js::from($layout) }} }">

    {{-- TOOLBAR NON-PRINT --}}
    <header class="print-toolbar no-print">
        <div class="max-w-6xl mx-auto flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('items.index') }}"
                   class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1.5 rounded bg-white/10 text-white hover:bg-white/20">
                    <x-icon name="chevron-left" />
                    <span>Master Item</span>
                </a>
                <div class="leading-tight">
                    <div class="text-sm font-bold text-white">Cetak Label QR Info Rak</div>
                    <div class="text-[11px] text-slate-300">
                        {{ $labels->count() }} label · Domain QR: <b class="num text-white">{{ $currentUrl }}</b>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded bg-white/10 p-0.5 text-xs">
                    <button type="button" @click="layout = 'a4_3'"
                            class="px-2.5 py-1 rounded font-semibold transition"
                            :class="layout === 'a4_3' ? 'bg-white text-slate-900' : 'text-slate-200 hover:text-white'">
                        A4 · 3 Kolom
                    </button>
                    <button type="button" @click="layout = 'a4_4'"
                            class="px-2.5 py-1 rounded font-semibold transition"
                            :class="layout === 'a4_4' ? 'bg-white text-slate-900' : 'text-slate-200 hover:text-white'">
                        A4 · 4 Kolom
                    </button>
                    <button type="button" @click="layout = 'thermal'"
                            class="px-2.5 py-1 rounded font-semibold transition"
                            :class="layout === 'thermal' ? 'bg-white text-slate-900' : 'text-slate-200 hover:text-white'">
                        Label Termal
                    </button>
                </div>

                <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
                    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 9V2h12v7"/>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                        <rect x="6" y="14" width="12" height="8"/>
                    </svg>
                    <span>Cetak / Simpan PDF</span>
                </button>
            </div>
        </div>
    </header>

    <div class="sheet-wrap">
        @if($isLocalUrl)
            <div class="alert alert-warn text-xs leading-relaxed mb-4 no-print">
                <x-icon name="alert" />
                <div>
                    Anda sedang mencetak dari alamat lokal / tunnel sementara (<b class="num">{{ $currentUrl }}</b>).
                    Stiker QR ini <b>tetap kebal ganti domain</b> saat dipindai lewat menu <b>Cek Info Stok</b> maupun seluruh modul scanner di dalam aplikasi,
                    namun untuk scan langsung lewat kamera bawaan HP, cetak ulang setelah domain permanen pabrik aktif.
                </div>
            </div>
        @endif

        <div id="qr-lib-error" class="alert alert-crit text-xs mb-4 no-print" style="display: none;">
            <x-icon name="alert" />
            <span>Pustaka lokal <code>public/vendor/qrcode/qrcode.min.js</code> belum ditemukan. Unduh file tersebut terlebih dahulu lalu muat ulang halaman ini.</span>
        </div>

        <div class="qr-grid" :class="'layout-' + layout">
            @foreach($labels as $lb)
                @php
                    $tyCls = 'ty-' . ($lb['type'] === 'FG/KOMPONEN' ? 'FGK' : $lb['type']);
                @endphp
                <article class="qr-card">
                    <div class="qr-card-top">
                        <span class="qr-brand">DRC · INFO STOK RAK</span>
                        <span class="ty {{ $tyCls }}">{{ $lb['type'] }}</span>
                    </div>

                    <div class="qr-body">
                        <div class="qr-svg-box" data-qr-text="{{ $lb['qr_url'] }}" aria-label="QR {{ $lb['part_name'] }}"></div>
                        <div class="qr-meta">
                            @if($lb['part_no'] !== '-')
                                <div class="qr-partno num">{{ $lb['part_no'] }}</div>
                            @endif
                            <div class="qr-partname">{{ $lb['part_name'] }}</div>
                            <div class="qr-sub num">{{ $lb['company_code'] }} · {{ $lb['uom'] }}</div>
                        </div>
                    </div>

                    <div class="qr-foot num">
                        <span>ID #{{ $lb['id'] }}</span>
                        <span>Scan untuk cek stok</span>
                    </div>
                </article>
            @endforeach
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.qrcode !== 'function') {
                const err = document.getElementById('qr-lib-error');
                if (err) err.style.display = 'flex';
                return;
            }

            document.querySelectorAll('[data-qr-text]').forEach(function (box) {
                const text = box.getAttribute('data-qr-text') || '';
                if (!text) return;
                const qr = window.qrcode(0, 'M');
                qr.addData(text);
                qr.make();
                box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
            });
        });
    </script>
</body>
</html>