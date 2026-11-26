<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\Delivery;
use App\Models\Inbound;
use App\Models\Item;
use App\Models\LotTracer;
use App\Models\MaterialOut;
use App\Models\MaterialReturn;
use App\Models\Production;
use App\Models\ProductionNg;
use App\Models\ReturnToSupplier;
use App\Models\StockAdjustment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StockSnapshotService
{
    /**
     * Sumber tunggal perhitungan Stock 07:00 untuk 1 item (digunakan oleh Kartu Info Item Mobile)
     * agar 100% identik dengan rumus Dashboard & Export (cut-off hari ini >= 07:00 WIB & max(0, ...)).
     *
     * @param  iterable<int, array<string, mixed>>  $history
     * @return array{stock_07: float, cutoff_label: string, is_past_07: bool}
     */
    public function computeItemStock07(float $currentStock, iterable $history): array
    {
        $today    = Carbon::today();
        $cutoff07 = $today->copy()->setTime(7, 0, 0);
        $isPast07 = now()->greaterThanOrEqualTo($cutoff07);

        $netAfter07 = 0.0;
        if ($isPast07) {
            $cutoffTs = $cutoff07->timestamp;
            foreach ($history as $h) {
                if ((int) ($h['timestamp'] ?? 0) >= $cutoffTs) {
                    $netAfter07 = round($netAfter07 + (float) ($h['qty_change'] ?? 0), 2);
                }
            }
        }

        return [
            'stock_07'     => round(max(0, $currentStock - $netAfter07), 2),
            'cutoff_label' => $cutoff07->format('d M Y, H:i') . ' WIB',
            'is_past_07'   => $isPast07,
        ];
    }

    /**
     * Membangun snapshot seluruh item aktif (Stock Saat Ini, Stock 07:00, WIP, Potensi Max,
     * Rincian Material, Std Day 1/2/3, Ratio, Status) sebagai sumber tunggal untuk
     * DashboardController dan ExportController.
     *
     * @return array{
     *     items: Collection<int, array<string, mixed>>,
     *     shared_materials_list: array<int, array<string, mixed>>,
     *     cutoff_label: string,
     *     is_past_07: bool
     * }
     */
    public function buildAll(): array
    {
        $today    = Carbon::today();
        $cutoff07 = $today->copy()->setTime(7, 0, 0);
        $isPast07 = now()->greaterThanOrEqualTo($cutoff07);

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
            $firstLot        = $lotRows->first();
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

        // Hitung mutasi setelah pukul 07:00 hari ini (termasuk pendamping MO & Multi-Return MR)
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

            foreach (
                MaterialReturn::where('date_time', '>=', $cutoff07)
                    ->get()
                    ->groupBy(fn ($r) => !empty($r->return_batch) ? $r->return_batch : ('LEGACY-' . $r->lot_number)) as $batchRows
            ) {
                $f = $batchRows->first();
                if ($f) {
                    $netAfter07[$f->material_item_id] = ($netAfter07[$f->material_item_id] ?? 0) + (float) $f->qty;
                }

                foreach ($batchRows as $mr) {
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

        // Daftar material pembentuk aktif untuk filter Detail Stock di Dashboard
        $sharedMaterialsList = [];
        foreach ($allBomsFlat->groupBy('input_item_id') as $bRows) {
            $inItm = $bRows->first()?->inputItem;
            if (!$inItm || $inItm->trashed()) {
                continue;
            }
            $uniqueOuts = $bRows->pluck('output_item_id')->unique();
            $sharedMaterialsList[] = [
                'id'         => $inItm->id,
                'label'      => ($inItm->part_no ? $inItm->part_no . ' — ' : '') . $inItm->part_name,
                'output_ids' => $uniqueOuts->values()->all(),
            ];
        }

        $items = $allActiveItems->map(function ($itm) use (
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
            $status07     = $this->classifyStockStatus($stock07, $std1, $std2, $std3);
            $ratio07      = $std1 > 0 ? round($stock07 / $std1, 2) : 0.0;

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
                        if (!$inItm || $inItm->trashed() || $usage <= 0) {
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
                'qty_po'             => (float) $itm->qty_po,
                'working_days'       => (int) $itm->working_days,
                'qty_kanban'         => (float) $itm->qty_kanban,
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

        return [
            'items'                 => $items,
            'shared_materials_list' => $sharedMaterialsList,
            'cutoff_label'          => $cutoff07->format('d M Y, H:i') . ' WIB',
            'is_past_07'            => $isPast07,
        ];
    }

    /**
     * Terapkan filter aktif dari Dashboard (Prioritas Stock / Detail Stock) maupun Inventory Stock.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function applyFilters(Collection $items, array $filters): Collection
    {
        $q            = mb_strtolower(trim((string) ($filters['q'] ?? '')), 'UTF-8');
        $type         = trim((string) ($filters['type'] ?? ''));
        $group        = trim((string) ($filters['group'] ?? ''));
        $companyType  = trim((string) ($filters['company_type'] ?? ''));
        $companyId    = trim((string) ($filters['company_id'] ?? ''));
        $status       = trim((string) ($filters['status'] ?? ''));
        $materialId   = trim((string) ($filters['material_id'] ?? ''));
        $snapshotMode = trim((string) ($filters['snapshot_mode'] ?? 'now'));
        $priorityOnly = !empty($filters['priority_only']);

        $filtered = $items->filter(function (array $row) use (
            $q,
            $type,
            $group,
            $companyType,
            $companyId,
            $status,
            $materialId,
            $snapshotMode,
            $priorityOnly
        ) {
            if ($priorityOnly) {
                if (!in_array($row['status_07'] ?? '', ['EMPTY STOCK', 'VERY LOW', 'LOW STOCK'], true)) {
                    return false;
                }
            }

            if ($q !== '') {
                $haystack = mb_strtolower(
                    ($row['part_no'] ?? '') . ' ' . ($row['part_name'] ?? '') . ' ' . ($row['company_code'] ?? '') . ' ' . ($row['company_name'] ?? ''),
                    'UTF-8'
                );
                if (!str_contains($haystack, $q)) {
                    return false;
                }
            }

            if ($type !== '' && ($row['type'] ?? '') !== $type) {
                return false;
            }

            if ($group !== '' && $group !== 'ALL') {
                $rowType = (string) ($row['type'] ?? '');
                $hasBom  = !empty($row['has_bom_inputs']);

                if (in_array($group, ['TARGET_PROD', 'FG_FGK'], true)) {
                    if (!in_array($rowType, ['FG', 'FG/KOMPONEN'], true)) {
                        return false;
                    }
                } elseif ($group === 'MATERIAL') {
                    if (!in_array($rowType, ['RM', 'KOMPONEN', 'FG/KOMPONEN'], true)) {
                        return false;
                    }
                } elseif ($group === 'ALL_PROD') {
                    if (!(in_array($rowType, ['FG', 'FG/KOMPONEN'], true) || ($rowType === 'KOMPONEN' && $hasBom))) {
                        return false;
                    }
                } elseif ($group === 'KOMPONEN_BOM') {
                    if (!($rowType === 'KOMPONEN' && $hasBom)) {
                        return false;
                    }
                } elseif ($rowType !== $group) {
                    return false;
                }
            }

            if ($companyType !== '' && ($row['company_type'] ?? '') !== $companyType) {
                return false;
            }

            if ($companyId !== '' && (string) ($row['company_id'] ?? '') !== $companyId) {
                return false;
            }

            if ($status !== '') {
                $effectiveStatus = $snapshotMode === '07' ? ($row['status_07'] ?? '') : ($row['status_stock'] ?? '');
                if ($effectiveStatus !== $status) {
                    return false;
                }
            }

            if ($materialId !== '' && is_numeric($materialId)) {
                $mId = (int) $materialId;
                if (!in_array($mId, $row['input_ids'] ?? [], true)) {
                    return false;
                }
            }

            return true;
        })->values();

        if ($priorityOnly) {
            $rank = fn (string $st): int => match ($st) {
                'EMPTY STOCK' => 1,
                'VERY LOW'    => 2,
                'LOW STOCK'   => 3,
                default       => 99,
            };

            $filtered = $filtered->sort(function (array $a, array $b) use ($rank) {
                $rA = $rank((string) ($a['status_07'] ?? ''));
                $rB = $rank((string) ($b['status_07'] ?? ''));
                if ($rA !== $rB) {
                    return $rA <=> $rB;
                }
                if ($a['stock_07'] !== $b['stock_07']) {
                    return $a['stock_07'] <=> $b['stock_07'];
                }
                if ($a['ratio_07'] !== $b['ratio_07']) {
                    return $a['ratio_07'] <=> $b['ratio_07'];
                }
                return $b['std_day_1'] <=> $a['std_day_1'];
            })->values();
        }

        return $filtered;
    }

    private function classifyStockStatus(float $stock, float $std1, float $std2, float $std3): string
    {
        if ($stock <= 0) {
            return 'EMPTY STOCK';
        }
        if ($std1 <= 0) {
            return 'NORMAL STOCK';
        }
        if ($stock < $std1) {
            return 'VERY LOW';
        }
        if ($stock < $std2) {
            return 'LOW STOCK';
        }
        if ($stock <= $std3) {
            return 'NORMAL STOCK';
        }

        return 'OVERSTOCK';
    }

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
}