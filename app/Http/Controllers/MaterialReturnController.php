<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\LotTracer;
use App\Models\MaterialReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MaterialReturnController extends Controller
{
    private function resolveBomMeta(int $materialItemId, int $targetItemId, $allBoms): array
    {
        $triggerBom = $allBoms->first(function ($b) use ($materialItemId, $targetItemId) {
            return (int) $b->input_item_id === $materialItemId
                && (int) $b->output_item_id === $targetItemId
                && (bool) $b->is_trigger === true;
        });

        if (!$triggerBom) {
            $triggerBom = $allBoms->first(function ($b) use ($materialItemId, $targetItemId) {
                return (int) $b->input_item_id === $materialItemId
                    && (int) $b->output_item_id === $targetItemId;
            });
        }

        if (!$triggerBom) {
            return [
                'bom_type'   => 'SINGLE',
                'bom_group'  => null,
                'qty_usage'  => 1,
                'companions' => collect(),
            ];
        }

        $groupKey = $triggerBom->bom_group;
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
        $allBoms = Bom::with(['process', 'inputItem', 'outputItem'])->get();

        if ($request->query('mode') === 'scanner') {
            $todayScans = MaterialReturn::with(['materialItem', 'targetItem', 'user'])
                ->whereDate('date_time', now()->toDateString())
                ->orderBy('date_time', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $items = Item::with(['company', 'inventoryStock'])->get();
            $returnedLots = MaterialReturn::pluck('lot_number')->unique()->toArray();

            $activeLots = LotTracer::with(['materialItem', 'targetItem'])
                ->where('status_lot', 'ON PROGRESS')
                ->get()
                ->filter(fn ($lt) => !in_array($lt->lot_number, $returnedLots) && $lt->material_saat_ini > 0)
                ->values();

            return view('material_returns.scanner', compact('todayScans', 'items', 'activeLots', 'allBoms'));
        }

        $materialReturns = MaterialReturn::with(['materialItem.company', 'targetItem.company', 'user'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($materialReturns as $row) {
            $meta = $this->resolveBomMeta((int) $row->material_item_id, (int) $row->target_item_id, $allBoms);
            $row->bom_type = $meta['bom_type'];
            $estRet = $meta['qty_usage'] > 0 ? ((float) $row->qty / $meta['qty_usage']) : 0;

            $compList = [];
            foreach ($meta['companions'] as $comp) {
                $cQty = round($estRet * (float) $comp->qty_usage, 2);
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

        $groupedReturns = $materialReturns->groupBy('lot_number')->map(function ($rows, $lotNo) {
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

            return [
                'lot_number'    => $lotNo,
                'bom_type'      => $bomType,
                'first'         => $first,
                'rows'          => $rows,
                'qty_trigger'   => (float) $first->qty,
                'companions'    => array_values($companionsMerged),
                'outputs'       => $outputs,
                'status_label'  => $first->qty_check === 'TIDAK SESUAI' ? 'DIKOREKSI' : 'TERCATAT',
                'search_index'  => $searchStr,
                'date_only'     => \Carbon\Carbon::parse($first->date_time)->format('Y-m-d'),
                'time_only'     => \Carbon\Carbon::parse($first->date_time)->format('H:i'),
            ];
        })->values();

        return view('material_returns.index', compact('materialReturns', 'groupedReturns'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'lot_number' => strtoupper(trim((string) $request->lot_number)),
        ]);

        $request->validate([
            'material_item_id' => 'required|exists:items,id',
            'target_item_id'   => 'required|exists:items,id',
            'lot_number'       => 'required|string',
            'qty'              => 'required',
        ]);

        $qtyReturn = BomController::evaluateMath($request->qty);
        if ($qtyReturn <= 0) {
            return back()->withInput()->with('error', 'Qty Material Return harus lebih besar dari 0.');
        }

        if (MaterialReturn::where('lot_number', $request->lot_number)->exists()) {
            return back()->withInput()->with('error', "Nomor Lot {$request->lot_number} sudah pernah melakukan Material Return.");
        }

        $lotRows = LotTracer::where('lot_number', $request->lot_number)
            ->where('material_item_id', $request->material_item_id)
            ->get();

        if ($lotRows->isEmpty()) {
            return back()->withInput()->with('error', 'Ditolak: Nomor Lot dan pasangan Material tersebut tidak ditemukan di Lot Tracer.');
        }

        $maxReturnable = $lotRows->min(fn ($lt) => (float) $lt->material_saat_ini);
        if ($qtyReturn > $maxReturnable) {
            return back()->withInput()->with('error', "Ditolak: Qty Return ({$qtyReturn}) melebihi sisa Material Saat Ini di mesin ({$maxReturnable}).");
        }

        $allBoms = Bom::with('inputItem')->get();

        DB::transaction(function () use ($request, $lotRows, $qtyReturn, $allBoms) {
            $matStock = InventoryStock::firstOrCreate(['item_id' => $request->material_item_id], ['current_stock' => 0]);
            $matStock->increment('current_stock', $qtyReturn);
            $matStock->refresh()->updateDynamicStatus();

            foreach ($lotRows as $lot) {
                $meta = $this->resolveBomMeta((int) $lot->material_item_id, (int) $lot->target_item_id, $allBoms);
                $estReturnedTarget = $meta['qty_usage'] > 0 ? ($qtyReturn / $meta['qty_usage']) : 0;

                foreach ($meta['companions'] as $compBom) {
                    $cAdd = round($estReturnedTarget * (float) $compBom->qty_usage, 2);
                    if ($cAdd > 0) {
                        $cStock = InventoryStock::firstOrCreate(['item_id' => $compBom->input_item_id], ['current_stock' => 0]);
                        $cStock->increment('current_stock', $cAdd);
                        $cStock->refresh()->updateDynamicStatus();
                    }
                }

                MaterialReturn::create([
                    'date_time'        => now(),
                    'user_id'          => Auth::id(),
                    'material_item_id' => $lot->material_item_id,
                    'target_item_id'   => $lot->target_item_id,
                    'lot_number'       => $lot->lot_number,
                    'qty'              => $qtyReturn,
                    'qty_check'        => 'SESUAI',
                ]);

                $newNetMat = max(0, (float) $lot->qty_material_out - $qtyReturn);
                $newEst = $meta['qty_usage'] > 0 ? round($newNetMat / $meta['qty_usage'], 2) : 0;
                $newWip = $lot->status_lot === 'FINISH' ? 0 : max(0, $newEst - (float) $lot->actual_qty_prod);

                $lot->update([
                    'qty_material_return' => $qtyReturn,
                    'target_qty_estimasi' => $newEst,
                    'qty_wip'             => $newWip,
                ]);
            }
        });

        $redirectParams = $request->input('mode') === 'scanner' ? ['mode' => 'scanner'] : [];
        return redirect()->route('material_returns.index', $redirectParams)
            ->with('success', "Material Return Lot {$request->lot_number} berhasil dicatat.");
    }

    public function verifyBatch(Request $request)
    {
        $items = $request->input('items', []);
        if (empty($items)) {
            return back()->with('error', 'Tidak ada data perubahan yang dikirim.');
        }

        $allBoms = Bom::all();

        try {
            DB::transaction(function () use ($items, $allBoms) {
                foreach ($items as $id => $data) {
                    $status = $data['qty_check'] ?? null;
                    if (!$status || $status === 'PENDING') {
                        continue;
                    }

                    $matRet = MaterialReturn::findOrFail($id);

                    if ($status === 'TIDAK SESUAI') {
                        $newQty = BomController::evaluateMath($data['actual_qty'] ?? 0);
                        if ($newQty <= 0) {
                            throw new \Exception('Qty koreksi harus lebih dari 0.');
                        }

                        $oldQty = (float) $matRet->qty;
                        $diffTrigger = $newQty - $oldQty;

                        $lotRows = LotTracer::where('lot_number', $matRet->lot_number)
                            ->where('material_item_id', $matRet->material_item_id)
                            ->get();

                        foreach ($lotRows as $lt) {
                            $usedByProd = 0;
                            $meta = $this->resolveBomMeta((int) $lt->material_item_id, (int) $lt->target_item_id, $allBoms);
                            $usedByProd = (float) $lt->actual_qty_prod * $meta['qty_usage'];
                            $maxAllowed = max(0, (float) $lt->qty_material_out - $usedByProd);
                            if ($newQty > $maxAllowed) {
                                throw new \Exception("Qty Return koreksi ({$newQty}) melebihi sisa material yang belum diproduksi ({$maxAllowed}).");
                            }
                        }

                        if ($diffTrigger != 0) {
                            $matStock = InventoryStock::firstOrCreate(['item_id' => $matRet->material_item_id], ['current_stock' => 0]);
                            if ($diffTrigger < 0 && (float) $matStock->current_stock < abs($diffTrigger)) {
                                throw new \Exception('Stok gudang tidak cukup untuk mengurangi selisih koreksi return.');
                            }

                            $siblingRets = MaterialReturn::where('lot_number', $matRet->lot_number)
                                ->where('material_item_id', $matRet->material_item_id)
                                ->get();

                            foreach ($siblingRets as $sib) {
                                $meta = $this->resolveBomMeta((int) $sib->material_item_id, (int) $sib->target_item_id, $allBoms);
                                $oldEst = $meta['qty_usage'] > 0 ? ($oldQty / $meta['qty_usage']) : 0;
                                $newEst = $meta['qty_usage'] > 0 ? ($newQty / $meta['qty_usage']) : 0;

                                foreach ($meta['companions'] as $compBom) {
                                    $oldComp = round($oldEst * (float) $compBom->qty_usage, 2);
                                    $newComp = round($newEst * (float) $compBom->qty_usage, 2);
                                    $diffComp = $newComp - $oldComp;

                                    if ($diffComp != 0) {
                                        $cStock = InventoryStock::firstOrCreate(['item_id' => $compBom->input_item_id], ['current_stock' => 0]);
                                        $cStock->increment('current_stock', $diffComp);
                                        $cStock->refresh()->updateDynamicStatus();
                                    }
                                }

                                $sib->update([
                                    'qty'       => $newQty,
                                    'qty_check' => 'TIDAK SESUAI',
                                ]);

                                $lot = LotTracer::where('lot_number', $sib->lot_number)
                                    ->where('target_item_id', $sib->target_item_id)
                                    ->first();

                                if ($lot) {
                                    $netMat = max(0, (float) $lot->qty_material_out - $newQty);
                                    $estTarget = $meta['qty_usage'] > 0 ? round($netMat / $meta['qty_usage'], 2) : 0;
                                    $wip = $lot->status_lot === 'FINISH' ? 0 : max(0, $estTarget - (float) $lot->actual_qty_prod);

                                    $lot->update([
                                        'qty_material_return' => $newQty,
                                        'target_qty_estimasi' => $estTarget,
                                        'qty_wip'             => $wip,
                                    ]);
                                }
                            }

                            $matStock->increment('current_stock', $diffTrigger);
                            $matStock->refresh()->updateDynamicStatus();
                        } else {
                            $matRet->update(['qty_check' => 'TIDAK SESUAI']);
                        }
                    } else {
                        $matRet->update(['qty_check' => 'SESUAI']);
                    }
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('material_returns.index')->with('success', 'Koreksi Material Return berhasil disimpan.');
    }
}