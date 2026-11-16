<x-app-layout>
    @php
        $today = \Carbon\Carbon::today();
        $cutoff07 = $today->copy()->setTime(7, 0, 0);
        $isPast07 = now()->greaterThanOrEqualTo($cutoff07);

        $outputToday = \App\Models\Production::whereDate('date_time', $today)->sum('qty');
        $totalStock = \App\Models\InventoryStock::whereHas('item', fn($q) => $q->whereNull('deleted_at'))->sum('current_stock');
        $inboundToday = \App\Models\Inbound::whereDate('date_time', $today)->count();

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
        // PERSIAPAN DATA ITEM, BOM, WIP, & SNAPSHOT PUKUL 07:00 PAGI
        // =========================================================================
        $allActiveItems = \App\Models\Item::with([
            'company',
            'inventoryStock',
            'bomsAsInput.outputItem.inventoryStock',
            'bomsAsInput.process',
            'bomsAsOutput.inputItem.inventoryStock',
            'bomsAsOutput.process',
        ])->orderBy('part_name')->get();

        $allRunningLots = \App\Models\LotTracer::where('status_lot', 'ON PROGRESS')->get();

        $wipByTargetItem = [];
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
            }
        }

        // Hitung mutasi setelah pukul 07:00 pagi hari ini untuk mendapatkan posisi stok jam 07:00
        $netAfter07 = [];
        if ($isPast07) {
            foreach (\App\Models\Inbound::where('date_time', '>=', $cutoff07)->get() as $r) {
                $netAfter07[$r->item_id] = ($netAfter07[$r->item_id] ?? 0) + (float) $r->qty;
            }
            foreach (\App\Models\MaterialOut::where('date_time', '>=', $cutoff07)->get()->groupBy('lot_number') as $lotRows) {
                $f = $lotRows->first();
                if ($f) {
                    $netAfter07[$f->material_item_id] = ($netAfter07[$f->material_item_id] ?? 0) - (float) $f->qty;
                }
            }
            foreach (\App\Models\MaterialReturn::where('date_time', '>=', $cutoff07)->get()->groupBy('lot_number') as $lotRows) {
                $f = $lotRows->first();
                if ($f) {
                    $netAfter07[$f->material_item_id] = ($netAfter07[$f->material_item_id] ?? 0) + (float) $f->qty;
                }
            }
            foreach (\App\Models\Production::where('date_time', '>=', $cutoff07)->get() as $r) {
                $netAfter07[$r->item_id] = ($netAfter07[$r->item_id] ?? 0) + (float) $r->qty;
            }
            foreach (\App\Models\ProductionNg::where('date_time', '>=', $cutoff07)->get() as $r) {
                $netAfter07[$r->item_id] = ($netAfter07[$r->item_id] ?? 0) - (float) $r->qty_ng;
            }
            foreach (\App\Models\Delivery::where('date_time', '>=', $cutoff07)->get() as $r) {
                $netAfter07[$r->item_id] = ($netAfter07[$r->item_id] ?? 0) - (float) $r->qty;
            }
            foreach (\App\Models\ReturnToSupplier::where('date_time', '>=', $cutoff07)->get() as $r) {
                $netAfter07[$r->item_id] = ($netAfter07[$r->item_id] ?? 0) - (float) $r->qty;
            }
            foreach (\App\Models\StockAdjustment::where('date_time', '>=', $cutoff07)->get() as $r) {
                $netAfter07[$r->item_id] = ($netAfter07[$r->item_id] ?? 0) + (float) $r->qty_diff;
            }
        }

        // Hitung total kebutuhan harian tiap material input (untuk alokasi proporsional Shared Material)
        $allBomsFlat = \App\Models\Bom::with(['inputItem.inventoryStock', 'outputItem'])->get();
        $totalDailyReqByInput = [];
        $sharedMaterialsList = [];

        foreach ($allBomsFlat->groupBy('input_item_id') as $inId => $bRows) {
            $uniqueOuts = $bRows->pluck('output_item_id')->unique();
            $sumReq = 0.0;
            foreach ($bRows as $bRow) {
                $outStd1 = $bRow->outputItem ? (float) $bRow->outputItem->calculated_std_day_1 : 0.0;
                $sumReq += ($outStd1 * (float) $bRow->qty_usage);
            }
            $totalDailyReqByInput[$inId] = $sumReq;

            if ($uniqueOuts->count() > 1 && $bRows->first()->inputItem) {
                $inItm = $bRows->first()->inputItem;
                $sharedMaterialsList[] = [
                    'id'         => $inItm->id,
                    'label'      => ($inItm->part_no ? $inItm->part_no . ' — ' : '') . $inItm->part_name . ' (' . $uniqueOuts->count() . ' Part)',
                    'output_ids' => $uniqueOuts->values()->all(),
                ];
            }
        }

        $dashboardItems = $allActiveItems->map(function ($itm) use ($wipByTargetItem, $matInMachineByItem, $netAfter07, $totalDailyReqByInput) {
            $st = $itm->inventoryStock;
            $currStock = $st ? (float) $st->current_stock : 0.0;
            $std1 = (float) $itm->calculated_std_day_1;
            $std2 = (float) $itm->std_day_2;
            $std3 = (float) $itm->std_day_3;
            $ratioFg = $std1 > 0 ? round($currStock / $std1, 2) : 0.0;
            $statusNow = $st ? $st->calculateDynamicStatus() : 'EMPTY STOCK';

            $deltaAfter07 = (float) ($netAfter07[$itm->id] ?? 0.0);
            $stock07 = round(max(0, $currStock - $deltaAfter07), 2);
            if ($stock07 <= 0) {
                $status07 = 'EMPTY STOCK';
            } elseif ($std1 <= 0) {
                $status07 = 'NORMAL STOCK';
            } elseif ($stock07 < $std1) {
                $status07 = 'VERY LOW';
            } elseif ($stock07 < $std2) {
                $status07 = 'LOW STOCK';
            } elseif ($stock07 <= $std3) {
                $status07 = 'NORMAL STOCK';
            } else {
                $status07 = 'OVERSTOCK';
            }
            $ratio07 = $std1 > 0 ? round($stock07 / $std1, 2) : 0.0;

            $wipTarget = (float) ($wipByTargetItem[$itm->id] ?? 0);
            $matInMach = (float) ($matInMachineByItem[$itm->id] ?? 0);
            $wipActive = $itm->type === 'RM' ? $matInMach : round($wipTarget + $matInMach, 2);

            $outBoms = $itm->bomsAsOutput;
            $potMax = 0.0;
            $potProp = 0.0;
            $hasSharedInput = false;
            $materialsDetail = [];

            if ($outBoms->isNotEmpty()) {
                $byGroupMax = [];
                $byGroupProp = [];

                foreach ($outBoms->groupBy(fn ($b) => $b->bom_group ?: ('SG-' . $b->id)) as $gKey => $gRows) {
                    $minMax = null;
                    $minProp = null;

                    foreach ($gRows as $bRow) {
                        $inItm = $bRow->inputItem;
                        $usage = (float) $bRow->qty_usage;
                        if (!$inItm || $usage <= 0) continue;

                        $inStock = $inItm->inventoryStock ? (float) $inItm->inventoryStock->current_stock : 0.0;
                        $usedCount = $inItm->bomsAsInput ? $inItm->bomsAsInput->pluck('output_item_id')->unique()->count() : 1;
                        if ($usedCount > 1) {
                            $hasSharedInput = true;
                        }

                        $canMakeMax = floor(($inStock / $usage) * 100) / 100;

                        $myReq = $std1 * $usage;
                        $totReq = (float) ($totalDailyReqByInput[$inItm->id] ?? $myReq);
                        $shareFactor = ($usedCount > 1 && $totReq > 0) ? min(1.0, $myReq / $totReq) : 1.0;
                        $allocatedStock = $inStock * $shareFactor;
                        $canMakeProp = floor(($allocatedStock / $usage) * 100) / 100;

                        if ($minMax === null || $canMakeMax < $minMax) $minMax = $canMakeMax;
                        if ($minProp === null || $canMakeProp < $minProp) $minProp = $canMakeProp;

                        $materialsDetail[] = [
                            'name'       => ($inItm->part_no ? $inItm->part_no . ' — ' : '') . $inItm->part_name,
                            'stock'      => floatval($inStock),
                            'uom'        => $inItm->uom,
                            'usage'      => $bRow->formatted_qty_usage,
                            'make_prop'  => round($canMakeProp),
                            'make_max'   => round($canMakeMax),
                            'is_shared'  => $usedCount > 1,
                            'share_pct'  => round($shareFactor * 100),
                            'used_count' => $usedCount,
                        ];
                    }

                    if ($minMax !== null) $byGroupMax[] = $minMax;
                    if ($minProp !== null) $byGroupProp[] = $minProp;
                }

                $potMax = !empty($byGroupMax) ? round(max($byGroupMax), 0) : 0.0;
                $potProp = !empty($byGroupProp) ? round(max($byGroupProp), 0) : 0.0;
            }

            $inputIds = $outBoms->pluck('input_item_id')->unique()->values()->all();

            return [
                'id'               => $itm->id,
                'part_no'          => $itm->part_no ?: '-',
                'part_name'        => $itm->part_name,
                'type'             => $itm->type,
                'uom'              => $itm->uom,
                'company_id'       => $itm->company_id,
                'company_code'     => $itm->company->company_code ?? 'INTERNAL',
                'company_name'     => $itm->company->company_name ?? 'PT. Dian Raya Cipta',
                'company_type'     => $itm->company->type_company ?? 'INTERNAL',
                'current_stock'    => $currStock,
                'stock_07'         => $stock07,
                'status_07'        => $status07,
                'ratio_07'         => $ratio07,
                'wip_active'       => $wipActive,
                'pot_max'          => $potMax,
                'pot_prop'         => $potProp,
                'has_shared_input' => $hasSharedInput,
                'input_ids'        => $inputIds,
                'materials_detail' => $materialsDetail,
                'std_day_1'        => $std1,
                'std_day_2'        => $std2,
                'std_day_3'        => $std3,
                'ratio_stock'      => $ratioFg,
                'status_stock'     => $statusNow,
                'info_payload'     => $itm->material_info_payload,
            ];
        })->values();

        $allCompaniesList = \App\Models\Company::orderByRaw("CASE type_company WHEN 'CUSTOMER' THEN 1 WHEN 'INTERNAL' THEN 2 ELSE 3 END")
            ->orderBy('company_code')
            ->get()
            ->map(fn ($c) => [
                'id'   => $c->id,
                'code' => $c->company_code,
                'name' => $c->company_name,
                'type' => $c->type_company,
            ])->values();

        $defaultFgCustomer = $allCompaniesList->firstWhere('type', 'CUSTOMER');
        $defaultStockCompanyId = $defaultFgCustomer ? (string) $defaultFgCustomer['id'] : '';

        $monthNamesShort = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];
        $monthNamesFull = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $allDelays = \App\Models\ProblemDelay::with(['company', 'item.company'])
            ->orderByDesc('incident_date')
            ->orderByDesc('date_time')
            ->get()
            ->map(function ($d) use ($monthNamesShort, $monthNamesFull) {
                $dt = $d->incident_date ? \Carbon\Carbon::parse($d->incident_date) : \Carbon\Carbon::parse($d->date_time);
                $mNum = (int) $dt->format('n');
                $yr   = (int) $dt->format('Y');
                return [
                    'id'             => $d->id,
                    'year'           => (string) $yr,
                    'month_num'      => $mNum,
                    'month_key'      => $dt->format('Y-m'),
                    'month_short'    => ($monthNamesShort[$mNum] ?? $dt->format('M')) . ' ' . substr((string) $yr, -2),
                    'month_label'    => ($monthNamesFull[$mNum] ?? $dt->format('M')) . ' ' . $yr,
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
                <span class="pill pill-good">TERCATAT DI SISTEM</span>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-l">NG Rate</div>
            <div class="kpi-v">{{ $ngRate }}<small>%</small></div>
            <div class="kpi-s">
                <span class="pill {{ $ngRate <= 2 ? 'pill-good' : 'pill-crit' }}">TOTAL NG: {{ floatval($totalNgAll) }}</span>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-l">Delay Delivery</div>
            <div class="kpi-v">{{ $delayCount }}<small>log hari ini</small></div>
            <div class="kpi-s">
                <span class="pill {{ $delayCount > 0 ? 'pill-crit' : 'pill-good' }}">
                    {{ $delayCount > 0 ? 'PERLU TINDAK LANJUT' : 'TIDAK ADA KENDALA' }}
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
                <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full h-auto min-h-[250px]" role="img" aria-label="Output produksi 7 hari terakhir">
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
                            <div class="text-xs text-slate-500 mt-0.5">Item dengan ketahanan stok kurang dari 1 hari</div>
                        </div>
                        <span class="pill {{ $veryLowItems > 0 ? 'pill-crit' : 'pill-good' }} num">{{ $veryLowItems }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3.5">
                        <div>
                            <div class="font-semibold text-slate-900">Total Item Stok Rendah</div>
                            <div class="text-xs text-slate-500 mt-0.5">Termasuk status Low Stock (di bawah Std Day 2)</div>
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
                <span class="count-chip"><span>{{ $activeLots->count() }}</span><span>LOT</span></span>
            </div>
            <a href="{{ route('lot_tracers.index') }}" class="btn btn-sm btn-primary">
                <x-icon name="route" />
                <span>Buka Lot Tracer</span>
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
    {{-- 4. DAFTAR PRIORITAS STOK KRITIS PUKUL 07:00 PAGI (TABEL URUT PRIORITAS) --}}
    {{-- ======================================================================= --}}
    <section class="panel"
             x-data="morningPriorityTable({{ Js::from($dashboardItems) }}, {{ Js::from($allCompaniesList) }})">
        <div class="panel-head">
            <div>
                <div class="panel-title">
                    <span>Daftar Prioritas Stok Kritis Pukul 07:00 Pagi (Empty · Very Low · Low Stock)</span>
                    <span class="count-chip"><span x-text="priorityRows.length"></span><span>ITEM PRIORITAS</span></span>
                </div>
                <p class="hint" style="margin-top: 2px">
                    Diurutkan mulai dari <b>EMPTY STOCK (0)</b>, <b>VERY LOW</b> (Qty terkecil di atas: 1 di atas 2), hingga <b>LOW STOCK</b> sebagai acuan prioritas produksi pagi &amp; pemesanan material.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q"
                       placeholder="Cari Part No / Part Name..."
                       class="h-8 text-xs px-2.5 w-44">

                <select x-model="fGroup" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="TARGET_PROD">Target Produksi (FG + FG/KOMPONEN)</option>
                    <option value="MATERIAL">Kebutuhan Material (RM + KOMPONEN + FG/KOMPONEN)</option>
                    <option value="FG">Hanya FG</option>
                    <option value="FG/KOMPONEN">Hanya FG/KOMPONEN</option>
                    <option value="KOMPONEN">Hanya KOMPONEN</option>
                    <option value="RM">Hanya RM</option>
                    <option value="TRANSIT">Hanya TRANSIT</option>
                    <option value="ALL">Semua Tipe Item</option>
                </select>

                <select x-model="fCompany" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Perusahaan</option>
                    @foreach($allCompaniesList as $c)
                        <option value="{{ $c['id'] }}">{{ $c['code'] }} — {{ $c['name'] }} ({{ $c['type'] }})</option>
                    @endforeach
                </select>

                <button type="button"
                        x-show="q || fGroup !== 'TARGET_PROD' || fCompany !== ''" x-cloak
                        @click="q=''; fGroup='TARGET_PROD'; fCompany=''"
                        class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap" style="max-height: 360px;">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[5%] text-center">Prioritas</th>
                        <th class="w-[26%]">Part No &amp; Part Name</th>
                        <th class="w-[10%] text-center">Tipe</th>
                        <th class="w-[13%]">Perusahaan</th>
                        <th class="num w-[11%]">Std 1 / Min Std 2</th>
                        <th class="num w-[10%]">Stok Pkl 07:00</th>
                        <th class="num w-[10%]">Stok Saat Ini</th>
                        <th class="num w-[7%]">WIP Lot</th>
                        <th class="w-[8%] text-center">Status 07:00</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, idx) in priorityRows" :key="row.id">
                        <tr>
                            <td class="text-center num font-bold text-slate-700" x-text="'#' + (idx + 1)"></td>
                            <td>
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="num text-xs font-bold text-accent" x-text="row.part_no"></div>
                                        <div class="font-semibold text-slate-900 truncate" x-text="row.part_name"></div>
                                    </div>
                                    <button type="button" class="act-btn flex-none" title="Informasi Material & Alur BOM"
                                            @click="$dispatch('open-material-info', row.info_payload)">
                                        <x-icon name="info" />
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="ty" :class="tyClass(row.type)" x-text="row.type"></span>
                            </td>
                            <td>
                                <div class="font-bold text-xs text-accent" x-text="row.company_code"></div>
                                <span class="pill mt-0.5"
                                      :class="row.company_type === 'CUSTOMER' ? 'pill-good' : (row.company_type === 'INTERNAL' ? 'pill-warn' : 'pill-info')"
                                      x-text="row.company_type"></span>
                            </td>
                            <td class="num text-xs text-slate-600">
                                <span class="font-semibold text-slate-800" x-text="row.std_day_1"></span>
                                <span class="text-slate-400">/</span>
                                <span x-text="row.std_day_2 + ' ' + row.uom"></span>
                            </td>
                            <td class="num whitespace-nowrap">
                                <span class="text-[13.5px] font-bold"
                                      :class="row.stock_07 <= 0 ? 'text-rose-700' : (row.status_07 === 'VERY LOW' ? 'text-rose-600' : 'text-amber-700')"
                                      x-text="row.stock_07"></span>
                                <span class="text-[11px] text-slate-500" x-text="row.uom"></span>
                                <div class="text-[10.5px] text-slate-400" x-text="'Ratio: ' + row.ratio_07 + ' Hr'"></div>
                            </td>
                            <td class="num whitespace-nowrap">
                                <span class="font-bold text-slate-900" x-text="row.current_stock"></span>
                                <span class="text-[11px] text-slate-500" x-text="row.uom"></span>
                            </td>
                            <td class="num whitespace-nowrap">
                                <span class="font-semibold"
                                      :class="row.wip_active > 0 ? 'text-amber-700' : 'text-slate-400'"
                                      x-text="row.wip_active + ' ' + row.uom"></span>
                            </td>
                            <td class="text-center">
                                <span class="pill" :class="pillClass(row.status_07)" x-text="row.status_07"></span>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="priorityRows.length === 0">
                        <td colspan="9">
                            <div class="empty">Tidak ada item berstatus Empty, Very Low, atau Low Stock pada filter ini.</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    {{-- ======================================================================= --}}
    {{-- 5. GRAFIK KETERSEDIAAN STOK 3 LAPIS (DENGAN KARTU HOVER SEDERHANA)     --}}
    {{-- ======================================================================= --}}
    <section class="panel"
             x-data="dashboardStockVerticalChart({{ Js::from($dashboardItems) }}, {{ Js::from($sharedMaterialsList) }}, '{{ $defaultStockCompanyId }}')">
        <div class="panel-head">
            <div>
                <div class="panel-title">
                    <span>Grafik Ketersediaan Stok Lintas Tahap (Potensi Material · WIP Lot · Stok FG)</span>
                    <span class="count-chip"><span x-text="chartItems.length"></span><span>ITEM</span></span>
                </div>
                <p class="hint" style="margin-top: 2px">
                    Batang vertikal dari bawah ke atas (urut stok terkecil di kiri ke terbesar di kanan):
                    <b>Lapis Bawah (Abu)</b> = Bisa dibuat dari Material,
                    <b>Lapis Tengah (Kuning)</b> = WIP di Lot Tracer,
                    <b>Lapis Atas (Biru)</b> = Stok Siap Kirim.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <select x-model="fCompany" class="h-8 text-xs py-0 pl-2 pr-7" title="Filter Perusahaan">
                    <option value="">Semua Perusahaan</option>
                    @foreach($allCompaniesList as $c)
                        <option value="{{ $c['id'] }}">{{ $c['code'] }} — {{ $c['name'] }} ({{ $c['type'] }})</option>
                    @endforeach
                </select>

                <select x-model="fCategory" class="h-8 text-xs py-0 pl-2 pr-7" title="Filter Kategori Tipe Item">
                    <option value="FG_FGK">FG + FG/KOMPONEN</option>
                    <option value="FG">FG</option>
                    <option value="FG/KOMPONEN">FG/KOMPONEN</option>
                    <option value="KOMP_RM">KOMPONEN + RM</option>
                    <option value="RM">RM</option>
                    <option value="KOMPONEN">KOMPONEN</option>
                    <option value="TRANSIT">TRANSIT</option>
                    <option value="ALL">Semua Tipe</option>
                </select>

                <select x-model="fSharedMat" class="h-8 text-xs py-0 pl-2 pr-7" title="Filter Kelompok Material Bersama (Shared RM)">
                    <option value="">Semua Material Pembentuk</option>
                    @foreach($sharedMaterialsList as $sm)
                        <option value="{{ $sm['id'] }}">Shared RM: {{ $sm['label'] }}</option>
                    @endforeach
                </select>

                <div class="seg" role="radiogroup" aria-label="Mode Alokasi Material Bersama">
                    <label>
                        <input type="radio" value="prop" x-model="allocMode">
                        <span>Alokasi Proporsional</span>
                    </label>
                    <label>
                        <input type="radio" value="max" x-model="allocMode">
                        <span>Maks Mandiri</span>
                    </label>
                </div>

                <button type="button"
                        x-show="fCompany !== '{{ $defaultStockCompanyId }}' || fCategory !== 'FG_FGK' || fSharedMat !== '' || allocMode !== 'prop'" x-cloak
                        @click="resetStockChart()"
                        class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="panel-body space-y-3">
            {{-- Legenda Grafik & Penjelasan Singkat Mode Alokasi --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-3 py-2 rounded border border-slate-200 bg-slate-50 text-xs">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="inline-flex items-center gap-1.5 font-semibold text-slate-700">
                        <i class="inline-block w-3 h-3 rounded-sm" style="background: #0B5CAD"></i>
                        Stok FG / Gudang (Siap Kirim)
                    </span>
                    <span class="inline-flex items-center gap-1.5 font-semibold text-slate-700">
                        <i class="inline-block w-3 h-3 rounded-sm" style="background: #F59E0B"></i>
                        WIP di Lot Tracer (Sedang Diproses)
                    </span>
                    <span class="inline-flex items-center gap-1.5 font-semibold text-slate-700">
                        <i class="inline-block w-3 h-3 rounded-sm" style="background: #94A3B8"></i>
                        Potensi dari Material (Bahan Baku)
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-slate-600">
                        <i class="inline-block w-3 h-0.5 bg-rose-600"></i>
                        Target Harian (Std Day 1)
                    </span>
                </div>
                <div class="text-[11px] font-medium text-slate-600">
                    <span x-show="allocMode === 'prop'">Mode aktif: <b>Alokasi Proporsional</b> (Bahan baku bersama dibagi adil sesuai porsi PO agar tidak terhitung ganda).</span>
                    <span x-show="allocMode === 'max'" x-cloak>Mode aktif: <b>Maks Mandiri</b> (Simulasi jika 100% bahan baku bersama dipakai khusus untuk 1 part ini saja).</span>
                </div>
            </div>

            {{-- PANEL RINGKASAN SAAT BATANG DI-HOVER (SEDERHANA & MUDAH DIPAHAMI) --}}
            <div class="rounded border px-3.5 py-2.5 transition-colors"
                 :class="hoveredItem ? 'border-[#0B5CAD] bg-[#E4EEF8]/50' : 'border-slate-200 bg-slate-50/60'">
                <template x-if="!hoveredItem">
                    <div class="flex items-center justify-between text-xs text-slate-500">
                        <span>Arahkan kursor (hover) pada salah satu batang grafik di bawah untuk melihat rincian sederhana 3 lapis stok, atau klik batang untuk membuka Alur BOM lengkap.</span>
                        <span class="pill pill-neu">ARAHKAN KURSOR KE BATANG</span>
                    </div>
                </template>

                <template x-if="hoveredItem">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-center text-xs">
                        {{-- Kolom 1: Identitas Part --}}
                        <div class="lg:col-span-4 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="ty" :class="tyClass(hoveredItem.type)" x-text="hoveredItem.type"></span>
                                <span class="num font-bold text-accent" x-text="hoveredItem.part_no !== '-' ? hoveredItem.part_no : ''"></span>
                                <span class="pill pill-neu" x-text="hoveredItem.company_code"></span>
                                <span class="pill" :class="pillClass(hoveredItem.status_stock)" x-text="hoveredItem.status_stock"></span>
                            </div>
                            <div class="font-bold text-slate-900 mt-1 truncate" x-text="hoveredItem.part_name"></div>
                            <div class="text-[11px] text-slate-600 mt-0.5 num">
                                Target Harian (Std 1): <b x-text="hoveredItem.std_day_1 + ' ' + hoveredItem.uom"></b> ·
                                Min (Std 2): <b x-text="hoveredItem.std_day_2"></b> ·
                                Max (Std 3): <b x-text="hoveredItem.std_day_3"></b>
                            </div>
                        </div>

                        {{-- Kolom 2: 3 Kotak Angka Sederhana (Biru + Kuning + Abu = Total) --}}
                        <div class="lg:col-span-5 grid grid-cols-4 gap-2 text-center num">
                            <div class="p-2 rounded bg-white border border-slate-200">
                                <div class="text-[10px] font-semibold text-slate-500 uppercase">1. Siap Kirim (Biru)</div>
                                <div class="text-sm font-bold text-[#0B5CAD] mt-0.5" x-text="hoveredItem.current_stock + ' ' + hoveredItem.uom"></div>
                                <div class="text-[10px] text-slate-500" x-text="hoveredItem.ratio_stock + ' Hari'"></div>
                            </div>
                            <div class="p-2 rounded bg-white border border-slate-200">
                                <div class="text-[10px] font-semibold text-slate-500 uppercase">2. WIP Mesin (Kuning)</div>
                                <div class="text-sm font-bold text-amber-600 mt-0.5" x-text="hoveredItem.wip_active + ' ' + hoveredItem.uom"></div>
                                <div class="text-[10px] text-slate-500">Sedang Jalan</div>
                            </div>
                            <div class="p-2 rounded bg-white border border-slate-200">
                                <div class="text-[10px] font-semibold text-slate-500 uppercase">3. Dari Bahan (Abu)</div>
                                <div class="text-sm font-bold text-slate-700 mt-0.5" x-text="matPot(hoveredItem) + ' ' + hoveredItem.uom"></div>
                                <div class="text-[10px] text-slate-500" x-text="allocMode === 'prop' ? 'Jatah Proporsional' : 'Maks Mandiri'"></div>
                            </div>
                            <div class="p-2 rounded bg-slate-900 text-white">
                                <div class="text-[10px] font-semibold text-slate-300 uppercase">Total Pipeline</div>
                                <div class="text-sm font-bold mt-0.5" x-text="totalPipeline(hoveredItem) + ' ' + hoveredItem.uom"></div>
                                <div class="text-[10px] text-amber-300" x-text="'Total ' + pipelineRatio(hoveredItem) + ' Hari'"></div>
                            </div>
                        </div>

                        {{-- Kolom 3: Penjelasan Perbandingan Proporsional vs Maks Mandiri untuk Part Ini --}}
                        <div class="lg:col-span-3 bg-white p-2.5 rounded border border-slate-200 leading-snug">
                            <template x-if="hoveredItem.has_shared_input">
                                <div>
                                    <div class="font-bold text-accent flex items-center justify-between">
                                        <span>Memakai Bahan Baku Bersama</span>
                                        <span class="pill pill-info text-[9.5px] h-4 px-1.5">SHARED RM</span>
                                    </div>
                                    <div class="text-[11px] text-slate-700 mt-1">
                                        • <b>Alokasi Proporsional:</b> bisa buat <b class="num text-slate-900" x-text="hoveredItem.pot_prop + ' ' + hoveredItem.uom"></b> (jatah dibagi adil dengan part lain).<br>
                                        • <b>Maks Mandiri:</b> bisa buat <b class="num text-slate-900" x-text="hoveredItem.pot_max + ' ' + hoveredItem.uom"></b> (jika 100% bahan dipakai part ini).
                                    </div>
                                </div>
                            </template>
                            <template x-if="!hoveredItem.has_shared_input && hoveredItem.materials_detail && hoveredItem.materials_detail.length > 0">
                                <div>
                                    <div class="font-bold text-slate-800">Bahan Baku Khusus (Tidak Dibagi)</div>
                                    <div class="text-[11px] text-slate-600 mt-0.5">
                                        Bahan pembentuk hanya dipakai oleh part ini, sehingga nilai Proporsional &amp; Maks Mandiri sama (<b class="num" x-text="hoveredItem.pot_prop + ' ' + hoveredItem.uom"></b>).
                                    </div>
                                </div>
                            </template>
                            <template x-if="!hoveredItem.materials_detail || hoveredItem.materials_detail.length === 0">
                                <div>
                                    <div class="font-bold text-slate-800">Item Non-Rakitan BOM</div>
                                    <div class="text-[11px] text-slate-600 mt-0.5">
                                        Item ini berasal langsung dari Supplier / tidak memiliki bahan pembentuk di BOM.
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Kanvas Grafik Batang Vertikal 3 Lapis --}}
            <div class="border border-slate-200 rounded bg-white p-4 overflow-x-auto" x-show="chartItems.length > 0">
                <div class="min-w-[680px]">
                    <div class="relative h-[260px] border-b border-l border-slate-300 ml-12 mr-2 flex items-end justify-around gap-3 px-4 pt-6">
                        <template x-for="g in [0.25, 0.5, 0.75, 1]" :key="g">
                            <div class="absolute left-0 right-0 border-t border-dashed border-slate-200 pointer-events-none"
                                 :style="'bottom:' + (g * 210) + 'px'">
                                <span class="num text-[10.5px] text-slate-400 absolute -left-12 -top-2 w-10 text-right"
                                      x-text="Math.round(yMax * g)"></span>
                            </div>
                        </template>
                        <span class="num text-[10.5px] text-slate-400 absolute -left-12 -bottom-2 w-10 text-right">0</span>

                        <template x-for="it in chartItems" :key="it.id">
                            <div class="relative flex flex-col items-center justify-end h-[210px] flex-1 max-w-[92px] min-w-[62px] group cursor-pointer"
                                 @mouseenter="hoveredItem = it"
                                 @focus="hoveredItem = it"
                                 @click="$dispatch('open-material-info', it.info_payload)">

                                <div class="mb-1 text-center leading-tight">
                                    <div class="num text-[11px] font-bold text-[#0B5CAD]" x-text="it.current_stock"></div>
                                    <div class="num text-[9.5px] text-slate-500"
                                         x-show="totalPipeline(it) > it.current_stock"
                                         x-text="'Tot: ' + totalPipeline(it)"></div>
                                </div>

                                <template x-if="it.std_day_1 > 0">
                                    <div class="absolute left-1 right-1 border-t-2 border-rose-600 z-20 pointer-events-none"
                                         :style="'bottom:' + barPx(it.std_day_1) + 'px'"></div>
                                </template>

                                <div class="w-10 sm:w-11 flex flex-col justify-end rounded-t overflow-hidden border transition-transform group-hover:scale-[1.04]"
                                     :class="hoveredItem?.id === it.id ? 'border-[#0B5CAD] ring-2 ring-[#0B5CAD]/25' : 'border-slate-300/80 bg-slate-100/50'">
                                    {{-- Lapis Atas: Stok FG / Gudang Siap Kirim (Biru) --}}
                                    <div class="w-full transition-all duration-200"
                                         x-show="it.current_stock > 0"
                                         :style="'height:' + Math.max(3, barPx(it.current_stock)) + 'px; background: #0B5CAD;'"></div>

                                    {{-- Lapis Tengah: WIP Aktif di Lot Tracer (Kuning / Amber) --}}
                                    <div class="w-full transition-all duration-200"
                                         x-show="it.wip_active > 0"
                                         :style="'height:' + Math.max(3, barPx(it.wip_active)) + 'px; background: #F59E0B;'"></div>

                                    {{-- Lapis Bawah: Potensi dari Material Pembentuk (Abu-abu) --}}
                                    <div class="w-full transition-all duration-200"
                                         x-show="matPot(it) > 0"
                                         :style="'height:' + Math.max(3, barPx(matPot(it))) + 'px; background: #94A3B8;'"></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="ml-12 mr-2 flex items-start justify-around gap-3 px-4 pt-2.5">
                        <template x-for="it in chartItems" :key="'lbl-' + it.id">
                            <div class="flex-1 max-w-[92px] min-w-[62px] text-center cursor-pointer"
                                 @mouseenter="hoveredItem = it"
                                 @click="$dispatch('open-material-info', it.info_payload)">
                                <div class="num text-[11px] font-bold text-accent truncate"
                                     x-text="it.part_no !== '-' ? it.part_no : it.part_name"></div>
                                <div class="text-[10px] text-slate-600 truncate" x-text="it.part_name"></div>

                                <div class="mt-1 flex flex-col items-center gap-0.5">
                                    <span class="ty text-[9.5px] h-4 px-1.5" :class="tyClass(it.type)" x-text="it.type"></span>
                                    <span class="pill text-[9.5px] h-4 px-1.5 mt-0.5" :class="pillClass(it.status_stock)" x-text="it.status_stock"></span>
                                    <span class="num text-[10px] font-semibold text-slate-700" x-text="'FG: ' + it.ratio_stock + ' Hr'"></span>
                                    <span class="num text-[9.5px] text-slate-500" x-text="'Pipe: ' + pipelineRatio(it) + ' Hr'"></span>
                                    <span class="pill pill-info text-[9px] h-3.5 px-1" x-show="it.has_shared_input">SHARED RM</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div x-show="chartItems.length === 0" class="empty">
                Tidak ada item yang sesuai dengan filter grafik ketersediaan stok.
            </div>
        </div>
    </section>

    {{-- ======================================================================= --}}
    {{-- 6. DUA GRAFIK DELAY DELIVERY (DENGAN WARNA PER BULAN & PER PROBLEM)    --}}
    {{-- ======================================================================= --}}
    <div x-data="dashboardDelayVerticalCharts({{ Js::from($allDelays) }}, {{ Js::from($allCompaniesList) }}, {{ Js::from($delayReasonsList) }}, '{{ $delayYears[0] ?? date('Y') }}')"
         class="space-y-[14px]">

        {{-- FILTER BERSAMA UNTUK KEDUA GRAFIK DELAY DELIVERY --}}
        <section class="panel">
            <div class="panel-head">
                <div>
                    <div class="panel-title">
                        <span>Filter Analitik Delay Delivery Bulanan</span>
                        <span class="count-chip"><span x-text="filteredDelays.length"></span><span>KASUS TERFILTER</span></span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select x-model="selectedYear" class="h-8 text-xs py-0 pl-2 pr-7">
                        <option value="">Semua Tahun</option>
                        @foreach($delayYears as $yr)
                            <option value="{{ $yr }}">Tahun {{ $yr }}</option>
                        @endforeach
                    </select>

                    <select x-model="selectedCompanyId" class="h-8 text-xs py-0 pl-2 pr-7">
                        <option value="">Semua Customer / Perusahaan</option>
                        @foreach($allCompaniesList as $c)
                            <option value="{{ $c['id'] }}">{{ $c['code'] }} — {{ $c['name'] }} ({{ $c['type'] }})</option>
                        @endforeach
                    </select>

                    <button type="button" class="btn btn-sm"
                            x-show="selectedMonths.length > 0 || selectedCompanyId !== '' || selectedYear !== '{{ $delayYears[0] ?? date('Y') }}'" x-cloak
                            @click="resetDelayFilter()">
                        Reset Filter Delay
                    </button>
                </div>
            </div>

            <div class="panel-body py-2.5 bg-slate-50/70 flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mr-1">Pilih Bulan:</span>
                <button type="button"
                        @click="selectedMonths = []"
                        class="btn btn-sm h-7 text-xs"
                        :class="selectedMonths.length === 0 ? 'btn-primary' : ''">
                    <span>Semua Bulan</span>
                </button>
                @foreach($monthNamesShort as $mNum => $mLabel)
                    <button type="button"
                            @click="toggleMonth({{ $mNum }})"
                            class="btn btn-sm h-7 text-xs"
                            :class="selectedMonths.includes({{ $mNum }}) ? 'btn-primary' : ''">
                        <span>{{ $mLabel }}</span>
                    </button>
                @endforeach
            </div>
        </section>

        {{-- GRAFIK DELAY 1: PER PERUSAHAAN / CUSTOMER (BATANG VERTIKAL BERWARNA PER BULAN + LIST PART) --}}
        <section class="panel">
            <div class="panel-head">
                <div>
                    <div class="panel-title">
                        <span>1. Grafik Problem Delay per Perusahaan / Customer (Batang Vertikal per Bulan)</span>
                        <span class="count-chip"><span x-text="companyChartGroups.length"></span><span>PERUSAHAAN</span></span>
                    </div>
                    <p class="hint" style="margin-top: 2px">
                        Setiap kelompok mewakili 1 perusahaan dengan warna batang berbeda untuk setiap bulan kejadian, diikuti rincian frekuensi Part No &amp; Part Name di bawah grafik.
                    </p>
                </div>
            </div>

            <div class="panel-body space-y-4">
                {{-- Legenda Warna Bulan --}}
                <div class="flex flex-wrap items-center gap-3 px-3 py-2 rounded border border-slate-200 bg-slate-50 text-xs">
                    <span class="font-semibold text-slate-600">Legenda Warna Bulan:</span>
                    <template x-for="mLeg in activeMonthsLegend" :key="mLeg.month_num">
                        <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                            <i class="inline-block w-3 h-3 rounded-sm" :style="'background:' + monthColor(mLeg.month_num)"></i>
                            <span x-text="mLeg.label"></span>
                        </span>
                    </template>
                </div>

                {{-- Grafik Batang Vertikal per Perusahaan --}}
                <div class="border border-slate-200 rounded bg-white p-4 overflow-x-auto" x-show="companyChartGroups.length > 0">
                    <div class="min-w-[600px]">
                        <div class="relative h-[220px] border-b border-l border-slate-300 ml-10 mr-2 flex items-end justify-around gap-6 px-4 pt-6">
                            <template x-for="g in [0.5, 1]" :key="'cg-' + g">
                                <div class="absolute left-0 right-0 border-t border-dashed border-slate-200 pointer-events-none"
                                     :style="'bottom:' + (g * 175) + 'px'">
                                    <span class="num text-[10.5px] text-slate-400 absolute -left-10 -top-2 w-8 text-right"
                                          x-text="Math.round(maxCompanyMonthCount * g)"></span>
                                </div>
                            </template>
                            <span class="num text-[10.5px] text-slate-400 absolute -left-10 -bottom-2 w-8 text-right">0</span>

                            {{-- Kelompok Perusahaan --}}
                            <template x-for="grp in companyChartGroups" :key="grp.company_id">
                                <div class="flex flex-col items-center justify-end h-[175px] flex-1">
                                    <div class="flex items-end justify-center gap-2.5 h-full px-2">
                                        <template x-for="mBar in grp.months" :key="mBar.month_key">
                                            <div class="flex flex-col items-center justify-end h-full w-10 group">
                                                <span class="num text-[11px] font-bold mb-1"
                                                      :style="'color:' + monthColor(mBar.month_num)"
                                                      x-text="mBar.count + 'x'"></span>
                                                <div class="w-7 rounded-t transition-all duration-200 group-hover:brightness-110"
                                                     :style="'height:' + Math.max(6, Math.round((mBar.count / maxCompanyMonthCount) * 150)) + 'px; background:' + monthColor(mBar.month_num) + ';'"
                                                     :title="grp.company_code + ' (' + mBar.month_label + '): ' + mBar.count + ' kasus'"></div>
                                                <span class="num text-[10px] font-semibold text-slate-700 mt-1 whitespace-nowrap" x-text="mBar.month_short"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Label Nama Perusahaan di Bawah Kelompok Batang --}}
                        <div class="ml-10 mr-2 flex items-start justify-around gap-6 px-4 pt-2.5">
                            <template x-for="grp in companyChartGroups" :key="'clbl-' + grp.company_id">
                                <div class="flex-1 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="num font-bold text-xs text-accent" x-text="grp.company_code"></span>
                                        <span class="pill"
                                              :class="grp.company_type === 'CUSTOMER' ? 'pill-good' : (grp.company_type === 'INTERNAL' ? 'pill-warn' : 'pill-info')"
                                              x-text="grp.company_type"></span>
                                        <span class="count-chip" x-text="grp.total + ' KASUS'"></span>
                                    </div>
                                    <div class="text-xs font-semibold text-slate-800 mt-0.5" x-text="grp.company_name"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- List Part No & Part Name + Frekuensinya --}}
                <div>
                    <div class="sec-h flex items-center gap-2">
                        <span>Rincian Part Bermasalah &amp; Frekuensi per Perusahaan</span>
                        <span class="count-chip"><span x-text="companyPartSummary.length"></span><span>PART TERDAMPAK</span></span>
                    </div>
                    <div class="tbl-wrap" style="max-height: 240px;">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th class="w-[6%] text-center">No</th>
                                    <th class="w-[20%]">Perusahaan</th>
                                    <th class="w-[34%]">Part No &amp; Part Name</th>
                                    <th class="w-[12%] text-center">Tipe</th>
                                    <th class="w-[16%]">Bulan Kejadian</th>
                                    <th class="num w-[12%]">Frekuensi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(p, i) in companyPartSummary" :key="p.key">
                                    <tr>
                                        <td class="text-center num text-slate-500 font-semibold" x-text="i + 1"></td>
                                        <td>
                                            <span class="font-bold text-xs text-accent" x-text="p.company_code"></span>
                                            <span class="text-xs text-slate-600 block truncate" x-text="p.company_name"></span>
                                        </td>
                                        <td>
                                            <div class="num text-xs font-bold text-accent" x-text="p.part_no"></div>
                                            <div class="font-semibold text-slate-900" x-text="p.part_name"></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="ty" :class="tyClass(p.item_type)" x-text="p.item_type"></span>
                                        </td>
                                        <td class="text-xs text-slate-600" x-text="p.months_list"></td>
                                        <td class="num">
                                            <span class="pill pill-crit num" x-text="p.count + ' KALI'"></span>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="companyPartSummary.length === 0">
                                    <td colspan="6"><div class="empty">Tidak ada data delay delivery pada filter ini.</div></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        {{-- GRAFIK DELAY 2: BERDASARKAN PROBLEM REASON (6 WARNA KATEGORI + LIST PROBLEM) --}}
        <section class="panel">
            <div class="panel-head">
                <div>
                    <div class="panel-title">
                        <span>2. Grafik Problem Delay Berdasarkan Problem Reason (Batang Vertikal per Bulan)</span>
                        <span class="count-chip"><span x-text="filteredDelays.length"></span><span>TOTAL FREKUENSI</span></span>
                    </div>
                    <p class="hint" style="margin-top: 2px">
                        Setiap kategori Problem Reason memiliki warna identitas tersendiri beserta perbandingan batang per bulan dan rincian part di bawahnya.
                    </p>
                </div>
            </div>

            <div class="panel-body space-y-4">
                {{-- Legenda 6 Warna Problem Reason --}}
                <div class="flex flex-wrap items-center gap-4 px-3 py-2 rounded border border-slate-200 bg-slate-50 text-xs">
                    <span class="font-semibold text-slate-600">Legenda Warna Problem Reason:</span>
                    <template x-for="rName in reasons" :key="'leg-' + rName">
                        <span class="inline-flex items-center gap-1.5 font-semibold text-slate-700">
                            <i class="inline-block w-3 h-3 rounded-sm" :style="'background:' + reasonColor(rName)"></i>
                            <span x-text="rName"></span>
                        </span>
                    </template>
                </div>

                {{-- Grafik Batang Vertikal per Problem Reason --}}
                <div class="border border-slate-200 rounded bg-white p-4 overflow-x-auto">
                    <div class="min-w-[680px]">
                        <div class="relative h-[220px] border-b border-l border-slate-300 ml-10 mr-2 flex items-end justify-around gap-4 px-3 pt-6">
                            <template x-for="g in [0.5, 1]" :key="'rg-' + g">
                                <div class="absolute left-0 right-0 border-t border-dashed border-slate-200 pointer-events-none"
                                     :style="'bottom:' + (g * 175) + 'px'">
                                    <span class="num text-[10.5px] text-slate-400 absolute -left-10 -top-2 w-8 text-right"
                                          x-text="Math.round(maxReasonMonthCount * g)"></span>
                                </div>
                            </template>
                            <span class="num text-[10.5px] text-slate-400 absolute -left-10 -bottom-2 w-8 text-right">0</span>

                            {{-- Kelompok 6 Problem Reason dengan Warna Masing-Masing --}}
                            <template x-for="rGrp in reasonChartGroups" :key="rGrp.reason">
                                <div class="flex flex-col items-center justify-end h-[175px] flex-1">
                                    <div class="flex items-end justify-center gap-1.5 h-full px-1">
                                        <template x-for="mBar in rGrp.months" :key="mBar.month_key">
                                            <div class="flex flex-col items-center justify-end h-full w-8 group">
                                                <span class="num text-[11px] font-bold mb-1"
                                                      :style="'color:' + reasonColor(rGrp.reason)"
                                                      x-text="mBar.count + 'x'"></span>
                                                <div class="w-6 rounded-t transition-all duration-200 group-hover:brightness-110"
                                                     :style="'height:' + Math.max(6, Math.round((mBar.count / maxReasonMonthCount) * 150)) + 'px; background:' + reasonColor(rGrp.reason) + ';'"
                                                     :title="rGrp.reason + ' (' + mBar.month_label + '): ' + mBar.count + ' kali'"></div>
                                                <span class="num text-[9.5px] font-semibold text-slate-600 mt-1 whitespace-nowrap" x-text="mBar.month_short"></span>
                                            </div>
                                        </template>
                                        <template x-if="rGrp.months.length === 0">
                                            <div class="text-[10.5px] text-slate-400 pb-4">0 kasus</div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Label Problem Reason di Bawah Kelompok Batang --}}
                        <div class="ml-10 mr-2 flex items-start justify-around gap-4 px-3 pt-2.5">
                            <template x-for="rGrp in reasonChartGroups" :key="'rlbl-' + rGrp.reason">
                                <div class="flex-1 text-center">
                                    <span class="pill text-white"
                                          :style="'background:' + reasonColor(rGrp.reason)"
                                          x-text="rGrp.reason"></span>
                                    <div class="mt-1">
                                        <span class="count-chip" x-text="rGrp.total + ' KALI'"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- List Problem Reason & Frekuensinya --}}
                <div>
                    <div class="sec-h flex items-center gap-2">
                        <span>Rincian Frekuensi Problem Reason &amp; Daftar Part Terdampak</span>
                        <span class="count-chip"><span x-text="reasonSummaryRows.length"></span><span>BARIS RINGKASAN</span></span>
                    </div>
                    <div class="tbl-wrap" style="max-height: 240px;">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th class="w-[6%] text-center">No</th>
                                    <th class="w-[16%] text-center">Problem Reason</th>
                                    <th class="w-[16%]">Bulan</th>
                                    <th class="w-[34%]">Part No &amp; Part Name</th>
                                    <th class="w-[16%]">Perusahaan</th>
                                    <th class="num w-[12%]">Frekuensi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(r, idx) in reasonSummaryRows" :key="r.key">
                                    <tr>
                                        <td class="text-center num text-slate-500 font-semibold" x-text="idx + 1"></td>
                                        <td class="text-center">
                                            <span class="pill text-white"
                                                  :style="'background:' + reasonColor(r.reason)"
                                                  x-text="r.reason"></span>
                                        </td>
                                        <td class="font-semibold text-xs text-slate-800" x-text="r.month_label"></td>
                                        <td>
                                            <div class="num text-xs font-bold text-accent" x-text="r.part_no"></div>
                                            <div class="font-semibold text-slate-900" x-text="r.part_name"></div>
                                        </td>
                                        <td>
                                            <span class="font-bold text-xs text-accent" x-text="r.company_code"></span>
                                        </td>
                                        <td class="num">
                                            <span class="pill pill-warn num" x-text="r.count + ' KALI'"></span>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="reasonSummaryRows.length === 0">
                                    <td colspan="6"><div class="empty">Tidak ada data problem delay pada filter ini.</div></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <x-material-info-modal />

    <script>
        function morningPriorityTable(items, companies) {
            return {
                items: items,
                companies: companies,
                q: '',
                fGroup: 'TARGET_PROD',
                fCompany: '',

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
                statusRank(st) {
                    if (st === 'EMPTY STOCK') return 1;
                    if (st === 'VERY LOW') return 2;
                    if (st === 'LOW STOCK') return 3;
                    return 99;
                },
                get priorityRows() {
                    const qLow = this.q.trim().toLowerCase();

                    return this.items
                        .filter(it => {
                            if (!['EMPTY STOCK', 'VERY LOW', 'LOW STOCK'].includes(it.status_07)) return false;

                            if (this.fGroup === 'TARGET_PROD') {
                                if (!['FG', 'FG/KOMPONEN'].includes(it.type)) return false;
                            } else if (this.fGroup === 'MATERIAL') {
                                if (!['RM', 'KOMPONEN', 'FG/KOMPONEN'].includes(it.type)) return false;
                            } else if (this.fGroup !== 'ALL') {
                                if (it.type !== this.fGroup) return false;
                            }

                            if (this.fCompany && String(it.company_id) !== String(this.fCompany)) return false;

                            if (qLow) {
                                const hit = (it.part_no + ' ' + it.part_name + ' ' + it.company_code).toLowerCase().includes(qLow);
                                if (!hit) return false;
                            }
                            return true;
                        })
                        .sort((a, b) => {
                            const rA = this.statusRank(a.status_07);
                            const rB = this.statusRank(b.status_07);
                            if (rA !== rB) return rA - rB;

                            if (rA === 1) {
                                return (Number(b.std_day_1) || 0) - (Number(a.std_day_1) || 0);
                            }

                            if (Number(a.stock_07) !== Number(b.stock_07)) {
                                return Number(a.stock_07) - Number(b.stock_07);
                            }
                            return Number(a.ratio_07) - Number(b.ratio_07);
                        });
                }
            };
        }

        function dashboardStockVerticalChart(items, sharedMaterials, defaultCompanyId) {
            return {
                items: items,
                sharedMaterials: sharedMaterials,
                defaultCompanyId: defaultCompanyId,
                fCompany: defaultCompanyId,
                fCategory: 'FG_FGK',
                fSharedMat: '',
                allocMode: 'prop',
                hoveredItem: null,

                resetStockChart() {
                    this.fCompany = this.defaultCompanyId;
                    this.fCategory = 'FG_FGK';
                    this.fSharedMat = '';
                    this.allocMode = 'prop';
                    this.hoveredItem = null;
                },
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
                matPot(it) {
                    if (!it) return 0;
                    return this.allocMode === 'prop' ? (Number(it.pot_prop) || 0) : (Number(it.pot_max) || 0);
                },
                totalPipeline(it) {
                    if (!it) return 0;
                    return Math.round(((Number(it.current_stock) || 0) + (Number(it.wip_active) || 0) + this.matPot(it)) * 100) / 100;
                },
                pipelineRatio(it) {
                    if (!it) return 0;
                    const std1 = Number(it.std_day_1) || 0;
                    if (std1 <= 0) return 0;
                    return (this.totalPipeline(it) / std1).toFixed(2);
                },
                get chartItems() {
                    return this.items
                        .filter(it => {
                            if (this.fSharedMat) {
                                if (!it.input_ids || !it.input_ids.map(String).includes(String(this.fSharedMat))) return false;
                            } else if (this.fCompany) {
                                if (String(it.company_id) !== String(this.fCompany)) return false;
                            }

                            if (this.fCategory === 'FG_FGK') {
                                if (!['FG', 'FG/KOMPONEN'].includes(it.type)) return false;
                            } else if (this.fCategory === 'KOMP_RM') {
                                if (!['KOMPONEN', 'RM'].includes(it.type)) return false;
                            } else if (this.fCategory !== 'ALL') {
                                if (it.type !== this.fCategory) return false;
                            }

                            return true;
                        })
                        .sort((a, b) => {
                            if (Number(a.current_stock) !== Number(b.current_stock)) {
                                return Number(a.current_stock) - Number(b.current_stock);
                            }
                            return this.totalPipeline(a) - this.totalPipeline(b);
                        });
                },
                get yMax() {
                    if (this.chartItems.length === 0) return 100;
                    let m = 10;
                    this.chartItems.forEach(it => {
                        const tot = this.totalPipeline(it);
                        const s1 = Number(it.std_day_1) || 0;
                        if (tot > m) m = tot;
                        if (s1 > m) m = s1;
                    });
                    return Math.ceil(m * 1.15);
                },
                barPx(val) {
                    const v = Number(val) || 0;
                    if (v <= 0) return 0;
                    return Math.min(205, Math.round((v / this.yMax) * 205));
                }
            };
        }

        function dashboardDelayVerticalCharts(delays, companies, reasons, defaultYear) {
            const MONTH_COLORS = {
                1: '#2563EB',  // Jan: Biru
                2: '#0891B2',  // Feb: Cyan
                3: '#0D9488',  // Mar: Teal
                4: '#16A34A',  // Apr: Hijau
                5: '#65A30D',  // Mei: Lime
                6: '#D97706',  // Jun: Amber/Oranye
                7: '#0B5CAD',  // Jul: Biru Navy Utama
                8: '#7C3AED',  // Agu: Ungu
                9: '#DB2777',  // Sep: Magenta
                10: '#EA580C', // Okt: Oranye Tua
                11: '#C4473C', // Nov: Merah
                12: '#334155', // Des: Slate
            };

            const REASON_COLORS = {
                'MATERIAL':   '#0B5CAD', // Biru
                'PROSES':     '#D97706', // Oranye Amber
                'QC':         '#C4473C', // Merah
                'DIES':       '#6D28D9', // Ungu
                'BOX/PALET':  '#0D9488', // Hijau Teal
                'PLANT 2':    '#334155', // Navy Slate
            };

            return {
                delays: delays,
                companies: companies,
                reasons: reasons,
                selectedYear: defaultYear || '',
                selectedCompanyId: '',
                selectedMonths: [],

                tyClass(t) {
                    return 'ty-' + (t === 'FG/KOMPONEN' ? 'FGK' : t);
                },
                monthColor(mNum) {
                    return MONTH_COLORS[Number(mNum)] || '#0B5CAD';
                },
                reasonColor(rName) {
                    return REASON_COLORS[rName] || '#A82A20';
                },
                toggleMonth(mNum) {
                    const idx = this.selectedMonths.indexOf(mNum);
                    if (idx === -1) {
                        this.selectedMonths.push(mNum);
                    } else {
                        this.selectedMonths.splice(idx, 1);
                    }
                },
                resetDelayFilter() {
                    this.selectedYear = defaultYear || '';
                    this.selectedCompanyId = '';
                    this.selectedMonths = [];
                },
                get filteredDelays() {
                    return this.delays.filter(d => {
                        if (this.selectedYear && String(d.year) !== String(this.selectedYear)) return false;
                        if (this.selectedCompanyId && String(d.company_id) !== String(this.selectedCompanyId)) return false;
                        if (this.selectedMonths.length > 0 && !this.selectedMonths.includes(Number(d.month_num))) return false;
                        return true;
                    });
                },
                get activeMonthsLegend() {
                    const map = {};
                    this.filteredDelays.forEach(d => {
                        if (!map[d.month_num]) {
                            map[d.month_num] = {
                                month_num: Number(d.month_num),
                                label: d.month_short
                            };
                        }
                    });
                    return Object.values(map).sort((a, b) => a.month_num - b.month_num);
                },
                get companyChartGroups() {
                    const byComp = {};
                    this.filteredDelays.forEach(d => {
                        const cid = d.company_id;
                        if (!byComp[cid]) {
                            byComp[cid] = {
                                company_id: cid,
                                company_code: d.company_code,
                                company_name: d.company_name,
                                company_type: d.company_type,
                                total: 0,
                                monthsMap: {}
                            };
                        }
                        byComp[cid].total++;
                        const mk = d.month_key;
                        if (!byComp[cid].monthsMap[mk]) {
                            byComp[cid].monthsMap[mk] = {
                                month_key: mk,
                                month_num: d.month_num,
                                month_short: d.month_short,
                                month_label: d.month_label,
                                count: 0
                            };
                        }
                        byComp[cid].monthsMap[mk].count++;
                    });

                    return Object.values(byComp).map(g => ({
                        ...g,
                        months: Object.values(g.monthsMap).sort((a, b) => a.month_key.localeCompare(b.month_key))
                    }));
                },
                get maxCompanyMonthCount() {
                    let m = 1;
                    this.companyChartGroups.forEach(g => {
                        g.months.forEach(mb => {
                            if (mb.count > m) m = mb.count;
                        });
                    });
                    return Math.ceil(m * 1.15);
                },
                get companyPartSummary() {
                    const map = {};
                    this.filteredDelays.forEach(d => {
                        const k = d.company_id + '_' + d.item_id;
                        if (!map[k]) {
                            map[k] = {
                                key: k,
                                company_code: d.company_code,
                                company_name: d.company_name,
                                part_no: d.part_no,
                                part_name: d.part_name,
                                item_type: d.item_type,
                                monthsSet: new Set(),
                                count: 0
                            };
                        }
                        map[k].count++;
                        map[k].monthsSet.add(d.month_short);
                    });

                    return Object.values(map)
                        .map(r => ({
                            ...r,
                            months_list: Array.from(r.monthsSet).join(', ')
                        }))
                        .sort((a, b) => b.count - a.count);
                },
                get reasonChartGroups() {
                    return this.reasons.map(reasonName => {
                        const rows = this.filteredDelays.filter(d => d.problem_reason === reasonName);
                        const monthsMap = {};
                        rows.forEach(r => {
                            const mk = r.month_key;
                            if (!monthsMap[mk]) {
                                monthsMap[mk] = {
                                    month_key: mk,
                                    month_num: r.month_num,
                                    month_short: r.month_short,
                                    month_label: r.month_label,
                                    count: 0
                                };
                            }
                            monthsMap[mk].count++;
                        });

                        return {
                            reason: reasonName,
                            total: rows.length,
                            months: Object.values(monthsMap).sort((a, b) => a.month_key.localeCompare(b.month_key))
                        };
                    });
                },
                get maxReasonMonthCount() {
                    let m = 1;
                    this.reasonChartGroups.forEach(rg => {
                        rg.months.forEach(mb => {
                            if (mb.count > m) m = mb.count;
                        });
                    });
                    return Math.ceil(m * 1.15);
                },
                get reasonSummaryRows() {
                    const map = {};
                    this.filteredDelays.forEach(d => {
                        const k = d.problem_reason + '_' + d.month_key + '_' + d.item_id + '_' + d.company_id;
                        if (!map[k]) {
                            map[k] = {
                                key: k,
                                month_key: d.month_key,
                                month_label: d.month_label,
                                reason: d.problem_reason,
                                part_no: d.part_no,
                                part_name: d.part_name,
                                company_code: d.company_code,
                                count: 0
                            };
                        }
                        map[k].count++;
                    });

                    return Object.values(map).sort((a, b) => {
                        if (b.month_key !== a.month_key) return b.month_key.localeCompare(a.month_key);
                        return b.count - a.count;
                    });
                }
            };
        }
    </script>
</x-app-layout>