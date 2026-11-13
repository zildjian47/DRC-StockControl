<x-scanner-layout :backUrl="route('stock_adjustments.index')">
    <x-slot name="header">Scanner Stock Adjustment</x-slot>

    @php
        $rawScans = $allScans ?? \App\Models\StockAdjustment::with(['item.company', 'user'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $allScansList = $rawScans->map(function ($s) {
            $it = $s->item;
            return [
                'id'        => $s->id,
                'date'      => \Carbon\Carbon::parse($s->date_time)->format('Y-m-d'),
                'time_fmt'  => \Carbon\Carbon::parse($s->date_time)->format('d M Y, H:i'),
                'part_no'   => $it->part_no ?: '-',
                'part_name' => $it->part_name ?? '-',
                'uom'       => $it->uom ?? '',
                'adj_type'  => $s->adjustment_type,
                'qty_diff'  => (float) $s->qty_diff,
                'qty_act'   => (float) $s->qty_actual,
                'user'      => $s->user->name ?? 'PPIC',
                'search'    => strtolower(($it->part_no ?? '') . ' ' . ($it->part_name ?? '') . ' ' . ($s->adjustment_type ?? '') . ' ' . floatval($s->qty_diff) . ' ' . floatval($s->qty_actual)),
            ];
        })->values();

        $itemsJs = ($items ?? collect())->map(fn ($i) => [
            'id'        => $i->id,
            'part_no'   => $i->part_no ?: '',
            'part_name' => $i->part_name,
            'type'      => $i->type,
            'uom'       => $i->uom,
            'stock'     => (float) ($i->inventoryStock->current_stock ?? 0),
        ])->values();
    @endphp

    <div x-data="stockAdjustmentScanner({{ Js::from($itemsJs) }}, {{ Js::from($allScansList) }}, '{{ now()->format('Y-m-d') }}')"
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
                <span class="truncate">Input Adjustment</span>
            </div>
        </div>

        <div x-show="errMsg" x-cloak class="alert alert-crit">
            <div class="flex-1" x-text="errMsg"></div>
            <button type="button" @click="errMsg = ''" class="font-bold">✕</button>
        </div>

        <div class="panel panel-body space-y-3.5">
            <div class="flex items-center justify-between">
                <h3 class="panel-title" x-text="step === 1 ? 'Scan Barang untuk Adjustment' : 'Input Penyesuaian Stok'"></h3>
                <span class="pill pill-info">OPNAME · LOSS · SURPLUS · REPAIR</span>
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

            {{-- STEP 2: FORM KALKULATOR 2 ARAH --}}
            <div x-show="step === 2" x-cloak class="space-y-3.5">
                <div class="p-3 rounded bg-slate-50 border border-slate-200 text-xs flex items-center justify-between">
                    <div>
                        <div class="num font-bold text-accent" x-text="selectedItem?.part_no || 'TANPA PART NO'"></div>
                        <div class="font-bold text-slate-900" x-text="selectedItem?.part_name"></div>
                    </div>
                    <div class="text-right">
                        <span class="text-slate-500 block text-[10.5px]">Qty System:</span>
                        <b class="num text-sm text-slate-900" x-text="(selectedItem?.stock ?? 0) + ' ' + (selectedItem?.uom ?? '')"></b>
                    </div>
                </div>

                <form action="{{ route('stock_adjustments.store') }}" method="POST" class="space-y-3.5">
                    @csrf
                    <input type="hidden" name="mode" value="scanner">
                    <input type="hidden" name="item_id" :value="selectedItem?.id">

                    <div>
                        <label class="lbl">Kategori Adjustment</label>
                        <select name="adjustment_type" x-model="adjType" required class="w-full h-10 text-xs font-semibold">
                            @foreach($types as $tp)
                                <option value="{{ $tp }}">{{ $tp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="lbl">Qty Actual (Kalkulator)</label>
                            <input type="text" name="qty_actual" x-model="exprActual"
                                   @input="onActualInput()"
                                   placeholder="Contoh: 3*50+10"
                                   class="num w-full h-11 text-sm font-bold text-center">
                        </div>
                        <div>
                            <label class="lbl">Qty Diff +/- (Kalkulator)</label>
                            <input type="text" name="qty_diff" x-model="exprDiff"
                                   @input="onDiffInput()"
                                   placeholder="Contoh: -5 atau +10"
                                   class="num w-full h-11 text-sm font-bold text-center">
                        </div>
                    </div>

                    <div x-show="valActual !== null || valDiff !== null"
                         class="p-2.5 rounded bg-blue-50/70 border border-blue-200 text-xs flex items-center justify-between">
                        <span>Menjadi: <b class="num text-accent" x-text="(valActual ?? sysStock) + ' ' + (selectedItem?.uom ?? '')"></b></span>
                        <span>Selisih: <b class="num" :class="(valDiff ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="((valDiff ?? 0) >= 0 ? '+' : '') + (valDiff ?? 0)"></b></span>
                    </div>

                    <div>
                        <label class="lbl">Catatan / Alasan</label>
                        <input type="text" name="notes" placeholder="Keterangan opname / repair..." class="w-full h-10 text-xs">
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-full">
                        Simpan Stock Adjustment
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
                    <span>Riwayat Scan Adjustment</span>
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
                       placeholder="Cari Part / Kategori / Qty..."
                       class="h-8 text-xs px-2.5 flex-1 min-w-0">
            </div>

            <div class="divide-y divide-slate-200 max-h-80 overflow-y-auto">
                <template x-for="row in filteredHistory" :key="row.id">
                    <div class="px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="pill pill-neu" x-text="row.adj_type"></span>
                                <span class="text-[11px] text-slate-400 num" x-text="row.time_fmt"></span>
                            </div>
                            <div class="font-semibold text-slate-900 truncate mt-0.5" x-text="row.part_name"></div>
                            <div class="text-[11px] text-slate-500" x-text="'Petugas: ' + row.user"></div>
                        </div>
                        <div class="text-right whitespace-nowrap">
                            <div class="num font-bold" :class="row.qty_diff >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                 x-text="(row.qty_diff >= 0 ? '+' : '') + row.qty_diff + ' ' + row.uom"></div>
                            <div class="num text-[11px] text-slate-500" x-text="'Actual: ' + row.qty_act"></div>
                        </div>
                    </div>
                </template>
                <div x-show="filteredHistory.length === 0" class="empty">
                    Tidak ada riwayat adjustment pada tanggal/filter ini.
                </div>
            </div>
        </div>
    </div>

    <script>
        function stockAdjustmentScanner(itemsList, scansList, todayStr) {
            return {
                items: itemsList,
                scans: scansList,
                todayStr: todayStr,
                step: 1,
                camPermitted: window.DRCScanner.isPermitted(),
                manualCode: '',
                selectedItem: null,
                adjType: 'STOCK OPNAME',
                exprActual: '',
                exprDiff: '',
                valActual: null,
                valDiff: null,
                errMsg: '',
                histDate: todayStr,
                histSearch: '',

                get sysStock() {
                    return this.selectedItem ? parseFloat(this.selectedItem.stock) : 0;
                },
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

                    window.DRCScanner.stop();
                    this.selectedItem = found;
                    this.exprActual = String(found.stock);
                    this.exprDiff = '0';
                    this.valActual = found.stock;
                    this.valDiff = 0;
                    this.step = 2;
                },
                evalMath(raw) {
                    if (raw === null || raw === undefined || String(raw).trim() === '') return null;
                    const expr = String(raw).replace(/,/g, '.').replace(/\s+/g, '');
                    if (!/^[0-9\.\+\-\*\/\(\)]+$/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r)) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                onActualInput() {
                    const a = this.evalMath(this.exprActual);
                    this.valActual = a;
                    if (a !== null) {
                        const d = Math.round((a - this.sysStock) * 100) / 100;
                        this.valDiff = d;
                        this.exprDiff = String(d);
                    }
                },
                onDiffInput() {
                    const d = this.evalMath(this.exprDiff);
                    this.valDiff = d;
                    if (d !== null) {
                        const a = Math.round((this.sysStock + d) * 100) / 100;
                        this.valActual = a;
                        this.exprActual = String(a);
                    }
                },
                resetScan() {
                    this.selectedItem = null;
                    this.manualCode = '';
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