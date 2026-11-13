<x-scanner-layout :backUrl="route('material_returns.index')">
    <x-slot name="header">Scanner Material Return</x-slot>

    @php
        $rawScans = $allScans ?? \App\Models\MaterialReturn::with(['materialItem', 'targetItem', 'user'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $allScansList = $rawScans->map(function ($s) {
            return [
                'id'          => $s->id,
                'date'        => \Carbon\Carbon::parse($s->date_time)->format('Y-m-d'),
                'time_fmt'    => \Carbon\Carbon::parse($s->date_time)->format('d M Y, H:i'),
                'lot_number'  => $s->lot_number,
                'mat_no'      => $s->materialItem->part_no ?: '-',
                'mat_name'    => $s->materialItem->part_name ?? '-',
                'mat_uom'     => $s->materialItem->uom ?? '',
                'target_no'   => $s->targetItem->part_no ?: '-',
                'target_name' => $s->targetItem->part_name ?? '-',
                'qty'         => (float) $s->qty,
                'user'        => $s->user->name ?? 'Operator',
                'search'      => strtolower(($s->lot_number ?? '') . ' ' . ($s->materialItem->part_no ?? '') . ' ' . ($s->materialItem->part_name ?? '') . ' ' . ($s->targetItem->part_no ?? '') . ' ' . ($s->targetItem->part_name ?? '') . ' ' . floatval($s->qty)),
            ];
        })->values();

        $itemsJs = ($items ?? collect())->map(fn ($i) => [
            'id'           => $i->id,
            'part_no'      => $i->part_no ?: '',
            'part_name'    => $i->part_name,
            'type'         => $i->type,
            'uom'          => $i->uom,
            'company_type' => $i->company->type_company ?? 'INTERNAL',
            'stock'        => (float) ($i->inventoryStock->current_stock ?? 0),
        ])->values();

        $lotsJs = ($activeLots ?? collect())->map(fn ($lt) => [
            'id'               => $lt->id,
            'lot_number'       => $lt->lot_number,
            'material_item_id' => (int) $lt->material_item_id,
            'target_item_id'   => (int) $lt->target_item_id,
            'qty_out'          => (float) $lt->qty_material_out,
            'mat_saat_ini'     => (float) $lt->material_saat_ini,
        ])->values();
    @endphp

    <div x-data="materialReturnScanner({{ Js::from($itemsJs) }}, {{ Js::from($lotsJs) }}, {{ Js::from($allScansList) }}, '{{ now()->format('Y-m-d') }}')"
         x-init="initScanner()"
         class="space-y-3.5">

        {{-- VISUAL STEPPER 3 LANGKAH --}}
        <div class="scan-stepper">
            <div class="scan-step" :class="step === 1 ? 'active' : (step > 1 ? 'done' : '')">
                <span class="scan-step-num" x-text="step > 1 ? '✓' : '1'"></span>
                <span class="truncate">Scan Material</span>
            </div>
            <div class="scan-step" :class="step === 2 ? 'active' : (step > 2 ? 'done' : '')">
                <span class="scan-step-num" x-text="step > 2 ? '✓' : '2'"></span>
                <span class="truncate">Scan Output</span>
            </div>
            <div class="scan-step" :class="step === 3 ? 'active' : ''">
                <span class="scan-step-num">3</span>
                <span class="truncate">Lot &amp; Qty</span>
            </div>
        </div>

        <div x-show="errMsg" x-cloak class="alert alert-crit">
            <div class="flex-1" x-text="errMsg"></div>
            <button type="button" @click="errMsg = ''" class="font-bold">✕</button>
        </div>

        <div class="panel panel-body space-y-3.5">
            <div class="flex items-center justify-between">
                <h3 class="panel-title"
                    x-text="step === 1 ? 'Scan Material yang Dikembalikan' : (step === 2 ? 'Scan Target Output' : 'Pilih Nomor Lot & Qty Return')"></h3>
                <span class="pill pill-info" x-text="'Langkah ' + step + '/3'"></span>
            </div>

            {{-- KAMERA PERSEGI (STEP 1 & STEP 2) --}}
            <div x-show="step === 1 || step === 2" class="space-y-3">
                <div x-show="step === 2 && materialItem" class="p-2.5 rounded bg-slate-50 border border-slate-200 text-xs flex items-center justify-between">
                    <div>
                        <span class="text-slate-500 block text-[10.5px]">Material Dikembalikan:</span>
                        <b class="text-slate-900" x-text="materialItem?.part_name"></b>
                    </div>
                    <span class="pill pill-neu" x-text="materialItem?.uom"></span>
                </div>

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
                    <label class="lbl" x-text="step === 1 ? 'Input Manual Material (Part No / Part Name)' : 'Input Manual Target Output (Part No / Part Name)'"></label>
                    <div class="flex gap-2">
                        <input type="text" x-model="manualCode"
                               @keydown.enter.prevent="handleScan(manualCode)"
                               oninput="this.value = this.value.toUpperCase()"
                               placeholder="Ketik Part No atau Part Name..."
                               class="num uppercase flex-1 h-10 text-xs">
                        <button type="button" @click="handleScan(manualCode)" class="btn btn-primary h-10 px-4">
                            Verifikasi
                        </button>
                    </div>
                </div>

                <button type="button" x-show="step === 2" @click="resetAll()" class="btn w-full">
                    Ganti Material
                </button>
            </div>

            {{-- STEP 3: PILIH LOT AKTIF & QTY RETURN --}}
            <div x-show="step === 3" x-cloak class="space-y-3.5">
                <div class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-1.5">
                    <div>Material: <b class="text-slate-900" x-text="materialItem?.part_name"></b></div>
                    <div>Target Output: <b class="text-slate-900" x-text="targetItem?.part_name"></b></div>
                </div>

                <form action="{{ route('material_returns.store') }}" method="POST" class="space-y-3.5">
                    @csrf
                    <input type="hidden" name="mode" value="scanner">
                    <input type="hidden" name="material_item_id" :value="materialItem?.id">
                    <input type="hidden" name="target_item_id" :value="targetItem?.id">

                    <div>
                        <label class="lbl">Cari &amp; Pilih Nomor Lot Aktif</label>
                        <input type="text" x-model="lotSearch"
                               placeholder="Ketik Nomor Lot untuk menyaring..."
                               class="num uppercase w-full h-8 text-xs mb-1.5">
                        <select name="lot_number" x-model="selectedLotNo" required class="w-full h-10 text-xs num font-semibold">
                            <option value="">-- Pilih Nomor Lot --</option>
                            <template x-for="lt in availableLots" :key="lt.lot_number">
                                <option :value="lt.lot_number"
                                        x-text="lt.lot_number + ' (Material Saat Ini: ' + lt.mat_saat_ini + ' ' + (materialItem?.uom ?? '') + ')'"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="selectedLotObj" class="p-2.5 rounded bg-blue-50/70 border border-blue-200 text-xs flex items-center justify-between">
                        <span>Batas Maksimal Return (Material Saat Ini):</span>
                        <b class="num text-accent" x-text="(selectedLotObj?.mat_saat_ini ?? 0) + ' ' + (materialItem?.uom ?? '')"></b>
                    </div>

                    <div>
                        <label class="lbl">Qty Material Return (<span x-text="materialItem?.uom"></span>)</label>
                        <input type="text" name="qty" x-model="qtyExpr" required
                               placeholder="0"
                               class="num w-full h-14 text-2xl text-center font-bold">
                        <p x-show="selectedLotObj && evalQty() > selectedLotObj.mat_saat_ini" class="text-xs text-rose-600 font-semibold mt-1">
                            Ditolak: Qty Return melebihi sisa Material Saat Ini di mesin (<span x-text="selectedLotObj?.mat_saat_ini"></span>).
                        </p>
                    </div>

                    <button type="submit"
                            :disabled="!selectedLotObj || !evalQty() || evalQty() > selectedLotObj.mat_saat_ini"
                            class="btn btn-primary btn-lg w-full">
                        Simpan Material Return (+ Stok)
                    </button>
                </form>

                <button type="button" @click="resetAll()" class="btn w-full">
                    Batal &amp; Scan Ulang
                </button>
            </div>
        </div>

        {{-- RIWAYAT SCAN DENGAN FILTER TANGGAL & SEARCH --}}
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Scan Material Return</span>
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
                       placeholder="Cari Lot / Part / Qty..."
                       class="h-8 text-xs px-2.5 flex-1 min-w-0">
            </div>

            <div class="divide-y divide-slate-200 max-h-80 overflow-y-auto">
                <template x-for="row in filteredHistory" :key="row.id">
                    <div class="px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="pill pill-info num" x-text="row.lot_number"></span>
                                <span class="text-[11px] text-slate-400 num" x-text="row.time_fmt"></span>
                            </div>
                            <div class="font-semibold text-slate-900 truncate mt-0.5" x-text="row.mat_name"></div>
                            <div class="text-[11px] text-slate-500" x-text="'Petugas: ' + row.user"></div>
                        </div>
                        <div class="num font-bold text-emerald-700 whitespace-nowrap"
                             x-text="'+' + row.qty + ' ' + row.mat_uom"></div>
                    </div>
                </template>
                <div x-show="filteredHistory.length === 0" class="empty">
                    Tidak ada riwayat scan pada tanggal/filter ini.
                </div>
            </div>
        </div>
    </div>

    <script>
        function materialReturnScanner(itemsList, lotsList, scansList, todayStr) {
            return {
                items: itemsList,
                lots: lotsList,
                scans: scansList,
                todayStr: todayStr,
                step: 1,
                camPermitted: window.DRCScanner.isPermitted(),
                manualCode: '',
                materialItem: null,
                targetItem: null,
                lotSearch: '',
                selectedLotNo: '',
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
                get availableLots() {
                    if (!this.materialItem || !this.targetItem) return [];
                    return this.lots.filter(l => {
                        const matchPair = l.material_item_id === this.materialItem.id && l.target_item_id === this.targetItem.id;
                        if (!matchPair) return false;
                        if (this.lotSearch && !l.lot_number.toLowerCase().includes(this.lotSearch.toLowerCase())) return false;
                        return true;
                    });
                },
                get selectedLotObj() {
                    return this.availableLots.find(l => l.lot_number === this.selectedLotNo) || null;
                },
                initScanner() {
                    if (this.camPermitted) {
                        this.$nextTick(() => this.startCamera());
                    }
                },
                async requestCamera() {
                    this.errMsg = '';
                    const ok = await window.DRCScanner.start('qr-reader', (txt) => this.handleScan(txt), (msg) => { this.errMsg = msg; });
                    if (ok) this.camPermitted = true;
                },
                async startCamera() {
                    await window.DRCScanner.start('qr-reader', (txt) => this.handleScan(txt), (msg) => { this.errMsg = msg; });
                },
                handleScan(raw) {
                    this.errMsg = '';
                    const code = String(raw || '').trim().toUpperCase();
                    if (!code) return;

                    const found = this.items.find(i => i.part_no.toUpperCase() === code || i.part_name.toUpperCase() === code);
                    if (!found) {
                        this.errMsg = `Ditolak: Item "${code}" tidak ditemukan pada Master Item aktif.`;
                        return;
                    }

                    if (this.step === 1) {
                        const hasActiveLot = this.lots.some(l => l.material_item_id === found.id);
                        if (!hasActiveLot) {
                            this.errMsg = `Ditolak: Tidak ada Nomor Lot aktif (ON PROGRESS) yang memiliki sisa Material Saat Ini untuk ${found.part_name}.`;
                            return;
                        }
                        this.materialItem = found;
                        this.manualCode = '';
                        this.step = 2;
                        return;
                    }

                    if (this.step === 2) {
                        const pairLots = this.lots.filter(l => l.material_item_id === this.materialItem.id && l.target_item_id === found.id);
                        if (pairLots.length === 0) {
                            this.errMsg = `Ditolak: Tidak ditemukan Lot aktif dengan pasangan ${this.materialItem.part_name} → ${found.part_name} yang belum diretur.`;
                            return;
                        }
                        window.DRCScanner.stop();
                        this.targetItem = found;
                        this.selectedLotNo = pairLots.length === 1 ? pairLots[0].lot_number : '';
                        this.manualCode = '';
                        this.step = 3;
                    }
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
                resetAll() {
                    this.materialItem = null;
                    this.targetItem = null;
                    this.lotSearch = '';
                    this.selectedLotNo = '';
                    this.qtyExpr = '';
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