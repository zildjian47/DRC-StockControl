<x-scanner-layout :backUrl="route('stock_adjustments.index')">
    <x-slot name="header">Scanner Stock Adjustment</x-slot>

    @php
        $allScansData = \App\Models\StockAdjustment::with(['item.company', 'user'])
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
                    'part_no'    => $s->item->part_no ?: '-',
                    'part_name'  => $s->item->part_name ?? '-',
                    'uom'        => $s->item->uom ?? '',
                    'adj_type'   => $s->adjustment_type,
                    'qty_actual' => (float) $s->qty_actual,
                    'qty_diff'   => (float) $s->qty_diff,
                    'user_name'  => $s->user->name ?? 'PPIC',
                ];
            })->values();

        $itemsPayload = $items->map(fn ($i) => [
            'id'           => $i->id,
            'part_no'      => $i->part_no ? strtoupper(trim($i->part_no)) : '',
            'part_name'    => strtoupper(trim($i->part_name)),
            'type'         => $i->type,
            'uom'          => $i->uom,
            'company_code' => $i->company->company_code ?? 'INTERNAL',
            'stock'        => $i->inventoryStock ? (float) $i->inventoryStock->current_stock : 0,
        ])->values();
    @endphp

    <div x-data="adjScanner({{ Js::from($itemsPayload) }}, {{ Js::from($allScansData) }}, '{{ now()->format('Y-m-d') }}')"
         x-init="initScanner()">

        <div class="scan-stepper mb-3">
            <div class="scan-step" :class="step === 1 ? 'active' : 'done'">
                <span class="scan-step-num" x-text="step > 1 ? '✓' : '1'"></span>
                <span class="truncate">Scan Item</span>
            </div>
            <div class="scan-step" :class="step === 2 ? 'active' : ''">
                <span class="scan-step-num">2</span>
                <span class="truncate">Input Adjustment</span>
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
                    <span x-show="step === 1">Langkah 1 · Scan Item Adjustment</span>
                    <span x-show="step === 2" x-cloak>Langkah 2 · Input Adjustment</span>
                </div>
                <span class="pill pill-info">SEMUA TIPE ITEM</span>
            </div>

            <div class="panel-body space-y-4">
                {{-- STEP 1: SCAN QR ITEM --}}
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
                                   placeholder="Contoh: FG-BKT-001 atau SHEET METAL SPHC 2.0MM"
                                   class="num uppercase flex-1 h-10 text-xs font-semibold">
                            <button type="button" @click="handleScan(manualCode)" class="btn btn-primary px-4 h-10">
                                Cek Kode
                            </button>
                        </div>
                    </div>
                </div>

                {{-- STEP 2: INPUT ADJUSTMENT --}}
                <div x-show="step === 2" x-cloak class="space-y-4">
                    <div class="p-3 rounded bg-slate-50 border border-slate-200 flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="ty" :class="tyClass(selectedItem?.type)" x-text="selectedItem?.type"></span>
                                <span class="text-xs font-semibold text-slate-600" x-text="selectedItem?.company_code"></span>
                            </div>
                            <div class="num text-xs font-bold text-accent mt-1" x-text="selectedItem?.part_no || '-'"></div>
                            <div class="text-xs font-semibold text-slate-900 break-words" x-text="selectedItem?.part_name"></div>
                        </div>
                        <div class="text-right whitespace-nowrap flex-none">
                            <div class="text-[11px] text-slate-500">Stok Sistem</div>
                            <div class="num text-sm font-bold text-slate-900" x-text="qtySystem + ' ' + (selectedItem?.uom ?? '')"></div>
                        </div>
                    </div>

                    <form action="{{ route('stock_adjustments.store') }}" method="POST" novalidate
                          @submit.prevent="submitForm($el)"
                          class="space-y-4">
                        @csrf
                        <input type="hidden" name="mode" value="scanner">
                        <input type="hidden" name="item_id" :value="selectedItem?.id">

                        <div x-show="formError" x-cloak class="alert alert-crit text-xs" role="alert">
                            <x-icon name="alert" />
                            <span class="flex-1 min-w-0" x-text="formError"></span>
                        </div>

                        <div>
                            <span class="lbl">Kategori Adjustment</span>
                            <div class="opt-chips">
                                @foreach($types as $t)
                                    <label>
                                        <input type="radio" name="adjustment_type" value="{{ $t }}" x-model="adjType" required>
                                        <span>{{ $t }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="lbl" for="sc_adj_actual">Qty Actual / Fisik</label>
                                <div class="inp-calc">
                                    <input id="sc_adj_actual" type="text" name="qty_actual" x-model="rawActual" @input="onInputActual()"
                                           placeholder="Contoh: 120 atau 3*40" class="num w-full h-11 font-semibold">
                                    <span class="inp-calc-ic" title="Mendukung kalkulator (+, -, *, /)">
                                        <x-icon name="calc" />
                                    </span>
                                </div>
                                <div class="calc-preview" x-show="String(rawActual || '').trim() !== '' && evalCalc(rawActual) !== null" x-cloak>
                                    Hasil: <b x-text="evalCalc(rawActual)"></b> <span x-text="selectedItem?.uom || ''"></span>
                                </div>
                                <div class="calc-err" x-show="String(rawActual || '').trim() !== '' && evalCalc(rawActual) === null" x-cloak>
                                    <x-icon name="alert" />
                                    <span>Format perhitungan tidak valid</span>
                                </div>
                            </div>
                            <div>
                                <label class="lbl" for="sc_adj_diff">Qty Diff / Selisih (+/-)</label>
                                <div class="inp-calc">
                                    <input id="sc_adj_diff" type="text" name="qty_diff" x-model="rawDiff" @input="onInputDiff()"
                                           placeholder="Contoh: -5 atau +10" class="num w-full h-11 font-semibold">
                                    <span class="inp-calc-ic" title="Mendukung kalkulator (+, -, *, /)">
                                        <x-icon name="calc" />
                                    </span>
                                </div>
                                <div class="calc-preview" x-show="String(rawDiff || '').trim() !== '' && evalCalc(rawDiff) !== null" x-cloak>
                                    Hasil: <b x-text="(evalCalc(rawDiff) > 0 ? '+' : '') + evalCalc(rawDiff)"></b> <span x-text="selectedItem?.uom || ''"></span>
                                </div>
                                <div class="calc-err" x-show="String(rawDiff || '').trim() !== '' && evalCalc(rawDiff) === null" x-cloak>
                                    <x-icon name="alert" />
                                    <span>Format perhitungan tidak valid</span>
                                </div>
                            </div>
                        </div>

                        <div class="dlg-list">
                            <div>
                                <span>Stok</span>
                                <b class="num"
                                   :class="resultDiff > 0 ? 'text-emerald-700' : (resultDiff < 0 ? 'text-rose-700' : 'text-slate-800')"
                                   x-text="qtySystem + ' → ' + resultActual + ' ' + (selectedItem?.uom || '') + ' (' + (resultDiff > 0 ? '+' : '') + resultDiff + ')'"></b>
                            </div>
                        </div>

                        <div>
                            <label class="lbl" for="sc_adj_notes">Catatan (Opsional)</label>
                            <input id="sc_adj_notes" type="text" name="notes" placeholder="Keterangan penyesuaian..." class="w-full h-10 text-xs">
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-full">Simpan Stock Adjustment</button>
                    </form>

                    <button type="button" class="btn w-full" @click="resetAll()">Batal &amp; Scan Ulang</button>
                </div>
            </div>
        </div>

        {{-- PANEL RIWAYAT SCAN --}}
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Scan Stock Adjustment</span>
                    <span class="count-chip"><span x-text="filteredHistory.length"></span><span>SCAN</span></span>
                </div>
            </div>
            <div class="p-3 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center gap-2">
                <input type="date" x-model="histDate" class="h-8 text-xs px-2 w-36">
                <input type="text" x-model="histSearch" placeholder="Cari Part / Kategori / Qty..." class="h-8 text-xs px-2.5 flex-1 min-w-[140px]">
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
                                <span class="pill"
                                      :class="s.adj_type === 'LOSS' ? 'pill-crit' : (['SURPLUS', 'FOUND', 'REPAIR'].includes(s.adj_type) ? 'pill-good' : 'pill-info')"
                                      x-text="s.adj_type"></span>
                                <span class="num text-[11px] text-slate-500" x-text="s.date_fmt"></span>
                                <span class="text-[11px] text-slate-500" x-text="'· ' + s.user_name"></span>
                            </div>
                            <div class="num font-bold text-accent mt-1" x-text="s.part_no"></div>
                            <div class="font-semibold text-slate-900 break-words" x-text="s.part_name"></div>
                        </div>
                        <div class="text-right num whitespace-nowrap flex-none">
                            <div class="text-sm font-bold"
                                 :class="s.qty_diff >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                 x-text="(s.qty_diff > 0 ? '+' : '') + s.qty_diff + ' ' + s.uom"></div>
                            <div class="text-[11px] text-slate-500" x-text="'Actual: ' + s.qty_actual"></div>
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
        function adjScanner(items, allScans, todayStr) {
            return {
                step: 1,
                cameraActive: false,
                manualCode: '',
                errorMsg: '',
                formError: '',
                items: items,
                allScans: allScans,
                todayStr: todayStr,
                histDate: todayStr,
                histSearch: '',
                selectedItem: null,
                adjType: 'STOCK OPNAME',
                qtySystem: 0,
                rawActual: '',
                rawDiff: '',

                tyClass(t) {
                    return 'ty-' + (t === 'FG/KOMPONEN' ? 'FGK' : (t || 'RM'));
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
                    const code = String(rawCode || '').trim().toUpperCase();
                    if (!code) return;

                    const found = this.items.find(i => i.part_no === code || i.part_name === code);
                    if (!found) {
                        this.errorMsg = `Kode "${rawCode}" tidak ditemukan.`;
                        return;
                    }

                    this.selectedItem = found;
                    this.qtySystem = found.stock;
                    this.rawActual = String(found.stock);
                    this.rawDiff = '0';
                    this.manualCode = '';
                    this.step = 2;
                    window.DRCScanner.stop();
                    this.cameraActive = false;
                },
                evalCalc(raw) {
                    if (raw === null || raw === undefined) return null;
                    const expr = String(raw).replace(/,/g, '.').replace(/[xX]/g, '*').replace(/:/g, '/').replace(/\s+/g, '');
                    if (!expr || !/^[0-9.+\-*\/()]+$/.test(expr) || /[\+\-\*\/]{2,}/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r)) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                get resultActual() {
                    const a = this.evalCalc(this.rawActual);
                    return a === null ? this.qtySystem : a;
                },
                get resultDiff() {
                    const d = this.evalCalc(this.rawDiff);
                    return d === null ? 0 : d;
                },
                onInputActual() {
                    const val = this.evalCalc(this.rawActual);
                    if (val !== null) {
                        const diff = Math.round((val - this.qtySystem) * 100) / 100;
                        this.rawDiff = (diff > 0 ? '+' : '') + diff;
                    }
                },
                onInputDiff() {
                    const val = this.evalCalc(this.rawDiff);
                    if (val !== null) {
                        const act = Math.round((this.qtySystem + val) * 100) / 100;
                        this.rawActual = String(act);
                    }
                },
                submitForm(formEl) {
                    this.formError = '';
                    if (!this.selectedItem) {
                        this.formError = 'Scan item terlebih dahulu.';
                        return;
                    }
                    if (!this.adjType) {
                        this.formError = 'Pilih kategori adjustment terlebih dahulu.';
                        return;
                    }

                    const hasActual = String(this.rawActual || '').trim() !== '';
                    const hasDiff = String(this.rawDiff || '').trim() !== '';
                    if (!hasActual && !hasDiff) {
                        this.formError = 'Qty Actual atau Qty Diff wajib diisi.';
                        return;
                    }

                    const parsedActual = hasActual ? this.evalCalc(this.rawActual) : null;
                    const parsedDiff = hasDiff ? this.evalCalc(this.rawDiff) : null;

                    if (hasActual && parsedActual === null) {
                        this.formError = 'Format perhitungan Qty Actual tidak valid.';
                        return;
                    }
                    if (hasDiff && parsedDiff === null) {
                        this.formError = 'Format perhitungan Qty Diff tidak valid.';
                        return;
                    }

                    let qtyActual, qtyDiff;
                    if (parsedActual !== null) {
                        qtyActual = parsedActual;
                        qtyDiff = Math.round((qtyActual - this.qtySystem) * 100) / 100;
                    } else {
                        qtyDiff = parsedDiff;
                        qtyActual = Math.round((this.qtySystem + qtyDiff) * 100) / 100;
                    }

                    if (qtyActual < 0) {
                        this.formError = 'Qty Actual tidak boleh minus.';
                        return;
                    }
                    if (this.adjType === 'LOSS' && qtyActual >= this.qtySystem) {
                        this.formError = `Kategori LOSS: Qty Actual harus lebih kecil dari Qty System (${this.qtySystem}).`;
                        return;
                    }
                    if (['SURPLUS', 'FOUND', 'REPAIR'].includes(this.adjType) && qtyActual <= this.qtySystem) {
                        this.formError = `Kategori ${this.adjType}: Qty Actual harus lebih besar dari Qty System (${this.qtySystem}).`;
                        return;
                    }
                    if (this.adjType === 'STOCK OPNAME' && Math.abs(qtyDiff) < 0.0001) {
                        this.formError = 'Tidak ada perubahan stok (selisih 0).';
                        return;
                    }

                    formEl.submit();
                },
                get filteredHistory() {
                    return this.allScans.filter(s => {
                        if (this.histDate && s.date !== this.histDate) return false;
                        if (this.histSearch) {
                            const q = this.histSearch.toLowerCase();
                            const hay = `${s.part_no} ${s.part_name} ${s.adj_type} ${s.qty_diff} ${s.qty_actual} ${s.user_name}`.toLowerCase();
                            if (!hay.includes(q)) return false;
                        }
                        return true;
                    });
                },
                resetAll() {
                    this.selectedItem = null;
                    this.adjType = 'STOCK OPNAME';
                    this.qtySystem = 0;
                    this.rawActual = '';
                    this.rawDiff = '';
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