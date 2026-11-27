<x-scanner-layout :backUrl="route('productions.index')">
    <x-slot name="header">Scanner Production</x-slot>

    <div x-data="prodScanner({{ Js::from(route('productions.lookup')) }}, {{ Js::from($allScansData ?? []) }}, '{{ now()->format('Y-m-d') }}')"
         x-init="initScanner()"
         @pageshow.window="isSubmitting = false"
         class="space-y-3.5">

        <div class="scan-stepper">
            <div class="scan-step" :class="step === 1 ? 'active' : 'done'">
                <span class="scan-step-num" x-text="step > 1 ? '✓' : '1'"></span>
                <span class="truncate">Scan Output</span>
            </div>
            <div class="scan-step" :class="step === 2 ? 'active' : ''">
                <span class="scan-step-num">2</span>
                <span class="truncate">Lot &amp; Qty</span>
            </div>
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
                    <div class="camera-box mb-2.5">
                        <div id="qr-reader" x-show="cameraActive"></div>
                        <div class="scan-scope-guide" x-show="cameraActive"></div>

                        <div x-show="!cameraActive" class="p-6 text-center space-y-3">
                            <div class="w-12 h-12 rounded bg-white/10 text-white grid place-items-center mx-auto">
                                <x-icon name="scan" class="w-6 h-6" />
                            </div>
                            <div class="text-xs text-slate-300 leading-relaxed">
                                Aktifkan kamera untuk scan QR Rak atau label part, lalu ketik manual jika diperlukan.
                            </div>
                            <button type="button" @click="activateCamera()" class="btn btn-primary">
                                <x-icon name="scan" /> Aktifkan Kamera
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="text-[11.5px] text-slate-500"
                              x-text="isLookingUp ? 'Memeriksa kode...' : (cameraActive ? 'Kamera aktif — bidik QR Rak atau label part' : 'Kamera nonaktif')"></span>
                        <button type="button" x-show="cameraActive" x-cloak @click="stopCamera()" class="btn btn-sm">
                            Matikan Kamera
                        </button>
                    </div>

                    <div>
                        <label class="lbl">Part No, Part Name, atau Kode QR Rak</label>
                        <div class="flex gap-2">
                            <input type="text" x-model="manualCode"
                                   @keydown.enter.prevent="handleScan(manualCode)"
                                   placeholder="Contoh: FG-BKT-001 atau FG-GUS-R"
                                   class="num uppercase flex-1 h-10 text-xs font-semibold">
                            <button type="button" @click="handleScan(manualCode)" :disabled="isLookingUp" class="btn btn-primary px-4 h-10">
                                <span x-text="isLookingUp ? 'Cek...' : 'Cek Kode'"></span>
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

                        <button type="submit" :disabled="isSubmitting" class="btn btn-primary btn-lg w-full">
                            <span x-text="isSubmitting ? 'Menyimpan...' : 'Simpan Production'"></span>
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
        function prodScanner(lookupUrl, allScans, todayStr) {
            const MAX_QTY = 99999999.99;
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
                scannedItem: null,
                itemLots: [],
                lotNumber: '',
                rawQty: '',

                tyClass(t) {
                    return 'ty-' + (t === 'FG/KOMPONEN' ? 'FGK' : (t || 'FG'));
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
                async stopCamera() {
                    if (window.DRCScanner) {
                        await window.DRCScanner.stop();
                    }
                    this.cameraActive = false;
                },
                async handleScan(rawCode, fromCamera = false) {
                    const c = String(rawCode || '').trim();
                    if (!c || this.isLookingUp) return;
                    const key = c.toUpperCase();
                    if (fromCamera) {
                        const now = Date.now();
                        if (this.lastCamCode === key && (now - this.lastCamAt) < 900) return;
                        this.lastCamCode = key;
                        this.lastCamAt = now;
                    }

                    this.isLookingUp = true;
                    try {
                        const url = `${this.lookupUrl}?code=${encodeURIComponent(c)}`;
                        const res = await fetch(url, {
                            method: 'GET',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (res.redirected || res.status === 401 || res.status === 419) {
                            this.notifyError('Sesi habis, silakan muat ulang halaman untuk masuk kembali.');
                            return;
                        }
                        const contentType = res.headers.get('content-type') || '';
                        if (!contentType.includes('application/json')) {
                            this.notifyError('Sesi habis atau respons server tidak valid. Silakan muat ulang halaman.');
                            return;
                        }
                        if (!res.ok) {
                            throw new Error(`Gagal memeriksa kode (HTTP ${res.status}).`);
                        }
                        const data = await res.json();
                        if (!data.ok || !data.item) {
                            this.notifyError(data.message || `Kode "${rawCode}" tidak ditemukan.`);
                            return;
                        }

                        this.clearToast();
                        this.scannedItem = data.item;
                        this.itemLots = Array.isArray(data.lots) ? data.lots : [];
                        this.lotNumber = this.itemLots.length === 1 ? this.itemLots[0].lot_number : '';
                        this.manualCode = '';
                        this.lastCamCode = '';
                        this.lastCamAt = 0;
                        this.step = 2;
                        await this.stopCamera();
                    } catch (err) {
                        this.notifyError((err && err.message) ? err.message : 'Gagal menghubungi server. Periksa koneksi jaringan.');
                    } finally {
                        this.isLookingUp = false;
                    }
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
                    if (this.isSubmitting) return;
                    if (!this.scannedItem) {
                        this.notifyError('Scan output terlebih dahulu.');
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
                    if (val > MAX_QTY) {
                        this.notifyError('Qty melebihi batas maksimal 99.999.999,99.');
                        return;
                    }
                    if ((this.scannedItem.stock || 0) + val > MAX_QTY) {
                        this.notifyError('Total stok melebihi batas maksimal 99.999.999,99.');
                        return;
                    }
                    if (val > this.selectedLotObj.qty_wip + 0.0001) {
                        this.notifyError(`Qty melebihi sisa WIP (tersedia ${this.selectedLotObj.qty_wip} ${this.scannedItem?.uom || ''}).`);
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
                            const hay = `${s.lot_number} ${s.part_no} ${s.part_name} ${s.qty} ${s.user_name}`.toLowerCase();
                            if (!hay.includes(q)) return false;
                        }
                        return true;
                    });
                },
                resetAll() {
                    this.isSubmitting = false;
                    this.scannedItem = null;
                    this.itemLots = [];
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