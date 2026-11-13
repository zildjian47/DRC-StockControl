<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\Process;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BomController extends Controller
{
    public static function evaluateMath($expr): float
    {
        if (is_numeric($expr)) {
            return round((float) $expr, 8);
        }

        $clean = str_replace(',', '.', trim((string) $expr));
        $clean = preg_replace('/\s+/', '', $clean);

        if ($clean === '' || !preg_match('/^[0-9\.\+\-\*\/\(\)]+$/', $clean)) {
            return 0.0;
        }

        if (preg_match('/[\+\-\*\/]{2,}/', $clean)) {
            return 0.0;
        }

        try {
            $result = eval("return ($clean);");
            if (is_numeric($result) && is_finite((float) $result)) {
                return round((float) $result, 8);
            }
        } catch (\Throwable $e) {
            return 0.0;
        }

        return 0.0;
    }

    public function index()
    {
        $processes = Process::orderBy('process_name', 'asc')->get();

        $outputItems = Item::with('company')
            ->whereIn('type', ['FG', 'FG/KOMPONEN', 'KOMPONEN'])
            ->orderBy('part_name', 'asc')
            ->get();

        $inputItems = Item::with('company')
            ->whereIn('type', ['RM', 'KOMPONEN', 'FG/KOMPONEN'])
            ->orderBy('part_name', 'asc')
            ->get();

        $boms = Bom::with(['process', 'outputItem.company', 'inputItem.company'])
            ->orderBy('process_id', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $groupedBoms = $boms->groupBy(function ($b) {
            return !empty($b->bom_group)
                ? $b->bom_group
                : ('LEGACY-' . $b->process_id . '-' . $b->output_item_id);
        })->map(function ($rows, $groupKey) {
            $first = $rows->first();
            $uIn = $rows->pluck('input_item_id')->unique()->count();
            $uOut = $rows->pluck('output_item_id')->unique()->count();

            if (str_starts_with($groupKey, 'CB-') || ($uIn > 1 && $uOut > 1)) {
                $bomType = 'KOMBINASI';
            } elseif (str_starts_with($groupKey, 'MO-') || ($uIn === 1 && $uOut > 1)) {
                $bomType = 'MULTI-OUTPUT';
            } elseif (str_starts_with($groupKey, 'MI-') || ($uIn > 1 && $uOut === 1)) {
                $bomType = 'MULTI-INPUT';
            } else {
                $bomType = 'SINGLE';
            }

            $inputsSummary = $rows->groupBy('input_item_id')->map(function ($inRows) {
                $f = $inRows->first();
                return [
                    'item'       => $f->inputItem,
                    'is_trigger' => (bool) $inRows->contains('is_trigger', true),
                    'usages'     => $inRows->map(fn ($r) => [
                        'output_part_no'   => $r->outputItem->part_no ?: '-',
                        'output_part_name' => $r->outputItem->part_name ?? '-',
                        'output_uom'       => $r->outputItem->uom ?? 'PCS',
                        'qty_usage'        => (float) $r->qty_usage,
                    ])->values(),
                ];
            })->sortByDesc('is_trigger')->values();

            $outputsSummary = $rows->groupBy('output_item_id')->map(function ($outRows) {
                $f = $outRows->first();
                return [
                    'item'   => $f->outputItem,
                    'inputs' => $outRows->map(fn ($r) => [
                        'input_part_no'   => $r->inputItem->part_no ?: '-',
                        'input_part_name' => $r->inputItem->part_name ?? '-',
                        'input_uom'       => $r->inputItem->uom ?? '',
                        'is_trigger'      => (bool) $r->is_trigger,
                        'qty_usage'       => (float) $r->qty_usage,
                    ])->sortByDesc('is_trigger')->values(),
                ];
            })->values();

            $searchStr = strtolower(
                $groupKey . ' ' .
                $bomType . ' ' .
                ($first->process->process_name ?? '') . ' ' .
                $rows->map(fn ($r) => ($r->inputItem->part_no ?? '') . ' ' . ($r->inputItem->part_name ?? '') . ' ' . ($r->outputItem->part_no ?? '') . ' ' . ($r->outputItem->part_name ?? ''))->implode(' ')
            );

            return [
                'group_key'    => $groupKey,
                'bom_type'     => $bomType,
                'first_id'     => $first->id,
                'process_id'   => $first->process_id,
                'process_name' => $first->process->process_name ?? '-',
                'rows'         => $rows,
                'inputs'       => $inputsSummary,
                'outputs'      => $outputsSummary,
                'search_index' => $searchStr,
            ];
        })->values();

        $groups = $groupedBoms;

        return view('boms.index', compact('boms', 'groupedBoms', 'groups', 'processes', 'outputItems', 'inputItems'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'process_id' => 'required|exists:processes,id',
            'inputs'     => 'required|array|min:1',
            'outputs'    => 'required|array|min:1',
        ]);

        $processId = (int) $request->process_id;
        $rawInputs = collect($request->input('inputs', []))
            ->filter(fn ($i) => !empty($i['item_id']))
            ->values();

        $rawOutputs = collect($request->input('outputs', []))
            ->filter(fn ($o) => !empty($o['item_id']))
            ->values();

        if ($rawInputs->isEmpty() || $rawOutputs->isEmpty()) {
            return back()->withInput()->with('error', 'Minimal wajib memilih 1 Material Input dan 1 Target Output.');
        }

        if ($rawInputs->pluck('item_id')->unique()->count() !== $rawInputs->count()) {
            return back()->withInput()->with('error', 'Terdapat Material Input yang dipilih ganda dalam 1 resep.');
        }

        if ($rawOutputs->pluck('item_id')->unique()->count() !== $rawOutputs->count()) {
            return back()->withInput()->with('error', 'Terdapat Target Output yang dipilih ganda dalam 1 resep.');
        }

        $triggerIndex = (int) $request->input('trigger_index', 0);
        if (!isset($rawInputs[$triggerIndex])) {
            $triggerIndex = 0;
        }

        $inCount = $rawInputs->count();
        $outCount = $rawOutputs->count();

        if ($inCount === 1 && $outCount === 1) {
            $prefix = 'SG';
        } elseif ($inCount > 1 && $outCount === 1) {
            $prefix = 'MI';
        } elseif ($inCount === 1 && $outCount > 1) {
            $prefix = 'MO';
        } else {
            $prefix = 'CB';
        }

        $bomGroup = $prefix . '-' . $processId . '-' . strtoupper(Str::random(5));
        $rowsToInsert = [];

        if ($outCount === 1) {
            $outItemId = (int) $rawOutputs[0]['item_id'];
            foreach ($rawInputs as $idx => $inData) {
                $inItemId = (int) $inData['item_id'];
                if ($inItemId === $outItemId) {
                    return back()->withInput()->with('error', 'Material Input dan Target Output tidak boleh item yang sama.');
                }

                $qtyVal = self::evaluateMath($inData['qty_usage'] ?? 0);
                if ($qtyVal <= 0) {
                    return back()->withInput()->with('error', 'Qty Usage pada setiap Material Input wajib lebih besar dari 0.');
                }

                $rowsToInsert[] = [
                    'bom_group'      => $bomGroup,
                    'process_id'     => $processId,
                    'output_item_id' => $outItemId,
                    'input_item_id'  => $inItemId,
                    'qty_usage'      => $qtyVal,
                    'is_trigger'     => ($idx === $triggerIndex),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }
        } else {
            foreach ($rawOutputs as $oIdx => $outData) {
                $outItemId = (int) $outData['item_id'];
                $usagesMap = $outData['usages'] ?? [];

                foreach ($rawInputs as $iIdx => $inData) {
                    $inItemId = (int) $inData['item_id'];
                    if ($inItemId === $outItemId) {
                        return back()->withInput()->with('error', 'Material Input dan Target Output tidak boleh item yang sama.');
                    }

                    $isTrigger = ($iIdx === $triggerIndex);
                    $rawExpr = $usagesMap[$iIdx] ?? ($inData['qty_usage'] ?? 0);
                    $qtyVal = self::evaluateMath($rawExpr);

                    if ($isTrigger && $qtyVal <= 0) {
                        return back()->withInput()->with('error', 'Qty Usage untuk Material Trigger wajib lebih besar dari 0 pada setiap Target Output.');
                    }

                    if ($qtyVal > 0) {
                        $rowsToInsert[] = [
                            'bom_group'      => $bomGroup,
                            'process_id'     => $processId,
                            'output_item_id' => $outItemId,
                            'input_item_id'  => $inItemId,
                            'qty_usage'      => $qtyVal,
                            'is_trigger'     => $isTrigger,
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ];
                    }
                }
            }
        }

        DB::transaction(function () use ($rowsToInsert) {
            Bom::insert($rowsToInsert);
            InventoryStock::with('item')->get()->each(fn ($s) => $s->updateDynamicStatus());
        });

        return redirect()->route('boms.index')->with('success', "Resep BOM ({$bomGroup}) berhasil disimpan.");
    }

    public function destroy(Bom $bom)
    {
        DB::transaction(function () use ($bom) {
            if (!empty($bom->bom_group)) {
                Bom::where('bom_group', $bom->bom_group)->delete();
            } else {
                Bom::where('process_id', $bom->process_id)
                    ->where('output_item_id', $bom->output_item_id)
                    ->delete();
            }
            InventoryStock::with('item')->get()->each(fn ($s) => $s->updateDynamicStatus());
        });

        return redirect()->route('boms.index')->with('success', 'Kelompok resep BOM berhasil dihapus.');
    }
}