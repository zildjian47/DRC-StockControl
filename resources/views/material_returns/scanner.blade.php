<x-scanner-layout header="Scanner Material Return" :backUrl="route('material_returns.index')">
    @php
        $itemsData = ($items ?? \App\Models\Item::with('company')->get())->map(fn ($i) => [
            'id'           => $i->id,
            'part_no'      => $i->part_no ?: '',
            'part_name'    => $i->part_name,
            'type'         => $i->type,
            'uom'          => $i->uom,
            'company_type' => $i->company->type_company ?? '',
        ])->values();

        $lotsData = ($activeLots ?? collect())->map(fn ($l) => [
            'lot_number'       => $l->lot_number,
            'material_item_id' => $l->material_item_id,
            'material_name'    => $l->materialItem->part_name ?? '-',
            'material_uom'     => $l->materialItem->uom ?? '',
            'target_item_id'   => $l->target_item_id,
            'target_name'      => $l->targetItem->part_name ?? '-',
            'mat_out'          => (float) $l->qty_material_out,
            'mat_current'      => (float) $l->material_saat_ini,
        ])->values();
    @endphp

    <div class="panel panel-body"
         x-data="{
            step: 1,
            manualInput: '',
            items: {{ Js::from($itemsData) }},
            activeLots: {{ Js::from($lotsData) }},
            selectedMaterial: null,
            selectedTarget: null,
            selectedLotNo: '',
            lotSearch: '',
            lotDropdown: false,
            qtyReturn: '',
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

                if (this.step === 1) {
                    const hasActiveLot = this.activeLots.some(l => Number(l.material_item_id) === Number(item.id));
                    if (!hasActiveLot) {
                        window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Ditolak: Tidak ada Lot aktif dengan sisa material untuk item ini.' } }));
                        return;
                    }
                    this.selectedMaterial = item;
                    this.manualInput = '';
                    this.step = 2;
                    window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Material terverifikasi. Silakan Scan Output.', type: 'good' } }));
                } else if (this.step === 2) {
                    const matching = this.activeLots.filter(l =>
                        Number(l.material_item_id) === Number(this.selectedMaterial.id) &&
                        Number(l.target_item_id) === Number(item.id)
                    );
                    if (matching.length === 0) {
                        window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Ditolak: Tidak ada Lot aktif untuk pasangan Material & Output ini.' } }));
                        return;
                    }
                    this.selectedTarget = item;
                    this.manualInput = '';
                    if (matching.length === 1) {
                        this.selectedLotNo = matching[0].lot_number;
                        this.lotSearch = matching[0].lot_number;
                    }
                    this.step = 3;
                    window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Output terverifikasi. Pilih Lot & isi Qty Return.', type: 'good' } }));
                }
            },
            get filteredLots() {
                if (!this.selectedMaterial || !this.selectedTarget) return [];
                return this.activeLots.filter(l =>
                    Number(l.material_item_id) === Number(this.selectedMaterial.id) &&
                    Number(l.target_item_id) === Number(this.selectedTarget.id) &&
                    (!this.lotSearch || l.lot_number.toLowerCase().includes(this.lotSearch.toLowerCase()))
                );
            },
            get currentLotObj() {
                return this.activeLots.find(l =>
                    l.lot_number === this.selectedLotNo &&
                    Number(l.material_item_id) === Number(this.selectedMaterial?.id)
                ) || null;
            },
            resetAll() {
                this.step = 1;
                this.selectedMaterial = null;
                this.selectedTarget = null;
                this.selectedLotNo = '';
                this.lotSearch = '';
                this.qtyReturn = '';
                this.manualInput = '';
            }
         }">

        {{-- VISUAL STEPPER 3 LANGKAH --}}
        <div class="stepper">
            <div class="step-item">
                <span class="step-circle"
                      :class="step === 1 ? 'step-circle-active' : 'step-circle-done'"
                      x-text="step > 1 ? '✓' : '1'"></span>
                <span class="step-label"
                      :class="step === 1 ? 'step-label-active' : 'step-label-done'">Scan Material</span>
            </div>

            <div class="step-line" :class="step > 1 ? 'step-line-done' : ''"></div>

            <div class="step-item">
                <span class="step-circle"
                      :class="step === 2 ? 'step-circle-active' : (step > 2 ? 'step-circle-done' : 'step-circle-idle')"
                      x-text="step > 2 ? '✓' : '2'"></span>
                <span class="step-label"
                      :class="step === 2 ? 'step-label-active' : (step > 2 ? 'step-label-done' : 'step-label-idle')">Scan Output</span>
            </div>

            <div class="step-line" :class="step > 2 ? 'step-line-done' : ''"></div>

            <div class="step-item">
                <span class="step-circle"
                      :class="step === 3 ? 'step-circle-active' : 'step-circle-idle'">3</span>
                <span class="step-label"
                      :class="step === 3 ? 'step-label-active' : 'step-label-idle'">Lot &amp; Qty</span>
            </div>
        </div>

        {{-- STEP 1 & 2 --}}
        <div x-show="step === 1 || step === 2" class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-accent"
                      x-text="step === 1 ? 'Scan Material' : 'Scan Output'"></span>
                <span class="pill pill-neu text-[10.5px]">Retur Material Sisa</span>
            </div>

            <div x-show="step === 2 && selectedMaterial" x-cloak
                 class="p-2.5 rounded bg-emerald-50/70 border border-emerald-200 flex items-center justify-between text-xs">
                <div>
                    <span class="text-slate-500">Material:</span>
                    <b class="text-slate-900 ml-1" x-text="selectedMaterial?.part_name"></b>
                </div>
                <button type="button" @click="resetAll()" class="text-xs text-rose-600 font-semibold hover:underline">Ubah</button>
            </div>

            <div class="camera-box">
                <div id="qr-reader"></div>
            </div>

            <div class="flex gap-2">
                <input type="text" x-model="manualInput"
                       @keydown.enter.prevent="handleScan(manualInput)"
                       :placeholder="step === 1 ? 'Ketik Part No / Nama Material...' : 'Ketik Part No / Nama Output...'"
                       class="num flex-1 h-10 text-xs">
                <button type="button" @click="handleScan(manualInput)" class="btn btn-primary px-4 h-10">
                    Verifikasi
                </button>
            </div>
        </div>

        {{-- STEP 3: PILIH LOT & INPUT QTY RETURN --}}
        <div x-show="step === 3" x-cloak class="space-y-4">
            <div class="p-3 rounded bg-slate-50 border border-slate-200 space-y-1.5 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-500">Material:</span>
                    <b class="text-slate-900" x-text="selectedMaterial?.part_name"></b>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Target Output:</span>
                    <b class="text-accent" x-text="selectedTarget?.part_name"></b>
                </div>
            </div>

            <form action="{{ route('material_returns.store') }}" method="POST" class="space-y-3.5">
                @csrf
                <input type="hidden" name="mode" value="scanner">
                <input type="hidden" name="material_item_id" :value="selectedMaterial?.id">
                <input type="hidden" name="target_item_id" :value="selectedTarget?.id">
                <input type="hidden" name="lot_number" :value="selectedLotNo" required>

                <div class="relative" @click.outside="lotDropdown = false">
                    <label class="lbl">Pilih Nomor Lot Aktif (Ketik &amp; Pilih)</label>
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
                                <span class="num text-slate-600" x-text="'Sisa Material: ' + lt.mat_current + ' ' + lt.material_uom"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div x-show="currentLotObj" class="p-2.5 rounded bg-blue-50/70 border border-blue-200 text-xs flex justify-between">
                    <span>Batas Maksimal Return (Material Saat Ini):</span>
                    <b class="num text-blue-800" x-text="currentLotObj?.mat_current + ' ' + currentLotObj?.material_uom"></b>
                </div>

                <div>
                    <label class="lbl">Qty Material Return (<span x-text="selectedMaterial?.uom"></span>)</label>
                    <input type="number" step="0.01" min="0.01" :max="currentLotObj?.mat_current || 999999"
                           name="qty" x-model="qtyReturn" required placeholder="0.00"
                           class="num w-full h-14 text-2xl text-center font-bold">
                </div>

                <button type="submit"
                        :disabled="!selectedLotNo || !qtyReturn || parseFloat(qtyReturn) <= 0 || parseFloat(qtyReturn) > (currentLotObj?.mat_current || 0)"
                        class="btn btn-primary btn-lg w-full">
                    Simpan Material Return (+ Stok)
                </button>
            </form>

            <button type="button" @click="resetAll()" class="btn w-full">
                Scan Ulang dari Awal
            </button>
        </div>
    </div>

    {{-- RIWAYAT SCAN HARI INI --}}
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-title">
                <span>Riwayat Scan Hari Ini</span>
                <span class="count-chip">{{ $todayScans->count() }}</span>
            </h3>
        </div>
        <div class="max-h-72 overflow-y-auto divide-y divide-slate-200">
            @forelse($todayScans as $scan)
                <div class="px-3.5 py-2.5 flex items-center justify-between text-xs">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="num font-bold text-accent">{{ $scan->lot_number }}</span>
                            <span class="text-slate-400">·</span>
                            <span class="num text-slate-500">{{ \Carbon\Carbon::parse($scan->date_time)->format('H:i') }}</span>
                        </div>
                        <div class="font-semibold text-slate-900 mt-0.5">{{ $scan->materialItem->part_name ?? '-' }}</div>
                    </div>
                    <div class="num font-bold text-emerald-700 text-sm whitespace-nowrap">
                        +{{ floatval($scan->qty) }} {{ $scan->materialItem->uom ?? '' }}
                    </div>
                </div>
            @empty
                <div class="empty py-6 text-xs">Belum ada scan Material Return hari ini.</div>
            @endforelse
        </div>
    </div>
</x-scanner-layout>