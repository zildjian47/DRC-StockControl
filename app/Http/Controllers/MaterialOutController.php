<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\LotTracer;
use App\Models\MaterialOut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MaterialOutController extends Controller
{
    private function resolveBomMeta(int $materialItemId, int $targetItemId, $allBoms, ?string $bomGroup = null): array
    {
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
                'bom_type'   => 'SINGLE',
                'bom_group'  => $bomGroup,
                'qty_usage'  => 1,
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

        $uIn = $groupRows->pluck('input_item_id')->unique()->count();
        $uOut = $groupRows->pluck('output_item_id')->unique()->count();

        if (($groupKey && str_starts_with($groupKey, 'CB-')) || ($uIn > 1 && $uOut > 1)) {
            $bomType = 'KOMBINASI';
        } elseif (($groupKey && str_starts_with($groupKey, 'MO-')) || ($uIn === 1 && $uOut > 1)) {
            $bomType = 'MULTI-OUTPUT';
        } elseif (($groupKey && str_starts_with($groupKey, 'MI-')) || ($uIn > 1 && $uOut === 1)) {
            $bomType = 'MULTI-INPUT';
        } else {
            $bomType = 'SINGLE';
        }

        $companions = $groupRows->where('output_item_id', $targetItemId)
            ->where('is_trigger', false)
            ->values();

        return [
            'bom_type'   => $bomType,
            'bom_group'  => $groupKey,
            'qty_usage'  => (float) $triggerBom->qty_usage,
            'companions' => $companions,
        ];
    }

    public function index(Request $request)
    {
        $allBoms = Bom::with(['process', 'inputItem.inventoryStock', 'outputItem'])->get();

        if ($request->query('mode') === 'scanner') {
            $todayScans = MaterialOut::with(['materialItem', 'targetItem', 'user'])
                ->whereDate('date_time', now()->toDateString())
                ->orderBy('date_time', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $recentScans = $todayScans;
            $items = Item::with(['company', 'inventoryStock'])->get();
            $existingLots = LotTracer::pluck('lot_number')->unique()->values();

            return view('material_outs.scanner', compact('todayScans', 'recentScans', 'allBoms', 'items', 'existingLots'));
        }

        $materialOuts = MaterialOut::with(['materialItem.company', 'targetItem.company', 'user'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($materialOuts as $row) {
            $meta = $this->resolveBomMeta((int) $row->material_item_id, (int) $row->target_item_id, $allBoms, $row->bom_group);
            $row->bom_type = $meta['bom_type'];
            $estOut = $meta['qty_usage'] > 0 ? ((float) $row->qty / $meta['qty_usage']) : 0;

            $compList = [];
            foreach ($meta['companions'] as $comp) {
                $cQty = round($estOut * (float) $comp->qty_usage, 2);
                if ($cQty > 0 && $comp->inputItem) {
                    $compList[] = [
                        'part_no'   => $comp->inputItem->part_no ?: '-',
                        'part_name' => $comp->inputItem->part_name,
                        'uom'       => $comp->inputItem->uom,
                        'qty'       => $cQty,
                    ];
                }
            }
            $row->companion_list = $compList;
        }

        $groupedOuts = $materialOuts->groupBy('lot_number')->map(function ($rows, $lotNo) {
            $first = $rows->first();
            $bomType = $first->bom_type ?: 'SINGLE';

            $companionsMerged = [];
            foreach ($rows as $r) {
                foreach ($r->companion_list as $c) {
                    $k = $c['part_no'] . '|' . $c['part_name'];
                    if (!isset($companionsMerged[$k])) {
                        $companionsMerged[$k] = $c;
                    } else {
                        $companionsMerged[$k]['qty'] = round($companionsMerged[$k]['qty'] + $c['qty'], 2);
                    }
                }
            }

            $outputs = $rows->map(fn ($r) => [
                'id'        => $r->id,
                'part_no'   => $r->targetItem->part_no ?: '-',
                'part_name' => $r->targetItem->part_name ?? '-',
                'type'      => $r->targetItem->type ?? '-',
                'uom'       => $r->targetItem->uom ?? 'PCS',
            ])->values();

            $searchStr = strtolower(
                $lotNo . ' ' .
                $bomType . ' ' .
                ($first->user->name ?? 'Operator') . ' ' .
                ($first->materialItem->part_no ?? '') . ' ' .
                ($first->materialItem->part_name ?? '') . ' ' .
                collect($companionsMerged)->pluck('part_name')->implode(' ') . ' ' .
                $outputs->pluck('part_no')->implode(' ') . ' ' .
                $outputs->pluck('part_name')->implode(' ')
            );

            $isCorrected = $rows->contains('qty_check', 'DIKOREKSI');

            return [
                'lot_number'   => $lotNo,
                'bom_type'     => $bomType,
                'first'        => $first,
                'rows'         => $rows,
                'qty_trigger'  => (float) $first->qty,
                'companions'   => array_values($companionsMerged),
                'outputs'      => $outputs,
                'status_label' => $isCorrected ? 'DIKOREKSI' : 'TERCATAT',
                'search_index' => $searchStr,
                'date_only'    => \Carbon\Carbon::parse($first->date_time)->format('Y-m-d'),
                'time_only'    => \Carbon\Carbon::parse($first->date_time)->format('H:i'),
            ];
        })->values();

        $groups = $groupedOuts;

        return view('material_outs.index', compact('materialOuts', 'groupedOuts', 'groups'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'lot_number' => strtoupper(trim((string) $request->lot_number)),
        ]);

        $request->validate([
            'material_item_id' => 'required|exists:items,id',
            'target_item_id'   => 'required|exists:items,id',
            'lot_number'       => ['required', 'string', 'regex:/^[A-Z][0-9]{6}$/'],
            'qty'              => 'required',
            'bom_group'        => 'nullable|string',
        ], [
            'lot_number.regex' => 'Format Lot wajib 1 huruf kapital + 6 angka (contoh: J260178).',
        ]);

        $qtyOut = BomController::evaluateMath($request->qty);
        if ($qtyOut <= 0) {
            return back()->withInput()->with('error', 'Qty harus lebih dari 0.');
        }

        $materialItem = Item::with('company')->findOrFail($request->material_item_id);
        $targetItem = Item::with('company')->findOrFail($request->target_item_id);

        $matCompanyType = $materialItem->company->type_company ?? '';
        if (!in_array($materialItem->type, ['RM', 'KOMPONEN', 'FG/KOMPONEN']) || !in_array($matCompanyType, ['SUPPLIER', 'INTERNAL'])) {
            return back()->withInput()->with('error', 'Material Input wajib RM, KOMPONEN, atau FG/KOMPONEN (SUPPLIER/INTERNAL).');
        }

        $tgtCompanyType = $targetItem->company->type_company ?? '';
        if (!in_array($targetItem->type, ['FG', 'KOMPONEN', 'FG/KOMPONEN']) || !in_array($tgtCompanyType, ['INTERNAL', 'CUSTOMER'])) {
            return back()->withInput()->with('error', 'Target Output wajib FG, KOMPONEN, atau FG/KOMPONEN (INTERNAL/CUSTOMER).');
        }

        $bomQuery = Bom::where('input_item_id', $materialItem->id)
            ->where('output_item_id', $targetItem->id)
            ->where('is_trigger', true);

        if ($request->filled('bom_group')) {
            $bomQuery->where('bom_group', $request->bom_group);
        }

        $triggerBom = $bomQuery->first();
        if (!$triggerBom) {
            return back()->withInput()->with('error', 'Pasangan Material & Target Output tidak terdaftar sebagai Trigger di BOM.');
        }

        $groupKey = $triggerBom->bom_group;
        if (!empty($groupKey) && (str_starts_with($groupKey, 'MO-') || str_starts_with($groupKey, 'CB-'))) {
            $triggerBomsInGroup = Bom::where('bom_group', $groupKey)
                ->where('input_item_id', $materialItem->id)
                ->where('is_trigger', true)
                ->get();
        } else {
            $triggerBomsInGroup = collect([$triggerBom]);
        }

        $companionDeductions = [];
        foreach ($triggerBomsInGroup as $tBom) {
            $rawEst = (float) $tBom->qty_usage > 0 ? ($qtyOut / (float) $tBom->qty_usage) : 0;
            $estTargetQty = abs($rawEst - round($rawEst)) < 0.001 ? round($rawEst) : round($rawEst, 2);

            $compQuery = Bom::with('inputItem')
                ->where('process_id', $tBom->process_id)
                ->where('output_item_id', $tBom->output_item_id)
                ->where('is_trigger', false);

            if (!empty($tBom->bom_group)) {
                $compQuery->where('bom_group', $tBom->bom_group);
            }

            foreach ($compQuery->get() as $compBom) {
                $needed = round($estTargetQty * (float) $compBom->qty_usage, 2);
                if ($needed > 0) {
                    $cId = $compBom->input_item_id;
                    if (!isset($companionDeductions[$cId])) {
                        $companionDeductions[$cId] = [
                            'item'   => $compBom->inputItem,
                            'needed' => 0,
                        ];
                    }
                    $companionDeductions[$cId]['needed'] = round($companionDeductions[$cId]['needed'] + $needed, 2);
                }
            }
        }

        try {
            DB::transaction(function () use ($request, $materialItem, $triggerBomsInGroup, $groupKey, $qtyOut, $companionDeductions) {
                if (LotTracer::where('lot_number', $request->lot_number)->lockForUpdate()->exists()) {
                    throw new \RuntimeException("Nomor Lot {$request->lot_number} sudah terdaftar.");
                }

                InventoryStock::firstOrCreate(['item_id' => $materialItem->id], ['current_stock' => 0]);
                $matStock = InventoryStock::where('item_id', $materialItem->id)->lockForUpdate()->first();
                if ((float) $matStock->current_stock < $qtyOut) {
                    throw new \RuntimeException('Stok kurang. Tersedia: ' . floatval($matStock->current_stock) . " {$materialItem->uom}.");
                }

                $lockedCompStocks = [];
                foreach ($companionDeductions as $cId => $cData) {
                    InventoryStock::firstOrCreate(['item_id' => $cId], ['current_stock' => 0]);
                    $cStock = InventoryStock::where('item_id', $cId)->lockForUpdate()->first();
                    if ((float) $cStock->current_stock < $cData['needed']) {
                        $cName = $cData['item']->part_name ?? 'Komponen';
                        $cUom = $cData['item']->uom ?? '';
                        throw new \RuntimeException("Stok pendamping {$cName} kurang (butuh " . floatval($cData['needed']) . ', tersedia ' . floatval($cStock->current_stock) . " {$cUom}).");
                    }
                    $lockedCompStocks[$cId] = $cStock;
                }

                $matStock->decrement('current_stock', $qtyOut);
                $matStock->refresh()->updateDynamicStatus();

                foreach ($companionDeductions as $cId => $cData) {
                    $cStock = $lockedCompStocks[$cId];
                    $cStock->decrement('current_stock', $cData['needed']);
                    $cStock->refresh()->updateDynamicStatus();
                }

                foreach ($triggerBomsInGroup as $tBom) {
                    $savedGroup = $tBom->bom_group ?: $groupKey;

                    MaterialOut::create([
                        'date_time'        => now(),
                        'user_id'          => Auth::id(),
                        'material_item_id' => $materialItem->id,
                        'target_item_id'   => $tBom->output_item_id,
                        'lot_number'       => $request->lot_number,
                        'bom_group'        => $savedGroup,
                        'qty'              => $qtyOut,
                        'qty_check'        => 'TERCATAT',
                    ]);

                    $rawEst = (float) $tBom->qty_usage > 0 ? ($qtyOut / (float) $tBom->qty_usage) : 0;
                    $estQty = abs($rawEst - round($rawEst)) < 0.001 ? round($rawEst) : round($rawEst, 2);

                    LotTracer::create([
                        'lot_number'          => $request->lot_number,
                        'bom_group'           => $savedGroup,
                        'process_id'          => $tBom->process_id,
                        'material_item_id'    => $materialItem->id,
                        'target_item_id'      => $tBom->output_item_id,
                        'qty_material_out'    => $qtyOut,
                        'qty_material_return' => 0,
                        'target_qty_estimasi' => $estQty,
                        'actual_qty_prod'     => 0,
                        'qty_ng'              => 0,
                        'qty_wip'             => $estQty,
                        'status_lot'          => 'ON PROGRESS',
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $redirectParams = $request->input('mode') === 'scanner' ? ['mode' => 'scanner'] : [];
        return redirect()->route('material_outs.index', $redirectParams)
            ->with('success', "Material Out Lot {$request->lot_number} (-" . floatval($qtyOut) . " {$materialItem->uom}) tersimpan.");
    }

    public function verifyBatch(Request $request)
    {
        $items = $request->input('items', []);
        if (empty($items)) {
            return back()->with('error', 'Tidak ada koreksi yang disimpan.');
        }

        $allBoms = Bom::all();

        try {
            DB::transaction(function () use ($items, $allBoms) {
                foreach ($items as $id => $data) {
                    $status = $data['qty_check'] ?? null;
                    if (!$status) {
                        continue;
                    }

                    $matOut = MaterialOut::lockForUpdate()->findOrFail($id);

                    if ($status === 'DIKOREKSI') {
                        $newQty = round(BomController::evaluateMath($data['actual_qty'] ?? 0), 2);
                        if ($newQty <= 0) {
                            throw new \Exception('Qty koreksi harus lebih dari 0.');
                        }

                        $oldQty = (float) $matOut->qty;
                        $diffTrigger = round($newQty - $oldQty, 2);

                        $siblingOuts = MaterialOut::where('lot_number', $matOut->lot_number)
                            ->where('material_item_id', $matOut->material_item_id)
                            ->lockForUpdate()
                            ->get();

                        if ($diffTrigger != 0) {
                            $lotRows = LotTracer::where('lot_number', $matOut->lot_number)
                                ->where('material_item_id', $matOut->material_item_id)
                                ->lockForUpdate()
                                ->get();

                            if ($diffTrigger < 0) {
                                foreach ($lotRows as $lt) {
                                    $metaLt = $this->resolveBomMeta((int) $lt->material_item_id, (int) $lt->target_item_id, $allBoms, $lt->bom_group);
                                    $minRequired = round((float) $lt->qty_material_return + ((float) $lt->actual_qty_prod * $metaLt['qty_usage']), 2);
                                    if ($newQty < $minRequired - 0.0001) {
                                        throw new \Exception('Qty koreksi tidak boleh lebih kecil dari total material yang sudah diretur dan terpakai produksi (minimal ' . floatval($minRequired) . ').');
                                    }
                                }
                            }

                            InventoryStock::firstOrCreate(['item_id' => $matOut->material_item_id], ['current_stock' => 0]);
                            $matStock = InventoryStock::where('item_id', $matOut->material_item_id)->lockForUpdate()->first();
                            if ($diffTrigger > 0 && (float) $matStock->current_stock < $diffTrigger) {
                                throw new \Exception('Stok Material Trigger tidak cukup untuk koreksi.');
                            }

                            foreach ($siblingOuts as $sib) {
                                $meta = $this->resolveBomMeta((int) $sib->material_item_id, (int) $sib->target_item_id, $allBoms, $sib->bom_group);
                                $oldEst = $meta['qty_usage'] > 0 ? ($oldQty / $meta['qty_usage']) : 0;
                                $newEst = $meta['qty_usage'] > 0 ? ($newQty / $meta['qty_usage']) : 0;

                                foreach ($meta['companions'] as $compBom) {
                                    $oldComp = round($oldEst * (float) $compBom->qty_usage, 2);
                                    $newComp = round($newEst * (float) $compBom->qty_usage, 2);
                                    $diffComp = round($newComp - $oldComp, 2);

                                    if ($diffComp != 0) {
                                        InventoryStock::firstOrCreate(['item_id' => $compBom->input_item_id], ['current_stock' => 0]);
                                        $cStock = InventoryStock::where('item_id', $compBom->input_item_id)->lockForUpdate()->first();
                                        if ($diffComp > 0 && (float) $cStock->current_stock < $diffComp) {
                                            throw new \Exception('Stok komponen pendamping tidak cukup untuk koreksi.');
                                        }
                                        $cStock->decrement('current_stock', $diffComp);
                                        $cStock->refresh()->updateDynamicStatus();
                                    }
                                }

                                $sib->update([
                                    'qty'       => $newQty,
                                    'qty_check' => 'DIKOREKSI',
                                ]);

                                $lot = $lotRows->firstWhere('target_item_id', $sib->target_item_id);

                                if ($lot) {
                                    $netMat = max(0, $newQty - (float) $lot->qty_material_return);
                                    $rawEst = $meta['qty_usage'] > 0 ? ($netMat / $meta['qty_usage']) : 0;
                                    $estTarget = abs($rawEst - round($rawEst)) < 0.001 ? round($rawEst) : round($rawEst, 2);
                                    $wip = $lot->status_lot === 'FINISH' ? 0 : round(max(0, $estTarget - (float) $lot->actual_qty_prod), 2);

                                    $lot->update([
                                        'qty_material_out'    => $newQty,
                                        'target_qty_estimasi' => $estTarget,
                                        'qty_wip'             => $wip,
                                    ]);
                                }
                            }

                            $matStock->decrement('current_stock', $diffTrigger);
                            $matStock->refresh()->updateDynamicStatus();
                        } else {
                            foreach ($siblingOuts as $sib) {
                                $sib->update(['qty_check' => 'DIKOREKSI']);
                            }
                        }
                    } else {
                        $matOut->update(['qty_check' => 'TERCATAT']);
                    }
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('material_outs.index')->with('success', 'Koreksi Material Out tersimpan.');
    }
}