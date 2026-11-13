<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Lot Tracer (Meja Kontrol WIP Produksi)"
                       subtitle="Pemantauan alur material keluar, sisa material di mesin, estimasi target, hasil produksi, NG, hingga WIP per Nomor Lot" />
    </x-slot>

    <x-flash />

    @php
        $lotTracers = $lotTracers ?? \App\Models\LotTracer::with(['process', 'materialItem', 'targetItem'])->orderBy('id', 'desc')->get();
        $processes = $processes ?? \App\Models\Process::orderBy('process_name', 'asc')->get();

        if (!isset($groupedLots)) {
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
        }
    @endphp

    <div class="panel"
         x-data="lotTracerPage({{ $groupedLots->count() }})"
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
                       class="h-8 text-xs px-2.5 w-48">

                <select x-model="fProcess" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Proses</option>
                    @foreach($processes as $proc)
                        <option value="{{ $proc->id }}">{{ $proc->process_name }}</option>
                    @endforeach
                </select>

                <select x-model="fStatus" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
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
                        <th class="min-w-[250px]">Material &amp; Pendamping</th>
                        <th class="num w-28">Mat. Out</th>
                        <th class="num w-32">Material Saat Ini</th>
                        <th class="num w-28">Mat. Return</th>
                        <th class="min-w-[210px]">Target Output</th>
                        <th class="num w-28">Target Est</th>
                        <th class="w-40">Actual Prod</th>
                        <th class="num w-24">Qty NG</th>
                        <th class="num w-24">Act FG</th>
                        <th class="num w-28">Qty WIP</th>
                        <th class="w-32 text-center">Status &amp; Aksi</th>
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
                                <div class="num font-bold text-accent">{{ $grp['lot_number'] }}</div>
                                <div class="text-xs font-semibold text-slate-700 mt-0.5">{{ $grp['process_name'] }}</div>
                                <div class="mt-1">
                                    @if($grp['bom_type'] === 'SINGLE')
                                        <span class="pill pill-neu">{{ $grp['bom_type'] }}</span>
                                    @elseif($grp['bom_type'] === 'MULTI-INPUT')
                                        <span class="pill pill-info">{{ $grp['bom_type'] }}</span>
                                    @elseif($grp['bom_type'] === 'MULTI-OUTPUT')
                                        <span class="pill pill-warn">{{ $grp['bom_type'] }}</span>
                                    @else
                                        <span class="pill pill-good">{{ $grp['bom_type'] }}</span>
                                    @endif
                                </div>
                            </td>

                            {{-- MATERIAL & PENDAMPING (Tinggi h-[52px] & lebar badge w-[78px] agar lurus dengan Mat. Out) --}}
                            <td class="p-0 align-top">
                                <div class="divide-y divide-slate-200">
                                    <div class="px-3 h-[52px] flex items-center gap-2.5">
                                        <span class="pill pill-good w-[78px] justify-center flex-none text-[10px] h-5 px-1.5">Trigger</span>
                                        <div class="min-w-0 flex-1 leading-tight">
                                            <div class="num text-[11.5px] font-bold text-slate-600 truncate">{{ $mat->part_no ?: '-' }}</div>
                                            <div class="text-xs font-semibold text-slate-900 truncate" title="{{ $mat->part_name ?? '-' }}">{{ $mat->part_name ?? '-' }}</div>
                                        </div>
                                    </div>
                                    @foreach($companions as $c)
                                        <div class="px-3 h-[52px] flex items-center gap-2.5 bg-slate-50/50">
                                            <span class="pill pill-neu w-[78px] justify-center flex-none text-[10px] h-5 px-1.5">Pendamping</span>
                                            <div class="min-w-0 flex-1 leading-tight">
                                                <div class="num text-[11.5px] font-bold text-slate-500 truncate">{{ $c['part_no'] ?: '-' }}</div>
                                                <div class="text-xs font-medium text-slate-800 truncate" title="{{ $c['part_name'] }}">{{ $c['part_name'] }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- MAT. OUT (Tinggi h-[52px] sejajar 100%) --}}
                            <td class="num p-0 whitespace-nowrap align-top">
                                <div class="divide-y divide-slate-200">
                                    <div class="px-3 h-[52px] flex items-center justify-end font-semibold text-slate-800">
                                        {{ floatval($first->qty_material_out) }} <span class="text-[11px] text-slate-500 ml-1">{{ $mat->uom ?? '' }}</span>
                                    </div>
                                    @foreach($companions as $c)
                                        <div class="px-3 h-[52px] flex items-center justify-end text-xs font-medium text-slate-700 bg-slate-50/50">
                                            {{ floatval($c['qty_out']) }} <span class="text-[10.5px] text-slate-500 ml-1">{{ $c['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- MATERIAL SAAT INI (Tinggi h-[52px] sejajar 100%) --}}
                            <td class="num p-0 whitespace-nowrap align-top">
                                <div class="divide-y divide-slate-200">
                                    <div class="px-3 h-[52px] flex items-center justify-end font-bold text-blue-700">
                                        {{ floatval($first->material_saat_ini) }} <span class="text-[11px] text-slate-500 ml-1">{{ $mat->uom ?? '' }}</span>
                                    </div>
                                    @foreach($companions as $c)
                                        <div class="px-3 h-[52px] flex items-center justify-end text-xs font-semibold text-blue-700 bg-slate-50/50">
                                            {{ floatval($c['qty_current']) }} <span class="text-[10.5px] text-slate-500 ml-1">{{ $c['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- MAT. RETURN (Tinggi h-[52px] sejajar 100%) --}}
                            <td class="num p-0 whitespace-nowrap align-top">
                                <div class="divide-y divide-slate-200">
                                    <div class="px-3 h-[52px] flex items-center justify-end font-semibold text-amber-700">
                                        {{ floatval($first->qty_material_return) }} <span class="text-[11px] text-slate-500 ml-1">{{ $mat->uom ?? '' }}</span>
                                    </div>
                                    @foreach($companions as $c)
                                        <div class="px-3 h-[52px] flex items-center justify-end text-xs font-medium text-amber-700 bg-slate-50/50">
                                            {{ floatval($c['qty_return']) }} <span class="text-[10.5px] text-slate-500 ml-1">{{ $c['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- TARGET OUTPUT (Tinggi h-[52px]) --}}
                            <td class="p-0 align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        <div class="px-3 h-[52px] flex flex-col justify-center leading-tight">
                                            <div class="num text-xs font-bold text-accent truncate">{{ $lt->targetItem->part_no ?: '-' }}</div>
                                            <div class="text-xs font-semibold text-slate-900 truncate" title="{{ $lt->targetItem->part_name ?? '-' }}">{{ $lt->targetItem->part_name ?? '-' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- TARGET EST (Tinggi h-[52px]) --}}
                            <td class="num p-0 whitespace-nowrap align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        <div class="px-3 h-[52px] flex items-center justify-end font-semibold text-slate-800">
                                            {{ floatval($lt->target_qty_estimasi) }} <span class="text-[11px] text-slate-500 ml-1">{{ $lt->targetItem->uom ?? 'PCS' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- ACTUAL PROD (Tinggi h-[52px]) --}}
                            <td class="p-0 align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        @php
                                            $pct = $lt->target_qty_estimasi > 0
                                                ? min(100, round(($lt->actual_qty_prod / $lt->target_qty_estimasi) * 100))
                                                : 0;
                                        @endphp
                                        <div class="px-3 h-[52px] flex flex-col justify-center">
                                            <div class="flex items-center justify-between text-xs mb-1" style="width: 120px;">
                                                <b class="num text-slate-900">{{ floatval($lt->actual_qty_prod) }} {{ $lt->targetItem->uom ?? 'PCS' }}</b>
                                                <span class="num text-[11px] font-semibold text-slate-500">{{ $pct }}%</span>
                                            </div>
                                            <span class="track"><i style="width:{{ $pct }}%"></i></span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- QTY NG (Tinggi h-[52px]) --}}
                            <td class="num p-0 whitespace-nowrap align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        <div class="px-3 h-[52px] flex items-center justify-end font-semibold text-rose-700">
                                            {{ floatval($lt->qty_ng) }}
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- ACT FG (Tinggi h-[52px]) --}}
                            <td class="num p-0 whitespace-nowrap align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        <div class="px-3 h-[52px] flex items-center justify-end font-bold text-emerald-700">
                                            {{ floatval($lt->act_fg) }}
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- QTY WIP (Tinggi h-[52px]) --}}
                            <td class="num p-0 whitespace-nowrap align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        <div class="px-3 h-[52px] flex flex-col items-end justify-center">
                                            @if($lt->status_lot === 'FINISH')
                                                <span class="font-bold text-slate-500">0 {{ $lt->targetItem->uom ?? 'PCS' }}</span>
                                                @if(($lt->top_end_coil ?? 0) > 0)
                                                    <span class="pill pill-warn text-[10px] h-4 px-1.5 mt-0.5">
                                                        Sisa/Top-End: {{ floatval($lt->top_end_coil) }} {{ $mat->uom ?? '' }}
                                                    </span>
                                                @endif
                                            @else
                                                <span class="font-bold text-amber-700">{{ floatval($lt->qty_wip) }} {{ $lt->targetItem->uom ?? 'PCS' }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- STATUS & AKSI (Tinggi h-[52px]) --}}
                            <td class="p-0 text-center align-top">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['rows'] as $lt)
                                        <div class="px-3 h-[52px] flex items-center justify-center gap-1.5">
                                            @if($lt->status_lot === 'ON PROGRESS')
                                                <span class="pill pill-good">ON PROGRESS</span>
                                                <form action="{{ route('lot_tracers.finish', $lt->id) }}" method="POST" class="inline"
                                                      @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Tutup Lot (Set FINISH)', message: 'Tandai Lot {{ $lt->lot_number }} ({{ addslashes($lt->targetItem->part_name ?? '') }}) sebagai FINISH?' })">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="act-btn" title="Set FINISH (Tutup Lot)">
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
                        <tr><td colspan="13"><div class="empty">Belum ada data Lot Tracer.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function lotTracerPage(initialCount) {
            return {
                q: '',
                fProcess: '',
                fStatus: '',
                visibleGroups: initialCount,
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
            };
        }
    </script>
</x-app-layout>