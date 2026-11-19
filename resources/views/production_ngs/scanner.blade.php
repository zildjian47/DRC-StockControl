<x-scanner-layout :backUrl="route('production_ngs.index')">
    <x-slot name="header">Scanner Form Production NG</x-slot>

    @php
        $allScansData = \App\Models\ProductionNg::with(['item.company', 'user'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->limit(300)
            ->get()
            ->map(function ($s) {
                $dt = \Carbon\Carbon::parse($s->date_time);
                return [
                    'id'         => $s->id,
                    'date'       => $dt->format('Y-m-d'),
                    'time'       => $dt->format('H:i'),
                    'date_fmt'   => $dt->format('d M Y, H:i'),
                    'lot_number' => $s->lot_number,
                    'part_no'    => $s->item->part_no ?: '-',
                    'part_name'  => $s->item->part_name ?? '-',
                    'uom'        => $s->item->uom ?? 'PCS',
                    'reason'     => $s->problem_reason,
                    'qty_ng'     => (float) $s->qty_ng,
                    'user_name'  => $s->user->name ?? 'QC',
                ];
            })->values();

        $itemsPayload = \App\Models\Item::with('inventoryStock')
            ->whereIn('type', ['FG', 'FG/KOMPONEN', 'KOMPONEN'])
            ->get()
            ->map(fn ($i) => [
                'id'        => $i->id,
                'part_no'   => $i->part_no ? strtoupper(trim($i->part_no)) : '',
                'part_name' => strtoupper(trim($i->part_name)),
                'type'      => $i->type,
                'uom'       => $i->uom,
                'stock'     => $i->inventoryStock ? (float) $i->inventoryStock->current_stock : 0,
            ])->values();

        $activeLotsPayload = \App\Models\LotTracer::with(['targetItem', 'process'])
            ->where('status_lot', 'ON PROGRESS')
            ->get()
            ->filter(fn ($lt) => $lt->act_fg > 0)
            ->map(fn ($lt) => [
                'id'             => $lt->id,
                'lot_number'     => $lt->lot_number,
                'target_item_id' => $lt->target_item_id,
                'process_name'   => $lt->process->process_name ?? '-',
                'actual_prod'    => (float) $lt->actual_qty_prod,
                'qty_ng'         => (float) $lt->qty_ng,
                'act_fg'         => (float) $lt->act_fg,
                'uom'            => $lt->targetItem->uom ?? 'PCS',
            ])->values();
    @endphp

    <div x-data="ngScanner({{ Js::from($itemsPayload) }}, {{ Js::from($activeLotsPayload) }}, {{ Js::from($allScansData) }}, '{{ now()->format('Y-m-d') }}', {{ Js::from($reasons[0] ?? 'MATERIAL') }})"
         x-init="initScanner()">

        <div class="scan-stepper mb-3">
            <div class="scan-step" :class="step === 1 ? 'active' : 'done'">
                <span class="scan-step-num" x-text="step > 1 ? '✓' : '1'"></span>
                <span class="truncate">Scan Barang Output</span>
            </div>
            <div class="scan-step" :class="step === 2 ? 'active' : ''">
                <span class="scan-step-num">2</span>
                <span class="truncate">Lot, Reason &amp; Qty NG</span>
            </div>
        </div>

        <div x-show="errorMsg" x-cloak class="alert alert-crit mb-3" role="alert">
            <x-icon name="alert" />
            <span class="flex-1 min-w-0" x-text="errorMsg"></span>
            <button type="button" @click="errorMsg = ''" class="act-btn flex-none -my-1 -mr-1"><x-icon name="x" /></button>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span x-show="step === 1">Langkah 1 · Scan QR Barang Temuan NG</span>
                    <span x-show="step === 2" x-cloak>Langkah 2 · Pilih Lot, Alasan &amp; Qty NG</span>
                </div>
                <span class="pill pill-crit">Reject QC (- Stok)</span>
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
                                   placeholder="Contoh: FGK-PLT-001 atau FG-BKT-001"
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
                            <div class="text-[11px] text-slate-500">Stok Gudang</div>
                            <div class="num text-sm font-bold text-slate-900" x-text="(scannedItem?.stock ?? 0) + ' ' + (scannedItem?.uom ?? '')"></div>
                        </div>
                    </div>

                    <form action="{{ route('production_ngs.store') }}" method="POST" novalidate
                          @submit.prevent="submitForm($el)"
                          class="space-y-4">
                        @csrf
                        <input type="hidden" name="mode" value="scanner">
                        <input type="hidden" name="item_id" :value="scannedItem?.id">

                        <div x-show="formError" x-cloak class="alert alert-crit text-xs" role="alert">
                            <x-icon name="alert" />
                            <span class="flex-1 min-w-0" x-text="formError"></span>
                        </div>

                        <div>
                            <label class="lbl">Pilih Nomor Lot Aktif (ON PROGRESS)</label>
                            <select name="lot_tracer_id" x-model="selectedLotId" required class="w-full h-11 text-xs font-semibold">
                                <option value="">-- Pilih Nomor Lot --</option>
                                <template x-for="lt in itemLots" :key="lt.id">
                                    <option :value="lt.id"
                                            x-text="lt.lot_number + ' (' + lt.process_name + ') — Saldo Act FG: ' + lt.act_fg + ' ' + lt.uom"></option>
                                </template>
                            </select>
                        </div>

                        <div x-show="selectedLotObj" class="p-3 rounded bg-rose-50/70 border border-rose-200 grid grid-cols-3 gap-2 text-center text-xs">
                            <div>
                                <div class="text-[11px] text-slate-500">Actual Prod</div>
                                <div class="num font-bold text-slate-900" x-text="selectedLotObj?.actual_prod"></div>
                            </div>
                            <div>
                                <div class="text-[11px] text-slate-500">NG Tercatat</div>
                                <div class="num font-bold text-rose-700" x-text="selectedLotObj?.qty_ng"></div>
                            </div>
                            <div>
                                <div class="text-[11px] text-slate-500">Maks NG (Act FG)</div>
                                <div class="num font-bold text-emerald-700" x-text="(selectedLotObj?.act_fg ?? 0) + ' ' + (scannedItem?.uom || '')"></div>
                            </div>
                        </div>

                        <div>
                            <label class="lbl">Pilih Problem Reason (Alasan NG)</label>
                            <select name="problem_reason" x-model="problemReason" required class="w-full h-11 text-xs font-semibold">
                                @foreach($reasons as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="lbl">Qty NG (<span x-text="scannedItem?.uom"></span>)</label>
                            <input type="number" step="0.01" min="0.01"
                                   :max="selectedLotObj ? selectedLotObj.act_fg : null"
                                   name="qty_ng" x-model="qtyNg" required
                                   placeholder="Contoh: 2"
                                   class="num w-full h-14 text-2xl text-center font-bold">
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-full">
                            Simpan Data NG (- Stok)
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
                    <span>Riwayat Pencatatan NG</span>
                    <span class="count-chip"><span x-text="filteredHistory.length"></span><span>Data</span></span>
                </div>
            </div>
            <div class="p-3 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center gap-2">
                <input type="date" x-model="histDate" class="h-8 text-xs px-2 w-36">
                <input type="text" x-model="histSearch" placeholder="Cari Lot / Part / Reason..." class="h-8 text-xs px-2.5 flex-1 min-w-[140px]">
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
                                <span class="pill pill-info num text-[10.5px]" x-text="s.lot_number"></span>
                                <span class="pill pill-crit text-[10px]" x-text="s.reason"></span>
                                <span class="num text-[11px] text-slate-500" x-text="s.date_fmt"></span>
                            </div>
                            <div class="num font-bold text-accent mt-1" x-text="s.part_no"></div>
                            <div class="font-semibold text-slate-900 break-words" x-text="s.part_name"></div>
                        </div>
                        <div class="text-right whitespace-nowrap flex-none">
                            <span class="num text-sm font-bold text-rose-700" x-text="'-' + s.qty_ng + ' ' + s.uom"></span>
                        </div>
                    </div>
                </template>
                <div x-show="filteredHistory.length === 0" class="empty">
                    Tidak ada riwayat NG pada tanggal / pencarian ini.
                </div>
            </div>
        </div>
    </div>

    <script>
        function ngScanner(items, activeLots, allScans, todayStr, defaultReason) {
            return {
                step: 1,
                cameraActive: false,
                manualCode: '',
                errorMsg: '',
                formError: '',
                items: items,
                activeLots: activeLots,
                allScans: allScans,
                todayStr: todayStr,
                histDate: todayStr,
                histSearch: '',
                scannedItem: null,
                selectedLotId: '',
                problemReason: defaultReason || 'MATERIAL',
                qtyNg: '',

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
                    this.formError = '';
                    const c = String(rawCode || '').trim().toUpperCase();
                    if (!c) return;
                    const found = this.items.find(i => i.part_no === c || i.part_name === c);
                    if (!found) {
                        this.errorMsg = `Ditolak: Kode "${rawCode}" tidak ditemukan pada item produksi aktif.`;
                        return;
                    }
                    const lots = this.activeLots.filter(l => String(l.target_item_id) === String(found.id));
                    if (lots.length === 0) {
                        this.errorMsg = `Ditolak: Item [${found.part_name}] tidak memiliki Lot ON PROGRESS dengan saldo Act FG > 0.`;
                        return;
                    }
                    this.scannedItem = found;
                    this.selectedLotId = lots.length === 1 ? String(lots[0].id) : '';
                    this.manualCode = '';
                    this.step = 2;
                    window.DRCScanner.stop();
                    this.cameraActive = false;
                },
                get itemLots() {
                    if (!this.scannedItem) return [];
                    return this.activeLots.filter(l => String(l.target_item_id) === String(this.scannedItem.id));
                },
                get selectedLotObj() {
                    return this.itemLots.find(l => String(l.id) === String(this.selectedLotId)) || null;
                },
                submitForm(formEl) {
                    this.formError = '';
                    if (!this.scannedItem) {
                        this.formError = 'Silakan scan barang temuan NG terlebih dahulu.';
                        return;
                    }
                    if (!this.selectedLotId || !this.selectedLotObj) {
                        this.formError = 'Nomor Lot aktif wajib dipilih terlebih dahulu.';
                        return;
                    }
                    if (!this.problemReason) {
                        this.formError = 'Problem Reason wajib dipilih.';
                        return;
                    }
                    const qty = parseFloat(this.qtyNg);
                    if (this.qtyNg === '' || this.qtyNg === null || isNaN(qty) || qty <= 0) {
                        this.formError = 'Qty NG wajib diisi dan lebih besar dari 0.';
                        return;
                    }
                    if (qty > this.selectedLotObj.act_fg + 0.0001) {
                        this.formError = `Ditolak: Qty NG (${qty}) melebihi saldo Act FG pada Lot ${this.selectedLotObj.lot_number} (Maksimal: ${this.selectedLotObj.act_fg} ${this.scannedItem.uom}).`;
                        return;
                    }
                    if (qty > (this.scannedItem.stock || 0) + 0.0001) {
                        this.formError = `Ditolak: Qty NG (${qty}) melebihi Current Stock ${this.scannedItem.part_name} di gudang (${this.scannedItem.stock} ${this.scannedItem.uom}).`;
                        return;
                    }
                    formEl.submit();
                },
                get filteredHistory() {
                    return this.allScans.filter(s => {
                        if (this.histDate && s.date !== this.histDate) return false;
                        if (this.histSearch) {
                            const q = this.histSearch.toLowerCase();
                            const hay = `${s.lot_number} ${s.part_no} ${s.part_name} ${s.reason} ${s.qty_ng} ${s.user_name}`.toLowerCase();
                            if (!hay.includes(q)) return false;
                        }
                        return true;
                    });
                },
                resetAll() {
                    this.scannedItem = null;
                    this.selectedLotId = '';
                    this.qtyNg = '';
                    this.manualCode = '';
                    this.errorMsg = '';
                    this.formError = '';
                    this.step = 1;
                    if (window.DRCScanner && window.DRCScanner.isPermitted()) {
                        this.activateCamera();
                    }
                }
            };
        }
    </script>
</x-scanner-layout>