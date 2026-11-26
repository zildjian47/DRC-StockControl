<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Inventory Stock"
                       subtitle="Monitoring saldo stok fisik real-time, standar buffer harian (Std 1/2/3), Ratio Stock (Hari), dan status ketersediaan">
            <x-slot name="actions">
                <span class="btn-group">
                    <button type="button" class="btn" @click="$dispatch('export-inv-stock', 'xlsx')"
                            title="Export data sesuai filter aktif ke Excel (.xlsx)">
                        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        <span>Excel</span>
                    </button>
                    <button type="button" class="btn" @click="$dispatch('export-inv-stock', 'pdf')"
                            title="Export data sesuai filter aktif ke PDF">
                        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                        <span>PDF</span>
                    </button>
                </span>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="invStockPage({{ $stocks->count() }}, {{ Js::from(route('exports.stock')) }})"
         @export-inv-stock.window="exportData($event.detail)"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Saldo Inventory Gudang</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>ITEM</span></span>
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
                        @click="q=''; fType=''; fCompType=''; fStatus=''; recalc()" class="btn btn-sm">
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
                        <th class="num w-[8%]">Std 2 (Min)</th>
                        <th class="num w-[8%]">Std 3 (Max)</th>
                        <th class="num w-[8%]">Ratio Stock</th>
                        <th class="num w-[9%]">Current Stock</th>
                        <th class="w-[6%] text-center">Status</th>
                        <th class="w-[4%] text-center">Info</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stocks as $idx => $st)
                        @php
                            $itm      = $st->item;
                            $compCode = $itm->company->company_code ?? 'INTERNAL';
                            $compType = $itm->company->type_company ?? 'INTERNAL';
                            $std1     = $itm->calculated_std_day_1;
                            $std2     = $itm->std_day_2;
                            $std3     = $itm->std_day_3;
                            $ratio    = $st->ratio_stock;
                            $status   = $st->status_stock;
                            $tyCls    = 'ty-' . ($itm->type === 'FG/KOMPONEN' ? 'FGK' : $itm->type);
                        @endphp
                        <tr data-inv-row
                            data-search="{{ strtolower(($itm->part_no ?? '') . ' ' . $itm->part_name . ' ' . $compCode) }}"
                            data-type="{{ $itm->type }}"
                            data-comptype="{{ $compType }}"
                            data-status="{{ $status }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500" data-col-no>{{ $idx + 1 }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $itm->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900">{{ $itm->part_name }}</div>
                            </td>
                            <td class="text-center">
                                <span class="ty {{ $tyCls }}">{{ $itm->type }}</span>
                            </td>
                            <td>
                                <div class="font-bold text-xs text-accent">{{ $compCode }}</div>
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
                            <td class="num text-slate-700">{{ floatval($std1) }}</td>
                            <td class="num text-slate-700">{{ floatval($std2) }}</td>
                            <td class="num text-slate-700">{{ floatval($std3) }}</td>
                            <td class="num font-bold text-accent">{{ floatval($ratio) }} <span class="text-[11px] font-normal text-slate-500">Hr</span></td>
                            <td class="num">
                                <span class="font-bold text-[13.5px] {{ $st->current_stock <= 0 ? 'text-rose-700' : 'text-slate-900' }}">
                                    {{ floatval($st->current_stock) }}
                                </span>
                                <span class="text-[11px] text-slate-500">{{ $itm->uom }}</span>
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
                                <button type="button" class="act-btn" title="Informasi Material, BOM & Riwayat Mutasi"
                                        @click="$dispatch('open-material-info', {{ Js::from(['id' => $itm->id, 'part_no' => $itm->part_no ?: '-', 'part_name' => $itm->part_name]) }})">
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
        function invStockPage(initialCount, exportBase) {
            return {
                q: '',
                fType: '',
                fCompType: '',
                fStatus: '',
                visibleCount: initialCount,
                exportBase: exportBase,

                exportData(format) {
                    const params = new URLSearchParams();
                    params.set('format', format || 'xlsx');
                    params.set('snapshot_mode', 'now');
                    if (this.q.trim()) params.set('q', this.q.trim());
                    if (this.fType) params.set('type', this.fType);
                    if (this.fCompType) params.set('company_type', this.fCompType);
                    if (this.fStatus) params.set('status', this.fStatus);
                    window.location.href = `${this.exportBase}?${params.toString()}`;
                },
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
                            if (this.matchRow(tr)) {
                                c++;
                                const noCell = tr.querySelector('[data-col-no]');
                                if (noCell) noCell.textContent = c;
                            }
                        });
                        this.visibleCount = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>