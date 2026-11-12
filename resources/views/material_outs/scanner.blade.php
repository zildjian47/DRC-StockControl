<x-scanner-layout header="Scanner Material Out" :backUrl="route('material_outs.index')">
    @php
        $itemsData = ($items ?? \App\Models\Item::with(['company', 'inventoryStock'])->get())->map(fn ($i) => [
            'id'           => $i->id,
            'part_no'      => $i->part_no ?: '',
            'part_name'    => $i->part_name,
            'type'         => $i->type,
            'uom'          => $i->uom,
            'company_code' => $i->company->company_code ?? '-',
            'company_type' => $i->company->type_company ?? '',
            'stock'        => (float) ($i->inventoryStock->current_stock ?? 0),
        ])->values();

        $bomsData = ($allBoms ?? \App\Models\Bom::with(['process', 'inputItem.inventoryStock', 'outputItem'])->get())->map(fn ($b) => [
            'id'             => $b->id,
            'bom_group'      => $b->bom_group ?: ('SG-' . $b->process_id . '-' . $b->output_item_id),
            'process_id'     => $b->process_id,
            'process_name'   => $b->process->process_name ?? '-',
            'input_item_id'  => $b->input_item_id,
            'input_no'       => $b->inputItem->part_no ?: '-',
            'input_name'     => $b->inputItem->part_name ?? '-',
            'input_uom'      => $b->inputItem->uom ?? '',
            'input_stock'    => (float) ($b->inputItem->inventoryStock->current_stock ?? 0),
            'output_item_id' => $b->output_item_id,
            'output_no'      => $b->outputItem->part_no ?: '-',
            'output_name'    => $b->outputItem->part_name ?? '-',
            'output_uom'     => $b->outputItem->uom ?? 'PCS',
            'qty_usage'      => (float) $b->qty_usage,
            'is_trigger'     => (bool) $b->is_trigger,
        ])->values();

        $usedLots = ($existingLots ?? \App\Models\LotTracer::pluck('lot_number')->unique()->values());
    @endphp

    <div class="panel panel-body"
         x-data="{
            step: 1,
            manualInput: '',
            items: {{ Js::from($itemsData) }},
            boms: {{ Js::from($bomsData) }},
            usedLots: {{ Js::from($usedLots) }},
            selectedMaterial: null,
            selectedTarget: null,
            matchingGroups: [],
            selectedGroupKey: '',
            lotNumber: '',
            qtyOut: '',
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
                    if (!['RM', 'KOMPONEN', 'FG/KOMPONEN'].includes(item.type) || !['SUPPLIER', 'INTERNAL'].includes(item.company_type)) {
                        window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Ditolak: Material harus bertipe RM, KOMPONEN, atau FG/KOMPONEN (SUPPLIER/INTERNAL).' } }));
                        return;
                    }
                    const hasTrigger = this.boms.some(b => Number(b.input_item_id) === Number(item.id) && b.is_trigger);
                    if (!hasTrigger) {
                        window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Ditolak: Material ini tidak terdaftar sebagai Trigger Utama di BOM.' } }));
                        return;
                    }
                    if (item.stock <= 0) {
                        window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Ditolak: Stok material ini sedang kosong (0).' } }));
                        return;
                    }
                    this.selectedMaterial = item;
                    this.manualInput = '';
                    this.step = 2;
                    window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Material Trigger terverifikasi. Silakan Scan Output.', type: 'good' } }));
                } else if (this.step === 2) {
                    if (!['FG', 'KOMPONEN', 'FG/KOMPONEN'].includes(item.type) || !['INTERNAL', 'CUSTOMER'].includes(item.company_type)) {
                        window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Ditolak: Output harus bertipe FG, KOMPONEN, atau FG/KOMPONEN (INTERNAL/CUSTOMER).' } }));
                        return;
                    }
                    const triggerMatches = this.boms.filter(b =>
                        Number(b.input_item_id) === Number(this.selectedMaterial.id) &&
                        Number(b.output_item_id) === Number(item.id) &&
                        b.is_trigger
                    );
                    if (triggerMatches.length === 0) {
                        window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Ditolak: Pasangan Material & Output ini tidak cocok di BOM.' } }));
                        return;
                    }
                    this.selectedTarget = item;
                    this.matchingGroups = triggerMatches.map(tm => {
                        const grpRows = this.boms.filter(b => b.bom_group === tm.bom_group);
                        const outs = grpRows.filter(b => b.is_trigger).map(b => b.output_name).join(' + ');
                        return {
                            bom_group: tm.bom_group,
                            process_name: tm.process_name,
                            outputs_label: outs,
                            rows: grpRows
                        };
                    });
                    this.selectedGroupKey = this.matchingGroups[0].bom_group;
                    this.manualInput = '';
                    this.step = 3;
                    window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Output terverifikasi. Isi Nomor Lot & Qty.', type: 'good' } }));
                }
            },
            get activeGroupRows() {
                return this.boms.filter(b => b.bom_group === this.selectedGroupKey);
            },
            get activeTriggerRows() {
                return this.activeGroupRows.filter(b => b.is_trigger && Number(b.input_item_id) === Number(this.selectedMaterial?.id));
            },
            get lotConflict() {
                const l = this.lotNumber.trim().toUpperCase();
                return l.length > 0 && this.usedLots.includes(l);
            },
            get lotValidFormat() {
                return /^[A-Z][0-9]{6}$/.test(this.lotNumber.trim().toUpperCase());
            },
            get parsedQty() {
                const v = parseFloat(String(this.qtyOut).replace(/,/g, '.'));
                return isFinite(v) && v > 0 ? v : 0;
            },
            resetAll() {
                this.step = 1;
                this.selectedMaterial = null;
                this.selectedTarget = null;
                this.matchingGroups = [];
                this.selectedGroupKey = '';
                this.lotNumber = '';
                this.qtyOut = '';
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

        {{-- AREA KAMERA & INPUT MANUAL (STEP 1 & 2) --}}
        <div x-show="step === 1 || step === 2" class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-accent"
                      x-text="step === 1 ? 'Scan Material (Trigger)' : 'Scan Output'"></span>
                <span class="pill pill-neu text-[10.5px]"
                      x-text="step === 1 ? 'RM · KOMPONEN · FG/KMP' : 'FG · FG/KMP · KOMPONEN'"></span>
            </div>

            <div x-show="step === 2 && selectedMaterial" x-cloak
                 class="p-2.5 rounded bg-emerald-50/70 border border-emerald-200 flex items-center justify-between text-xs">
                <div>
                    <span class="text-slate-500">Material:</span>
                    <b class="text-slate-900 ml-1" x-text="selectedMaterial?.part_name"></b>
                    <span class="num text-emerald-700 font-semibold ml-1" x-text="'(Stok: ' + selectedMaterial?.stock + ' ' + selectedMaterial?.uom + ')'"></span>
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

        {{-- STEP 3: INPUT LOT & QTY --}}
        <div x-show="step === 3" x-cloak class="space-y-4">
            <div class="p-3 rounded bg-slate-50 border border-slate-200 space-y-2 text-xs">
                <div class="flex items-center justify-between border-b border-slate-200 pb-1.5">
                    <span class="text-slate-500">Material Trigger:</span>
                    <b class="text-slate-900" x-text="selectedMaterial?.part_name + ' (' + selectedMaterial?.stock + ' ' + selectedMaterial?.uom + ')'"></b>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Target Output:</span>
                    <b class="text-accent" x-text="activeTriggerRows.map(r => r.output_name).join(' + ')"></b>
                </div>
            </div>

            {{-- Pilihan Kelompok Cetakan jika ada > 1 Grup BOM --}}
            <div x-show="matchingGroups.length > 1" class="space-y-1">
                <label class="lbl">Pilih Kelompok Cetakan BOM</label>
                <select x-model="selectedGroupKey" class="w-full h-10 text-xs">
                    <template x-for="g in matchingGroups" :key="g.bom_group">
                        <option :value="g.bom_group" x-text="g.process_name + ' · ' + g.outputs_label"></option>
                    </template>
                </select>
            </div>

            <form action="{{ route('material_outs.store') }}" method="POST" class="space-y-3.5">
                @csrf
                <input type="hidden" name="mode" value="scanner">
                <input type="hidden" name="material_item_id" :value="selectedMaterial?.id">
                <input type="hidden" name="target_item_id" :value="selectedTarget?.id">
                <input type="hidden" name="bom_group" :value="selectedGroupKey">

                <div>
                    <label class="lbl">Nomor Lot (1 Huruf Kapital + 6 Angka)</label>
                    <input type="text" name="lot_number" x-model="lotNumber"
                           oninput="this.value = this.value.toUpperCase()"
                           maxlength="7" required placeholder="Contoh: J260178"
                           class="num uppercase w-full h-11 text-base font-bold tracking-wider text-center">
                    <p x-show="lotConflict" class="text-xs text-rose-600 font-semibold mt-1">
                        Ditolak: Nomor Lot sudah pernah digunakan.
                    </p>
                </div>

                <div>
                    <label class="lbl">
                        Qty Material Out (<span x-text="selectedMaterial?.uom"></span>)
                        · Maks: <b class="num" x-text="selectedMaterial?.stock"></b>
                    </label>
                    <input type="number" step="0.01" min="0.01" :max="selectedMaterial?.stock"
                           name="qty" x-model="qtyOut" required placeholder="0.00"
                           class="num w-full h-14 text-2xl text-center font-bold">
                </div>

                {{-- Preview Estimasi Target Output --}}
                <div x-show="parsedQty > 0" class="p-2.5 rounded bg-blue-50/70 border border-blue-200 text-xs space-y-1">
                    <div class="font-semibold text-blue-900">Estimasi Target Produksi:</div>
                    <template x-for="tr in activeTriggerRows" :key="tr.id">
                        <div class="flex justify-between">
                            <span x-text="tr.output_name"></span>
                            <b class="num text-blue-800" x-text="Math.round((parsedQty / tr.qty_usage) * 100) / 100 + ' ' + tr.output_uom"></b>
                        </div>
                    </template>
                </div>

                <button type="submit"
                        :disabled="lotConflict || !lotValidFormat || parsedQty <= 0 || parsedQty > (selectedMaterial?.stock || 0)"
                        class="btn btn-primary btn-lg w-full">
                    Simpan Material Out (- Stok)
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
                        <div class="text-[11px] text-slate-500">Target: {{ $scan->targetItem->part_name ?? '-' }}</div>
                    </div>
                    <div class="num font-bold text-rose-700 text-sm whitespace-nowrap">
                        -{{ floatval($scan->qty) }} {{ $scan->materialItem->uom ?? '' }}
                    </div>
                </div>
            @empty
                <div class="empty py-6 text-xs">Belum ada scan Material Out hari ini.</div>
            @endforelse
        </div>
    </div>
</x-scanner-layout>