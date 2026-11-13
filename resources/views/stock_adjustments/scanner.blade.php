<x-scanner-layout>
    <x-slot name="header">Scanner Stock Adjustment</x-slot>

    @php
        $todayScans = $todayScans ?? ($recentScans ?? \App\Models\StockAdjustment::with(['item', 'user'])
            ->whereDate('date_time', now()->toDateString())
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get());

        $items = $items ?? \App\Models\Item::with(['company', 'inventoryStock'])->get();

        $itemsJs = $items->map(fn ($i) => [
            'id'        => $i->id,
            'part_no'   => $i->part_no ?: '',
            'part_name' => $i->part_name,
            'type'      => $i->type,
            'uom'       => $i->uom,
            'stock'     => (float) ($i->inventoryStock->current_stock ?? 0),
        ])->values();
    @endphp

    <div x-data="stockAdjustmentScanner({{ Js::from($itemsJs) }})" x-init="initScanner()">
        <div class="panel panel-body">
            {{-- VISUAL STEPPER --}}
            <div class="stepper">
                <div class="step-item" :class="step === 1 ? 'active' : 'done'">
                    <span class="step-badge" x-text="step > 1 ? '✓' : '1'"></span>
                    <span>Scan Item</span>
                </div>
                <div class="step-line" :class="step > 1 ? 'done' : ''"></div>
                <div class="step-item" :class="step === 2 ? 'active' : ''">
                    <span class="step-badge">2</span>
                    <span>Stok &amp; Qty</span>
                </div>
            </div>

            {{-- LANGKAH 1: SCAN ITEM --}}
            <div x-show="step === 1" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Scan Item</h3>
                    <span class="pill pill-info">Semua Tipe Item</span>
                </div>

                <div class="camera-box">
                    <div id="qr-reader"></div>
                    <div x-show="cameraRunning" class="scan-laser"></div>
                    <div x-show="!cameraRunning" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-900/90 p-4 text-center">
                        <x-icon name="scan" class="w-8 h-8 text-slate-400 mb-2" />
                        <p class="text-xs text-slate-300 mb-2.5">Kamera belum aktif atau gunakan input cepat di bawah</p>
                        <button type="button" @click="startCam()" class="btn btn-primary btn-sm">Aktifkan Kamera QR</button>
                    </div>
                </div>

                <div>
                    <label class="lbl">Input Part No / Part Name</label>
                    <div class="flex gap-2">
                        <input type="text" x-model="manualCode" @keydown.enter.prevent="verifyCode(manualCode)"
                               placeholder="Ketik Part No atau Part Name..." class="num flex-1 h-10">
                        <button type="button" @click="verifyCode(manualCode)" class="btn btn-primary h-10 px-4">Verifikasi</button>
                    </div>
                </div>
            </div>

            {{-- LANGKAH 2: KALKULATOR 2 ARAH STOCK ADJUSTMENT --}}
            <div x-show="step === 2" x-cloak class="space-y-3.5">
                <div class="p-3 rounded bg-slate-50 border border-slate-200 space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="num text-xs font-bold text-accent" x-text="selectedItem?.part_no || '-'"></span>
                        <span class="pill pill-neu" x-text="selectedItem?.type"></span>
                    </div>
                    <div class="font-bold text-slate-900 text-sm" x-text="selectedItem?.part_name"></div>
                    <div class="text-xs text-slate-600">
                        Qty System Saat Ini: <b class="num text-slate-900" x-text="qtySystem + ' ' + (selectedItem?.uom || '')"></b>
                    </div>
                </div>

                <form action="{{ route('stock_adjustments.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <input type="hidden" name="mode" value="scanner">
                    <input type="hidden" name="item_id" :value="selectedItem?.id">
                    <input type="hidden" name="qty_actual" :value="computedActual">
                    <input type="hidden" name="qty_diff" :value="computedDiff">

                    <div>
                        <label class="lbl">Kategori Adjustment</label>
                        <select name="adjustment_type" x-model="adjType" class="w-full h-10 text-xs font-semibold">
                            <option value="STOCK OPNAME">STOCK OPNAME (Bebas +/-)</option>
                            <option value="SURPLUS">SURPLUS (+ Stok)</option>
                            <option value="FOUND">FOUND (+ Stok)</option>
                            <option value="REPAIR">REPAIR (+ Stok)</option>
                            <option value="LOSS">LOSS (- Stok)</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="lbl">Qty Actual (Fisik)</label>
                            <input type="text" x-model="rawActual" @input="onActualInput()"
                                   placeholder="Contoh: 3*50+10" class="num w-full h-11 text-sm font-bold text-center">
                        </div>
                        <div>
                            <label class="lbl">Qty Diff (Selisih +/-)</label>
                            <input type="text" x-model="rawDiff" @input="onDiffInput()"
                                   placeholder="Contoh: -5 atau +10" class="num w-full h-11 text-sm font-bold text-center">
                        </div>
                    </div>

                    <div class="p-2.5 rounded bg-blue-50/70 border border-blue-200 text-xs space-y-1">
                        <div>
                            Hasil Qty Actual = <b class="num text-slate-900" x-text="computedActual + ' ' + (selectedItem?.uom || '')"></b>
                        </div>
                        <div>
                            Selisih (Qty Diff) =
                            <b class="num" :class="computedDiff >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                               x-text="(computedDiff >= 0 ? '+' : '') + computedDiff + ' ' + (selectedItem?.uom || '')"></b>
                        </div>
                    </div>

                    <div x-show="validationMsg" class="text-xs text-rose-600 font-semibold" x-text="validationMsg"></div>

                    <div>
                        <label class="lbl">Catatan (Opsional)</label>
                        <input type="text" name="notes" placeholder="Keterangan penyesuaian stok..." class="w-full h-10 text-xs">
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="!canSubmit">
                        Simpan Stock Adjustment
                    </button>
                </form>

                <button type="button" class="btn w-full" @click="resetStep()">Batal &amp; Scan Ulang</button>
            </div>
        </div>

        {{-- RIWAYAT HARI INI --}}
        <div class="panel mt-3.5">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Adjustment Hari Ini</span>
                    <span class="count-chip">{{ $todayScans->count() }}</span>
                </div>
                <a href="{{ route('stock_adjustments.index') }}" class="text-xs font-semibold text-accent hover:underline">Mode Desktop</a>
            </div>
            <div class="max-h-72 overflow-y-auto divide-y divide-slate-200">
                @forelse($todayScans as $sc)
                    <div class="px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs">
                        <div class="min-w-0">
                            <div class="font-semibold text-slate-900 truncate">{{ $sc->item->part_name ?? '-' }}</div>
                            <div class="text-[11px] text-slate-500">
                                {{ $sc->adjustment_type }} · {{ \Carbon\Carbon::parse($sc->date_time)->format('H:i') }}
                            </div>
                        </div>
                        <div class="num font-bold {{ $sc->qty_diff >= 0 ? 'text-emerald-700' : 'text-rose-700' }} whitespace-nowrap">
                            {{ $sc->qty_diff >= 0 ? '+' : '' }}{{ floatval($sc->qty_diff) }} {{ $sc->item->uom ?? '' }}
                        </div>
                    </div>
                @empty
                    <div class="empty py-6">Belum ada Stock Adjustment hari ini.</div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        function stockAdjustmentScanner(itemsList) {
            return {
                items: itemsList,
                step: 1,
                cameraRunning: false,
                manualCode: '',
                selectedItem: null,
                adjType: 'STOCK OPNAME',
                qtySystem: 0,
                rawActual: '',
                rawDiff: '',
                computedActual: 0,
                computedDiff: 0,

                initScanner() {
                    this.startCam();
                },
                startCam() {
                    window.startDrcCamera('qr-reader', (code) => {
                        this.verifyCode(code);
                    }, (running) => {
                        this.cameraRunning = running;
                    });
                },
                verifyCode(raw) {
                    const q = String(raw || '').trim().toLowerCase();
                    if (!q) return;

                    const found = this.items.find(i =>
                        (i.part_no && i.part_no.toLowerCase() === q) ||
                        (i.part_name && i.part_name.toLowerCase() === q)
                    );

                    if (!found) {
                        window.showScannerToast('Ditolak: Item tidak terdaftar di Master Item.', 'crit');
                        return;
                    }

                    this.selectedItem = found;
                    this.qtySystem = Number(found.stock || 0);
                    this.rawActual = String(this.qtySystem);
                    this.rawDiff = '0';
                    this.computedActual = this.qtySystem;
                    this.computedDiff = 0;
                    this.step = 2;
                    window.stopDrcCamera();
                    this.cameraRunning = false;
                    window.showScannerToast('Item terverifikasi: ' + found.part_name, 'good');
                },
                evalMath(str) {
                    if (str === undefined || str === null || String(str).trim() === '') return null;
                    const expr = String(str).replace(/,/g, '.').replace(/\s+/g, '');
                    if (!/^[0-9\.\+\-\*\/\(\)]+$/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r)) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                onActualInput() {
                    const val = this.evalMath(this.rawActual);
                    if (val !== null) {
                        this.computedActual = val;
                        this.computedDiff = Math.round((val - this.qtySystem) * 100) / 100;
                        this.rawDiff = String(this.computedDiff);
                    }
                },
                onDiffInput() {
                    const val = this.evalMath(this.rawDiff);
                    if (val !== null) {
                        this.computedDiff = val;
                        this.computedActual = Math.round((this.qtySystem + val) * 100) / 100;
                        this.rawActual = String(this.computedActual);
                    }
                },
                get validationMsg() {
                    if (this.computedActual < 0) return 'Qty Actual (Fisik) tidak boleh bernilai minus.';
                    if (this.computedDiff === 0) return 'Selisih (Qty Diff) tidak boleh 0.';
                    if (this.adjType === 'LOSS' && this.computedActual >= this.qtySystem) {
                        return 'Kategori LOSS mewajibkan Qty Actual lebih kecil dari Qty System.';
                    }
                    if (['SURPLUS', 'FOUND', 'REPAIR'].includes(this.adjType) && this.computedActual <= this.qtySystem) {
                        return 'Kategori ' + this.adjType + ' mewajibkan Qty Actual lebih besar dari Qty System (+ Stok).';
                    }
                    return '';
                },
                get canSubmit() {
                    return this.selectedItem && this.validationMsg === '' && this.computedDiff !== 0 && this.computedActual >= 0;
                },
                resetStep() {
                    this.selectedItem = null;
                    this.manualCode = '';
                    this.step = 1;
                    this.startCam();
                }
            };
        }
    </script>
</x-scanner-layout>