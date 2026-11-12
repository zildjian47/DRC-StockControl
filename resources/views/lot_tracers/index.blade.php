<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Lot Tracer (Meja Kontrol WIP Produksi)"
                       subtitle="Pemantauan alur material keluar, sisa material di mesin, estimasi target, hasil produksi, NG, hingga WIP per Nomor Lot" />
    </x-slot>

    <x-flash />

    @php
        $groupedLots = $lotTracers->groupBy('lot_number')->map(function ($rows, $lotNo) {
            $first = $rows->first();
            $bomType = $first->bom_type ?? 'SINGLE';
            $searchStr = strtolower(
                $lotNo . ' ' .
                $bomType . ' ' .
                ($first->process->process_name ?? '') . ' ' .
                ($first->materialItem->part_no ?? '') . ' ' .
                ($first->materialItem->part_name ?? '') . ' ' .
                $rows->map(fn ($r) => ($r->targetItem->part_no ?? '') . ' ' . ($r->targetItem->part_name ?? ''))->implode(' ')
            );

            return [
                'lot_number'   => $lotNo,
                'bom_type'     => $bomType,
                'first'        => $first,
                'rows'         => $rows,
                'process_id'   => $first->process_id,
                'process_name' => $first->process->process_name ?? '-',
                'status_lot'   => $rows->contains('status_lot', 'ON PROGRESS') ? 'ON PROGRESS' : 'FINISH',
                'search_index' => $searchStr,
            ];
        })->values();
    @endphp

    <div class="panel"
         x-data="{
            q: '',
            fProcess: '',
            fStatus: '',
            visibleGroups: {{ $groupedLots->count() }},
            matchLot(el) {
                const s = (el.dataset.search || '').toLowerCase();
                const p = el.dataset.process || '';
                const st = el.dataset.status || '';
                if (this.q && !s.includes(this.q.toLowerCase())) return false;
                if (this.fProcess && p !== String(this.fProcess)) return false;
                if (this.fStatus && st !== this.fStatus) return false;
                return true;
            },
            recalc() {
                this.$nextTick(() => {
                    let c = 0;
                    this.$el.querySelectorAll('tbody tr[data-lot-row]').forEach(tr => {
                        if (this.matchLot(tr)) c++;
                    });
                    this.visibleGroups = c;
                });
            }
         }"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Pemantauan Lot Produksi</span>
                <span class="count-chip"><span x-text="visibleGroups"></span> Kelompok Lot</span>
                <span class="count-chip count-chip-sub">{{ $lotTracers->count() }} Target WIP</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari No. Lot / Material / Target..."
                       class="h-8 text-xs px-2.5 w-56">

                <select x-model="fProcess" @change="recalc()" class="h-8 text-xs py-0 pl-2.5 pr-7">
                    <option value="">Semua Proses</option>
                    @foreach($processes as $proc)
                        <option value="{{ $proc->id }}">{{ $proc->process_name }}</option>
                    @endforeach
                </select>

                <select x-model="fStatus" @change="recalc()" class="h-8 text-xs py-0 pl-2.5 pr-7">
                    <option value="">Semua Status</option>
                    <option value="ON PROGRESS">ON PROGRESS</option>
                    <option value="FINISH">FINISH</option>
                </select>

                <button type="button" x-show="q || fProcess || fStatus" x-cloak
                        @click="q=''; fProcess=''; fStatus=''; recalc()" class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-12 text-center">No</th>
                        <th class="w-36">No. Lot &amp; Proses</th>
                        <th class="min-w-[220px]">Material &amp; Pendamping</th>
                        <th class="num w-28">Mat. Out</th>
                        <th class="num w-32">Material Saat Ini</th>
                        <th class="num w-28">Mat. Return</th>
                        <th class="min-w-[220px]">Target Output</th>
                        <th class="num w-28">Target Est</th>
                        <th class="w-44">Actual Prod</th>
                        <th class="num w-24">Qty NG</th>
                        <th class="num w-24">Act FG</th>
                        <th class="num w-28">Qty WIP</th>
                        <th class="w-28 text-center">Status &amp; Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groupedLots as $idx => $grp)
                        @php
                            $first = $grp['first'];
                            $mat = $first->materialItem;
                            $companions = $first->companion_details ?? [];
                        @endphp
                        <tr data-lot-row
                            data-search="{{ $grp['search_index'] }}"
                            data-process="{{ $grp['process_id'] }}"
                            data-status="{{ $grp['status_lot'] }}"
                            x-show="matchLot($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td>
                                <div class="num font-bold text-accent text-sm">{{ $grp['lot_number'] }}</div>
                                <div class="text-xs font-semibold text-slate-700 mt-0.5">{{ $grp['process_name'] }}</div>
                                <div class="mt-1">
                                    <span class="pill pill-neu text-[10px] h-5 px-1.5">{{ $grp['bom_type'] }}</span>
                                </div>
                            </td>

                            {{-- Material Trigger & Pendamping --}}
                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    <div class="px-3 py-2 min-h-[44px] flex flex-col justify-center">
                                        <div class="flex items-center gap-1.5">
                                            <span class="pill pill-good text-[10px] h-4 px-1.5">Trigger</span>
                                            <span class="num text-xs font-semibold text-slate-700">{{ $mat->part_no ?: '-' }}</span>
                                        </div>
                                        <div class="text-xs font-semibold text-slate-900 mt-0.5">{{ $mat->part_name ?? '-' }}</div>
                                    </div>
                                    @foreach($companions as $c)
                                        <div class="px-3 py-2 min-h-[42px] flex flex-col justify-center bg-slate-50/50">
                                            <div class="flex items-center gap-1.5">
                                                <span class="pill pill-neu text-[10px] h-4 px-1.5">Pendamping</span>
                                                <span class="num text-xs font-semibold text-slate-600">{{ $c['part_no'] ?: '-' }}</span>
                                            </div>
                                            <div class="text-xs text-slate-800 font-medium mt-0.5">{{ $c['part_name'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Mat. Out --}}
                            <td class="num p-0 whitespace-nowrap">
                                <div class="divide-y divide-slate-200">
                                    <div class="px-3 py-2 min-h-[44px] flex items-center justify-end font-semibold text-slate-800">
                                        {{ floatval($first->qty_material_out) }} <span class="text-[11px] text-slate-500 ml-1">{{ $mat->uom ?? '' }}</span>
                                    </div>
                                    @foreach($companions as $c)
                                        <div class="px-3 py-2 min-h-[42px] flex items-center justify-end text-xs text-slate-700 bg-slate-50/50">
                                            {{ floatval($c['qty_out']) }} <span class="text-[10.5px] text-slate-500 ml-1">{{ $c['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Material Saat Ini --}}
                            <td class="num p-0 whitespace-nowrap">
                                <div class="divide-y divide-slate-200">
                                    <div class="px-3 py-2 min-h-[44px] flex items-center justify-end font-bold text-blue-700">
                                        {{ floatval($first->material_saat_ini) }} <span class="text-[11px] text-slate-500 ml-1">{{ $mat->uom ?? '' }}</span>
                                    </div>
                                    @foreach($companions as $c)
                                        <div class="px-3 py-2 min-h-[42px] flex items-center justify-end text-xs font-semibold text-blue-700 bg-slate-50/50">
                                            {{ floatval($c['qty_current']) }} <span class="text-[10.5px] text-slate-500 ml-1">{{ $c['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Mat. Return --}}
                            <td class="num p-0 whitespace-nowrap">
                                <div class="divide-y divide-slate-200">
                                    <div class="px-3 py-2 min-h-[44px] flex items-center justify-end font-semibold text-amber-700">
                                        {{ floatval($first->qty_material_return) }} <span class="text-[11px] text-slate-500 ml-1">{{ $mat->uom ?? '' }}</span>
                                    </div>
                                    @foreach($companions as $c)
                                        <div class="px-3 py-2 min-h-[42px] flex items-center justify-end text-xs text-amber-700 bg-slate-50/50">
                                            {{ floatval($c['qty_return']) }} <span class="text-[10.5px] text-slate-500 ml-1">{{ $c['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Target Output --}}
                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        <div class="px-3 py-2.5 min-h-[54px] flex flex-col justify-center">
                                            <div class="num text-xs font-semibold text-accent">{{ $lt->targetItem->part_no ?: '-' }}</div>
                                            <div class="text-xs font-semibold text-slate-900">{{ $lt->targetItem->part_name ?? '-' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Target Est --}}
                            <td class="num p-0 whitespace-nowrap">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        <div class="px-3 py-2.5 min-h-[54px] flex items-center justify-end font-semibold text-slate-800">
                                            {{ floatval($lt->target_qty_estimasi) }} <span class="text-[11px] text-slate-500 ml-1">{{ $lt->targetItem->uom ?? 'PCS' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Actual Prod + Bar Progres 8px x 120px Seragam --}}
                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        @php
                                            $pct = $lt->target_qty_estimasi > 0
                                                ? min(100, round(($lt->actual_qty_prod / $lt->target_qty_estimasi) * 100))
                                                : 0;
                                        @endphp
                                        <div class="px-3 py-2 min-h-[54px] flex flex-col justify-center">
                                            <div class="flex items-center justify-between text-xs mb-1" style="width: 120px;">
                                                <b class="num text-slate-900">{{ floatval($lt->actual_qty_prod) }} {{ $lt->targetItem->uom ?? 'PCS' }}</b>
                                                <span class="num text-[11px] font-semibold text-slate-500">{{ $pct }}%</span>
                                            </div>
                                            <div style="width: 120px; height: 8px; background-color: #E2E8F0; border-radius: 4px; overflow: hidden; transform: translateZ(0);">
                                                <div style="width: {{ $pct }}%; height: 8px; background-color: #0B5CAD; border-radius: 4px;"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Qty NG --}}
                            <td class="num p-0 whitespace-nowrap">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        <div class="px-3 py-2.5 min-h-[54px] flex items-center justify-end font-semibold text-rose-700">
                                            {{ floatval($lt->qty_ng) }}
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Act FG --}}
                            <td class="num p-0 whitespace-nowrap">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        <div class="px-3 py-2.5 min-h-[54px] flex items-center justify-end font-bold text-emerald-700">
                                            {{ floatval($lt->act_fg) }}
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Qty WIP / Top-End Coil --}}
                            <td class="num p-0 whitespace-nowrap">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        <div class="px-3 py-2.5 min-h-[54px] flex flex-col items-end justify-center">
                                            @if($lt->status_lot === 'FINISH')
                                                <span class="font-bold text-slate-500">0 {{ $lt->targetItem->uom ?? 'PCS' }}</span>
                                                @if(($lt->top_end_sisa ?? 0) > 0)
                                                    <span class="pill pill-warn text-[10px] h-4 px-1.5 mt-0.5">
                                                        Sisa/Top-End: {{ floatval($lt->top_end_sisa) }} {{ $mat->uom ?? '' }}
                                                    </span>
                                                @endif
                                            @else
                                                <span class="font-bold text-amber-700">{{ floatval($lt->qty_wip) }} {{ $lt->targetItem->uom ?? 'PCS' }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Status & Aksi --}}
                            <td class="p-0 text-center">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        <div class="px-3 py-2.5 min-h-[54px] flex items-center justify-center gap-1.5">
                                            @if($lt->status_lot === 'ON PROGRESS')
                                                <span class="pill pill-good text-[11px]">ON PROGRESS</span>
                                                <form action="{{ route('lot_tracers.finish', $lt->id) }}" method="POST"
                                                      @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Tutup Lot (Set FINISH)', message: 'Tandai Lot {{ $lt->lot_number }} ({{ $lt->targetItem->part_name ?? '' }}) sebagai FINISH?' })">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="act-icon" title="Set FINISH (Tutup Lot)">
                                                        <x-icon name="check" class="w-3.5 h-3.5" />
                                                    </button>
                                                </form>
                                            @else
                                                <span class="pill pill-neu text-[11px]">FINISH</span>
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
</x-app-layout>