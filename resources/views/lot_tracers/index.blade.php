<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Lot Tracer (WIP Control)"
                       subtitle="Pemantauan siklus Lot produksi, saldo material di mesin (Trigger & Pendamping), progres WIP, dan konversi Top/End Coil" />
    </x-slot>

    <x-flash />

    @php
        $groupedLots = $groupedLots ?? ($groups ?? collect());
    @endphp

    <div class="panel"
         x-data="lotTracerPage({{ $groupedLots->count() }})"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Pemantauan Lot Produksi</span>
                <span class="count-chip">
                    <span x-text="visibleGroups"></span><span>Kelompok Lot</span>
                </span>
                <span class="count-chip count-chip-sub">
                    <span>{{ $lotTracers->count() }}</span><span>Target WIP</span>
                </span>
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
                    <option value="">Semua Status</option>
                    <option value="ON PROGRESS">ON PROGRESS</option>
                    <option value="FINISH">FINISH</option>
                </select>

                <button type="button"
                        x-show="q || fProcess || fBomType || fStatus"
                        x-cloak
                        @click="q=''; fProcess=''; fBomType=''; fStatus=''; recalc()"
                        class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[3%] text-center">No</th>
                        <th class="w-[9%]">No. Lot &amp; Proses</th>
                        <th class="w-[18%]">Material &amp; Pendamping</th>
                        <th class="num w-[7%]">Mat. Out</th>
                        <th class="num w-[8%]">Material Saat Ini</th>
                        <th class="num w-[7%]">Mat. Return</th>
                        <th class="w-[14%]">Target Output Item</th>
                        <th class="num w-[6%]">Target Est</th>
                        <th class="num w-[6%]">Actual Prod</th>
                        <th class="num w-[4%]">NG</th>
                        <th class="num w-[5%]">Act FG</th>
                        <th class="num w-[5%]">Qty WIP</th>
                        <th class="w-[8%] text-center">Status &amp; Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groupedLots as $idx => $grp)
                        @php
                            $first = $grp['first'];
                            $matItem = $first->materialItem;
                            $bType = $grp['bom_type'];
                            $companions = $first->companion_details ?? [];
                        @endphp
                        <tr data-lot-row
                            data-search="{{ $grp['search_index'] }}"
                            data-process="{{ $grp['process_id'] }}"
                            data-bomtype="{{ $bType }}"
                            data-status="{{ $grp['status_lot'] }}"
                            x-show="matchRow($el)">

                            <td class="text-center num font-semibold text-slate-500 border-r border-slate-200">{{ $idx + 1 }}</td>

                            <td class="border-r border-slate-200">
                                <div class="num font-bold text-accent text-xs">{{ $grp['lot_number'] }}</div>
                                <div class="text-[11px] font-semibold text-slate-700 mt-0.5">{{ $grp['process_name'] }}</div>
                                <div class="mt-1 flex items-center gap-1 flex-wrap">
                                    @if($bType === 'SINGLE')
                                        <span class="pill pill-neu">SINGLE</span>
                                    @elseif($bType === 'MULTI-INPUT')
                                        <span class="pill pill-info">MULTI-INPUT</span>
                                    @elseif($bType === 'MULTI-OUTPUT')
                                        <span class="pill pill-warn">MULTI-OUTPUT</span>
                                    @else
                                        <span class="pill pill-good">KOMBINASI</span>
                                    @endif

                                    @if($first->top_end_coil !== null)
                                        <span class="pill pill-warn num text-[10px]">Top/End: {{ floatval($first->top_end_coil) }} {{ $matItem->uom ?? 'KG' }}</span>
                                    @endif
                                </div>
                            </td>

                            {{-- KOLOM MATERIAL & PENDAMPING --}}
                            <td class="p-0 border-r border-slate-200 align-top">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="pill pill-good w-[74px] justify-center flex-none text-[10px] h-5 px-1.5">Trigger</span>
                                            <div class="min-w-0 leading-tight">
                                                <div class="num text-[11px] font-bold text-slate-500 truncate">{{ $matItem->part_no ?: '-' }}</div>
                                                <div class="text-xs font-semibold text-slate-900 truncate">{{ $matItem->part_name ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <span class="pill pill-neu text-[10px] flex-none">{{ $matItem->uom ?? '' }}</span>
                                    </div>

                                    @foreach($companions as $c)
                                        <div class="subrow justify-between gap-2 bg-slate-50/60">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="pill pill-neu w-[74px] justify-center flex-none text-[10px] h-5 px-1.5">Pendamping</span>
                                                <div class="min-w-0 leading-tight">
                                                    <div class="num text-[11px] font-bold text-slate-500 truncate">{{ $c['part_no'] }}</div>
                                                    <div class="text-xs font-semibold text-slate-800 truncate">{{ $c['part_name'] }}</div>
                                                </div>
                                            </div>
                                            <span class="pill pill-neu text-[10px] flex-none">{{ $c['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM MAT. OUT (SEJAJAR SIMETRIS) --}}
                            <td class="p-0 border-r border-slate-200 align-top">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow-qty">
                                        <div class="inline-flex items-baseline justify-end gap-1 leading-none">
                                            <span class="num text-xs font-bold text-slate-900">{{ floatval($first->qty_material_out) }}</span>
                                            <span class="text-[10.5px] font-medium text-slate-500">{{ $matItem->uom ?? '' }}</span>
                                        </div>
                                    </div>
                                    @foreach($companions as $c)
                                        <div class="subrow-qty bg-slate-50/60">
                                            <div class="inline-flex items-baseline justify-end gap-1 leading-none">
                                                <span class="num text-xs font-semibold text-slate-700">{{ floatval($c['qty_out']) }}</span>
                                                <span class="text-[10.5px] text-slate-500">{{ $c['uom'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM MATERIAL SAAT INI (SEJAJAR SIMETRIS) --}}
                            <td class="p-0 border-r border-slate-200 align-top">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow-qty">
                                        <div class="inline-flex items-baseline justify-end gap-1 leading-none">
                                            <span class="num text-xs font-bold text-accent">{{ floatval($first->material_saat_ini) }}</span>
                                            <span class="text-[10.5px] font-medium text-slate-500">{{ $matItem->uom ?? '' }}</span>
                                        </div>
                                    </div>
                                    @foreach($companions as $c)
                                        <div class="subrow-qty bg-slate-50/60">
                                            <div class="inline-flex items-baseline justify-end gap-1 leading-none">
                                                <span class="num text-xs font-semibold text-accent">{{ floatval($c['qty_current']) }}</span>
                                                <span class="text-[10.5px] text-slate-500">{{ $c['uom'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM MAT. RETURN (SEJAJAR SIMETRIS) --}}
                            <td class="p-0 border-r border-slate-200 align-top">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow-qty">
                                        <div class="inline-flex items-baseline justify-end gap-1 leading-none">
                                            <span class="num text-xs font-semibold {{ (float)$first->qty_material_return > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                                {{ floatval($first->qty_material_return) }}
                                            </span>
                                            <span class="text-[10.5px] text-slate-400">{{ $matItem->uom ?? '' }}</span>
                                        </div>
                                    </div>
                                    @foreach($companions as $c)
                                        <div class="subrow-qty bg-slate-50/60">
                                            <div class="inline-flex items-baseline justify-end gap-1 leading-none">
                                                <span class="num text-xs font-semibold {{ (float)$c['qty_return'] > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                                    {{ floatval($c['qty_return']) }}
                                                </span>
                                                <span class="text-[10.5px] text-slate-400">{{ $c['uom'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM TARGET OUTPUT ITEM --}}
                            <td class="p-0 border-r border-slate-200 align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow justify-between gap-2">
                                            <div class="min-w-0 leading-tight">
                                                <div class="num text-[11px] font-bold text-accent truncate">{{ $r->targetItem->part_no ?: '-' }}</div>
                                                <div class="text-xs font-semibold text-slate-900 truncate">{{ $r->targetItem->part_name ?? '-' }}</div>
                                            </div>
                                            <span class="pill pill-neu text-[10px] flex-none">{{ $r->targetItem->uom ?? 'PCS' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM TARGET EST --}}
                            <td class="p-0 border-r border-slate-200 align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow-qty">
                                            <span class="num text-xs font-bold text-slate-800">{{ floatval($r->target_qty_estimasi) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM ACTUAL PROD --}}
                            <td class="p-0 border-r border-slate-200 align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow-qty">
                                            <span class="num text-xs font-bold text-blue-700">{{ floatval($r->actual_qty_prod) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM NG --}}
                            <td class="p-0 border-r border-slate-200 align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow-qty">
                                            <span class="num text-xs font-bold {{ (float)$r->qty_ng > 0 ? 'text-rose-700' : 'text-slate-400' }}">
                                                {{ floatval($r->qty_ng) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM ACT FG --}}
                            <td class="p-0 border-r border-slate-200 align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow-qty">
                                            <span class="num text-xs font-bold text-emerald-700">{{ floatval($r->act_fg) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM QTY WIP --}}
                            <td class="p-0 border-r border-slate-200 align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow-qty">
                                            <span class="num text-xs font-bold {{ (float)$r->qty_wip > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                                                {{ floatval($r->qty_wip) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM STATUS & AKSI --}}
                            <td class="p-0 align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $r)
                                        <div class="subrow justify-center gap-1.5">
                                            @if($r->status_lot === 'ON PROGRESS')
                                                <span class="pill pill-good text-[10px]">ON PROGRESS</span>
                                                <form action="{{ route('lot_tracers.finish', $r->id) }}" method="POST" class="inline"
                                                      @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Tutup Lot (Set FINISH)', message: 'Tutup Lot {{ $r->lot_number }} ({{ addslashes($r->targetItem->part_name ?? '') }}) menjadi FINISH? Sisa WIP akan diset ke 0.' })">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="act-btn" title="Set FINISH (Tutup Lot)">
                                                        <x-icon name="check" />
                                                    </button>
                                                </form>
                                            @else
                                                <span class="pill pill-neu text-[10px]">FINISH</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="13"><div class="empty">Belum ada data Lot Tracer.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function lotTracerPage(initGroups) {
            return {
                q: '',
                fProcess: '',
                fBomType: '',
                fStatus: '',
                visibleGroups: initGroups,
                matchRow(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const p = el.dataset.process || '';
                    const bt = el.dataset.bomtype || '';
                    const st = el.dataset.status || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.fProcess && p !== String(this.fProcess)) return false;
                    if (this.fBomType && bt !== this.fBomType) return false;
                    if (this.fStatus && st !== this.fStatus) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tr[data-lot-row]').forEach(tr => {
                            if (this.matchRow(tr)) c++;
                        });
                        this.visibleGroups = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>