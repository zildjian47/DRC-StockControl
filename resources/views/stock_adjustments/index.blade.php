<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Stock Adjustment"
                       subtitle="Penyesuaian saldo fisik stok gudang (Stock Opname, Loss, Surplus, Found, & Repair)">
            <x-slot name="actions">
                <a href="{{ route('stock_adjustments.index', ['mode' => 'scanner']) }}" class="btn">
                    <x-icon name="scan" /> Buka Mobile Scanner
                </a>
                <button type="button" class="btn btn-primary" @click="$dispatch('open-add-adjustment')">
                    <x-icon name="plus" /> Input Adjustment
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $itemsJs = $items->map(fn ($i) => [
            'id'        => $i->id,
            'part_no'   => $i->part_no ?: '-',
            'part_name' => $i->part_name,
            'type'      => $i->type,
            'uom'       => $i->uom,
            'stock'     => (float) ($i->inventoryStock->current_stock ?? 0),
            'label'     => ($i->part_no ? $i->part_no . ' — ' : '') . $i->part_name . ' (Stok: ' . floatval($i->inventoryStock->current_stock ?? 0) . ' ' . $i->uom . ')',
        ])->values();
    @endphp

    <div class="panel"
         x-data="stockAdjustmentPage({{ $adjustments->count() }}, {{ Js::from($itemsJs) }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-adjustment.window="openAddModal()"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Riwayat Stock Adjustment</span>
                <span class="count-chip"><span x-text="visibleRows"></span> Data</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Part / Petugas / Catatan..."
                       class="h-8 text-xs px-2.5 w-48">

                <div class="flex items-center gap-1 text-xs text-slate-600">
                    <span>Tgl:</span>
                    <input type="date" x-model="startDate" @change="recalc()" class="h-8 text-xs px-2 w-32">
                    <span>-</span>
                    <input type="date" x-model="endDate" :min="startDate" @change="recalc()" class="h-8 text-xs px-2 w-32">
                </div>

                <div class="flex items-center gap-1 text-xs text-slate-600">
                    <span>Jam:</span>
                    <input type="time" x-model="startTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                    <span>-</span>
                    <input type="time" x-model="endTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                </div>

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Kategori</option>
                    @foreach($types as $tp)
                        <option value="{{ $tp }}">{{ $tp }}</option>
                    @endforeach
                </select>

                <button type="button"
                        x-show="q || startDate || endDate || startTime || endTime || fType"
                        x-cloak @click="resetFilter()" class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-12 text-center">No</th>
                        <th class="w-36 text-left col-time">Tanggal &amp; Waktu</th>
                        <th class="w-40">Petugas</th>
                        <th>Part No &amp; Part Name</th>
                        <th class="w-32 text-center">Kategori</th>
                        <th class="num w-28">Qty System</th>
                        <th class="num w-28">Qty Actual</th>
                        <th class="num w-28">Qty Diff</th>
                        <th>Catatan / Alasan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adjustments as $idx => $adj)
                        @php
                            $it = $adj->item;
                            $diff = (float) $adj->qty_diff;
                            $searchStr = strtolower(
                                ($it->part_no ?? '') . ' ' .
                                ($it->part_name ?? '') . ' ' .
                                ($adj->adjustment_type ?? '') . ' ' .
                                ($adj->notes ?? '') . ' ' .
                                ($adj->user->name ?? 'PPIC')
                            );
                        @endphp
                        <tr data-row-item
                            data-search="{{ $searchStr }}"
                            data-date="{{ \Carbon\Carbon::parse($adj->date_time)->format('Y-m-d') }}"
                            data-time="{{ \Carbon\Carbon::parse($adj->date_time)->format('H:i') }}"
                            data-type="{{ $adj->adjustment_type }}"
                            x-show="matchFilter($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">
                                {{ \Carbon\Carbon::parse($adj->date_time)->format('d M Y, H:i') }}
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $adj->user->name ?? 'PPIC' }}</div>
                                <div class="text-[11px] text-slate-500 uppercase">{{ $adj->user->role ?? 'PPIC' }}</div>
                            </td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $it->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $it->part_name ?? '-' }}</div>
                            </td>
                            <td class="text-center">
                                @if($adj->adjustment_type === 'LOSS')
                                    <span class="pill pill-crit">LOSS</span>
                                @elseif(in_array($adj->adjustment_type, ['SURPLUS', 'FOUND', 'REPAIR'], true))
                                    <span class="pill pill-good">{{ $adj->adjustment_type }}</span>
                                @else
                                    <span class="pill pill-info">{{ $adj->adjustment_type }}</span>
                                @endif
                            </td>
                            <td class="num font-medium text-slate-700 whitespace-nowrap">
                                {{ floatval($adj->qty_system) }} <span class="text-[11px] text-slate-500">{{ $it->uom ?? '' }}</span>
                            </td>
                            <td class="num font-bold text-slate-900 whitespace-nowrap">
                                {{ floatval($adj->qty_actual) }} <span class="text-[11px] font-normal text-slate-500">{{ $it->uom ?? '' }}</span>
                            </td>
                            <td class="num font-bold whitespace-nowrap {{ $diff >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $diff >= 0 ? '+' : '' }}{{ floatval($diff) }} <span class="text-[11px] font-normal text-slate-500">{{ $it->uom ?? '' }}</span>
                            </td>
                            <td class="text-xs text-slate-600">{{ $adj->notes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty">Belum ada data Stock Adjustment.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT STOCK ADJUSTMENT --}}
        <div x-show="addOpen" x-cloak
             class="modal-backdrop"
             @click.self="addOpen = false">
            <div class="panel w-full max-w-xl max-h-[90vh] flex flex-col overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Input Stock Adjustment</h3>
                    <button type="button" class="act-btn" @click="addOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('stock_adjustments.store') }}" method="POST" class="flex-1 overflow-y-auto">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div>
                            <label class="lbl">Cari &amp; Pilih Item / Barang</label>
                            <input type="text" x-model="itemSearch"
                                   placeholder="Ketik Part No atau Part Name untuk menyaring daftar..."
                                   class="w-full h-8 text-xs mb-1.5">
                            <select name="item_id" x-model="selectedItemId" @change="onSelectItem()" required class="w-full h-9 text-xs">
                                <option value="">-- Pilih Barang --</option>
                                <template x-for="it in filteredItems" :key="it.id">
                                    <option :value="it.id" x-text="it.label"></option>
                                </template>
                            </select>
                        </div>

                        <div x-show="selectedItem" class="p-3 rounded bg-slate-50 border border-slate-200 flex items-center justify-between text-xs">
                            <div>
                                <div class="num font-bold text-accent" x-text="selectedItem?.part_no"></div>
                                <div class="font-semibold text-slate-900" x-text="selectedItem?.part_name"></div>
                            </div>
                            <div class="text-right">
                                <span class="text-slate-500 block text-[11px]">Qty System Saat Ini:</span>
                                <b class="num text-sm text-slate-900" x-text="(selectedItem?.stock ?? 0) + ' ' + (selectedItem?.uom ?? '')"></b>
                            </div>
                        </div>

                        <div>
                            <label class="lbl">Kategori Adjustment</label>
                            <select name="adjustment_type" x-model="adjType" required class="w-full h-9 text-xs">
                                @foreach($types as $tp)
                                    <option value="{{ $tp }}">{{ $tp }}</option>
                                @endforeach
                            </select>
                            <p class="hint" x-text="typeRuleHint()"></p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Qty Actual / Fisik (Kalkulator + - * /)</label>
                                <input type="text" name="qty_actual" x-model="exprActual"
                                       @input="onActualInput()"
                                       placeholder="Contoh: 150 atau 3*50+10"
                                       class="num w-full h-9 text-xs font-semibold">
                            </div>
                            <div>
                                <label class="lbl">Qty Diff / Selisih +/- (Kalkulator)</label>
                                <input type="text" name="qty_diff" x-model="exprDiff"
                                       @input="onDiffInput()"
                                       placeholder="Contoh: -5 atau +10"
                                       class="num w-full h-9 text-xs font-semibold">
                            </div>
                        </div>

                        <div x-show="selectedItem && (valActual !== null || valDiff !== null)"
                             class="p-3 rounded border border-blue-200 bg-blue-50/60 text-xs space-y-1">
                            <div class="font-semibold text-slate-800">Preview Hasil Kalkulasi:</div>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span>Stok Sistem: <b class="num" x-text="sysStock + ' ' + (selectedItem?.uom ?? '')"></b></span>
                                <span>Selisih (Diff): <b class="num" :class="(valDiff ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="((valDiff ?? 0) >= 0 ? '+' : '') + (valDiff ?? 0) + ' ' + (selectedItem?.uom ?? '')"></b></span>
                                <span>Menjadi (Actual): <b class="num text-accent" x-text="(valActual ?? sysStock) + ' ' + (selectedItem?.uom ?? '')"></b></span>
                            </div>
                        </div>

                        <div>
                            <label class="lbl">Catatan / Alasan Penyesuaian</label>
                            <textarea name="notes" rows="2" placeholder="Tulis keterangan opname, temuan, atau repair..." class="w-full text-xs"></textarea>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Adjustment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function stockAdjustmentPage(initialRows, itemsList, openInitially) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fType: '',
                visibleRows: initialRows,
                items: itemsList,
                addOpen: openInitially,
                itemSearch: '',
                selectedItemId: '',
                adjType: 'STOCK OPNAME',
                exprActual: '',
                exprDiff: '',
                valActual: null,
                valDiff: null,

                get filteredItems() {
                    if (!this.itemSearch) return this.items;
                    const s = this.itemSearch.toLowerCase();
                    return this.items.filter(i => i.label.toLowerCase().includes(s));
                },
                get selectedItem() {
                    return this.items.find(i => String(i.id) === String(this.selectedItemId)) || null;
                },
                get sysStock() {
                    return this.selectedItem ? parseFloat(this.selectedItem.stock) : 0;
                },
                openAddModal() {
                    this.addOpen = true;
                },
                onSelectItem() {
                    if (this.selectedItem) {
                        this.exprActual = String(this.sysStock);
                        this.exprDiff = '0';
                        this.valActual = this.sysStock;
                        this.valDiff = 0;
                    }
                },
                evalMath(raw) {
                    if (raw === null || raw === undefined || String(raw).trim() === '') return null;
                    const expr = String(raw).replace(/,/g, '.').replace(/\s+/g, '');
                    if (!/^[0-9\.\+\-\*\/\(\)]+$/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r)) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                onActualInput() {
                    const a = this.evalMath(this.exprActual);
                    this.valActual = a;
                    if (a !== null) {
                        const d = Math.round((a - this.sysStock) * 100) / 100;
                        this.valDiff = d;
                        this.exprDiff = String(d);
                    }
                },
                onDiffInput() {
                    const d = this.evalMath(this.exprDiff);
                    this.valDiff = d;
                    if (d !== null) {
                        const a = Math.round((this.sysStock + d) * 100) / 100;
                        this.valActual = a;
                        this.exprActual = String(a);
                    }
                },
                typeRuleHint() {
                    if (this.adjType === 'LOSS') return 'LOSS: Qty Actual wajib lebih kecil dari Qty System (- Stok).';
                    if (['SURPLUS', 'FOUND', 'REPAIR'].includes(this.adjType)) return this.adjType + ': Qty Actual wajib lebih besar dari Qty System (+ Stok).';
                    return 'STOCK OPNAME: Qty Actual dapat lebih besar (+) maupun lebih kecil (-) dari Qty System.';
                },
                matchFilter(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const d = el.dataset.date || '';
                    const t = el.dataset.time || '';
                    const tp = el.dataset.type || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.startDate && d < this.startDate) return false;
                    if (this.endDate && d > this.endDate) return false;
                    if (this.startTime && t < this.startTime) return false;
                    if (this.endTime && t > this.endTime) return false;
                    if (this.fType && tp !== this.fType) return false;
                    return true;
                },
                recalc() {
                    if (this.startDate && this.endDate && this.endDate < this.startDate) {
                        this.endDate = this.startDate;
                    }
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-row-item]').forEach(tr => {
                            if (this.matchFilter(tr)) c++;
                        });
                        this.visibleRows = c;
                    });
                },
                resetFilter() {
                    this.q = '';
                    this.startDate = '';
                    this.endDate = '';
                    this.startTime = '';
                    this.endTime = '';
                    this.fType = '';
                    this.recalc();
                }
            };
        }
    </script>
</x-app-layout>