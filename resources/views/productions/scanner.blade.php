<x-scanner-layout>
    <x-slot name="header">Scanner Production</x-slot>

    @php
        $todayScans = $todayScans ?? ($recentScans ?? \App\Models\Production::with(['item', 'user'])
            ->whereDate('date_time', now()->toDateString())
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get());

        $items = $items ?? \App\Models\Item::with(['company', 'inventoryStock'])->get();
        $activeLots = $activeLots ?? \App\Models\LotTracer::with(['targetItem', 'process'])
            ->where('status_lot', 'ON PROGRESS')
            ->where('qty_wip', '>', 0)
            ->get();

        $itemsJs = $items->map(fn ($i) => [
            'id'           => $i->id,
            'part_no'      => $i->part_no ?: '',
            'part_name'    => $i->part_name,
            'type'         => $i->type,
            'uom'          => $i->uom,
            'company_type' => $i->company->type_company ?? '',
        ])->values();

        $lotsJs = $activeLots->map(fn ($lt) => [
            'lot_number'     => $lt->lot_number,
            'target_item_id' => $lt->target_item_id,
            'process_name'   => $lt->process->process_name ?? '-',
            'target_est'     => (float) $lt->target_qty_estimasi,
            'actual_prod'    => (float) $lt->actual_qty_prod,
            'qty_wip'        => (float) $lt->qty_wip,
        ])->values();
    @endphp

    <div x-data="productionScanner({{ Js::from($itemsJs) }}, {{ Js::from($lotsJs) }})"
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

            {{-- LANGKAH 1: SCAN OUTPUT --}}
            <div x-show="step === 1" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Scan Output</h3>
                    <span class="pill pill-info">FG · FG/KOMPONEN · KOMPONEN</span>
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

            {{-- LANGKAH 2: PILIH LOT & INPUT QTY --}}
            <div x-show="step === 2" x-cloak class="space-y-3.5">
                <div class="p-3 rounded bg-slate-50 border border-slate-200 space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="num text-xs font-bold text-accent" x-text="selectedItem?.part_no || '-'"></span>
                        <span class="pill pill-neu" x-text="selectedItem?.type"></span>
                    </div>
                    <div class="font-bold text-slate-900 text-sm" x-text="selectedItem?.part_name"></div>
                </div>

                <form action="{{ route('productions.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <input type="hidden" name="mode" value="scanner">
                    <input type="hidden" name="item_id" :value="selectedItem?.id">
                    <input type="hidden" name="lot_number" :value="selectedLotNo">

                    <div>
                        <label class="lbl">Cari &amp; Pilih Nomor Lot WIP Aktif</label>
                        <input type="text" x-model="lotSearch" placeholder="Ketik Nomor Lot..."
                               class="num w-full h-9 text-xs mb-1.5">
                        <div class="max-h-36 overflow-y-auto border border-slate-200 rounded divide-y divide-slate-100 bg-white">
                            <template x-for="lt in filteredLots" :key="lt.lot_number">
                                <button type="button"
                                        @click="selectedLotNo = lt.lot_number"
                                        class="w-full px-3 py-2 text-left text-xs flex items-center justify-between hover:bg-slate-50"
                                        :class="selectedLotNo === lt.lot_number ? 'bg-blue-50/80 font-bold text-accent' : ''">
                                    <div>
                                        <span class="num font-bold" x-text="lt.lot_number"></span>
                                        <span class="text-slate-500 ml-1" x-text="'(' + lt.process_name + ')'"></span>
                                    </div>
                                    <span class="num font-semibold text-amber-700"
                                          x-text="'Sisa WIP: ' + lt.qty_wip + ' ' + (selectedItem?.uom || 'PCS')"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div x-show="selectedLotObj" class="p-2.5 rounded bg-amber-50/80 border border-amber-200 text-xs flex items-center justify-between">
                        <span>Batas Maksimal Input (Sisa WIP):</span>
                        <b class="num text-amber-900" x-text="(selectedLotObj?.qty_wip ?? 0) + ' ' + (selectedItem?.uom || 'PCS')"></b>
                    </div>

                    <div>
                        <label class="lbl">Qty Hasil Produksi (+ Stok)</label>
                        <input type="text" name="qty" x-model="qtyInput" required placeholder="0"
                               class="num w-full h-14 text-2xl text-center font-bold">
                        <div x-show="selectedLotObj && evalQty() > selectedLotObj.qty_wip"
                             class="text-xs text-rose-600 font-semibold mt-1 text-center">
                            Ditolak: Qty melebihi sisa WIP pada Lot ini!
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="!canSubmit">
                        Simpan Production (+ Stok)
                    </button>
                </form>

                <button type="button" class="btn w-full" @click="resetStep()">Batal &amp; Scan Ulang</button>
            </div>
        </div>

        {{-- RIWAYAT SCAN HARI INI --}}
        <div class="panel mt-3.5">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Scan Hari Ini</span>
                    <span class="count-chip">{{ $todayScans->count() }}</span>
                </div>
                <a href="{{ route('productions.index') }}" class="text-xs font-semibold text-accent hover:underline">Mode Desktop</a>
            </div>
            <div class="max-h-72 overflow-y-auto divide-y divide-slate-200">
                @forelse($todayScans as $sc)
                    <div class="px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="num font-bold text-accent">{{ $sc->lot_number }}</span>
                                <span class="text-slate-400">·</span>
                                <span class="font-semibold text-slate-900 truncate">{{ $sc->item->part_name ?? '-' }}</span>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                {{ \Carbon\Carbon::parse($sc->date_time)->format('H:i') }} · {{ $sc->user->name ?? 'Operator' }}
                            </div>
                        </div>
                        <div class="num font-bold text-emerald-700 whitespace-nowrap">
                            +{{ floatval($sc->qty) }} {{ $sc->item->uom ?? '' }}
                        </div>
                    </div>
                @empty
                    <div class="empty py-6">Belum ada scan Production hari ini.</div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        function productionScanner(itemsList, activeLotsList) {
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

                    if (!['FG', 'FG/KOMPONEN', 'KOMPONEN'].includes(found.type) || !['INTERNAL', 'CUSTOMER'].includes(found.company_type)) {
                        window.showScannerToast('Ditolak: Production hanya menerima item FG, FG/KOMPONEN, atau KOMPONEN (INTERNAL/CUSTOMER).', 'crit');
                        return;
                    }

                    const lotsForItem = this.activeLots.filter(l => Number(l.target_item_id) === Number(found.id) && l.qty_wip > 0);
                    if (lotsForItem.length === 0) {
                        window.showScannerToast('Ditolak: Tidak ada Nomor Lot aktif dengan sisa WIP > 0 untuk item ini.', 'crit');
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
                    return q <= this.selectedLotObj.qty_wip;
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