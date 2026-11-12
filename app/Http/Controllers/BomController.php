<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\Item;
use App\Models\Process;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BomController extends Controller
{
    public function index(Request $request)
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
            ->orderBy('bom_group', 'asc')
            ->orderByDesc('is_trigger')
            ->orderBy('id', 'asc')
            ->get();

        $groupedBoms = $boms->groupBy(function ($bom) {
            return $bom->bom_group ?: ('SG-' . $bom->process_id . '-' . $bom->output_item_id);
        })->map(function ($rows, $groupKey) {
            $first = $rows->first();
            $uniqueInputs = $rows->pluck('input_item_id')->unique()->count();
            $uniqueOutputs = $rows->pluck('output_item_id')->unique()->count();

            if (str_starts_with($groupKey, 'CB-') || ($uniqueInputs > 1 && $uniqueOutputs > 1)) {
                $type = 'KOMBINASI';
            } elseif (str_starts_with($groupKey, 'MO-') || ($uniqueInputs === 1 && $uniqueOutputs > 1)) {
                $type = 'MULTI-OUTPUT';
            } elseif (str_starts_with($groupKey, 'MI-') || ($uniqueInputs > 1 && $uniqueOutputs === 1)) {
                $type = 'MULTI-INPUT';
            } else {
                $type = 'SINGLE';
            }

            $outputs = $rows->groupBy('output_item_id')->map(function ($outRows) {
                $outItem = $outRows->first()->outputItem;
                $triggerRow = $outRows->firstWhere('is_trigger', true) ?? $outRows->first();
                return [
                    'output_item' => $outItem,
                    'trigger_row' => $triggerRow,
                    'inputs'      => $outRows->values(),
                ];
            })->values();

            $inputs = $rows->groupBy('input_item_id')->map(function ($inRows) {
                $inItem = $inRows->first()->inputItem;
                $isTrigger = (bool) $inRows->contains('is_trigger', true);
                return [
                    'input_item' => $inItem,
                    'is_trigger' => $isTrigger,
                    'usages'     => $inRows->values(),
                ];
            })->sortByDesc('is_trigger')->values();

            $searchIndex = strtolower(
                ($first->process->process_name ?? '') . ' ' .
                $type . ' ' .
                $groupKey . ' ' .
                $rows->map(fn ($r) => ($r->inputItem->part_no ?? '') . ' ' . ($r->inputItem->part_name ?? '') . ' ' . ($r->outputItem->part_no ?? '') . ' ' . ($r->outputItem->part_name ?? ''))->implode(' ')
            );

            return [
                'group_key'    => $groupKey,
                'type'         => $type,
                'process_id'   => $first->process_id,
                'process_name' => $first->process->process_name ?? '-',
                'rows'         => $rows->values(),
                'inputs'       => $inputs,
                'outputs'      => $outputs,
                'first_id'     => $first->id,
                'search_index' => $searchIndex,
            ];
        })->values();

        $groups = $groupedBoms;

        return view('boms.index', compact(
            'boms',
            'groupedBoms',
            'groups',
            'processes',
            'outputItems',
            'inputItems'
        ));
    }

    public static function evaluateMath($expression): float
    {
        $expr = str_replace([',', ' '], ['.', ''], trim((string) $expression));
        if ($expr === '') {
            return 0;
        }
        if (is_numeric($expr)) {
            return round((float) $expr, 8);
        }
        if (!preg_match('/^[0-9\.\+\-\*\/\(\)]+$/', $expr)) {
            return -1;
        }
        try {
            $tokens = preg_split('/([\+\-\*\/\(\)])/', $expr, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $outputQueue = [];
            $operatorStack = [];
            $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];

            foreach ($tokens as $idx => $token) {
                if (is_numeric($token)) {
                    $outputQueue[] = (float) $token;
                } elseif (isset($precedence[$token])) {
                    if ($token === '-' && ($idx === 0 || in_array($tokens[$idx - 1], ['+', '-', '*', '/', '('], true))) {
                        $outputQueue[] = 0.0;
                    }
                    while (
                        !empty($operatorStack) &&
                        end($operatorStack) !== '(' &&
                        ($precedence[end($operatorStack)] ?? 0) >= $precedence[$token]
                    ) {
                        $outputQueue[] = array_pop($operatorStack);
                    }
                    $operatorStack[] = $token;
                } elseif ($token === '(') {
                    $operatorStack[] = $token;
                } elseif ($token === ')') {
                    while (!empty($operatorStack) && end($operatorStack) !== '(') {
                        $outputQueue[] = array_pop($operatorStack);
                    }
                    if (!empty($operatorStack) && end($operatorStack) === '(') {
                        array_pop($operatorStack);
                    }
                }
            }

            while (!empty($operatorStack)) {
                $op = array_pop($operatorStack);
                if ($op === '(' || $op === ')') {
                    return -1;
                }
                $outputQueue[] = $op;
            }

            $evalStack = [];
            foreach ($outputQueue as $item) {
                if (is_float($item) || is_int($item)) {
                    $evalStack[] = (float) $item;
                } else {
                    if (count($evalStack) < 2) {
                        return -1;
                    }
                    $b = array_pop($evalStack);
                    $a = array_pop($evalStack);
                    if ($item === '+') {
                        $evalStack[] = $a + $b;
                    } elseif ($item === '-') {
                        $evalStack[] = $a - $b;
                    } elseif ($item === '*') {
                        $evalStack[] = $a * $b;
                    } elseif ($item === '/') {
                        if ($b == 0) {
                            return -1;
                        }
                        $evalStack[] = $a / $b;
                    }
                }
            }

            return count($evalStack) === 1 ? round((float) $evalStack[0], 8) : -1;
        } catch (\Throwable $e) {
            return -1;
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'process_id'               => 'required|exists:processes,id',
            'trigger_index'            => 'required|integer|min:0',
            'inputs'                   => 'required|array|min:1',
            'inputs.*.input_item_id'   => 'required|exists:items,id',
            'outputs'                  => 'required|array|min:1',
            'outputs.*.output_item_id' => 'required|exists:items,id',
        ]);

        $processId = (int) $request->process_id;
        $triggerIdx = (int) $request->trigger_index;
        $inputs = array_values($request->inputs);
        $outputs = array_values($request->outputs);

        if (!isset($inputs[$triggerIdx])) {
            return back()->withInput()->with('error', 'Pilih tepat 1 Material Input sebagai Trigger Utama.');
        }

        $inputIds = array_column($inputs, 'input_item_id');
        if (count($inputIds) !== count(array_unique($inputIds))) {
            return back()->withInput()->with('error', 'Terdapat Material Input yang dipilih ganda dalam 1 resep BOM.');
        }

        $outputIds = array_column($outputs, 'output_item_id');
        if (count($outputIds) !== count(array_unique($outputIds))) {
            return back()->withInput()->with('error', 'Terdapat Target Output yang dipilih ganda dalam 1 resep BOM.');
        }

        foreach ($inputIds as $inId) {
            if (in_array($inId, $outputIds)) {
                return back()->withInput()->with('error', 'Item Input dan Output tidak boleh menggunakan barang yang sama.');
            }
        }

        $numInputs = count($inputs);
        $numOutputs = count($outputs);

        if ($numInputs === 1 && $numOutputs === 1) {
            $prefix = 'SG';
        } elseif ($numInputs > 1 && $numOutputs === 1) {
            $prefix = 'MI';
        } elseif ($numInputs === 1 && $numOutputs > 1) {
            $prefix = 'MO';
        } else {
            $prefix = 'CB';
        }

        $groupCode = $prefix . '-' . $processId . '-' . now()->format('His') . '-' . rand(10, 99);
        $recordsToInsert = [];

        foreach ($outputs as $oIdx => $outData) {
            $outId = (int) $outData['output_item_id'];

            foreach ($inputs as $iIdx => $inData) {
                $inId = (int) $inData['input_item_id'];
                $isTrigger = ($iIdx === $triggerIdx);

                if ($numOutputs === 1) {
                    $rawFormula = $inData['qty_usage'] ?? '';
                } else {
                    $rawFormula = $outData['usages'][$iIdx] ?? ($inData['qty_usage'] ?? '');
                }

                $rawTrimmed = trim((string) $rawFormula);

                // Pada KOMBINASI, komponen pendamping (bukan trigger) boleh diisi 0 atau dikosongkan jika tidak dipakai oleh salah satu Output
                if (!$isTrigger && $numOutputs > 1 && ($rawTrimmed === '' || $rawTrimmed === '0')) {
                    continue;
                }

                $qtyVal = self::evaluateMath($rawTrimmed);
                if ($qtyVal <= 0) {
                    $inName = Item::find($inId)->part_name ?? 'Input';
                    $outName = Item::find($outId)->part_name ?? 'Output';
                    return back()->withInput()->with('error', "Qty Usage untuk [{$inName} -> {$outName}] tidak valid atau bernilai 0.");
                }

                $recordsToInsert[] = [
                    'bom_group'      => $groupCode,
                    'process_id'     => $processId,
                    'output_item_id' => $outId,
                    'input_item_id'  => $inId,
                    'qty_usage'      => $qtyVal,
                    'is_trigger'     => $isTrigger,
                ];
            }
        }

        DB::transaction(function () use ($recordsToInsert, $numOutputs, $processId) {
            foreach ($recordsToInsert as $rec) {
                if ($numOutputs === 1) {
                    Bom::where('process_id', $processId)
                        ->where('output_item_id', $rec['output_item_id'])
                        ->where('input_item_id', $rec['input_item_id'])
                        ->delete();
                }
                Bom::create($rec);
            }
        });

        return redirect()->route('boms.index')->with('success', 'Resep BOM berhasil disimpan.');
    }

    public function destroy(Bom $bom)
    {
        if (!empty($bom->bom_group)) {
            Bom::where('bom_group', $bom->bom_group)->delete();
            return redirect()->route('boms.index')->with('success', 'Seluruh kelompok resep BOM berhasil dihapus.');
        }

        $bom->delete();
        return redirect()->route('boms.index')->with('success', 'Baris BOM berhasil dihapus.');
    }
}