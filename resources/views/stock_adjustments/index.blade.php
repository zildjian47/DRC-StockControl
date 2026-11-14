<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Stock Adjustment"
                       subtitle="Penyesuaian saldo stok fisik gudang (STOCK OPNAME, LOSS, SURPLUS, FOUND, REPAIR)">
            <x-slot name="actions">
                <a href="{{ route('stock_adjustments.index', ['mode' => 'scanner']) }}" class="btn">
                    <x-icon name="scan" /> Buka Mobile Scanner
                </a>
                <button type="button" class="btn btn-primary" @click="$dispatch('open-add-adj')">
                    <x-icon name="plus" /> Input Adjustment
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $itemsJs = $items->map(fn ($i) => [
            'id'    => $i->id,
            'part'  => ($i->part_no ? $i->part_no . ' — ' : '') . $i->part_name,
            'uom'   => $i->uom,
            'stock' => $i->inventoryStock ? (float) $i->inventoryStock->current_stock : 0,
        ])->values();
    @endphp

    <div class="panel"
         x-data="stockAdjPage({{ $adjustments->count() }}, {{ Js::from($itemsJs) }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-adj.window="addOpen = true"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Riwayat Stock Adjustment</span>
                <span class="count-chip"><span x-text="visibleRows"></span><span>Data</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Part / Petugas / Catatan..."
                       class="h-8 text-xs px-2.5 w-48">

                <div class="flex items-center gap-1 text-xs text-slate-600">
                    <span>Tgl:</span>
                    <input type="date" x-model="startDate"
                           @change="if(endDate && endDate < startDate) endDate = startDate; recalc()"
                           class="h-8 text-xs px-2 w-32">
                    <span>-</span>
                    <input type="date" x-model="endDate" :min="startDate" @change="recalc()"
                           class="h-8 text-xs px-2 w-32">
                </div>

                <div class="flex items-center gap-1 text-xs text-slate-600">
                    <span>Jam:</span>
                    <input type="time" x-model="startTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                    <span>-</span>
                    <input type="time" x-model="endTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                </div>

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Kategori</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>

                <button type="button"
                        x-show="q || startDate || endDate || startTime || endTime || fType"
                        x-cloak
                        @click="resetFilter()"
                        class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[5%] text-center">No</th>
                        <th class="w-[14%] text-left col-time">Tanggal &amp; Waktu</th>
                        <th class="w-[13%]">Petugas</th>
                        <th class="w-[22%]">Part No &amp; Part Name</th>
                        <th class="w-[11%] text-center">Kategori</th>
                        <th class="num w-[9%]">Qty System</th>
                        <th class="num w-[9%]">Qty Actual</th>
                        <th class="num w-[9%]">Qty Diff</th>
                        <th class="w-[8%]">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adjustments as $idx => $adj)
                        @php
                            $dt = \Carbon\Carbon::parse($adj->date_time);
                            $diff = (float) $adj->qty_diff;
                            $searchStr = strtolower(
                                ($adj->item->part_no ?? '') . ' ' .
                                ($adj->item->part_name ?? '') . ' ' .
                                ($adj->adjustment_type ?? '') . ' ' .
                                ($adj->user->name ?? '') . ' ' .
                                ($adj->notes ?? '')
                            );
                        @endphp
                        <tr data-adj-row
                            data-search="{{ $searchStr }}"
                            data-date="{{ $dt->format('Y-m-d') }}"
                            data-time="{{ $dt->format('H:i') }}"
                            data-type="{{ $adj->adjustment_type }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">{{ $dt->format('d M Y, H:i') }}</td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $adj->user->name ?? 'PPIC' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $adj->user->role ?? 'PPIC' }}</div>
                            </td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $adj->item->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $adj->item->part_name ?? '-' }}</div>
                            </td>
                            <td class="text-center">
                                @if($adj->adjustment_type === 'LOSS')
                                    <span class="pill pill-crit">LOSS</span>
                                @elseif(in_array($adj->adjustment_type, ['SURPLUS', 'FOUND', 'REPAIR']))
                                    <span class="pill pill-good">{{ $adj->adjustment_type }}</span>
                                @else
                                    <span class="pill pill-info">{{ $adj->adjustment_type }}</span>
                                @endif
                            </td>
                            <td class="num font-semibold text-slate-600">{{ floatval($adj->qty_system) }}</td>
                            <td class="num font-bold text-slate-900">{{ floatval($adj->qty_actual) }}</td>
                            <td class="num whitespace-nowrap">
                                <span class="font-bold {{ $diff > 0 ? 'text-emerald-700' : ($diff < 0 ? 'text-rose-700' : 'text-slate-600') }}">
                                    {{ $diff > 0 ? '+' : '' }}{{ floatval($diff) }}
                                </span>
                                <span class="text-[11px] text-slate-500">{{ $adj->item->uom ?? '' }}</span>
                            </td>
                            <td class="text-xs text-slate-600">{{ $adj->notes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty">Belum ada riwayat Stock Adjustment.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT STOCK ADJUSTMENT --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false">
            <div class="panel w-full max-w-xl overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Input Stock Adjustment Baru</h3>
                    <button type="button" class="act-btn" @click="addOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>
                <form action="{{ route('stock_adjustments.store') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Pilih Item (Klik &amp; Ketik untuk Mencari)</label>
                                <select name="item_id" x-model="selectedItemId" @change="onSelectItem()" required class="w-full h-9 text-xs">
                                    <option value="">-- Pilih Item --</option>
                                    @foreach($items as $it)
                                        <option value="{{ $it->id }}">
                                            {{ ($it->part_no ? $it->part_no . ' — ' : '') . $it->part_name }} (Stok: {{ floatval($it->inventoryStock->current_stock ?? 0) }} {{ $it->uom }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Kategori Adjustment</label>
                                <select name="adjustment_type" x-model="adjType" required class="w-full h-9 text-xs">
                                    @foreach($types as $t)
                                        <option value="{{ $t }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div x-show="selectedItem" class="p-3 rounded bg-slate-50 border border-slate-200 flex items-center justify-between text-xs">
                            <span class="text-slate-600">Stok Sistem Saat Ini (<b x-text="selectedItem?.part"></b>):</span>
                            <b class="num text-sm text-accent" x-text="qtySystem + ' ' + (selectedItem?.uom || '')"></b>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Qty Actual / Fisik (Kalkulator: 3*50+10)</label>
                                <input type="text" name="qty_actual" x-model="rawActual" @input="onInputActual()"
                                       placeholder="Contoh: 120 atau 3*40" class="num w-full h-9 font-semibold">
                            </div>
                            <div>
                                <label class="lbl">Qty Diff / Selisih (+/-)</label>
                                <input type="text" name="qty_diff" x-model="rawDiff" @input="onInputDiff()"
                                       placeholder="Contoh: -5 atau +10" class="num w-full h-9 font-semibold">
                            </div>
                        </div>

                        <div x-show="previewText" class="p-2.5 rounded bg-blue-50/70 border border-blue-200 text-xs text-blue-900 font-semibold num"
                             x-text="previewText"></div>

                        <div>
                            <label class="lbl">Catatan / Keterangan (Opsional)</label>
                            <input type="text" name="notes" placeholder="Contoh: Koreksi hasil stock opname bulanan" class="w-full h-9 text-xs">
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
        function stockAdjPage(initialCount, itemsList, openInitially) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fType: '',
                visibleRows: initialCount,
                itemsList: itemsList,
                addOpen: openInitially,
                selectedItemId: '',
                adjType: 'STOCK OPNAME',
                qtySystem: 0,
                rawActual: '',
                rawDiff: '',
                previewText: '',

                get selectedItem() {
                    return this.itemsList.find(i => String(i.id) === String(this.selectedItemId)) || null;
                },
                evalCalc(expr) {
                    if (expr === null || expr === undefined) return null;
                    const clean = String(expr).replace(/,/g, '.').replace(/\s+/g, '');
                    if (!clean || !/^[0-9\.\+\-\*\/\(\)]+$/.test(clean)) return null;
                    try {
                        const r = Function('return (' + clean + ')')();
                        return (typeof r === 'number' && isFinite(r)) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                onSelectItem() {
                    const it = this.selectedItem;
                    this.qtySystem = it ? it.stock : 0;
                    if (this.rawActual) this.onInputActual();
                    else if (this.rawDiff) this.onInputDiff();
                },
                onInputActual() {
                    const val = this.evalCalc(this.rawActual);
                    if (val === null) {
                        this.previewText = '';
                        return;
                    }
                    const diff = Math.round((val - this.qtySystem) * 100) / 100;
                    this.rawDiff = (diff > 0 ? '+' : '') + diff;
                    const uom = this.selectedItem?.uom || '';
                    this.previewText = `Hasil Qty Actual = ${val} ${uom} | Selisih (Qty Diff) = ${diff > 0 ? '+' : ''}${diff} ${uom} (Stok Sistem: ${this.qtySystem} → Menjadi: ${val} ${uom})`;
                },
                onInputDiff() {
                    const diff = this.evalCalc(this.rawDiff);
                    if (diff === null) {
                        this.previewText = '';
                        return;
                    }
                    const act = Math.round((this.qtySystem + diff) * 100) / 100;
                    this.rawActual = String(act);
                    const uom = this.selectedItem?.uom || '';
                    this.previewText = `Hasil Qty Actual = ${act} ${uom} | Selisih (Qty Diff) = ${diff > 0 ? '+' : ''}${diff} ${uom} (Stok Sistem: ${this.qtySystem} → Menjadi: ${act} ${uom})`;
                },
                matchRow(el) {
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
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-adj-row]').forEach(tr => {
                            if (this.matchRow(tr)) c++;
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