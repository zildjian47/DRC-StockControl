<x-scanner-layout :backUrl="route('return_to_suppliers.index')">
    <x-slot name="header">Scanner Return to Supplier</x-slot>

    @php
        $allScansData = ($allScans ?? collect())->map(function ($s) {
            $dt = \Carbon\Carbon::parse($s->date_time);
            return [
                'id'        => $s->id,
                'date'      => $dt->format('Y-m-d'),
                'time'      => $dt->format('H:i'),
                'date_fmt'  => $dt->format('d M Y, H:i'),
                'part_no'   => $s->item->part_no ?: '-',
                'part_name' => $s->item->part_name ?? '-',
                'uom'       => $s->item->uom ?? '',
                'supplier'  => $s->item->company->company_code ?? '-',
                'qty'       => (float) $s->qty,
                'user_name' => $s->user->name ?? 'Operator',
            ];
        })->values();

        $itemsPayload = $items->map(fn ($i) => [
            'id'           => $i->id,
            'part_no'      => $i->part_no ? strtoupper(trim($i->part_no)) : '',
            'part_name'    => strtoupper(trim($i->part_name)),
            'type'         => $i->type,
            'uom'          => $i->uom,
            'company_code' => $i->company->company_code ?? '-',
            'company_type' => $i->company->type_company ?? 'INTERNAL',
            'stock'        => $i->inventoryStock ? (float) $i->inventoryStock->current_stock : 0,
        ])->values();
    @endphp

    <div x-data="retSupScanner({{ Js::from($itemsPayload) }}, {{ Js::from($allScansData) }}, '{{ now()->format('Y-m-d') }}')"
         x-init="initScanner()">

        <div class="scan-stepper mb-3">
            <div class="scan-step" :class="step === 1 ? 'active' : 'done'">
                <span class="scan-step-num" x-text="step > 1 ? '✓' : '1'"></span>
                <span class="truncate">Scan Item</span>
            </div>
            <div class="scan-step" :class="step === 2 ? 'active' : ''">
                <span class="scan-step-num">2</span>
                <span class="truncate">Input Qty</span>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span x-show="step === 1">Langkah 1 · Scan Item Return</span>
                    <span x-show="step === 2" x-cloak>Langkah 2 · Input Qty</span>
                </div>
                <span class="pill pill-info">RM · KOMPONEN · TRANSIT</span>
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
                                   placeholder="Contoh: SHEET METAL SPHC 2.0MM atau KMP-NUT-M8"
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
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="ty" :class="tyClass(scannedItem?.type)" x-text="scannedItem?.type"></span>
                                <span class="text-xs font-semibold text-slate-600" x-text="scannedItem?.company_code"></span>
                            </div>
                            <div class="num text-xs font-bold text-accent mt-1" x-text="scannedItem?.part_no || '-'"></div>
                            <div class="text-xs font-semibold text-slate-900 break-words" x-text="scannedItem?.part_name"></div>
                        </div>
                        <div class="text-right whitespace-nowrap flex-none">
                            <div class="text-[11px] text-slate-500">Stok Tersedia</div>
                            <div class="num text-sm font-bold text-emerald-700" x-text="(scannedItem?.stock ?? 0) + ' ' + (scannedItem?.uom ?? '')"></div>
                        </div>
                    </div>

                    <form action="{{ route('return_to_suppliers.store') }}" method="POST" novalidate
                          @submit.prevent="submitForm($el)"
                          class="space-y-4">
                        @csrf
                        <input type="hidden" name="mode" value="scanner">
                        <input type="hidden" name="item_id" :value="scannedItem?.id">

                        <div>
                            <label class="lbl">Qty Return to Supplier (<span x-text="scannedItem?.uom"></span>)</label>
                            <div class="inp-calc">
                                <input type="text" name="qty" x-model="rawQty" required
                                       placeholder="Contoh: 10"
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

                        <div class="dlg-list" x-show="parsedQty > 0" x-cloak>
                            <div>
                                <span>Stok</span>
                                <b class="num"
                                   :class="parsedQty > (scannedItem?.stock || 0) ? 'text-rose-700' : 'text-slate-900'"
                                   x-text="(scannedItem?.stock ?? 0) + ' → ' + Math.round(((scannedItem?.stock || 0) - parsedQty) * 100) / 100 + ' ' + (scannedItem?.uom || '')"></b>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-full">
                            Simpan Return to Supplier
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
                    <span>Riwayat Scan Return to Supplier</span>
                    <span class="count-chip"><span x-text="filteredHistory.length"></span><span>SCAN</span></span>
                </div>
            </div>
            <div class="p-3 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center gap-2">
                <input type="date" x-model="histDate" class="h-8 text-xs px-2 w-36">
                <input type="text" x-model="histSearch" placeholder="Cari Part / Supplier / Qty..." class="h-8 text-xs px-2.5 flex-1 min-w-[140px]">
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
                                <span class="text-[11px] font-bold text-slate-700" x-text="s.supplier"></span>
                                <span class="num text-[11px] text-slate-500" x-text="'· ' + s.date_fmt"></span>
                                <span class="text-[11px] text-slate-500" x-text="'· ' + s.user_name"></span>
                            </div>
                            <div class="num font-bold text-accent mt-1" x-text="s.part_no"></div>
                            <div class="font-semibold text-slate-900 break-words" x-text="s.part_name"></div>
                        </div>
                        <div class="text-right whitespace-nowrap flex-none">
                            <span class="num text-sm font-bold text-rose-700" x-text="'-' + s.qty + ' ' + s.uom"></span>
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
        function retSupScanner(items, allScans, todayStr) {
            return {
                step: 1,
                cameraActive: false,
                manualCode: '',
                lastCamCode: '',
                lastCamAt: 0,
                items: items,
                allScans: allScans,
                todayStr: todayStr,
                histDate: todayStr,
                histSearch: '',
                scannedItem: null,
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
                handleScan(rawCode, fromCamera = false) {
                    const c = String(rawCode || '').trim().toUpperCase();
                    if (!c) return;
                    if (fromCamera) {
                        const now = Date.now();
                        if (this.lastCamCode === c && (now - this.lastCamAt) < 900) return;
                        this.lastCamCode = c;
                        this.lastCamAt = now;
                    }
                    const found = this.items.find(i => i.part_no === c || i.part_name === c);
                    if (!found) {
                        this.notifyError(`Kode "${rawCode}" tidak ditemukan.`);
                        return;
                    }
                    if (!['RM', 'KOMPONEN', 'TRANSIT'].includes(found.type) || found.company_type !== 'SUPPLIER') {
                        this.notifyError(`${found.part_name} tidak sesuai untuk Return to Supplier (wajib RM, KOMPONEN, atau TRANSIT dari SUPPLIER).`);
                        return;
                    }
                    if (found.stock <= 0) {
                        this.notifyError(`Stok ${found.part_name} kosong (0 ${found.uom}).`);
                        return;
                    }
                    this.clearToast();
                    this.scannedItem = found;
                    this.manualCode = '';
                    this.lastCamCode = '';
                    this.lastCamAt = 0;
                    this.step = 2;
                    window.DRCScanner.stop();
                    this.cameraActive = false;
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
                    if (!this.scannedItem) {
                        this.notifyError('Scan item terlebih dahulu.');
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
                    if (val > (this.scannedItem.stock || 0) + 0.0001) {
                        this.notifyError(`Stok kurang. Tersedia: ${this.scannedItem.stock} ${this.scannedItem.uom}.`);
                        return;
                    }
                    formEl.submit();
                },
                get filteredHistory() {
                    return this.allScans.filter(s => {
                        if (this.histDate && s.date !== this.histDate) return false;
                        if (this.histSearch) {
                            const q = this.histSearch.toLowerCase();
                            const hay = `${s.part_no} ${s.part_name} ${s.supplier} ${s.qty} ${s.user_name}`.toLowerCase();
                            if (!hay.includes(q)) return false;
                        }
                        return true;
                    });
                },
                resetAll() {
                    this.scannedItem = null;
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