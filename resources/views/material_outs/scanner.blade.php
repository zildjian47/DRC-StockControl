<x-scanner-layout :backUrl="route('material_outs.index')">
    <x-slot name="header">Scanner Material Out</x-slot>

    <div x-data="matOutScanner({{ Js::from(route('material_outs.lookup')) }}, {{ Js::from($allScansData ?? []) }}, '{{ now()->format('Y-m-d') }}')"
         x-init="initScanner()"
         @pageshow.window="isSubmitting = false">

        {{-- VISUAL STEPPER --}}
        <div class="scan-stepper mb-3">
            <div class="scan-step" :class="step === 1 ? 'active' : (step > 1 ? 'done' : '')">
                <span class="scan-step-num" x-text="step > 1 ? '✓' : '1'"></span>
                <span class="truncate">Material</span>
            </div>
            <div class="scan-step" :class="step === 2 ? 'active' : (step > 2 ? 'done' : '')">
                <span class="scan-step-num" x-text="step > 2 ? '✓' : '2'"></span>
                <span class="truncate">Output</span>
            </div>
            <div class="scan-step" :class="step === 3 ? 'active' : ''">
                <span class="scan-step-num">3</span>
                <span class="truncate">Lot &amp; Qty</span>
            </div>
        </div>

        {{-- KARTU UTAMA SCANNER --}}
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span x-show="step === 1">Langkah 1 · Scan Material</span>
                    <span x-show="step === 2" x-cloak>Langkah 2 · Scan Output</span>
                    <span x-show="step === 3" x-cloak>Langkah 3 · Lot &amp; Qty</span>
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
                                Aktifkan kamera untuk scan QR, atau ketik kode di bawah.
                            </div>
                            <button type="button" @click="activateCamera()" class="btn btn-primary">
                                <x-icon name="scan" /> Aktifkan Kamera
                            </button>
                        </div>
                    </div>

                    {{-- RINGKASAN MATERIAL TERPILIH SAAT DI LANGKAH 2 --}}
                    <div x-show="step === 2 && materialItem" x-cloak class="p-3 rounded bg-slate-50 border border-slate-200 mb-3 flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="ty" :class="tyClass(materialItem?.type)" x-text="materialItem?.type"></span>
                                <span class="text-[11px] font-semibold uppercase text-slate-500">Material Trigger</span>
                            </div>
                            <div class="num text-xs font-bold text-accent mt-1" x-text="materialItem?.part_no || '-'"></div>
                            <div class="text-xs font-semibold text-slate-900 break-words" x-text="materialItem?.part_name"></div>
                        </div>
                        <div class="text-right whitespace-nowrap flex-none">
                            <div class="text-[11px] text-slate-500">Stok Tersedia</div>
                            <div class="num text-sm font-bold text-emerald-700" x-text="(materialItem?.stock ?? 0) + ' ' + (materialItem?.uom ?? '')"></div>
                            <button type="button" @click="resetAll()" class="text-[11px] font-semibold text-rose-600 hover:underline mt-0.5">Ganti</button>
                        </div>
                    </div>

                    {{-- INPUT MANUAL STEP 1 / STEP 2 --}}
                    <div>
                        <label class="lbl" x-text="step === 1 ? 'Part No / Part Name Material' : 'Part No / Part Name Output'"></label>
                        <div class="flex gap-2">
                            <input type="text" x-model="manualCode"
                                   @keydown.enter.prevent="handleScan(manualCode)"
                                   :placeholder="step === 1 ? 'Contoh: SHEET METAL SPHC 2.0MM atau FGK-PLT-001' : 'Contoh: FG-BKT-001 atau FG-GUS-R'"
                                   class="num uppercase flex-1 h-10 text-xs font-semibold">
                            <button type="button" @click="handleScan(manualCode)" :disabled="isLookingUp" class="btn btn-primary px-4 h-10">
                                <span x-text="isLookingUp ? 'Cek...' : 'Cek Kode'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- LANGKAH 3: FORM LOT & QTY MATERIAL OUT --}}
                <div x-show="step === 3" x-cloak class="space-y-4">
                    {{-- 1. Kartu Material Trigger --}}
                    <div class="p-3 rounded bg-slate-50 border border-slate-200 flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="ty" :class="tyClass(materialItem?.type)" x-text="materialItem?.type"></span>
                                <span class="pill pill-good">TRIGGER</span>
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
                        <label class="lbl text-amber-900">Kelompok BOM</label>
                        <select x-model="selectedBomGroup" class="w-full h-10 text-xs font-semibold">
                            <template x-for="cg in candidateGroups" :key="cg.bom_group">
                                <option :value="cg.bom_group" x-text="cg.bom_group + ' (' + cg.group_type + '): ' + cg.outputs_label"></option>
                            </template>
                        </select>
                    </div>

                    {{-- 2. Daftar Target Output & Estimasi Hasil --}}
                    <div class="border border-slate-200 rounded overflow-hidden">
                        <div class="px-3 py-2 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 uppercase">Target Output &amp; Estimasi</span>
                            <span class="count-chip"><span x-text="groupTargetOutputs.length"></span><span>OUTPUT</span></span>
                        </div>
                        <div class="divide-y divide-slate-200">
                            <template x-for="out in groupTargetOutputs" :key="out.output_item_id">
                                <div class="p-3 flex items-center justify-between gap-3 bg-white">
                                    <div class="min-w-0 flex-1">
                                        <div class="num text-xs font-bold text-accent" x-text="out.part_no || '-'"></div>
                                        <div class="text-xs font-semibold text-slate-900 break-words" x-text="out.part_name"></div>
                                        <div class="text-[11px] text-slate-500 mt-0.5 num" x-text="'Rasio: ' + out.qty_usage_display + ' ' + (materialItem?.uom || '') + ' / 1 ' + out.uom"></div>
                                    </div>
                                    <div class="text-right whitespace-nowrap flex-none">
                                        <span class="pill pill-info num" x-text="'EST: ' + out.est_qty + ' ' + out.uom"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- 3. Daftar Komponen Pendamping (Jika Multi-Input / Kombinasi) --}}
                    <div x-show="groupCompanions.length > 0" class="border border-slate-200 rounded overflow-hidden">
                        <div class="px-3 py-2 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 uppercase">Komponen Pendamping</span>
                            <span class="count-chip count-chip-sub"><span x-text="groupCompanions.length"></span><span>ITEM</span></span>
                        </div>
                        <div class="divide-y divide-slate-200">
                            <template x-for="comp in groupCompanions" :key="comp.input_item_id">
                                <div class="p-3 flex items-center justify-between gap-3 bg-white">
                                    <div class="min-w-0 flex-1">
                                        <div class="num text-xs font-bold text-slate-600" x-text="comp.part_no || '-'"></div>
                                        <div class="text-xs font-semibold text-slate-900 break-words" x-text="comp.part_name"></div>
                                        <div class="text-[11px] text-slate-500 mt-0.5 num" x-text="'Stok: ' + comp.stock + ' ' + comp.uom"></div>
                                    </div>
                                    <div class="text-right whitespace-nowrap flex-none">
                                        <span class="pill num"
                                              :class="comp.needed > comp.stock ? 'pill-crit' : 'pill-warn'"
                                              x-text="'-' + comp.needed + ' ' + comp.uom"></span>
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
                        <input type="hidden" name="bom_group" :value="activeGroupObj?.bom_group_raw ?? ''">

                        <div>
                            <label class="lbl">Nomor Lot</label>
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
                                <span class="inp-calc-ic" title="Mendukung kalkulator (+, -, *, /)">
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

                        <div class="dlg-list" x-show="parsedQty > 0" x-cloak>
                            <div>
                                <span>Stok</span>
                                <b class="num"
                                   :class="parsedQty > (materialItem?.stock || 0) ? 'text-rose-700' : 'text-slate-900'"
                                   x-text="(materialItem?.stock ?? 0) + ' → ' + Math.round(((materialItem?.stock || 0) - parsedQty) * 100) / 100 + ' ' + (materialItem?.uom || '')"></b>
                            </div>
                        </div>

                        <button type="submit" :disabled="isSubmitting" class="btn btn-primary btn-lg w-full">
                            <span x-text="isSubmitting ? 'Menyimpan...' : 'Simpan Material Out'"></span>
                        </button>
                    </form>

                    <button type="button" @click="resetAll()" :disabled="isSubmitting" class="btn w-full">
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
                <template x-for="s in filteredHistory" :key="s.lot_number">
                    <div class="p-3 flex items-center justify-between gap-3 text-xs bg-white hover:bg-slate-50">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="pill pill-info num" x-text="s.lot_number"></span>
                                <span class="num text-[11px] text-slate-500" x-text="s.date_fmt"></span>
                                <span class="text-[11px] text-slate-500" x-text="'· ' + s.user_name"></span>
                            </div>
                            <div class="font-semibold text-slate-900 mt-1 break-words" x-text="(s.mat_no !== '-' ? s.mat_no + ' — ' : '') + s.mat_name"></div>
                            <div class="text-[11px] text-slate-500 break-words" x-text="'Target: ' + s.targets"></div>
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
        function matOutScanner(lookupUrl, allScans, todayStr) {
            return {
                lookupUrl: lookupUrl,
                step: 1,
                cameraActive: false,
                isLookingUp: false,
                isSubmitting: false,
                manualCode: '',
                lastCamCode: '',
                lastCamAt: 0,
                existingLots: [],
                allScans: allScans || [],
                todayStr: todayStr,
                histDate: todayStr,
                histSearch: '',

                materialItem: null,
                targetItem: null,
                candidateGroups: [],
                selectedBomGroup: '',
                lotNumber: '',
                rawQty: '',

                tyClass(t) {
                    return 'ty-' + (t === 'FG/KOMPONEN' ? 'FGK' : (t || 'RM'));
                },
                notifyError(msg) {
                    if (window.DRCToast) window.DRCToast.show(msg, 'crit');
                },
                clearToast() {
                    if (window.DRCToast) window.DRCToast.hide();
                },
                initScanner() {
                    if (window.DRCScanner && window.DRCScanner.isPermitted()) {
                        this.activateCamera();
                    }
                },
                async activateCamera() {
                    this.cameraActive = true;
                    await this.$nextTick();
                    const ok = await window.DRCScanner.start(
                        'qr-reader',
                        (decoded) => this.handleScan(decoded, true),
                        (err) => {
                            this.cameraActive = false;
                            this.notifyError(err);
                        }
                    );
                    if (!ok) this.cameraActive = false;
                },
                async handleScan(rawCode, fromCamera = false) {
                    const c = String(rawCode || '').trim().toUpperCase();
                    if (!c || this.isLookingUp) return;
                    if (fromCamera) {
                        const now = Date.now();
                        if (this.lastCamCode === c && (now - this.lastCamAt) < 900) return;
                        this.lastCamCode = c;
                        this.lastCamAt = now;
                    }

                    this.isLookingUp = true;
                    try {
                        const params = new URLSearchParams({
                            step: String(this.step),
                            code: c
                        });
                        if (this.step === 2 && this.materialItem) {
                            params.set('material_item_id', String(this.materialItem.id));
                        }

                        const res = await fetch(`${this.lookupUrl}?${params.toString()}`, {
                            method: 'GET',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (!res.ok) {
                            throw new Error(`Gagal memeriksa kode (HTTP ${res.status}).`);
                        }
                        const data = await res.json();
                        if (!data.ok || !data.item) {
                            this.notifyError(data.message || `Kode "${rawCode}" tidak ditemukan.`);
                            return;
                        }

                        if (this.step === 1) {
                            this.clearToast();
                            this.materialItem = data.item;
                            this.manualCode = '';
                            if (fromCamera) {
                                this.lastCamCode = c;
                                this.lastCamAt = Date.now() + 600;
                            }
                            this.step = 2;
                        } else if (this.step === 2) {
                            this.clearToast();
                            if (data.material_item) {
                                this.materialItem = data.material_item;
                            }
                            this.targetItem = data.item;
                            this.candidateGroups = Array.isArray(data.candidate_groups) ? data.candidate_groups : [];
                            this.existingLots = Array.isArray(data.existing_lots) ? data.existing_lots : [];
                            this.selectedBomGroup = this.candidateGroups.length > 0 ? this.candidateGroups[0].bom_group : '';
                            this.manualCode = '';
                            this.lastCamCode = '';
                            this.lastCamAt = 0;
                            this.step = 3;
                            window.DRCScanner.stop();
                            this.cameraActive = false;
                        }
                    } catch (err) {
                        this.notifyError((err && err.message) ? err.message : 'Gagal menghubungi server. Periksa koneksi jaringan.');
                    } finally {
                        this.isLookingUp = false;
                    }
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
                get activeGroupObj() {
                    return this.candidateGroups.find(x => x.bom_group === this.selectedBomGroup) || null;
                },
                get activeGroupType() {
                    return this.activeGroupObj ? this.activeGroupObj.group_type : 'SINGLE';
                },
                get activeProcessName() {
                    return this.activeGroupObj ? this.activeGroupObj.process_name : '-';
                },
                get groupTargetOutputs() {
                    const grp = this.activeGroupObj;
                    if (!this.materialItem || !grp || !Array.isArray(grp.triggers)) return [];
                    const q = this.parsedQty;
                    return grp.triggers.map(r => {
                        const rawEst = (q > 0 && r.qty_usage > 0) ? (q / r.qty_usage) : 0;
                        const nearest = Math.round(rawEst);
                        const est = rawEst > 0
                            ? (Math.abs(rawEst - nearest) < 0.001 ? nearest : Math.round(rawEst * 100) / 100)
                            : 0;
                        return {
                            output_item_id: r.output_item_id,
                            part_no: r.part_no || '-',
                            part_name: r.part_name || '-',
                            uom: r.uom || 'PCS',
                            qty_usage: r.qty_usage,
                            qty_usage_display: r.qty_usage_display || r.qty_usage,
                            est_qty: est
                        };
                    });
                },
                get groupCompanions() {
                    const grp = this.activeGroupObj;
                    if (!this.materialItem || !grp || !Array.isArray(grp.companions)) return [];
                    const outputs = this.groupTargetOutputs;
                    const map = {};
                    outputs.forEach(out => {
                        grp.companions
                            .filter(cr => String(cr.output_item_id) === String(out.output_item_id))
                            .forEach(cr => {
                                const needed = Math.round((out.est_qty * cr.qty_usage) * 100) / 100;
                                if (!map[cr.input_item_id]) {
                                    map[cr.input_item_id] = {
                                        input_item_id: cr.input_item_id,
                                        part_no: cr.part_no || '-',
                                        part_name: cr.part_name,
                                        uom: cr.uom,
                                        stock: cr.stock,
                                        needed: 0
                                    };
                                }
                                map[cr.input_item_id].needed = Math.round((map[cr.input_item_id].needed + needed) * 100) / 100;
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
                    if (this.isSubmitting) return;
                    if (!this.materialItem || !this.targetItem) {
                        this.notifyError('Scan Material dan Target Output terlebih dahulu.');
                        return;
                    }
                    const lot = String(this.lotNumber || '').trim().toUpperCase();
                    this.lotNumber = lot;
                    if (!lot) {
                        this.notifyError('Nomor Lot wajib diisi.');
                        return;
                    }
                    if (!this.isValidLotFormat) {
                        this.notifyError('Format Lot wajib 1 huruf kapital + 6 angka (contoh: J260178).');
                        return;
                    }
                    if (this.isLotDuplicate) {
                        this.notifyError(`Nomor Lot ${lot} sudah terdaftar.`);
                        return;
                    }
                    if (String(this.rawQty || '').trim() === '') {
                        this.notifyError('Qty wajib diisi.');
                        return;
                    }
                    const val = this.evalCalc(this.rawQty);
                    if (val === null) {
                        this.notifyError('Format perhitungan tidak valid.');
                        return;
                    }
                    if (val <= 0) {
                        this.notifyError('Qty harus lebih dari 0.');
                        return;
                    }
                    if (val > (this.materialItem.stock || 0) + 0.0001) {
                        this.notifyError(`Stok kurang. Tersedia: ${this.materialItem.stock} ${this.materialItem.uom}.`);
                        return;
                    }
                    const lacking = this.groupCompanions.find(c => c.needed > c.stock + 0.0001);
                    if (lacking) {
                        this.notifyError(`Stok pendamping ${lacking.part_name} kurang (butuh ${lacking.needed}, tersedia ${lacking.stock} ${lacking.uom}).`);
                        return;
                    }
                    this.isSubmitting = true;
                    formEl.submit();
                },
                get filteredHistory() {
                    return this.allScans.filter(s => {
                        if (this.histDate && s.date !== this.histDate) return false;
                        if (this.histSearch) {
                            const q = this.histSearch.toLowerCase();
                            const hay = `${s.lot_number} ${s.mat_no} ${s.mat_name} ${s.targets} ${s.qty} ${s.user_name}`.toLowerCase();
                            if (!hay.includes(q)) return false;
                        }
                        return true;
                    });
                },
                resetAll() {
                    this.isSubmitting = false;
                    this.materialItem = null;
                    this.targetItem = null;
                    this.candidateGroups = [];
                    this.existingLots = [];
                    this.selectedBomGroup = '';
                    this.lotNumber = '';
                    this.rawQty = '';
                    this.manualCode = '';
                    this.lastCamCode = '';
                    this.lastCamAt = 0;
                    this.clearToast();
                    this.step = 1;
                    if (window.DRCScanner && window.DRCScanner.isPermitted()) {
                        this.activateCamera();
                    }
                }
            };
        }
    </script>
</x-scanner-layout>