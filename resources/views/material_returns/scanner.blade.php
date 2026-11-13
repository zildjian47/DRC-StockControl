<x-scanner-layout>
    <x-slot name="header">Scanner Material Return</x-slot>

    @php
        $todayScans = $todayScans ?? ($recentScans ?? \App\Models\MaterialReturn::with(['materialItem', 'targetItem', 'user'])
            ->whereDate('date_time', now()->toDateString())
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get());

        $items = $items ?? \App\Models\Item::with(['company', 'inventoryStock'])->get();
        $returnedLots = \App\Models\MaterialReturn::pluck('lot_number')->unique()->toArray();

        $activeLots = $activeLots ?? \App\Models\LotTracer::with(['materialItem', 'targetItem'])
            ->where('status_lot', 'ON PROGRESS')
            ->get()
            ->filter(fn ($lt) => !in_array($lt->lot_number, $returnedLots) && $lt->material_saat_ini > 0)
            ->values();

        $itemsJs = $items->map(fn ($i) => [
            'id'           => $i->id,
            'part_no'      => $i->part_no ?: '',
            'part_name'    => $i->part_name,
            'type'         => $i->type,
            'uom'          => $i->uom,
            'company_type' => $i->company->type_company ?? '',
        ])->values();

        $lotsJs = $activeLots->map(fn ($lt) => [
            'lot_number'       => $lt->lot_number,
            'material_item_id' => $lt->material_item_id,
            'target_item_id'   => $lt->target_item_id,
            'target_name'      => $lt->targetItem->part_name ?? '-',
            'mat_out'          => (float) $lt->qty_material_out,
            'mat_current'      => (float) $lt->material_saat_ini,
        ])->values();
    @endphp

    <div x-data="materialReturnScanner({{ Js::from($itemsJs) }}, {{ Js::from($lotsJs) }})"
         x-init="initScanner()">
        <div class="panel panel-body">
            {{-- VISUAL STEPPER 3 LANGKAH --}}
            <div class="stepper">
                <div class="step-item" :class="step === 1 ? 'active' : 'done'">
                    <span class="step-badge" x-text="step > 1 ? '✓' : '1'"></span>
                    <span>Scan Material</span>
                </div>
                <div class="step-line" :class="step > 1 ? 'done' : ''"></div>
                <div class="step-item" :class="step === 2 ? 'active' : (step > 2 ? 'done' : '')">
                    <span class="step-badge" x-text="step > 2 ? '✓' : '2'"></span>
                    <span>Scan Output</span>
                </div>
                <div class="step-line" :class="step > 2 ? 'done' : ''"></div>
                <div class="step-item" :class="step === 3 ? 'active' : ''">
                    <span class="step-badge">3</span>
                    <span>Lot &amp; Qty</span>
                </div>
            </div>

            {{-- AREA KAMERA (STEP 1 & STEP 2) --}}
            <div x-show="step === 1 || step === 2" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700"
                        x-text="step === 1 ? 'Scan Material' : 'Scan Output'"></h3>
                    <span class="pill pill-info"
                          x-text="step === 1 ? 'Material Dikembalikan' : 'Target Output Lot'"></span>
                </div>

                <div x-show="step === 2 && materialItem" class="p-2.5 rounded bg-blue-50/70 border border-blue-200 text-xs flex items-center justify-between">
                    <div>
                        <span class="text-slate-500">Material:</span>
                        <b class="text-slate-900 ml-1" x-text="materialItem?.part_name"></b>
                    </div>
                    <span class="pill pill-good" x-text="materialItem?.uom"></span>
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
                    <label class="lbl" x-text="step === 1 ? 'Input Part No / Part Name Material' : 'Input Part No / Part Name Output'"></label>
                    <div class="flex gap-2">
                        <input type="text" x-model="manualCode" @keydown.enter.prevent="handleScan(manualCode)"
                               placeholder="Ketik Part No atau Part Name..." class="num flex-1 h-10">
                        <button type="button" @click="handleScan(manualCode)" class="btn btn-primary h-10 px-4">Verifikasi</button>
                    </div>
                </div>

                <button type="button" x-show="step === 2" @click="resetAll()" class="btn w-full">
                    Ulangi dari Scan Material
                </button>
            </div>

            {{-- LANGKAH 3: PILIH LOT & INPUT QTY RETURN --}}
            <div x-show="step === 3" x-cloak class="space-y-3.5">
                <div class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-1.5">
                    <div>Material: <b class="text-slate-900" x-text="materialItem?.part_name"></b></div>
                    <div>Output: <b class="text-slate-900" x-text="targetItem?.part_name"></b></div>
                </div>

                <form action="{{ route('material_returns.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <input type="hidden" name="mode" value="scanner">
                    <input type="hidden" name="material_item_id" :value="materialItem?.id">
                    <input type="hidden" name="target_item_id" :value="targetItem?.id">
                    <input type="hidden" name="lot_number" :value="selectedLotNo">

                    <div>
                        <label class="lbl">Cari &amp; Pilih Nomor Lot Aktif</label>
                        <input type="text" x-model="lotSearch" placeholder="Ketik untuk memfilter Nomor Lot..."
                               class="num w-full h-9 text-xs mb-1.5">
                        <div class="max-h-36 overflow-y-auto border border-slate-200 rounded divide-y divide-slate-100 bg-white">
                            <template x-for="lt in filteredLots" :key="lt.lot_number + '-' + lt.target_item_id">
                                <button type="button"
                                        @click="selectedLotNo = lt.lot_number"
                                        class="w-full px-3 py-2 text-left text-xs flex items-center justify-between hover:bg-slate-50"
                                        :class="selectedLotNo === lt.lot_number ? 'bg-blue-50/80 font-bold text-accent' : ''">
                                    <span class="num font-bold" x-text="lt.lot_number"></span>
                                    <span class="num text-slate-600"
                                          x-text="'Material Saat Ini: ' + lt.mat_current + ' ' + (materialItem?.uom || '')"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div x-show="selectedLotObj" class="p-2.5 rounded bg-blue-50/70 border border-blue-200 text-xs flex items-center justify-between">
                        <span>Batas Maksimal Return (Material Saat Ini):</span>
                        <b class="num text-blue-900" x-text="(selectedLotObj?.mat_current ?? 0) + ' ' + (materialItem?.uom || '')"></b>
                    </div>

                    <div>
                        <label class="lbl">Qty Material Return (+ Stok Gudang)</label>
                        <input type="text" name="qty" x-model="qtyInput" required placeholder="0"
                               class="num w-full h-14 text-2xl text-center font-bold">
                        <div x-show="selectedLotObj && evalQty() > selectedLotObj.mat_current"
                             class="text-xs text-rose-600 font-semibold mt-1 text-center">
                            Ditolak: Qty Return melebihi sisa Material Saat Ini di mesin!
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="!canSubmit">
                        Simpan Material Return (+ Stok)
                    </button>
                </form>

                <button type="button" class="btn w-full" @click="resetAll()">Batal &amp; Scan Ulang</button>
            </div>
        </div>

        {{-- RIWAYAT SCAN HARI INI --}}
        <div class="panel mt-3.5">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Scan Hari Ini</span>
                    <span class="count-chip">{{ $todayScans->count() }}</span>
                </div>
                <a href="{{ route('material_returns.index') }}" class="text-xs font-semibold text-accent hover:underline">Mode Desktop</a>
            </div>
            <div class="max-h-72 overflow-y-auto divide-y divide-slate-200">
                @forelse($todayScans as $sc)
                    <div class="px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="num font-bold text-accent">{{ $sc->lot_number }}</span>
                                <span class="text-slate-400">·</span>
                                <span class="font-semibold text-slate-900 truncate">{{ $sc->materialItem->part_name ?? '-' }}</span>
                            </div>
                            <div class="text-[11px] text-slate-500 truncate">
                                {{ \Carbon\Carbon::parse($sc->date_time)->format('H:i') }} · {{ $sc->user->name ?? 'Operator' }}
                            </div>
                        </div>
                        <div class="num font-bold text-emerald-700 whitespace-nowrap">
                            +{{ floatval($sc->qty) }} {{ $sc->materialItem->uom ?? '' }}
                        </div>
                    </div>
                @empty
                    <div class="empty py-6">Belum ada scan Material Return hari ini.</div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        function materialReturnScanner(itemsList, activeLotsList) {
            return {
                items: itemsList,
                activeLots: activeLotsList,
                step: 1,
                cameraRunning: false,
                manualCode: '',
                materialItem: null,
                targetItem: null,
                lotSearch: '',
                selectedLotNo: '',
                qtyInput: '',

                initScanner() {
                    this.startCam();
                },
                startCam() {
                    window.startDrcCamera('qr-reader', (code) => {
                        this.handleScan(code);
                    }, (running) => {
                        this.cameraRunning = running;
                    });
                },
                handleScan(raw) {
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

                    if (this.step === 1) {
                        const hasLot = this.activeLots.some(l => Number(l.material_item_id) === Number(found.id));
                        if (!hasLot) {
                            window.showScannerToast('Ditolak: Tidak ada Nomor Lot aktif yang memiliki sisa Material Saat Ini untuk item ini.', 'crit');
                            return;
                        }
                        this.materialItem = found;
                        this.manualCode = '';
                        this.step = 2;
                        window.showScannerToast('Material terverifikasi: ' + found.part_name, 'good');
                    } else if (this.step === 2) {
                        const matchLots = this.activeLots.filter(l =>
                            Number(l.material_item_id) === Number(this.materialItem.id) &&
                            Number(l.target_item_id) === Number(found.id)
                        );
                        if (matchLots.length === 0) {
                            window.showScannerToast('Ditolak: Tidak ditemukan Lot aktif untuk pasangan Material & Output ini.', 'crit');
                            return;
                        }
                        this.targetItem = found;
                        this.selectedLotNo = matchLots[0].lot_number;
                        this.manualCode = '';
                        this.step = 3;
                        window.stopDrcCamera();
                        this.cameraRunning = false;
                        window.showScannerToast('Output terverifikasi: ' + found.part_name, 'good');
                    }
                },
                get filteredLots() {
                    if (!this.materialItem || !this.targetItem) return [];
                    return this.activeLots.filter(l => {
                        const pairOk = Number(l.material_item_id) === Number(this.materialItem.id) &&
                                       Number(l.target_item_id) === Number(this.targetItem.id);
                        if (!pairOk) return false;
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
                    return q <= this.selectedLotObj.mat_current;
                },
                resetAll() {
                    this.materialItem = null;
                    this.targetItem = null;
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