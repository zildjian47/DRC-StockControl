<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Stock Adjustment"
                       subtitle="Penyesuaian saldo stok fisik terhadap sistem (Stock Opname, Loss, Surplus, Found, & Repair)">
            <x-slot name="actions">
                <span class="btn-group">
                    <a href="{{ route('stock_adjustments.index', ['mode' => 'scanner']) }}" class="btn">
                        <x-icon name="scan" /> <span>Buka Mobile Scanner</span>
                    </a>
                    <button type="button" class="btn btn-primary" @click="$dispatch('open-add-adj')">
                        <x-icon name="plus" /> <span>Input Adjustment</span>
                    </button>
                </span>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $itemsJs = $items->map(fn ($i) => [
            'id'    => $i->id,
            'part'  => ($i->part_no ? $i->part_no . ' — ' : '') . $i->part_name,
            'uom'   => $i->uom,
            'stock' => (float) ($i->inventoryStock->current_stock ?? 0),
        ])->values();
    @endphp

    <div class="panel"
         x-data="stockAdjPage({{ $adjustments->count() }}, {{ Js::from($itemsJs) }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-adj.window="addOpen = true"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Riwayat Penyesuaian Stok (Stock Adjustment)</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>Data</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Part No / Part Name / Petugas..."
                       class="h-8 text-xs px-2.5 w-48">

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Kategori</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>

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

                <button type="button"
                        x-show="q || fType || startDate || endDate || startTime || endTime" x-cloak
                        @click="q=''; fType=''; startDate=''; endDate=''; startTime=''; endTime=''; recalc()"
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
                        <th class="w-[15%] col-time">Tanggal &amp; Waktu</th>
                        <th class="w-[14%]">Petugas</th>
                        <th class="w-[24%]">Part No &amp; Part Name</th>
                        <th class="w-[12%] text-center">Kategori</th>
                        <th class="num w-[9%]">Qty System</th>
                        <th class="num w-[9%]">Qty Actual</th>
                        <th class="num w-[12%]">Qty Diff (+/-)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adjustments as $idx => $adj)
                        @php
                            $dt = \Carbon\Carbon::parse($adj->date_time);
                            $uName = $adj->user->name ?? 'PPIC';
                            $pNo = $adj->item->part_no ?: '-';
                            $pName = $adj->item->part_name ?? '-';
                            $pType = $adj->item->type ?? 'FG';
                            $tyCls = 'ty-' . ($pType === 'FG/KOMPONEN' ? 'FGK' : $pType);
                            $uom = $adj->item->uom ?? '';
                            $diff = (float) $adj->qty_diff;
                            $searchStr = strtolower($pNo . ' ' . $pName . ' ' . $uName . ' ' . $adj->adjustment_type . ' ' . ($adj->notes ?? ''));
                        @endphp
                        <tr data-adj-row
                            data-search="{{ $searchStr }}"
                            data-type="{{ $adj->adjustment_type }}"
                            data-date="{{ $dt->format('Y-m-d') }}"
                            data-time="{{ $dt->format('H:i') }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num col-time whitespace-nowrap">{{ $dt->format('d M Y, H:i') }}</td>
                            <td>
                                <div class="font-semibold text-slate-800">{{ $uName }}</div>
                                <div class="text-[11px] text-slate-500">{{ $adj->user->role ?? 'PPIC' }}</div>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="ty {{ $tyCls }}">{{ $pType }}</span>
                                    <span class="num text-xs font-bold text-accent">{{ $pNo }}</span>
                                </div>
                                <div class="font-semibold text-slate-900 mt-0.5">{{ $pName }}</div>
                                @if($adj->notes)
                                    <div class="text-[11px] text-slate-500 mt-0.5">Catatan: {{ $adj->notes }}</div>
                                @endif
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
                            <td class="num text-slate-600">{{ floatval($adj->qty_system) }} {{ $uom }}</td>
                            <td class="num font-semibold text-slate-900">{{ floatval($adj->qty_actual) }} {{ $uom }}</td>
                            <td class="num font-bold {{ $diff >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $diff > 0 ? '+' : '' }}{{ floatval($diff) }} {{ $uom }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty">Belum ada data Stock Adjustment.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT STOCK ADJUSTMENT (Dua Panel, Panel Kiri Abu, Bebas Scroll Kanan-Kiri) --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false" @keydown.escape.window="addOpen = false">
            <div class="panel dlg w-full max-w-3xl" role="dialog" aria-label="Input Stock Adjustment Baru">
                <div class="panel-head">
                    <h3 class="panel-title">Input Stock Adjustment Baru</h3>
                    <button type="button" class="act-btn" @click="addOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('stock_adjustments.store') }}" method="POST" class="flex flex-col min-h-0 flex-1">
                    @csrf
                    <div class="dlg-split">
                        <aside class="dlg-side hide-sm">
                            <div class="dlg-k">Ringkasan koreksi</div>
                            <template x-if="selectedItem">
                                <div>
                                    <div class="dlg-name" x-text="selectedItem.part"></div>
                                </div>
                            </template>
                            <template x-if="!selectedItem">
                                <p class="hint">Pilih item untuk melihat selisih stok sistem dan stok fisik.</p>
                            </template>
                            <div class="dlg-list">
                                <div><span>Stok sistem</span><b class="num" x-text="qtySystem + ' ' + (selectedItem?.uom || '')"></b></div>
                                <div><span>Qty actual</span><b class="num" x-text="resultActual + ' ' + (selectedItem?.uom || '')"></b></div>
                            </div>
                            <div>
                                <div class="dlg-k">Selisih</div>
                                <div class="num" style="font-size: 30px; font-weight: 600; line-height: 1.1; margin-top: 4px;"
                                     :style="resultDiff > 0 ? 'color: var(--good)' : (resultDiff < 0 ? 'color: var(--crit)' : 'color: var(--ink)')"
                                     x-text="(resultDiff > 0 ? '+' : '') + resultDiff"></div>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <div>
                                <label class="lbl" for="adj_item">Pilih Item (Klik &amp; Ketik untuk Mencari)</label>
                                <select id="adj_item" name="item_id" x-model="selectedItemId" @change="onSelectItem()" required class="w-full h-9 text-xs">
                                    <option value="">-- Pilih Item --</option>
                                    @foreach($items as $it)
                                        <option value="{{ $it->id }}">
                                            {{ ($it->part_no ? $it->part_no . ' — ' : '') . $it->part_name }} (Stok: {{ floatval($it->inventoryStock->current_stock ?? 0) }} {{ $it->uom }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <span class="lbl">Kategori Adjustment</span>
                                <div class="opt-chips">
                                    @foreach($types as $t)
                                        <label>
                                            <input type="radio" name="adjustment_type" value="{{ $t }}" x-model="adjType" required>
                                            <span>{{ $t }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="lbl" for="adj_actual">Qty Actual / Fisik (Kalkulator: 3*50+10)</label>
                                    <input id="adj_actual" type="text" name="qty_actual" x-model="rawActual" @input="onInputActual()"
                                           placeholder="Contoh: 120 atau 3*40" class="num w-full h-9 font-semibold">
                                </div>
                                <div>
                                    <label class="lbl" for="adj_diff">Qty Diff / Selisih (+/-)</label>
                                    <input id="adj_diff" type="text" name="qty_diff" x-model="rawDiff" @input="onInputDiff()"
                                           placeholder="Contoh: -5 atau +10" class="num w-full h-9 font-semibold">
                                </div>
                            </div>

                            <div>
                                <label class="lbl" for="adj_notes">Catatan / Keterangan (Opsional)</label>
                                <input id="adj_notes" type="text" name="notes" placeholder="Contoh: Koreksi hasil stock opname bulanan" class="w-full h-9 text-xs">
                            </div>
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
                fType: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                addOpen: openInitially,
                visibleCount: initialCount,
                itemsList: itemsList,
                selectedItemId: '',
                adjType: 'STOCK OPNAME',
                qtySystem: 0,
                rawActual: '',
                rawDiff: '',
                previewText: '',

                get selectedItem() {
                    return this.itemsList.find(x => String(x.id) === String(this.selectedItemId)) || null;
                },
                get resultActual() {
                    const a = this.evalCalc(this.rawActual);
                    return a === null ? this.qtySystem : a;
                },
                get resultDiff() {
                    const d = this.evalCalc(this.rawDiff);
                    return d === null ? 0 : d;
                },
                evalCalc(expr) {
                    if (expr === null || expr === undefined) return null;
                    const s = String(expr).replace(/,/g, '.').replace(/[xX]/g, '*').replace(/:/g, '/').replace(/\s+/g, '');
                    if (!s || !/^[0-9.+\-*\/()]+$/.test(s)) return null;
                    try {
                        const val = Function('return (' + s + ')')();
                        return (typeof val === 'number' && isFinite(val)) ? Math.round(val * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                onSelectItem() {
                    const it = this.selectedItem;
                    this.qtySystem = it ? it.stock : 0;
                    this.rawActual = '';
                    this.rawDiff = '';
                    this.previewText = '';
                },
                onInputActual() {
                    const act = this.evalCalc(this.rawActual);
                    if (act === null) {
                        this.rawDiff = '';
                        this.previewText = '';
                        return;
                    }
                    const diff = Math.round((act - this.qtySystem) * 100) / 100;
                    this.rawDiff = (diff > 0 ? '+' : '') + diff;
                    const u = this.selectedItem?.uom || '';
                    this.previewText = `Hasil Qty Actual = ${act} ${u} | Selisih = ${this.rawDiff} ${u}`;
                },
                onInputDiff() {
                    const diff = this.evalCalc(this.rawDiff);
                    if (diff === null) {
                        this.rawActual = '';
                        this.previewText = '';
                        return;
                    }
                    const act = Math.round((this.qtySystem + diff) * 100) / 100;
                    this.rawActual = String(act);
                    const u = this.selectedItem?.uom || '';
                    const sign = diff > 0 ? '+' : '';
                    this.previewText = `Hasil Qty Actual = ${act} ${u} | Selisih = ${sign}${diff} ${u}`;
                },
                matchRow(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const t = el.dataset.type || '';
                    const d = el.dataset.date || '';
                    const tm = el.dataset.time || '';
                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.fType && t !== this.fType) return false;
                    if (this.startDate && d < this.startDate) return false;
                    if (this.endDate && d > this.endDate) return false;
                    if (this.startTime && tm < this.startTime) return false;
                    if (this.endTime && tm > this.endTime) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-adj-row]').forEach(tr => {
                            if (this.matchRow(tr)) c++;
                        });
                        this.visibleCount = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>