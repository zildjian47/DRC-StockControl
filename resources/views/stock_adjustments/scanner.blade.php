<x-scanner-layout header="Scanner Stock Adjustment" :backUrl="route('stock_adjustments.index')">
    @php
        $itemsData = ($items ?? \App\Models\Item::with(['company', 'inventoryStock'])->get())->map(fn ($i) => [
            'id'        => $i->id,
            'part_no'   => $i->part_no ?: '',
            'part_name' => $i->part_name,
            'type'      => $i->type,
            'uom'       => $i->uom,
            'stock'     => (float) ($i->inventoryStock->current_stock ?? 0),
        ])->values();
    @endphp

    <div class="panel panel-body"
         x-data="{
            step: 1,
            manualInput: '',
            items: {{ Js::from($itemsData) }},
            selectedItem: null,
            adjType: 'STOCK OPNAME',
            rawActual: '',
            rawDiff: '',
            numActual: 0,
            numDiff: 0,
            notes: '',
            scanner: null,

            init() {
                this.$nextTick(() => this.startCamera());
            },
            startCamera() {
                const el = document.getElementById('qr-reader');
                if (!el || typeof Html5QrcodeScanner === 'undefined') return;
                try {
                    if (this.scanner) this.scanner.clear();
                    this.scanner = new Html5QrcodeScanner('qr-reader', { fps: 10, qrbox: { width: 220, height: 220 } }, false);
                    this.scanner.render((decodedText) => {
                        this.handleScan(decodedText);
                    }, () => {});
                } catch (e) {}
            },
            evalMath(val) {
                if (val === '' || val === null || val === undefined) return null;
                const expr = String(val).replace(/,/g, '.').replace(/\s+/g, '');
                if (!/^[0-9\.\+\-\*\/\(\)]+$/.test(expr)) return null;
                try {
                    const r = Function('\"use strict\"; return (' + expr + ')')();
                    return (typeof r === 'number' && isFinite(r)) ? Math.round(r * 100) / 100 : null;
                } catch (e) { return null; }
            },
            findItem(code) {
                const q = String(code || '').trim().toLowerCase();
                if (!q) return null;
                return this.items.find(i => i.part_no.toLowerCase() === q || i.part_name.toLowerCase() === q) || null;
            },
            handleScan(rawCode) {
                const item = this.findItem(rawCode);
                if (!item) {
                    window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Ditolak: Part No / Part Name tidak ditemukan.' } }));
                    return;
                }
                this.selectedItem = item;
                this.rawActual = String(item.stock);
                this.numActual = item.stock;
                this.rawDiff = '0';
                this.numDiff = 0;
                this.manualInput = '';
                this.step = 2;
                window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Item terverifikasi. Silakan isi angka koreksi.', type: 'good' } }));
            },
            onActualInput() {
                const v = this.evalMath(this.rawActual);
                if (v !== null && v >= 0 && this.selectedItem) {
                    this.numActual = v;
                    this.numDiff = Math.round((v - this.selectedItem.stock) * 100) / 100;
                    this.rawDiff = String(this.numDiff);
                }
            },
            onDiffInput() {
                const v = this.evalMath(this.rawDiff);
                if (v !== null && this.selectedItem) {
                    const act = Math.round((this.selectedItem.stock + v) * 100) / 100;
                    if (act >= 0) {
                        this.numDiff = v;
                        this.numActual = act;
                        this.rawActual = String(act);
                    }
                }
            },
            get isRuleValid() {
                if (!this.selectedItem || this.numActual < 0 || this.numDiff === 0) return false;
                if (this.adjType === 'LOSS') return this.numActual < this.selectedItem.stock;
                if (['SURPLUS', 'FOUND', 'REPAIR'].includes(this.adjType)) return this.numActual > this.selectedItem.stock;
                return true;
            },
            resetAll() {
                this.step = 1;
                this.selectedItem = null;
                this.rawActual = '';
                this.rawDiff = '';
                this.manualInput = '';
            }
         }">

        {{-- VISUAL STEPPER 2 LANGKAH --}}
        <div class="stepper">
            <div class="step-item">
                <span class="step-circle"
                      :class="step === 1 ? 'step-circle-active' : 'step-circle-done'"
                      x-text="step > 1 ? '✓' : '1'"></span>
                <span class="step-label"
                      :class="step === 1 ? 'step-label-active' : 'step-label-done'">Scan Item</span>
            </div>

            <div class="step-line" :class="step > 1 ? 'step-line-done' : ''"></div>

            <div class="step-item">
                <span class="step-circle"
                      :class="step === 2 ? 'step-circle-active' : 'step-circle-idle'">2</span>
                <span class="step-label"
                      :class="step === 2 ? 'step-label-active' : 'step-label-idle'">Input Koreksi</span>
            </div>
        </div>

        {{-- STEP 1 --}}
        <div x-show="step === 1" class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-accent">Scan Item</span>
                <span class="pill pill-neu text-[10.5px]">Koreksi Stok Gudang</span>
            </div>

            <div class="camera-box">
                <div id="qr-reader"></div>
            </div>

            <div class="flex gap-2">
                <input type="text" x-model="manualInput"
                       @keydown.enter.prevent="handleScan(manualInput)"
                       placeholder="Ketik Part No / Nama Barang..."
                       class="num flex-1 h-10 text-xs">
                <button type="button" @click="handleScan(manualInput)" class="btn btn-primary px-4 h-10">
                    Verifikasi
                </button>
            </div>
        </div>

        {{-- STEP 2 --}}
        <div x-show="step === 2" x-cloak class="space-y-4">
            <div class="p-3 rounded bg-slate-50 border border-slate-200 flex items-center justify-between text-xs">
                <div>
                    <div class="num font-semibold text-accent" x-text="selectedItem?.part_no || '-'"></div>
                    <div class="font-bold text-slate-900" x-text="selectedItem?.part_name"></div>
                </div>
                <div class="text-right">
                    <div class="text-slate-500">Qty System:</div>
                    <b class="num text-sm text-slate-900" x-text="selectedItem?.stock + ' ' + selectedItem?.uom"></b>
                </div>
            </div>

            <form action="{{ route('stock_adjustments.store') }}" method="POST" class="space-y-3.5">
                @csrf
                <input type="hidden" name="mode" value="scanner">
                <input type="hidden" name="item_id" :value="selectedItem?.id">
                <input type="hidden" name="qty_actual" :value="numActual">
                <input type="hidden" name="qty_diff" :value="numDiff">

                <div>
                    <label class="lbl">Kategori Penyesuaian</label>
                    <select name="adjustment_type" x-model="adjType" required class="w-full h-11 text-xs font-semibold">
                        <option value="STOCK OPNAME">STOCK OPNAME (Bebas +/-)</option>
                        <option value="LOSS">LOSS (Qty Actual &lt; System)</option>
                        <option value="SURPLUS">SURPLUS (Qty Actual &gt; System)</option>
                        <option value="FOUND">FOUND (Qty Actual &gt; System)</option>
                        <option value="REPAIR">REPAIR (+ Stok Selesai Repair)</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="lbl">Qty Actual (Kalkulator)</label>
                        <input type="text" x-model="rawActual" @input="onActualInput()" required
                               placeholder="Contoh: 3*50+12" class="num w-full h-11 text-sm font-bold text-center">
                    </div>
                    <div>
                        <label class="lbl">Qty Diff (+/-)</label>
                        <input type="text" x-model="rawDiff" @input="onDiffInput()" required
                               placeholder="Contoh: -2 atau +5" class="num w-full h-11 text-sm font-bold text-center">
                    </div>
                </div>

                <div class="p-2.5 rounded bg-blue-50/70 border border-blue-200 text-xs flex justify-between items-center">
                    <span>Preview Hasil:</span>
                    <span>
                        Actual: <b class="num text-slate-900" x-text="numActual"></b> |
                        Diff: <b class="num" :class="numDiff >= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="(numDiff > 0 ? '+' : '') + numDiff"></b>
                        <span x-text="selectedItem?.uom"></span>
                    </span>
                </div>

                <div>
                    <label class="lbl">Catatan (Opsional)</label>
                    <input type="text" name="notes" x-model="notes" placeholder="Keterangan penyesuaian..." class="w-full h-10 text-xs">
                </div>

                <button type="submit" :disabled="!isRuleValid" class="btn btn-primary btn-lg w-full">
                    Simpan Stock Adjustment
                </button>
            </form>

            <button type="button" @click="resetAll()" class="btn w-full">
                Scan Ulang dari Awal
            </button>
        </div>
    </div>

    {{-- RIWAYAT SCAN HARI INI --}}
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-title">
                <span>Riwayat Penyesuaian Hari Ini</span>
                <span class="count-chip">{{ $todayScans->count() }}</span>
            </h3>
        </div>
        <div class="max-h-72 overflow-y-auto divide-y divide-slate-200">
            @forelse($todayScans as $scan)
                <div class="px-3.5 py-2.5 flex items-center justify-between text-xs">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="pill pill-neu text-[10px] h-4 px-1.5">{{ $scan->adjustment_type }}</span>
                            <span class="num text-slate-500">{{ \Carbon\Carbon::parse($scan->date_time)->format('H:i') }}</span>
                        </div>
                        <div class="font-semibold text-slate-900 mt-0.5">{{ $scan->item->part_name ?? '-' }}</div>
                    </div>
                    <div class="num font-bold text-sm whitespace-nowrap {{ $scan->qty_diff >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $scan->qty_diff > 0 ? '+' : '' }}{{ floatval($scan->qty_diff) }} {{ $scan->item->uom ?? '' }}
                    </div>
                </div>
            @empty
                <div class="empty py-6 text-xs">Belum ada Stock Adjustment hari ini.</div>
            @endforelse
        </div>
    </div>
</x-scanner-layout>