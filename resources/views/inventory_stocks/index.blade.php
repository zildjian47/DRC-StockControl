<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Inventory Stock"
                       subtitle="Monitoring saldo stok real-time, parameter buffer harian (Std Day 1/2/3), Ratio Stock (Hari), dan 5 level status stok" />
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="invStockPage({{ $stocks->count() }})"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Saldo Inventory Stock</span>
                <span class="count-chip"><span x-text="visibleRows"></span><span>Item</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Part No / Part Name..."
                       class="h-8 text-xs px-2.5 w-48">

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe Item</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>

                <select x-model="fCompType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe Perusahaan</option>
                    @foreach($companyTypes as $ct)
                        <option value="{{ $ct }}">{{ $ct }}</option>
                    @endforeach
                </select>

                <select x-model="fStatus" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Status Stock</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st }}">{{ $st }}</option>
                    @endforeach
                </select>

                <button type="button"
                        x-show="q || fType || fCompType || fStatus"
                        x-cloak
                        @click="q=''; fType=''; fCompType=''; fStatus=''; recalc()"
                        class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[4%] text-center">No</th>
                        <th class="w-[23%]">Part No &amp; Part Name</th>
                        <th class="w-[9%] text-center">Tipe Item</th>
                        <th class="w-[12%]">Perusahaan</th>
                        <th class="num w-[8%]">Std Day 1</th>
                        <th class="num w-[8%]">Std Day 2 (Min)</th>
                        <th class="num w-[8%]">Std Day 3 (Max)</th>
                        <th class="num w-[7%]">Ratio Stock</th>
                        <th class="num w-[9%]">Current Stock</th>
                        <th class="w-[8%] text-center">Status Stock</th>
                        <th class="w-[4%] text-center">Info</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stocks as $idx => $st)
                        @php
                            $item = $st->item;
                            $comp = $item->company;
                            $compType = $comp->type_company ?? 'INTERNAL';
                            $std1 = $item->calculated_std_day_1;
                            $std2 = $item->std_day_2;
                            $std3 = $item->std_day_3;
                            $ratio = $st->ratio_stock;
                            $status = $st->status_stock;
                            $payload = $item->material_info_payload;
                            $searchStr = strtolower(
                                ($item->part_no ?? '') . ' ' .
                                ($item->part_name ?? '') . ' ' .
                                ($item->type ?? '') . ' ' .
                                ($comp->company_code ?? '') . ' ' .
                                ($comp->company_name ?? '')
                            );
                        @endphp
                        <tr data-inv-row
                            data-search="{{ $searchStr }}"
                            data-type="{{ $item->type }}"
                            data-comptype="{{ $compType }}"
                            data-status="{{ $status }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $item->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $item->part_name }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-neu">{{ $item->type }}</span>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $comp->company_code ?? '-' }}</div>
                                <div class="mt-0.5">
                                    @if($compType === 'SUPPLIER')
                                        <span class="pill pill-info text-[10px] h-4 px-1.5">SUPPLIER</span>
                                    @elseif($compType === 'INTERNAL')
                                        <span class="pill pill-warn text-[10px] h-4 px-1.5">INTERNAL</span>
                                    @else
                                        <span class="pill pill-good text-[10px] h-4 px-1.5">CUSTOMER</span>
                                    @endif
                                </div>
                            </td>
                            <td class="num font-semibold text-slate-700">{{ floatval($std1) }}</td>
                            <td class="num font-semibold text-slate-700">{{ floatval($std2) }}</td>
                            <td class="num font-semibold text-slate-700">{{ floatval($std3) }}</td>
                            <td class="num">
                                <span class="font-bold text-accent">{{ floatval($ratio) }}</span>
                                <span class="text-[11px] text-slate-500">Hr</span>
                            </td>
                            <td class="num whitespace-nowrap">
                                <div class="inline-flex items-baseline justify-end gap-1.5 leading-none">
                                    <span class="num text-sm font-bold text-slate-900">{{ floatval($st->current_stock) }}</span>
                                    <span class="text-xs font-medium text-slate-500">{{ $item->uom }}</span>
                                </div>
                            </td>
                            <td class="text-center">
                                @if($status === 'EMPTY STOCK')
                                    <span class="pill pill-empty">EMPTY STOCK</span>
                                @elseif($status === 'VERY LOW')
                                    <span class="pill pill-crit">VERY LOW</span>
                                @elseif($status === 'LOW STOCK')
                                    <span class="pill pill-warn">LOW STOCK</span>
                                @elseif($status === 'NORMAL STOCK')
                                    <span class="pill pill-good">NORMAL STOCK</span>
                                @else
                                    <span class="pill pill-info">OVERSTOCK</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="act-btn" title="Info Material, BOM & Riwayat Mutasi"
                                        @click="$dispatch('open-material-info', {{ Js::from($payload) }})">
                                    <x-icon name="info" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><div class="empty">Belum ada data Inventory Stock.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-material-info-modal />

    <script>
        function invStockPage(initialCount) {
            return {
                q: '',
                fType: '',
                fCompType: '',
                fStatus: '',
                visibleRows: initialCount,
                matchRow(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const t = el.dataset.type || '';
                    const ct = el.dataset.comptype || '';
                    const st = el.dataset.status || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.fType && t !== this.fType) return false;
                    if (this.fCompType && ct !== this.fCompType) return false;
                    if (this.fStatus && st !== this.fStatus) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-inv-row]').forEach(tr => {
                            if (this.matchRow(tr)) c++;
                        });
                        this.visibleRows = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>