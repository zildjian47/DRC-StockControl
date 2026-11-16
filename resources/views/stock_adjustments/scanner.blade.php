<x-scanner-layout :backUrl="route('stock_adjustments.index')">
    <x-slot name="header">Scanner Stock Adjustment</x-slot>

    @php
        $itemsJs = $items->map(fn ($i) => [
            'id'        => $i->id,
            'part_no'   => strtoupper(trim((string) $i->part_no)),
            'part_name' => strtoupper(trim((string) $i->part_name)),
            'type'      => $i->type,
            'uom'       => $i->uom,
            'stock'     => floatval($i->inventoryStock->current_stock ?? 0),
        ])->values();
    @endphp

    <div x-data="adjScanner({{ Js::from($itemsJs) }}, '{{ now()->toDateString() }}')" class="space-y-4">
        <div class="panel panel-body space-y-4">
            {{-- Visual Stepper --}}
            <div class="grid grid-cols-2 gap-2">
                <div class="px-3 py-2 rounded border text-xs font-semibold flex items-center gap-2"
                     :class="step === 1 ? 'border-accent bg-blue-50 text-accent' : 'border-emerald-300 bg-emerald-50 text-emerald-700'">
                    <span class="w-5 h-5 rounded-full grid place-items-center text-[11px] font-bold"
                          :class="step === 1 ? 'bg-accent text-white' : 'bg-emerald-600 text-white'">1</span>
                    <span>Scan Item</span>
                </div>
                <div class="px-3 py-2 rounded border text-xs font-semibold flex items-center gap-2"
                     :class="step === 2 ? 'border-accent bg-blue-50 text-accent' : 'border-slate-200 bg-slate-50 text-slate-400'">
                    <span class="w-5 h-5 rounded-full grid place-items-center text-[11px] font-bold"
                          :class="step === 2 ? 'bg-accent text-white' : 'bg-slate-200 text-slate-500'">2</span>
                    <span>Input Adjustment</span>
                </div>
            </div>

            {{-- pesan error lokal --}}
            <div x-show="errMsg" x-cloak class="alert alert-crit text-xs" x-text="errMsg"></div>

            {{-- STEP 1: SCAN QR ITEM --}}
            <div x-show="step === 1" class="space-y-3">
                <div class="camera-box">
                    <div id="qr-reader"></div>
                </div>

                <div class="flex gap-2">
                    <input type="text" x-model="manualCode" @keydown.enter.prevent="verifyCode(manualCode)"
                           placeholder="Ketik Part No atau Part Name..."
                           oninput="this.value = this.value.toUpperCase()"
                           class="num uppercase flex-1 h-10 text-xs">
                    <button type="button" @click="verifyCode(manualCode)" class="btn btn-primary h-10">Verifikasi</button>
                </div>
            </div>

            {{-- STEP 2: INPUT ADJUSTMENT DENGAN IKON KALKULATOR --}}
            <div x-show="step === 2" x-cloak class="space-y-4">
                <div class="sumc bg-slate-50">
                    <div class="flex items-center justify-between gap-2">
                        <span class="ty" :class="'ty-' + (selectedItem?.type === 'FG/KOMPONEN' ? 'FGK' : selectedItem?.type)" x-text="selectedItem?.type"></span>
                        <span class="pill pill-info num" x-text="'STOK SISTEM: ' + qtySystem + ' ' + (selectedItem?.uom || '')"></span>
                    </div>
                    <div class="dlg-code num mt-1" x-text="selectedItem?.part_no || '-'"></div>
                    <div class="dlg-name" x-text="selectedItem?.part_name"></div>
                </div>

                <form action="{{ route('stock_adjustments.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="mode" value="scanner">
                    <input type="hidden" name="item_id" :value="selectedItem?.id">

                    <div>
                        <span class="lbl">Kategori Adjustment</span>
                        <div class="opt-chips">
                            @foreach($types as $t)
                                <label>
                                    <input type="radio" name="adjustment_type" value="{{ $t }}" x-model="adjType" required>
                                    <span>{{ $t }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="lbl" for="sc_adj_actual">Qty Actual / Fisik</label>
                            <div class="inp-calc">
                                <input id="sc_adj_actual" type="text" name="qty_actual" x-model="rawActual" @input="onInputActual()"
                                       placeholder="Contoh: 120 atau 3*40" class="num w-full h-11 font-semibold">
                                <span class="inp-calc-ic" title="Mendukung perhitungan kalkulator (+, -, *, /)">
                                    <x-icon name="calc" />
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="lbl" for="sc_adj_diff">Qty Diff / Selisih (+/-)</label>
                            <div class="inp-calc">
                                <input id="sc_adj_diff" type="text" name="qty_diff" x-model="rawDiff" @input="onInputDiff()"
                                       placeholder="Contoh: -5 atau +10" class="num w-full h-11 font-semibold">
                                <span class="inp-calc-ic" title="Mendukung perhitungan kalkulator (+, -, *, /)">
                                    <x-icon name="calc" />
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="dlg-list">
                        <div>
                            <span>Pratinjau Hasil</span>
                            <b class="num"
                               :class="resultDiff > 0 ? 'text-emerald-700' : (resultDiff < 0 ? 'text-rose-700' : 'text-slate-800')"
                               x-text="'Actual: ' + resultActual + ' | Diff: ' + (resultDiff > 0 ? '+' : '') + resultDiff + ' ' + (selectedItem?.uom || '')"></b>
                        </div>
                    </div>

                    <div>
                        <label class="lbl" for="sc_adj_notes">Catatan (Opsional)</label>
                        <input id="sc_adj_notes" type="text" name="notes" placeholder="Keterangan penyesuaian..." class="w-full h-10 text-xs">
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-full">Simpan Stock Adjustment</button>
                </form>

                <button type="button" class="btn w-full" @click="resetScan()">Batal &amp; Scan Ulang</button>
            </div>
        </div>

        {{-- RIWAYAT ADJUSTMENT HARI INI --}}
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Adjustment</span>
                    <span class="count-chip"><span x-text="visibleHistCount"></span><span>DATA</span></span>
                </div>
                <div class="flex items-center gap-2">
                    <input type="text" x-model="histSearch" @input="recalcHist()" placeholder="Cari Part / Qty..." class="h-8 text-xs px-2.5 w-36">
                </div>
            </div>
            <div class="divide-y divide-slate-200 max-h-80 overflow-y-auto">
                @forelse($recentScans as $sc)
                    @php
                        $dt   = \Carbon\Carbon::parse($sc->date_time);
                        $diff = floatval($sc->qty_diff);
                    @endphp
                    <div data-hist-row
                         data-search="{{ strtolower(($sc->item->part_no ?? '') . ' ' . ($sc->item->part_name ?? '') . ' ' . $sc->adjustment_type . ' ' . $diff) }}"
                         x-show="matchHist($el)"
                         class="px-4 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="pill pill-info">{{ $sc->adjustment_type }}</span>
                                <span class="num text-xs text-slate-500">{{ $dt->format('H:i') }} WIB</span>
                            </div>
                            <div class="num text-xs font-bold text-accent mt-1 truncate">{{ $sc->item->part_no ?: '-' }}</div>
                            <div class="text-xs font-semibold text-slate-900 truncate">{{ $sc->item->part_name ?? '-' }}</div>
                        </div>
                        <div class="text-right num whitespace-nowrap flex-none">
                            <div class="text-sm font-bold {{ $diff >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $diff > 0 ? '+' : '' }}{{ $diff }} {{ $sc->item->uom ?? '' }}
                            </div>
                            <div class="text-[11px] text-slate-500">Actual: {{ floatval($sc->qty_actual) }}</div>
                        </div>
                    </div>
                @empty
                    <div class="empty">Belum ada riwayat Stock Adjustment hari ini.</div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        function adjScanner(items, todayStr) {
            return {
                items: items,
                step: 1,
                manualCode: '',
                errMsg: '',
                selectedItem: null,
                adjType: 'STOCK OPNAME',
                qtySystem: 0,
                rawActual: '',
                rawDiff: '',
                histSearch: '',
                visibleHistCount: {{ $recentScans->count() }},

                init() {
                    window.onQrCodeScanned = (decodedText) => {
                        if (this.step === 1) {
                            this.verifyCode(decodedText);
                        }
                    };
                },
                verifyCode(raw) {
                    this.errMsg = '';
                    const code = String(raw || '').trim().toUpperCase();
                    if (!code) return;

                    const found = this.items.find(i => i.part_no === code || i.part_name === code);
                    if (!found) {
                        this.errMsg = 'Ditolak: Part No atau Part Name [' + code + '] tidak terdaftar di Master Item.';
                        return;
                    }

                    this.selectedItem = found;
                    this.qtySystem = found.stock;
                    this.rawActual = String(found.stock);
                    this.rawDiff = '0';
                    this.step = 2;
                    if (window.stopQrScanner) window.stopQrScanner();
                },
                resetScan() {
                    this.step = 1;
                    this.selectedItem = null;
                    this.manualCode = '';
                    this.errMsg = '';
                    if (window.startQrScannerIfPermitted) window.startQrScannerIfPermitted();
                },
                evalCalc(raw) {
                    if (raw === null || raw === undefined) return null;
                    const expr = String(raw).replace(/,/g, '.').replace(/\s+/g, '');
                    if (!expr || !/^[0-9.+\-*\/()]+$/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r)) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                get resultActual() {
                    const a = this.evalCalc(this.rawActual);
                    return a === null ? this.qtySystem : a;
                },
                get resultDiff() {
                    const d = this.evalCalc(this.rawDiff);
                    return d === null ? 0 : d;
                },
                onInputActual() {
                    const val = this.evalCalc(this.rawActual);
                    if (val !== null) {
                        const diff = Math.round((val - this.qtySystem) * 100) / 100;
                        this.rawDiff = (diff > 0 ? '+' : '') + diff;
                    }
                },
                onInputDiff() {
                    const val = this.evalCalc(this.rawDiff);
                    if (val !== null) {
                        const act = Math.round((this.qtySystem + val) * 100) / 100;
                        this.rawActual = String(act);
                    }
                },
                matchHist(el) {
                    const s = el.dataset.search || '';
                    if (this.histSearch && !s.includes(this.histSearch.toLowerCase())) return false;
                    return true;
                },
                recalcHist() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('[data-hist-row]').forEach(el => {
                            if (this.matchHist(el)) c++;
                        });
                        this.visibleHistCount = c;
                    });
                }
            };
        }
    </script>
</x-scanner-layout>