<x-scanner-layout :backUrl="route('material_returns.index')">
    <x-slot name="header">Scanner Material Return</x-slot>

    <div x-data="matReturnScanner({{ Js::from(route('material_returns.lookup')) }}, {{ Js::from($allScansData ?? []) }}, '{{ now()->format('Y-m-d') }}')"
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

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span x-show="step === 1">Langkah 1 · Scan Material</span>
                    <span x-show="step === 2" x-cloak>Langkah 2 · Scan Output</span>
                    <span x-show="step === 3" x-cloak>Langkah 3 · Lot &amp; Qty</span>
                </div>
                <span class="pill pill-info">1X RETURN / LOT</span>
            </div>

            <div class="panel-body space-y-4">
                {{-- AREA KAMERA (STEP 1 & 2) --}}
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

                    <div x-show="step === 2 && materialItem" x-cloak class="p-3 rounded bg-slate-50 border border-slate-200 mb-3 flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="ty" :class="tyClass(materialItem?.type)" x-text="materialItem?.type"></span>
                                <span class="text-[11px] font-semibold uppercase text-slate-500">Material Retur</span>
                            </div>
                            <div class="num text-xs font-bold text-accent mt-1" x-text="materialItem?.part_no || '-'"></div>
                            <div class="text-xs font-semibold text-slate-900 break-words" x-text="materialItem?.part_name"></div>
                        </div>
                        <button type="button" @click="resetAll()" class="text-xs font-semibold text-rose-600 hover:underline flex-none">Ganti</button>
                    </div>

                    <div>
                        <label class="lbl" x-text="step === 1 ? 'Part No / Part Name Material' : 'Part No / Part Name Output'"></label>
                        <div class="flex gap-2">
                            <input type="text" x-model="manualCode"
                                   @keydown.enter.prevent="handleScan(manualCode)"
                                   :placeholder="step === 1 ? 'Contoh: SHEET TRAPESIUM SPHC 2.3MM' : 'Contoh: FG-GUS-R'"
                                   class="num uppercase flex-1 h-10 text-xs font-semibold">
                            <button type="button" @click="handleScan(manualCode)" :disabled="isLookingUp" class="btn btn-primary px-4 h-10">
                                <span x-text="isLookingUp ? 'Cek...' : 'Cek Kode'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- STEP 3: PILIH LOT & INPUT QTY RETURN --}}
                <div x-show="step === 3" x-cloak class="space-y-4">
                    <div class="p-3 rounded bg-slate-50 border border-slate-200 space-y-2 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="ty" :class="tyClass(materialItem?.type)" x-text="materialItem?.type"></span>
                                    <span class="text-[11px] font-semibold text-slate-500 uppercase">Material</span>
                                </div>
                                <div class="font-bold text-slate-900 break-words mt-1" x-text="(materialItem?.part_no ? materialItem.part_no + ' — ' : '') + (materialItem?.part_name || '')"></div>
                            </div>
                            <span class="num text-xs font-semibold text-slate-600 flex-none" x-text="materialItem?.uom"></span>
                        </div>
                        <div class="pt-2 border-t border-slate-200 flex items-center justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="text-[11px] font-semibold text-slate-500 uppercase">Target Output</div>
                                <div class="font-bold text-accent break-words mt-0.5" x-text="(targetItem?.part_no ? targetItem.part_no + ' — ' : '') + (targetItem?.part_name || '')"></div>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('material_returns.store') }}" method="POST" novalidate
                          @submit.prevent="submitForm($el)"
                          class="space-y-4">
                        @csrf
                        <input type="hidden" name="mode" value="scanner">
                        <input type="hidden" name="material_item_id" :value="materialItem?.id">
                        <input type="hidden" name="target_item_id" :value="targetItem?.id">

                        <div>
                            <label class="lbl">Nomor Lot Aktif</label>
                            <select name="lot_number" x-model="lotNumber" required class="w-full h-11 text-xs font-semibold">
                                <option value="">-- Pilih Nomor Lot --</option>
                                <template x-for="lt in matchingLots" :key="lt.lot_number">
                                    <option :value="lt.lot_number"
                                            x-text="lt.lot_number + ' · Sisa: ' + lt.mat_saat_ini + ' ' + lt.mat_uom"></option>
                                </template>
                            </select>
                        </div>

                        <div x-show="selectedLotObj" class="p-3 rounded bg-blue-50/70 border border-blue-200 flex items-center justify-between text-xs">
                            <span class="text-blue-900 font-medium">Sisa Material di Mesin</span>
                            <b class="num text-sm text-accent" x-text="(selectedLotObj?.mat_saat_ini ?? 0) + ' ' + (materialItem?.uom || '')"></b>
                        </div>

                        <div>
                            <label class="lbl">Qty Material Return (<span x-text="materialItem?.uom || 'Unit'"></span>)</label>
                            <div class="inp-calc">
                                <input type="text" name="qty" x-model="rawQty" required
                                       placeholder="Contoh: 5"
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

                        <div class="dlg-list" x-show="selectedLotObj && parsedQty > 0" x-cloak>
                            <div>
                                <span>Sisa Mesin</span>
                                <b class="num"
                                   :class="parsedQty > (selectedLotObj?.mat_saat_ini || 0) ? 'text-rose-700' : 'text-slate-900'"
                                   x-text="(selectedLotObj?.mat_saat_ini ?? 0) + ' → ' + Math.round(((selectedLotObj?.mat_saat_ini || 0) - parsedQty) * 100) / 100 + ' ' + (materialItem?.uom || '')"></b>
                            </div>
                        </div>

                        <button type="submit" :disabled="isSubmitting" class="btn btn-primary btn-lg w-full">
                            <span x-text="isSubmitting ? 'Menyimpan...' : 'Simpan Material Return'"></span>
                        </button>
                    </form>

                    <button type="button" @click="resetAll()" :disabled="isSubmitting" class="btn w-full">
                        Batal &amp; Scan Ulang
                    </button>
                </div>
            </div>
        </div>

        {{-- PANEL RIWAYAT SCAN --}}
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Scan Material Return</span>
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
                            <span class="num text-sm font-bold text-emerald-700" x-text="'+' + s.qty + ' ' + s.mat_uom"></span>
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
        function matReturnScanner(lookupUrl, allScans, todayStr) {
            return {
                lookupUrl: lookupUrl,
                step: 1,
                cameraActive: false,
                isLookingUp: false,
                isSubmitting: false,
                manualCode: '',
                lastCamCode: '',
                lastCamAt: 0,
                allScans: allScans || [],
                todayStr: todayStr,
                histDate: todayStr,
                histSearch: '',

                materialItem: null,
                targetItem: null,
                matchingLots: [],
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
                            this.matchingLots = Array.isArray(data.lots) ? data.lots : [];
                            this.lotNumber = this.matchingLots.length === 1 ? this.matchingLots[0].lot_number : '';
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
                get selectedLotObj() {
                    return this.matchingLots.find(l => l.lot_number === this.lotNumber) || null;
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
                    if (this.isSubmitting) return;
                    if (!this.materialItem || !this.targetItem) {
                        this.notifyError('Scan Material dan Target Output terlebih dahulu.');
                        return;
                    }
                    if (!this.lotNumber || !this.selectedLotObj) {
                        this.notifyError('Pilih Nomor Lot terlebih dahulu.');
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
                    if (val > this.selectedLotObj.mat_saat_ini + 0.0001) {
                        this.notifyError(`Qty melebihi sisa material di mesin (tersedia ${this.selectedLotObj.mat_saat_ini} ${this.materialItem?.uom || ''}).`);
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
                    this.matchingLots = [];
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