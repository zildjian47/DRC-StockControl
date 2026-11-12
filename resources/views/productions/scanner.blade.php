<x-scanner-layout header="Scanner Form NG (QC)" :backUrl="route('production_ngs.index')">
    @php
        $itemsData = ($items ?? \App\Models\Item::with(['company', 'inventoryStock'])->get())->map(fn ($i) => [
            'id'        => $i->id,
            'part_no'   => $i->part_no ?: '',
            'part_name' => $i->part_name,
            'type'      => $i->type,
            'uom'       => $i->uom,
            'stock'     => (float) ($i->inventoryStock->current_stock ?? 0),
        ])->values();

        $lotsData = ($activeLots ?? \App\Models\LotTracer::with('targetItem')->where('status_lot', 'ON PROGRESS')->get())
            ->filter(fn ($l) => ((float) $l->actual_qty_prod - (float) $l->qty_ng) > 0)
            ->map(fn ($l) => [
                'lot_number'     => $l->lot_number,
                'target_item_id' => $l->target_item_id,
                'actual_prod'    => (float) $l->actual_qty_prod,
                'qty_ng'         => (float) $l->qty_ng,
                'act_fg'         => max(0, (float) $l->actual_qty_prod - (float) $l->qty_ng),
                'uom'            => $l->targetItem->uom ?? 'PCS',
            ])->values();
    @endphp

    <div class="panel panel-body"
         x-data="{
            step: 1,
            manualInput: '',
            items: {{ Js::from($itemsData) }},
            activeLots: {{ Js::from($lotsData) }},
            selectedItem: null,
            selectedLotNo: '',
            lotSearch: '',
            lotDropdown: false,
            problemReason: 'MATERIAL',
            qtyNg: '',
            scanner: null,

            init() {
                this.$nextTick(() => this.startCamera());
            },
            startCamera() {
                const el = document.getElementById('qr-reader');
                if (!el || typeof Html5QrcodeScanner === 'undefined') return;
                try {
                    if (this.scanner) this.scanner.clear();
                    this.scanner = new Html5QrcodeScanner('qr-reader', { fps: 10, qrbox: { width: 220, height: 220 } }, false);
                    this.scanner.render((decodedText) => {
                        this.handleScan(decodedText);
                    }, () => {});
                } catch (e) {}
            },
            findItem(code) {
                const q = String(code || '').trim().toLowerCase();
                if (!q) return null;
                return this.items.find(i => i.part_no.toLowerCase() === q || i.part_name.toLowerCase() === q) || null;
            },
            handleScan(rawCode) {
                const item = this.findItem(rawCode);
                if (!item) {
                    window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Ditolak: Part No / Part Name tidak ditemukan.' } }));
                    return;
                }
                const matching = this.activeLots.filter(l => Number(l.target_item_id) === Number(item.id) && l.act_fg > 0);
                if (matching.length === 0) {
                    window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Ditolak: Tidak ada Lot ON PROGRESS dengan saldo Act FG > 0 untuk part ini.' } }));
                    return;
                }
                this.selectedItem = item;
                this.manualInput = '';
                if (matching.length === 1) {
                    this.selectedLotNo = matching[0].lot_number;
                    this.lotSearch = matching[0].lot_number;
                }
                this.step = 2;
                window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Output terverifikasi. Pilih Lot & isi Qty NG.', type: 'good' } }));
            },
            get filteredLots() {
                if (!this.selectedItem) return [];
                return this.activeLots.filter(l =>
                    Number(l.target_item_id) === Number(this.selectedItem.id) &&
                    l.act_fg > 0 &&
                    (!this.lotSearch || l.lot_number.toLowerCase().includes(this.lotSearch.toLowerCase()))
                );
            },
            get currentLotObj() {
                return this.activeLots.find(l =>
                    l.lot_number === this.selectedLotNo &&
                    Number(l.target_item_id) === Number(this.selectedItem?.id)
                ) || null;
            },
            resetAll() {
                this.step = 1;
                this.selectedItem = null;
                this.selectedLotNo = '';
                this.lotSearch = '';
                this.qtyNg = '';
                this.manualInput = '';
            }
         }">

        {{-- VISUAL STEPPER 2 LANGKAH --}}
        <div class="stepper">
            <div class="step-item">
                <span class="step-circle"
                      :class="step === 1 ? 'step-circle-active' : 'step-circle-done'"
                      x-text="step > 1 ? '✓' : '1'"></span>
                <span class="step-label"
                      :class="step === 1 ? 'step-label-active' : 'step-label-done'">Scan Output</span>
            </div>

            <div class="step-line" :class="step > 1 ? 'step-line-done' : ''"></div>

            <div class="step-item">
                <span class="step-circle"
                      :class="step === 2 ? 'step-circle-active' : 'step-circle-idle'">2</span>
                <span class="step-label"
                      :class="step === 2 ? 'step-label-active' : 'step-label-idle'">Lot &amp; Qty NG</span>
            </div>
        </div>

        {{-- STEP 1 --}}
        <div x-show="step === 1" class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-accent">Scan Output</span>
                <span class="pill pill-crit text-[10.5px]">Pencatatan Reject / NG QC</span>
            </div>

            <div class="camera-box">
                <div id="qr-reader"></div>
            </div>

            <div class="flex gap-2">
                <input type="text" x-model="manualInput"
                       @keydown.enter.prevent="handleScan(manualInput)"
                       placeholder="Ketik Part No / Nama Output..."
                       class="num flex-1 h-10 text-xs">
                <button type="button" @click="handleScan(manualInput)" class="btn btn-primary px-4 h-10">
                    Verifikasi
                </button>
            </div>
        </div>

        {{-- STEP 2 --}}
        <div x-show="step === 2" x-cloak class="space-y-4">
            <div class="p-3 rounded bg-slate-50 border border-slate-200 flex items-center justify-between text-xs">
                <div>
                    <div class="num font-semibold text-accent" x-text="selectedItem?.part_no || '-'"></div>
                    <div class="font-bold text-slate-900" x-text="selectedItem?.part_name"></div>
                </div>
                <span class="pill pill-neu" x-text="selectedItem?.type"></span>
            </div>

            <form action="{{ route('production_ngs.store') }}" method="POST" class="space-y-3.5">
                @csrf
                <input type="hidden" name="mode" value="scanner">
                <input type="hidden" name="item_id" :value="selectedItem?.id">
                <input type="hidden" name="lot_number" :value="selectedLotNo" required>

                <div class="relative" @click.outside="lotDropdown = false">
                    <label class="lbl">Pilih Nomor Lot (Ketik &amp; Pilih)</label>
                    <input type="text" x-model="lotSearch"
                           @focus="lotDropdown = true"
                           @input="lotDropdown = true; selectedLotNo = ''"
                           placeholder="Ketik Nomor Lot..."
                           class="num uppercase w-full h-11 text-sm font-bold">
                    <div x-show="lotDropdown" x-cloak
                         class="absolute z-30 mt-1 w-full bg-white border border-slate-300 rounded shadow-lg max-h-48 overflow-y-auto">
                        <template x-for="lt in filteredLots" :key="lt.lot_number">
                            <div @click="selectedLotNo = lt.lot_number; lotSearch = lt.lot_number; lotDropdown = false"
                                 class="px-3 py-2 text-xs hover:bg-blue-50 cursor-pointer border-b border-slate-100 flex justify-between items-center">
                                <b class="num text-accent" x-text="lt.lot_number"></b>
                                <span class="num text-emerald-700 font-semibold" x-text="'Act FG: ' + lt.act_fg + ' ' + lt.uom"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div x-show="currentLotObj" class="p-2.5 rounded bg-rose-50/70 border border-rose-200 text-xs flex justify-between">
                    <span>Batas Maksimal NG (Saldo Act FG):</span>
                    <b class="num text-rose-800" x-text="currentLotObj?.act_fg + ' ' + currentLotObj?.uom"></b>
                </div>

                <div>
                    <label class="lbl">Kategori Alasan NG (Problem Reason)</label>
                    <select name="problem_reason" x-model="problemReason" required class="w-full h-11 text-xs font-semibold">
                        <option value="MATERIAL">MATERIAL</option>
                        <option value="PROSES">PROSES</option>
                        <option value="DIES/TOOLING">DIES/TOOLING</option>
                        <option value="SETTING/TRIAL">SETTING/TRIAL</option>
                        <option value="HANDLING/OPERATOR">HANDLING/OPERATOR</option>
                    </select>
                </div>

                <div>
                    <label class="lbl">Qty NG (<span x-text="selectedItem?.uom"></span>)</label>
                    <input type="number" step="0.01" min="0.01" :max="currentLotObj?.act_fg || 999999"
                           name="qty_ng" x-model="qtyNg" required placeholder="0.00"
                           class="num w-full h-14 text-2xl text-center font-bold">
                </div>

                <button type="submit"
                        :disabled="!selectedLotNo || !qtyNg || parseFloat(qtyNg) <= 0 || parseFloat(qtyNg) > (currentLotObj?.act_fg || 0)"
                        class="btn btn-primary btn-lg w-full">
                    Simpan Data NG (- Stok)
                </button>
            </form>

            <button type="button" @click="resetAll()" class="btn w-full">
                Scan Ulang dari Awal
            </button>
        </div>
    </div>

    {{-- RIWAYAT NG HARI INI --}}
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-title">
                <span>Riwayat Pencatatan NG Hari Ini</span>
                <span class="count-chip">{{ ($todayScans ?? collect())->count() }}</span>
            </h3>
        </div>
        <div class="max-h-72 overflow-y-auto divide-y divide-slate-200">
            @forelse(($todayScans ?? collect()) as $scan)
                <div class="px-3.5 py-2.5 flex items-center justify-between text-xs">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="num font-bold text-accent">{{ $scan->lot_number }}</span>
                            <span class="pill pill-crit text-[10px] h-4 px-1.5">{{ $scan->problem_reason }}</span>
                            <span class="num text-slate-500">{{ \Carbon\Carbon::parse($scan->date_time)->format('H:i') }}</span>
                        </div>
                        <div class="font-semibold text-slate-900 mt-0.5">{{ $scan->item->part_name ?? '-' }}</div>
                    </div>
                    <div class="num font-bold text-rose-700 text-sm whitespace-nowrap">
                        -{{ floatval($scan->qty_ng) }} {{ $scan->item->uom ?? '' }}
                    </div>
                </div>
            @empty
                <div class="empty py-6 text-xs">Belum ada pencatatan NG hari ini.</div>
            @endforelse
        </div>
    </div>
</x-scanner-layout>