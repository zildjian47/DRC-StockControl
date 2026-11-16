<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Lot Tracer"
                       subtitle="Meja kontrol WIP produksi per Nomor Lot (Material Saat Ini, Komponen Pendamping, Act FG, Top/End Coil, & Penutupan Lot)" />
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="lotTracerPage({{ $groupedLots->count() }}, {{ $lotTracers->count() }})"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Pemantauan Lot Produksi</span>
                <span class="count-chip"><span x-text="visibleGroups"></span><span>KELOMPOK LOT</span></span>
                <span class="count-chip count-chip-sub"><span x-text="visibleRows"></span><span>TARGET WIP</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari No. Lot / Material / Target..."
                       class="h-8 text-xs px-2.5 w-48">

                <select x-model="fProcess" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Proses</option>
                    @foreach($processes as $proc)
                        <option value="{{ $proc->id }}">{{ $proc->process_name }}</option>
                    @endforeach
                </select>

                <select x-model="fBomType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe BOM</option>
                    <option value="SINGLE">SINGLE</option>
                    <option value="MULTI-INPUT">MULTI-INPUT</option>
                    <option value="MULTI-OUTPUT">MULTI-OUTPUT</option>
                    <option value="KOMBINASI">KOMBINASI</option>
                </select>

                <select x-model="fStatus" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Status Lot</option>
                    <option value="ON PROGRESS">ON PROGRESS</option>
                    <option value="FINISH">FINISH</option>
                </select>

                <button type="button"
                        x-show="q || fProcess || fBomType || fStatus" x-cloak
                        @click="resetFilter()" class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[4%] text-center">No</th>
                        <th class="w-[11%]">No. Lot &amp; Proses</th>
                        <th class="w-[20%]">Material &amp; Pendamping</th>
                        <th class="num w-[8%]">Mat. Out</th>
                        <th class="num w-[9%]">Material Saat Ini</th>
                        <th class="num w-[8%]">Mat. Return</th>
                        <th class="num w-[7%]">Top/End Coil</th>
                        <th class="w-[14%]">Target Output</th>
                        <th class="num w-[6%]">Target Est</th>
                        <th class="num w-[5%]">Act Prod</th>
                        <th class="num w-[4%]">NG</th>
                        <th class="num w-[5%]">Act FG</th>
                        <th class="num w-[5%]">WIP</th>
                        <th class="w-[7%] text-center">Status &amp; Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groupedLots as $idx => $grp)
                        @php
                            $first      = $grp['first'];
                            $bType      = $grp['bom_type'];
                            $matItem    = $first->materialItem;
                            $matUom     = $matItem->uom ?? '';
                            $companions = $first->companion_details ?? [];
                            $topEnd     = $first->top_end_coil;
                        @endphp
                        <tr data-lot-group
                            data-rows="{{ $grp['rows']->count() }}"
                            data-search="{{ $grp['search_index'] }}"
                            data-process="{{ $grp['process_id'] }}"
                            data-bomtype="{{ $bType }}"
                            data-status="{{ $grp['status_lot'] }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td>
                                <div class="num font-bold text-accent">{{ $grp['lot_number'] }}</div>
                                <div class="text-xs font-semibold text-slate-700 mt-0.5">{{ $grp['process_name'] }}</div>
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

                            {{-- MATERIAL TRIGGER & PENDAMPING --}}
                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="pill pill-good w-[88px] justify-center flex-none">TRIGGER</span>
                                            <div class="min-w-0 leading-tight">
                                                <div class="num text-[11px] font-bold text-accent truncate">{{ $matItem->part_no ?: '-' }}</div>
                                                <div class="text-xs font-semibold text-slate-900 truncate">{{ $matItem->part_name ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <span class="pill pill-neu flex-none">{{ $matUom }}</span>
                                    </div>

                                    @foreach($companions as $comp)
                                        <div class="subrow justify-between gap-2 bg-slate-50/60">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="pill pill-neu w-[88px] justify-center flex-none">PENDAMPING</span>
                                                <div class="min-w-0 leading-tight">
                                                    <div class="num text-[11px] font-bold text-slate-600 truncate">{{ $comp['part_no'] }}</div>
                                                    <div class="text-xs font-medium text-slate-700 truncate">{{ $comp['part_name'] }}</div>
                                                </div>
                                            </div>
                                            <span class="pill pill-neu flex-none">{{ $comp['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- MAT. OUT --}}
                            <td class="p-0 num">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow-qty">
                                        <span class="font-bold text-slate-900">{{ floatval($first->qty_material_out) }}</span>
                                        <span class="text-[11px] text-slate-500">{{ $matUom }}</span>
                                    </div>
                                    @foreach($companions as $comp)
                                        <div class="subrow-qty bg-slate-50/60">
                                            <span class="text-xs font-semibold text-slate-700">{{ floatval($comp['qty_out']) }}</span>
                                            <span class="text-[11px] text-slate-500">{{ $comp['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- MATERIAL SAAT INI --}}
                            <td class="p-0 num">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow-qty">
                                        <span class="font-bold text-accent">{{ floatval($first->material_saat_ini) }}</span>
                                        <span class="text-[11px] text-slate-500">{{ $matUom }}</span>
                                    </div>
                                    @foreach($companions as $comp)
                                        <div class="subrow-qty bg-slate-50/60">
                                            <span class="text-xs font-semibold text-accent">{{ floatval($comp['qty_current']) }}</span>
                                            <span class="text-[11px] text-slate-500">{{ $comp['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- MAT. RETURN --}}
                            <td class="p-0 num">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow-qty">
                                        <span class="font-semibold text-emerald-700">{{ floatval($first->qty_material_return) }}</span>
                                        <span class="text-[11px] text-slate-500">{{ $matUom }}</span>
                                    </div>
                                    @foreach($companions as $comp)
                                        <div class="subrow-qty bg-slate-50/60">
                                            <span class="text-xs font-semibold text-emerald-700">{{ floatval($comp['qty_return']) }}</span>
                                            <span class="text-[11px] text-slate-500">{{ $comp['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- TOP / END COIL --}}
                            <td class="num text-center">
                                @if($topEnd !== null)
                                    <span class="pill pill-warn num">{{ floatval($topEnd) }} {{ $matUom }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>

                            {{-- TARGET OUTPUT --}}
                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow">
                                            <div class="min-w-0 leading-tight">
                                                <div class="num text-[11px] font-bold text-accent truncate">{{ $r->targetItem->part_no ?: '-' }}</div>
                                                <div class="text-xs font-semibold text-slate-900 truncate">{{ $r->targetItem->part_name ?? '-' }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- TARGET EST --}}
                            <td class="p-0 num">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow-qty">
                                            <span class="font-semibold text-slate-800">{{ floatval($r->target_qty_estimasi) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- ACTUAL PROD --}}
                            <td class="p-0 num">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow-qty">
                                            <span class="font-bold text-slate-900">{{ floatval($r->actual_qty_prod) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- NG --}}
                            <td class="p-0 num">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow-qty">
                                            <span class="font-semibold {{ $r->qty_ng > 0 ? 'text-rose-700' : 'text-slate-400' }}">{{ floatval($r->qty_ng) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- ACT FG --}}
                            <td class="p-0 num">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow-qty">
                                            <span class="font-bold text-emerald-700">{{ floatval($r->act_fg) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- WIP --}}
                            <td class="p-0 num">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow-qty">
                                            <span class="font-bold {{ $r->qty_wip > 0 ? 'text-amber-700' : 'text-slate-500' }}">{{ floatval($r->qty_wip) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- STATUS & AKSI SET FINISH --}}
                            <td class="p-0 text-center">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow justify-center gap-1.5">
                                            @if($r->status_lot === 'ON PROGRESS')
                                                <span class="pill pill-good">ON PROGRESS</span>
                                                <form id="fin-lot-{{ $r->id }}" action="{{ route('lot_tracers.finish', $r->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="button" class="act-btn" title="Set FINISH (Tutup Lot)"
                                                            @click="$dispatch('open-confirm', {
                                                                title: 'Tutup Lot Produksi (Set FINISH)',
                                                                message: 'Tandai Lot {{ $r->lot_number }} ({{ addslashes($r->targetItem->part_name ?? '-') }}) sebagai FINISH? Sisa WIP akan ditutup menjadi 0.',
                                                                formId: 'fin-lot-{{ $r->id }}'
                                                            })">
                                                        <x-icon name="check" />
                                                    </button>
                                                </form>
                                            @else
                                                <span class="pill pill-neu">FINISH</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="14"><div class="empty">Belum ada data Lot Tracer.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function lotTracerPage(initialGroups, initialRows) {
            return {
                q: '',
                fProcess: '',
                fBomType: '',
                fStatus: '',
                visibleGroups: initialGroups,
                visibleRows: initialRows,

                resetFilter() {
                    this.q = '';
                    this.fProcess = '';
                    this.fBomType = '';
                    this.fStatus = '';
                    this.recalc();
                },
                matchRow(el) {
                    const s = el.dataset.search || '';
                    const pr = el.dataset.process || '';
                    const bt = el.dataset.bomtype || '';
                    const st = el.dataset.status || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.fProcess && String(pr) !== String(this.fProcess)) return false;
                    if (this.fBomType && bt !== this.fBomType) return false;
                    if (this.fStatus && st !== this.fStatus) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let g = 0, r = 0;
                        this.$el.querySelectorAll('tbody tr[data-lot-group]').forEach(tr => {
                            if (this.matchRow(tr)) {
                                g++;
                                r += parseInt(tr.dataset.rows || '1', 10);
                            }
                        });
                        this.visibleGroups = g;
                        this.visibleRows = r;
                    });
                }
            };
        }
    </script>
</x-app-layout>