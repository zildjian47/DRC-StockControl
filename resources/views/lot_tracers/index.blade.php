<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Lot Tracer"
                       subtitle="Pemantauan progres Lot produksi, sisa material di mesin, komponen pendamping, WIP, Act FG, dan Top/End Coil" />
    </x-slot>

    <x-flash />

    @php
        $totalTargetRows = $groupedLots->sum(fn ($g) => $g['rows']->count());
    @endphp

    <div class="panel"
         x-data="lotTracerPage()"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Pemantauan Lot Produksi</span>
                <span class="count-chip"><span x-text="visibleGroups">{{ $groupedLots->count() }}</span><span>KELOMPOK LOT</span></span>
                <span class="count-chip count-chip-sub"><span x-text="visibleTargets">{{ $totalTargetRows }}</span><span>TARGET WIP</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari No. Lot, Material, Output..."
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

                <button type="button"
                        x-show="q || fProcess || fStatus" x-cloak
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
                        <th class="w-[4%] text-center">No</th>
                        <th class="w-[9%]">No. Lot &amp; Tipe</th>
                        <th class="w-[9%]">Proses</th>
                        <th class="w-[18%]">Material &amp; Pendamping</th>
                        <th class="num w-[6%]">Mat. Out</th>
                        <th class="num w-[7%]">Material Saat Ini</th>
                        <th class="num w-[6%]">Mat. Return</th>
                        <th class="num w-[6%]">Top/End Coil</th>
                        <th class="w-[13%]">Target Output</th>
                        <th class="num w-[5%]">Target Est</th>
                        <th class="num w-[5%]">Act Prod</th>
                        <th class="num w-[4%]">NG</th>
                        <th class="num w-[5%]">Act FG</th>
                        <th class="num w-[5%]">WIP</th>
                        <th class="w-[9%]">Progres</th>
                        <th class="w-[7%] text-center">Status &amp; Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groupedLots as $gIdx => $grp)
                        @php
                            $first = $grp['first'];
                            $matItem = $first->materialItem;
                            $matUom = $matItem->uom ?? '';
                            $companions = $first->companion_details ?? [];
                            $rows = $grp['rows'];
                            $matSaatIni = $grp['material_saat_ini'];
                            $topEndVal = $grp['top_end_coil'];
                        @endphp
                        <tr x-ref="grp_{{ $gIdx }}"
                            data-process="{{ $grp['process_id'] }}"
                            data-status="{{ $grp['status_lot'] }}"
                            data-search="{{ $grp['search_index'] }}"
                            data-targets="{{ $rows->count() }}"
                            x-show="matchGroup($el)">

                            {{-- 1. NO KELOMPOK --}}
                            <td class="text-center num text-slate-500 font-semibold" data-col-no>{{ $gIdx + 1 }}</td>

                            {{-- 2. NO. LOT & TIPE BOM --}}
                            <td>
                                <div class="num font-bold text-accent text-[13px]">{{ $grp['lot_number'] }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <span class="pill pill-info">{{ $grp['bom_type'] }}</span>
                                    @if($first->is_coil)
                                        <span class="text-[10.5px] font-semibold uppercase tracking-wide text-slate-500">· COIL</span>
                                    @endif
                                </div>
                            </td>

                            {{-- 3. PROSES --}}
                            <td>
                                <span class="font-semibold text-xs text-slate-800">{{ $grp['process_name'] }}</span>
                            </td>

                            {{-- 4. MATERIAL & PENDAMPING --}}
                            <td class="!p-0">
                                <div class="divide-y divide-slate-200">
                                    {{-- Baris Trigger Utama --}}
                                    <div class="subrow justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="pill pill-good w-[78px] justify-center flex-none">TRIGGER</span>
                                            <div class="min-w-0">
                                                @if(!empty($matItem?->part_no))
                                                    <div class="num text-[11px] font-bold text-accent truncate">{{ $matItem->part_no }}</div>
                                                @endif
                                                <div class="font-semibold text-slate-900 text-xs truncate">{{ $matItem->part_name ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <span class="ty ty-{{ ($matItem->type ?? 'RM') === 'FG/KOMPONEN' ? 'FGK' : ($matItem->type ?? 'RM') }} flex-none">
                                            {{ $matItem->type ?? 'RM' }}
                                        </span>
                                    </div>

                                    {{-- Baris Komponen Pendamping --}}
                                    @foreach($companions as $comp)
                                        <div class="subrow justify-between gap-2 bg-slate-50/50">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="pill pill-neu w-[78px] justify-center flex-none">PENDAMPING</span>
                                                <div class="min-w-0">
                                                    @if(!empty($comp['part_no']) && $comp['part_no'] !== '-')
                                                        <div class="num text-[11px] font-bold text-accent truncate">{{ $comp['part_no'] }}</div>
                                                    @endif
                                                    <div class="font-medium text-slate-800 text-xs truncate">{{ $comp['part_name'] }}</div>
                                                </div>
                                            </div>
                                            <span class="num text-[11px] font-semibold text-slate-500 flex-none">{{ $comp['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- 5. MAT. OUT --}}
                            <td class="num !p-0">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow-qty">
                                        <span class="font-bold text-slate-900">{{ floatval($first->qty_material_out) }}</span>
                                        <span class="text-[11px] text-slate-500">{{ $matUom }}</span>
                                    </div>
                                    @foreach($companions as $comp)
                                        <div class="subrow-qty bg-slate-50/50">
                                            <span class="font-semibold text-slate-700">{{ floatval($comp['qty_out']) }}</span>
                                            <span class="text-[11px] text-slate-500">{{ $comp['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- 6. MATERIAL SAAT INI --}}
                            <td class="num !p-0">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow-qty">
                                        <span class="font-bold {{ $matSaatIni > 0 ? 'text-accent' : 'text-slate-400' }}">
                                            {{ floatval($matSaatIni) }}
                                        </span>
                                        <span class="text-[11px] text-slate-500">{{ $matUom }}</span>
                                    </div>
                                    @foreach($companions as $comp)
                                        <div class="subrow-qty bg-slate-50/50">
                                            <span class="font-semibold {{ $comp['qty_saat_ini'] > 0 ? 'text-accent' : 'text-slate-400' }}">
                                                {{ floatval($comp['qty_saat_ini']) }}
                                            </span>
                                            <span class="text-[11px] text-slate-500">{{ $comp['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- 7. MAT. RETURN --}}
                            <td class="num !p-0">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow-qty">
                                        <span class="font-semibold {{ $first->qty_material_return > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                            {{ floatval($first->qty_material_return) }}
                                        </span>
                                        <span class="text-[11px] text-slate-500">{{ $matUom }}</span>
                                    </div>
                                    @foreach($companions as $comp)
                                        <div class="subrow-qty bg-slate-50/50">
                                            <span class="font-semibold {{ $comp['qty_return'] > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                                {{ floatval($comp['qty_return']) }}
                                            </span>
                                            <span class="text-[11px] text-slate-500">{{ $comp['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- 8. TOP/END COIL --}}
                            <td class="num whitespace-nowrap">
                                @if($topEndVal !== null)
                                    <div class="inline-flex items-baseline justify-end gap-1">
                                        <span class="font-bold {{ $topEndVal > 0 ? 'text-amber-700' : 'text-slate-500' }}">
                                            {{ floatval($topEndVal) }}
                                        </span>
                                        <span class="text-[11px] text-slate-500">KG</span>
                                    </div>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>

                            {{-- 9. TARGET OUTPUT --}}
                            <td class="!p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($rows as $r)
                                        @php
                                            $tItem = $r->targetItem;
                                            $tType = $tItem->type ?? 'FG';
                                        @endphp
                                        <div class="subrow justify-between gap-2">
                                            <div class="min-w-0">
                                                @if(!empty($tItem?->part_no))
                                                    <div class="num text-[11px] font-bold text-accent truncate">{{ $tItem->part_no }}</div>
                                                @endif
                                                <div class="font-semibold text-slate-900 text-xs truncate">{{ $tItem->part_name ?? '-' }}</div>
                                            </div>
                                            <span class="ty ty-{{ $tType === 'FG/KOMPONEN' ? 'FGK' : $tType }} flex-none">{{ $tType }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- 10. TARGET EST (+ UOM OUTPUT) --}}
                            <td class="num !p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($rows as $r)
                                        @php $outUom = $r->targetItem->uom ?? 'PCS'; @endphp
                                        <div class="subrow-qty">
                                            <span class="font-semibold text-slate-800">{{ floatval($r->target_qty_estimasi) }}</span>
                                            <span class="text-[11px] text-slate-500">{{ $outUom }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- 11. ACT PROD (+ UOM OUTPUT) --}}
                            <td class="num !p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($rows as $r)
                                        @php $outUom = $r->targetItem->uom ?? 'PCS'; @endphp
                                        <div class="subrow-qty">
                                            <span class="font-bold text-slate-900">{{ floatval($r->actual_qty_prod) }}</span>
                                            <span class="text-[11px] text-slate-500">{{ $outUom }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- 12. NG (SEJAJAR LURUS + UOM OUTPUT) --}}
                            <td class="num !p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($rows as $r)
                                        @php $outUom = $r->targetItem->uom ?? 'PCS'; @endphp
                                        <div class="subrow-qty">
                                            <span class="{{ (float) $r->qty_ng > 0 ? 'font-bold text-rose-700' : 'font-semibold text-slate-400' }}">
                                                {{ floatval($r->qty_ng) }}
                                            </span>
                                            <span class="text-[11px] text-slate-500">{{ $outUom }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- 13. ACT FG (+ UOM OUTPUT) --}}
                            <td class="num !p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($rows as $r)
                                        @php $outUom = $r->targetItem->uom ?? 'PCS'; @endphp
                                        <div class="subrow-qty">
                                            <span class="font-bold text-emerald-700">{{ floatval($r->act_fg) }}</span>
                                            <span class="text-[11px] text-slate-500">{{ $outUom }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- 14. WIP (+ UOM OUTPUT) --}}
                            <td class="num !p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($rows as $r)
                                        @php $outUom = $r->targetItem->uom ?? 'PCS'; @endphp
                                        <div class="subrow-qty">
                                            <span class="font-bold {{ (float) $r->qty_wip > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                                                {{ floatval($r->qty_wip) }}
                                            </span>
                                            <span class="text-[11px] text-slate-500">{{ $outUom }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- 15. PROGRES --}}
                            <td class="!p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($rows as $r)
                                        @php
                                            $pct = (float) $r->target_qty_estimasi > 0
                                                ? min(100, round(((float) $r->actual_qty_prod / (float) $r->target_qty_estimasi) * 100))
                                                : 0;
                                        @endphp
                                        <div class="subrow">
                                            <div class="inline-flex items-center gap-2" style="width: 160px;">
                                                <span class="track"><i style="width:{{ $pct }}%"></i></span>
                                                <span class="num text-xs font-semibold text-slate-700" style="width: 34px; text-align: right; flex: 0 0 34px;">{{ $pct }}%</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- 16. STATUS & AKSI --}}
                            <td class="text-center whitespace-nowrap">
                                @php
                                    $closeMsg = "Tutup seluruh Lot {$grp['lot_number']} menjadi FINISH? Semua sisa WIP pada Lot ini akan dianggap 0"
                                        . ($first->is_coil ? " dan sisa berat Coil akan dicatat sebagai Top/End Coil." : ".");
                                @endphp
                                <div class="inline-flex items-center justify-center gap-1.5">
                                    @if($grp['status_lot'] === 'ON PROGRESS')
                                        <span class="pill pill-good">ON PROGRESS</span>
                                        <form id="fin-lot-{{ $first->id }}"
                                              action="{{ route('lot_tracers.set_finish', $first->id) }}"
                                              method="POST"
                                              class="inline"
                                              data-confirm-title="Tutup Lot Produksi"
                                              data-confirm-msg="{{ $closeMsg }}"
                                              @submit.prevent="$dispatch('open-confirm', { form: $el, title: $el.dataset.confirmTitle, message: $el.dataset.confirmMsg })">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="act-btn"
                                                    title="Tutup Lot (Set FINISH)">
                                                <x-icon name="check" />
                                            </button>
                                        </form>
                                    @else
                                        <span class="pill pill-neu">FINISH</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="16">
                                <div class="empty">Belum ada data Lot Tracer.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function lotTracerPage() {
            return {
                q: '',
                fProcess: '',
                fStatus: '',
                visibleGroups: {{ $groupedLots->count() }},
                visibleTargets: {{ $totalTargetRows }},

                matchGroup(el) {
                    if (this.fProcess && String(el.dataset.process) !== String(this.fProcess)) {
                        return false;
                    }
                    if (this.fStatus && String(el.dataset.status) !== String(this.fStatus)) {
                        return false;
                    }
                    if (this.q.trim() !== '') {
                        const hay = String(el.dataset.search || '').toLowerCase();
                        if (!hay.includes(this.q.trim().toLowerCase())) {
                            return false;
                        }
                    }
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let gCount = 0;
                        let tCount = 0;
                        const rows = this.$el.querySelectorAll('tbody tr[data-search]');
                        rows.forEach(tr => {
                            if (this.matchGroup(tr)) {
                                gCount++;
                                tCount += parseInt(tr.dataset.targets || '1', 10);
                                const noCell = tr.querySelector('[data-col-no]');
                                if (noCell) noCell.textContent = gCount;
                            }
                        });
                        this.visibleGroups = gCount;
                        this.visibleTargets = tCount;
                    });
                },
                resetFilter() {
                    this.q = '';
                    this.fProcess = '';
                    this.fStatus = '';
                    this.recalc();
                }
            };
        }
    </script>
</x-app-layout>