<x-scanner-layout :backUrl="route('production_ngs.index')">
    <x-slot name="header">Scanner Form NG (QC)</x-slot>

    @php
        $rawScans = $allScans ?? \App\Models\ProductionNg::with(['item.company', 'user'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $allScansList = $rawScans->map(function ($s) {
            $it = $s->item;
            return [
                'id'         => $s->id,
                'date'       => \Carbon\Carbon::parse($s->date_time)->format('Y-m-d'),
                'time_fmt'   => \Carbon\Carbon::parse($s->date_time)->format('d M Y, H:i'),
                'lot_number' => $s->lot_number,
                'part_no'    => $it->part_no ?: '-',
                'part_name'  => $it->part_name ?? '-',
                'uom'        => $it->uom ?? 'PCS',
                'reason'     => $s->problem_reason,
                'qty_ng'     => (float) $s->qty_ng,
                'user'       => $s->user->name ?? 'QC',
                'search'     => strtolower(($s->lot_number ?? '') . ' ' . ($it->part_no ?? '') . ' ' . ($it->part_name ?? '') . ' ' . ($s->problem_reason ?? '') . ' ' . floatval($s->qty_ng)),
            ];
        })->values();

        $itemsList = \App\Models\Item::with(['company', 'inventoryStock'])
            ->whereIn('type', ['FG', 'FG/KOMPONEN', 'KOMPONEN'])
            ->get()
            ->map(fn ($i) => [
                'id'        => $i->id,
                'part_no'   => $i->part_no ?: '',
                'part_name' => $i->part_name,
                'type'      => $i->type,
                'uom'       => $i->uom,
                'stock'     => (float) ($i->inventoryStock->current_stock ?? 0),
            ])->values();

        $lotsList = \App\Models\LotTracer::with('process')
            ->where('status_lot', 'ON PROGRESS')
            ->get()
            ->map(function ($lt) {
                $actFg = max(0, (float) $lt->actual_qty_prod - (float) $lt->qty_ng);
                return [
                    'id'             => $lt->id,
                    'lot_number'     => $lt->lot_number,
                    'target_item_id' => (int) $lt->target_item_id,
                    'process_name'   => $lt->process->process_name ?? '-',
                    'actual_prod'    => (float) $lt->actual_qty_prod,
                    'qty_ng'         => (float) $lt->qty_ng,
                    'act_fg'         => $actFg,
                ];
            })->filter(fn ($l) => $l['act_fg'] > 0)->values();
    @endphp

    <div x-data="productionNgScanner({{ Js::from($itemsList) }}, {{ Js::from($lotsList) }}, {{ Js::from($allScansList) }}, '{{ now()->format('Y-m-d') }}')"
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
                <span class="truncate">Pilih Lot &amp; NG</span>
            </div>
        </div>

        <div x-show="errMsg" x-cloak class="alert alert-crit">
            <div class="flex-1" x-text="errMsg"></div>
            <button type="button" @click="errMsg = ''" class="font-bold">✕</button>
        </div>

        <div class="panel panel-body space-y-3.5">
            <div class="flex items-center justify-between">
                <h3 class="panel-title" x-text="step === 1 ? 'Scan Barang Output (Temuan NG)' : 'Pilih Nomor Lot & Input Qty NG'"></h3>
                <span class="pill pill-crit">REJECT QC</span>
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
                    <label class="lbl">Input Manual Part No / Part Name</label>
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

            {{-- STEP 2: PILIH LOT AKTIF, PROBLEM REASON, & QTY NG --}}
            <div x-show="step === 2" x-cloak class="space-y-3.5">
                <div class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="num font-bold text-accent" x-text="selectedItem?.part_no || '-'"></span>
                        <span class="pill pill-neu" x-text="selectedItem?.type"></span>
                    </div>
                    <div class="font-bold text-slate-900 text-sm" x-text="selectedItem?.part_name"></div>
                    <div class="text-slate-600">Current Stock Gudang: <b class="num text-slate-900" x-text="(selectedItem?.stock ?? 0) + ' ' + (selectedItem?.uom ?? '')"></b></div>
                </div>

                <form action="{{ route('production_ngs.store') }}" method="POST" class="space-y-3.5">
                    @csrf
                    <input type="hidden" name="mode" value="scanner">
                    <input type="hidden" name="lot_tracer_id" :value="selectedLotObj?.id">

                    <div>
                        <label class="lbl">Cari &amp; Pilih Nomor Lot Aktif (Act FG &gt; 0)</label>
                        <input type="text" x-model="lotSearch"
                               placeholder="Ketik Nomor Lot..."
                               class="num uppercase w-full h-8 text-xs mb-1.5">
                        <select x-model="selectedLotId" required class="w-full h-10 text-xs num font-semibold">
                            <option value="">-- Pilih Nomor Lot --</option>
                            <template x-for="lt in itemLots" :key="lt.id">
                                <option :value="lt.id"
                                        x-text="lt.lot_number + ' · ' + lt.process_name + ' (Act FG: ' + lt.act_fg + ')'"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="selectedLotObj" class="p-2.5 rounded bg-rose-50/70 border border-rose-200 text-xs grid grid-cols-3 gap-2 text-center">
                        <div>Actual Prod:<br><b class="num" x-text="selectedLotObj?.actual_prod"></b></div>
                        <div>NG Tercatat:<br><b class="num text-rose-700" x-text="selectedLotObj?.qty_ng"></b></div>
                        <div>Maks Input (Act FG):<br><b class="num text-emerald-700" x-text="selectedLotObj?.act_fg"></b></div>
                    </div>

                    <div>
                        <label class="lbl">Problem Reason (Penyebab NG)</label>
                        <select name="problem_reason" required class="w-full h-10 text-xs font-semibold">
                            @foreach($reasons as $rs)
                                <option value="{{ $rs }}">{{ $rs }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="lbl">Qty NG (<span x-text="selectedItem?.uom"></span>)</label>
                        <input type="number" step="0.01" min="0.01"
                               :max="selectedLotObj ? selectedLotObj.act_fg : null"
                               name="qty_ng" x-model="qtyNg" required placeholder="0"
                               class="num w-full h-14 text-2xl text-center font-bold">
                    </div>

                    <button type="submit"
                            :disabled="!selectedLotObj || !qtyNg || parseFloat(qtyNg) <= 0 || parseFloat(qtyNg) > selectedLotObj.act_fg"
                            class="btn btn-primary btn-lg w-full">
                        Simpan Data NG (- Stok)
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
                    <span>Riwayat Pencatatan NG</span>
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
                       placeholder="Cari Lot / Part / Alasan..."
                       class="h-8 text-xs px-2.5 flex-1 min-w-0">
            </div>

            <div class="divide-y divide-slate-200 max-h-80 overflow-y-auto">
                <template x-for="row in filteredHistory" :key="row.id">
                    <div class="px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="pill pill-info num" x-text="row.lot_number"></span>
                                <span class="pill pill-crit" x-text="row.reason"></span>
                                <span class="text-[11px] text-slate-400 num" x-text="row.time_fmt"></span>
                            </div>
                            <div class="font-semibold text-slate-900 truncate mt-0.5" x-text="row.part_name"></div>
                            <div class="text-[11px] text-slate-500" x-text="'Petugas QC: ' + row.user"></div>
                        </div>
                        <div class="num font-bold text-rose-700 whitespace-nowrap"
                             x-text="'-' + row.qty_ng + ' ' + row.uom"></div>
                    </div>
                </template>
                <div x-show="filteredHistory.length === 0" class="empty">
                    Tidak ada riwayat NG pada tanggal/filter ini.
                </div>
            </div>
        </div>
    </div>

    <script>
        function productionNgScanner(itemsList, lotsList, scansList, todayStr) {
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
                selectedLotId: '',
                qtyNg: '',
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
                    return this.itemLots.find(l => String(l.id) === String(this.selectedLotId)) || null;
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
                        this.errMsg = `Ditolak: Item "${code}" tidak terdaftar atau bukan barang hasil produksi (FG / FG/KOMPONEN / KOMPONEN).`;
                        return;
                    }
                    const avail = this.lots.filter(l => l.target_item_id === found.id && l.act_fg > 0);
                    if (avail.length === 0) {
                        this.errMsg = `Ditolak: Tidak ada Nomor Lot berstatus ON PROGRESS yang sudah memiliki hasil produksi (Act FG > 0) untuk ${found.part_name}.`;
                        return;
                    }

                    window.DRCScanner.stop();
                    this.selectedItem = found;
                    this.selectedLotId = avail.length === 1 ? String(avail[0].id) : '';
                    this.qtyNg = '';
                    this.step = 2;
                },
                resetScan() {
                    this.selectedItem = null;
                    this.lotSearch = '';
                    this.selectedLotId = '';
                    this.qtyNg = '';
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