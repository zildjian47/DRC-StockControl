<x-scanner-layout :backUrl="route('stock_adjustments.index')">
    <x-slot name="header">Scanner Stock Adjustment</x-slot>

    @php
        $allScansData = \App\Models\StockAdjustment::with(['item.company', 'user'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->limit(300)
            ->get()
            ->map(function ($s) {
                $dt = \Carbon\Carbon::parse($s->date_time);
                return [
                    'id'        => $s->id,
                    'date'      => $dt->format('Y-m-d'),
                    'time'      => $dt->format('H:i'),
                    'date_fmt'  => $dt->format('d M Y, H:i'),
                    'part_no'   => $s->item->part_no ?: '-',
                    'part_name' => $s->item->part_name ?? '-',
                    'uom'       => $s->item->uom ?? '',
                    'adj_type'  => $s->adjustment_type,
                    'qty_diff'  => (float) $s->qty_diff,
                    'qty_act'   => (float) $s->qty_actual,
                    'user_name' => $s->user->name ?? 'PPIC',
                ];
            })->values();

        $itemsPayload = $items->map(fn ($i) => [
            'id'        => $i->id,
            'part_no'   => $i->part_no ? strtoupper(trim($i->part_no)) : '',
            'part_name' => strtoupper(trim($i->part_name)),
            'type'      => $i->type,
            'uom'       => $i->uom,
            'stock'     => $i->inventoryStock ? (float) $i->inventoryStock->current_stock : 0,
        ])->values();
    @endphp

    <div x-data="adjScanner({{ Js::from($itemsPayload) }}, {{ Js::from($allScansData) }}, '{{ now()->format('Y-m-d') }}')"
         x-init="initScanner()">

        <div class="scan-stepper mb-3">
            <div class="scan-step" :class="step === 1 ? 'active' : 'done'">
                <span class="scan-step-num" x-text="step > 1 ? '✓' : '1'"></span>
                <span class="truncate">Scan Item Gudang</span>
            </div>
            <div class="scan-step" :class="step === 2 ? 'active' : ''">
                <span class="scan-step-num">2</span>
                <span class="truncate">Input Adjustment</span>
            </div>
        </div>

        <div x-show="errorMsg" x-cloak class="alert alert-crit mb-3 flex items-center justify-between">
            <span x-text="errorMsg"></span>
            <button type="button" @click="errorMsg = ''" class="act-btn"><x-icon name="x" /></button>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span x-show="step === 1">Langkah 1 · Scan QR Barang</span>
                    <span x-show="step === 2" x-cloak>Langkah 2 · Input Penyesuaian Stok</span>
                </div>
                <span class="pill pill-info">OPNAME · LOSS · SURPLUS · FOUND · REPAIR</span>
            </div>

            <div class="panel-body space-y-4">
                <div x-show="step === 1">
                    <div class="camera-box mb-3">
                        <div id="qr-reader" x-show="cameraActive"></div>
                        <div class="scan-scope-guide" x-show="cameraActive"></div>

                        <div x-show="!cameraActive" class="p-6 text-center space-y-3">
                            <div class="w-12 h-12 rounded bg-white/10 text-white grid place-items-center mx-auto">
                                <x-icon name="scan" class="w-6 h-6" />
                            </div>
                            <div class="text-xs text-slate-300 leading-relaxed">
                                Aktifkan izin kamera 1 kali untuk perangkat ini, atau gunakan input kode manual di bawah.
                            </div>
                            <button type="button" @click="activateCamera()" class="btn btn-primary">
                                <x-icon name="scan" /> Aktifkan Kamera Scanner
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="lbl">Ketik Part No / Part Name Barang</label>
                        <div class="flex gap-2">
                            <input type="text" x-model="manualCode"
                                   @keydown.enter.prevent="handleScan(manualCode)"
                                   placeholder="Contoh: FG-BKT-001 atau SHEET METAL SPHC 2.0MM"
                                   class="num uppercase flex-1 h-10 text-xs font-semibold">
                            <button type="button" @click="handleScan(manualCode)" class="btn btn-primary px-4 h-10">
                                Cek Kode
                            </button>
                        </div>
                    </div>
                </div>

                <div x-show="step === 2" x-cloak class="space-y-4">
                    <div class="p-3 rounded bg-slate-50 border border-slate-200 flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <span class="pill pill-neu" x-text="scannedItem?.type"></span>
                            <div class="num text-xs font-bold text-accent mt-1" x-text="scannedItem?.part_no || '-'"></div>
                            <div class="text-xs font-semibold text-slate-900 break-words" x-text="scannedItem?.part_name"></div>
                        </div>
                        <div class="text-right whitespace-nowrap flex-none">
                            <div class="text-[11px] text-slate-500">Qty System</div>
                            <div class="num text-sm font-bold text-accent" x-text="qtySystem + ' ' + (scannedItem?.uom ?? '')"></div>
                        </div>
                    </div>

                    <form action="{{ route('stock_adjustments.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="mode" value="scanner">
                        <input type="hidden" name="item_id" :value="scannedItem?.id">

                        <div>
                            <label class="lbl">Kategori Adjustment</label>
                            <select name="adjustment_type" x-model="adjType" required class="w-full h-11 text-xs font-semibold">
                                @foreach($types as $t)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="lbl">Qty Actual / Fisik (Kalkulator: 3*50+5)</label>
                                <input type="text" name="qty_actual" x-model="rawActual" @input="onInputActual()"
                                       placeholder="Contoh: 145" class="num w-full h-11 text-base font-bold">
                            </div>
                            <div>
                                <label class="lbl">Qty Diff / Selisih (+/-)</label>
                                <input type="text" name="qty_diff" x-model="rawDiff" @input="onInputDiff()"
                                       placeholder="Contoh: +5 atau -3" class="num w-full h-11 text-base font-bold">
                            </div>
                        </div>

                        <div x-show="previewText" class="p-2.5 rounded bg-blue-50/70 border border-blue-200 text-xs text-blue-900 font-semibold num"
                             x-text="previewText"></div>

                        <div>
                            <label class="lbl">Catatan / Keterangan (Opsional)</label>
                            <input type="text" name="notes" placeholder="Contoh: Koreksi stock opname lapangan" class="w-full h-10 text-xs">
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-full">
                            Simpan Stock Adjustment
                        </button>
                    </form>

                    <button type="button" @click="resetAll()" class="btn w-full">
                        Batal &amp; Scan Ulang
                    </button>
                </div>
            </div>
        </div>

        {{-- PANEL RIWAYAT SCAN --}}
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Stock Adjustment</span>
                    <span class="count-chip"><span x-text="filteredHistory.length"></span><span>Data</span></span>
                </div>
            </div>
            <div class="p-3 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center gap-2">
                <input type="date" x-model="histDate" class="h-8 text-xs px-2 w-36">
                <input type="text" x-model="histSearch" placeholder="Cari Part / Kategori / Petugas..." class="h-8 text-xs px-2.5 flex-1 min-w-[140px]">
                <button type="button" x-show="histSearch || histDate !== todayStr" x-cloak
                        @click="histSearch = ''; histDate = todayStr" class="btn btn-sm">
                    Hari Ini
                </button>
            </div>
            <div class="divide-y divide-slate-200 max-h-80 overflow-y-auto">
                <template x-for="s in filteredHistory" :key="s.id">
                    <div class="p-3 flex items-center justify-between gap-3 text-xs bg-white hover:bg-slate-50">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="pill pill-info text-[10px]" x-text="s.adj_type"></span>
                                <span class="num text-[11px] text-slate-500" x-text="s.date_fmt"></span>
                                <span class="text-[11px] text-slate-500" x-text="'· ' + s.user_name"></span>
                            </div>
                            <div class="num font-bold text-accent mt-1" x-text="s.part_no"></div>
                            <div class="font-semibold text-slate-900 break-words" x-text="s.part_name"></div>
                        </div>
                        <div class="text-right whitespace-nowrap flex-none">
                            <div class="num text-sm font-bold"
                                 :class="s.qty_diff > 0 ? 'text-emerald-700' : 'text-rose-700'"
                                 x-text="(s.qty_diff > 0 ? '+' : '') + s.qty_diff + ' ' + s.uom"></div>
                            <div class="num text-[11px] text-slate-500" x-text="'Actual: ' + s.qty_act + ' ' + s.uom"></div>
                        </div>
                    </div>
                </template>
                <div x-show="filteredHistory.length === 0" class="empty">
                    Tidak ada riwayat adjustment pada tanggal / pencarian ini.
                </div>
            </div>
        </div>
    </div>

    <script>
        function adjScanner(items, allScans, todayStr) {
            return {
                step: 1,
                cameraActive: false,
                manualCode: '',
                errorMsg: '',
                items: items,
                allScans: allScans,
                todayStr: todayStr,
                histDate: todayStr,
                histSearch: '',
                scannedItem: null,
                adjType: 'STOCK OPNAME',
                qtySystem: 0,
                rawActual: '',
                rawDiff: '',
                previewText: '',

                initScanner() {
                    if (window.DRCScanner && window.DRCScanner.isPermitted()) {
                        this.activateCamera();
                    }
                },
                async activateCamera() {
                    this.errorMsg = '';
                    this.cameraActive = true;
                    await this.$nextTick();
                    const ok = await window.DRCScanner.start(
                        'qr-reader',
                        (decoded) => this.handleScan(decoded),
                        (err) => {
                            this.cameraActive = false;
                            this.errorMsg = err;
                        }
                    );
                    if (!ok) this.cameraActive = false;
                },
                handleScan(rawCode) {
                    this.errorMsg = '';
                    const c = String(rawCode || '').trim().toUpperCase();
                    if (!c) return;
                    const found = this.items.find(i => i.part_no === c || i.part_name === c);
                    if (!found) {
                        this.errorMsg = `Ditolak: Kode "${rawCode}" tidak ditemukan di Master Item aktif.`;
                        return;
                    }
                    this.scannedItem = found;
                    this.qtySystem = found.stock;
                    this.manualCode = '';
                    this.step = 2;
                    window.DRCScanner.stop();
                    this.cameraActive = false;
                },
                evalCalc(expr) {
                    const clean = String(expr || '').replace(/,/g, '.').replace(/\s+/g, '');
                    if (!clean || !/^[0-9\.\+\-\*\/\(\)]+$/.test(clean)) return null;
                    try {
                        const r = Function('return (' + clean + ')')();
                        return (typeof r === 'number' && isFinite(r)) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                onInputActual() {
                    const val = this.evalCalc(this.rawActual);
                    if (val === null) {
                        this.previewText = '';
                        return;
                    }
                    const diff = Math.round((val - this.qtySystem) * 100) / 100;
                    this.rawDiff = (diff > 0 ? '+' : '') + diff;
                    const uom = this.scannedItem?.uom || '';
                    this.previewText = `Qty Actual = ${val} ${uom} | Selisih = ${diff > 0 ? '+' : ''}${diff} ${uom} (Stok: ${this.qtySystem} → ${val} ${uom})`;
                },
                onInputDiff() {
                    const diff = this.evalCalc(this.rawDiff);
                    if (diff === null) {
                        this.previewText = '';
                        return;
                    }
                    const act = Math.round((this.qtySystem + diff) * 100) / 100;
                    this.rawActual = String(act);
                    const uom = this.scannedItem?.uom || '';
                    this.previewText = `Qty Actual = ${act} ${uom} | Selisih = ${diff > 0 ? '+' : ''}${diff} ${uom} (Stok: ${this.qtySystem} → ${act} ${uom})`;
                },
                get filteredHistory() {
                    return this.allScans.filter(s => {
                        if (this.histDate && s.date !== this.histDate) return false;
                        if (this.histSearch) {
                            const q = this.histSearch.toLowerCase();
                            const hay = `${s.part_no} ${s.part_name} ${s.adj_type} ${s.qty_diff} ${s.user_name}`.toLowerCase();
                            if (!hay.includes(q)) return false;
                        }
                        return true;
                    });
                },
                resetAll() {
                    this.scannedItem = null;
                    this.rawActual = '';
                    this.rawDiff = '';
                    this.previewText = '';
                    this.manualCode = '';
                    this.errorMsg = '';
                    this.step = 1;
                    if (window.DRCScanner && window.DRCScanner.isPermitted()) {
                        this.activateCamera();
                    }
                }
            };
        }
    </script>
</x-scanner-layout>