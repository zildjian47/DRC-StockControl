<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Form Production NG (Reject QC)"
                       subtitle="Pencatatan temuan barang cacat (NG) per Nomor Lot yang memotong saldo Act FG & stok gudang">
            <x-slot name="actions">
                <a href="{{ route('production_ngs.index', ['mode' => 'scanner']) }}" class="btn">
                    <x-icon name="scan" /> Buka Mobile Scanner
                </a>
                <button type="button" class="btn btn-primary" @click="$dispatch('open-add-ng')">
                    <x-icon name="plus" /> Input NG
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $lotsJs = $activeLots->map(function ($lt) {
            $actFg = max(0, (float) $lt->actual_qty_prod - (float) $lt->qty_ng);
            return [
                'id'          => $lt->id,
                'lot_number'  => $lt->lot_number,
                'part_no'     => $lt->targetItem->part_no ?: '-',
                'part_name'   => $lt->targetItem->part_name ?? '-',
                'uom'         => $lt->targetItem->uom ?? 'PCS',
                'process'     => $lt->process->process_name ?? '-',
                'actual_prod' => (float) $lt->actual_qty_prod,
                'qty_ng'      => (float) $lt->qty_ng,
                'act_fg'      => $actFg,
                'label'       => $lt->lot_number . ' — ' . ($lt->targetItem->part_name ?? '-') . ' (Act FG: ' . floatval($actFg) . ' ' . ($lt->targetItem->uom ?? 'PCS') . ')',
            ];
        })->values();
    @endphp

    <div class="panel"
         x-data="productionNgPage({{ $ngs->count() }}, {{ Js::from($lotsJs) }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-ng.window="addOpen = true"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Riwayat Production NG</span>
                <span class="count-chip"><span x-text="visibleRows"></span> Data</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Lot / Part / Petugas QC..."
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

                <select x-model="fReason" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Alasan NG</option>
                    @foreach($reasons as $rs)
                        <option value="{{ $rs }}">{{ $rs }}</option>
                    @endforeach
                </select>

                <button type="button"
                        x-show="q || startDate || endDate || startTime || endTime || fReason"
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
                        <th class="w-40">Petugas QC</th>
                        <th class="w-32 text-center">No. Lot</th>
                        <th>Part No &amp; Part Name</th>
                        <th class="w-44 text-center">Problem Reason</th>
                        <th class="num w-36">Qty NG</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ngs as $idx => $ng)
                        @php
                            $it = $ng->item;
                            $searchStr = strtolower(
                                ($ng->lot_number ?? '') . ' ' .
                                ($it->part_no ?? '') . ' ' .
                                ($it->part_name ?? '') . ' ' .
                                ($ng->problem_reason ?? '') . ' ' .
                                ($ng->user->name ?? 'QC')
                            );
                        @endphp
                        <tr data-row-item
                            data-search="{{ $searchStr }}"
                            data-date="{{ \Carbon\Carbon::parse($ng->date_time)->format('Y-m-d') }}"
                            data-time="{{ \Carbon\Carbon::parse($ng->date_time)->format('H:i') }}"
                            data-reason="{{ $ng->problem_reason }}"
                            x-show="matchFilter($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">
                                {{ \Carbon\Carbon::parse($ng->date_time)->format('d M Y, H:i') }}
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $ng->user->name ?? 'QC' }}</div>
                                <div class="text-[11px] text-slate-500 uppercase">{{ $ng->user->role ?? 'PPIC' }}</div>
                            </td>
                            <td class="text-center num font-bold text-accent">{{ $ng->lot_number }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $it->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $it->part_name ?? '-' }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-crit">{{ $ng->problem_reason }}</span>
                            </td>
                            <td class="num whitespace-nowrap">
                                <span class="font-bold text-rose-700">-{{ floatval($ng->qty_ng) }}</span>
                                <span class="text-xs text-slate-500 ml-1">{{ $it->uom ?? 'PCS' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty">Belum ada data pencatatan Production NG.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT NG --}}
        <div x-show="addOpen" x-cloak
             class="modal-backdrop"
             @click.self="addOpen = false">
            <div class="panel w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Input Data Production NG (QC)</h3>
                    <button type="button" class="act-btn" @click="addOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('production_ngs.store') }}" method="POST" class="flex-1 overflow-y-auto">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div>
                            <label class="lbl">Cari &amp; Pilih Nomor Lot Aktif (ON PROGRESS)</label>
                            <input type="text" x-model="lotSearch"
                                   placeholder="Ketik Nomor Lot atau Nama Part..."
                                   class="w-full h-8 text-xs mb-1.5">
                            <select name="lot_tracer_id" x-model="selectedLotId" required class="w-full h-9 text-xs">
                                <option value="">-- Pilih Nomor Lot &amp; Target Output --</option>
                                <template x-for="lt in filteredLots" :key="lt.id">
                                    <option :value="lt.id" x-text="lt.label"></option>
                                </template>
                            </select>
                        </div>

                        <div x-show="selectedLot" class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-1">
                            <div class="flex items-center justify-between">
                                <span>No. Lot: <b class="num text-accent" x-text="selectedLot?.lot_number"></b></span>
                                <span class="pill pill-neu" x-text="selectedLot?.process"></span>
                            </div>
                            <div>Part Output: <b class="text-slate-900" x-text="selectedLot?.part_name"></b></div>
                            <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-200">
                                <div>Actual Prod: <b class="num" x-text="selectedLot?.actual_prod"></b></div>
                                <div>NG Tercatat: <b class="num text-rose-700" x-text="selectedLot?.qty_ng"></b></div>
                                <div>Maks Input (Act FG): <b class="num text-emerald-700" x-text="selectedLot?.act_fg + ' ' + (selectedLot?.uom ?? '')"></b></div>
                            </div>
                        </div>

                        <div>
                            <label class="lbl">Problem Reason (Penyebab NG)</label>
                            <select name="problem_reason" required class="w-full h-9 text-xs">
                                @foreach($reasons as $rs)
                                    <option value="{{ $rs }}">{{ $rs }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="lbl">Qty NG (Maksimal sebesar Act FG)</label>
                            <input type="number" step="0.01" min="0.01"
                                   :max="selectedLot ? selectedLot.act_fg : null"
                                   name="qty_ng" required placeholder="0"
                                   class="num w-full h-9 text-xs font-semibold">
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Data NG</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function productionNgPage(initialRows, lotsList, openInitially) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fReason: '',
                visibleRows: initialRows,
                lots: lotsList,
                addOpen: openInitially,
                lotSearch: '',
                selectedLotId: '',

                get filteredLots() {
                    if (!this.lotSearch) return this.lots;
                    const s = this.lotSearch.toLowerCase();
                    return this.lots.filter(l => l.label.toLowerCase().includes(s));
                },
                get selectedLot() {
                    return this.lots.find(l => String(l.id) === String(this.selectedLotId)) || null;
                },
                matchFilter(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const d = el.dataset.date || '';
                    const t = el.dataset.time || '';
                    const r = el.dataset.reason || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.startDate && d < this.startDate) return false;
                    if (this.endDate && d > this.endDate) return false;
                    if (this.startTime && t < this.startTime) return false;
                    if (this.endTime && t > this.endTime) return false;
                    if (this.fReason && r !== this.fReason) return false;
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
                    this.fReason = '';
                    this.recalc();
                }
            };
        }
    </script>
</x-app-layout>