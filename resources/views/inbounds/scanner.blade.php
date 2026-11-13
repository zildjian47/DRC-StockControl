<x-scanner-layout :backUrl="route('inbounds.index')">
    <x-slot name="header">Scanner Inbound</x-slot>

    @php
        $allScansList = ($allScans ?? $recentScans ?? collect())->map(function ($s) {
            $it = $s->item;
            return [
                'id'        => $s->id,
                'date'      => \Carbon\Carbon::parse($s->date_time)->format('Y-m-d'),
                'time_fmt'  => \Carbon\Carbon::parse($s->date_time)->format('d M Y, H:i'),
                'part_no'   => $it->part_no ?: '-',
                'part_name' => $it->part_name ?? '-',
                'type'      => $it->type ?? '-',
                'uom'       => $it->uom ?? '',
                'qty'       => (float) $s->qty,
                'user'      => $s->user->name ?? 'Operator',
                'search'    => strtolower(($it->part_no ?? '') . ' ' . ($it->part_name ?? '') . ' ' . floatval($s->qty) . ' ' . ($s->user->name ?? '')),
            ];
        })->values();

        $itemsJs = ($items ?? collect())->map(function ($i) {
            return [
                'id'           => $i->id,
                'part_no'      => $i->part_no ?: '',
                'part_name'    => $i->part_name,
                'type'         => $i->type,
                'uom'          => $i->uom,
                'company_code' => $i->company->company_code ?? '-',
                'company_type' => $i->company->type_company ?? '',
                'stock'        => (float) ($i->inventoryStock->current_stock ?? 0),
            ];
        })->values();
    @endphp

    <div x-data="inboundScanner({{ Js::from($itemsJs) }}, {{ Js::from($allScansList) }}, '{{ now()->format('Y-m-d') }}')"
         x-init="initScanner()"
         class="space-y-3.5">

        {{-- VISUAL STEPPER --}}
        <div class="scan-stepper">
            <div class="scan-step" :class="step === 1 ? 'active' : 'done'">
                <span class="scan-step-num" x-text="step > 1 ? '✓' : '1'"></span>
                <span class="truncate">Scan Item</span>
            </div>
            <div class="scan-step" :class="step === 2 ? 'active' : ''">
                <span class="scan-step-num">2</span>
                <span class="truncate">Input Qty</span>
            </div>
        </div>

        {{-- NOTIFIKASI ERROR LOCK --}}
        <div x-show="errMsg" x-cloak class="alert alert-crit">
            <div class="flex-1" x-text="errMsg"></div>
            <button type="button" @click="errMsg = ''" class="font-bold">✕</button>
        </div>

        {{-- PANEL SCAN & FORM --}}
        <div class="panel panel-body space-y-3.5">
            <div class="flex items-center justify-between">
                <h3 class="panel-title" x-text="step === 1 ? 'Scan Material / Barang Masuk' : 'Input Qty Inbound'"></h3>
                <span class="pill pill-info">RM · KOMPONEN · TRANSIT</span>
            </div>

            {{-- STEP 1: KAMERA PERSEGI & INPUT MANUAL --}}
            <div x-show="step === 1" class="space-y-3">
                <div class="camera-box">
                    <div id="qr-reader" x-show="camPermitted"></div>
                    <div class="scan-scope-guide" x-show="camPermitted"></div>

                    <div x-show="!camPermitted" x-cloak class="p-5 text-center text-white space-y-3 z-20">
                        <div class="w-11 h-11 rounded bg-white/10 mx-auto grid place-items-center text-sky-400">
                            <x-icon name="scan" class="w-6 h-6" />
                        </div>
                        <div class="space-y-1">
                            <div class="text-xs font-bold uppercase tracking-wider">Akses Kamera Diperlukan</div>
                            <p class="text-[11px] text-slate-300 leading-relaxed">
                                Izinkan akses kamera 1 kali pada perangkat ini. Untuk penggunaan berikutnya kamera akan aktif otomatis.
                            </p>
                        </div>
                        <button type="button" @click="requestCamera()" class="btn btn-primary w-full">
                            Aktifkan &amp; Izinkan Kamera
                        </button>
                    </div>
                </div>

                <div>
                    <label class="lbl">Input Manual Part No / Part Name</label>
                    <div class="flex gap-2">
                        <input type="text" x-model="manualCode"
                               @keydown.enter.prevent="verifyCode(manualCode)"
                               oninput="this.value = this.value.toUpperCase()"
                               placeholder="Ketik Part No atau Part Name..."
                               class="num uppercase flex-1 h-10 text-xs">
                        <button type="button" @click="verifyCode(manualCode)" class="btn btn-primary h-10 px-4">
                            Verifikasi
                        </button>
                    </div>
                </div>
            </div>

            {{-- STEP 2: KARTU ITEM & INPUT QTY --}}
            <div x-show="step === 2" x-cloak class="space-y-4">
                <div class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="num font-bold text-accent" x-text="selectedItem?.part_no || 'TANPA PART NO'"></span>
                        <span class="pill pill-good" x-text="selectedItem?.type"></span>
                    </div>
                    <div class="font-bold text-slate-900 text-sm" x-text="selectedItem?.part_name"></div>
                    <div class="flex items-center justify-between pt-1.5 border-t border-slate-200 text-slate-600">
                        <span>Supplier: <b class="text-slate-900" x-text="selectedItem?.company_code"></b></span>
                        <span>Stok Saat Ini: <b class="num text-slate-900" x-text="(selectedItem?.stock ?? 0) + ' ' + (selectedItem?.uom ?? '')"></b></span>
                    </div>
                </div>

                <form action="{{ route('inbounds.store') }}" method="POST" class="space-y-3.5">
                    @csrf
                    <input type="hidden" name="mode" value="scanner">
                    <input type="hidden" name="item_id" :value="selectedItem?.id">

                    <div>
                        <label class="lbl">Qty Inbound (<span x-text="selectedItem?.uom"></span>)</label>
                        <input type="text" name="qty" x-model="qtyExpr" required
                               placeholder="Contoh: 100 atau 4*25"
                               class="num w-full h-14 text-2xl text-center font-bold">
                        <div class="text-xs text-slate-500 mt-1 text-center" x-show="evalQty() !== null">
                            Hasil Kalkulasi: <b class="num text-emerald-700" x-text="'+' + evalQty() + ' ' + (selectedItem?.uom ?? '')"></b>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-full">
                        Simpan Inbound (+ Stok)
                    </button>
                </form>

                <button type="button" @click="resetScan()" class="btn w-full">
                    Batal &amp; Scan Ulang
                </button>
            </div>
        </div>

        {{-- RIWAYAT SCAN DENGAN FILTER TANGGAL & SEARCH --}}
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Scan Inbound</span>
                    <span class="count-chip"><span x-text="filteredHistory.length"></span> Data</span>
                </div>
                <button type="button" x-show="histDate || histSearch" x-cloak
                        @click="histDate = todayStr; histSearch = ''" class="btn btn-sm">
                    Hari Ini
                </button>
            </div>

            <div class="p-2.5 bg-slate-50 border-b border-slate-200 flex items-center gap-2">
                <input type="date" x-model="histDate" class="h-8 text-xs px-2 w-32 num">
                <input type="text" x-model="histSearch"
                       placeholder="Cari Part / Qty..."
                       class="h-8 text-xs px-2.5 flex-1 min-w-0">
            </div>

            <div class="divide-y divide-slate-200 max-h-80 overflow-y-auto">
                <template x-for="(row, idx) in filteredHistory" :key="row.id">
                    <div class="px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="num font-bold text-accent" x-text="row.part_no !== '-' ? row.part_no : row.type"></span>
                                <span class="text-[11px] text-slate-400 num" x-text="row.time_fmt"></span>
                            </div>
                            <div class="font-semibold text-slate-900 truncate" x-text="row.part_name"></div>
                            <div class="text-[11px] text-slate-500" x-text="'Petugas: ' + row.user"></div>
                        </div>
                        <div class="num font-bold text-emerald-700 whitespace-nowrap"
                             x-text="'+' + row.qty + ' ' + row.uom"></div>
                    </div>
                </template>
                <div x-show="filteredHistory.length === 0" class="empty">
                    Tidak ada riwayat scan pada tanggal/filter ini.
                </div>
            </div>
        </div>
    </div>

    <script>
        function inboundScanner(itemsList, scansList, todayStr) {
            return {
                items: itemsList,
                scans: scansList,
                todayStr: todayStr,
                step: 1,
                camPermitted: window.DRCScanner.isPermitted(),
                manualCode: '',
                selectedItem: null,
                qtyExpr: '',
                errMsg: '',
                histDate: todayStr,
                histSearch: '',

                get filteredHistory() {
                    return this.scans.filter(r => {
                        if (this.histDate && r.date !== this.histDate) return false;
                        if (this.histSearch && !r.search.includes(this.histSearch.toLowerCase())) return false;
                        return true;
                    });
                },
                initScanner() {
                    if (this.camPermitted) {
                        this.$nextTick(() => this.startCamera());
                    }
                },
                async requestCamera() {
                    this.errMsg = '';
                    const ok = await window.DRCScanner.start('qr-reader', (txt) => this.verifyCode(txt), (msg) => { this.errMsg = msg; });
                    if (ok) this.camPermitted = true;
                },
                async startCamera() {
                    await window.DRCScanner.start('qr-reader', (txt) => this.verifyCode(txt), (msg) => { this.errMsg = msg; });
                },
                verifyCode(raw) {
                    this.errMsg = '';
                    const code = String(raw || '').trim().toUpperCase();
                    if (!code) return;

                    const found = this.items.find(i => i.part_no.toUpperCase() === code || i.part_name.toUpperCase() === code);
                    if (!found) {
                        this.errMsg = `Ditolak: Item "${code}" tidak terdaftar pada Master Item aktif.`;
                        return;
                    }
                    if (!['RM', 'KOMPONEN', 'TRANSIT'].includes(found.type)) {
                        this.errMsg = `Ditolak: Item ${found.part_name} bertipe ${found.type}. Inbound hanya menerima RM, KOMPONEN, atau TRANSIT.`;
                        return;
                    }
                    if (found.company_type !== 'SUPPLIER') {
                        this.errMsg = `Ditolak: Perusahaan ${found.part_name} (${found.company_code}) bukan bertipe SUPPLIER.`;
                        return;
                    }

                    window.DRCScanner.stop();
                    this.selectedItem = found;
                    this.qtyExpr = '';
                    this.step = 2;
                },
                evalQty() {
                    if (!this.qtyExpr) return null;
                    const expr = String(this.qtyExpr).replace(/,/g, '.').replace(/\s+/g, '');
                    if (!/^[0-9\.\+\-\*\/\(\)]+$/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r) && r > 0) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                resetScan() {
                    this.selectedItem = null;
                    this.manualCode = '';
                    this.qtyExpr = '';
                    this.errMsg = '';
                    this.step = 1;
                    if (this.camPermitted) {
                        this.$nextTick(() => this.startCamera());
                    }
                }
            };
        }
    </script>
</x-scanner-layout>