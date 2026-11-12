<x-scanner-layout>
    <x-slot name="header">Scanner Form NG (QC)</x-slot>

    @php
        $reasons = $reasons ?? ['MATERIAL', 'PROSES', 'DIES/TOOLING', 'SETTING/TRIAL', 'HANDLING/OPERATOR'];
        $recentScans = $recentScans ?? \App\Models\ProductionNg::with(['item.company', 'user'])
            ->whereDate('date_time', today())
            ->orderBy('id', 'desc')
            ->get();

        $validOutputItems = \App\Models\Item::with(['company', 'inventoryStock'])
            ->whereIn('type', ['FG', 'KOMPONEN', 'FG/KOMPONEN'])
            ->get()
            ->map(function ($i) {
                return [
                    'id'        => $i->id,
                    'part_no'   => strtoupper(trim((string) $i->part_no)),
                    'part_name' => strtoupper(trim((string) $i->part_name)),
                    'type'      => $i->type,
                    'uom'       => $i->uom,
                    'stock'     => $i->inventoryStock ? (float) $i->inventoryStock->current_stock : 0.0,
                ];
            })->values();

        $activeNgLots = \App\Models\LotTracer::with('process')
            ->where('status_lot', 'ON PROGRESS')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($l) {
                return [
                    'id'             => $l->id,
                    'lot_number'     => $l->lot_number,
                    'target_item_id' => $l->target_item_id,
                    'process_name'   => $l->process->process_name ?? '-',
                    'actual_prod'    => (float) $l->actual_qty_prod,
                    'qty_ng'         => (float) $l->qty_ng,
                    'act_fg'         => (float) $l->act_fg,
                ];
            })
            ->filter(fn($l) => $l['act_fg'] > 0)
            ->values();
    @endphp

    <div x-data="{
            items: {{ Js::from($validOutputItems) }},
            activeLots: {{ Js::from($activeNgLots) }},
            step: 1,
            manualCode: '',
            selectedItem: null,
            lotSearch: '',
            selectedLotId: '',
            problemReason: 'MATERIAL',
            qtyNg: '',
            errorMsg: '',
            scanner: null,

            showError(msg) {
                this.errorMsg = msg;
                setTimeout(() => { if (this.errorMsg === msg) this.errorMsg = ''; }, 4500);
            },
            verifyScan(rawCode) {
                let code = String(rawCode || '').trim().toUpperCase();
                if (!code) return;

                let found = this.items.find(i => i.part_no === code || i.part_name === code);
                if (!found) {
                    this.showError('Ditolak: Item tidak ditemukan atau bukan barang hasil produksi (FG / FG-KOMPONEN / KOMPONEN).');
                    return;
                }

                let lotsForItem = this.activeLots.filter(l => Number(l.target_item_id) === Number(found.id));
                if (lotsForItem.length === 0) {
                    this.showError('Ditolak: Tidak ada Lot ON PROGRESS dengan saldo Act FG > 0 untuk item ' + found.part_name + '.');
                    return;
                }

                this.selectedItem = found;
                this.selectedLotId = lotsForItem.length === 1 ? String(lotsForItem[0].id) : '';
                this.lotSearch = '';
                this.qtyNg = '';
                this.step = 2;
                this.errorMsg = '';
            },
            get matchingLots() {
                if (!this.selectedItem) return [];
                let q = this.lotSearch.trim().toUpperCase();
                return this.activeLots.filter(l => {
                    if (Number(l.target_item_id) !== Number(this.selectedItem.id)) return false;
                    if (q && !l.lot_number.toUpperCase().includes(q) && !l.process_name.toUpperCase().includes(q)) return false;
                    return true;
                });
            },
            get currentLotInfo() {
                if (!this.selectedLotId) return null;
                return this.activeLots.find(l => String(l.id) === String(this.selectedLotId)) || null;
            },
            get maxAllowedNg() {
                if (!this.currentLotInfo || !this.selectedItem) return 0;
                return Math.min(this.currentLotInfo.act_fg, this.selectedItem.stock);
            },
            resetScan() {
                this.step = 1;
                this.selectedItem = null;
                this.selectedLotId = '';
                this.lotSearch = '';
                this.qtyNg = '';
                this.manualCode = '';
                this.errorMsg = '';
            },
            init() {
                this.$nextTick(() => {
                    if (document.getElementById('qr-reader')) {
                        this.scanner = new Html5QrcodeScanner('qr-reader', { fps: 10, qrbox: { width: 220, height: 220 } }, false);
                        this.scanner.render((decodedText) => {
                            if (this.step === 1) {
                                this.verifyScan(decodedText);
                            }
                        }, () => {});
                    }
                });
            }
         }" class="space-y-4">

        <div x-show="errorMsg" x-cloak class="toast toast-crit" x-text="errorMsg"></div>

        {{-- STEP 1: SCAN BARANG OUTPUT --}}
        <div x-show="step === 1" class="panel panel-body space-y-3">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Langkah 1 · Scan Item Temuan NG</span>
                <span class="pill pill-info">FG · FG/KOMPONEN · KOMPONEN</span>
            </div>

            <div class="camera-box">
                <div id="qr-reader"></div>
            </div>

            <div class="flex gap-2">
                <input type="text" x-model="manualCode"
                       @keydown.enter.prevent="verifyScan(manualCode)"
                       oninput="this.value = this.value.toUpperCase()"
                       placeholder="Ketik Part No / Part Name..."
                       class="num flex-1 uppercase font-semibold">
                <button type="button" @click="verifyScan(manualCode)" class="btn btn-primary">Verifikasi</button>
            </div>
        </div>

        {{-- STEP 2: PILIH LOT, PROBLEM REASON & INPUT QTY NG --}}
        <div x-show="step === 2" x-cloak class="panel panel-body space-y-4">
            <div class="p-3 rounded border border-slate-200 bg-slate-50 flex items-center justify-between">
                <div>
                    <div class="num text-xs font-semibold text-accent" x-text="selectedItem?.part_no || selectedItem?.type"></div>
                    <div class="font-semibold text-slate-900 text-sm" x-text="selectedItem?.part_name"></div>
                </div>
                <div class="text-right">
                    <div class="text-[11px] text-slate-500">Stok Gudang Saat Ini</div>
                    <div class="num font-bold text-slate-900 text-sm" x-text="(selectedItem?.stock ?? 0) + ' ' + (selectedItem?.uom ?? '')"></div>
                </div>
            </div>

            <form action="{{ route('production_ngs.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="mode" value="scanner">
                <input type="hidden" name="lot_tracer_id" :value="selectedLotId">

                <div class="space-y-2">
                    <label class="lbl">Pilih Nomor Lot Aktif (Ketik untuk Mencari)</label>
                    <input type="text" x-model="lotSearch"
                           oninput="this.value = this.value.toUpperCase()"
                           placeholder="Ketik pencarian Nomor Lot..."
                           class="num w-full h-9 text-xs uppercase">

                    <div class="max-h-36 overflow-y-auto border border-slate-200 rounded divide-y divide-slate-200 bg-white">
                        <template x-for="lot in matchingLots" :key="lot.id">
                            <button type="button"
                                    @click="selectedLotId = String(lot.id)"
                                    :class="String(selectedLotId) === String(lot.id) ? 'bg-blue-50/80 border-l-4 border-accent' : 'hover:bg-slate-50'"
                                    class="w-full px-3 py-2 text-left flex items-center justify-between text-xs">
                                <div>
                                    <span class="num font-bold text-accent" x-text="lot.lot_number"></span>
                                    <span class="text-slate-500 ml-1" x-text="'· ' + lot.process_name"></span>
                                </div>
                                <span class="num font-semibold text-emerald-700"
                                      x-text="'Act FG: ' + lot.act_fg + ' ' + (selectedItem?.uom ?? '')"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div x-show="currentLotInfo" class="p-2.5 rounded border border-rose-200 bg-rose-50/60 grid grid-cols-3 gap-2 text-xs">
                    <div>
                        <span class="text-slate-500 block">Actual Prod:</span>
                        <b class="num text-slate-900" x-text="(currentLotInfo?.actual_prod ?? 0) + ' ' + (selectedItem?.uom ?? '')"></b>
                    </div>
                    <div>
                        <span class="text-slate-500 block">NG Tercatat:</span>
                        <b class="num text-rose-700" x-text="(currentLotInfo?.qty_ng ?? 0) + ' ' + (selectedItem?.uom ?? '')"></b>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Maks Input NG:</span>
                        <b class="num text-emerald-700" x-text="maxAllowedNg + ' ' + (selectedItem?.uom ?? '')"></b>
                    </div>
                </div>

                <div>
                    <label class="lbl">Problem Reason (Alasan NG)</label>
                    <select name="problem_reason" x-model="problemReason" required class="w-full h-11 font-semibold">
                        @foreach ($reasons as $r)
                            <option value="{{ $r }}">{{ $r }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="lbl">Qty NG</label>
                    <input type="number" step="0.01" min="0.01"
                           :max="maxAllowedNg > 0 ? maxAllowedNg : undefined"
                           name="qty_ng" x-model="qtyNg" required placeholder="0"
                           class="num w-full h-14 text-2xl text-center font-semibold">
                </div>

                <button type="submit"
                        :disabled="!selectedLotId || !qtyNg || parseFloat(qtyNg) <= 0 || parseFloat(qtyNg) > maxAllowedNg"
                        class="btn btn-primary btn-lg w-full">
                    Simpan Data NG (- Stok)
                </button>
            </form>

            <button type="button" @click="resetScan()" class="btn w-full">Scan Ulang Item</button>
        </div>

        {{-- RIWAYAT HARI INI --}}
        <div class="panel">
            <div class="panel-head">
                <h3 class="panel-title">Riwayat Production NG Hari Ini ({{ $recentScans->count() }})</h3>
                <a href="{{ route('production_ngs.index') }}" class="link-act">Mode Desktop</a>
            </div>
            <div class="max-h-72 overflow-y-auto divide-y divide-slate-200">
                @forelse ($recentScans as $scan)
                    <div class="px-4 py-2.5 flex items-center justify-between text-xs">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="num font-bold text-accent">{{ $scan->lot_number }}</span>
                                <span class="pill pill-crit">{{ $scan->problem_reason }}</span>
                                <span class="num text-slate-400">· {{ \Carbon\Carbon::parse($scan->date_time)->format('H:i') }}</span>
                                <span class="text-slate-500">· {{ $scan->user->name ?? 'QC' }}</span>
                            </div>
                            <div class="font-semibold text-slate-900 mt-0.5">{{ $scan->item->part_name ?? '-' }}</div>
                        </div>
                        <div class="num font-bold text-rose-700 text-sm whitespace-nowrap">
                            -{{ floatval($scan->qty_ng) }} {{ $scan->item->uom ?? '' }}
                        </div>
                    </div>
                @empty
                    <div class="empty py-6 text-xs">Belum ada pencatatan Production NG hari ini.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-scanner-layout>