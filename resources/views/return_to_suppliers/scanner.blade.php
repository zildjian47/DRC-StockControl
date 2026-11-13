<x-scanner-layout>
    <x-slot name="header">Scanner Return to Supplier</x-slot>

    @php
        $todayScans = $todayScans ?? ($recentScans ?? \App\Models\ReturnToSupplier::with(['item.company', 'user'])
            ->whereDate('date_time', now()->toDateString())
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get());

        $items = $items ?? \App\Models\Item::with(['company', 'inventoryStock'])->get();

        $itemsJs = $items->map(fn ($i) => [
            'id'           => $i->id,
            'part_no'      => $i->part_no ?: '',
            'part_name'    => $i->part_name,
            'type'         => $i->type,
            'uom'          => $i->uom,
            'company_code' => $i->company->company_code ?? '-',
            'company_type' => $i->company->type_company ?? '',
            'stock'        => (float) ($i->inventoryStock->current_stock ?? 0),
        ])->values();
    @endphp

    <div x-data="returnSupplierScanner({{ Js::from($itemsJs) }})" x-init="initScanner()">
        <div class="panel panel-body">
            {{-- VISUAL STEPPER --}}
            <div class="stepper">
                <div class="step-item" :class="step === 1 ? 'active' : 'done'">
                    <span class="step-badge" x-text="step > 1 ? '✓' : '1'"></span>
                    <span>Scan Item</span>
                </div>
                <div class="step-line" :class="step > 1 ? 'done' : ''"></div>
                <div class="step-item" :class="step === 2 ? 'active' : ''">
                    <span class="step-badge">2</span>
                    <span>Input Qty</span>
                </div>
            </div>

            {{-- LANGKAH 1: SCAN ITEM --}}
            <div x-show="step === 1" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Scan Item</h3>
                    <span class="pill pill-info">RM · KOMPONEN · TRANSIT</span>
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
                    <label class="lbl">Input Part No / Part Name</label>
                    <div class="flex gap-2">
                        <input type="text" x-model="manualCode" @keydown.enter.prevent="verifyCode(manualCode)"
                               placeholder="Ketik Part No atau Part Name..." class="num flex-1 h-10">
                        <button type="button" @click="verifyCode(manualCode)" class="btn btn-primary h-10 px-4">Verifikasi</button>
                    </div>
                </div>
            </div>

            {{-- LANGKAH 2: INPUT QTY RETURN --}}
            <div x-show="step === 2" x-cloak class="space-y-4">
                <div class="p-3 rounded bg-slate-50 border border-slate-200 space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="num text-xs font-bold text-accent" x-text="selectedItem?.part_no || '-'"></span>
                        <span class="pill pill-neu" x-text="(selectedItem?.type || '') + ' · ' + (selectedItem?.company_code || '')"></span>
                    </div>
                    <div class="font-bold text-slate-900 text-sm" x-text="selectedItem?.part_name"></div>
                    <div class="text-xs text-slate-600">
                        Stok Tersedia: <b class="num text-emerald-700" x-text="(selectedItem?.stock ?? 0) + ' ' + (selectedItem?.uom || '')"></b>
                    </div>
                </div>

                <form action="{{ route('return_to_suppliers.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <input type="hidden" name="mode" value="scanner">
                    <input type="hidden" name="item_id" :value="selectedItem?.id">

                    <div>
                        <label class="lbl">Qty Return to Supplier (- Stok)</label>
                        <input type="text" name="qty" x-model="qtyInput" required placeholder="0"
                               class="num w-full h-14 text-2xl text-center font-bold">
                        <div x-show="evalQty() > (selectedItem?.stock ?? 0)"
                             class="text-xs text-rose-600 font-semibold mt-1 text-center">
                            Ditolak: Qty melebihi stok gudang yang tersedia!
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-full"
                            :disabled="!evalQty() || evalQty() <= 0 || evalQty() > (selectedItem?.stock ?? 0)">
                        Simpan Return to Supplier (- Stok)
                    </button>
                </form>

                <button type="button" class="btn w-full" @click="resetStep()">Batal &amp; Scan Ulang</button>
            </div>
        </div>

        {{-- RIWAYAT SCAN HARI INI --}}
        <div class="panel mt-3.5">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Riwayat Scan Hari Ini</span>
                    <span class="count-chip">{{ $todayScans->count() }}</span>
                </div>
                <a href="{{ route('return_to_suppliers.index') }}" class="text-xs font-semibold text-accent hover:underline">Mode Desktop</a>
            </div>
            <div class="max-h-72 overflow-y-auto divide-y divide-slate-200">
                @forelse($todayScans as $sc)
                    <div class="px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs">
                        <div class="min-w-0">
                            <div class="font-semibold text-slate-900 truncate">{{ $sc->item->part_name ?? '-' }}</div>
                            <div class="text-[11px] text-slate-500 num">
                                {{ $sc->item->part_no ?: '-' }} · {{ \Carbon\Carbon::parse($sc->date_time)->format('H:i') }} · {{ $sc->user->name ?? 'Operator' }}
                            </div>
                        </div>
                        <div class="num font-bold text-rose-700 whitespace-nowrap">
                            -{{ floatval($sc->qty) }} {{ $sc->item->uom ?? '' }}
                        </div>
                    </div>
                @empty
                    <div class="empty py-6">Belum ada scan Return to Supplier hari ini.</div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        function returnSupplierScanner(itemsList) {
            return {
                items: itemsList,
                step: 1,
                cameraRunning: false,
                manualCode: '',
                selectedItem: null,
                qtyInput: '',

                initScanner() {
                    this.startCam();
                },
                startCam() {
                    window.startDrcCamera('qr-reader', (code) => {
                        this.verifyCode(code);
                    }, (running) => {
                        this.cameraRunning = running;
                    });
                },
                verifyCode(raw) {
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

                    if (!['RM', 'KOMPONEN', 'TRANSIT'].includes(found.type) || found.company_type !== 'SUPPLIER') {
                        window.showScannerToast('Ditolak: Hanya menerima item RM, KOMPONEN, atau TRANSIT dari SUPPLIER.', 'crit');
                        return;
                    }

                    if (found.stock <= 0) {
                        window.showScannerToast('Ditolak: Stok barang ini sedang kosong (0).', 'crit');
                        return;
                    }

                    this.selectedItem = found;
                    this.step = 2;
                    window.stopDrcCamera();
                    this.cameraRunning = false;
                    window.showScannerToast('Item terverifikasi: ' + found.part_name, 'good');
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
                resetStep() {
                    this.selectedItem = null;
                    this.manualCode = '';
                    this.qtyInput = '';
                    this.step = 1;
                    this.startCam();
                }
            };
        }
    </script>
</x-scanner-layout>