<x-scanner-layout>
    <x-slot name="header">Scanner Material Out</x-slot>

    @php
        $todayScans = $todayScans ?? ($recentScans ?? \App\Models\MaterialOut::with(['materialItem', 'targetItem', 'user'])
            ->whereDate('date_time', now()->toDateString())
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get());

        $items = $items ?? \App\Models\Item::with(['company', 'inventoryStock'])->get();
        $allBoms = $allBoms ?? \App\Models\Bom::with(['process', 'inputItem.inventoryStock', 'outputItem'])->get();
        $existingLots = $existingLots ?? \App\Models\LotTracer::pluck('lot_number')->unique()->values();

        $itemsJs = $items->map(fn ($i) => [
            'id'           => $i->id,
            'part_no'      => $i->part_no ?: '',
            'part_name'    => $i->part_name,
            'type'         => $i->type,
            'uom'          => $i->uom,
            'company_type' => $i->company->type_company ?? '',
            'stock'        => (float) ($i->inventoryStock->current_stock ?? 0),
        ])->values();

        $bomsJs = $allBoms->map(fn ($b) => [
            'id'              => $b->id,
            'bom_group'       => $b->bom_group ?: ('LEGACY-' . $b->process_id . '-' . $b->output_item_id),
            'process_id'      => $b->process_id,
            'process_name'    => $b->process->process_name ?? '-',
            'input_item_id'   => $b->input_item_id,
            'input_part_no'   => $b->inputItem->part_no ?: '-',
            'input_part_name' => $b->inputItem->part_name ?? '-',
            'input_uom'       => $b->inputItem->uom ?? '',
            'input_stock'     => (float) ($b->inputItem->inventoryStock->current_stock ?? 0),
            'output_item_id'  => $b->output_item_id,
            'output_part_no'  => $b->outputItem->part_no ?: '-',
            'output_part_name'=> $b->outputItem->part_name ?? '-',
            'output_uom'      => $b->outputItem->uom ?? 'PCS',
            'qty_usage'       => (float) $b->qty_usage,
            'is_trigger'      => (bool) $b->is_trigger,
        ])->values();
    @endphp

    <div x-data="materialOutScanner({{ Js::from($itemsJs) }}, {{ Js::from($bomsJs) }}, {{ Js::from($existingLots) }})"
         x-init="initScanner()">
        <div class="panel panel-body">
            {{-- VISUAL STEPPER 3 LANGKAH --}}
            <div class="stepper">
                <div class="step-item" :class="step === 1 ? 'active' : 'done'">
                    <span class="step-badge" x-text="step > 1 ? '✓' : '1'"></span>
                    <span>Scan Material</span>
                </div>
                <div class="step-line" :class="step > 1 ? 'done' : ''"></div>
                <div class="step-item" :class="step === 2 ? 'active' : (step > 2 ? 'done' : '')">
                    <span class="step-badge" x-text="step > 2 ? '✓' : '2'"></span>
                    <span>Scan Output</span>
                </div>
                <div class="step-line" :class="step > 2 ? 'done' : ''"></div>
                <div class="step-item" :class="step === 3 ? 'active' : ''">
                    <span class="step-badge">3</span>
                    <span>Lot &amp; Qty</span>
                </div>
            </div>

            {{-- AREA KAMERA (AKTIF DI STEP 1 & STEP 2) --}}
            <div x-show="step === 1 || step === 2" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700"
                        x-text="step === 1 ? 'Scan Material' : 'Scan Output'"></h3>
                    <span class="pill pill-info"
                          x-text="step === 1 ? 'Trigger BOM' : 'FG · FG/KOMPONEN · KOMPONEN'"></span>
                </div>

                <div x-show="step === 2 && materialItem" class="p-2.5 rounded bg-blue-50/70 border border-blue-200 text-xs flex items-center justify-between">
                    <div>
                        <span class="text-slate-500">Material Trigger:</span>
                        <b class="text-slate-900 ml-1" x-text="materialItem?.part_name"></b>
                    </div>
                    <span class="num font-bold text-blue-800" x-text="(materialItem?.stock ?? 0) + ' ' + (materialItem?.uom || '')"></span>
                </div>

                <div class="camera-box">
                    <div id="qr-reader"></div>
                    <div x-show="cameraRunning" class="scan-laser"></div>
                    <div x-show="!cameraRunning" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-900/90 p-4 text-center">
                        <x-icon name="scan" class="w-8 h-8 text-slate-400 mb-2" />
                        <p class="text-xs text-slate-300 mb-2.5">Kamera belum aktif atau gunakan input cepat di bawah</p>
                        <button type="button" @click="startCam()" class="btn btn-primary btn-sm">Aktifkan Kamera QR</button>
                    </div>
                </div>

                <div>
                    <label class="lbl" x-text="step === 1 ? 'Input Part No / Part Name Material' : 'Input Part No / Part Name Output'"></label>
                    <div class="flex gap-2">
                        <input type="text" x-model="manualCode" @keydown.enter.prevent="handleScan(manualCode)"
                               placeholder="Ketik Part No atau Part Name..." class="num flex-1 h-10">
                        <button type="button" @click="handleScan(manualCode)" class="btn btn-primary h-10 px-4">Verifikasi</button>
                    </div>
                </div>

                <button type="button" x-show="step === 2" @click="resetAll()" class="btn w-full">
                    Ulangi dari Scan Material
                </button>
            </div>

            {{-- LANGKAH 3: INPUT LOT & QTY --}}
            <div x-show="step === 3" x-cloak class="space-y-3.5">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Input Lot &amp; Qty</h3>
                    <span class="pill pill-good" x-text="activeGroupInfo.process_name"></span>
                </div>

                {{-- Pilihan Grup Cetakan jika ada >1 Grup BOM untuk pasangan Material + Output yang sama --}}
                <div x-show="candidateGroups.length > 1" class="p-2.5 rounded bg-amber-50 border border-amber-200 space-y-1.5">
                    <label class="lbl text-amber-900">Pilih Kelompok Cetakan BOM:</label>
                    <select x-model="selectedBomGroup" class="w-full h-9 text-xs font-semibold">
                        <template x-for="cg in candidateGroups" :key="cg.bom_group">
                            <option :value="cg.bom_group" x-text="cg.bom_group + ' — ' + cg.outputs_label"></option>
                        </template>
                    </select>
                </div>

                <div class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-2">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-1.5">
                        <div>
                            <span class="text-slate-500">Trigger:</span>
                            <b class="text-slate-900 ml-1" x-text="materialItem?.part_name"></b>
                        </div>
                        <span class="num font-bold text-emerald-700" x-text="'Stok: ' + (materialItem?.stock ?? 0) + ' ' + (materialItem?.uom || '')"></span>
                    </div>

                    <div>
                        <div class="text-[11px] font-semibold text-slate-500 mb-1">Target Output dalam Kelompok Ini:</div>
                        <template x-for="o in activeGroupInfo.outputs" :key="o.output_item_id">
                            <div class="flex items-center justify-between py-0.5">
                                <span class="font-semibold text-slate-800" x-text="o.output_part_name"></span>
                                <span class="num font-bold text-accent"
                                      x-text="evalQty() ? ('Est: ' + Math.round((evalQty() / o.qty_usage) * 100) / 100 + ' ' + o.output_uom) : ('Rasio: ' + o.qty_usage)"></span>
                            </div>
                        </template>
                    </div>

                    <div x-show="activeGroupInfo.companions.length > 0" class="border-t border-slate-200 pt-1.5">
                        <div class="text-[11px] font-semibold text-slate-500 mb-1">Komponen Pendamping Otomatis Terpotong:</div>
                        <template x-for="c in activeGroupInfo.companions" :key="c.input_item_id">
                            <div class="flex items-center justify-between py-0.5">
                                <span class="text-slate-700" x-text="c.input_part_name"></span>
                                <span class="num font-semibold"
                                      :class="c.needed > c.input_stock ? 'text-rose-600 font-bold' : 'text-slate-800'"
                                      x-text="'-' + c.needed + ' / Stok: ' + c.input_stock + ' ' + c.input_uom"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <form action="{{ route('material_outs.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <input type="hidden" name="mode" value="scanner">
                    <input type="hidden" name="material_item_id" :value="materialItem?.id">
                    <input type="hidden" name="target_item_id" :value="targetItem?.id">
                    <input type="hidden" name="bom_group" :value="selectedBomGroup">

                    <div>
                        <label class="lbl">Nomor Lot (1 Huruf Kapital + 6 Angka, contoh: J260178)</label>
                        <input type="text" name="lot_number" x-model="lotNumber"
                               @input="lotNumber = lotNumber.toUpperCase().trim()"
                               maxlength="7" required placeholder="Contoh: J260178"
                               class="num uppercase w-full h-11 text-base font-bold text-center tracking-wider">
                        <div x-show="lotNumber && !isLotFormatValid" class="text-xs text-rose-600 font-semibold mt-1">
                            Format wajib 1 Huruf Kapital diikuti 6 Angka (contoh: J260178).
                        </div>
                        <div x-show="isLotDuplicate" class="text-xs text-rose-600 font-semibold mt-1">
                            Ditolak: Nomor Lot ini sudah pernah digunakan pada Material Out sebelumnya.
                        </div>
                    </div>

                    <div>
                        <label class="lbl">Qty Material Out (<span x-text="materialItem?.uom || ''"></span>)</label>
                        <input type="text" name="qty" x-model="qtyInput" required placeholder="0"
                               class="num w-full h-14 text-2xl text-center font-bold">
                        <div x-show="evalQty() > (materialItem?.stock ?? 0)" class="text-xs text-rose-600 font-semibold mt-1 text-center">
                            Melebihi stok Material Trigger yang tersedia!
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="!canSubmit">
                        Simpan Material Out (- Stok)
                    </button>
                </form>

                <button type="button" class="btn w-full" @click="resetAll()">Batal &amp; Scan Ulang</button>
            </div>
        </div>

        {{-- RIWAYAT SCAN HARI INI --}}
        <div class="panel mt-3.5">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Scan Hari Ini</span>
                    <span class="count-chip">{{ $todayScans->count() }}</span>
                </div>
                <a href="{{ route('material_outs.index') }}" class="text-xs font-semibold text-accent hover:underline">Mode Desktop</a>
            </div>
            <div class="max-h-72 overflow-y-auto divide-y divide-slate-200">
                @forelse($todayScans as $sc)
                    <div class="px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="num font-bold text-accent">{{ $sc->lot_number }}</span>
                                <span class="text-slate-400">·</span>
                                <span class="font-semibold text-slate-900 truncate">{{ $sc->materialItem->part_name ?? '-' }}</span>
                            </div>
                            <div class="text-[11px] text-slate-500 truncate">
                                Output: {{ $sc->targetItem->part_name ?? '-' }} · {{ \Carbon\Carbon::parse($sc->date_time)->format('H:i') }}
                            </div>
                        </div>
                        <div class="num font-bold text-rose-700 whitespace-nowrap">
                            -{{ floatval($sc->qty) }} {{ $sc->materialItem->uom ?? '' }}
                        </div>
                    </div>
                @empty
                    <div class="empty py-6">Belum ada scan Material Out hari ini.</div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        function materialOutScanner(itemsList, bomsList, existingLotsList) {
            return {
                items: itemsList,
                boms: bomsList,
                existingLots: existingLotsList,
                step: 1,
                cameraRunning: false,
                manualCode: '',
                materialItem: null,
                targetItem: null,
                candidateGroups: [],
                selectedBomGroup: '',
                lotNumber: '',
                qtyInput: '',

                initScanner() {
                    this.startCam();
                },
                startCam() {
                    window.startDrcCamera('qr-reader', (code) => {
                        this.handleScan(code);
                    }, (running) => {
                        this.cameraRunning = running;
                    });
                },
                handleScan(raw) {
                    const q = String(raw || '').trim().toLowerCase();
                    if (!q) return;

                    const found = this.items.find(i =>
                        (i.part_no && i.part_no.toLowerCase() === q) ||
                        (i.part_name && i.part_name.toLowerCase() === q)
                    );

                    if (!found) {
                        window.showScannerToast('Ditolak: Item tidak terdaftar di Master Item.', 'crit');
                        return;
                    }

                    if (this.step === 1) {
                        if (!['RM', 'KOMPONEN', 'FG/KOMPONEN'].includes(found.type) || !['SUPPLIER', 'INTERNAL'].includes(found.company_type)) {
                            window.showScannerToast('Ditolak: Material harus bertipe RM, KOMPONEN, atau FG/KOMPONEN (SUPPLIER/INTERNAL).', 'crit');
                            return;
                        }
                        const isTrig = this.boms.some(b => Number(b.input_item_id) === Number(found.id) && b.is_trigger);
                        if (!isTrig) {
                            window.showScannerToast('Ditolak: Material ini tidak terdaftar sebagai Trigger Utama di BOM.', 'crit');
                            return;
                        }
                        if (found.stock <= 0) {
                            window.showScannerToast('Ditolak: Stok material ini sedang kosong (0).', 'crit');
                            return;
                        }

                        this.materialItem = found;
                        this.manualCode = '';
                        this.step = 2;
                        window.showScannerToast('Material terverifikasi: ' + found.part_name, 'good');
                    } else if (this.step === 2) {
                        if (!['FG', 'KOMPONEN', 'FG/KOMPONEN'].includes(found.type) || !['INTERNAL', 'CUSTOMER'].includes(found.company_type)) {
                            window.showScannerToast('Ditolak: Target Output harus bertipe FG, KOMPONEN, atau FG/KOMPONEN.', 'crit');
                            return;
                        }

                        const matchingTriggers = this.boms.filter(b =>
                            Number(b.input_item_id) === Number(this.materialItem.id) &&
                            Number(b.output_item_id) === Number(found.id) &&
                            b.is_trigger
                        );

                        if (matchingTriggers.length === 0) {
                            window.showScannerToast('Ditolak: Pasangan Material & Output ini tidak cocok di resep BOM.', 'crit');
                            return;
                        }

                        this.targetItem = found;
                        this.candidateGroups = matchingTriggers.map(mt => {
                            const gRows = this.boms.filter(b => b.bom_group === mt.bom_group && b.is_trigger);
                            return {
                                bom_group: mt.bom_group,
                                outputs_label: gRows.map(r => r.output_part_name).join(' + ')
                            };
                        });
                        this.selectedBomGroup = this.candidateGroups[0].bom_group;
                        this.manualCode = '';
                        this.step = 3;
                        window.stopDrcCamera();
                        this.cameraRunning = false;
                        window.showScannerToast('Output terverifikasi: ' + found.part_name, 'good');
                    }
                },
                evalQty() {
                    if (!this.qtyInput) return null;
                    const expr = String(this.qtyInput).replace(/,/g, '.').replace(/\s+/g, '');
                    if (!/^[0-9\.\+\-\*\/\(\)]+$/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r) && r > 0) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                get activeGroupInfo() {
                    if (!this.selectedBomGroup) return { process_name: '-', outputs: [], companions: [] };
                    const gRows = this.boms.filter(b => b.bom_group === this.selectedBomGroup);
                    const outputs = gRows.filter(b => b.is_trigger);
                    const qOut = this.evalQty() || 0;

                    const compMap = {};
                    outputs.forEach(o => {
                        const est = o.qty_usage > 0 ? (qOut / o.qty_usage) : 0;
                        gRows.filter(b => !b.is_trigger && Number(b.output_item_id) === Number(o.output_item_id)).forEach(c => {
                            const need = Math.round(est * c.qty_usage * 100) / 100;
                            if (!compMap[c.input_item_id]) {
                                compMap[c.input_item_id] = {
                                    input_item_id: c.input_item_id,
                                    input_part_name: c.input_part_name,
                                    input_uom: c.input_uom,
                                    input_stock: c.input_stock,
                                    needed: 0
                                };
                            }
                            compMap[c.input_item_id].needed = Math.round((compMap[c.input_item_id].needed + need) * 100) / 100;
                        });
                    });

                    return {
                        process_name: gRows[0]?.process_name || '-',
                        outputs: outputs,
                        companions: Object.values(compMap)
                    };
                },
                get isLotFormatValid() {
                    return /^[A-Z][0-9]{6}$/.test(this.lotNumber);
                },
                get isLotDuplicate() {
                    return this.existingLots.includes(this.lotNumber);
                },
                get canSubmit() {
                    const q = this.evalQty();
                    if (!q || q <= 0 || !this.materialItem) return false;
                    if (q > this.materialItem.stock) return false;
                    if (!this.isLotFormatValid || this.isLotDuplicate) return false;
                    const companionShortage = this.activeGroupInfo.companions.some(c => c.needed > c.input_stock);
                    return !companionShortage;
                },
                resetAll() {
                    this.materialItem = null;
                    this.targetItem = null;
                    this.candidateGroups = [];
                    this.selectedBomGroup = '';
                    this.manualCode = '';
                    this.lotNumber = '';
                    this.qtyInput = '';
                    this.step = 1;
                    this.startCam();
                }
            };
        }
    </script>
</x-scanner-layout>