<x-scanner-layout :backUrl="route('material_outs.index')">
    <x-slot name="header">Scanner Material Out</x-slot>

    @php
        $allScansData = \App\Models\MaterialOut::with(['materialItem', 'targetItem', 'user'])
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
                    'mat_no'     => $s->materialItem->part_no ?: '-',
                    'mat_name'   => $s->materialItem->part_name ?? '-',
                    'mat_uom'    => $s->materialItem->uom ?? '',
                    'tgt_no'     => $s->targetItem->part_no ?: '-',
                    'tgt_name'   => $s->targetItem->part_name ?? '-',
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
            'company_code' => $i->company->company_code ?? 'INTERNAL',
            'company_type' => $i->company->type_company ?? 'INTERNAL',
            'stock'        => $i->inventoryStock ? (float) $i->inventoryStock->current_stock : 0,
        ])->values();

        $bomsPayload = $allBoms->map(fn ($b) => [
            'id'             => $b->id,
            'bom_group'      => $b->bom_group ?: ('LEGACY-' . $b->process_id . '-' . $b->output_item_id),
            'group_type'     => $b->group_type,
            'process_id'     => $b->process_id,
            'process_name'   => $b->process->process_name ?? '-',
            'input_item_id'  => $b->input_item_id,
            'output_item_id' => $b->output_item_id,
            'qty_usage'      => (float) $b->qty_usage,
            'is_trigger'     => (bool) $b->is_trigger,
        ])->values();
    @endphp

    <div x-data="matOutScanner({{ Js::from($itemsPayload) }}, {{ Js::from($bomsPayload) }}, {{ Js::from($existingLots) }}, {{ Js::from($allScansData) }}, '{{ now()->format('Y-m-d') }}')"
         x-init="initScanner()">

        {{-- VISUAL STEPPER --}}
        <div class="scan-stepper mb-3">
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

        {{-- NOTIFIKASI ERROR VALIDASI SCANNER --}}
        <div x-show="errorMsg" x-cloak class="alert alert-crit mb-3" role="alert">
            <x-icon name="alert" />
            <span class="flex-1 min-w-0" x-text="errorMsg"></span>
            <button type="button" @click="errorMsg = ''" class="act-btn flex-none -my-1 -mr-1"><x-icon name="x" /></button>
        </div>

        {{-- KARTU UTAMA SCANNER --}}
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span x-show="step === 1">Langkah 1 · Scan Material Trigger</span>
                    <span x-show="step === 2" x-cloak>Langkah 2 · Scan Target Output</span>
                    <span x-show="step === 3" x-cloak>Langkah 3 · Input Nomor Lot &amp; Qty Out</span>
                </div>
                <span class="pill pill-info" x-show="step === 1">RM · KOMPONEN · FG/KOMPONEN</span>
                <span class="pill pill-info" x-show="step === 2" x-cloak>FG · FG/KOMPONEN · KOMPONEN</span>
                <span class="pill pill-good" x-show="step === 3" x-cloak x-text="activeGroupType"></span>
            </div>

            <div class="panel-body space-y-4">
                {{-- AREA KAMERA (AKTIF DI LANGKAH 1 & 2) --}}
                <div x-show="step === 1 || step === 2">
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

                    {{-- RINGKASAN MATERIAL TERPILIH SAAT DI LANGKAH 2 --}}
                    <div x-show="step === 2 && materialItem" x-cloak class="p-3 rounded bg-slate-50 border border-slate-200 mb-3 flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="text-[11px] font-semibold uppercase text-slate-500">Material Trigger Terpilih</div>
                            <div class="num text-xs font-bold text-accent mt-0.5" x-text="materialItem?.part_no || materialItem?.type"></div>
                            <div class="text-xs font-semibold text-slate-900 break-words" x-text="materialItem?.part_name"></div>
                        </div>
                        <div class="text-right whitespace-nowrap flex-none">
                            <span class="pill pill-good num" x-text="'Stok: ' + (materialItem?.stock ?? 0) + ' ' + (materialItem?.uom ?? '')"></span>
                            <div class="mt-1">
                                <button type="button" @click="resetAll()" class="text-[11px] font-semibold text-rose-600 hover:underline">Ganti Material</button>
                            </div>
                        </div>
                    </div>

                    {{-- INPUT MANUAL STEP 1 / STEP 2 --}}
                    <div>
                        <label class="lbl" x-text="step === 1 ? 'Ketik Part No / Part Name Material Trigger' : 'Ketik Part No / Part Name Target Output'"></label>
                        <div class="flex gap-2">
                            <input type="text" x-model="manualCode"
                                   @keydown.enter.prevent="handleScan(manualCode)"
                                   :placeholder="step === 1 ? 'Contoh: SHEET METAL SPHC 2.0MM atau FGK-PLT-001' : 'Contoh: FG-BKT-001 atau FG-GUS-R'"
                                   class="num uppercase flex-1 h-10 text-xs font-semibold">
                            <button type="button" @click="handleScan(manualCode)" class="btn btn-primary px-4 h-10">
                                Cek Kode
                            </button>
                        </div>
                    </div>
                </div>

                {{-- LANGKAH 3: FORM LOT & QTY MATERIAL OUT --}}
                <div x-show="step === 3" x-cloak class="space-y-4">
                    {{-- 1. Kartu Material Trigger --}}
                    <div class="p-3 rounded bg-slate-50 border border-slate-200 flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="pill pill-good text-[10px] h-4 px-1.5">Material Trigger</span>
                                <span class="text-[11px] font-semibold text-slate-500" x-text="activeProcessName"></span>
                            </div>
                            <div class="num text-xs font-bold text-accent mt-1" x-text="materialItem?.part_no || '-'"></div>
                            <div class="text-xs font-semibold text-slate-900 break-words" x-text="materialItem?.part_name"></div>
                        </div>
                        <div class="text-right whitespace-nowrap flex-none">
                            <div class="text-[11px] text-slate-500">Stok Tersedia</div>
                            <div class="num text-sm font-bold text-emerald-700" x-text="(materialItem?.stock ?? 0) + ' ' + (materialItem?.uom ?? '')"></div>
                        </div>
                    </div>

                    {{-- Pilihan Kelompok Cetakan jika ada >1 Grup BOM untuk pasangan Material + Target yang sama --}}
                    <div x-show="candidateGroups.length > 1" class="p-3 rounded bg-amber-50/70 border border-amber-200 space-y-2">
                        <label class="lbl text-amber-900">Pilih Kelompok Cetakan BOM</label>
                        <select x-model="selectedBomGroup" class="w-full h-10 text-xs font-semibold">
                            <template x-for="cg in candidateGroups" :key="cg.bom_group">
                                <option :value="cg.bom_group" x-text="cg.bom_group + ' (' + cg.group_type + '): ' + cg.outputs_label"></option>
                            </template>
                        </select>
                    </div>

                    {{-- 2. Daftar Target Output & Estimasi Hasil (2-Tier Anti-Terpotong) --}}
                    <div class="border border-slate-200 rounded overflow-hidden">
                        <div class="px-3 py-2 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 uppercase">Target Output &amp; Estimasi Hasil</span>
                            <span class="count-chip"><span x-text="groupTargetOutputs.length"></span><span>Output</span></span>
                        </div>
                        <div class="divide-y divide-slate-200">
                            <template x-for="out in groupTargetOutputs" :key="out.output_item_id">
                                <div class="p-3 flex items-center justify-between gap-3 bg-white">
                                    <div class="min-w-0 flex-1">
                                        <div class="num text-xs font-bold text-accent" x-text="out.part_no || '-'"></div>
                                        <div class="text-xs font-semibold text-slate-900 break-words" x-text="out.part_name"></div>
                                        <div class="text-[11px] text-slate-500 mt-0.5 num" x-text="'Rasio Trigger: ' + out.qty_usage + ' ' + (materialItem?.uom || '') + ' / 1 ' + out.uom"></div>
                                    </div>
                                    <div class="text-right whitespace-nowrap flex-none">
                                        <span class="pill pill-info num text-xs" x-text="'Est: ' + out.est_qty + ' ' + out.uom"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- 3. Daftar Komponen Pendamping (Jika Multi-Input / Kombinasi) --}}
                    <div x-show="groupCompanions.length > 0" class="border border-slate-200 rounded overflow-hidden">
                        <div class="px-3 py-2 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 uppercase">Komponen Pendamping (Otomatis Terpotong)</span>
                            <span class="count-chip count-chip-sub"><span x-text="groupCompanions.length"></span><span>Komponen</span></span>
                        </div>
                        <div class="divide-y divide-slate-200">
                            <template x-for="comp in groupCompanions" :key="comp.input_item_id">
                                <div class="p-3 flex items-center justify-between gap-3 bg-white">
                                    <div class="min-w-0 flex-1">
                                        <div class="num text-xs font-bold text-slate-600" x-text="comp.part_no || '-'"></div>
                                        <div class="text-xs font-semibold text-slate-900 break-words" x-text="comp.part_name"></div>
                                        <div class="text-[11px] text-slate-500 mt-0.5 num" x-text="'Stok Gudang: ' + comp.stock + ' ' + comp.uom"></div>
                                    </div>
                                    <div class="text-right whitespace-nowrap flex-none">
                                        <span class="pill num text-xs"
                                              :class="comp.needed > comp.stock ? 'pill-crit' : 'pill-warn'"
                                              x-text="'Butuh: -' + comp.needed + ' ' + comp.uom"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- FORM SUBMIT MATERIAL OUT --}}
                    <form action="{{ route('material_outs.store') }}" method="POST" novalidate
                          @submit.prevent="submitForm($el)"
                          class="space-y-4">
                        @csrf
                        <input type="hidden" name="mode" value="scanner">
                        <input type="hidden" name="material_item_id" :value="materialItem?.id">
                        <input type="hidden" name="target_item_id" :value="targetItem?.id">
                        <input type="hidden" name="bom_group" :value="selectedBomGroup">

                        <div x-show="formError" x-cloak class="alert alert-crit text-xs" role="alert">
                            <x-icon name="alert" />
                            <span class="flex-1 min-w-0" x-text="formError"></span>
                        </div>

                        <div>
                            <label class="lbl">Nomor Lot Produksi (1 Huruf Kapital + 6 Angka)</label>
                            <input type="text" name="lot_number" x-model="lotNumber"
                                   @input="lotNumber = lotNumber.toUpperCase()"
                                   maxlength="7" required placeholder="Contoh: J260178"
                                   class="num uppercase w-full h-11 text-base font-bold tracking-wider">
                        </div>

                        <div>
                            <label class="lbl">Qty Material Out (<span x-text="materialItem?.uom || 'Unit'"></span>)</label>
                            <div class="inp-calc">
                                <input type="text" name="qty" x-model="rawQty" required
                                       placeholder="Contoh: 20 atau 10*2"
                                       class="num w-full h-14 text-2xl text-center font-bold pl-[30px]">
                                <span class="inp-calc-ic" title="Mendukung perhitungan kalkulator (+, -, *, /)">
                                    <x-icon name="calc" />
                                </span>
                            </div>
                            <div class="calc-preview text-center" x-show="String(rawQty || '').trim() !== '' && evalCalc(rawQty) !== null" x-cloak>
                                Hasil: <b x-text="evalCalc(rawQty)"></b> <span x-text="materialItem?.uom || ''"></span>
                            </div>
                            <div class="calc-err justify-center w-full" x-show="String(rawQty || '').trim() !== '' && evalCalc(rawQty) === null" x-cloak>
                                <x-icon name="alert" />
                                <span>Format perhitungan tidak valid</span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-full">
                            Simpan Material Out (- Stok)
                        </button>
                    </form>

                    <button type="button" @click="resetAll()" class="btn w-full">
                        Batal &amp; Scan Ulang
                    </button>
                </div>
            </div>
        </div>

        {{-- PANEL RIWAYAT SCAN DENGAN FILTER TANGGAL & SEARCH --}}
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Scan Material Out</span>
                    <span class="count-chip"><span x-text="filteredHistory.length"></span><span>Scan</span></span>
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
                                <span class="pill pill-info num text-[10.5px]" x-text="s.lot_number"></span>
                                <span class="num text-[11px] text-slate-500" x-text="s.date_fmt"></span>
                                <span class="text-[11px] text-slate-500" x-text="'· ' + s.user_name"></span>
                            </div>
                            <div class="font-semibold text-slate-900 mt-1 break-words" x-text="(s.mat_no !== '-' ? s.mat_no + ' — ' : '') + s.mat_name"></div>
                            <div class="text-[11px] text-slate-500 break-words" x-text="'Target: ' + (s.tgt_no !== '-' ? s.tgt_no + ' — ' : '') + s.tgt_name"></div>
                        </div>
                        <div class="text-right whitespace-nowrap flex-none">
                            <span class="num text-sm font-bold text-rose-700" x-text="'-' + s.qty + ' ' + s.mat_uom"></span>
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
        function matOutScanner(items, boms, existingLots, allScans, todayStr) {
            return {
                step: 1,
                cameraActive: false,
                manualCode: '',
                errorMsg: '',
                formError: '',
                items: items,
                boms: boms,
                existingLots: existingLots,
                allScans: allScans,
                todayStr: todayStr,
                histDate: todayStr,
                histSearch: '',

                materialItem: null,
                targetItem: null,
                candidateGroups: [],
                selectedBomGroup: '',
                lotNumber: '',
                rawQty: '',

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
                findItem(code) {
                    const c = String(code || '').trim().toUpperCase();
                    if (!c) return null;
                    return this.items.find(i => i.part_no === c || i.part_name === c) || null;
                },
                handleScan(rawCode) {
                    this.errorMsg = '';
                    this.formError = '';
                    const found = this.findItem(rawCode);
                    if (!found) {
                        this.errorMsg = `Ditolak: Kode "${rawCode}" tidak ditemukan di Master Item aktif.`;
                        return;
                    }

                    if (this.step === 1) {
                        if (!['RM', 'KOMPONEN', 'FG/KOMPONEN'].includes(found.type) || !['SUPPLIER', 'INTERNAL'].includes(found.company_type)) {
                            this.errorMsg = `Ditolak: ${found.part_name} (${found.type} - ${found.company_type}) tidak diizinkan sebagai Material Input.`;
                            return;
                        }
                        const isTrig = this.boms.some(b => String(b.input_item_id) === String(found.id) && b.is_trigger);
                        if (!isTrig) {
                            this.errorMsg = `Ditolak: ${found.part_name} tidak terdaftar sebagai Material Trigger Utama di BOM.`;
                            return;
                        }
                        if (found.stock <= 0) {
                            this.errorMsg = `Ditolak: Stok ${found.part_name} saat ini kosong (0 ${found.uom}).`;
                            return;
                        }
                        this.materialItem = found;
                        this.manualCode = '';
                        this.step = 2;
                    } else if (this.step === 2) {
                        if (!['FG', 'KOMPONEN', 'FG/KOMPONEN'].includes(found.type) || !['INTERNAL', 'CUSTOMER'].includes(found.company_type)) {
                            this.errorMsg = `Ditolak: ${found.part_name} (${found.type}) tidak sah sebagai Target Output.`;
                            return;
                        }
                        const matchedTriggers = this.boms.filter(b =>
                            String(b.input_item_id) === String(this.materialItem.id) &&
                            String(b.output_item_id) === String(found.id) &&
                            b.is_trigger
                        );
                        if (matchedTriggers.length === 0) {
                            this.errorMsg = `Ditolak: Pasangan Material [${this.materialItem.part_name}] dan Output [${found.part_name}] tidak terdaftar di BOM.`;
                            return;
                        }

                        this.targetItem = found;
                        this.candidateGroups = matchedTriggers.map(mt => {
                            const grpRows = this.boms.filter(b => b.bom_group === mt.bom_group && b.is_trigger);
                            const outNames = grpRows.map(gr => {
                                const it = this.items.find(x => String(x.id) === String(gr.output_item_id));
                                return it ? (it.part_no || it.part_name) : '-';
                            }).join(' + ');
                            return {
                                bom_group: mt.bom_group,
                                group_type: mt.group_type,
                                process_name: mt.process_name,
                                outputs_label: outNames
                            };
                        });
                        this.selectedBomGroup = this.candidateGroups[0].bom_group;
                        this.manualCode = '';
                        this.step = 3;
                        window.DRCScanner.stop();
                        this.cameraActive = false;
                    }
                },
                evalCalc(raw) {
                    if (raw === null || raw === undefined) return null;
                    const expr = String(raw).replace(/,/g, '.').replace(/\s+/g, '');
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
                get activeGroupType() {
                    const g = this.candidateGroups.find(x => x.bom_group === this.selectedBomGroup);
                    return g ? g.group_type : 'SINGLE';
                },
                get activeProcessName() {
                    const g = this.candidateGroups.find(x => x.bom_group === this.selectedBomGroup);
                    return g ? g.process_name : '-';
                },
                get groupTargetOutputs() {
                    if (!this.materialItem || !this.selectedBomGroup) return [];
                    const trigRows = this.boms.filter(b =>
                        b.bom_group === this.selectedBomGroup &&
                        String(b.input_item_id) === String(this.materialItem.id) &&
                        b.is_trigger
                    );
                    const q = this.parsedQty;
                    return trigRows.map(r => {
                        const outIt = this.items.find(i => String(i.id) === String(r.output_item_id));
                        const est = (q > 0 && r.qty_usage > 0) ? Math.round((q / r.qty_usage) * 100) / 100 : 0;
                        return {
                            output_item_id: r.output_item_id,
                            part_no: outIt?.part_no || '-',
                            part_name: outIt?.part_name || '-',
                            uom: outIt?.uom || 'PCS',
                            qty_usage: r.qty_usage,
                            est_qty: est
                        };
                    });
                },
                get groupCompanions() {
                    if (!this.materialItem || !this.selectedBomGroup) return [];
                    const outputs = this.groupTargetOutputs;
                    const map = {};
                    outputs.forEach(out => {
                        const compRows = this.boms.filter(b =>
                            b.bom_group === this.selectedBomGroup &&
                            String(b.output_item_id) === String(out.output_item_id) &&
                            !b.is_trigger
                        );
                        compRows.forEach(cr => {
                            const compIt = this.items.find(i => String(i.id) === String(cr.input_item_id));
                            if (!compIt) return;
                            const needed = Math.round((out.est_qty * cr.qty_usage) * 100) / 100;
                            if (!map[compIt.id]) {
                                map[compIt.id] = {
                                    input_item_id: compIt.id,
                                    part_no: compIt.part_no || '-',
                                    part_name: compIt.part_name,
                                    uom: compIt.uom,
                                    stock: compIt.stock,
                                    needed: 0
                                };
                            }
                            map[compIt.id].needed = Math.round((map[compIt.id].needed + needed) * 100) / 100;
                        });
                    });
                    return Object.values(map);
                },
                get isValidLotFormat() {
                    return /^[A-Z][0-9]{6}$/.test(this.lotNumber);
                },
                get isLotDuplicate() {
                    if (!this.lotNumber) return false;
                    return this.existingLots.includes(this.lotNumber.toUpperCase());
                },
                submitForm(formEl) {
                    this.formError = '';
                    if (!this.materialItem || !this.targetItem) {
                        this.formError = 'Silakan scan Material Trigger dan Target Output terlebih dahulu.';
                        return;
                    }
                    const lot = String(this.lotNumber || '').trim().toUpperCase();
                    this.lotNumber = lot;
                    if (!lot) {
                        this.formError = 'Nomor Lot Produksi wajib diisi.';
                        return;
                    }
                    if (!this.isValidLotFormat) {
                        this.formError = 'Format Nomor Lot wajib 1 Huruf Kapital diikuti 6 Angka (contoh: J260178).';
                        return;
                    }
                    if (this.isLotDuplicate) {
                        this.formError = `Ditolak: Nomor Lot ${lot} sudah digunakan pada Material Out sebelumnya.`;
                        return;
                    }
                    if (String(this.rawQty || '').trim() === '') {
                        this.formError = 'Qty Material Out wajib diisi.';
                        return;
                    }
                    const val = this.evalCalc(this.rawQty);
                    if (val === null) {
                        this.formError = 'Format perhitungan Qty Material Out tidak valid.';
                        return;
                    }
                    if (val <= 0) {
                        this.formError = 'Qty Material Out harus lebih besar dari 0.';
                        return;
                    }
                    if (val > (this.materialItem.stock || 0) + 0.0001) {
                        this.formError = `Ditolak: Qty Material Out (${val}) melebihi stok ${this.materialItem.part_name} yang tersedia (${this.materialItem.stock} ${this.materialItem.uom}).`;
                        return;
                    }
                    const lacking = this.groupCompanions.find(c => c.needed > c.stock + 0.0001);
                    if (lacking) {
                        this.formError = `Ditolak: Stok komponen pendamping [${lacking.part_name}] tidak mencukupi (Butuh: ${lacking.needed} ${lacking.uom}, Tersedia: ${lacking.stock} ${lacking.uom}).`;
                        return;
                    }
                    formEl.submit();
                },
                get filteredHistory() {
                    return this.allScans.filter(s => {
                        if (this.histDate && s.date !== this.histDate) return false;
                        if (this.histSearch) {
                            const q = this.histSearch.toLowerCase();
                            const hay = `${s.lot_number} ${s.mat_no} ${s.mat_name} ${s.tgt_no} ${s.tgt_name} ${s.qty} ${s.user_name}`.toLowerCase();
                            if (!hay.includes(q)) return false;
                        }
                        return true;
                    });
                },
                resetAll() {
                    this.materialItem = null;
                    this.targetItem = null;
                    this.candidateGroups = [];
                    this.selectedBomGroup = '';
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