<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Inbound;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\LotTracer;
use App\Models\MaterialOut;
use App\Models\MaterialReturn;
use App\Models\ProblemDelay;
use App\Models\Production;
use App\Models\ProductionNg;
use App\Models\ReturnToSupplier;
use App\Models\StockAdjustment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    private function resolveBomContext(
        int $materialItemId,
        int $targetItemId,
        Collection $allBoms,
        ?string $bomGroup = null,
        ?int $processId = null
    ): array {
        $triggerBom = null;

        if (!empty($bomGroup)) {
            $triggerBom = $allBoms->first(function ($b) use ($materialItemId, $targetItemId, $bomGroup) {
                return (string) $b->bom_group === $bomGroup
                    && (int) $b->input_item_id === $materialItemId
                    && (int) $b->output_item_id === $targetItemId
                    && (bool) $b->is_trigger === true;
            }) ?? $allBoms->first(function ($b) use ($materialItemId, $targetItemId, $bomGroup) {
                return (string) $b->bom_group === $bomGroup
                    && (int) $b->input_item_id === $materialItemId
                    && (int) $b->output_item_id === $targetItemId;
            });
        }

        if (!$triggerBom && $processId !== null) {
            $triggerBom = $allBoms->first(function ($b) use ($materialItemId, $targetItemId, $processId) {
                return (int) $b->process_id === $processId
                    && (int) $b->input_item_id === $materialItemId
                    && (int) $b->output_item_id === $targetItemId
                    && (bool) $b->is_trigger === true;
            });
        }

        if (!$triggerBom) {
            $triggerBom = $allBoms->first(function ($b) use ($materialItemId, $targetItemId) {
                return (int) $b->input_item_id === $materialItemId
                    && (int) $b->output_item_id === $targetItemId
                    && (bool) $b->is_trigger === true;
            }) ?? $allBoms->first(function ($b) use ($materialItemId, $targetItemId) {
                return (int) $b->input_item_id === $materialItemId
                    && (int) $b->output_item_id === $targetItemId;
            });
        }

        if (!$triggerBom) {
            return [
                'trigger'    => null,
                'companions' => collect(),
            ];
        }

        $groupKey = $bomGroup ?: $triggerBom->bom_group;
        if (!empty($groupKey)) {
            $groupRows = $allBoms->where('bom_group', $groupKey);
        } else {
            $groupRows = $allBoms->where('process_id', $triggerBom->process_id)
                ->where('output_item_id', $triggerBom->output_item_id);
        }

        $companions = $groupRows->where('output_item_id', $targetItemId)
            ->where('is_trigger', false)
            ->values();

        return [
            'trigger'    => $triggerBom,
            'companions' => $companions,
        ];
    }

    public function index()
    {
        $today    = Carbon::today();
        $cutoff07 = $today->copy()->setTime(7, 0, 0);
        $isPast07 = now()->greaterThanOrEqualTo($cutoff07);

        $outputToday  = Production::whereDate('date_time', $today)->sum('qty');
        $totalStock   = InventoryStock::whereHas('item', fn ($q) => $q->whereNull('deleted_at'))->sum('current_stock');
        $inboundToday = Inbound::whereDate('date_time', $today)->count();

        $totalProdAll = Production::sum('qty');
        $totalNgAll   = ProductionNg::sum('qty_ng');
        $ngRate       = $totalProdAll > 0 ? round(($totalNgAll / $totalProdAll) * 100, 2) : 0;

        // Diselaraskan dengan incident_date (fallback date_time bila data lama null)
        $delayCount = ProblemDelay::where(function ($q) use ($today) {
            $q->whereDate('incident_date', $today)
                ->orWhere(function ($sub) use ($today) {
                    $sub->whereNull('incident_date')
                        ->whereDate('date_time', $today);
                });
        })->count();

        $activeLots = LotTracer::with(['process', 'materialItem', 'targetItem'])
            ->where('status_lot', 'ON PROGRESS')
            ->orderBy('updated_at', 'desc')
            ->take(8)
            ->get();

        $outByDay = Production::selectRaw('DATE(date_time) as d, SUM(qty) as q')
            ->whereDate('date_time', '>=', $today->copy()->subDays(6))
            ->groupBy('d')
            ->pluck('q', 'd');

        $runningLots = LotTracer::where('status_lot', 'ON PROGRESS')->count();
        $lowStockItems = InventoryStock::whereHas('item', fn ($q) => $q->whereNull('deleted_at'))
            ->whereIn('status_stock', ['EMPTY STOCK', 'VERY LOW', 'LOW STOCK'])
            ->count();
        $veryLowItems = InventoryStock::whereHas('item', fn ($q) => $q->whereNull('deleted_at'))
            ->whereIn('status_stock', ['EMPTY STOCK', 'VERY LOW'])
            ->count();

        // =========================================================================
        // PERSIAPAN DATA ITEM, BOM, WIP, & SNAPSHOT PUKUL 07:00 PAGI
        // =========================================================================
        $allActiveItems = Item::with([
            'company',
            'inventoryStock',
            'bomsAsInput.outputItem',
            'bomsAsOutput.inputItem.inventoryStock',
        ])->orderBy('part_name')->get();

        $allBomsFlat = Bom::with(['inputItem.inventoryStock', 'outputItem'])->get();

        $outputCountByInputId = $allBomsFlat
            ->groupBy('input_item_id')
            ->map(fn ($rows) => $rows->pluck('output_item_id')->unique()->count())
            ->all();

        $allRunningLots = LotTracer::with('process')
            ->where('status_lot', 'ON PROGRESS')
            ->orderBy('id', 'desc')
            ->get();

        $wipByTargetItem        = [];
        $wipDetailsByTargetItem = [];
        $matInMachineByItem     = [];

        foreach ($allRunningLots->groupBy('lot_number') as $lotRows) {
            $firstLot = $lotRows->first();
            $maxUsedMaterial = 0.0;

            foreach ($lotRows as $lr) {
                $tId    = (int) $lr->target_item_id;
                $wipVal = (float) $lr->qty_wip;
                $wipByTargetItem[$tId] = round(($wipByTargetItem[$tId] ?? 0) + $wipVal, 2);
                if ($wipVal > 0) {
                    $wipDetailsByTargetItem[$tId][] = [
                        'lot_number'   => $lr->lot_number,
                        'process_name' => $lr->process->process_name ?? '-',
                        'qty_wip'      => floatval($wipVal),
                    ];
                }

                $ctx   = $this->resolveBomContext((int) $lr->material_item_id, $tId, $allBomsFlat, $lr->bom_group, (int) $lr->process_id);
                $tb    = $ctx['trigger'];
                $usage = ($tb && (float) $tb->qty_usage > 0) ? (float) $tb->qty_usage : 1.0;
                $used  = (float) $lr->actual_qty_prod * $usage;
                if ($used > $maxUsedMaterial) {
                    $maxUsedMaterial = $used;
                }

                if ($tb && (float) $tb->qty_usage > 0 && $ctx['companions']->isNotEmpty()) {
                    $estInit = (float) $lr->qty_material_out / (float) $tb->qty_usage;
                    if (abs($estInit - round($estInit)) < 0.001) {
                        $estInit = round($estInit);
                    }

                    $estRet = (float) $lr->qty_material_return / (float) $tb->qty_usage;
                    if (abs($estRet - round($estRet)) < 0.001) {
                        $estRet = round($estRet);
                    }

                    $estNet  = max(0, $estInit - $estRet);
                    $actProd = (float) $lr->actual_qty_prod;

                    foreach ($ctx['companions'] as $cb) {
                        $cId       = (int) $cb->input_item_id;
                        $usageComp = (float) $cb->qty_usage;
                        $netVal    = round($estNet * $usageComp, 2);
                        $usedVal   = round($actProd * $usageComp, 2);
                        $currVal   = $lr->status_lot === 'FINISH' ? 0.0 : round(max(0, $netVal - $usedVal), 2);
                        if ($cId > 0 && $currVal > 0) {
                            $matInMachineByItem[$cId] = round(($matInMachineByItem[$cId] ?? 0) + $currVal, 2);
                        }
                    }
                }
            }

            if ($firstLot) {
                $remTrigger = round(
                    max(0, (float) $firstLot->qty_material_out - (float) $firstLot->qty_material_return - $maxUsedMaterial),
                    2
                );
                if ($remTrigger > 0) {
                    $mId = (int) $firstLot->material_item_id;
                    $matInMachineByItem[$mId] = round(($matInMachineByItem[$mId] ?? 0) + $remTrigger, 2);
                }
            }
        }

        // Hitung mutasi setelah pukul 07:00 pagi hari ini (termasuk komponen pendamping MO & MR)
        $netAfter07 = [];
        if ($isPast07) {
            foreach (Inbound::where('date_time', '>=', $cutoff07)->get() as $r) {
                $netAfter07[$r->item_id] = ($netAfter07[$r->item_id] ?? 0) + (float) $r->qty;
            }

            foreach (MaterialOut::where('date_time', '>=', $cutoff07)->get()->groupBy('lot_number') as $lotRows) {
                $f = $lotRows->first();
                if ($f) {
                    $netAfter07[$f->material_item_id] = ($netAfter07[$f->material_item_id] ?? 0) - (float) $f->qty;
                }

                foreach ($lotRows as $mo) {
                    $ctx = $this->resolveBomContext((int) $mo->material_item_id, (int) $mo->target_item_id, $allBomsFlat, $mo->bom_group);
                    $tb  = $ctx['trigger'];
                    if (!$tb || (float) $tb->qty_usage <= 0 || $ctx['companions']->isEmpty()) {
                        continue;
                    }

                    $estOut = (float) $mo->qty / (float) $tb->qty_usage;
                    if (abs($estOut - round($estOut)) < 0.001) {
                        $estOut = round($estOut);
                    }

                    foreach ($ctx['companions'] as $cb) {
                        $deducted = round($estOut * (float) $cb->qty_usage, 2);
                        if ($deducted > 0) {
                            $cId = (int) $cb->input_item_id;
                            $netAfter07[$cId] = round(($netAfter07[$cId] ?? 0) - $deducted, 2);
                        }
                    }
                }
            }

            foreach (MaterialReturn::where('date_time', '>=', $cutoff07)->get()->groupBy('lot_number') as $lotRows) {
                $f = $lotRows->first();
                if ($f) {
                    $netAfter07[$f->material_item_id] = ($netAfter07[$f->material_item_id] ?? 0) + (float) $f->qty;
                }

                foreach ($lotRows as $mr) {
                    $ctx = $this->resolveBomContext((int) $mr->material_item_id, (int) $mr->target_item_id, $allBomsFlat, $mr->bom_group);
                    $tb  = $ctx['trigger'];
                    if (!$tb || (float) $tb->qty_usage <= 0 || $ctx['companions']->isEmpty()) {
                        continue;
                    }

                    $estRet = (float) $mr->qty / (float) $tb->qty_usage;
                    if (abs($estRet - round($estRet)) < 0.001) {
                        $estRet = round($estRet);
                    }

                    foreach ($ctx['companions'] as $cb) {
                        $restored = round($estRet * (float) $cb->qty_usage, 2);
                        if ($restored > 0) {
                            $cId = (int) $cb->input_item_id;
                            $netAfter07[$cId] = round(($netAfter07[$cId] ?? 0) + $restored, 2);
                        }
                    }
                }
            }

            foreach (Production::where('date_time', '>=', $cutoff07)->get() as $r) {
                $netAfter07[$r->item_id] = ($netAfter07[$r->item_id] ?? 0) + (float) $r->qty;
            }
            foreach (ProductionNg::where('date_time', '>=', $cutoff07)->get() as $r) {
                $netAfter07[$r->item_id] = ($netAfter07[$r->item_id] ?? 0) - (float) $r->qty_ng;
            }
            foreach (Delivery::where('date_time', '>=', $cutoff07)->get() as $r) {
                $netAfter07[$r->item_id] = ($netAfter07[$r->item_id] ?? 0) - (float) $r->qty;
            }
            foreach (ReturnToSupplier::where('date_time', '>=', $cutoff07)->get() as $r) {
                $netAfter07[$r->item_id] = ($netAfter07[$r->item_id] ?? 0) - (float) $r->qty;
            }
            foreach (StockAdjustment::where('date_time', '>=', $cutoff07)->get() as $r) {
                $netAfter07[$r->item_id] = ($netAfter07[$r->item_id] ?? 0) + (float) $r->qty_diff;
            }
        }

        // Daftar material pembentuk untuk filter pada Detail Stock
        $sharedMaterialsList = [];
        foreach ($allBomsFlat->groupBy('input_item_id') as $bRows) {
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

        $dashboardItems = $allActiveItems->map(function ($itm) use (
            $wipByTargetItem,
            $wipDetailsByTargetItem,
            $matInMachineByItem,
            $netAfter07,
            $outputCountByInputId
        ) {
            $st        = $itm->inventoryStock;
            $currStock = $st ? (float) $st->current_stock : 0.0;
            $std1      = (float) $itm->calculated_std_day_1;
            $std2      = (float) $itm->std_day_2;
            $std3      = (float) $itm->std_day_3;
            $ratioFg   = $std1 > 0 ? round($currStock / $std1, 2) : 0.0;
            $statusNow = $st ? $st->calculateDynamicStatus() : 'EMPTY STOCK';

            $deltaAfter07 = (float) ($netAfter07[$itm->id] ?? 0.0);
            $stock07      = round(max(0, $currStock - $deltaAfter07), 2);
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

            $outBoms           = $itm->bomsAsOutput;
            $hasBomInputs      = $outBoms->isNotEmpty();
            $potMax            = 0.0;
            $hasSharedInput    = false;
            $materialsDetail   = [];
            $bottleneckFormula = '';

            if ($hasBomInputs) {
                $byGroupMax = [];

                foreach ($outBoms->groupBy(fn ($b) => $b->bom_group ?: ('SG-' . $b->id)) as $gRows) {
                    $minMax       = null;
                    $minInputMeta = null;

                    foreach ($gRows as $bRow) {
                        $inItm = $bRow->inputItem;
                        $usage = (float) $bRow->qty_usage;
                        if (!$inItm || $usage <= 0) {
                            continue;
                        }

                        $inStock   = $inItm->inventoryStock ? (float) $inItm->inventoryStock->current_stock : 0.0;
                        $usedCount = (int) ($outputCountByInputId[$inItm->id] ?? 1);
                        if ($usedCount > 1) {
                            $hasSharedInput = true;
                        }

                        $canMakeMax = floor($inStock / $usage);
                        if ($minMax === null || $canMakeMax < $minMax) {
                            $minMax       = $canMakeMax;
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
                    usort($byGroupMax, fn ($a, $b) => $b['val'] <=> $a['val']);
                    $bestGrp = $byGroupMax[0];
                    $potMax  = round($bestGrp['val'], 0);
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
                'wip_details'        => $wipDetailsByTargetItem[$itm->id] ?? [],
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
            ];
        })->values();

        $allCompaniesList = Company::orderByRaw("CASE type_company WHEN 'CUSTOMER' THEN 1 WHEN 'INTERNAL' THEN 2 ELSE 3 END")
            ->orderBy('company_code')
            ->get()
            ->map(fn ($c) => [
                'id'   => $c->id,
                'code' => $c->company_code,
                'name' => $c->company_name,
                'type' => $c->type_company,
            ])->values();

        $defaultFgCustomer     = $allCompaniesList->firstWhere('type', 'CUSTOMER');
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

        $allDelays = ProblemDelay::with(['company', 'item.company'])
            ->orderByDesc('incident_date')
            ->orderByDesc('date_time')
            ->get()
            ->map(function ($d) use ($monthNamesShort, $monthNamesFull) {
                $dt   = $d->incident_date ? Carbon::parse($d->incident_date) : Carbon::parse($d->date_time);
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
        $delayReasonsList = ProblemDelayController::REASONS;

        return view('dashboard', compact(
            'outputToday',
            'totalStock',
            'inboundToday',
            'totalNgAll',
            'ngRate',
            'delayCount',
            'activeLots',
            'outByDay',
            'runningLots',
            'lowStockItems',
            'veryLowItems',
            'sharedMaterialsList',
            'dashboardItems',
            'allCompaniesList',
            'defaultStockCompanyId',
            'allDelays',
            'delayYears',
            'delayReasonsList'
        ));
    }
}