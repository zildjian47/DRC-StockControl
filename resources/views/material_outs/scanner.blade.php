<x-scanner-layout :backUrl="route('material_outs.index')">
    <x-slot name="header">Scanner Material Out</x-slot>

    @php
        $rawScans = $allScans ?? \App\Models\MaterialOut::with(['materialItem', 'targetItem', 'user'])
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
            'company_code' => $i->company->company_code ?? '-',
            'company_type' => $i->company->type_company ?? 'INTERNAL',
            'stock'        => (float) ($i->inventoryStock->current_stock ?? 0),
        ])->values();

        $bomsJs = ($allBoms ?? collect())->map(fn ($b) => [
            'id'              => $b->id,
            'bom_group'       => $b->bom_group ?: ('LEGACY-' . $b->process_id . '-' . $b->output_item_id),
            'group_type'      => $b->group_type,
            'process_name'    => $b->process->process_name ?? '-',
            'input_item_id'   => (int) $b->input_item_id,
            'input_name'      => $b->inputItem->part_name ?? '-',
            'input_uom'       => $b->inputItem->uom ?? '',
            'input_stock'     => (float) ($b->inputItem->inventoryStock->current_stock ?? 0),
            'output_item_id'  => (int) $b->output_item_id,
            'output_part_no'  => $b->outputItem->part_no ?: '-',
            'output_name'     => $b->outputItem->part_name ?? '-',
            'output_uom'      => $b->outputItem->uom ?? 'PCS',
            'qty_usage'       => (float) $b->qty_usage,
            'is_trigger'      => (bool) $b->is_trigger,
        ])->values();
    @endphp

    <div x-data="materialOutScanner({{ Js::from($itemsJs) }}, {{ Js::from($bomsJs) }}, {{ Js::from($existingLots ?? []) }}, {{ Js::from($allScansList) }}, '{{ now()->format('Y-m-d') }}')"
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
                    x-text="step === 1 ? 'Scan Material Trigger' : (step === 2 ? 'Scan Target Output' : 'Input Nomor Lot & Qty Out')"></h3>
                <span class="pill pill-info" x-text="'Langkah ' + step + '/3'"></span>
            </div>

            {{-- KAMERA PERSEGI (AKTIF DI STEP 1 & STEP 2) --}}
            <div x-show="step === 1 || step === 2" class="space-y-3">
                <div x-show="step === 2 && materialItem" class="p-2.5 rounded bg-slate-50 border border-slate-200 text-xs flex items-center justify-between">
                    <div>
                        <span class="text-slate-500 block text-[10.5px]">Material Trigger Terpilih:</span>
                        <b class="text-slate-900" x-text="materialItem?.part_name"></b>
                    </div>
                    <span class="pill pill-good num" x-text="'Stok: ' + (materialItem?.stock ?? 0) + ' ' + (materialItem?.uom ?? '')"></span>
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
                    Ganti Material Trigger
                </button>
            </div>

            {{-- STEP 3: RINGKASAN RESEP BOM, PILIH GRUP (JIKA >1), INPUT LOT & QTY --}}
            <div x-show="step === 3" x-cloak class="space-y-3.5">
                <div class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-2">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-1.5">
                        <span class="font-bold text-slate-700" x-text="'Proses: ' + (activeGroup?.process_name ?? '-')"></span>
                        <span class="pill pill-info" x-text="activeGroup?.group_type ?? 'SINGLE'"></span>
                    </div>

                    <div>
                        <span class="text-slate-500 text-[11px] block">Material Trigger:</span>
                        <div class="flex items-center justify-between font-semibold text-slate-900">
                            <span x-text="materialItem?.part_name"></span>
                            <span class="num text-accent" x-text="'Stok: ' + (materialItem?.stock ?? 0) + ' ' + (materialItem?.uom ?? '')"></span>
                        </div>
                    </div>

                    <div x-show="matchingGroups.length > 1" class="pt-1">
                        <label class="lbl">Pilih Kelompok Cetakan BOM:</label>
                        <select x-model="selectedBomGroup" class="w-full h-9 text-xs">
                            <template x-for="g in matchingGroups" :key="g.bom_group">
                                <option :value="g.bom_group" x-text="g.bom_group + ' (' + g.outputs.map(o => o.output_name).join(' + ') + ')'"></option>
                            </template>
                        </select>
                    </div>

                    <div class="pt-1 border-t border-slate-200">
                        <span class="text-slate-500 text-[11px] block mb-1">Target Output &amp; Estimasi Hasil:</span>
                        <template x-for="out in (activeGroup?.outputs || [])" :key="out.output_item_id">
                            <div class="flex items-center justify-between py-0.5">
                                <span class="font-semibold text-slate-800" x-text="out.output_part_no + ' — ' + out.output_name"></span>
                                <b class="num text-emerald-700" x-text="'Est: ' + calcEst(out.qty_usage) + ' ' + out.output_uom"></b>
                            </div>
                        </template>
                    </div>

                    <template x-if="activeGroup && activeGroup.companions.length > 0">
                        <div class="pt-1 border-t border-slate-200">
                            <span class="text-slate-500 text-[11px] block mb-1">Komponen Pendamping Ikut Terpotong:</span>
                            <template x-for="c in activeGroup.companions" :key="c.input_item_id">
                                <div class="flex items-center justify-between py-0.5 text-[11px]">
                                    <span class="text-slate-700" x-text="c.input_name + ' (Stok: ' + c.input_stock + ')'"></span>
                                    <b class="num text-rose-700" x-text="'-' + c.needed + ' ' + c.input_uom"></b>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <form action="{{ route('material_outs.store') }}" method="POST" class="space-y-3.5">
                    @csrf
                    <input type="hidden" name="mode" value="scanner">
                    <input type="hidden" name="material_item_id" :value="materialItem?.id">
                    <input type="hidden" name="target_item_id" :value="targetItem?.id">
                    <input type="hidden" name="bom_group" :value="selectedBomGroup">

                    <div>
                        <label class="lbl">Nomor Lot (1 Huruf Kapital + 6 Angka)</label>
                        <input type="text" name="lot_number" x-model="lotNumber" required maxlength="7"
                               oninput="this.value = this.value.toUpperCase()"
                               placeholder="Contoh: J260178"
                               class="num uppercase w-full h-11 text-base font-bold text-center">
                        <p x-show="lotNumber && !isValidLotFormat" class="text-xs text-rose-600 font-semibold mt-1">
                            Format wajib 1 Huruf Kapital diikuti 6 Angka (contoh: J260178).
                        </p>
                        <p x-show="isDuplicateLot" class="text-xs text-rose-600 font-semibold mt-1">
                            Ditolak: Nomor Lot ini sudah pernah dipakai pada Material Out sebelumnya.
                        </p>
                    </div>

                    <div>
                        <label class="lbl">Qty Material Out (<span x-text="materialItem?.uom"></span>)</label>
                        <input type="text" name="qty" x-model="qtyExpr" required
                               placeholder="0"
                               class="num w-full h-14 text-2xl text-center font-bold">
                        <p x-show="evalQty() !== null && evalQty() > (materialItem?.stock ?? 0)" class="text-xs text-rose-600 font-semibold mt-1">
                            Ditolak: Qty Material Out melebihi stok tersedia (<span x-text="materialItem?.stock"></span>).
                        </p>
                    </div>

                    <button type="submit"
                            :disabled="!canSubmit"
                            class="btn btn-primary btn-lg w-full">
                        Simpan Material Out (- Stok)
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
                    <span>Riwayat Scan Material Out</span>
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
                            <div class="font-semibold text-slate-900 truncate mt-0.5" x-text="row.mat_name + ' → ' + row.target_name"></div>
                            <div class="text-[11px] text-slate-500" x-text="'Petugas: ' + row.user"></div>
                        </div>
                        <div class="num font-bold text-rose-700 whitespace-nowrap"
                             x-text="'-' + row.qty + ' ' + row.mat_uom"></div>
                    </div>
                </template>
                <div x-show="filteredHistory.length === 0" class="empty">
                    Tidak ada riwayat scan pada tanggal/filter ini.
                </div>
            </div>
        </div>
    </div>

    <script>
        function materialOutScanner(itemsList, bomsList, existingLots, scansList, todayStr) {
            return {
                items: itemsList,
                boms: bomsList,
                existingLots: existingLots,
                scans: scansList,
                todayStr: todayStr,
                step: 1,
                camPermitted: window.DRCScanner.isPermitted(),
                manualCode: '',
                materialItem: null,
                targetItem: null,
                matchingGroups: [],
                selectedBomGroup: '',
                lotNumber: '',
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
                get isValidLotFormat() {
                    return /^[A-Z][0-9]{6}$/.test(this.lotNumber);
                },
                get isDuplicateLot() {
                    return this.existingLots.includes(this.lotNumber.toUpperCase());
                },
                get activeGroup() {
                    const g = this.matchingGroups.find(x => x.bom_group === this.selectedBomGroup) || this.matchingGroups[0];
                    if (!g) return null;
                    const q = this.evalQty() || 0;
                    const comps = {};
                    g.outputs.forEach(out => {
                        const est = out.qty_usage > 0 ? Math.round((q / out.qty_usage) * 100) / 100 : 0;
                        out.raw_companions.forEach(c => {
                            if (!comps[c.input_item_id]) {
                                comps[c.input_item_id] = {
                                    input_item_id: c.input_item_id,
                                    input_name: c.input_name,
                                    input_uom: c.input_uom,
                                    input_stock: c.input_stock,
                                    needed: 0
                                };
                            }
                            comps[c.input_item_id].needed = Math.round((comps[c.input_item_id].needed + (est * c.qty_usage)) * 100) / 100;
                        });
                    });
                    return {
                        bom_group: g.bom_group,
                        group_type: g.group_type,
                        process_name: g.process_name,
                        outputs: g.outputs,
                        companions: Object.values(comps)
                    };
                },
                get canSubmit() {
                    const q = this.evalQty();
                    if (!q || q <= 0) return false;
                    if (!this.isValidLotFormat || this.isDuplicateLot) return false;
                    if (q > (this.materialItem?.stock ?? 0)) return false;
                    if (this.activeGroup) {
                        for (const c of this.activeGroup.companions) {
                            if (c.needed > c.input_stock) return false;
                        }
                    }
                    return true;
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
                        if (!['RM', 'KOMPONEN', 'FG/KOMPONEN'].includes(found.type) || !['SUPPLIER', 'INTERNAL'].includes(found.company_type)) {
                            this.errMsg = `Ditolak: Material Input wajib bertipe RM, KOMPONEN, atau FG/KOMPONEN (SUPPLIER/INTERNAL).`;
                            return;
                        }
                        const isTriggerInBom = this.boms.some(b => b.input_item_id === found.id && b.is_trigger);
                        if (!isTriggerInBom) {
                            this.errMsg = `Ditolak: Item ${found.part_name} tidak terdaftar sebagai Material Trigger Utama di BOM.`;
                            return;
                        }
                        if (found.stock <= 0) {
                            this.errMsg = `Ditolak: Stok ${found.part_name} saat ini kosong (0 ${found.uom}).`;
                            return;
                        }
                        this.materialItem = found;
                        this.manualCode = '';
                        this.step = 2;
                        return;
                    }

                    if (this.step === 2) {
                        if (!['FG', 'KOMPONEN', 'FG/KOMPONEN'].includes(found.type) || !['INTERNAL', 'CUSTOMER'].includes(found.company_type)) {
                            this.errMsg = `Ditolak: Target Output wajib bertipe FG, KOMPONEN, atau FG/KOMPONEN (INTERNAL/CUSTOMER).`;
                            return;
                        }
                        const triggerMatches = this.boms.filter(b => b.input_item_id === this.materialItem.id && b.output_item_id === found.id && b.is_trigger);
                        if (triggerMatches.length === 0) {
                            this.errMsg = `Ditolak: Pasangan Material ${this.materialItem.part_name} → ${found.part_name} tidak terdaftar sebagai Trigger di BOM.`;
                            return;
                        }

                        const groups = triggerMatches.map(tb => {
                            const grpRows = this.boms.filter(b => b.bom_group === tb.bom_group);
                            const outTriggerRows = grpRows.filter(b => b.input_item_id === this.materialItem.id && b.is_trigger);
                            const outputs = outTriggerRows.map(ot => ({
                                output_item_id: ot.output_item_id,
                                output_part_no: ot.output_part_no,
                                output_name: ot.output_name,
                                output_uom: ot.output_uom,
                                qty_usage: ot.qty_usage,
                                raw_companions: grpRows.filter(b => b.output_item_id === ot.output_item_id && !b.is_trigger)
                            }));
                            return {
                                bom_group: tb.bom_group,
                                group_type: tb.group_type,
                                process_name: tb.process_name,
                                outputs: outputs
                            };
                        });

                        window.DRCScanner.stop();
                        this.targetItem = found;
                        this.matchingGroups = groups;
                        this.selectedBomGroup = groups[0].bom_group;
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
                calcEst(usage) {
                    const q = this.evalQty();
                    if (!q || !usage || usage <= 0) return 0;
                    const raw = q / usage;
                    return Math.abs(raw - Math.round(raw)) < 0.001 ? Math.round(raw) : Math.round(raw * 100) / 100;
                },
                resetAll() {
                    this.materialItem = null;
                    this.targetItem = null;
                    this.matchingGroups = [];
                    this.selectedBomGroup = '';
                    this.lotNumber = '';
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