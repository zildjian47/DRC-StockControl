@php
    $tyCls = 'ty-' . ($item->type === 'FG/KOMPONEN' ? 'FGK' : $item->type);
    $status = $info['status_stock'] ?? 'EMPTY STOCK';
    $pillCls = match ($status) {
        'EMPTY STOCK'  => 'pill-empty',
        'VERY LOW'     => 'pill-crit',
        'LOW STOCK'    => 'pill-warn',
        'NORMAL STOCK' => 'pill-good',
        'OVERSTOCK'    => 'pill-info',
        default        => 'pill-neu',
    };
    $barColor = match ($status) {
        'EMPTY STOCK'  => 'var(--ink)',
        'VERY LOW'     => 'var(--crit)',
        'LOW STOCK'    => 'var(--warn)',
        'NORMAL STOCK' => 'var(--good)',
        'OVERSTOCK'    => 'var(--accent)',
        default        => 'var(--mut)',
    };

    $curStock = (float) ($info['current_stock'] ?? 0);
    $s1       = (float) ($info['std_day_1'] ?? 0);
    $s2       = (float) ($info['std_day_2'] ?? 0);
    $s3       = (float) ($info['std_day_3'] ?? 0);

    if ($curStock <= 0) {
        $barWidth = 0;
    } elseif ($s1 <= 0 || $s3 <= 0) {
        $barWidth = 50;
    } elseif ($curStock <= $s3) {
        $barWidth = round(min(75, max(2, ($curStock / $s3) * 75)), 1);
    } else {
        $barWidth = round(min(90, 78 + (($curStock - $s3) / ($curStock + $s3)) * 12), 1);
    }

    $compType = $info['company_type'] ?? 'INTERNAL';
    $compPill = $compType === 'CUSTOMER' ? 'pill-good' : ($compType === 'INTERNAL' ? 'pill-warn' : 'pill-info');

    // Kelompokkan BOM Input & Output berdasarkan bom_group
    $groupedInputs = collect($info['inputs'] ?? [])->groupBy(fn ($r) => $r['bom_group'] ?? 'DEFAULT');
    $groupedOutputs = collect($info['outputs'] ?? [])
        ->groupBy(fn ($r) => $r['bom_group'] ?? 'DEFAULT')
        ->map(fn ($rows) => $rows->unique(fn ($r) => ($r['part_no'] ?? '') . '|' . ($r['part_name'] ?? ''))->values());

    $showPoBlock = $canViewPo && $poParams !== null && in_array($item->type, ['FG', 'FG/KOMPONEN', 'TRANSIT'], true);
@endphp

<x-scanner-layout :backUrl="route('items.index')">
    <x-slot name="header">Kartu Info Item</x-slot>

    {{-- BAR AKSI CEPAT ATAS --}}
    <div class="flex items-center gap-2">
        <a href="{{ route('item_cards.scanner') }}" class="btn btn-primary flex-1 justify-center">
            <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
                <path d="M14 14h3v3h-3zM20 14h1v3M14 20h7"/>
            </svg>
            <span>Scan Item Lain</span>
        </a>
        @if($canViewPo)
            <a href="{{ route('items.qr_print', ['item_id' => $item->id]) }}" target="_blank" class="btn flex-none">
                <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9V2h12v7"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                <span>Cetak QR</span>
            </a>
        @endif
    </div>

    {{-- 1. IDENTITAS ITEM & SALDO STOK --}}
    <section class="panel">
        <div class="panel-body space-y-3.5">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    @if(!empty($item->part_no))
                        <div class="num text-xs font-bold text-accent">{{ $item->part_no }}</div>
                    @endif
                    <h1 class="text-base font-bold text-slate-900 leading-snug break-words">{{ $item->part_name }}</h1>
                </div>
                <span class="pill {{ $pillCls }} flex-none">{{ $status }}</span>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <span class="ty {{ $tyCls }}">{{ $item->type }}</span>
                <span class="num text-xs font-semibold text-slate-700">{{ $item->uom }}</span>
                <span class="pill {{ $compPill }}">{{ $compType }}</span>
                <span class="text-xs font-semibold text-slate-600">{{ $info['company'] ?? 'INTERNAL' }}</span>
            </div>

            {{-- SALDO AKTUAL VS STOCK 07:00 --}}
            <div class="grid grid-cols-2 gap-2.5">
                <div class="p-3 rounded bg-slate-50 border border-slate-200">
                    <div class="text-[10.5px] font-bold uppercase tracking-wider text-slate-500">Stock Saat Ini</div>
                    <div class="num text-xl font-bold text-slate-900 mt-0.5 leading-tight">
                        {{ floatval($curStock) }}
                        <span class="text-xs font-semibold text-slate-500">{{ $item->uom }}</span>
                    </div>
                    <div class="text-[11px] font-semibold text-accent num mt-0.5">
                        Ratio: {{ floatval($info['ratio_stock'] ?? 0) }} Hari
                    </div>
                </div>

                <div class="p-3 rounded bg-slate-50 border border-slate-200">
                    <div class="text-[10.5px] font-bold uppercase tracking-wider text-slate-500">Stock 07:00</div>
                    <div class="num text-xl font-bold text-slate-800 mt-0.5 leading-tight">
                        {{ floatval($stock07) }}
                        <span class="text-xs font-semibold text-slate-500">{{ $item->uom }}</span>
                    </div>
                    <div class="text-[10.5px] text-slate-500 num mt-0.5 truncate" title="{{ $cutoffLabel }}">
                        {{ $cutoffLabel }}
                    </div>
                </div>
            </div>

            {{-- PROGRESS BAR STANDAR BUFFER --}}
            <div>
                <div class="sbar">
                    <i style="width:{{ $barWidth }}%; background:{{ $barColor }}"></i>
                    @if($s1 > 0)
                        <u style="left:25%" title="Std Day 1: {{ floatval($s1) }}"></u>
                        <u style="left:50%" title="Std Day 2: {{ floatval($s2) }}"></u>
                        <u style="left:75%" title="Std Day 3: {{ floatval($s3) }}"></u>
                    @endif
                </div>
                @if($s1 > 0)
                    <div class="sbar-lbl">
                        <span class="num" style="left:25%">{{ floatval($s1) }}</span>
                        <span class="num" style="left:50%">{{ floatval($s2) }}</span>
                        <span class="num" style="left:75%">{{ floatval($s3) }}</span>
                    </div>
                @endif
            </div>

            {{-- RINGKASAN BUFFER & WIP --}}
            <div class="grid grid-cols-4 gap-1.5 text-center pt-1">
                <div class="p-2 rounded bg-slate-50 border border-slate-200">
                    <div class="text-[10px] font-semibold uppercase text-slate-500">WIP</div>
                    <div class="num text-xs font-bold text-slate-900 mt-0.5">{{ floatval($info['wip_qty'] ?? 0) }}</div>
                </div>
                <div class="p-2 rounded bg-slate-50 border border-slate-200">
                    <div class="text-[10px] font-semibold uppercase text-slate-500">Std 1</div>
                    <div class="num text-xs font-bold text-slate-900 mt-0.5">{{ floatval($s1) }}</div>
                </div>
                <div class="p-2 rounded bg-slate-50 border border-slate-200">
                    <div class="text-[10px] font-semibold uppercase text-slate-500">Std 2</div>
                    <div class="num text-xs font-bold text-slate-900 mt-0.5">{{ floatval($s2) }}</div>
                </div>
                <div class="p-2 rounded bg-slate-50 border border-slate-200">
                    <div class="text-[10px] font-semibold uppercase text-slate-500">Std 3</div>
                    <div class="num text-xs font-bold text-slate-900 mt-0.5">{{ floatval($s3) }}</div>
                </div>
            </div>

            {{-- PARAMETER PO / WD / KANBAN (HANYA KHUSUS PPIC) --}}
            @if($showPoBlock)
                <div class="pt-2.5 border-t border-slate-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10.5px] font-bold uppercase tracking-wider text-slate-500">Parameter PO (Khusus PPIC)</span>
                        <span class="pill pill-info">PPIC</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="p-2 rounded bg-blue-50/60 border border-blue-200/70">
                            <div class="text-[10px] font-semibold uppercase text-slate-600">Qty PO</div>
                            <div class="num text-xs font-bold text-slate-900 mt-0.5">{{ $poParams['qty_po'] }}</div>
                        </div>
                        <div class="p-2 rounded bg-blue-50/60 border border-blue-200/70">
                            <div class="text-[10px] font-semibold uppercase text-slate-600">Working Days</div>
                            <div class="num text-xs font-bold text-slate-900 mt-0.5">{{ $poParams['working_days'] }} Hr</div>
                        </div>
                        <div class="p-2 rounded bg-blue-50/60 border border-blue-200/70">
                            <div class="text-[10px] font-semibold uppercase text-slate-600">Qty Kanban</div>
                            <div class="num text-xs font-bold text-slate-900 mt-0.5">{{ $poParams['qty_kanban'] }}</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- 2. LOT WIP YANG SEDANG ON PROGRESS --}}
    <section class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span>Lot WIP Aktif (ON PROGRESS)</span>
                <span class="count-chip"><span>{{ $activeLots->count() }}</span><span>LOT</span></span>
            </div>
        </div>
        <div class="panel-body space-y-2.5">
            @forelse($activeLots as $lot)
                <div class="p-3 rounded bg-slate-50 border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="num text-xs font-bold text-accent">{{ $lot['lot_number'] }}</span>
                            <span class="pill {{ $lot['role'] === 'OUTPUT' ? 'pill-good' : 'pill-warn' }}">
                                {{ $lot['role'] === 'OUTPUT' ? 'SEDANG DIPRODUKSI' : 'DIPAKAI DI MESIN' }}
                            </span>
                        </div>
                        <span class="text-[11px] font-semibold text-slate-600">{{ $lot['process_name'] }}</span>
                    </div>

                    <div class="text-xs text-slate-700 leading-snug">
                        @if($lot['role'] === 'OUTPUT')
                            Material: <b class="text-slate-900">{{ $lot['material_name'] }}</b>
                            <span class="num text-slate-500">(Sisa di mesin: {{ $lot['material_saat_ini'] }} {{ $lot['material_uom'] }})</span>
                        @else
                            Target Output: <b class="text-slate-900">{{ $lot['target_part_no'] !== '-' ? $lot['target_part_no'] . ' · ' : '' }}{{ $lot['target_name'] }}</b>
                        @endif
                    </div>

                    <div class="w-full h-1.5 rounded bg-slate-200 overflow-hidden">
                        <div class="h-full bg-emerald-600" style="width: {{ $lot['progress_pct'] }}%"></div>
                    </div>

                    <div class="grid grid-cols-3 gap-1.5 text-[11px] num">
                        <div>
                            <span class="text-slate-500 block">Estimasi</span>
                            <b class="text-slate-800">{{ $lot['target_qty_estimasi'] }} {{ $lot['target_uom'] }}</b>
                        </div>
                        <div>
                            <span class="text-slate-500 block">Act Prod / FG</span>
                            <b class="text-emerald-700">{{ $lot['actual_qty_prod'] }} / {{ $lot['act_fg'] }}</b>
                        </div>
                        <div class="text-right">
                            <span class="text-slate-500 block">Sisa WIP</span>
                            <b class="text-amber-700">{{ $lot['qty_wip'] }} {{ $lot['target_uom'] }}</b>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-xs text-slate-500 text-center py-2">
                    Tidak ada Lot ON PROGRESS yang terkait dengan item ini saat ini.
                </div>
            @endforelse
        </div>
    </section>

    {{-- 3. RELASI BOM (RINGKAS MOBILE DENGAN TAB) --}}
    <section class="panel" x-data="{ tab: {{ $groupedInputs->isNotEmpty() ? "'in'" : "'out'" }} }">
        <div class="panel-head">
            <div class="panel-title">Relasi BOM</div>
            <div class="flex items-center gap-1">
                <button type="button" @click="tab = 'in'"
                        class="btn btn-sm"
                        :class="tab === 'in' ? 'btn-primary' : ''">
                    Pembentuk ({{ count($info['inputs'] ?? []) }})
                </button>
                <button type="button" @click="tab = 'out'"
                        class="btn btn-sm"
                        :class="tab === 'out' ? 'btn-primary' : ''">
                    Dipakai Di ({{ $groupedOutputs->flatten(1)->count() }})
                </button>
            </div>
        </div>
        <div class="panel-body space-y-2.5">
            <div x-show="tab === 'in'" class="space-y-2.5">
                @forelse($groupedInputs as $bomGroup => $rows)
                    <div class="bom-grp-box">
                        <div class="bom-grp-head">
                            <span class="num text-[11px] font-bold text-slate-800">BOM: {{ $bomGroup }}</span>
                            <span class="pill pill-info">{{ $rows->first()['group_type'] ?? 'SINGLE' }}</span>
                        </div>
                        @foreach($rows as $inp)
                            @php
                                $inpTy = 'ty-' . (($inp['type'] ?? '') === 'FG/KOMPONEN' ? 'FGK' : ($inp['type'] ?? 'RM'));
                            @endphp
                            <div class="rel">
                                <div class="rel-t">
                                    <span class="text-slate-700">{{ $inp['process'] ?? '-' }}</span>
                                    <span class="pill {{ !empty($inp['is_trigger']) ? 'pill-good' : 'pill-neu' }}">
                                        {{ !empty($inp['is_trigger']) ? 'TRIGGER' : 'PENDAMPING' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="ty {{ $inpTy }}">{{ $inp['type'] ?? '' }}</span>
                                    @if(($inp['part_no'] ?? '-') !== '-')
                                        <span class="rel-part num">{{ $inp['part_no'] }}</span>
                                    @endif
                                </div>
                                <div class="font-medium text-slate-900 break-words">{{ $inp['part_name'] ?? '-' }}</div>
                                <div class="rel-m num">
                                    <span>Usage: {{ $inp['qty_usage'] }} {{ $inp['uom'] }}</span>
                                    <span>Stock: {{ $inp['current_stock'] }} {{ $inp['uom'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <div class="text-xs text-slate-500 text-center py-2">Tidak memiliki material pembentuk.</div>
                @endforelse
            </div>

            <div x-show="tab === 'out'" x-cloak class="space-y-2.5">
                @forelse($groupedOutputs as $bomGroup => $rows)
                    <div class="bom-grp-box">
                        <div class="bom-grp-head">
                            <span class="num text-[11px] font-bold text-slate-800">BOM: {{ $bomGroup }}</span>
                            <span class="pill pill-info">{{ $rows->first()['group_type'] ?? 'SINGLE' }}</span>
                        </div>
                        @foreach($rows as $out)
                            @php
                                $outTy = 'ty-' . (($out['type'] ?? '') === 'FG/KOMPONEN' ? 'FGK' : ($out['type'] ?? 'FG'));
                            @endphp
                            <div class="rel">
                                <div class="rel-t">
                                    <span class="text-slate-700">{{ $out['process'] ?? '-' }}</span>
                                    <span class="pill {{ !empty($out['is_trigger']) ? 'pill-good' : 'pill-neu' }}">
                                        {{ !empty($out['is_trigger']) ? 'TRIGGER' : 'PENDAMPING' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="ty {{ $outTy }}">{{ $out['type'] ?? '' }}</span>
                                    @if(($out['part_no'] ?? '-') !== '-')
                                        <span class="rel-part num">{{ $out['part_no'] }}</span>
                                    @endif
                                </div>
                                <div class="font-medium text-slate-900 break-words">{{ $out['part_name'] ?? '-' }}</div>
                                <div class="rel-m num">
                                    <span>Usage: {{ $out['qty_usage'] }} {{ $item->uom }}</span>
                                    <span>Stock: {{ $out['current_stock'] }} {{ $out['uom'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <div class="text-xs text-slate-500 text-center py-2">Tidak dipakai pada item lain.</div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- 4. RIWAYAT MUTASI STOCK & AUDIT TRAIL --}}
    <section class="panel" x-data="itemCardHistory({{ Js::from($historyList->values()) }})">
        <div class="panel-head">
            <div class="panel-title">
                <span>Riwayat Mutasi Stock</span>
                <span class="count-chip"><span x-text="filtered.length"></span><span>DATA</span></span>
            </div>
        </div>
        <div class="panel-body space-y-2.5">
            <div class="flex items-center gap-2">
                <input type="text" x-model="q" @input="limit = 20"
                       placeholder="Cari transaksi, lot, petugas..."
                       class="h-8 text-xs px-2.5 flex-1">
                <button type="button" x-show="q" x-cloak @click="q = ''; limit = 20" class="btn btn-sm">Reset</button>
            </div>

            <div class="tl-box">
                <template x-for="g in visibleGroups" :key="g.date">
                    <div>
                        <div class="tl-day">
                            <span style="font-weight: 600; letter-spacing: .08em; text-transform: uppercase" x-text="g.label"></span>
                            <span class="count-chip count-chip-sub" x-text="g.rows.length + ' DATA'"></span>
                        </div>
                        <template x-for="(h, idx) in g.rows" :key="g.date + '-' + idx">
                            <div class="tl-item" :class="h.qty_change >= 0 ? 'tl-in' : 'tl-out'">
                                <div class="tl-time" x-text="h.time"></div>
                                <div class="tl-dot"><i></i></div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-semibold text-xs" style="color: var(--ink)" x-text="h.type_label || '-'"></span>
                                        <span class="pill pill-info" x-show="h.status === 'DIKOREKSI'">DIKOREKSI</span>
                                    </div>
                                    <div class="hint break-words" style="margin-top: 0" x-text="h.reference || '-'"></div>
                                    <div class="hint" style="margin-top: 0" x-text="cleanLabel(h.user_name || '-')"></div>
                                    <div x-show="h.status === 'DIKOREKSI' && (h.qty_original !== null || h.corrected_by || h.correction_notes)"
                                         x-cloak
                                         class="mt-1 px-2 py-1 rounded bg-slate-50 border border-slate-200 text-[11px] text-slate-600 space-y-0.5">
                                        <div class="flex flex-wrap items-center gap-x-2">
                                            <span x-show="h.qty_original !== null">Qty Awal: <b class="num text-slate-800" x-text="h.qty_original + ' ' + h.uom"></b></span>
                                            <span x-show="h.corrected_by">Koreksi: <b class="text-slate-800" x-text="cleanLabel(h.corrected_by)"></b></span>
                                        </div>
                                        <div x-show="h.correction_notes" class="break-words" x-text="'Alasan: ' + h.correction_notes"></div>
                                    </div>
                                </div>
                                <div class="tl-qty num">
                                    <span x-text="(h.qty_change >= 0 ? '+' : '') + h.qty_change"></span>
                                    <small x-text="h.uom"></small>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <div x-show="filtered.length === 0" class="empty" style="padding: 20px 12px">
                    Belum ada riwayat mutasi transaksi.
                </div>
            </div>

            <div x-show="filtered.length > limit" x-cloak class="text-center pt-1">
                <button type="button" @click="limit += 30" class="btn btn-sm">
                    Muat lebih banyak (<span x-text="filtered.length - limit"></span> tersisa)
                </button>
            </div>
        </div>
    </section>

    <script>
        function itemCardHistory(rows) {
            const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return {
                rows: Array.isArray(rows) ? rows : [],
                q: '',
                limit: 20,

                cleanLabel(str) {
                    return String(str || '').replace(/\s*\([^)]*\)/g, '').trim();
                },
                dayLabel(d) {
                    const p = String(d).split('-');
                    return p.length === 3 ? (parseInt(p[2], 10) + ' ' + MON[parseInt(p[1], 10) - 1] + ' ' + p[0]) : d;
                },
                get filtered() {
                    const kw = this.q.trim().toLowerCase();
                    if (!kw) return this.rows;
                    return this.rows.filter(h => [
                        h.type_label, h.reference, h.user_name, h.status, h.corrected_by, h.correction_notes
                    ].some(v => String(v || '').toLowerCase().includes(kw)));
                },
                get visibleGroups() {
                    const slice = this.filtered.slice(0, this.limit);
                    const out = [];
                    const idx = {};
                    slice.forEach(h => {
                        if (!(h.date in idx)) {
                            idx[h.date] = out.length;
                            out.push({ date: h.date, label: this.dayLabel(h.date), rows: [] });
                        }
                        out[idx[h.date]].rows.push(h);
                    });
                    return out;
                }
            };
        }
    </script>
</x-scanner-layout>