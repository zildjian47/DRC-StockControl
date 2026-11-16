<x-app-layout>
    @php
        $today = \Carbon\Carbon::today();
        $outputToday = \App\Models\Production::whereDate('date_time', $today)->sum('qty');
        $totalStock = \App\Models\InventoryStock::whereHas('item', fn($q) => $q->whereNull('deleted_at'))->sum('current_stock');
        $inboundToday = \App\Models\Inbound::whereDate('date_time', $today)->count();
        $pendingInbound = \App\Models\Inbound::where('qty_check', 'PENDING')->count();

        $totalProdAll = \App\Models\Production::sum('qty');
        $totalNgAll = \App\Models\ProductionNg::sum('qty_ng');
        $ngRate = $totalProdAll > 0 ? round(($totalNgAll / $totalProdAll) * 100, 2) : 0;

        $delayCount = \App\Models\ProblemDelay::whereDate('date_time', $today)->count();
        $activeLots = \App\Models\LotTracer::with(['process', 'materialItem', 'targetItem'])
            ->where('status_lot', 'ON PROGRESS')
            ->orderBy('updated_at', 'desc')
            ->take(8)
            ->get();

        $outByDay = \App\Models\Production::selectRaw('DATE(date_time) as d, SUM(qty) as q')
            ->whereDate('date_time', '>=', $today->copy()->subDays(6))
            ->groupBy('d')
            ->pluck('q', 'd');

        $runningLots = \App\Models\LotTracer::where('status_lot', 'ON PROGRESS')->count();
        $lowStockItems = \App\Models\InventoryStock::whereHas('item', fn($q) => $q->whereNull('deleted_at'))
            ->whereIn('status_stock', ['EMPTY STOCK', 'VERY LOW', 'LOW STOCK'])
            ->count();
        $veryLowItems = \App\Models\InventoryStock::whereHas('item', fn($q) => $q->whereNull('deleted_at'))
            ->whereIn('status_stock', ['EMPTY STOCK', 'VERY LOW'])
            ->count();

        // =========================================================================
        // DATA GRAFIK 1: PEMANTAUAN STOK LINTAS TAHAP (RM, KOMPONEN, WIP LOT, FG)
        // =========================================================================
        $allActiveItems = \App\Models\Item::with([
            'company',
            'inventoryStock',
            'bomsAsInput.outputItem.inventoryStock',
            'bomsAsInput.process',
            'bomsAsOutput.inputItem.inventoryStock',
            'bomsAsOutput.process',
        ])->orderByRaw("CASE type
            WHEN 'FG' THEN 1
            WHEN 'FG/KOMPONEN' THEN 2
            WHEN 'KOMPONEN' THEN 3
            WHEN 'RM' THEN 4
            WHEN 'TRANSIT' THEN 5
            ELSE 6 END")
          ->orderBy('part_name')
          ->get();

        $allRunningLots = \App\Models\LotTracer::where('status_lot', 'ON PROGRESS')->get();

        // Hitung WIP Target & Sisa Material di Mesin (Trigger + Pendamping) dari Lot ON PROGRESS
        $wipByTargetItem = [];
        $actFgByTargetItem = [];
        $matInMachineByItem = [];

        foreach ($allRunningLots->groupBy('lot_number') as $lotNo => $lotRows) {
            $firstLot = $lotRows->first();
            if ($firstLot) {
                $remTrigger = (float) $firstLot->material_saat_ini;
                if ($remTrigger > 0) {
                    $mId = (int) $firstLot->material_item_id;
                    $matInMachineByItem[$mId] = round(($matInMachineByItem[$mId] ?? 0) + $remTrigger, 2);
                }
                foreach ($firstLot->companion_items as $comp) {
                    $cId = (int) ($comp['input_item_id'] ?? 0);
                    $cRem = (float) ($comp['qty_saat_ini'] ?? 0);
                    if ($cId > 0 && $cRem > 0) {
                        $matInMachineByItem[$cId] = round(($matInMachineByItem[$cId] ?? 0) + $cRem, 2);
                    }
                }
            }
            foreach ($lotRows as $lr) {
                $tId = (int) $lr->target_item_id;
                $wipByTargetItem[$tId] = round(($wipByTargetItem[$tId] ?? 0) + (float) $lr->qty_wip, 2);
                $actFgByTargetItem[$tId] = round(($actFgByTargetItem[$tId] ?? 0) + (float) $lr->act_fg, 2);
            }
        }

        // Peta Rantai BOM (Untuk Filter Fokus Rantai Produk dari FG -> Komponen -> RM)
        $allBomsFlat = \App\Models\Bom::all();
        $bomChainMap = [];
        $fgProductsFilter = [];

        foreach ($allActiveItems as $itm) {
            if (in_array($itm->type, ['FG', 'FG/KOMPONEN'], true)) {
                $chainIds = [$itm->id];
                $queue = [$itm->id];
                while (!empty($queue)) {
                    $currOutId = array_shift($queue);
                    $inIds = $allBomsFlat->where('output_item_id', $currOutId)->pluck('input_item_id')->unique();
                    foreach ($inIds as $inId) {
                        if (!in_array((int) $inId, $chainIds, true)) {
                            $chainIds[] = (int) $inId;
                            $queue[] = (int) $inId;
                        }
                    }
                }
                $bomChainMap[$itm->id] = $chainIds;
                $fgProductsFilter[] = [
                    'id'    => $itm->id,
                    'label' => ($itm->part_no ? $itm->part_no . ' — ' : '') . $itm->part_name . ' [' . $itm->type . ']',
                ];
            }
        }

        $stockChartItems = $allActiveItems->map(function ($itm) use ($wipByTargetItem, $actFgByTargetItem, $matInMachineByItem) {
            $st = $itm->inventoryStock;
            $currStock = $st ? (float) $st->current_stock : 0.0;
            $std1 = (float) $itm->calculated_std_day_1;
            $std2 = (float) $itm->std_day_2;
            $std3 = (float) $itm->std_day_3;
            $ratio = $std1 > 0 ? round($currStock / $std1, 2) : 0.0;
            $status = $st ? $st->calculateDynamicStatus() : 'EMPTY STOCK';

            $wipTarget = (float) ($wipByTargetItem[$itm->id] ?? 0);
            $actFgLot  = (float) ($actFgByTargetItem[$itm->id] ?? 0);
            $matInMach = (float) ($matInMachineByItem[$itm->id] ?? 0);

            // Total yang sedang berada di Lot Tracer (WIP Target + Sisa Material di Mesin)
            $lotActiveQty = round($wipTarget + $matInMach, 2);
            $totalCombined = round($currStock + $lotActiveQty, 2);

            $usedByNames = $itm->bomsAsInput->map(fn ($b) => $b->outputItem?->part_no ?: $b->outputItem?->part_name)->filter()->unique()->values()->all();

            return [
                'id'             => $itm->id,
                'part_no'        => $itm->part_no ?: '-',
                'part_name'      => $itm->part_name,
                'type'           => $itm->type,
                'uom'            => $itm->uom,
                'company_code'   => $itm->company->company_code ?? 'INTERNAL',
                'company_type'   => $itm->company->type_company ?? 'INTERNAL',
                'current_stock'  => $currStock,
                'wip_target'     => $wipTarget,
                'act_fg_lot'     => $actFgLot,
                'mat_in_machine' => $matInMach,
                'lot_active_qty' => $lotActiveQty,
                'total_combined' => $totalCombined,
                'std_day_1'      => $std1,
                'std_day_2'      => $std2,
                'std_day_3'      => $std3,
                'ratio_stock'    => $ratio,
                'status_stock'   => $status,
                'used_by_count'  => count($usedByNames),
                'used_by_list'   => implode(', ', $usedByNames),
                'info_payload'   => $itm->material_info_payload,
            ];
        })->values();

        // =========================================================================
        // DATA GRAFIK 2 & 3: DELAY DELIVERY (PER CUSTOMER & PER PROBLEM BULANAN)
        // =========================================================================
        $monthNamesId = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $allDelays = \App\Models\ProblemDelay::with(['company', 'item.company'])
            ->orderByDesc('incident_date')
            ->orderByDesc('date_time')
            ->get()
            ->map(function ($d) use ($monthNamesId) {
                $dt = $d->incident_date ? \Carbon\Carbon::parse($d->incident_date) : \Carbon\Carbon::parse($d->date_time);
                $mNum = (int) $dt->format('n');
                $yr   = (int) $dt->format('Y');
                return [
                    'id'             => $d->id,
                    'year'           => (string) $yr,
                    'month_num'      => $mNum,
                    'month_key'      => $dt->format('Y-m'),
                    'month_label'    => ($monthNamesId[$mNum] ?? $dt->format('M')) . ' ' . $yr,
                    'incident_fmt'   => $dt->format('d M Y'),
                    'company_id'     => $d->company_id,
                    'company_code'   => $d->company->company_code ?? '-',
                    'company_name'   => $d->company->company_name ?? '-',
                    'company_type'   => $d->company->type_company ?? 'CUSTOMER',
                    'item_id'        => $d->item_id,
                    'part_no'        => $d->item->part_no ?: '-',
                    'part_name'      => $d->item->part_name ?? '-',
                    'item_type'      => $d->item->type ?? 'FG',
                    'problem_reason' => $d->problem_reason,
                ];
            })->values();

        $delayYears = $allDelays->pluck('year')->unique()->sortDesc()->values()->all();
        if (empty($delayYears)) {
            $delayYears = [(string) date('Y')];
        }
        $delayReasonsList = \App\Http\Controllers\ProblemDelayController::REASONS;
        $allCompaniesList = \App\Models\Company::orderBy('type_company')->orderBy('company_code')->get()->map(fn($c) => [
            'id'   => $c->id,
            'code' => $c->company_code,
            'name' => $c->company_name,
            'type' => $c->type_company,
        ])->values();
    @endphp

    <x-slot name="header">
        <x-page-header title="Dashboard Produksi"
                       subtitle="{{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }} · Monitoring real-time" />
    </x-slot>

    {{-- 1. KPI UTAMA --}}
    <div class="kpis">
        <div class="kpi">
            <div class="kpi-l">Output Hari Ini</div>
            <div class="kpi-v">{{ number_format($outputToday, 0, ',', '.') }}<small>unit</small></div>
            <div class="kpi-s">Hasil scan produksi hari ini</div>
        </div>
        <div class="kpi">
            <div class="kpi-l">Stok Tersedia</div>
            <div class="kpi-v">{{ number_format($totalStock, 0, ',', '.') }}</div>
            <div class="kpi-s">Total saldo seluruh part aktif</div>
        </div>
        <div class="kpi">
            <div class="kpi-l">Inbound Hari Ini</div>
            <div class="kpi-v">{{ $inboundToday }}<small>dokumen</small></div>
            <div class="kpi-s">
                <span class="pill pill-good">Tercatat di sistem</span>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-l">NG Rate</div>
            <div class="kpi-v">{{ $ngRate }}<small>%</small></div>
            <div class="kpi-s">
                <span class="pill {{ $ngRate <= 2 ? 'pill-good' : 'pill-crit' }}">Total NG: {{ floatval($totalNgAll) }}</span>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-l">Delay Delivery</div>
            <div class="kpi-v">{{ $delayCount }}<small>log hari ini</small></div>
            <div class="kpi-s">
                <span class="pill {{ $delayCount > 0 ? 'pill-crit' : 'pill-good' }}">
                    {{ $delayCount > 0 ? 'Perlu tindak lanjut' : 'Tidak ada kendala' }}
                </span>
            </div>
        </div>
    </div>

    {{-- 2. OUTPUT 7 HARI TERAKHIR & PERLU TINDAKAN --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-[18px] items-stretch">
        <section class="panel xl:col-span-2 flex flex-col">
            <div class="panel-head">
                <h3 class="panel-title">Output produksi, 7 hari terakhir</h3>
            </div>
            <div class="panel-body flex-1 flex flex-col justify-between">
                @php
                    $series = collect(range(6, 0))->map(function ($i) use ($outByDay) {
                        $d = \Carbon\Carbon::today()->subDays($i);
                        return ['label' => $d->translatedFormat('D d'), 'v' => (float) ($outByDay[$d->toDateString()] ?? 0)];
                    });
                    $max = max(1, $series->max('v'));
                    $W = 560; $H = 225; $L = 46; $R = 12; $T = 16; $B = 28; $step = ($W - $L - $R) / 7; $bw = 36;
                @endphp
                <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full h-auto min-h-[260px]" role="img" aria-label="Output produksi 7 hari terakhir">
                    @foreach ([0, 0.5, 1] as $g)
                        @php $y = $T + ($H - $T - $B) * (1 - $g); @endphp
                        <line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $y }}" y2="{{ $y }}" stroke="var(--line)" stroke-width="1"/>
                        <text x="{{ $L - 8 }}" y="{{ $y + 4 }}" text-anchor="end" fill="var(--mut)" font-size="11" font-family="Inter, sans-serif">{{ number_format($max * $g, 0, ',', '.') }}</text>
                    @endforeach
                    @foreach ($series as $i => $s)
                        @php
                            $h = $s['v'] > 0 ? (($H - $T - $B) * $s['v'] / $max) : 0;
                            $x = $L + $step * $i + ($step - $bw) / 2;
                        @endphp
                        @if($s['v'] > 0 && $h > 0)
                            <rect x="{{ $x }}" y="{{ $H - $B - $h }}" width="{{ $bw }}" height="{{ $h }}" rx="2" fill="var(--c1)" @if($i === 6) opacity=".85" @endif>
                                <title>{{ $s['label'] }}: {{ number_format($s['v'], 0, ',', '.') }}</title>
                            </rect>
                        @endif
                        <text x="{{ $x + $bw / 2 }}" y="{{ $H - 8 }}" text-anchor="middle" fill="var(--mut)" font-size="11" font-family="Inter, sans-serif">{{ $s['label'] }}</text>
                    @endforeach
                </svg>
                <p class="hint mt-2">Batang paling kanan adalah akumulasi output hari ini dan masih berjalan.</p>
            </div>
        </section>

        <section class="panel flex flex-col">
            <div class="panel-head">
                <h3 class="panel-title">Perlu tindakan</h3>
            </div>
            <div class="panel-body flex-1 flex flex-col justify-between gap-4">
                <div class="divide-y divide-slate-200 border-b border-slate-200 text-[13px]">
                    <div class="flex items-center justify-between py-3.5 first:pt-1">
                        <div>
                            <div class="font-semibold text-slate-900">Stok Kritis (Empty / Very Low)</div>
                            <div class="text-xs text-slate-500 mt-0.5">Item dengan ketahanan stok &lt; 1 hari</div>
                        </div>
                        <span class="pill {{ $veryLowItems > 0 ? 'pill-crit' : 'pill-good' }} num">{{ $veryLowItems }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3.5">
                        <div>
                            <div class="font-semibold text-slate-900">Total Item Stok Rendah</div>
                            <div class="text-xs text-slate-500 mt-0.5">Termasuk status Low Stock (&lt; Std Day 2)</div>
                        </div>
                        <span class="pill {{ $lowStockItems > 0 ? 'pill-warn' : 'pill-good' }} num">{{ $lowStockItems }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3.5">
                        <div>
                            <div class="font-semibold text-slate-900">Log Delay Delivery Hari Ini</div>
                            <div class="text-xs text-slate-500 mt-0.5">Kendala pengiriman tercatat hari ini</div>
                        </div>
                        <span class="pill {{ $delayCount ? 'pill-crit' : 'pill-good' }} num">{{ $delayCount }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3.5">
                        <div>
                            <div class="font-semibold text-slate-900">Lot Sedang Berjalan</div>
                            <div class="text-xs text-slate-500 mt-0.5">Status pemantauan WIP ON PROGRESS</div>
                        </div>
                        <span class="pill pill-info num">{{ $runningLots }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <a href="{{ route('inventory_stocks.index') }}" class="btn btn-sm flex-1">Cek Inventory Stock</a>
                    <a href="{{ route('problem_delays.index') }}" class="btn btn-sm flex-1">Buka Delay Delivery</a>
                </div>
            </div>
        </section>
    </div>

    {{-- 3. LOT SEDANG BERJALAN (LIVE WIP) --}}
    <section class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span>Lot Sedang Berjalan (Live WIP)</span>
                <span class="count-chip"><span>{{ $activeLots->count() }}</span><span>Lot</span></span>
            </div>
            <a href="{{ route('lot_tracers.index') }}" class="btn btn-sm btn-primary">
                <x-icon name="route" /> <span>Buka Lot Tracer</span>
            </a>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[12%]">Lot</th>
                        <th class="w-[30%]">Produk (Target)</th>
                        <th class="w-[18%]">Proses</th>
                        <th class="w-[18%]">Progres Produksi</th>
                        <th class="num w-[12%]">Act FG / WIP</th>
                        <th class="w-[10%] text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeLots as $lot)
                        @php
                            $pct = $lot->target_qty_estimasi > 0
                                ? min(100, round(($lot->actual_qty_prod / $lot->target_qty_estimasi) * 100))
                                : 0;
                        @endphp
                        <tr>
                            <td class="num font-semibold text-accent">{{ $lot->lot_number }}</td>
                            <td class="font-medium text-slate-900">{{ $lot->targetItem->part_name ?? '-' }}</td>
                            <td class="text-slate-600 text-xs">{{ $lot->process->process_name ?? '-' }}</td>
                            <td>
                                <div class="inline-flex items-center gap-2.5" style="width: 164px;">
                                    <span class="track"><i style="width:{{ $pct }}%"></i></span>
                                    <span class="num text-xs font-semibold text-slate-700" style="width: 34px; text-align: right; flex: 0 0 34px;">{{ $pct }}%</span>
                                </div>
                            </td>
                            <td class="num text-xs whitespace-nowrap">
                                <b class="text-emerald-700">{{ floatval($lot->act_fg) }}</b> / <b class="text-amber-700">WIP {{ floatval($lot->qty_wip) }}</b>
                            </td>
                            <td class="text-center">
                                @if($lot->status_lot === 'ON PROGRESS')
                                    <span class="pill pill-good">ON PROGRESS</span>
                                @else
                                    <span class="pill pill-neu">FINISH</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">Belum ada Lot yang berjalan.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ======================================================================= --}}
    {{-- 4. GRAFIK BARU 1: PEMANTAUAN STOK MENYELURUH (RM, KOMPONEN, WIP, FG)   --}}
    {{-- ======================================================================= --}}
    <section class="panel"
             x-data="dashboardStockChart({{ Js::from($stockChartItems) }}, {{ Js::from($bomChainMap) }})">
        <div class="panel-head">
            <div>
                <div class="panel-title">
                    <span>Grafik Ketersediaan Stok Lintas Tahap (RM · Komponen · WIP Lot Tracer · FG)</span>
                    <span class="count-chip"><span x-text="filteredItems.length"></span><span>Item</span></span>
                </div>
                <p class="hint" style="margin-top: 2px">
                    Memantau posisi stok fisik di gudang (<b class="text-accent">Current Stock</b>) ditambah barang yang masih diproses di lantai produksi (<b class="text-amber-700">WIP / Material di Lot Tracer</b>) terhadap target buffer harian (<b class="text-slate-700">Min Std 2 &amp; Max Std 3</b>).
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q"
                       placeholder="Cari Part No / Nama Item..."
                       class="h-8 text-xs px-2.5 w-44">

                <select x-model="fChainFg" class="h-8 text-xs py-0 pl-2 pr-7" title="Filter Rantai BOM Produk">
                    <option value="">Semua Rantai Produk (BOM)</option>
                    @foreach($fgProductsFilter as $fgOpt)
                        <option value="{{ $fgOpt['id'] }}">Rantai BOM: {{ $fgOpt['label'] }}</option>
                    @endforeach
                </select>

                <select x-model="fType" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe Item</option>
                    <option value="FG">FG (Finish Good)</option>
                    <option value="FG/KOMPONEN">FG/KOMPONEN</option>
                    <option value="KOMPONEN">KOMPONEN</option>
                    <option value="RM">RM (Raw Material)</option>
                    <option value="TRANSIT">TRANSIT</option>
                </select>

                <select x-model="fStatus" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Status Stok</option>
                    <option value="KRITIS">KRITIS (Empty &amp; Very Low)</option>
                    <option value="EMPTY STOCK">EMPTY STOCK</option>
                    <option value="VERY LOW">VERY LOW</option>
                    <option value="LOW STOCK">LOW STOCK</option>
                    <option value="NORMAL STOCK">NORMAL STOCK</option>
                    <option value="OVERSTOCK">OVERSTOCK</option>
                </select>

                <button type="button"
                        x-show="q || fChainFg || fType || fStatus" x-cloak
                        @click="q=''; fChainFg=''; fType=''; fStatus=''"
                        class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="panel-body space-y-3">
            {{-- Legenda Grafik --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-3 py-2 rounded border border-slate-200 bg-slate-50 text-xs">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="inline-flex items-center gap-1.5 font-semibold text-slate-700">
                        <i class="inline-block w-3 h-3 rounded-sm" style="background: var(--accent)"></i>
                        Stok Gudang (Current Stock)
                    </span>
                    <span class="inline-flex items-center gap-1.5 font-semibold text-slate-700">
                        <i class="inline-block w-3 h-3 rounded-sm bg-amber-500"></i>
                        WIP Target / Sisa Material Aktif di Lot Tracer
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-slate-600">
                        <i class="inline-block w-0.5 h-3.5 bg-amber-700"></i>
                        Garis Min (Std Day 2)
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-slate-600">
                        <i class="inline-block w-0.5 h-3.5 bg-emerald-700"></i>
                        Garis Max (Std Day 3)
                    </span>
                </div>
                <div class="text-[11px] text-slate-500">
                    Klik ikon dokumen di sebelah kanan baris untuk membuka Alur BOM &amp; Riwayat Mutasi.
                </div>
            </div>

            {{-- Daftar Batang Grafik Stok --}}
            <div class="divide-y divide-slate-200 border border-slate-200 rounded max-h-[460px] overflow-y-auto overflow-x-hidden">
                <template x-for="it in filteredItems" :key="it.id">
                    <div class="p-3 hover:bg-slate-50/80 transition-colors grid grid-cols-1 lg:grid-cols-12 gap-3 items-center">
                        {{-- Identitas Item (Kiri) --}}
                        <div class="lg:col-span-4 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="ty" :class="tyClass(it.type)" x-text="it.type"></span>
                                <span class="num text-xs font-bold text-accent" x-text="it.part_no !== '-' ? it.part_no : ''"></span>
                                <span class="pill pill-neu text-[10px] h-4 px-1.5" x-text="it.company_code"></span>
                                <template x-if="it.used_by_count > 1">
                                    <span class="pill pill-info text-[10px] h-4 px-1.5"
                                          :title="'Dipakai bersama oleh: ' + it.used_by_list"
                                          x-text="'Shared: ' + it.used_by_count + ' Part'"></span>
                                </template>
                            </div>
                            <div class="font-semibold text-slate-900 text-xs mt-1 truncate" :title="it.part_name" x-text="it.part_name"></div>
                            <div class="text-[11px] text-slate-500 truncate" x-show="it.used_by_count > 0" x-text="'Dipakai di: ' + it.used_by_list"></div>
                        </div>

                        {{-- Visual Bar Bertumpuk (Tengah) --}}
                        <div class="lg:col-span-5 min-w-0">
                            <div class="relative h-5 rounded bg-slate-200/80 overflow-hidden flex items-stretch border border-slate-300/80">
                                {{-- Batang Current Stock Gudang --}}
                                <div class="h-full transition-all duration-200"
                                     :style="'width:' + stockPct(it) + '%; background: var(--accent);'"
                                     :title="'Stok Gudang: ' + it.current_stock + ' ' + it.uom"></div>
                                {{-- Batang WIP / Material di Lot Tracer --}}
                                <div class="h-full bg-amber-500 transition-all duration-200"
                                     x-show="it.lot_active_qty > 0"
                                     :style="'width:' + wipPct(it) + '%'"
                                     :title="'Aktif di Lot Tracer: ' + it.lot_active_qty + ' ' + it.uom"></div>

                                {{-- Penanda Garis Std Day 2 (Min) & Std Day 3 (Max) --}}
                                <template x-if="it.std_day_2 > 0">
                                    <div class="absolute top-0 bottom-0 w-[2px] bg-amber-700 z-10"
                                         :style="'left:' + markerPct(it, it.std_day_2) + '%'"
                                         :title="'Batas Min (Std Day 2): ' + it.std_day_2 + ' ' + it.uom"></div>
                                </template>
                                <template x-if="it.std_day_3 > 0">
                                    <div class="absolute top-0 bottom-0 w-[2px] bg-emerald-700 z-10"
                                         :style="'left:' + markerPct(it, it.std_day_3) + '%'"
                                         :title="'Batas Max (Std Day 3): ' + it.std_day_3 + ' ' + it.uom"></div>
                                </template>
                            </div>

                            <div class="flex items-center justify-between text-[11px] text-slate-500 mt-1 num">
                                <span x-text="'Std 1/2/3: ' + it.std_day_1 + ' / ' + it.std_day_2 + ' / ' + it.std_day_3 + ' ' + it.uom"></span>
                                <span class="font-semibold text-accent" x-text="'Ratio Gudang: ' + it.ratio_stock + ' Hari'"></span>
                            </div>
                        </div>

                        {{-- Angka Rincian & Status (Kanan) --}}
                        <div class="lg:col-span-3 flex items-center justify-between lg:justify-end gap-2.5">
                            <div class="text-right num leading-tight">
                                <div class="text-xs font-bold text-slate-900">
                                    <span x-text="'Gudang: ' + it.current_stock"></span>
                                    <span class="text-[11px] font-normal text-slate-500" x-text="it.uom"></span>
                                </div>
                                <div class="text-[11px] font-semibold text-amber-700 mt-0.5" x-show="it.wip_target > 0">
                                    <span x-text="'+ WIP Lot: ' + it.wip_target + ' ' + it.uom"></span>
                                </div>
                                <div class="text-[11px] font-semibold text-amber-700 mt-0.5" x-show="it.mat_in_machine > 0">
                                    <span x-text="'+ Di Mesin: ' + it.mat_in_machine + ' ' + it.uom"></span>
                                </div>
                                <div class="text-[11px] text-slate-400 mt-0.5" x-show="it.lot_active_qty === 0">
                                    WIP Lot: 0
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 flex-none">
                                <span class="pill" :class="pillClass(it.status_stock)" x-text="it.status_stock"></span>
                                <button type="button" class="act-btn" title="Informasi Material & Alur BOM"
                                        @click="$dispatch('open-material-info', it.info_payload)">
                                    <x-icon name="info" />
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <div x-show="filteredItems.length === 0" class="empty">
                    Tidak ada item yang sesuai dengan filter pemantauan stok.
                </div>
            </div>
        </div>
    </section>

    {{-- ======================================================================= --}}
    {{-- 5. GRAFIK BARU 2 & 3: ANALITIK DELAY DELIVERY (CUSTOMER & PROBLEM)     --}}
    {{-- ======================================================================= --}}
    <div x-data="dashboardDelayAnalytics({{ Js::from($allDelays) }}, {{ Js::from($allCompaniesList) }}, {{ Js::from($delayReasonsList) }}, '{{ $delayYears[0] ?? date('Y') }}')"
         class="space-y-[14px]">

        {{-- GRAFIK 2: HISTORY DELAY PER PERUSAHAAN / CUSTOMER (BULANAN) --}}
        <section class="panel">
            <div class="panel-head">
                <div>
                    <div class="panel-title">
                        <span>Grafik History Delay Delivery per Perusahaan / Customer (Bulanan)</span>
                        <span class="count-chip"><span x-text="totalCustomerDelays"></span><span>Kasus</span></span>
                    </div>
                    <p class="hint" style="margin-top: 2px">
                        Setiap kolom mewakili 1 perusahaan/pelanggan beserta rincian frekuensi item yang bermasalah pada setiap bulan berdasarkan Tanggal Kejadian.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="seg" role="radiogroup" aria-label="Filter Tipe Perusahaan Delay">
                        <label><input type="radio" value="CUSTOMER" x-model="compTypeFilter"><span>Customer</span></label>
                        <label><input type="radio" value="INTERNAL" x-model="compTypeFilter"><span>Internal (FG/Komponen)</span></label>
                        <label><input type="radio" value="SUPPLIER" x-model="compTypeFilter"><span>Supplier (Transit/RM)</span></label>
                        <label><input type="radio" value="ALL" x-model="compTypeFilter"><span>Semua</span></label>
                    </div>

                    <select x-model="selectedYear" class="h-8 text-xs py-0 pl-2 pr-7">
                        <option value="">Semua Tahun</option>
                        @foreach($delayYears as $yr)
                            <option value="{{ $yr }}">Tahun {{ $yr }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="panel-body">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <template x-for="col in customerColumns" :key="col.company_id">
                        <div class="border border-slate-200 rounded bg-slate-50/50 flex flex-col overflow-hidden">
                            {{-- Header Kolom Perusahaan --}}
                            <div class="px-3.5 py-2.5 bg-white border-b border-slate-200 flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="num font-bold text-accent text-xs" x-text="col.company_code"></span>
                                        <span class="pill text-[10px] h-4 px-1.5"
                                              :class="col.company_type === 'CUSTOMER' ? 'pill-good' : (col.company_type === 'INTERNAL' ? 'pill-warn' : 'pill-info')"
                                              x-text="col.company_type"></span>
                                    </div>
                                    <div class="font-semibold text-slate-900 text-xs truncate mt-0.5" x-text="col.company_name"></div>
                                </div>
                                <span class="count-chip" x-text="col.total_cases + 'x Delay'"></span>
                            </div>

                            {{-- Isi Grafik Per Bulan di Dalam Kolom Perusahaan --}}
                            <div class="p-3 space-y-3 flex-1 max-h-80 overflow-y-auto">
                                <template x-for="m in col.months" :key="m.month_key">
                                    <div class="p-2.5 rounded bg-white border border-slate-200 space-y-2">
                                        <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                                            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider" x-text="m.month_label"></span>
                                            <span class="pill pill-crit num text-[11px]" x-text="m.total + ' Kali'"></span>
                                        </div>

                                        <div class="space-y-1.5">
                                            <template x-for="it in m.items" :key="it.item_id">
                                                <div>
                                                    <div class="flex items-center justify-between text-xs gap-2">
                                                        <div class="min-w-0 flex items-center gap-1.5">
                                                            <span class="ty" :class="tyClass(it.item_type)" x-text="it.item_type"></span>
                                                            <span class="num font-semibold text-accent" x-text="it.part_no !== '-' ? it.part_no : ''"></span>
                                                            <span class="font-medium text-slate-800 truncate" x-text="it.part_name"></span>
                                                        </div>
                                                        <b class="num text-rose-700 whitespace-nowrap" x-text="it.count + 'x'"></b>
                                                    </div>
                                                    <div class="mt-1 h-1.5 w-full bg-slate-100 rounded overflow-hidden">
                                                        <div class="h-full bg-rose-600 rounded"
                                                             :style="'width:' + Math.min(100, Math.round((it.count / m.max_item) * 100)) + '%'"></div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="col.months.length === 0">
                                    <div class="py-6 text-center text-xs text-slate-400">
                                        Tidak ada catatan delay pada periode ini.
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        {{-- GRAFIK 3: HISTORY DELAY BERDASARKAN PROBLEM REASON (BULANAN) --}}
        <section class="panel">
            <div class="panel-head">
                <div>
                    <div class="panel-title">
                        <span>Grafik Frekuensi Delay Delivery Berdasarkan Problem Reason (Bulanan)</span>
                        <span class="count-chip"><span x-text="totalReasonDelays"></span><span>Kasus</span></span>
                    </div>
                    <p class="hint" style="margin-top: 2px">
                        Setiap kolom mewakili 1 kategori masalah (<b class="text-slate-700">MATERIAL, PROSES, QC, DIES, BOX/PALET, PLANT 2</b>), menampilkan akumulasi kasus per bulan beserta frekuensi part yang bermasalah.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select x-model="selectedMonthKey" class="h-8 text-xs py-0 pl-2 pr-7">
                        <option value="">Semua Bulan</option>
                        <template x-for="mOpt in availableMonths" :key="mOpt.key">
                            <option :value="mOpt.key" x-text="mOpt.label"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="panel-body">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <template x-for="rCol in reasonColumns" :key="rCol.reason">
                        <div class="border border-slate-200 rounded bg-slate-50/50 flex flex-col overflow-hidden">
                            {{-- Header Kolom Problem Reason --}}
                            <div class="px-3.5 py-2.5 bg-white border-b border-slate-200 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="pill pill-crit" x-text="rCol.reason"></span>
                                    <span class="text-xs font-semibold text-slate-600">Masalah <span x-text="rCol.reason"></span></span>
                                </div>
                                <span class="count-chip" x-text="rCol.total_cases + ' Kali'"></span>
                            </div>

                            {{-- Isi Per Bulan di Dalam Kolom Problem Reason --}}
                            <div class="p-3 space-y-3 flex-1 max-h-80 overflow-y-auto">
                                <template x-for="m in rCol.months" :key="m.month_key">
                                    <div class="p-2.5 rounded bg-white border border-slate-200 space-y-2">
                                        <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                                            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider" x-text="m.month_label"></span>
                                            <span class="pill pill-warn num text-[11px]" x-text="'Total: ' + m.total + ' Kali'"></span>
                                        </div>

                                        <div class="space-y-1.5">
                                            <template x-for="it in m.items" :key="it.item_id + '-' + it.company_code">
                                                <div>
                                                    <div class="flex items-center justify-between text-xs gap-2">
                                                        <div class="min-w-0 flex items-center gap-1.5">
                                                            <span class="pill pill-neu text-[10px] h-4 px-1.5" x-text="it.company_code"></span>
                                                            <span class="num font-semibold text-accent" x-text="it.part_no !== '-' ? it.part_no : ''"></span>
                                                            <span class="font-medium text-slate-800 truncate" x-text="it.part_name"></span>
                                                        </div>
                                                        <b class="num text-slate-900 whitespace-nowrap" x-text="it.count + 'x'"></b>
                                                    </div>
                                                    <div class="mt-1 h-1.5 w-full bg-slate-100 rounded overflow-hidden">
                                                        <div class="h-full rounded"
                                                             style="background: var(--accent)"
                                                             :style="'width:' + Math.min(100, Math.round((it.count / m.total) * 100)) + '%'"></div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="rCol.months.length === 0">
                                    <div class="py-6 text-center text-xs text-slate-400">
                                        0 kendala <span x-text="rCol.reason"></span> pada periode ini.
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </div>

    <x-material-info-modal />

    <script>
        function dashboardStockChart(items, bomChainMap) {
            return {
                items: items,
                bomChainMap: bomChainMap,
                q: '',
                fChainFg: '',
                fType: '',
                fStatus: '',

                tyClass(t) {
                    return 'ty-' + (t === 'FG/KOMPONEN' ? 'FGK' : t);
                },
                pillClass(status) {
                    if (status === 'EMPTY STOCK') return 'pill-empty';
                    if (status === 'VERY LOW') return 'pill-crit';
                    if (status === 'LOW STOCK') return 'pill-warn';
                    if (status === 'NORMAL STOCK') return 'pill-good';
                    if (status === 'OVERSTOCK') return 'pill-info';
                    return 'pill-neu';
                },
                scaleMax(it) {
                    return Math.max(
                        Number(it.total_combined) || 0,
                        Number(it.std_day_3) || 0,
                        Number(it.std_day_1) || 0,
                        1
                    ) * 1.15;
                },
                stockPct(it) {
                    return Math.min(100, Math.max(0, ((Number(it.current_stock) || 0) / this.scaleMax(it)) * 100)).toFixed(1);
                },
                wipPct(it) {
                    return Math.min(100, Math.max(0, ((Number(it.lot_active_qty) || 0) / this.scaleMax(it)) * 100)).toFixed(1);
                },
                markerPct(it, val) {
                    return Math.min(99, Math.max(0, ((Number(val) || 0) / this.scaleMax(it)) * 100)).toFixed(1);
                },
                get filteredItems() {
                    const qLow = this.q.trim().toLowerCase();
                    const chainAllowed = this.fChainFg && this.bomChainMap[this.fChainFg]
                        ? this.bomChainMap[this.fChainFg].map(Number)
                        : null;

                    return this.items.filter(it => {
                        if (chainAllowed && !chainAllowed.includes(Number(it.id))) return false;
                        if (this.fType && it.type !== this.fType) return false;
                        if (this.fStatus) {
                            if (this.fStatus === 'KRITIS') {
                                if (it.status_stock !== 'EMPTY STOCK' && it.status_stock !== 'VERY LOW') return false;
                            } else if (it.status_stock !== this.fStatus) {
                                return false;
                            }
                        }
                        if (qLow) {
                            const hit = (it.part_no + ' ' + it.part_name + ' ' + it.company_code + ' ' + it.used_by_list)
                                .toLowerCase()
                                .includes(qLow);
                            if (!hit) return false;
                        }
                        return true;
                    });
                }
            };
        }

        function dashboardDelayAnalytics(delays, companies, reasons, defaultYear) {
            return {
                delays: delays,
                companies: companies,
                reasons: reasons,
                selectedYear: defaultYear || '',
                selectedMonthKey: '',
                compTypeFilter: 'CUSTOMER',

                tyClass(t) {
                    return 'ty-' + (t === 'FG/KOMPONEN' ? 'FGK' : t);
                },
                get yearFilteredDelays() {
                    return this.delays.filter(d => !this.selectedYear || String(d.year) === String(this.selectedYear));
                },
                get availableMonths() {
                    const map = new Map();
                    this.yearFilteredDelays.forEach(d => {
                        if (!map.has(d.month_key)) {
                            map.set(d.month_key, { key: d.month_key, label: d.month_label });
                        }
                    });
                    return Array.from(map.values()).sort((a, b) => b.key.localeCompare(a.key));
                },
                get totalCustomerDelays() {
                    return this.customerColumns.reduce((sum, c) => sum + c.total_cases, 0);
                },
                get customerColumns() {
                    const relevantCompanies = this.companies.filter(c => {
                        return this.compTypeFilter === 'ALL' || c.type === this.compTypeFilter;
                    });

                    return relevantCompanies.map(comp => {
                        const rows = this.yearFilteredDelays.filter(d => Number(d.company_id) === Number(comp.id));
                        const byMonth = {};

                        rows.forEach(r => {
                            if (!byMonth[r.month_key]) {
                                byMonth[r.month_key] = {
                                    month_key: r.month_key,
                                    month_label: r.month_label,
                                    total: 0,
                                    itemsMap: {}
                                };
                            }
                            byMonth[r.month_key].total++;
                            const iKey = r.item_id;
                            if (!byMonth[r.month_key].itemsMap[iKey]) {
                                byMonth[r.month_key].itemsMap[iKey] = {
                                    item_id: r.item_id,
                                    part_no: r.part_no,
                                    part_name: r.part_name,
                                    item_type: r.item_type,
                                    count: 0
                                };
                            }
                            byMonth[r.month_key].itemsMap[iKey].count++;
                        });

                        const monthsArr = Object.values(byMonth)
                            .sort((a, b) => b.month_key.localeCompare(a.month_key))
                            .map(m => {
                                const itemsList = Object.values(m.itemsMap).sort((a, b) => b.count - a.count);
                                const maxItem = itemsList.length ? Math.max(...itemsList.map(x => x.count)) : 1;
                                return {
                                    month_key: m.month_key,
                                    month_label: m.month_label,
                                    total: m.total,
                                    max_item: maxItem,
                                    items: itemsList
                                };
                            });

                        return {
                            company_id: comp.id,
                            company_code: comp.code,
                            company_name: comp.name,
                            company_type: comp.type,
                            total_cases: rows.length,
                            months: monthsArr
                        };
                    });
                },
                get totalReasonDelays() {
                    return this.reasonColumns.reduce((sum, r) => sum + r.total_cases, 0);
                },
                get reasonColumns() {
                    return this.reasons.map(reasonName => {
                        const rows = this.yearFilteredDelays.filter(d => {
                            if (d.problem_reason !== reasonName) return false;
                            if (this.selectedMonthKey && d.month_key !== this.selectedMonthKey) return false;
                            return true;
                        });

                        const byMonth = {};
                        rows.forEach(r => {
                            if (!byMonth[r.month_key]) {
                                byMonth[r.month_key] = {
                                    month_key: r.month_key,
                                    month_label: r.month_label,
                                    total: 0,
                                    itemsMap: {}
                                };
                            }
                            byMonth[r.month_key].total++;
                            const iKey = r.item_id + '_' + r.company_code;
                            if (!byMonth[r.month_key].itemsMap[iKey]) {
                                byMonth[r.month_key].itemsMap[iKey] = {
                                    item_id: r.item_id,
                                    part_no: r.part_no,
                                    part_name: r.part_name,
                                    item_type: r.item_type,
                                    company_code: r.company_code,
                                    count: 0
                                };
                            }
                            byMonth[r.month_key].itemsMap[iKey].count++;
                        });

                        const monthsArr = Object.values(byMonth)
                            .sort((a, b) => b.month_key.localeCompare(a.month_key))
                            .map(m => ({
                                month_key: m.month_key,
                                month_label: m.month_label,
                                total: m.total,
                                items: Object.values(m.itemsMap).sort((a, b) => b.count - a.count)
                            }));

                        return {
                            reason: reasonName,
                            total_cases: rows.length,
                            months: monthsArr
                        };
                    });
                }
            };
        }
    </script>
</x-app-layout>