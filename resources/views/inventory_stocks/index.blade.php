<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Inventory Stock"
                       subtitle="Pemantauan saldo stok gudang real-time, standar buffer harian (Std Day 1/2/3), Ratio Stock, dan Status Stock" />
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="invStockPage({{ $stocks->count() }})"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Saldo Stok Gudang</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>Item</span></span>
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

                <button type="button" x-show="q || fType || fCompType || fStatus" x-cloak
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
                        <th class="w-[24%]">Part No &amp; Part Name</th>
                        <th class="w-[9%] text-center">Tipe</th>
                        <th class="w-[12%]">Perusahaan</th>
                        <th class="num w-[8%]">Std Day 1</th>
                        <th class="num w-[8%]">Std Day 2 (Min)</th>
                        <th class="num w-[8%]">Std Day 3 (Max)</th>
                        <th class="num w-[8%]">Ratio Stock</th>
                        <th class="num w-[9%]">Current Stock</th>
                        <th class="w-[6%] text-center">Status Stock</th>
                        <th class="w-[4%] text-center">Info</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stocks as $idx => $stock)
                        @php
                            $item = $stock->item;
                            $compCode = $item->company->company_code ?? 'INTERNAL';
                            $compType = $item->company->type_company ?? 'INTERNAL';
                            $std1 = $item->calculated_std_day_1;
                            $std2 = $item->std_day_2;
                            $std3 = $item->std_day_3;
                            $ratio = $stock->ratio_stock;
                            $status = $stock->status_stock;
                            $tyCls = 'ty-' . ($item->type === 'FG/KOMPONEN' ? 'FGK' : $item->type);
                        @endphp
                        <tr data-stock-row
                            data-search="{{ strtolower(($item->part_no ?? '') . ' ' . $item->part_name . ' ' . $compCode) }}"
                            data-type="{{ $item->type }}"
                            data-comptype="{{ $compType }}"
                            data-status="{{ $status }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $item->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900">{{ $item->part_name }}</div>
                            </td>
                            <td class="text-center">
                                <span class="ty {{ $tyCls }}">{{ $item->type }}</span>
                            </td>
                            <td>
                                <div class="font-bold text-slate-800">{{ $compCode }}</div>
                                <div class="mt-0.5">
                                    @if($compType === 'SUPPLIER')
                                        <span class="pill pill-info">SUPPLIER</span>
                                    @elseif($compType === 'INTERNAL')
                                        <span class="pill pill-warn">INTERNAL</span>
                                    @else
                                        <span class="pill pill-good">CUSTOMER</span>
                                    @endif
                                </div>
                            </td>
                            <td class="num text-slate-700">{{ floatval($std1) }} {{ $item->uom }}</td>
                            <td class="num text-amber-700 font-semibold">{{ floatval($std2) }} {{ $item->uom }}</td>
                            <td class="num text-emerald-700 font-semibold">{{ floatval($std3) }} {{ $item->uom }}</td>
                            <td class="num font-bold text-accent">{{ floatval($ratio) }} Hr</td>
                            <td class="num font-bold text-slate-900 text-sm">{{ floatval($stock->current_stock) }} {{ $item->uom }}</td>
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
                                <button type="button" class="act-btn" title="Informasi Material & Riwayat Mutasi Stok"
                                        @click="$dispatch('open-material-info', {{ Js::from($item->material_info_payload) }})">
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
                visibleCount: initialCount,
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
                        this.$el.querySelectorAll('tbody tr[data-stock-row]').forEach(tr => {
                            if (this.matchRow(tr)) c++;
                        });
                        this.visibleCount = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>