<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\LotTracer;
use App\Models\Process;
use Illuminate\Http\Request;

class LotTracerController extends Controller
{
    public function index(Request $request)
    {
        $processes = Process::orderBy('process_name', 'asc')->get();
        $allBoms = Bom::with('inputItem')->get();

        $lotTracers = LotTracer::with(['process', 'materialItem.company', 'targetItem.company'])
            ->orderByRaw("CASE WHEN status_lot = 'ON PROGRESS' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc')
            ->get();

        $groupedLots = $lotTracers->groupBy('lot_number')->map(function ($rows, $lotNo) use ($allBoms) {
            $first = $rows->first();

            $triggerBom = $allBoms->first(function ($b) use ($first) {
                return (int) $b->process_id === (int) $first->process_id
                    && (int) $b->input_item_id === (int) $first->material_item_id
                    && (int) $b->output_item_id === (int) $first->target_item_id
                    && (bool) $b->is_trigger === true;
            }) ?? $allBoms->first(function ($b) use ($first) {
                return (int) $b->input_item_id === (int) $first->material_item_id
                    && (int) $b->output_item_id === (int) $first->target_item_id;
            });

            $groupKey = $triggerBom->bom_group ?? null;
            if (!empty($groupKey)) {
                $groupBoms = $allBoms->where('bom_group', $groupKey);
            } elseif ($triggerBom) {
                $groupBoms = $allBoms->where('process_id', $triggerBom->process_id)
                                     ->where('output_item_id', $triggerBom->output_item_id);
            } else {
                $groupBoms = collect();
            }

            $uIn = $groupBoms->pluck('input_item_id')->unique()->count();
            $uOut = $groupBoms->pluck('output_item_id')->unique()->count();

            if (($groupKey && str_starts_with($groupKey, 'CB-')) || ($uIn > 1 && $uOut > 1)) {
                $bomType = 'KOMBINASI';
            } elseif (($groupKey && str_starts_with($groupKey, 'MO-')) || ($uIn === 1 && $uOut > 1) || $rows->count() > 1) {
                $bomType = 'MULTI-OUTPUT';
            } elseif (($groupKey && str_starts_with($groupKey, 'MI-')) || ($uIn > 1 && $uOut === 1)) {
                $bomType = 'MULTI-INPUT';
            } else {
                $bomType = 'SINGLE';
            }

            $companionsMap = [];
            foreach ($rows as $r) {
                $tBom = $groupBoms->first(fn ($b) => (int) $b->output_item_id === (int) $r->target_item_id && (bool) $b->is_trigger);
                $triggerUsage = ($tBom && (float) $tBom->qty_usage > 0) ? (float) $tBom->qty_usage : 1;

                $estInitial = (float) $r->qty_material_out / $triggerUsage;
                $estReturn = (float) $r->qty_material_return / $triggerUsage;
                $actProd = (float) $r->actual_qty_prod;

                $compRows = $groupBoms->where('output_item_id', $r->target_item_id)->where('is_trigger', false);
                foreach ($compRows as $cBom) {
                    if (!$cBom->inputItem) {
                        continue;
                    }
                    $cId = $cBom->input_item_id;
                    $u = (float) $cBom->qty_usage;
                    $cOut = round($estInitial * $u, 2);
                    $cRet = round($estReturn * $u, 2);
                    $cUsed = round($actProd * $u, 2);
                    $cCurr = max(0, round($cOut - $cRet - $cUsed, 2));

                    if (!isset($companionsMap[$cId])) {
                        $companionsMap[$cId] = [
                            'part_no'     => $cBom->inputItem->part_no ?: '-',
                            'part_name'   => $cBom->inputItem->part_name,
                            'uom'         => $cBom->inputItem->uom,
                            'qty_out'     => $cOut,
                            'qty_return'  => $cRet,
                            'qty_current' => $cCurr,
                        ];
                    } else {
                        $companionsMap[$cId]['qty_out'] = round($companionsMap[$cId]['qty_out'] + $cOut, 2);
                        $companionsMap[$cId]['qty_return'] = round($companionsMap[$cId]['qty_return'] + $cRet, 2);
                        $companionsMap[$cId]['qty_current'] = round($companionsMap[$cId]['qty_current'] + $cCurr, 2);
                    }
                }
            }

            $first->bom_type = $bomType;
            $first->companion_details = array_values($companionsMap);

            $searchStr = strtolower(
                $lotNo . ' ' .
                $bomType . ' ' .
                ($first->process->process_name ?? '') . ' ' .
                ($first->materialItem->part_no ?? '') . ' ' .
                ($first->materialItem->part_name ?? '') . ' ' .
                collect($companionsMap)->pluck('part_name')->implode(' ') . ' ' .
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

        $groups = $groupedLots;

        return view('lot_tracers.index', compact('lotTracers', 'groupedLots', 'groups', 'processes'));
    }

    public function finish(LotTracer $lotTracer)
    {
        $lotTracer->update([
            'qty_wip'    => 0,
            'status_lot' => 'FINISH',
        ]);

        return redirect()->route('lot_tracers.index')
            ->with('success', "Lot {$lotTracer->lot_number} berhasil ditutup (FINISH).");
    }
}