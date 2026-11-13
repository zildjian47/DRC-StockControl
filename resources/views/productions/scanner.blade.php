<x-scanner-layout :backUrl="route('productions.index')">
    <x-slot name="header">Scanner Production</x-slot>

    @php
        $allScansList = ($allScans ?? $recentScans ?? collect())->map(function ($s) {
            $it = $s->item;
            return [
                'id'         => $s->id,
                'date'       => \Carbon\Carbon::parse($s->date_time)->format('Y-m-d'),
                'time_fmt'   => \Carbon\Carbon::parse($s->date_time)->format('d M Y, H:i'),
                'lot_number' => $s->lot_number,
                'part_no'    => $it->part_no ?: '-',
                'part_name'  => $it->part_name ?? '-',
                'uom'        => $it->uom ?? 'PCS',
                'qty'        => (float) $s->qty,
                'user'       => $s->user->name ?? 'Operator',
                'search'     => strtolower(($s->lot_number ?? '') . ' ' . ($it->part_no ?? '') . ' ' . ($it->part_name ?? '') . ' ' . floatval($s->qty) . ' ' . ($s->user->name ?? '')),
            ];
        })->values();

        $itemsJs = ($items ?? collect())->map(fn ($i) => [
            'id'           => $i->id,
            'part_no'      => $i->part_no ?: '',
            'part_name'    => $i->part_name,
            'type'         => $i->type,
            'uom'          => $i->uom,
            'company_type' => $i->company->type_company ?? 'INTERNAL',
            'stock'        => (float) ($i->inventoryStock->current_stock ?? 0),
        ])->values();

        $lotsJs = ($activeLots ?? collect())->map(fn ($lt) => [
            'id'             => $lt->id,
            'lot_number'     => $lt->lot_number,
            'target_item_id' => (int) $lt->target_item_id,
            'process_name'   => $lt->process->process_name ?? '-',
            'target_est'     => (float) $lt->target_qty_estimasi,
            'actual_prod'    => (float) $lt->actual_qty_prod,
            'qty_wip'        => (float) $lt->qty_wip,
        ])->values();
    @endphp

    <div x-data="productionScanner({{ Js::from($itemsJs) }}, {{ Js::from($lotsJs) }}, {{ Js::from($allScansList) }}, '{{ now()->format('Y-m-d') }}')"
         x-init="initScanner()"
         class="space-y-3.5">

        {{-- VISUAL STEPPER --}}
        <div class="scan-stepper">
            <div class="scan-step" :class="step === 1 ? 'active' : 'done'">
                <span class="scan-step-num" x-text="step > 1 ? '✓' : '1'"></span>
                <span class="truncate">Scan Output</span>
            </div>
            <div class="scan-step" :class="step === 2 ? 'active' : ''">
                <span class="scan-step-num">2</span>
                <span class="truncate">Pilih Lot &amp; Qty</span>
            </div>
        </div>

        <div x-show="errMsg" x-cloak class="alert alert-crit">
            <div class="flex-1" x-text="errMsg"></div>
            <button type="button" @click="errMsg = ''" class="font-bold">✕</button>
        </div>

        <div class="panel panel-body space-y-3.5">
            <div class="flex items-center justify-between">
                <h3 class="panel-title" x-text="step === 1 ? 'Scan Item Hasil Produksi' : 'Pilih Nomor Lot & Input Qty'"></h3>
                <span class="pill pill-info">FG · FG/KOMPONEN · KOMPONEN</span>
            </div>

            {{-- STEP 1: KAMERA PERSEGI & INPUT MANUAL --}}
            <div x-show="step === 1" class="space-y-3">
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
                    <label class="lbl">Input Manual Part No / Part Name Output</label>
                    <div class="flex gap-2">
                        <input type="text" x-model="manualCode"
                               @keydown.enter.prevent="verifyCode(manualCode)"
                               oninput="this.value = this.value.toUpperCase()"
                               placeholder="Ketik Part No atau Part Name..."
                               class="num uppercase flex-1 h-10 text-xs">
                        <button type="button" @click="verifyCode(manualCode)" class="btn btn-primary h-10 px-4">
                            Verifikasi
                        </button>
                    </div>
                </div>
            </div>

            {{-- STEP 2: PILIH LOT AKTIF & QTY PRODUCTION --}}
            <div x-show="step === 2" x-cloak class="space-y-3.5">
                <div class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="num font-bold text-accent" x-text="selectedItem?.part_no || '-'"></span>
                        <span class="pill pill-good" x-text="selectedItem?.type"></span>
                    </div>
                    <div class="font-bold text-slate-900 text-sm" x-text="selectedItem?.part_name"></div>
                    <div class="text-slate-600">Stok Gudang Saat Ini: <b class="num text-slate-900" x-text="(selectedItem?.stock ?? 0) + ' ' + (selectedItem?.uom ?? '')"></b></div>
                </div>

                <form action="{{ route('productions.store') }}" method="POST" class="space-y-3.5">
                    @csrf
                    <input type="hidden" name="mode" value="scanner">
                    <input type="hidden" name="item_id" :value="selectedItem?.id">

                    <div>
                        <label class="lbl">Cari &amp; Pilih Nomor Lot Aktif (WIP &gt; 0)</label>
                        <input type="text" x-model="lotSearch"
                               placeholder="Ketik Nomor Lot..."
                               class="num uppercase w-full h-8 text-xs mb-1.5">
                        <select name="lot_number" x-model="selectedLotNo" required class="w-full h-10 text-xs num font-semibold">
                            <option value="">-- Pilih Nomor Lot --</option>
                            <template x-for="lt in itemLots" :key="lt.id">
                                <option :value="lt.lot_number"
                                        x-text="lt.lot_number + ' · ' + lt.process_name + ' (Sisa WIP: ' + lt.qty_wip + ' ' + (selectedItem?.uom ?? '') + ')'"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="selectedLotObj" class="p-2.5 rounded bg-blue-50/70 border border-blue-200 text-xs grid grid-cols-3 gap-2 text-center">
                        <div>Target Est:<br><b class="num" x-text="selectedLotObj?.target_est"></b></div>
                        <div>Actual Prod:<br><b class="num text-emerald-700" x-text="selectedLotObj?.actual_prod"></b></div>
                        <div>Sisa WIP (Maks):<br><b class="num text-accent" x-text="selectedLotObj?.qty_wip"></b></div>
                    </div>

                    <div>
                        <label class="lbl">Qty Hasil Produksi (<span x-text="selectedItem?.uom"></span>)</label>
                        <input type="text" name="qty" x-model="qtyExpr" required
                               placeholder="0"
                               class="num w-full h-14 text-2xl text-center font-bold">
                        <p x-show="selectedLotObj && evalQty() > selectedLotObj.qty_wip" class="text-xs text-rose-600 font-semibold mt-1">
                            Ditolak: Qty Production melebihi sisa Qty WIP pada Lot ini (<span x-text="selectedLotObj?.qty_wip"></span>).
                        </p>
                    </div>

                    <button type="submit"
                            :disabled="!selectedLotObj || !evalQty() || evalQty() > selectedLotObj.qty_wip"
                            class="btn btn-primary btn-lg w-full">
                        Simpan Production (+ Stok)
                    </button>
                </form>

                <button type="button" @click="resetScan()" class="btn w-full">
                    Batal &amp; Scan Ulang
                </button>
            </div>
        </div>

        {{-- RIWAYAT SCAN DENGAN FILTER TANGGAL & SEARCH --}}
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Scan Production</span>
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
                            <div class="font-semibold text-slate-900 truncate mt-0.5" x-text="row.part_name"></div>
                            <div class="text-[11px] text-slate-500" x-text="'Petugas: ' + row.user"></div>
                        </div>
                        <div class="num font-bold text-emerald-700 whitespace-nowrap"
                             x-text="'+' + row.qty + ' ' + row.uom"></div>
                    </div>
                </template>
                <div x-show="filteredHistory.length === 0" class="empty">
                    Tidak ada riwayat scan pada tanggal/filter ini.
                </div>
            </div>
        </div>
    </div>

    <script>
        function productionScanner(itemsList, lotsList, scansList, todayStr) {
            return {
                items: itemsList,
                lots: lotsList,
                scans: scansList,
                todayStr: todayStr,
                step: 1,
                camPermitted: window.DRCScanner.isPermitted(),
                manualCode: '',
                selectedItem: null,
                lotSearch: '',
                selectedLotNo: '',
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
                get itemLots() {
                    if (!this.selectedItem) return [];
                    return this.lots.filter(l => {
                        if (l.target_item_id !== this.selectedItem.id) return false;
                        if (this.lotSearch && !l.lot_number.toLowerCase().includes(this.lotSearch.toLowerCase())) return false;
                        return true;
                    });
                },
                get selectedLotObj() {
                    return this.itemLots.find(l => l.lot_number === this.selectedLotNo) || null;
                },
                initScanner() {
                    if (this.camPermitted) {
                        this.$nextTick(() => this.startCamera());
                    }
                },
                async requestCamera() {
                    this.errMsg = '';
                    const ok = await window.DRCScanner.start('qr-reader', (txt) => this.verifyCode(txt), (msg) => { this.errMsg = msg; });
                    if (ok) this.camPermitted = true;
                },
                async startCamera() {
                    await window.DRCScanner.start('qr-reader', (txt) => this.verifyCode(txt), (msg) => { this.errMsg = msg; });
                },
                verifyCode(raw) {
                    this.errMsg = '';
                    const code = String(raw || '').trim().toUpperCase();
                    if (!code) return;

                    const found = this.items.find(i => i.part_no.toUpperCase() === code || i.part_name.toUpperCase() === code);
                    if (!found) {
                        this.errMsg = `Ditolak: Item "${code}" tidak terdaftar pada Master Item aktif.`;
                        return;
                    }
                    if (!['FG', 'KOMPONEN', 'FG/KOMPONEN'].includes(found.type) || !['INTERNAL', 'CUSTOMER'].includes(found.company_type)) {
                        this.errMsg = `Ditolak: Production hanya menerima item bertipe FG, FG/KOMPONEN, atau KOMPONEN (INTERNAL/CUSTOMER).`;
                        return;
                    }
                    const avail = this.lots.filter(l => l.target_item_id === found.id && l.qty_wip > 0);
                    if (avail.length === 0) {
                        this.errMsg = `Ditolak: Tidak ada Nomor Lot berstatus ON PROGRESS dengan sisa WIP > 0 untuk item ${found.part_name}.`;
                        return;
                    }

                    window.DRCScanner.stop();
                    this.selectedItem = found;
                    this.selectedLotNo = avail.length === 1 ? avail[0].lot_number : '';
                    this.qtyExpr = '';
                    this.step = 2;
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
                resetScan() {
                    this.selectedItem = null;
                    this.lotSearch = '';
                    this.selectedLotNo = '';
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