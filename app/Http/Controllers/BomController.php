<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\LotTracer;
use App\Models\MaterialOut;
use App\Models\MaterialReturn;
use App\Models\Process;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BomController extends Controller
{
    private const ALLOWED_INPUT_TYPES  = ['RM', 'KOMPONEN', 'FG/KOMPONEN'];
    private const ALLOWED_OUTPUT_TYPES = ['FG', 'FG/KOMPONEN', 'KOMPONEN'];

    public static function evaluateMath($expr): float
    {
        if (is_numeric($expr)) {
            $val = (float) $expr;
            return ($val > 0 && is_finite($val)) ? round($val, 8) : 0.0;
        }

        $parsed = Bom::parseCalculatorValue($expr);
        return ($parsed !== null && is_finite($parsed)) ? round($parsed, 8) : 0.0;
    }

    public function index()
    {
        $processes = Process::orderBy('process_name', 'asc')->get();

        $outputItems = Item::with('company')
            ->whereIn('type', self::ALLOWED_OUTPUT_TYPES)
            ->orderBy('part_name', 'asc')
            ->get();

        $inputItems = Item::with('company')
            ->whereIn('type', self::ALLOWED_INPUT_TYPES)
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
            $uIn   = $rows->pluck('input_item_id')->unique()->count();
            $uOut  = $rows->pluck('output_item_id')->unique()->count();

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
                        'output_part_no'    => $r->outputItem?->part_no ?: '-',
                        'output_part_name'  => $r->outputItem?->part_name ?? '-',
                        'output_uom'        => $r->outputItem?->uom ?? 'PCS',
                        'qty_usage'         => (float) $r->qty_usage,
                        'qty_usage_display' => $r->formatted_qty_usage,
                    ])->values(),
                ];
            })->sortByDesc('is_trigger')->values();

            $outputsSummary = $rows->groupBy('output_item_id')->map(function ($outRows) {
                $f = $outRows->first();
                return [
                    'item'   => $f->outputItem,
                    'inputs' => $outRows->map(fn ($r) => [
                        'input_part_no'     => $r->inputItem?->part_no ?: '-',
                        'input_part_name'   => $r->inputItem?->part_name ?? '-',
                        'input_uom'         => $r->inputItem?->uom ?? '',
                        'is_trigger'        => (bool) $r->is_trigger,
                        'qty_usage'         => (float) $r->qty_usage,
                        'qty_usage_display' => $r->formatted_qty_usage,
                    ])->sortByDesc('is_trigger')->values(),
                ];
            })->values();

            $searchStr = strtolower(
                $groupKey . ' ' .
                $bomType . ' ' .
                ($first->process?->process_name ?? '') . ' ' .
                $rows->map(fn ($r) => ($r->inputItem?->part_no ?? '') . ' ' . ($r->inputItem?->part_name ?? '') . ' ' . ($r->outputItem?->part_no ?? '') . ' ' . ($r->outputItem?->part_name ?? ''))->implode(' ')
            );

            return [
                'group_key'    => $groupKey,
                'bom_type'     => $bomType,
                'first_id'     => $first->id,
                'process_id'   => $first->process_id,
                'process_name' => $first->process?->process_name ?? '-',
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
            'process_id'        => 'required|exists:processes,id',
            'inputs'            => 'required|array|min:1',
            'inputs.*.item_id'  => ['required', 'integer', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'outputs'           => 'required|array|min:1',
            'outputs.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->whereNull('deleted_at')],
        ], [
            'inputs.*.item_id.exists'  => 'Material Input tidak ditemukan atau sudah dinonaktifkan.',
            'outputs.*.item_id.exists' => 'Target Output tidak ditemukan atau sudah dinonaktifkan.',
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

        $inputIds  = $rawInputs->pluck('item_id')->map(fn ($v) => (int) $v);
        $outputIds = $rawOutputs->pluck('item_id')->map(fn ($v) => (int) $v);

        if ($inputIds->unique()->count() !== $rawInputs->count()) {
            return back()->withInput()->with('error', 'Terdapat Material Input yang dipilih ganda dalam 1 resep.');
        }

        if ($outputIds->unique()->count() !== $rawOutputs->count()) {
            return back()->withInput()->with('error', 'Terdapat Target Output yang dipilih ganda dalam 1 resep.');
        }

        // Validasi keaktifan dan kesesuaian tipe item Input vs Output di backend
        $itemsById = Item::whereIn('id', $inputIds->merge($outputIds)->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($inputIds as $inId) {
            $inItem = $itemsById->get($inId);
            if (!$inItem || !in_array($inItem->type, self::ALLOWED_INPUT_TYPES, true)) {
                $label = $inItem ? "{$inItem->part_name} ({$inItem->type})" : "ID #{$inId}";
                return back()->withInput()->with('error', "Item {$label} tidak dapat dijadikan Material Input (wajib RM, KOMPONEN, atau FG/KOMPONEN aktif).");
            }
        }

        foreach ($outputIds as $outId) {
            $outItem = $itemsById->get($outId);
            if (!$outItem || !in_array($outItem->type, self::ALLOWED_OUTPUT_TYPES, true)) {
                $label = $outItem ? "{$outItem->part_name} ({$outItem->type})" : "ID #{$outId}";
                return back()->withInput()->with('error', "Item {$label} tidak dapat dijadikan Target Output (wajib FG, FG/KOMPONEN, atau KOMPONEN aktif).");
            }
        }

        $triggerIndex = (int) $request->input('trigger_index', 0);
        if (!isset($rawInputs[$triggerIndex])) {
            $triggerIndex = 0;
        }

        $inCount  = $rawInputs->count();
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

        $bomGroup     = $prefix . '-' . $processId . '-' . strtoupper(Str::random(5));
        $rowsToInsert = [];

        if ($outCount === 1) {
            $outItemId = (int) $rawOutputs[0]['item_id'];
            foreach ($rawInputs as $idx => $inData) {
                $inItemId = (int) $inData['item_id'];
                if ($inItemId === $outItemId) {
                    return back()->withInput()->with('error', 'Material Input dan Target Output tidak boleh item yang sama.');
                }

                $rawExpr = $inData['qty_usage'] ?? 0;
                $qtyVal  = self::evaluateMath($rawExpr);
                if ($qtyVal <= 0 || $qtyVal > Item::MAX_QTY) {
                    return back()->withInput()->with('error', 'Qty Usage pada setiap Material Input wajib valid (> 0 dan maksimal 99.999.999,99).');
                }

                $rowsToInsert[] = [
                    'bom_group'         => $bomGroup,
                    'process_id'        => $processId,
                    'output_item_id'    => $outItemId,
                    'input_item_id'     => $inItemId,
                    'qty_usage'         => $qtyVal,
                    'qty_usage_display' => Bom::formatDisplayInput($rawExpr, $qtyVal),
                    'is_trigger'        => ($idx === $triggerIndex),
                    'created_at'        => now(),
                    'updated_at'        => now(),
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
                    $rawExpr   = $usagesMap[$iIdx] ?? ($inData['qty_usage'] ?? 0);
                    $qtyVal    = self::evaluateMath($rawExpr);

                    if ($isTrigger && ($qtyVal <= 0 || $qtyVal > Item::MAX_QTY)) {
                        return back()->withInput()->with('error', 'Qty Usage untuk Material Trigger wajib valid (> 0 dan maksimal 99.999.999,99) pada setiap Target Output.');
                    }

                    if ($qtyVal > Item::MAX_QTY) {
                        return back()->withInput()->with('error', 'Qty Usage pada Material Pendamping melebihi batas maksimal 99.999.999,99.');
                    }

                    if ($qtyVal > 0) {
                        $rowsToInsert[] = [
                            'bom_group'         => $bomGroup,
                            'process_id'        => $processId,
                            'output_item_id'    => $outItemId,
                            'input_item_id'     => $inItemId,
                            'qty_usage'         => $qtyVal,
                            'qty_usage_display' => Bom::formatDisplayInput($rawExpr, $qtyVal),
                            'is_trigger'        => $isTrigger,
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ];
                    }
                }
            }
        }

        if ($this->hasCircularDependency($rowsToInsert)) {
            return back()->withInput()->with('error', 'Resep BOM ditolak karena membentuk siklus melingkar (Circular BOM) antar item.');
        }

        DB::transaction(function () use ($rowsToInsert) {
            Bom::insert($rowsToInsert);
            InventoryStock::with('item')->get()->each(fn ($s) => $s->updateDynamicStatus());
        });

        return redirect()->route('boms.index')->with('success', "Resep BOM ({$bomGroup}) berhasil disimpan.");
    }

    public function destroy(Bom $bom)
    {
        $groupRows = !empty($bom->bom_group)
            ? Bom::where('bom_group', $bom->bom_group)->get()
            : Bom::where('process_id', $bom->process_id)
                ->where('output_item_id', $bom->output_item_id)
                ->get();

        $isUsed = false;

        if (!empty($bom->bom_group)) {
            $isUsed = LotTracer::where('bom_group', $bom->bom_group)->exists()
                || MaterialOut::where('bom_group', $bom->bom_group)->exists()
                || MaterialReturn::where('bom_group', $bom->bom_group)->exists();
        }

        if (!$isUsed) {
            foreach ($groupRows->where('is_trigger', true) as $row) {
                $usedByTrigger = LotTracer::where('process_id', $row->process_id)
                    ->where('material_item_id', $row->input_item_id)
                    ->where('target_item_id', $row->output_item_id)
                    ->exists()
                    || MaterialOut::where('material_item_id', $row->input_item_id)
                        ->where('target_item_id', $row->output_item_id)
                        ->exists()
                    || MaterialReturn::where('material_item_id', $row->input_item_id)
                        ->where('target_item_id', $row->output_item_id)
                        ->exists();

                if ($usedByTrigger) {
                    $isUsed = true;
                    break;
                }
            }
        }

        if ($isUsed) {
            return back()->with('error', 'Resep BOM tidak dapat dihapus karena sudah digunakan pada riwayat Lot Tracer atau transaksi produksi.');
        }

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

    /**
     * Deteksi apakah penambahan baris BOM baru membentuk siklus melingkar (langsung maupun tak langsung, mis. A -> B -> A).
     *
     * @param  array<int, array<string, mixed>>  $candidateRows
     */
    private function hasCircularDependency(array $candidateRows): bool
    {
        $adj = [];

        foreach (Bom::select('input_item_id', 'output_item_id')->get() as $edge) {
            $adj[(int) $edge->input_item_id][] = (int) $edge->output_item_id;
        }

        foreach ($candidateRows as $row) {
            $adj[(int) $row['input_item_id']][] = (int) $row['output_item_id'];
        }

        foreach ($candidateRows as $row) {
            $inId  = (int) $row['input_item_id'];
            $outId = (int) $row['output_item_id'];

            $visited = [];
            $queue   = [$outId];

            while (!empty($queue)) {
                $curr = array_shift($queue);
                if ($curr === $inId) {
                    return true;
                }
                if (isset($visited[$curr])) {
                    continue;
                }
                $visited[$curr] = true;

                foreach ($adj[$curr] ?? [] as $next) {
                    if (!isset($visited[$next])) {
                        $queue[] = $next;
                    }
                }
            }
        }

        return false;
    }
}