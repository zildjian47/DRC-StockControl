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

        // Hitung mutasi setelah pukul 07:00 pagi hari ini untuk mendapatkan posisi stock jam 07:00
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

        // Daftar material pembentuk untuk filter pada Detail Stock
        $allBomsFlat = \App\Models\Bom::with(['inputItem.inventoryStock', 'outputItem'])->get();
        $sharedMaterialsList = [];

        foreach ($allBomsFlat->groupBy('input_item_id') as $inId => $bRows) {
            $uniqueOuts = $bRows->pluck('output_item_id')->unique();
            if ($bRows->first() && $bRows->first()->inputItem) {
                $inItm = $bRows->first()->inputItem;
                $sharedMaterialsList[] = [
                    'id'         => $inItm->id,
                    'label'      => ($inItm->part_no ? $inItm->part_no . ' — ' : '') . $inItm->part_name,
                    'output_ids' => $uniqueOuts->values()->all(),
                ];
            }
        }

        $dashboardItems = $allActiveItems->map(function ($itm) use ($wipByTargetItem, $matInMachineByItem, $netAfter07) {
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
            $wipActive = $itm->type === 'RM' ? $matInMach : round($wipTarget, 2);

            $outBoms = $itm->bomsAsOutput;
            $hasBomInputs = $outBoms->isNotEmpty();
            $potMax = 0.0;
            $hasSharedInput = false;
            $materialsDetail = [];
            $bottleneckFormula = '';

            if ($hasBomInputs) {
                $byGroupMax = [];

                foreach ($outBoms->groupBy(fn ($b) => $b->bom_group ?: ('SG-' . $b->id)) as $gKey => $gRows) {
                    $minMax = null;
                    $minInputMeta = null;

                    foreach ($gRows as $bRow) {
                        $inItm = $bRow->inputItem;
                        $usage = (float) $bRow->qty_usage;
                        if (!$inItm || $usage <= 0) continue;

                        $inStock = $inItm->inventoryStock ? (float) $inItm->inventoryStock->current_stock : 0.0;
                        $usedCount = $inItm->bomsAsInput ? $inItm->bomsAsInput->pluck('output_item_id')->unique()->count() : 1;
                        if ($usedCount > 1) {
                            $hasSharedInput = true;
                        }

                        $canMakeMax = floor($inStock / $usage);
                        if ($minMax === null || $canMakeMax < $minMax) {
                            $minMax = $canMakeMax;
                            $minInputMeta = [
                                'stock' => floatval($inStock),
                                'uom'   => $inItm->uom,
                                'usage' => $bRow->formatted_qty_usage,
                                'make'  => (int) $canMakeMax,
                            ];
                        }

                        $materialsDetail[] = [
                            'name'       => ($inItm->part_no ? $inItm->part_no . ' — ' : '') . $inItm->part_name,
                            'stock'      => floatval($inStock),
                            'uom'        => $inItm->uom,
                            'usage'      => $bRow->formatted_qty_usage,
                            'make_max'   => (int) $canMakeMax,
                            'is_shared'  => $usedCount > 1,
                            'used_count' => $usedCount,
                        ];
                    }

                    if ($minMax !== null) {
                        $byGroupMax[] = [
                            'val'  => $minMax,
                            'meta' => $minInputMeta,
                        ];
                    }
                }

                if (!empty($byGroupMax)) {
                    usort($byGroupMax, fn($a, $b) => $b['val'] <=> $a['val']);
                    $bestGrp = $byGroupMax[0];
                    $potMax = round($bestGrp['val'], 0);
                    if (!empty($bestGrp['meta'])) {
                        $m = $bestGrp['meta'];
                        $bottleneckFormula = $m['stock'] . ' ' . $m['uom'] . ' ÷ ' . $m['usage'] . ' = ' . $m['make'] . ' ' . $itm->uom;
                    }
                }
            }

            $inputIds = $outBoms->pluck('input_item_id')->unique()->values()->all();

            return [
                'id'                 => $itm->id,
                'part_no'            => $itm->part_no ?: '-',
                'part_name'          => $itm->part_name,
                'type'               => $itm->type,
                'uom'                => $itm->uom,
                'company_id'         => $itm->company_id,
                'company_code'       => $itm->company->company_code ?? 'INTERNAL',
                'company_name'       => $itm->company->company_name ?? 'PT. Dian Raya Cipta',
                'company_type'       => $itm->company->type_company ?? 'INTERNAL',
                'current_stock'      => $currStock,
                'stock_07'           => $stock07,
                'status_07'          => $status07,
                'ratio_07'           => $ratio07,
                'wip_active'         => $wipActive,
                'has_bom_inputs'     => $hasBomInputs,
                'pot_max'            => $potMax,
                'bottleneck_formula' => $bottleneckFormula,
                'has_shared_input'   => $hasSharedInput,
                'input_ids'          => $inputIds,
                'materials_detail'   => $materialsDetail,
                'std_day_1'          => $std1,
                'std_day_2'          => $std2,
                'std_day_3'          => $std3,
                'ratio_stock'        => $ratioFg,
                'status_stock'       => $statusNow,
                'info_payload'       => $itm->material_info_payload,
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
            <div class="kpi-l">Stock Tersedia</div>
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

    {{-- TAB HALAMAN --}}
    <div x-data="{
            tab: ['ringkasan', 'stok', 'delay'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'ringkasan',
            setTab(t) { this.tab = t; history.replaceState(null, '', '#' + t); }
         }"
         @hashchange.window="if (['ringkasan', 'stok', 'delay'].includes(location.hash.slice(1))) tab = location.hash.slice(1)"
         class="space-y-[18px]">

        <div class="ptabs" role="tablist" aria-label="Bagian dashboard">
            <button type="button" role="tab" id="tab-ringkasan" aria-controls="pane-ringkasan"
                    :aria-selected="tab === 'ringkasan'" @click="setTab('ringkasan')">
                Ringkasan
            </button>
            <button type="button" role="tab" id="tab-stok" aria-controls="pane-stok"
                    :aria-selected="tab === 'stok'" @click="setTab('stok')">
                Stock
                @if($veryLowItems > 0)<span class="ptab-n num">{{ $veryLowItems }}</span>@endif
            </button>
            <button type="button" role="tab" id="tab-delay" aria-controls="pane-delay"
                    :aria-selected="tab === 'delay'" @click="setTab('delay')">
                Delay Delivery
                @if($delayCount > 0)<span class="ptab-n num">{{ $delayCount }}</span>@endif
            </button>
        </div>

        {{-- TAB 1: RINGKASAN --}}
        <div id="pane-ringkasan" role="tabpanel" aria-labelledby="tab-ringkasan"
             x-show="tab === 'ringkasan'" class="space-y-[18px]">

            {{-- 2. OUTPUT 7 HARI TERAKHIR & PERLU TINDAKAN --}}
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-[18px] items-stretch">
                <section class="panel xl:col-span-2 flex flex-col">
                    <div class="panel-head" style="position: static;">
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
                                    <rect x="{{ $x }}" y="{{ $H - $B - $h }}" width="{{ $bw }}" height="{{ $h }}" rx="2" fill="var(--c1)" @if($i === 6) opacity=".6" @endif>
                                        <title>{{ $s['label'] }}: {{ number_format($s['v'], 0, ',', '.') }}</title>
                                    </rect>
                                    <text x="{{ $x + $bw / 2 }}" y="{{ $H - $B - $h - 5 }}" text-anchor="middle" fill="var(--ink2)" font-size="11" font-weight="600" font-family="Inter, sans-serif">{{ number_format($s['v'], 0, ',', '.') }}</text>
                                @else
                                    <text x="{{ $x + $bw / 2 }}" y="{{ $H - $B - 6 }}" text-anchor="middle" fill="var(--mut)" font-size="10.5" font-family="Inter, sans-serif">0</text>
                                @endif
                                <text x="{{ $x + $bw / 2 }}" y="{{ $H - 8 }}" text-anchor="middle" fill="var(--mut)" font-size="11" font-family="Inter, sans-serif">{{ $s['label'] }}</text>
                            @endforeach
                        </svg>
                        <p class="hint mt-2">Batang paling kanan adalah akumulasi output hari ini dan masih berjalan.</p>
                    </div>
                </section>

                <section class="panel flex flex-col">
                    <div class="panel-head" style="position: static;">
                        <h3 class="panel-title">Perlu tindakan</h3>
                    </div>
                    <div class="panel-body flex-1 flex flex-col justify-between gap-4">
                        <div class="divide-y divide-slate-200 border-b border-slate-200 text-[13px]">
                            <div class="flex items-center justify-between py-3.5 first:pt-1">
                                <div>
                                    <div class="font-semibold text-slate-900">Stock Kritis</div>
                                    <div class="text-xs text-slate-500 mt-0.5">Ketahanan stock kurang dari 1 hari</div>
                                </div>
                                <span class="pill {{ $veryLowItems > 0 ? 'pill-crit' : 'pill-good' }} num">{{ $veryLowItems }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3.5">
                                <div>
                                    <div class="font-semibold text-slate-900">Total Item Stock Rendah</div>
                                    <div class="text-xs text-slate-500 mt-0.5">Status Low Stock di bawah Std Day 2</div>
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
                <div class="panel-head" style="position: static;">
                    <div class="panel-title">
                        <span>Lot Sedang Berjalan</span>
                        <span class="count-chip"><span>{{ $activeLots->count() }}</span><span>LOT</span></span>
                    </div>
                    <a href="{{ route('lot_tracers.index') }}" class="btn btn-sm btn-primary">
                        <x-icon name="route" />
                        <span>Buka Lot Tracer</span>
                    </a>
                </div>
                <div class="tbl-wrap tbl-no-freeze">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th class="w-[12%]">Lot</th>
                                <th class="w-[30%]">Produk</th>
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
                                    $uOut = $lot->targetItem->uom ?? 'PCS';
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
                                        <b class="text-emerald-700">{{ floatval($lot->act_fg) }} {{ $uOut }}</b> / <b class="text-amber-700">WIP {{ floatval($lot->qty_wip) }} {{ $uOut }}</b>
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
        </div>

        {{-- TAB 2: STOCK --}}
        <div id="pane-stok" role="tabpanel" aria-labelledby="tab-stok"
             x-show="tab === 'stok'" x-cloak class="space-y-[18px]">

            {{-- 4. PRIORITAS STOCK (10 BARIS DATA & HEADER TIDAK DI-FREEZE) --}}
            <section class="panel"
                     x-data="morningPriorityTable({{ Js::from($dashboardItems) }}, {{ Js::from($allCompaniesList) }})">
                <div class="panel-head" style="position: static;">
                    <div class="panel-title">
                        <span>Prioritas Stock</span>
                        <span class="count-chip"><span x-text="priorityRows.length"></span><span>ITEM</span></span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" x-model="q"
                               placeholder="Cari Part No / Part Name..."
                               class="h-8 text-xs px-2.5 w-44">

                        <select x-model="fGroup" class="h-8 text-xs py-0 pl-2 pr-7">
                            <option value="TARGET_PROD">FG + FG/KOMPONEN</option>
                            <option value="MATERIAL">Material (RM + KOMPONEN + FG/KOMPONEN)</option>
                            <option value="FG">FG</option>
                            <option value="FG/KOMPONEN">FG/KOMPONEN</option>
                            <option value="KOMPONEN">KOMPONEN</option>
                            <option value="RM">RM</option>
                            <option value="TRANSIT">TRANSIT</option>
                            <option value="ALL">Semua Tipe</option>
                        </select>

                        <select x-model="fCompany" class="h-8 text-xs py-0 pl-2 pr-7">
                            <option value="">Semua Perusahaan</option>
                            @foreach($allCompaniesList as $c)
                                <option value="{{ $c['id'] }}">{{ $c['code'] }} — {{ $c['name'] }}</option>
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

                <div class="tbl-wrap tbl-no-freeze" style="max-height: 540px;">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th class="w-[6%] text-center">Prioritas</th>
                                <th class="w-[27%]">Part No &amp; Part Name</th>
                                <th class="w-[10%] text-center">Tipe</th>
                                <th class="w-[13%]">Perusahaan</th>
                                <th class="num w-[11%]">Std 1 / Std 2</th>
                                <th class="num w-[10%]">Stock 07:00</th>
                                <th class="num w-[10%]">Stock Saat Ini</th>
                                <th class="num w-[6%]">WIP</th>
                                <th class="w-[7%] text-center">Status 07:00</th>
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
                                            <button type="button" class="act-btn flex-none" title="Informasi Item"
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
                                        <div class="text-[10.5px] text-slate-400" x-text="row.ratio_07 + ' Hr'"></div>
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

            {{-- 5. DETAIL STOCK (FG, FG/KOMPONEN, & KOMPONEN BERUNTUN YANG MEMILIKI BOM) --}}
            <section class="panel"
                     x-data="dashboardStockVerticalChart({{ Js::from($dashboardItems) }}, {{ Js::from($sharedMaterialsList) }}, '{{ $defaultStockCompanyId }}')">
                <div class="panel-head" style="position: static;">
                    <div class="panel-title">
                        <span>Detail Stock</span>
                        <span class="count-chip"><span x-text="chartItems.length"></span><span>ITEM</span></span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <select x-model="fCompany" @change="syncHovered()" class="h-8 text-xs py-0 pl-2 pr-7" title="Filter Perusahaan">
                            <option value="">Semua Perusahaan</option>
                            @foreach($allCompaniesList as $c)
                                @if(in_array($c['type'], ['CUSTOMER', 'INTERNAL'], true))
                                    <option value="{{ $c['id'] }}">{{ $c['code'] }} — {{ $c['name'] }}</option>
                                @endif
                            @endforeach
                        </select>

                        <select x-model="fCategory" @change="onCategoryChange()" class="h-8 text-xs py-0 pl-2 pr-7" title="Filter Tipe Item">
                            <option value="FG_FGK">FG + FG/KOMPONEN</option>
                            <option value="FG">FG</option>
                            <option value="FG/KOMPONEN">FG/KOMPONEN</option>
                            <option value="KOMPONEN">KOMPONEN</option>
                            <option value="ALL_PROD">Semua (FG · FG/KOMPONEN · KOMPONEN)</option>
                        </select>

                        <select x-model="fSharedMat" @change="syncHovered()" class="h-8 text-xs py-0 pl-2 pr-7" title="Filter Material Pembentuk">
                            <option value="">Semua Material</option>
                            @foreach($sharedMaterialsList as $sm)
                                <option value="{{ $sm['id'] }}">{{ $sm['label'] }}</option>
                            @endforeach
                        </select>

                        <button type="button"
                                x-show="fCompany !== '{{ $defaultStockCompanyId }}' || fCategory !== 'FG_FGK' || fSharedMat !== ''" x-cloak
                                @click="resetStockChart()"
                                class="btn btn-sm">
                            Reset
                        </button>
                    </div>
                </div>

                <div class="panel-body space-y-3">
                    {{-- Legenda Grafik Bersih --}}
                    <div class="flex flex-wrap items-center gap-5 px-3 py-2 rounded border border-slate-200 bg-slate-50 text-xs">
                        <span class="inline-flex items-center gap-1.5 font-semibold text-slate-700">
                            <i class="inline-block w-3 h-3 rounded-sm" style="background: #0B5CAD"></i>
                            Stock FG
                        </span>
                        <span class="inline-flex items-center gap-1.5 font-semibold text-slate-700">
                            <i class="inline-block w-3 h-3 rounded-sm" style="background: #F59E0B"></i>
                            WIP
                        </span>
                        <span class="inline-flex items-center gap-1.5 font-semibold text-slate-700">
                            <i class="inline-block w-3 h-3 rounded-sm" style="background: #94A3B8"></i>
                            Material
                        </span>
                    </div>

                    {{-- Panel Ringkasan Saat Batang Di-hover (Beserta Ratio Hari & Rumus Konversi) --}}
                    <div class="rounded border px-3.5 py-2.5 transition-colors"
                         :class="hoveredItem ? 'border-[#0B5CAD] bg-[#E4EEF8]/50' : 'border-slate-200 bg-slate-50/60'">
                        <template x-if="!hoveredItem">
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>Arahkan kursor pada batang grafik untuk melihat rincian stock dan rumus konversi hari, atau klik batang untuk membuka detail item.</span>
                                <span class="pill pill-neu">PILIH BATANG</span>
                            </div>
                        </template>

                        <template x-if="hoveredItem">
                            <div class="space-y-2.5 text-xs">
                                <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-center">
                                    {{-- Identitas Part --}}
                                    <div class="lg:col-span-4 min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="ty" :class="tyClass(hoveredItem.type)" x-text="hoveredItem.type"></span>
                                            <span class="num font-bold text-accent" x-text="hoveredItem.part_no !== '-' ? hoveredItem.part_no : ''"></span>
                                            <span class="pill pill-neu" x-text="hoveredItem.company_code"></span>
                                            <span class="pill" :class="pillClass(hoveredItem.status_stock)" x-text="hoveredItem.status_stock"></span>
                                            <span class="pill pill-info" x-show="hoveredItem.has_shared_input">SHARED RM</span>
                                        </div>
                                        <div class="font-bold text-slate-900 mt-1 truncate" x-text="hoveredItem.part_name"></div>
                                        <div class="text-[11px] text-slate-600 mt-0.5 num">
                                            Std 1: <b x-text="hoveredItem.std_day_1 + ' ' + hoveredItem.uom"></b> ·
                                            Std 2: <b x-text="hoveredItem.std_day_2"></b> ·
                                            Std 3: <b x-text="hoveredItem.std_day_3"></b>
                                        </div>
                                    </div>

                                    {{-- 4 Kotak Angka (Stock FG, WIP, Material, Total) + Ratio Hari + Rumus --}}
                                    <div class="lg:col-span-8 grid grid-cols-2 sm:grid-cols-4 gap-2 text-center num">
                                        {{-- Kotak 1: Stock FG --}}
                                        <div class="p-2 rounded bg-white border border-slate-200 flex flex-col justify-between">
                                            <div class="text-[10px] font-semibold text-slate-500 uppercase">Stock FG</div>
                                            <div class="text-sm font-bold text-[#0B5CAD] mt-0.5" x-text="hoveredItem.current_stock + ' ' + hoveredItem.uom"></div>
                                            <div class="text-[11px] font-bold text-slate-800 mt-0.5" x-text="hoveredItem.ratio_stock + ' Hari'"></div>
                                            <div class="text-[10px] text-slate-500 border-t border-slate-100 pt-1 mt-1"
                                                 x-text="dayFormula(hoveredItem.current_stock, hoveredItem.std_day_1)"></div>
                                        </div>

                                        {{-- Kotak 2: WIP --}}
                                        <div class="p-2 rounded bg-white border border-slate-200 flex flex-col justify-between">
                                            <div class="text-[10px] font-semibold text-slate-500 uppercase">WIP</div>
                                            <div class="text-sm font-bold text-amber-600 mt-0.5" x-text="hoveredItem.wip_active + ' ' + hoveredItem.uom"></div>
                                            <div class="text-[11px] font-bold text-slate-800 mt-0.5" x-text="wipRatio(hoveredItem) + ' Hari'"></div>
                                            <div class="text-[10px] text-slate-500 border-t border-slate-100 pt-1 mt-1"
                                                 x-text="dayFormula(hoveredItem.wip_active, hoveredItem.std_day_1)"></div>
                                        </div>

                                        {{-- Kotak 3: Material --}}
                                        <div class="p-2 rounded bg-white border border-slate-200 flex flex-col justify-between">
                                            <div class="text-[10px] font-semibold text-slate-500 uppercase">Material</div>
                                            <div class="text-sm font-bold text-slate-700 mt-0.5" x-text="matPot(hoveredItem) + ' ' + hoveredItem.uom"></div>
                                            <div class="text-[11px] font-bold text-slate-800 mt-0.5" x-text="matRatio(hoveredItem) + ' Hari'"></div>
                                            <div class="text-[10px] text-slate-500 border-t border-slate-100 pt-1 mt-1"
                                                 x-text="dayFormula(matPot(hoveredItem), hoveredItem.std_day_1)"></div>
                                        </div>

                                        {{-- Kotak 4: Total --}}
                                        <div class="p-2 rounded bg-slate-900 text-white flex flex-col justify-between">
                                            <div class="text-[10px] font-semibold text-slate-300 uppercase">Total</div>
                                            <div class="text-sm font-bold mt-0.5" x-text="totalPipeline(hoveredItem) + ' ' + hoveredItem.uom"></div>
                                            <div class="text-[11px] font-bold text-amber-300 mt-0.5" x-text="pipelineRatio(hoveredItem) + ' Hari'"></div>
                                            <div class="text-[10px] text-slate-300 border-t border-slate-700 pt-1 mt-1"
                                                 x-text="dayFormula(totalPipeline(hoveredItem), hoveredItem.std_day_1)"></div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Rincian Rumus Konversi Material Pembentuk (Stock Bahan ÷ Qty Usage = Kapasitas PCS) --}}
                                <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-slate-200/80 text-[11px]"
                                     x-show="hoveredItem.materials_detail && hoveredItem.materials_detail.length > 0">
                                    <span class="font-semibold text-slate-600 mr-1">Konversi Material (Stock Bahan ÷ Usage):</span>
                                    <template x-for="(md, mIdx) in hoveredItem.materials_detail" :key="'md-' + mIdx">
                                        <span class="count-chip count-chip-sub"
                                              x-text="md.name + ': ' + md.stock + ' ' + md.uom + ' ÷ ' + md.usage + ' = ' + md.make_max + ' ' + hoveredItem.uom"></span>
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
                                                 x-text="'Total: ' + totalPipeline(it)"></div>
                                        </div>

                                        <div class="w-10 sm:w-11 flex flex-col justify-end rounded-t overflow-hidden border transition-transform group-hover:scale-[1.04]"
                                             :class="hoveredItem?.id === it.id ? 'border-[#0B5CAD] ring-2 ring-[#0B5CAD]/25' : 'border-slate-300/80 bg-slate-100/50'">
                                            {{-- Lapis Atas: Stock FG (Biru) --}}
                                            <div class="w-full transition-all duration-200"
                                                 x-show="it.current_stock > 0"
                                                 :style="'height:' + Math.max(3, barPx(it.current_stock)) + 'px; background: #0B5CAD;'"></div>

                                            {{-- Lapis Tengah: WIP (Kuning) --}}
                                            <div class="w-full transition-all duration-200"
                                                 x-show="it.wip_active > 0"
                                                 :style="'height:' + Math.max(3, barPx(it.wip_active)) + 'px; background: #F59E0B;'"></div>

                                            {{-- Lapis Bawah: Material (Abu-abu) --}}
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
                                            <span class="num text-[9.5px] text-slate-500" x-text="'Total: ' + pipelineRatio(it) + ' Hr'"></span>
                                            <span class="pill pill-info text-[9px] h-3.5 px-1" x-show="it.has_shared_input">SHARED RM</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div x-show="chartItems.length === 0" class="empty">
                        Tidak ada item yang sesuai dengan filter.
                    </div>
                </div>
            </section>
        </div>

        {{-- TAB 3: DELAY DELIVERY (SUSUNAN ATAS-BAWAH AGAR LELUASA HINGGA 12 BULAN) --}}
        <div id="pane-delay" role="tabpanel" aria-labelledby="tab-delay"
             x-show="tab === 'delay'" x-cloak class="space-y-[18px]">

            <div x-data="dashboardDelayVerticalCharts({{ Js::from($allDelays) }}, {{ Js::from($allCompaniesList) }}, {{ Js::from($delayReasonsList) }}, '{{ $delayYears[0] ?? date('Y') }}')"
                 class="space-y-[18px]">

                {{-- FILTER ANALITIK DELAY DELIVERY --}}
                <section class="panel">
                    <div class="panel-head" style="position: static;">
                        <div class="panel-title">
                            <span>Filter Delay Delivery</span>
                            <span class="count-chip"><span x-text="filteredDelays.length"></span><span>KASUS</span></span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <select x-model="fYear" class="h-8 text-xs py-0 pl-2 pr-7">
                                <option value="">Semua Tahun</option>
                                @foreach($delayYears as $yr)
                                    <option value="{{ $yr }}">Tahun {{ $yr }}</option>
                                @endforeach
                            </select>

                            <select x-model="fCompany" class="h-8 text-xs py-0 pl-2 pr-7">
                                <option value="">Semua Perusahaan / Customer</option>
                                @foreach($allCompaniesList as $c)
                                    <option value="{{ $c['id'] }}">{{ $c['code'] }} — {{ $c['name'] }}</option>
                                @endforeach
                            </select>

                            <button type="button" class="btn btn-sm" @click="selectAllMonths()">Semua Bulan</button>
                            <button type="button" class="btn btn-sm" @click="resetDelayFilter()">Reset</button>
                        </div>
                    </div>

                    <div class="panel-body pt-3 pb-3 border-t border-slate-100 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold text-slate-600 mr-1">Bulan:</span>
                        <template x-for="m in allMonths" :key="m.num">
                            <button type="button"
                                    @click="toggleMonth(m.num)"
                                    class="h-7 px-2.5 rounded text-xs font-semibold border transition-colors"
                                    :class="selectedMonths.includes(m.num)
                                        ? 'bg-[#0B5CAD] text-white border-[#0B5CAD]'
                                        : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50'"
                                    x-text="m.short">
                            </button>
                        </template>
                    </div>
                </section>

                {{-- SUSUNAN ATAS-BAWAH (1 KOLOM PENUH) --}}
                <div class="space-y-[18px]">
                    {{-- GRAFIK 1: PROBLEM DELAY PER PERUSAHAAN / CUSTOMER --}}
                    <section class="panel">
                        <div class="panel-head" style="position: static;">
                            <div class="panel-title">
                                <span>1. Problem delay per perusahaan / customer</span>
                                <span class="count-chip"><span x-text="companyGroups.length"></span><span>PERUSAHAAN</span></span>
                            </div>
                        </div>

                        <div class="panel-body space-y-4">
                            <div class="flex flex-wrap items-center gap-3 px-3 py-2 rounded border border-slate-200 bg-slate-50 text-xs">
                                <span class="font-semibold text-slate-600">Bulan:</span>
                                <template x-for="mLeg in activeMonthsLegend" :key="mLeg.month_num">
                                    <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                                        <i class="inline-block w-3 h-3 rounded-sm" :style="'background:' + monthColor(mLeg.month_num)"></i>
                                        <span x-text="mLeg.label"></span>
                                    </span>
                                </template>
                            </div>

                            <div class="border border-slate-200 rounded bg-white p-4 overflow-x-auto" x-show="companyGroups.length > 0">
                                <div class="min-w-[600px]">
                                    <div class="relative h-[220px] border-b border-l border-slate-300 ml-10 mr-2 flex items-end justify-around gap-6 px-4 pt-6">
                                        <template x-for="g in [0.25, 0.5, 0.75, 1]" :key="'cg-' + g">
                                            <div class="absolute left-0 right-0 border-t border-dashed border-slate-200 pointer-events-none"
                                                 :style="'bottom:' + (g * 175) + 'px'">
                                                <span class="num text-[10px] text-slate-400 absolute -left-10 -top-2 w-8 text-right"
                                                      x-text="Math.round(compYMax * g)"></span>
                                            </div>
                                        </template>
                                        <span class="num text-[10px] text-slate-400 absolute -left-10 -bottom-2 w-8 text-right">0</span>

                                        <template x-for="cGrp in companyGroups" :key="cGrp.company_id">
                                            <div class="flex flex-col items-center justify-end h-[175px] flex-1">
                                                <div class="flex items-end justify-center gap-2 h-full px-2">
                                                    <template x-for="mBar in cGrp.months" :key="cGrp.company_id + '-' + mBar.month_num">
                                                        <div class="flex flex-col items-center justify-end h-full group">
                                                            <span class="num text-[11px] font-bold mb-1 text-slate-700"
                                                                  x-text="mBar.count"></span>
                                                            <div class="w-7 rounded-t transition-all duration-200 group-hover:brightness-110"
                                                                 :style="'height:' + Math.max(4, Math.round((mBar.count / compYMax) * 155)) + 'px; background:' + monthColor(mBar.month_num) + '; box-shadow: inset 0 0 0 1px rgba(11,74,142,.25);'"
                                                                 :title="cGrp.company_code + ' (' + mBar.month_label + '): ' + mBar.count + ' kasus'">
                                                            </div>
                                                            <span class="num text-[10px] font-semibold text-slate-500 mt-1" x-text="mBar.month_short"></span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="ml-10 mr-2 flex items-start justify-around gap-6 px-4 pt-2.5">
                                        <template x-for="cGrp in companyGroups" :key="'clbl-' + cGrp.company_id">
                                            <div class="flex-1 text-center">
                                                <div class="font-bold text-xs text-accent" x-text="cGrp.company_code"></div>
                                                <div class="text-[11px] text-slate-600 truncate" x-text="cGrp.company_name"></div>
                                                <span class="count-chip mt-1"><span x-text="cGrp.total"></span><span>KASUS</span></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="tbl-wrap tbl-no-freeze" style="max-height: 280px;" x-show="partFrequencyList.length > 0">
                                <table class="tbl">
                                    <thead>
                                        <tr>
                                            <th class="w-[8%] text-center">No</th>
                                            <th class="w-[46%]">Part No &amp; Part Name</th>
                                            <th class="w-[18%]">Perusahaan</th>
                                            <th class="w-[14%] text-center">Tipe</th>
                                            <th class="num w-[14%]">Frekuensi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(pf, pIdx) in partFrequencyList" :key="pf.key">
                                            <tr>
                                                <td class="text-center num text-slate-500" x-text="pIdx + 1"></td>
                                                <td>
                                                    <div class="num text-xs font-bold text-accent" x-text="pf.part_no"></div>
                                                    <div class="font-semibold text-slate-900" x-text="pf.part_name"></div>
                                                </td>
                                                <td>
                                                    <span class="font-bold text-xs text-slate-800" x-text="pf.company_code"></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="ty" :class="tyClass(pf.item_type)" x-text="pf.item_type"></span>
                                                </td>
                                                <td class="num">
                                                    <span class="pill pill-crit num" x-text="pf.count + ' KASUS'"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <div x-show="companyGroups.length === 0" class="empty">
                                Tidak ada data delay delivery pada filter ini.
                            </div>
                        </div>
                    </section>

                    {{-- GRAFIK 2: PROBLEM DELAY BERDASARKAN PROBLEM REASON --}}
                    <section class="panel">
                        <div class="panel-head" style="position: static;">
                            <div class="panel-title">
                                <span>2. Problem delay berdasarkan problem reason</span>
                                <span class="count-chip"><span x-text="reasonGroups.length"></span><span>KATEGORI</span></span>
                            </div>
                        </div>

                        <div class="panel-body space-y-4">
                            <div class="flex flex-wrap items-center gap-3 px-3 py-2 rounded border border-slate-200 bg-slate-50 text-xs">
                                <span class="font-semibold text-slate-600">Bulan:</span>
                                <template x-for="mLeg in activeMonthsLegend" :key="'r-' + mLeg.month_num">
                                    <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                                        <i class="inline-block w-3 h-3 rounded-sm" :style="'background:' + monthColor(mLeg.month_num)"></i>
                                        <span x-text="mLeg.label"></span>
                                    </span>
                                </template>
                            </div>

                            <div class="border border-slate-200 rounded bg-white p-4 overflow-x-auto" x-show="reasonGroups.length > 0">
                                <div class="min-w-[600px]">
                                    <div class="relative h-[220px] border-b border-l border-slate-300 ml-10 mr-2 flex items-end justify-around gap-4 px-3 pt-6">
                                        <template x-for="g in [0.25, 0.5, 0.75, 1]" :key="'rg-' + g">
                                            <div class="absolute left-0 right-0 border-t border-dashed border-slate-200 pointer-events-none"
                                                 :style="'bottom:' + (g * 175) + 'px'">
                                                <span class="num text-[10px] text-slate-400 absolute -left-10 -top-2 w-8 text-right"
                                                      x-text="Math.round(reasonYMax * g)"></span>
                                            </div>
                                        </template>
                                        <span class="num text-[10px] text-slate-400 absolute -left-10 -bottom-2 w-8 text-right">0</span>

                                        <template x-for="rGrp in reasonGroups" :key="rGrp.reason">
                                            <div class="flex flex-col items-center justify-end h-[175px] flex-1">
                                                <div class="flex items-end justify-center gap-1.5 h-full px-1">
                                                    <template x-for="mBar in rGrp.months" :key="rGrp.reason + '-' + mBar.month_num">
                                                        <div class="flex flex-col items-center justify-end h-full group">
                                                            <span class="num text-[11px] font-bold mb-1 text-slate-700"
                                                                  x-text="mBar.count"></span>
                                                            <div class="w-6 rounded-t transition-all duration-200 group-hover:brightness-110"
                                                                 :style="'height:' + Math.max(4, Math.round((mBar.count / reasonYMax) * 155)) + 'px; background:' + monthColor(mBar.month_num) + '; box-shadow: inset 0 0 0 1px rgba(11,74,142,.25);'"
                                                                 :title="rGrp.reason + ' (' + mBar.month_label + '): ' + mBar.count + ' kasus'">
                                                            </div>
                                                            <span class="num text-[9.5px] font-semibold text-slate-500 mt-1" x-text="mBar.month_short"></span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="ml-10 mr-2 flex items-start justify-around gap-4 px-3 pt-2.5">
                                        <template x-for="rGrp in reasonGroups" :key="'rlbl-' + rGrp.reason">
                                            <div class="flex-1 text-center">
                                                <span class="pill pill-neu" x-text="rGrp.reason"></span>
                                                <div class="mt-1">
                                                    <span class="count-chip"><span x-text="rGrp.total"></span><span>KASUS</span></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="tbl-wrap tbl-no-freeze" style="max-height: 280px;" x-show="reasonSummaryList.length > 0">
                                <table class="tbl">
                                    <thead>
                                        <tr>
                                            <th class="w-[8%] text-center">No</th>
                                            <th class="w-[24%]">Problem Reason</th>
                                            <th class="w-[52%]">Rincian Item Terdampak</th>
                                            <th class="num w-[16%]">Frekuensi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(r, rIdx) in reasonSummaryList" :key="r.reason">
                                            <tr>
                                                <td class="text-center num text-slate-500" x-text="rIdx + 1"></td>
                                                <td>
                                                    <span class="pill pill-neu" x-text="r.reason"></span>
                                                </td>
                                                <td>
                                                    <div class="flex flex-wrap gap-1.5">
                                                        <template x-for="it in r.items" :key="r.reason + '-' + it.part_name">
                                                            <span class="count-chip count-chip-sub"
                                                                  x-text="(it.part_no !== '-' ? it.part_no + ' ' : '') + it.part_name + ': ' + it.count + 'x'"></span>
                                                        </template>
                                                    </div>
                                                </td>
                                                <td class="num">
                                                    <span class="pill pill-crit num" x-text="r.total + ' KASUS'"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <div x-show="reasonGroups.length === 0" class="empty">
                                Tidak ada data problem reason pada filter ini.
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <x-material-info-modal />

    <script>
        function morningPriorityTable(items, companies) {
            return {
                items: items || [],
                companies: companies || [],
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
                statusRank(status) {
                    if (status === 'EMPTY STOCK') return 1;
                    if (status === 'VERY LOW') return 2;
                    if (status === 'LOW STOCK') return 3;
                    return 99;
                },
                get priorityRows() {
                    const search = this.q.trim().toLowerCase();
                    return this.items
                        .filter(it => {
                            if (!['EMPTY STOCK', 'VERY LOW', 'LOW STOCK'].includes(it.status_07)) {
                                return false;
                            }
                            if (this.fGroup === 'TARGET_PROD' && !['FG', 'FG/KOMPONEN'].includes(it.type)) {
                                return false;
                            } else if (this.fGroup === 'MATERIAL' && !['RM', 'KOMPONEN', 'FG/KOMPONEN'].includes(it.type)) {
                                return false;
                            } else if (!['ALL', 'TARGET_PROD', 'MATERIAL'].includes(this.fGroup) && it.type !== this.fGroup) {
                                return false;
                            }
                            if (this.fCompany !== '' && String(it.company_id) !== String(this.fCompany)) {
                                return false;
                            }
                            if (search !== '') {
                                const hay = (it.part_no + ' ' + it.part_name + ' ' + it.company_code).toLowerCase();
                                if (!hay.includes(search)) return false;
                            }
                            return true;
                        })
                        .sort((a, b) => {
                            const rA = this.statusRank(a.status_07);
                            const rB = this.statusRank(b.status_07);
                            if (rA !== rB) return rA - rB;
                            if (a.stock_07 !== b.stock_07) return a.stock_07 - b.stock_07;
                            if (a.ratio_07 !== b.ratio_07) return a.ratio_07 - b.ratio_07;
                            return b.std_day_1 - a.std_day_1;
                        });
                }
            };
        }

        function dashboardStockVerticalChart(items, sharedMaterials, defaultCompanyId) {
            return {
                items: items || [],
                sharedMaterials: sharedMaterials || [],
                defaultCompanyId: defaultCompanyId || '',
                fCompany: defaultCompanyId || '',
                fCategory: 'FG_FGK',
                fSharedMat: '',
                hoveredItem: null,

                init() {
                    const hasDefault = this.items.some(
                        it => ['FG', 'FG/KOMPONEN'].includes(it.type) && String(it.company_id) === String(this.fCompany)
                    );
                    if (!hasDefault) {
                        this.fCompany = '';
                    }
                    this.syncHovered();
                },
                onCategoryChange() {
                    if (this.fCompany !== '' && this.chartItems.length === 0) {
                        this.fCompany = '';
                    }
                    this.syncHovered();
                },
                syncHovered() {
                    this.$nextTick(() => {
                        if (this.chartItems.length > 0) {
                            const stillExists = this.hoveredItem && this.chartItems.some(x => x.id === this.hoveredItem.id);
                            if (!stillExists) {
                                this.hoveredItem = this.chartItems[0];
                            }
                        } else {
                            this.hoveredItem = null;
                        }
                    });
                },
                resetStockChart() {
                    this.fCompany = this.defaultCompanyId || '';
                    this.fCategory = 'FG_FGK';
                    this.fSharedMat = '';
                    this.syncHovered();
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
                    return Number(it.pot_max || 0);
                },
                wipRatio(it) {
                    if (!it || Number(it.std_day_1 || 0) <= 0) return 0;
                    return Math.round((Number(it.wip_active || 0) / Number(it.std_day_1)) * 100) / 100;
                },
                matRatio(it) {
                    if (!it || Number(it.std_day_1 || 0) <= 0) return 0;
                    return Math.round((this.matPot(it) / Number(it.std_day_1)) * 100) / 100;
                },
                totalPipeline(it) {
                    if (!it) return 0;
                    return Math.round((Number(it.current_stock || 0) + Number(it.wip_active || 0) + this.matPot(it)) * 100) / 100;
                },
                pipelineRatio(it) {
                    if (!it || Number(it.std_day_1 || 0) <= 0) return 0;
                    return Math.round((this.totalPipeline(it) / Number(it.std_day_1)) * 100) / 100;
                },
                dayFormula(qty, std1) {
                    const q = Number(qty || 0);
                    const s = Number(std1 || 0);
                    if (s <= 0) return q + ' ÷ 0 = 0 Hari';
                    const r = Math.round((q / s) * 100) / 100;
                    return q + ' ÷ ' + s + ' = ' + r + ' Hari';
                },
                isEligibleProdItem(it) {
                    if (['FG', 'FG/KOMPONEN'].includes(it.type)) return true;
                    if (it.type === 'KOMPONEN' && it.has_bom_inputs) return true;
                    return false;
                },
                get chartItems() {
                    return this.items
                        .filter(it => {
                            if (!this.isEligibleProdItem(it)) {
                                return false;
                            }
                            if (this.fSharedMat !== '') {
                                const matId = Number(this.fSharedMat);
                                if (!Array.isArray(it.input_ids) || !it.input_ids.includes(matId)) {
                                    return false;
                                }
                            } else {
                                if (this.fCompany !== '' && String(it.company_id) !== String(this.fCompany)) {
                                    return false;
                                }
                                if (this.fCategory === 'FG_FGK' && !['FG', 'FG/KOMPONEN'].includes(it.type)) {
                                    return false;
                                } else if (this.fCategory === 'KOMPONEN' && !(it.type === 'KOMPONEN' && it.has_bom_inputs)) {
                                    return false;
                                } else if (!['FG_FGK', 'KOMPONEN', 'ALL_PROD'].includes(this.fCategory) && it.type !== this.fCategory) {
                                    return false;
                                }
                            }
                            return true;
                        })
                        .sort((a, b) => {
                            if (a.current_stock !== b.current_stock) {
                                return a.current_stock - b.current_stock;
                            }
                            return a.std_day_1 - b.std_day_1;
                        });
                },
                get yMax() {
                    if (this.chartItems.length === 0) return 100;
                    let m = 10;
                    this.chartItems.forEach(it => {
                        const tot = this.totalPipeline(it);
                        if (tot > m) m = tot;
                    });
                    return Math.ceil(m * 1.15);
                },
                barPx(val) {
                    const v = Number(val || 0);
                    if (v <= 0 || this.yMax <= 0) return 0;
                    return Math.min(205, Math.round((v / this.yMax) * 205));
                }
            };
        }

        function dashboardDelayVerticalCharts(delays, companies, reasons, defaultYear) {
            const ALL_MONTHS = [
                { num: 1, short: 'Jan', full: 'Januari' },
                { num: 2, short: 'Feb', full: 'Februari' },
                { num: 3, short: 'Mar', full: 'Maret' },
                { num: 4, short: 'Apr', full: 'April' },
                { num: 5, short: 'Mei', full: 'Mei' },
                { num: 6, short: 'Jun', full: 'Juni' },
                { num: 7, short: 'Jul', full: 'Juli' },
                { num: 8, short: 'Agu', full: 'Agustus' },
                { num: 9, short: 'Sep', full: 'September' },
                { num: 10, short: 'Okt', full: 'Oktober' },
                { num: 11, short: 'Nov', full: 'November' },
                { num: 12, short: 'Des', full: 'Desember' }
            ];

            const REASON_COLORS = {
                'MATERIAL': '#0B5CAD',
                'PROSES': '#D97706',
                'QC': '#DC2626',
                'DIES': '#7C3AED',
                'BOX/PALET': '#0D9488',
                'PLANT 2': '#475569'
            };

            return {
                delays: delays || [],
                companies: companies || [],
                reasons: reasons || [],
                defaultYear: defaultYear || '',
                allMonths: ALL_MONTHS,
                fYear: defaultYear || '',
                fCompany: '',
                selectedMonths: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],

                toggleMonth(mNum) {
                    if (this.selectedMonths.includes(mNum)) {
                        if (this.selectedMonths.length > 1) {
                            this.selectedMonths = this.selectedMonths.filter(x => x !== mNum);
                        }
                    } else {
                        this.selectedMonths.push(mNum);
                        this.selectedMonths.sort((a, b) => a - b);
                    }
                },
                selectAllMonths() {
                    this.selectedMonths = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
                },
                resetDelayFilter() {
                    this.fYear = this.defaultYear;
                    this.fCompany = '';
                    this.selectAllMonths();
                },
                monthColor(mNum) {
                    const order = this.activeMonthsLegend.map(m => Number(m.month_num));
                    const i = order.indexOf(Number(mNum));
                    const n = order.length;
                    const t = (n <= 1 || i < 0) ? 1 : i / (n - 1);
                    const a = [188, 211, 234];
                    const b = [11, 74, 142];
                    return 'rgb(' + a.map((v, k) => Math.round(v + (b[k] - v) * t)).join(',') + ')';
                },
                reasonColor(rName) {
                    return REASON_COLORS[rName] || '#0B5CAD';
                },
                tyClass(t) {
                    return 'ty-' + (t === 'FG/KOMPONEN' ? 'FGK' : t);
                },
                get filteredDelays() {
                    return this.delays.filter(d => {
                        if (this.fYear !== '' && String(d.year) !== String(this.fYear)) return false;
                        if (this.fCompany !== '' && String(d.company_id) !== String(this.fCompany)) return false;
                        if (!this.selectedMonths.includes(Number(d.month_num))) return false;
                        return true;
                    });
                },
                get activeMonthsLegend() {
                    const map = {};
                    this.filteredDelays.forEach(d => {
                        if (!map[d.month_num]) {
                            const mObj = ALL_MONTHS.find(x => x.num === Number(d.month_num));
                            map[d.month_num] = {
                                month_num: Number(d.month_num),
                                label: mObj ? mObj.full : d.month_label
                            };
                        }
                    });
                    return Object.values(map).sort((a, b) => a.month_num - b.month_num);
                },
                get companyGroups() {
                    const map = {};
                    this.filteredDelays.forEach(d => {
                        const cid = d.company_id;
                        if (!map[cid]) {
                            map[cid] = {
                                company_id: cid,
                                company_code: d.company_code,
                                company_name: d.company_name,
                                total: 0,
                                monthMap: {}
                            };
                        }
                        map[cid].total++;
                        const mKey = d.month_num;
                        if (!map[cid].monthMap[mKey]) {
                            const mObj = ALL_MONTHS.find(x => x.num === Number(mKey));
                            map[cid].monthMap[mKey] = {
                                month_num: Number(mKey),
                                month_short: mObj ? mObj.short : d.month_short,
                                month_label: d.month_label,
                                count: 0
                            };
                        }
                        map[cid].monthMap[mKey].count++;
                    });

                    return Object.values(map)
                        .map(g => ({
                            ...g,
                            months: Object.values(g.monthMap).sort((a, b) => a.month_num - b.month_num)
                        }))
                        .sort((a, b) => b.total - a.total);
                },
                get compYMax() {
                    let m = 4;
                    this.companyGroups.forEach(cg => {
                        cg.months.forEach(mb => {
                            if (mb.count > m) m = mb.count;
                        });
                    });
                    return Math.ceil(m * 1.15);
                },
                get partFrequencyList() {
                    const map = {};
                    this.filteredDelays.forEach(d => {
                        const k = d.company_code + '|' + d.item_id;
                        if (!map[k]) {
                            map[k] = {
                                key: k,
                                part_no: d.part_no,
                                part_name: d.part_name,
                                item_type: d.item_type,
                                company_code: d.company_code,
                                count: 0
                            };
                        }
                        map[k].count++;
                    });
                    return Object.values(map).sort((a, b) => b.count - a.count);
                },
                get reasonGroups() {
                    const map = {};
                    this.filteredDelays.forEach(d => {
                        const r = d.problem_reason;
                        if (!map[r]) {
                            map[r] = {
                                reason: r,
                                total: 0,
                                monthMap: {}
                            };
                        }
                        map[r].total++;
                        const mKey = d.month_num;
                        if (!map[r].monthMap[mKey]) {
                            const mObj = ALL_MONTHS.find(x => x.num === Number(mKey));
                            map[r].monthMap[mKey] = {
                                month_num: Number(mKey),
                                month_short: mObj ? mObj.short : d.month_short,
                                month_label: d.month_label,
                                count: 0
                            };
                        }
                        map[r].monthMap[mKey].count++;
                    });

                    return Object.values(map)
                        .map(g => ({
                            ...g,
                            months: Object.values(g.monthMap).sort((a, b) => a.month_num - b.month_num)
                        }))
                        .sort((a, b) => b.total - a.total);
                },
                get reasonYMax() {
                    let m = 4;
                    this.reasonGroups.forEach(rg => {
                        rg.months.forEach(mb => {
                            if (mb.count > m) m = mb.count;
                        });
                    });
                    return Math.ceil(m * 1.15);
                },
                get reasonSummaryList() {
                    const map = {};
                    this.filteredDelays.forEach(d => {
                        const r = d.problem_reason;
                        if (!map[r]) {
                            map[r] = {
                                reason: r,
                                total: 0,
                                itemsMap: {}
                            };
                        }
                        map[r].total++;
                        const ik = d.item_id;
                        if (!map[r].itemsMap[ik]) {
                            map[r].itemsMap[ik] = {
                                part_no: d.part_no,
                                part_name: d.part_name,
                                count: 0
                            };
                        }
                        map[r].itemsMap[ik].count++;
                    });

                    return Object.values(map)
                        .map(g => ({
                            reason: g.reason,
                            total: g.total,
                            items: Object.values(g.itemsMap).sort((a, b) => b.count - a.count)
                        }))
                        .sort((a, b) => b.total - a.total);
                }
            };
        }
    </script>
</x-app-layout>