<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Material Out"
                       subtitle="Riwayat pengeluaran material produksi ke lantai mesin (Trigger Utama & Komponen Pendamping)">
            <x-slot name="actions">
                <a href="{{ route('material_outs.index', ['mode' => 'scanner']) }}" class="btn btn-primary">
                    <x-icon name="scan" /> Buka Mobile Scanner
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="matOutPage({{ $groupedOuts->count() }}, {{ $materialOuts->count() }})"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Transaksi Material Out</span>
                <span class="count-chip" x-show="viewMode === 'grouped'">
                    <span x-text="visibleGroups"></span><span>Kelompok</span>
                </span>
                <span class="count-chip" x-show="viewMode === 'flat'" x-cloak>
                    <span x-text="visibleFlat"></span><span>Data</span>
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="seg" role="group" aria-label="Mode tampilan">
                    <label>
                        <input type="radio" value="grouped" x-model="viewMode" @change="recalc()">
                        <span>Kelompok</span>
                    </label>
                    <label>
                        <input type="radio" value="flat" x-model="viewMode" @change="recalc()">
                        <span>Per Baris</span>
                    </label>
                </div>

                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Lot / Part / Petugas..."
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

                <select x-model="fBomType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe BOM</option>
                    <option value="SINGLE">SINGLE</option>
                    <option value="MULTI-INPUT">MULTI-INPUT</option>
                    <option value="MULTI-OUTPUT">MULTI-OUTPUT</option>
                    <option value="KOMBINASI">KOMBINASI</option>
                </select>

                <select x-model="fStatus" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Status</option>
                    <option value="TERCATAT">TERCATAT</option>
                    <option value="DIKOREKSI">DIKOREKSI</option>
                </select>

                <button type="button"
                        x-show="q || startDate || endDate || startTime || endTime || fBomType || fStatus"
                        x-cloak
                        @click="resetFilter()"
                        class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        {{-- MODE 1: TAMPILAN KELOMPOK (TRIGGER + PENDAMPING SEJAJAR SIMETRIS) --}}
        <div class="tbl-wrap" x-show="viewMode === 'grouped'">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[4%] text-center">No</th>
                        <th class="w-[12%] text-left col-time">Tanggal &amp; Waktu</th>
                        <th class="w-[11%]">Petugas Scan</th>
                        <th class="w-[11%]">No. Lot &amp; Tipe BOM</th>
                        <th class="w-[24%]">Material &amp; Pendamping</th>
                        <th class="num w-[11%]">Qty Out</th>
                        <th class="w-[15%]">Output Item</th>
                        <th class="w-[7%] text-center">Status</th>
                        <th class="w-[5%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groupedOuts as $idx => $grp)
                        @php
                            $first = $grp['first'];
                            $matItem = $first->materialItem;
                            $bType = $grp['bom_type'];
                            $stLabel = $grp['status_label'];
                        @endphp
                        <tr data-grp-row
                            data-search="{{ $grp['search_index'] }}"
                            data-date="{{ $grp['date_only'] }}"
                            data-time="{{ $grp['time_only'] }}"
                            data-bomtype="{{ $bType }}"
                            data-status="{{ $stLabel }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500 border-r border-slate-200">{{ $idx + 1 }}</td>
                            <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600 border-r border-slate-200">
                                {{ \Carbon\Carbon::parse($first->date_time)->format('d M Y, H:i') }}
                            </td>
                            <td class="border-r border-slate-200">
                                <div class="font-semibold text-slate-900 text-xs">{{ $first->user->name ?? 'Operator' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $first->user->role ?? 'STAFF' }}</div>
                            </td>
                            <td class="border-r border-slate-200">
                                <div class="num font-bold text-accent text-xs">{{ $grp['lot_number'] }}</div>
                                <div class="mt-1">
                                    @if($bType === 'SINGLE')
                                        <span class="pill pill-neu">SINGLE</span>
                                    @elseif($bType === 'MULTI-INPUT')
                                        <span class="pill pill-info">MULTI-INPUT</span>
                                    @elseif($bType === 'MULTI-OUTPUT')
                                        <span class="pill pill-warn">MULTI-OUTPUT</span>
                                    @else
                                        <span class="pill pill-good">KOMBINASI</span>
                                    @endif
                                </div>
                            </td>

                            {{-- KOLOM MATERIAL & PENDAMPING --}}
                            <td class="p-0 border-r border-slate-200 align-top">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow justify-between gap-2">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="pill pill-good w-[78px] justify-center flex-none text-[10px] h-5 px-1.5">Trigger</span>
                                            <div class="min-w-0 leading-tight">
                                                <div class="num text-[11px] font-bold text-slate-500 truncate">{{ $matItem->part_no ?: '-' }}</div>
                                                <div class="text-xs font-semibold text-slate-900 truncate">{{ $matItem->part_name ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <span class="pill pill-neu text-[10.5px] flex-none">{{ $matItem->uom ?? '' }}</span>
                                    </div>

                                    @foreach($grp['companions'] as $comp)
                                        <div class="subrow justify-between gap-2 bg-slate-50/60">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="pill pill-neu w-[78px] justify-center flex-none text-[10px] h-5 px-1.5">Pendamping</span>
                                                <div class="min-w-0 leading-tight">
                                                    <div class="num text-[11px] font-bold text-slate-500 truncate">{{ $comp['part_no'] }}</div>
                                                    <div class="text-xs font-semibold text-slate-800 truncate">{{ $comp['part_name'] }}</div>
                                                </div>
                                            </div>
                                            <span class="pill pill-neu text-[10.5px] flex-none">{{ $comp['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM QTY OUT (SEJAJAR SIMETRIS DENGAN MATERIAL & PENDAMPING) --}}
                            <td class="p-0 border-r border-slate-200 align-top">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow-qty">
                                        <div class="inline-flex items-baseline justify-end gap-1.5 leading-none">
                                            <span class="num text-sm font-bold text-rose-700">-{{ floatval($grp['qty_trigger']) }}</span>
                                            <span class="text-[11px] font-semibold text-slate-500">{{ $matItem->uom ?? '' }}</span>
                                        </div>
                                    </div>

                                    @foreach($grp['companions'] as $comp)
                                        <div class="subrow-qty bg-slate-50/60">
                                            <div class="inline-flex items-baseline justify-end gap-1.5 leading-none">
                                                <span class="num text-xs font-bold text-rose-600">-{{ floatval($comp['qty']) }}</span>
                                                <span class="text-[11px] font-medium text-slate-500">{{ $comp['uom'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM OUTPUT ITEM --}}
                            <td class="border-r border-slate-200">
                                <div class="space-y-1.5">
                                    @foreach($grp['outputs'] as $out)
                                        <div class="leading-tight">
                                            <div class="num text-[11px] font-bold text-accent">{{ $out['part_no'] }}</div>
                                            <div class="text-xs font-semibold text-slate-900">{{ $out['part_name'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="text-center border-r border-slate-200">
                                @if($stLabel === 'DIKOREKSI')
                                    <span class="pill pill-info">DIKOREKSI</span>
                                @else
                                    <span class="pill pill-good">TERCATAT</span>
                                @endif
                            </td>

                            <td class="text-center">
                                <button type="button" class="act-btn" title="Koreksi Qty Out"
                                        @click="openEdit({{ $first->id }}, '{{ $grp['lot_number'] }}', '{{ addslashes($matItem->part_name ?? '-') }}', '{{ floatval($grp['qty_trigger']) }}', '{{ $matItem->uom ?? '' }}')">
                                    <x-icon name="edit" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty">Belum ada data transaksi Material Out.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- MODE 2: TAMPILAN PER BARIS (MURNI TRIGGER SAJA TANPA PENDAMPING) --}}
        <div class="tbl-wrap" x-show="viewMode === 'flat'" x-cloak>
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[4%] text-center">No</th>
                        <th class="w-[13%] text-left col-time">Tanggal &amp; Waktu</th>
                        <th class="w-[12%]">Petugas Scan</th>
                        <th class="w-[11%]">No. Lot &amp; Tipe BOM</th>
                        <th class="w-[22%]">Material &amp; Pendamping</th>
                        <th class="num w-[11%]">Qty Out</th>
                        <th class="w-[15%]">Output Item</th>
                        <th class="w-[7%] text-center">Status</th>
                        <th class="w-[5%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($materialOuts as $idx => $row)
                        @php
                            $dt = \Carbon\Carbon::parse($row->date_time);
                            $stLabel = $row->qty_check === 'TIDAK SESUAI' ? 'DIKOREKSI' : 'TERCATAT';
                            $bType = $row->bom_type ?: 'SINGLE';
                            $flatSearch = strtolower(
                                $row->lot_number . ' ' .
                                $bType . ' ' .
                                ($row->user->name ?? 'Operator') . ' ' .
                                ($row->materialItem->part_no ?? '') . ' ' .
                                ($row->materialItem->part_name ?? '') . ' ' .
                                ($row->targetItem->part_no ?? '') . ' ' .
                                ($row->targetItem->part_name ?? '')
                            );
                        @endphp
                        <tr data-flat-row
                            data-search="{{ $flatSearch }}"
                            data-date="{{ $dt->format('Y-m-d') }}"
                            data-time="{{ $dt->format('H:i') }}"
                            data-bomtype="{{ $bType }}"
                            data-status="{{ $stLabel }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">{{ $dt->format('d M Y, H:i') }}</td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $row->user->name ?? 'Operator' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $row->user->role ?? 'STAFF' }}</div>
                            </td>
                            <td>
                                <div class="num font-bold text-accent text-xs">{{ $row->lot_number }}</div>
                                <div class="mt-1">
                                    @if($bType === 'SINGLE')
                                        <span class="pill pill-neu">SINGLE</span>
                                    @elseif($bType === 'MULTI-INPUT')
                                        <span class="pill pill-info">MULTI-INPUT</span>
                                    @elseif($bType === 'MULTI-OUTPUT')
                                        <span class="pill pill-warn">MULTI-OUTPUT</span>
                                    @else
                                        <span class="pill pill-good">KOMBINASI</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="pill pill-good w-[78px] justify-center flex-none text-[10px] h-5 px-1.5">Trigger</span>
                                        <div class="min-w-0 leading-tight">
                                            <div class="num text-[11px] font-bold text-slate-500 truncate">{{ $row->materialItem->part_no ?: '-' }}</div>
                                            <div class="text-xs font-semibold text-slate-900 truncate">{{ $row->materialItem->part_name ?? '-' }}</div>
                                        </div>
                                    </div>
                                    <span class="pill pill-neu text-[10.5px] flex-none">{{ $row->materialItem->uom ?? '' }}</span>
                                </div>
                            </td>
                            <td class="num whitespace-nowrap">
                                <div class="inline-flex items-baseline justify-end gap-1.5 leading-none">
                                    <span class="num text-sm font-bold text-rose-700">-{{ floatval($row->qty) }}</span>
                                    <span class="text-[11px] font-semibold text-slate-500">{{ $row->materialItem->uom ?? '' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="num text-[11px] font-bold text-accent">{{ $row->targetItem->part_no ?: '-' }}</div>
                                <div class="text-xs font-semibold text-slate-900">{{ $row->targetItem->part_name ?? '-' }}</div>
                            </td>
                            <td class="text-center">
                                @if($stLabel === 'DIKOREKSI')
                                    <span class="pill pill-info">DIKOREKSI</span>
                                @else
                                    <span class="pill pill-good">TERCATAT</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="act-btn" title="Koreksi Qty Out"
                                        @click="openEdit({{ $row->id }}, '{{ $row->lot_number }}', '{{ addslashes($row->materialItem->part_name ?? '-') }}', '{{ floatval($row->qty) }}', '{{ $row->materialItem->uom ?? '' }}')">
                                    <x-icon name="edit" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty">Belum ada data transaksi Material Out.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL KOREKSI QTY MATERIAL OUT --}}
        <div x-show="editOpen" x-cloak class="modal-backdrop" @click.self="editOpen = false">
            <div class="panel w-full max-w-md overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Koreksi Qty Material Out</h3>
                    <button type="button" class="act-btn" @click="editOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>
                <form action="{{ route('material_outs.verify_batch') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-1">
                            <div class="text-slate-500">Nomor Lot: <b class="num text-accent" x-text="editLot"></b></div>
                            <div class="font-bold text-slate-900" x-text="editPart"></div>
                            <div class="text-slate-500">Qty Tercatat Saat Ini: <b class="num text-slate-800" x-text="editOldQty + ' ' + editUom"></b></div>
                        </div>

                        <input type="hidden" :name="'items[' + editId + '][qty_check]'" value="TIDAK SESUAI">

                        <div>
                            <label class="lbl">Qty Material Out Sebenarnya (Mendukung Kalkulator)</label>
                            <input type="text"
                                   :name="'items[' + editId + '][actual_qty]'"
                                   x-model="editNewQty"
                                   required
                                   class="num w-full h-10 text-sm font-semibold"
                                   placeholder="Contoh: 25 atau 20+5">
                            <p class="hint">Selisih koreksi otomatis menyesuaikan stok Material Trigger, Komponen Pendamping, dan Estimasi di Lot Tracer.</p>
                        </div>
                    </div>
                    <div class="panel-foot">
                        <button type="button" class="btn" @click="editOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Koreksi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function matOutPage(initGroups, initFlat) {
            return {
                viewMode: 'grouped',
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fBomType: '',
                fStatus: '',
                visibleGroups: initGroups,
                visibleFlat: initFlat,
                editOpen: false,
                editId: null,
                editLot: '',
                editPart: '',
                editOldQty: '',
                editNewQty: '',
                editUom: '',

                openEdit(id, lot, part, qty, uom) {
                    this.editId = id;
                    this.editLot = lot;
                    this.editPart = part;
                    this.editOldQty = qty;
                    this.editNewQty = qty;
                    this.editUom = uom;
                    this.editOpen = true;
                },
                matchRow(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const d = el.dataset.date || '';
                    const t = el.dataset.time || '';
                    const bt = el.dataset.bomtype || '';
                    const st = el.dataset.status || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.startDate && d < this.startDate) return false;
                    if (this.endDate && d > this.endDate) return false;
                    if (this.startTime && t < this.startTime) return false;
                    if (this.endTime && t > this.endTime) return false;
                    if (this.fBomType && bt !== this.fBomType) return false;
                    if (this.fStatus && st !== this.fStatus) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let g = 0;
                        this.$el.querySelectorAll('tr[data-grp-row]').forEach(tr => {
                            if (this.matchRow(tr)) g++;
                        });
                        this.visibleGroups = g;

                        let f = 0;
                        this.$el.querySelectorAll('tr[data-flat-row]').forEach(tr => {
                            if (this.matchRow(tr)) f++;
                        });
                        this.visibleFlat = f;
                    });
                },
                resetFilter() {
                    this.q = '';
                    this.startDate = '';
                    this.endDate = '';
                    this.startTime = '';
                    this.endTime = '';
                    this.fBomType = '';
                    this.fStatus = '';
                    this.recalc();
                }
            };
        }
    </script>
</x-app-layout>