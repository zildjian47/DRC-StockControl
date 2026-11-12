<x-scanner-layout header="Scanner Delivery" :backUrl="route('deliveries.index')">
    @php
        $itemsData = ($items ?? \App\Models\Item::with(['company', 'inventoryStock'])->get())->map(fn ($i) => [
            'id'           => $i->id,
            'part_no'      => $i->part_no ?: '',
            'part_name'    => $i->part_name,
            'type'         => $i->type,
            'uom'          => $i->uom,
            'company_name' => $i->company->company_name ?? '-',
            'company_type' => $i->company->type_company ?? '',
            'stock'        => (float) ($i->inventoryStock->current_stock ?? 0),
        ])->values();
    @endphp

    <div class="panel panel-body"
         x-data="{
            step: 1,
            manualInput: '',
            items: {{ Js::from($itemsData) }},
            selectedItem: null,
            qtyDeliv: '',
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
                const validFg = item.type === 'FG' && item.company_type === 'CUSTOMER';
                const validFgk = item.type === 'FG/KOMPONEN' && ['INTERNAL', 'CUSTOMER'].includes(item.company_type);
                const validTrs = item.type === 'TRANSIT' && ['SUPPLIER', 'CUSTOMER'].includes(item.company_type);

                if (!validFg && !validFgk && !validTrs) {
                    window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Ditolak: Item tidak memenuhi kriteria pengiriman (Delivery).' } }));
                    return;
                }
                if (item.stock <= 0) {
                    window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Ditolak: Stok item ini sedang kosong (0).' } }));
                    return;
                }
                this.selectedItem = item;
                this.manualInput = '';
                this.step = 2;
                window.dispatchEvent(new CustomEvent('scan-notify', { detail: { message: 'Item terverifikasi. Silakan isi Qty Delivery.', type: 'good' } }));
            },
            resetAll() {
                this.step = 1;
                this.selectedItem = null;
                this.qtyDeliv = '';
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
                      :class="step === 1 ? 'step-label-active' : 'step-label-done'">Scan Item</span>
            </div>

            <div class="step-line" :class="step > 1 ? 'step-line-done' : ''"></div>

            <div class="step-item">
                <span class="step-circle"
                      :class="step === 2 ? 'step-circle-active' : 'step-circle-idle'">2</span>
                <span class="step-label"
                      :class="step === 2 ? 'step-label-active' : 'step-label-idle'">Input Qty</span>
            </div>
        </div>

        {{-- STEP 1 --}}
        <div x-show="step === 1" class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-accent">Scan Item</span>
                <span class="pill pill-neu text-[10.5px]">FG · FG/KOMPONEN · TRANSIT</span>
            </div>

            <div class="camera-box">
                <div id="qr-reader"></div>
            </div>

            <div class="flex gap-2">
                <input type="text" x-model="manualInput"
                       @keydown.enter.prevent="handleScan(manualInput)"
                       placeholder="Ketik Part No / Nama Barang..."
                       class="num flex-1 h-10 text-xs">
                <button type="button" @click="handleScan(manualInput)" class="btn btn-primary px-4 h-10">
                    Verifikasi
                </button>
            </div>
        </div>

        {{-- STEP 2 --}}
        <div x-show="step === 2" x-cloak class="space-y-4">
            <div class="p-3 rounded bg-slate-50 border border-slate-200 space-y-1 text-xs">
                <div class="flex justify-between">
                    <span class="num font-semibold text-accent" x-text="selectedItem?.part_no || '-'"></span>
                    <span class="num font-bold text-emerald-700" x-text="'Stok: ' + selectedItem?.stock + ' ' + selectedItem?.uom"></span>
                </div>
                <div class="font-bold text-slate-900 text-sm" x-text="selectedItem?.part_name"></div>
            </div>

            <form action="{{ route('deliveries.store') }}" method="POST" class="space-y-3.5">
                @csrf
                <input type="hidden" name="mode" value="scanner">
                <input type="hidden" name="item_id" :value="selectedItem?.id">

                <div>
                    <label class="lbl">Qty Delivery (<span x-text="selectedItem?.uom"></span>) · Maks: <b class="num" x-text="selectedItem?.stock"></b></label>
                    <input type="number" step="0.01" min="0.01" :max="selectedItem?.stock" name="qty" x-model="qtyDeliv" required placeholder="0.00"
                           class="num w-full h-14 text-2xl text-center font-bold">
                </div>

                <button type="submit"
                        :disabled="!qtyDeliv || parseFloat(qtyDeliv) <= 0 || parseFloat(qtyDeliv) > (selectedItem?.stock || 0)"
                        class="btn btn-primary btn-lg w-full">
                    Simpan Delivery (- Stok)
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
                        <div class="num text-slate-500">{{ \Carbon\Carbon::parse($scan->date_time)->format('H:i') }}</div>
                        <div class="font-semibold text-slate-900 mt-0.5">{{ $scan->item->part_name ?? '-' }}</div>
                    </div>
                    <div class="num font-bold text-rose-700 text-sm whitespace-nowrap">
                        -{{ floatval($scan->qty) }} {{ $scan->item->uom ?? '' }}
                    </div>
                </div>
            @empty
                <div class="empty py-6 text-xs">Belum ada scan Delivery hari ini.</div>
            @endforelse
        </div>
    </div>
</x-scanner-layout>