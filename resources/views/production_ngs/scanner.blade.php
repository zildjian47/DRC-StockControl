<x-scanner-layout>
    <x-slot name="header">Scanner Form NG (QC)</x-slot>

    @php
        $todayScans = $todayScans ?? ($recentScans ?? \App\Models\ProductionNg::with(['item', 'user'])
            ->whereDate('date_time', now()->toDateString())
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get());

        $items = $items ?? \App\Models\Item::with(['company', 'inventoryStock'])->get();

        $activeLots = $activeLots ?? \App\Models\LotTracer::with(['targetItem', 'process'])
            ->where('status_lot', 'ON PROGRESS')
            ->get()
            ->filter(fn ($lt) => ((float) $lt->actual_qty_prod - (float) $lt->qty_ng) > 0)
            ->values();

        $itemsJs = $items->map(fn ($i) => [
            'id'        => $i->id,
            'part_no'   => $i->part_no ?: '',
            'part_name' => $i->part_name,
            'type'      => $i->type,
            'uom'       => $i->uom,
            'stock'     => (float) ($i->inventoryStock->current_stock ?? 0),
        ])->values();

        $lotsJs = $activeLots->map(fn ($lt) => [
            'lot_number'     => $lt->lot_number,
            'target_item_id' => $lt->target_item_id,
            'process_name'   => $lt->process->process_name ?? '-',
            'actual_prod'    => (float) $lt->actual_qty_prod,
            'qty_ng'         => (float) $lt->qty_ng,
            'act_fg'         => max(0, (float) $lt->actual_qty_prod - (float) $lt->qty_ng),
        ])->values();
    @endphp

    <div x-data="productionNgScanner({{ Js::from($itemsJs) }}, {{ Js::from($lotsJs) }})"
         x-init="initScanner()">
        <div class="panel panel-body">
            {{-- VISUAL STEPPER --}}
            <div class="stepper">
                <div class="step-item" :class="step === 1 ? 'active' : 'done'">
                    <span class="step-badge" x-text="step > 1 ? '✓' : '1'"></span>
                    <span>Scan Output</span>
                </div>
                <div class="step-line" :class="step > 1 ? 'done' : ''"></div>
                <div class="step-item" :class="step === 2 ? 'active' : ''">
                    <span class="step-badge">2</span>
                    <span>Lot &amp; Qty</span>
                </div>
            </div>

            {{-- LANGKAH 1: SCAN BARANG OUTPUT --}}
            <div x-show="step === 1" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Scan Output</h3>
                    <span class="pill pill-crit">Inspeksi QC NG</span>
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
                    <label class="lbl">Input Part No / Part Name Output</label>
                    <div class="flex gap-2">
                        <input type="text" x-model="manualCode" @keydown.enter.prevent="verifyCode(manualCode)"
                               placeholder="Ketik Part No atau Part Name..." class="num flex-1 h-10">
                        <button type="button" @click="verifyCode(manualCode)" class="btn btn-primary h-10 px-4">Verifikasi</button>
                    </div>
                </div>
            </div>

            {{-- LANGKAH 2: PILIH LOT, ALASAN NG, & INPUT QTY NG --}}
            <div x-show="step === 2" x-cloak class="space-y-3.5">
                <div class="p-3 rounded bg-slate-50 border border-slate-200 space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="num text-xs font-bold text-accent" x-text="selectedItem?.part_no || '-'"></span>
                        <span class="pill pill-neu" x-text="selectedItem?.type"></span>
                    </div>
                    <div class="font-bold text-slate-900 text-sm" x-text="selectedItem?.part_name"></div>
                    <div class="text-xs text-slate-600">
                        Stok Gudang Saat Ini: <b class="num text-slate-900" x-text="(selectedItem?.stock ?? 0) + ' ' + (selectedItem?.uom || 'PCS')"></b>
                    </div>
                </div>

                <form action="{{ route('production_ngs.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <input type="hidden" name="mode" value="scanner">
                    <input type="hidden" name="item_id" :value="selectedItem?.id">
                    <input type="hidden" name="lot_number" :value="selectedLotNo">

                    <div>
                        <label class="lbl">Cari &amp; Pilih Nomor Lot Aktif</label>
                        <input type="text" x-model="lotSearch" placeholder="Ketik Nomor Lot..."
                               class="num w-full h-9 text-xs mb-1.5">
                        <div class="max-h-36 overflow-y-auto border border-slate-200 rounded divide-y divide-slate-100 bg-white">
                            <template x-for="lt in filteredLots" :key="lt.lot_number">
                                <button type="button"
                                        @click="selectedLotNo = lt.lot_number"
                                        class="w-full px-3 py-2 text-left text-xs flex items-center justify-between hover:bg-slate-50"
                                        :class="selectedLotNo === lt.lot_number ? 'bg-blue-50/80 font-bold text-accent' : ''">
                                    <span class="num font-bold" x-text="lt.lot_number"></span>
                                    <span class="num text-emerald-700 font-semibold"
                                          x-text="'Act FG: ' + lt.act_fg + ' ' + (selectedItem?.uom || 'PCS')"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="lbl">Problem Reason (Alasan NG)</label>
                        <select name="problem_reason" required class="w-full h-10 text-xs font-semibold">
                            <option value="MATERIAL">MATERIAL</option>
                            <option value="PROSES">PROSES</option>
                            <option value="DIES/TOOLING">DIES/TOOLING</option>
                            <option value="SETTING/TRIAL">SETTING/TRIAL</option>
                            <option value="HANDLING/OPERATOR">HANDLING/OPERATOR</option>
                        </select>
                    </div>

                    <div x-show="selectedLotObj" class="p-2.5 rounded bg-rose-50/80 border border-rose-200 text-xs flex items-center justify-between">
                        <span>Batas Maksimal Input NG (Act FG):</span>
                        <b class="num text-rose-800" x-text="maxNgAllowed + ' ' + (selectedItem?.uom || 'PCS')"></b>
                    </div>

                    <div>
                        <label class="lbl">Qty NG (- Act FG &amp; - Stok)</label>
                        <input type="text" name="qty_ng" x-model="qtyInput" required placeholder="0"
                               class="num w-full h-14 text-2xl text-center font-bold">
                        <div x-show="evalQty() > maxNgAllowed"
                             class="text-xs text-rose-600 font-semibold mt-1 text-center">
                            Ditolak: Qty NG melebihi saldo Act FG / Stok yang tersedia!
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="!canSubmit">
                        Simpan Data NG (- Stok)
                    </button>
                </form>

                <button type="button" class="btn w-full" @click="resetStep()">Batal &amp; Scan Ulang</button>
            </div>
        </div>

        {{-- RIWAYAT NG HARI INI --}}
        <div class="panel mt-3.5">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Pencatatan NG Hari Ini</span>
                    <span class="count-chip">{{ $todayScans->count() }}</span>
                </div>
                <a href="{{ route('production_ngs.index') }}" class="text-xs font-semibold text-accent hover:underline">Mode Desktop</a>
            </div>
            <div class="max-h-72 overflow-y-auto divide-y divide-slate-200">
                @forelse($todayScans as $sc)
                    <div class="px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="num font-bold text-accent">{{ $sc->lot_number }}</span>
                                <span class="pill pill-crit text-[10px] h-4 px-1.5">{{ $sc->problem_reason }}</span>
                            </div>
                            <div class="font-semibold text-slate-900 truncate mt-0.5">{{ $sc->item->part_name ?? '-' }}</div>
                        </div>
                        <div class="num font-bold text-rose-700 whitespace-nowrap">
                            -{{ floatval($sc->qty_ng) }} {{ $sc->item->uom ?? '' }}
                        </div>
                    </div>
                @empty
                    <div class="empty py-6">Belum ada pencatatan NG hari ini.</div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        function productionNgScanner(itemsList, activeLotsList) {
            return {
                items: itemsList,
                activeLots: activeLotsList,
                step: 1,
                cameraRunning: false,
                manualCode: '',
                selectedItem: null,
                lotSearch: '',
                selectedLotNo: '',
                qtyInput: '',

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

                    const lotsForItem = this.activeLots.filter(l => Number(l.target_item_id) === Number(found.id) && l.act_fg > 0);
                    if (lotsForItem.length === 0) {
                        window.showScannerToast('Ditolak: Item ini belum memiliki Lot ON PROGRESS dengan saldo hasil produksi (Act FG > 0).', 'crit');
                        return;
                    }

                    this.selectedItem = found;
                    this.selectedLotNo = lotsForItem[0].lot_number;
                    this.step = 2;
                    window.stopDrcCamera();
                    this.cameraRunning = false;
                    window.showScannerToast('Item terverifikasi: ' + found.part_name, 'good');
                },
                get filteredLots() {
                    if (!this.selectedItem) return [];
                    return this.activeLots.filter(l => {
                        if (Number(l.target_item_id) !== Number(this.selectedItem.id)) return false;
                        if (!this.lotSearch) return true;
                        return l.lot_number.toLowerCase().includes(this.lotSearch.toLowerCase());
                    });
                },
                get selectedLotObj() {
                    return this.filteredLots.find(l => l.lot_number === this.selectedLotNo) || null;
                },
                get maxNgAllowed() {
                    if (!this.selectedLotObj || !this.selectedItem) return 0;
                    return Math.min(this.selectedLotObj.act_fg, this.selectedItem.stock);
                },
                evalQty() {
                    if (!this.qtyInput) return null;
                    const expr = String(this.qtyInput).replace(/,/g, '.').replace(/\s+/g, '');
                    if (!/^[0-9\.\+\-\*\/\(\)]+$/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r) && r > 0) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                get canSubmit() {
                    const q = this.evalQty();
                    if (!q || q <= 0 || !this.selectedLotObj) return false;
                    return q <= this.maxNgAllowed;
                },
                resetStep() {
                    this.selectedItem = null;
                    this.manualCode = '';
                    this.lotSearch = '';
                    this.selectedLotNo = '';
                    this.qtyInput = '';
                    this.step = 1;
                    this.startCam();
                }
            };
        }
    </script>
</x-scanner-layout>