<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Form Production NG (Reject QC)"
                       subtitle="Pencatatan temuan barang NG (Not Good) oleh QC per Nomor Lot yang sedang berjalan">
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
        $lotsJs = $activeLots->map(fn ($lt) => [
            'id'       => $lt->id,
            'lot'      => $lt->lot_number,
            'part_no'  => $lt->targetItem->part_no ?: '-',
            'part_name'=> $lt->targetItem->part_name ?? '-',
            'uom'      => $lt->targetItem->uom ?? 'PCS',
            'act_prod' => (float) $lt->actual_qty_prod,
            'qty_ng'   => (float) $lt->qty_ng,
            'act_fg'   => (float) $lt->act_fg,
        ])->values();
    @endphp

    <div class="panel"
         x-data="prodNgPage({{ $ngs->count() }}, {{ Js::from($lotsJs) }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-ng.window="addOpen = true"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Riwayat Production NG</span>
                <span class="count-chip"><span x-text="visibleRows"></span><span>Data</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
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

                <select x-model="fReason" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Problem Reason</option>
                    @foreach($reasons as $r)
                        <option value="{{ $r }}">{{ $r }}</option>
                    @endforeach
                </select>

                <button type="button"
                        x-show="q || startDate || endDate || startTime || endTime || fReason"
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
                        <th class="w-[15%] text-left col-time">Waktu Pencatatan</th>
                        <th class="w-[15%]">Petugas QC</th>
                        <th class="w-[12%] text-center">No. Lot</th>
                        <th class="w-[26%]">Part No &amp; Part Name</th>
                        <th class="w-[15%] text-center">Problem Reason</th>
                        <th class="num w-[12%]">Qty NG</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ngs as $idx => $ng)
                        @php
                            $dt = \Carbon\Carbon::parse($ng->date_time);
                            $searchStr = strtolower(
                                $ng->lot_number . ' ' .
                                ($ng->item->part_no ?? '') . ' ' .
                                ($ng->item->part_name ?? '') . ' ' .
                                ($ng->problem_reason ?? '') . ' ' .
                                ($ng->user->name ?? 'QC')
                            );
                        @endphp
                        <tr data-ng-row
                            data-search="{{ $searchStr }}"
                            data-date="{{ $dt->format('Y-m-d') }}"
                            data-time="{{ $dt->format('H:i') }}"
                            data-reason="{{ $ng->problem_reason }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">{{ $dt->format('d M Y, H:i') }}</td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $ng->user->name ?? 'Petugas QC' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $ng->user->role ?? 'PPIC' }}</div>
                            </td>
                            <td class="text-center num font-bold text-accent text-xs">{{ $ng->lot_number }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $ng->item->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $ng->item->part_name ?? '-' }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-crit">{{ $ng->problem_reason }}</span>
                            </td>
                            <td class="num whitespace-nowrap">
                                <div class="inline-flex items-baseline justify-end gap-1.5 leading-none">
                                    <span class="num text-sm font-bold text-rose-700">-{{ floatval($ng->qty_ng) }}</span>
                                    <span class="text-xs font-medium text-slate-500">{{ $ng->item->uom ?? 'PCS' }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty">Belum ada data pencatatan Production NG.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT NG --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false">
            <div class="panel w-full max-w-lg overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Input Production NG Baru</h3>
                    <button type="button" class="act-btn" @click="addOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>
                <form action="{{ route('production_ngs.store') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div>
                            <label class="lbl">Pilih Nomor Lot &amp; Output Item (ON PROGRESS)</label>
                            <select name="lot_tracer_id" x-model="selectedLotId" required class="w-full h-9 text-xs">
                                <option value="">-- Pilih Nomor Lot Aktif --</option>
                                @foreach($activeLots as $lt)
                                    <option value="{{ $lt->id }}">
                                        {{ $lt->lot_number }} — {{ $lt->targetItem->part_name ?? '-' }} (Act FG: {{ floatval($lt->act_fg) }} {{ $lt->targetItem->uom ?? 'PCS' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="selectedLot" class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-1">
                            <div class="font-bold text-slate-900" x-text="selectedLot?.part_no + ' — ' + selectedLot?.part_name"></div>
                            <div class="flex items-center justify-between text-slate-600">
                                <span>Actual Prod: <b class="num text-slate-900" x-text="selectedLot?.act_prod"></b></span>
                                <span>NG Tercatat: <b class="num text-rose-700" x-text="selectedLot?.qty_ng"></b></span>
                                <span>Batas Maks NG (Act FG): <b class="num text-emerald-700" x-text="selectedLot?.act_fg + ' ' + (selectedLot?.uom || '')"></b></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Problem Reason</label>
                                <select name="problem_reason" required class="w-full h-9 text-xs">
                                    @foreach($reasons as $r)
                                        <option value="{{ $r }}">{{ $r }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Qty NG</label>
                                <input type="number" step="0.01" min="0.01" :max="selectedLot ? selectedLot.act_fg : null"
                                       name="qty_ng" required placeholder="Contoh: 2" class="num w-full h-9 font-semibold">
                            </div>
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
        function prodNgPage(initialCount, lotsList, openInitially) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fReason: '',
                visibleRows: initialCount,
                lotsList: lotsList,
                addOpen: openInitially,
                selectedLotId: '',

                get selectedLot() {
                    return this.lotsList.find(l => String(l.id) === String(this.selectedLotId)) || null;
                },
                matchRow(el) {
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
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-ng-row]').forEach(tr => {
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
                    this.fReason = '';
                    this.recalc();
                }
            };
        }
    </script>
</x-app-layout>