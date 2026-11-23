<x-scanner-layout :backUrl="route('production_ngs.index')">
    <x-slot name="header">Scanner Form Production NG</x-slot>

    <div x-data="ngScanner({{ Js::from(route('production_ngs.lookup')) }}, {{ Js::from($allScansData ?? []) }}, '{{ now()->format('Y-m-d') }}', {{ Js::from($reasons[0] ?? 'MATERIAL') }})"
         x-init="initScanner()"
         @pageshow.window="isSubmitting = false">

        <div class="scan-stepper mb-3">
            <div class="scan-step" :class="step === 1 ? 'active' : 'done'">
                <span class="scan-step-num" x-text="step > 1 ? '✓' : '1'"></span>
                <span class="truncate">Scan Output</span>
            </div>
            <div class="scan-step" :class="step === 2 ? 'active' : ''">
                <span class="scan-step-num">2</span>
                <span class="truncate">Lot &amp; Qty NG</span>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span x-show="step === 1">Langkah 1 · Scan Output NG</span>
                    <span x-show="step === 2" x-cloak>Langkah 2 · Lot &amp; Qty NG</span>
                </div>
                <span class="pill pill-crit">REJECT QC</span>
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
                                   placeholder="Contoh: FGK-PLT-001 atau FG-BKT-001"
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

                    <form action="{{ route('production_ngs.store') }}" method="POST" novalidate
                          @submit.prevent="submitForm($el)"
                          class="space-y-4">
                        @csrf
                        <input type="hidden" name="mode" value="scanner">
                        <input type="hidden" name="item_id" :value="scannedItem?.id">

                        <div>
                            <label class="lbl">Nomor Lot Aktif</label>
                            <select name="lot_tracer_id" x-model="selectedLotId" required class="w-full h-11 text-xs font-semibold">
                                <option value="">-- Pilih Nomor Lot --</option>
                                <template x-for="lt in itemLots" :key="lt.id">
                                    <option :value="lt.id"
                                            x-text="lt.lot_number + ' (' + lt.process_name + ') · Act FG: ' + lt.act_fg + ' ' + lt.uom"></option>
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
                                <div class="text-[11px] text-slate-500">Act FG</div>
                                <div class="num font-bold text-emerald-700" x-text="(selectedLotObj?.act_fg ?? 0) + ' ' + (scannedItem?.uom || '')"></div>
                            </div>
                        </div>

                        <div>
                            <label class="lbl">Problem Reason</label>
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

                        <div class="dlg-list" x-show="selectedLotObj && parsedQtyNg > 0" x-cloak>
                            <div>
                                <span>Act FG</span>
                                <b class="num"
                                   :class="parsedQtyNg > (selectedLotObj?.act_fg || 0) ? 'text-rose-700' : 'text-slate-900'"
                                   x-text="(selectedLotObj?.act_fg ?? 0) + ' → ' + Math.round(((selectedLotObj?.act_fg || 0) - parsedQtyNg) * 100) / 100 + ' ' + (scannedItem?.uom || '')"></b>
                            </div>
                            <div>
                                <span>Stok</span>
                                <b class="num"
                                   :class="parsedQtyNg > (scannedItem?.stock || 0) ? 'text-rose-700' : 'text-slate-900'"
                                   x-text="(scannedItem?.stock ?? 0) + ' → ' + Math.round(((scannedItem?.stock || 0) - parsedQtyNg) * 100) / 100 + ' ' + (scannedItem?.uom || '')"></b>
                            </div>
                        </div>

                        <button type="submit" :disabled="isSubmitting" class="btn btn-primary btn-lg w-full">
                            <span x-text="isSubmitting ? 'Menyimpan...' : 'Simpan Data NG'"></span>
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
                    <span>Riwayat Pencatatan NG</span>
                    <span class="count-chip"><span x-text="filteredHistory.length"></span><span>SCAN</span></span>
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
                                <span class="pill pill-info num" x-text="s.lot_number"></span>
                                <span class="pill pill-crit" x-text="s.reason"></span>
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
        function ngScanner(lookupUrl, allScans, todayStr, defaultReason) {
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
                selectedLotId: '',
                problemReason: defaultReason || 'MATERIAL',
                qtyNg: '',

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
                        const url = `${this.lookupUrl}?code=${encodeURIComponent(c)}`;
                        const res = await fetch(url, {
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

                        this.clearToast();
                        this.scannedItem = data.item;
                        this.itemLots = Array.isArray(data.lots) ? data.lots : [];
                        this.selectedLotId = this.itemLots.length === 1 ? String(this.itemLots[0].id) : '';
                        this.manualCode = '';
                        this.lastCamCode = '';
                        this.lastCamAt = 0;
                        this.step = 2;
                        window.DRCScanner.stop();
                        this.cameraActive = false;
                    } catch (err) {
                        this.notifyError((err && err.message) ? err.message : 'Gagal menghubungi server. Periksa koneksi jaringan.');
                    } finally {
                        this.isLookingUp = false;
                    }
                },
                get selectedLotObj() {
                    return this.itemLots.find(l => String(l.id) === String(this.selectedLotId)) || null;
                },
                get parsedQtyNg() {
                    const q = parseFloat(this.qtyNg);
                    return (!isNaN(q) && q > 0) ? Math.round(q * 100) / 100 : 0;
                },
                submitForm(formEl) {
                    if (this.isSubmitting) return;
                    if (!this.scannedItem) {
                        this.notifyError('Scan output terlebih dahulu.');
                        return;
                    }
                    if (!this.selectedLotId || !this.selectedLotObj) {
                        this.notifyError('Pilih Nomor Lot terlebih dahulu.');
                        return;
                    }
                    if (!this.problemReason) {
                        this.notifyError('Pilih Problem Reason terlebih dahulu.');
                        return;
                    }
                    const qty = parseFloat(this.qtyNg);
                    if (this.qtyNg === '' || this.qtyNg === null || isNaN(qty) || qty <= 0) {
                        this.notifyError('Qty NG harus lebih dari 0.');
                        return;
                    }
                    if (qty > this.selectedLotObj.act_fg + 0.0001) {
                        this.notifyError(`Qty NG melebihi saldo Act FG (maksimal ${this.selectedLotObj.act_fg} ${this.scannedItem.uom}).`);
                        return;
                    }
                    if (qty > (this.scannedItem.stock || 0) + 0.0001) {
                        this.notifyError(`Stok gudang kurang. Tersedia: ${this.scannedItem.stock} ${this.scannedItem.uom}.`);
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
                            const hay = `${s.lot_number} ${s.part_no} ${s.part_name} ${s.reason} ${s.qty_ng} ${s.user_name}`.toLowerCase();
                            if (!hay.includes(q)) return false;
                        }
                        return true;
                    });
                },
                resetAll() {
                    this.isSubmitting = false;
                    this.scannedItem = null;
                    this.itemLots = [];
                    this.selectedLotId = '';
                    this.qtyNg = '';
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