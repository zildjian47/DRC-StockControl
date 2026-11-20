<x-scanner-layout :backUrl="route('productions.index')">
    <x-slot name="header">Scanner Production</x-slot>

    @php
        $allScansData = ($allScans ?? collect())->map(function ($s) {
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
                'qty'        => (float) $s->qty,
                'user_name'  => $s->user->name ?? 'Operator',
            ];
        })->values();

        $itemsPayload = $items->map(fn ($i) => [
            'id'           => $i->id,
            'part_no'      => $i->part_no ? strtoupper(trim($i->part_no)) : '',
            'part_name'    => strtoupper(trim($i->part_name)),
            'type'         => $i->type,
            'uom'          => $i->uom,
            'company_type' => $i->company->type_company ?? 'INTERNAL',
            'stock'        => $i->inventoryStock ? (float) $i->inventoryStock->current_stock : 0,
        ])->values();

        $activeLotsPayload = $activeLots->map(fn ($lt) => [
            'id'             => $lt->id,
            'lot_number'     => $lt->lot_number,
            'target_item_id' => $lt->target_item_id,
            'process_name'   => $lt->process->process_name ?? '-',
            'target_est'     => (float) $lt->target_qty_estimasi,
            'actual_prod'    => (float) $lt->actual_qty_prod,
            'qty_wip'        => (float) $lt->qty_wip,
            'uom'            => $lt->targetItem->uom ?? 'PCS',
        ])->values();
    @endphp

    <div x-data="prodScanner({{ Js::from($itemsPayload) }}, {{ Js::from($activeLotsPayload) }}, {{ Js::from($allScansData) }}, '{{ now()->format('Y-m-d') }}')"
         x-init="initScanner()">

        <div class="scan-stepper mb-3">
            <div class="scan-step" :class="step === 1 ? 'active' : 'done'">
                <span class="scan-step-num" x-text="step > 1 ? '✓' : '1'"></span>
                <span class="truncate">Scan Output</span>
            </div>
            <div class="scan-step" :class="step === 2 ? 'active' : ''">
                <span class="scan-step-num">2</span>
                <span class="truncate">Lot &amp; Qty</span>
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
                    <span x-show="step === 1">Langkah 1 · Scan Output</span>
                    <span x-show="step === 2" x-cloak>Langkah 2 · Lot &amp; Qty</span>
                </div>
                <span class="pill pill-info">FG · FG/KOMPONEN · KOMPONEN</span>
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
                                Aktifkan kamera untuk scan QR, atau ketik kode di bawah.
                            </div>
                            <button type="button" @click="activateCamera()" class="btn btn-primary">
                                <x-icon name="scan" /> Aktifkan Kamera
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="lbl">Part No / Part Name</label>
                        <div class="flex gap-2">
                            <input type="text" x-model="manualCode"
                                   @keydown.enter.prevent="handleScan(manualCode)"
                                   placeholder="Contoh: FG-BKT-001 atau FG-GUS-R"
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
                            <span class="ty" :class="tyClass(scannedItem?.type)" x-text="scannedItem?.type"></span>
                            <div class="num text-xs font-bold text-accent mt-1" x-text="scannedItem?.part_no || '-'"></div>
                            <div class="text-xs font-semibold text-slate-900 break-words" x-text="scannedItem?.part_name"></div>
                        </div>
                        <div class="text-right whitespace-nowrap flex-none">
                            <div class="text-[11px] text-slate-500">Stok Gudang</div>
                            <div class="num text-sm font-bold text-slate-900" x-text="(scannedItem?.stock ?? 0) + ' ' + (scannedItem?.uom ?? '')"></div>
                        </div>
                    </div>

                    <form action="{{ route('productions.store') }}" method="POST" novalidate
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
                            <label class="lbl">Nomor Lot Aktif</label>
                            <select name="lot_number" x-model="lotNumber" required class="w-full h-11 text-xs font-semibold">
                                <option value="">-- Pilih Nomor Lot --</option>
                                <template x-for="lt in itemLots" :key="lt.id">
                                    <option :value="lt.lot_number"
                                            x-text="lt.lot_number + ' (' + lt.process_name + ') · Sisa WIP: ' + lt.qty_wip + ' ' + lt.uom"></option>
                                </template>
                            </select>
                        </div>

                        <div x-show="selectedLotObj" class="p-3 rounded bg-blue-50/70 border border-blue-200 grid grid-cols-3 gap-2 text-center text-xs">
                            <div>
                                <div class="text-[11px] text-slate-500">Target Est</div>
                                <div class="num font-bold text-slate-900" x-text="selectedLotObj?.target_est"></div>
                            </div>
                            <div>
                                <div class="text-[11px] text-slate-500">Actual Prod</div>
                                <div class="num font-bold text-blue-700" x-text="selectedLotObj?.actual_prod"></div>
                            </div>
                            <div>
                                <div class="text-[11px] text-slate-500">Sisa WIP</div>
                                <div class="num font-bold text-amber-700" x-text="(selectedLotObj?.qty_wip ?? 0) + ' ' + (scannedItem?.uom || '')"></div>
                            </div>
                        </div>

                        <div>
                            <label class="lbl">Qty Production (<span x-text="scannedItem?.uom"></span>)</label>
                            <div class="inp-calc">
                                <input type="text" name="qty" x-model="rawQty" required
                                       placeholder="Contoh: 20"
                                       class="num w-full h-14 text-2xl text-center font-bold pl-[30px]">
                                <span class="inp-calc-ic" title="Mendukung kalkulator (+, -, *, /)">
                                    <x-icon name="calc" />
                                </span>
                            </div>
                            <div class="calc-preview text-center" x-show="String(rawQty || '').trim() !== '' && evalCalc(rawQty) !== null" x-cloak>
                                Hasil: <b x-text="evalCalc(rawQty)"></b> <span x-text="scannedItem?.uom || ''"></span>
                            </div>
                            <div class="calc-err justify-center w-full" x-show="String(rawQty || '').trim() !== '' && evalCalc(rawQty) === null" x-cloak>
                                <x-icon name="alert" />
                                <span>Format perhitungan tidak valid</span>
                            </div>
                        </div>

                        <div class="dlg-list" x-show="selectedLotObj && parsedQty > 0" x-cloak>
                            <div>
                                <span>Sisa WIP</span>
                                <b class="num"
                                   :class="parsedQty > (selectedLotObj?.qty_wip || 0) ? 'text-rose-700' : 'text-amber-700'"
                                   x-text="(selectedLotObj?.qty_wip ?? 0) + ' → ' + Math.round(((selectedLotObj?.qty_wip || 0) - parsedQty) * 100) / 100 + ' ' + (scannedItem?.uom || '')"></b>
                            </div>
                            <div>
                                <span>Stok</span>
                                <b class="num text-emerald-700"
                                   x-text="(scannedItem?.stock ?? 0) + ' → ' + Math.round(((scannedItem?.stock || 0) + parsedQty) * 100) / 100 + ' ' + (scannedItem?.uom || '')"></b>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-full">
                            Simpan Production
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
                    <span>Riwayat Scan Production</span>
                    <span class="count-chip"><span x-text="filteredHistory.length"></span><span>SCAN</span></span>
                </div>
            </div>
            <div class="p-3 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center gap-2">
                <input type="date" x-model="histDate" class="h-8 text-xs px-2 w-36">
                <input type="text" x-model="histSearch" placeholder="Cari Lot / Part / Qty..." class="h-8 text-xs px-2.5 flex-1 min-w-[140px]">
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
                                <span class="pill pill-info num" x-text="s.lot_number"></span>
                                <span class="num text-[11px] text-slate-500" x-text="s.date_fmt"></span>
                                <span class="text-[11px] text-slate-500" x-text="'· ' + s.user_name"></span>
                            </div>
                            <div class="num font-bold text-accent mt-1" x-text="s.part_no"></div>
                            <div class="font-semibold text-slate-900 break-words" x-text="s.part_name"></div>
                        </div>
                        <div class="text-right whitespace-nowrap flex-none">
                            <span class="num text-sm font-bold text-emerald-700" x-text="'+' + s.qty + ' ' + s.uom"></span>
                        </div>
                    </div>
                </template>
                <div x-show="filteredHistory.length === 0" class="empty">
                    Tidak ada riwayat scan pada tanggal / pencarian ini.
                </div>
            </div>
        </div>
    </div>

    <script>
        function prodScanner(items, activeLots, allScans, todayStr) {
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
                lotNumber: '',
                rawQty: '',

                tyClass(t) {
                    return 'ty-' + (t === 'FG/KOMPONEN' ? 'FGK' : (t || 'FG'));
                },
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
                        this.errorMsg = `Kode "${rawCode}" tidak ditemukan.`;
                        return;
                    }
                    if (!['FG', 'FG/KOMPONEN', 'KOMPONEN'].includes(found.type) || !['INTERNAL', 'CUSTOMER'].includes(found.company_type)) {
                        this.errorMsg = `${found.part_name} tidak sesuai untuk Production.`;
                        return;
                    }
                    const lots = this.activeLots.filter(l => String(l.target_item_id) === String(found.id));
                    if (lots.length === 0) {
                        this.errorMsg = `Tidak ada Lot aktif dengan sisa WIP untuk ${found.part_name}.`;
                        return;
                    }
                    this.scannedItem = found;
                    this.lotNumber = lots.length === 1 ? lots[0].lot_number : '';
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
                    return this.itemLots.find(l => l.lot_number === this.lotNumber) || null;
                },
                evalCalc(raw) {
                    if (raw === null || raw === undefined) return null;
                    const expr = String(raw).replace(/,/g, '.').replace(/[xX]/g, '*').replace(/:/g, '/').replace(/\s+/g, '');
                    if (!expr || !/^[0-9\.\+\-\*\/\(\)]+$/.test(expr) || /[\+\-\*\/]{2,}/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r)) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                get parsedQty() {
                    const r = this.evalCalc(this.rawQty);
                    return (r !== null && r > 0) ? r : 0;
                },
                submitForm(formEl) {
                    this.formError = '';
                    if (!this.scannedItem) {
                        this.formError = 'Scan output terlebih dahulu.';
                        return;
                    }
                    if (!this.lotNumber || !this.selectedLotObj) {
                        this.formError = 'Pilih Nomor Lot terlebih dahulu.';
                        return;
                    }
                    if (String(this.rawQty || '').trim() === '') {
                        this.formError = 'Qty wajib diisi.';
                        return;
                    }
                    const val = this.evalCalc(this.rawQty);
                    if (val === null) {
                        this.formError = 'Format perhitungan tidak valid.';
                        return;
                    }
                    if (val <= 0) {
                        this.formError = 'Qty harus lebih dari 0.';
                        return;
                    }
                    if (val > this.selectedLotObj.qty_wip + 0.0001) {
                        this.formError = `Qty melebihi sisa WIP (tersedia ${this.selectedLotObj.qty_wip} ${this.scannedItem?.uom || ''}).`;
                        return;
                    }
                    formEl.submit();
                },
                get filteredHistory() {
                    return this.allScans.filter(s => {
                        if (this.histDate && s.date !== this.histDate) return false;
                        if (this.histSearch) {
                            const q = this.histSearch.toLowerCase();
                            const hay = `${s.lot_number} ${s.part_no} ${s.part_name} ${s.qty} ${s.user_name}`.toLowerCase();
                            if (!hay.includes(q)) return false;
                        }
                        return true;
                    });
                },
                resetAll() {
                    this.scannedItem = null;
                    this.lotNumber = '';
                    this.rawQty = '';
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