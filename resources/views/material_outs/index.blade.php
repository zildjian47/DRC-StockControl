<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Material Out"
                       subtitle="Riwayat pengeluaran bahan baku dan komponen pendamping ke lantai produksi">
            <x-slot name="actions">
                <a href="{{ route('material_outs.index', ['mode' => 'scanner']) }}" class="btn btn-primary">
                    <x-icon name="scan" /> Buka Mobile Scanner
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $groupedOuts = $groupedOuts ?? ($groups ?? collect());
    @endphp

    <div class="panel"
         x-data="materialOutPage({{ $groupedOuts->count() }}, {{ $materialOuts->count() }})"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="flex items-center gap-2 flex-wrap">
                <h3 class="panel-title">Daftar Transaksi Material Out</h3>
                <span class="count-chip"><span x-text="visibleGroups"></span> Kelompok</span>
                <span class="count-chip count-chip-sub"><span x-text="visibleRows"></span> Baris</span>

                <div class="seg ml-1">
                    <label @click="viewMode = 'grouped'">
                        <input type="radio" name="vm_mo" value="grouped" :checked="viewMode === 'grouped'">
                        <span>Kelompok</span>
                    </label>
                    <label @click="viewMode = 'flat'">
                        <input type="radio" name="vm_mo" value="flat" :checked="viewMode === 'flat'">
                        <span>Per Baris</span>
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Lot / Part / Petugas Scan..."
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
                        <th class="w-40">Petugas Scan</th>
                        <th class="w-36">No. Lot &amp; Tipe BOM</th>
                        <th>Material &amp; Pendamping</th>
                        <th class="num w-36">Qty Out</th>
                        <th>Output Item</th>
                        <th class="w-28 text-center">Status</th>
                        <th class="w-16 text-center">Aksi</th>
                    </tr>
                </thead>

                {{-- MODE 1: TAMPILAN KELOMPOK --}}
                <tbody data-group-body x-show="viewMode === 'grouped'">
                    @forelse($groupedOuts as $idx => $grp)
                        @php
                            $first = $grp['first'];
                            $mat = $first->materialItem;
                            $bomType = $grp['bom_type'] ?: 'SINGLE';
                        @endphp
                        <tr data-row-item
                            data-search="{{ $grp['search_index'] }}"
                            data-date="{{ $grp['date_only'] }}"
                            data-time="{{ $grp['time_only'] }}"
                            data-bomtype="{{ $bomType }}"
                            data-status="{{ $grp['status_label'] }}"
                            x-show="matchFilter($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">
                                {{ \Carbon\Carbon::parse($first->date_time)->format('d M Y, H:i') }}
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $first->user->name ?? 'Operator' }}</div>
                                <div class="text-[11px] text-slate-500 uppercase">{{ $first->user->role ?? 'STAFF' }}</div>
                            </td>
                            <td>
                                <div class="num font-bold text-accent">{{ $grp['lot_number'] }}</div>
                                <div class="mt-1">
                                    @if($bomType === 'SINGLE')
                                        <span class="pill pill-neu">{{ $bomType }}</span>
                                    @elseif($bomType === 'MULTI-INPUT')
                                        <span class="pill pill-info">{{ $bomType }}</span>
                                    @elseif($bomType === 'MULTI-OUTPUT')
                                        <span class="pill pill-warn">{{ $bomType }}</span>
                                    @else
                                        <span class="pill pill-good">{{ $bomType }}</span>
                                    @endif
                                </div>
                            </td>

                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    <div class="px-3 py-2 min-h-[42px] flex flex-col justify-center">
                                        <div class="flex items-center gap-1.5">
                                            <span class="pill pill-good text-[10px] h-4 px-1.5">Trigger</span>
                                            <span class="num text-xs font-semibold text-slate-700">{{ $mat->part_no ?: '-' }}</span>
                                        </div>
                                        <div class="font-semibold text-slate-900 text-xs mt-0.5">{{ $mat->part_name ?? '-' }}</div>
                                    </div>
                                    @foreach($grp['companions'] as $comp)
                                        <div class="px-3 py-2 min-h-[40px] flex flex-col justify-center bg-slate-50/50">
                                            <div class="flex items-center gap-1.5">
                                                <span class="pill pill-neu text-[10px] h-4 px-1.5">Pendamping</span>
                                                <span class="num text-xs font-semibold text-slate-600">{{ $comp['part_no'] }}</span>
                                            </div>
                                            <div class="text-xs text-slate-800 font-medium mt-0.5">{{ $comp['part_name'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="num p-0 whitespace-nowrap">
                                <div class="divide-y divide-slate-200">
                                    <div class="px-3 py-2 min-h-[42px] flex items-center justify-end">
                                        <span class="font-bold text-rose-700">-{{ floatval($grp['qty_trigger']) }}</span>
                                        <span class="text-xs text-slate-500 ml-1">{{ $mat->uom ?? '' }}</span>
                                    </div>
                                    @foreach($grp['companions'] as $comp)
                                        <div class="px-3 py-2 min-h-[40px] flex items-center justify-end bg-slate-50/50">
                                            <span class="text-xs font-bold text-rose-600">-{{ floatval($comp['qty']) }}</span>
                                            <span class="text-[11px] text-slate-500 ml-1">{{ $comp['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['outputs'] as $outIt)
                                        <div class="px-3 py-2 min-h-[42px] flex flex-col justify-center">
                                            <div class="num text-xs font-semibold text-accent">{{ $outIt['part_no'] }}</div>
                                            <div class="font-semibold text-slate-900 text-xs">{{ $outIt['part_name'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="text-center">
                                @if($grp['status_label'] === 'DIKOREKSI')
                                    <span class="pill pill-info">DIKOREKSI</span>
                                @else
                                    <span class="pill pill-good">TERCATAT</span>
                                @endif
                            </td>

                            <td class="text-center">
                                <button type="button" class="act-icon" title="Koreksi Qty Out"
                                        @click="openEdit({{ $first->id }}, '{{ $grp['lot_number'] }}', '{{ addslashes($mat->part_name ?? '-') }}', '{{ $mat->uom ?? '' }}', {{ floatval($grp['qty_trigger']) }})">
                                    <x-icon name="edit" class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty">Belum ada data Material Out.</div></td></tr>
                    @endforelse
                </tbody>

                {{-- MODE 2: TAMPILAN PER BARIS --}}
                <tbody data-flat-body x-show="viewMode === 'flat'" x-cloak>
                    @forelse($materialOuts as $idx => $mo)
                        @php
                            $mat = $mo->materialItem;
                            $tgt = $mo->targetItem;
                            $bType = $mo->bom_type ?: 'SINGLE';
                            $stLabel = $mo->qty_check === 'TIDAK SESUAI' ? 'DIKOREKSI' : 'TERCATAT';
                            $sFlat = strtolower(($mo->lot_number ?? '') . ' ' . $bType . ' ' . ($mo->user->name ?? '') . ' ' . ($mat->part_no ?? '') . ' ' . ($mat->part_name ?? '') . ' ' . ($tgt->part_no ?? '') . ' ' . ($tgt->part_name ?? ''));
                        @endphp
                        <tr data-row-item
                            data-search="{{ $sFlat }}"
                            data-date="{{ \Carbon\Carbon::parse($mo->date_time)->format('Y-m-d') }}"
                            data-time="{{ \Carbon\Carbon::parse($mo->date_time)->format('H:i') }}"
                            data-bomtype="{{ $bType }}"
                            data-status="{{ $stLabel }}"
                            x-show="matchFilter($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">
                                {{ \Carbon\Carbon::parse($mo->date_time)->format('d M Y, H:i') }}
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $mo->user->name ?? 'Operator' }}</div>
                                <div class="text-[11px] text-slate-500 uppercase">{{ $mo->user->role ?? 'STAFF' }}</div>
                            </td>
                            <td>
                                <div class="num font-bold text-accent">{{ $mo->lot_number }}</div>
                                <div class="mt-1">
                                    @if($bType === 'SINGLE')
                                        <span class="pill pill-neu">{{ $bType }}</span>
                                    @elseif($bType === 'MULTI-INPUT')
                                        <span class="pill pill-info">{{ $bType }}</span>
                                    @elseif($bType === 'MULTI-OUTPUT')
                                        <span class="pill pill-warn">{{ $bType }}</span>
                                    @else
                                        <span class="pill pill-good">{{ $bType }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    <div class="px-3 py-2 min-h-[42px] flex flex-col justify-center">
                                        <div class="flex items-center gap-1.5">
                                            <span class="pill pill-good text-[10px] h-4 px-1.5">Trigger</span>
                                            <span class="num text-xs font-semibold text-slate-700">{{ $mat->part_no ?: '-' }}</span>
                                        </div>
                                        <div class="font-semibold text-slate-900 text-xs mt-0.5">{{ $mat->part_name ?? '-' }}</div>
                                    </div>
                                    @foreach($mo->companion_list as $comp)
                                        <div class="px-3 py-2 min-h-[40px] flex flex-col justify-center bg-slate-50/50">
                                            <div class="flex items-center gap-1.5">
                                                <span class="pill pill-neu text-[10px] h-4 px-1.5">Pendamping</span>
                                                <span class="num text-xs font-semibold text-slate-600">{{ $comp['part_no'] }}</span>
                                            </div>
                                            <div class="text-xs text-slate-800 font-medium mt-0.5">{{ $comp['part_name'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="num p-0 whitespace-nowrap">
                                <div class="divide-y divide-slate-200">
                                    <div class="px-3 py-2 min-h-[42px] flex items-center justify-end">
                                        <span class="font-bold text-rose-700">-{{ floatval($mo->qty) }}</span>
                                        <span class="text-xs text-slate-500 ml-1">{{ $mat->uom ?? '' }}</span>
                                    </div>
                                    @foreach($mo->companion_list as $comp)
                                        <div class="px-3 py-2 min-h-[40px] flex items-center justify-end bg-slate-50/50">
                                            <span class="text-xs font-bold text-rose-600">-{{ floatval($comp['qty']) }}</span>
                                            <span class="text-[11px] text-slate-500 ml-1">{{ $comp['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                <div class="num text-xs font-semibold text-accent">{{ $tgt->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $tgt->part_name ?? '-' }}</div>
                            </td>
                            <td class="text-center">
                                @if($stLabel === 'DIKOREKSI')
                                    <span class="pill pill-info">DIKOREKSI</span>
                                @else
                                    <span class="pill pill-good">TERCATAT</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="act-icon" title="Koreksi Qty Out"
                                        @click="openEdit({{ $mo->id }}, '{{ $mo->lot_number }}', '{{ addslashes($mat->part_name ?? '-') }}', '{{ $mat->uom ?? '' }}', {{ floatval($mo->qty) }})">
                                    <x-icon name="edit" class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty">Belum ada data Material Out.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL EDIT QTY MATERIAL OUT --}}
        <div x-show="editOpen" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 !mt-0"
             @click.self="editOpen = false">
            <div class="panel w-full max-w-md overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Koreksi Qty Material Out</h3>
                    <button type="button" class="btn btn-sm" @click="editOpen = false">Tutup</button>
                </div>
                <form action="{{ route('material_outs.verify_batch') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-1">
                            <div>No. Lot: <b class="num text-accent" x-text="editLot"></b></div>
                            <div>Material Trigger: <b class="text-slate-900" x-text="editMatName"></b></div>
                            <div>Qty Tercatat Saat Ini: <b class="num text-slate-900" x-text="editOldQty + ' ' + editUom"></b></div>
                        </div>

                        <input type="hidden" :name="'items[' + editId + '][qty_check]'" value="TIDAK SESUAI">

                        <div>
                            <label class="lbl">Qty Material Out Sebenarnya (Kalkulator + - * /)</label>
                            <input type="text"
                                   :name="'items[' + editId + '][actual_qty]'"
                                   x-model="editNewQty"
                                   required
                                   class="num w-full h-9 text-xs font-semibold">
                            <div class="text-xs text-slate-500 mt-1" x-show="evalCalc(editNewQty) !== null">
                                Hasil Kalkulasi: <b class="num text-slate-900" x-text="evalCalc(editNewQty) + ' ' + editUom"></b>
                            </div>
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
        function materialOutPage(initialGroups, initialRows) {
            return {
                viewMode: 'grouped',
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fBomType: '',
                fStatus: '',
                editOpen: false,
                editId: null,
                editLot: '',
                editMatName: '',
                editUom: '',
                editOldQty: 0,
                editNewQty: '',
                visibleGroups: initialGroups,
                visibleRows: initialRows,
                openEdit(id, lot, matName, uom, qty) {
                    this.editId = id;
                    this.editLot = lot;
                    this.editMatName = matName;
                    this.editUom = uom;
                    this.editOldQty = qty;
                    this.editNewQty = String(qty);
                    this.editOpen = true;
                },
                evalCalc(val) {
                    if (!val) return null;
                    const expr = String(val).replace(/,/g, '.').replace(/\s+/g, '');
                    if (!/^[0-9\.\+\-\*\/\(\)]+$/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r) && r > 0) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                matchFilter(el) {
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
                    if (this.startDate && this.endDate && this.endDate < this.startDate) {
                        this.endDate = this.startDate;
                    }
                    this.$nextTick(() => {
                        let cg = 0, cr = 0;
                        this.$el.querySelectorAll('tbody[data-group-body] tr[data-row-item]').forEach(tr => {
                            if (this.matchFilter(tr)) cg++;
                        });
                        this.$el.querySelectorAll('tbody[data-flat-body] tr[data-row-item]').forEach(tr => {
                            if (this.matchFilter(tr)) cr++;
                        });
                        this.visibleGroups = cg;
                        this.visibleRows = cr;
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