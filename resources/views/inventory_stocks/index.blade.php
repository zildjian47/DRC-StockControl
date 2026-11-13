<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Inventory Stock"
                       subtitle="Monitoring saldo stok aktual, standar buffer harian (Std Day 1/2/3), rasio ketahanan hari, dan status stok" />
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="inventoryStockPage({{ $stocks->count() }})"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Inventory Stock</span>
                <span class="count-chip"><span x-text="visibleRows"></span> Item</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Part No / Part Name..."
                       class="h-8 text-xs px-2.5 w-48">

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe Item</option>
                    @foreach($types as $tp)
                        <option value="{{ $tp }}">{{ $tp }}</option>
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
                        <th>Part No &amp; Part Name</th>
                        <th class="w-28 text-center">Tipe</th>
                        <th class="w-36 text-center">Perusahaan</th>
                        <th class="num w-24">Std Day 1</th>
                        <th class="num w-28">Std Day 2 (Min)</th>
                        <th class="num w-28">Std Day 3 (Max)</th>
                        <th class="num w-28">Ratio Stock</th>
                        <th class="num w-36">Current Stock</th>
                        <th class="w-36 text-center">Status Stock</th>
                        <th class="w-16 text-center">Info</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stocks as $idx => $st)
                        @php
                            $it = $st->item;
                            $comp = $it->company ?? null;
                            $compType = $comp->type_company ?? 'INTERNAL';
                            $std1 = (float) ($it->calculated_std_day_1 ?? 0);
                            $std2 = (float) ($it->std_day_2 ?? 0);
                            $std3 = (float) ($it->std_day_3 ?? 0);
                            $ratio = (float) $st->ratio_stock;
                            $curr = (float) $st->current_stock;
                            $status = $st->status_stock;
                            $searchStr = strtolower(
                                ($it->part_no ?? '') . ' ' .
                                ($it->part_name ?? '') . ' ' .
                                ($it->type ?? '') . ' ' .
                                ($comp->company_code ?? '') . ' ' .
                                ($comp->company_name ?? '') . ' ' .
                                $compType . ' ' .
                                $status
                            );
                        @endphp
                        <tr data-row-item
                            data-search="{{ $searchStr }}"
                            data-type="{{ $it->type ?? '' }}"
                            data-comptype="{{ $compType }}"
                            data-status="{{ $status }}"
                            x-show="matchFilter($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $it->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $it->part_name ?? '-' }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-neu">{{ $it->type ?? '-' }}</span>
                            </td>
                            <td class="text-center">
                                <div class="num text-xs font-bold text-accent">{{ $comp->company_code ?? 'INTERNAL' }}</div>
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
                            <td class="num font-medium text-slate-700">{{ floatval($std1) }}</td>
                            <td class="num font-medium text-slate-700">{{ floatval($std2) }}</td>
                            <td class="num font-medium text-slate-700">{{ floatval($std3) }}</td>
                            <td class="num font-bold text-accent whitespace-nowrap">
                                {{ floatval($ratio) }} <span class="text-[11px] font-normal text-slate-500">Hari</span>
                            </td>
                            <td class="num whitespace-nowrap">
                                <span class="text-sm font-bold text-slate-900">{{ floatval($curr) }}</span>
                                <span class="text-xs text-slate-500 ml-1">{{ $it->uom ?? '' }}</span>
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
                                <button type="button" class="act-btn"
                                        title="Informasi Material, BOM & Riwayat Mutasi"
                                        @click="$dispatch('open-material-info', {{ Js::from($it->material_info_payload) }})">
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
        function inventoryStockPage(initialRows) {
            return {
                q: '',
                fType: '',
                fCompType: '',
                fStatus: '',
                visibleRows: initialRows,
                matchFilter(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const tp = el.dataset.type || '';
                    const ct = el.dataset.comptype || '';
                    const st = el.dataset.status || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.fType && tp !== this.fType) return false;
                    if (this.fCompType && ct !== this.fCompType) return false;
                    if (this.fStatus && st !== this.fStatus) return false;
                    return true;
                },
                recalc() {
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
                    this.fType = '';
                    this.fCompType = '';
                    this.fStatus = '';
                    this.recalc();
                }
            };
        }
    </script>
</x-app-layout>