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
                <span class="truncate">Scan Barang Retur</span>
            </div>
            <div class="scan-step" :class="step === 2 ? 'active' : ''">
                <span class="scan-step-num">2</span>
                <span class="truncate">Input Qty Return</span>
            </div>
        </div>

        <div x-show="errorMsg" x-cloak class="alert alert-crit mb-3 flex items-center justify-between">
            <span x-text="errorMsg"></span>
            <button type="button" @click="errorMsg = ''" class="act-btn"><x-icon name="x" /></button>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span x-show="step === 1">Langkah 1 · Scan QR Barang Return to Supplier</span>
                    <span x-show="step === 2" x-cloak>Langkah 2 · Input Qty Return to Supplier</span>
                </div>
                <span class="pill pill-info">RM · KOMPONEN · TRANSIT (SUPPLIER)</span>
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
                            <div class="flex items-center gap-1.5">
                                <span class="pill pill-neu" x-text="scannedItem?.type"></span>
                                <span class="pill pill-info" x-text="scannedItem?.company_code"></span>
                            </div>
                            <div class="num text-xs font-bold text-accent mt-1" x-text="scannedItem?.part_no || '-'"></div>
                            <div class="text-xs font-semibold text-slate-900 break-words" x-text="scannedItem?.part_name"></div>
                        </div>
                        <div class="text-right whitespace-nowrap flex-none">
                            <div class="text-[11px] text-slate-500">Stok Tersedia</div>
                            <div class="num text-sm font-bold text-emerald-700" x-text="(scannedItem?.stock ?? 0) + ' ' + (scannedItem?.uom ?? '')"></div>
                        </div>
                    </div>

                    <form action="{{ route('return_to_suppliers.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="mode" value="scanner">
                        <input type="hidden" name="item_id" :value="scannedItem?.id">

                        <div>
                            <label class="lbl">Qty Return to Supplier (<span x-text="scannedItem?.uom"></span>) — Mendukung Kalkulator</label>
                            <input type="text" name="qty" x-model="rawQty" required
                                   placeholder="Contoh: 10"
                                   class="num w-full h-14 text-2xl text-center font-bold">
                            <div x-show="parsedQty > (scannedItem?.stock ?? 0)" class="text-xs text-rose-600 font-semibold mt-1">
                                Ditolak: Qty Return (<span class="num" x-text="parsedQty"></span>) melebihi Current Stock (<span class="num" x-text="scannedItem?.stock"></span>).
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-full"
                                :disabled="parsedQty <= 0 || parsedQty > (scannedItem?.stock ?? 0)">
                            Simpan Return to Supplier (- Stok)
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
                    <span class="count-chip"><span x-text="filteredHistory.length"></span><span>Scan</span></span>
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
                                <span class="pill pill-neu text-[10px]" x-text="s.supplier"></span>
                                <span class="num text-[11px] text-slate-500" x-text="s.date_fmt"></span>
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
                errorMsg: '',
                items: items,
                allScans: allScans,
                todayStr: todayStr,
                histDate: todayStr,
                histSearch: '',
                scannedItem: null,
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
                handleScan(rawCode) {
                    this.errorMsg = '';
                    const c = String(rawCode || '').trim().toUpperCase();
                    if (!c) return;
                    const found = this.items.find(i => i.part_no === c || i.part_name === c);
                    if (!found) {
                        this.errorMsg = `Ditolak: Kode "${rawCode}" tidak ditemukan di Master Item aktif.`;
                        return;
                    }
                    if (!['RM', 'KOMPONEN', 'TRANSIT'].includes(found.type) || found.company_type !== 'SUPPLIER') {
                        this.errorMsg = `Ditolak: ${found.part_name} (${found.type} - ${found.company_type}) tidak sah. Return to Supplier hanya menerima RM/KOMPONEN/TRANSIT dari SUPPLIER.`;
                        return;
                    }
                    if (found.stock <= 0) {
                        this.errorMsg = `Ditolak: Stok ${found.part_name} saat ini kosong (0 ${found.uom}).`;
                        return;
                    }
                    this.scannedItem = found;
                    this.manualCode = '';
                    this.step = 2;
                    window.DRCScanner.stop();
                    this.cameraActive = false;
                },
                get parsedQty() {
                    const clean = String(this.rawQty || '').replace(/,/g, '.').replace(/\s+/g, '');
                    if (!clean || !/^[0-9\.\+\-\*\/\(\)]+$/.test(clean)) return 0;
                    try {
                        const r = Function('return (' + clean + ')')();
                        return (typeof r === 'number' && isFinite(r) && r > 0) ? Math.round(r * 100) / 100 : 0;
                    } catch (e) {
                        return 0;
                    }
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
                    this.errorMsg = '';
                    this.step = 1;
                    if (window.DRCScanner && window.DRCScanner.isPermitted()) {
                        this.activateCamera();
                    }
                }
            };
        }
    </script>
</x-scanner-layout>