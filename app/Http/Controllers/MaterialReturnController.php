<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\LotTracer;
use App\Models\MaterialReturn;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MaterialReturnController extends Controller
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

        $uIn  = $groupRows->pluck('input_item_id')->unique()->count();
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
        if ($request->query('mode') === 'scanner') {
            $allScansData = MaterialReturn::with(['materialItem', 'targetItem', 'user'])
                ->orderBy('date_time', 'desc')
                ->orderBy('id', 'desc')
                ->limit(500)
                ->get()
                ->groupBy(fn ($r) => !empty($r->return_batch) ? $r->return_batch : ('LEGACY-' . $r->lot_number))
                ->map(function ($rows, $batchKey) {
                    $s       = $rows->first();
                    $dt      = Carbon::parse($s->date_time);
                    $targets = $rows->map(function ($r) {
                        $no = $r->targetItem?->part_no ?? '';
                        $nm = $r->targetItem?->part_name ?? '-';
                        return $no ? "{$no} — {$nm}" : $nm;
                    })->unique()->values()->implode(', ');

                    return [
                        'id'         => $s->id,
                        'batch_key'  => $batchKey,
                        'date'       => $dt->format('Y-m-d'),
                        'time'       => $dt->format('H:i'),
                        'date_fmt'   => $dt->format('d M Y, H:i'),
                        'lot_number' => $s->lot_number,
                        'mat_no'     => $s->materialItem?->part_no ?: '-',
                        'mat_name'   => $s->materialItem?->part_name ?? '-',
                        'mat_uom'    => $s->materialItem?->uom ?? '',
                        'targets'    => $targets,
                        'qty'        => (float) $s->qty,
                        'user_name'  => $s->user?->name ?? 'Operator',
                    ];
                })->values();

            return view('material_returns.scanner', compact('allScansData'));
        }

        $allBoms = Bom::with(['process', 'inputItem', 'outputItem'])->get();

        $materialReturns = MaterialReturn::with(['materialItem.company', 'targetItem.company', 'user', 'correctedBy'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($materialReturns as $row) {
            $meta          = $this->resolveBomMeta((int) $row->material_item_id, (int) $row->target_item_id, $allBoms, $row->bom_group);
            $row->bom_type = $meta['bom_type'];
            $rawEst        = $meta['qty_usage'] > 0 ? ((float) $row->qty / $meta['qty_usage']) : 0;
            $estRet        = abs($rawEst - round($rawEst)) < 0.001 ? round($rawEst) : $rawEst;

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

        $stockMap = InventoryStock::whereIn('item_id', $materialReturns->pluck('material_item_id')->unique())
            ->pluck('current_stock', 'item_id');

        $lotRowsByKey = LotTracer::whereIn('lot_number', $materialReturns->pluck('lot_number')->unique())
            ->get()
            ->groupBy(fn ($l) => $l->lot_number . '|' . $l->material_item_id);

        $lotRemainingMap = [];
        $lotFinishMap    = [];
        foreach ($lotRowsByKey as $k => $lRows) {
            $minRem = null;
            foreach ($lRows as $lt) {
                $meta       = $this->resolveBomMeta((int) $lt->material_item_id, (int) $lt->target_item_id, $allBoms, $lt->bom_group);
                $usage      = $meta['qty_usage'] > 0 ? (float) $meta['qty_usage'] : 1.0;
                $usedByProd = (float) $lt->actual_qty_prod * $usage;
                $rem        = max(0, round((float) $lt->qty_material_out - (float) $lt->qty_material_return - $usedByProd, 2));
                if ($minRem === null || $rem < $minRem) {
                    $minRem = $rem;
                }
            }
            $lotRemainingMap[$k] = $minRem ?? 0.0;
            $lotFinishMap[$k]    = $lRows->contains('status_lot', 'FINISH');
        }

        $maxAllowedMap  = [];
        $groupedReturns = $materialReturns
            ->groupBy(fn ($r) => !empty($r->return_batch) ? $r->return_batch : ('LEGACY-' . $r->lot_number))
            ->map(function ($rows, $batchKey) use ($lotRemainingMap, $lotFinishMap, &$maxAllowedMap) {
                $first   = $rows->first();
                $lotNo   = $first->lot_number;
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
                    'part_no'   => $r->targetItem?->part_no ?: '-',
                    'part_name' => $r->targetItem?->part_name ?? '-',
                    'type'      => $r->targetItem?->type ?? '-',
                    'uom'       => $r->targetItem?->uom ?? 'PCS',
                ])->values();

                $searchStr = strtolower(
                    $lotNo . ' ' .
                    $bomType . ' ' .
                    ($first->user?->name ?? 'Operator') . ' ' .
                    ($first->materialItem?->part_no ?? '') . ' ' .
                    ($first->materialItem?->part_name ?? '') . ' ' .
                    collect($companionsMerged)->pluck('part_name')->implode(' ') . ' ' .
                    $outputs->pluck('part_no')->implode(' ') . ' ' .
                    $outputs->pluck('part_name')->implode(' ')
                );

                $isCorrected              = $rows->contains('qty_check', 'DIKOREKSI');
                $auditRow                 = $rows->first(fn ($r) => $r->qty_check === 'DIKOREKSI') ?? $first;
                $lotKey                   = $lotNo . '|' . $first->material_item_id;
                $thisQty                  = (float) $first->qty;
                $maxAllowed               = round($thisQty + (float) ($lotRemainingMap[$lotKey] ?? 0.0), 2);
                $maxAllowedMap[$batchKey] = $maxAllowed;

                return [
                    'batch_key'         => $batchKey,
                    'lot_number'        => $lotNo,
                    'bom_type'          => $bomType,
                    'first'             => $first,
                    'rows'              => $rows,
                    'qty_trigger'       => $thisQty,
                    'companions'        => array_values($companionsMerged),
                    'outputs'           => $outputs,
                    'status_label'      => $isCorrected ? 'DIKOREKSI' : 'TERCATAT',
                    'max_allowed'       => $maxAllowed,
                    'lot_is_finish'     => (bool) ($lotFinishMap[$lotKey] ?? false),
                    'qty_original'      => $auditRow->qty_original !== null ? floatval($auditRow->qty_original) : null,
                    'corrected_by_name' => $auditRow->correctedBy?->name ?? null,
                    'corrected_at_fmt'  => $auditRow->corrected_at ? Carbon::parse($auditRow->corrected_at)->format('d M Y, H:i') : null,
                    'correction_notes'  => $auditRow->correction_notes ?: null,
                    'search_index'      => $searchStr,
                    'date_only'         => Carbon::parse($first->date_time)->format('Y-m-d'),
                    'time_only'         => Carbon::parse($first->date_time)->format('H:i'),
                ];
            })->values();

        $groups = $groupedReturns;

        return view('material_returns.index', compact('materialReturns', 'groupedReturns', 'groups', 'stockMap', 'maxAllowedMap', 'lotFinishMap'));
    }

    public function lookup(Request $request)
    {
        $step    = (int) $request->query('step', 1);
        $rawCode = trim((string) $request->query('code', ''));

        if ($rawCode === '') {
            return response()->json([
                'ok'      => false,
                'message' => 'Kode QR / Part wajib diisi.',
            ]);
        }

        $item = Item::findActiveByScanCode($rawCode, ['company', 'inventoryStock']);

        if (!$item) {
            return response()->json([
                'ok'      => false,
                'message' => "Kode \"{$rawCode}\" tidak ditemukan.",
            ]);
        }

        $formatItem = fn (Item $it) => [
            'id'           => $it->id,
            'part_no'      => $it->part_no ? strtoupper(trim($it->part_no)) : '',
            'part_name'    => strtoupper(trim($it->part_name)),
            'type'         => $it->type,
            'uom'          => $it->uom,
            'company_type' => $it->company->type_company ?? 'INTERNAL',
            'stock'        => $it->inventoryStock ? (float) $it->inventoryStock->current_stock : 0.0,
        ];

        $allBoms = Bom::all();

        if ($step === 1) {
            $candidateLotNos = LotTracer::where('material_item_id', $item->id)
                ->where('status_lot', 'ON PROGRESS')
                ->pluck('lot_number')
                ->unique()
                ->all();

            if (empty($candidateLotNos)) {
                return response()->json([
                    'ok'      => false,
                    'message' => "{$item->part_name} tidak memiliki Lot aktif untuk diretur.",
                ]);
            }

            $allLotRows    = LotTracer::whereIn('lot_number', $candidateLotNos)->get();
            $hasReturnable = false;

            foreach ($allLotRows->groupBy('lot_number') as $lotRows) {
                if ($lotRows->contains('status_lot', 'FINISH')) {
                    continue;
                }
                $first   = $lotRows->first();
                $maxUsed = 0.0;
                foreach ($lotRows as $lr) {
                    $meta  = $this->resolveBomMeta((int) $lr->material_item_id, (int) $lr->target_item_id, $allBoms, $lr->bom_group);
                    $usage = $meta['qty_usage'] > 0 ? (float) $meta['qty_usage'] : 1.0;
                    $used  = (float) $lr->actual_qty_prod * $usage;
                    if ($used > $maxUsed) {
                        $maxUsed = $used;
                    }
                }
                $remMat = round(max(0, (float) $first->qty_material_out - (float) $first->qty_material_return - $maxUsed), 2);
                if ($remMat > 0) {
                    $hasReturnable = true;
                    break;
                }
            }

            if (!$hasReturnable) {
                return response()->json([
                    'ok'      => false,
                    'message' => "{$item->part_name} tidak memiliki Lot aktif dengan sisa material untuk diretur.",
                ]);
            }

            return response()->json([
                'ok'   => true,
                'item' => $formatItem($item),
            ]);
        }

        // STEP 2: Validasi Target Output & muat daftar Lot aktif beserta sisa material terbaru (Multi-Return diizinkan selama ON PROGRESS)
        $materialItem = Item::with(['company', 'inventoryStock'])->find((int) $request->query('material_item_id', 0));
        if (!$materialItem) {
            return response()->json([
                'ok'      => false,
                'message' => 'Material Retur tidak ditemukan. Silakan ulangi dari Langkah 1.',
            ]);
        }

        $matchedLotNos = LotTracer::where('material_item_id', $materialItem->id)
            ->where('target_item_id', $item->id)
            ->where('status_lot', 'ON PROGRESS')
            ->pluck('lot_number')
            ->unique()
            ->all();

        if (empty($matchedLotNos)) {
            return response()->json([
                'ok'      => false,
                'message' => "Tidak ada Lot aktif untuk {$materialItem->part_name} → {$item->part_name}.",
            ]);
        }

        $allLotRows = LotTracer::with(['materialItem', 'targetItem'])
            ->whereIn('lot_number', $matchedLotNos)
            ->orderBy('id', 'desc')
            ->get();

        $lotsPayload = [];
        foreach ($allLotRows->groupBy('lot_number') as $lotNo => $lotRows) {
            if ($lotRows->contains('status_lot', 'FINISH')) {
                continue;
            }
            $first   = $lotRows->first();
            $maxUsed = 0.0;
            foreach ($lotRows as $lr) {
                $meta  = $this->resolveBomMeta((int) $lr->material_item_id, (int) $lr->target_item_id, $allBoms, $lr->bom_group);
                $usage = $meta['qty_usage'] > 0 ? (float) $meta['qty_usage'] : 1.0;
                $used  = (float) $lr->actual_qty_prod * $usage;
                if ($used > $maxUsed) {
                    $maxUsed = $used;
                }
            }
            $remMat = round(max(0, (float) $first->qty_material_out - (float) $first->qty_material_return - $maxUsed), 2);
            if ($remMat <= 0) {
                continue;
            }

            $targetRow     = $lotRows->firstWhere('target_item_id', $item->id) ?? $first;
            $lotsPayload[] = [
                'id'               => $targetRow->id,
                'lot_number'       => $lotNo,
                'material_item_id' => $targetRow->material_item_id,
                'target_item_id'   => $targetRow->target_item_id,
                'mat_out'          => (float) $targetRow->qty_material_out,
                'mat_saat_ini'     => $remMat,
                'target_name'      => $targetRow->targetItem?->part_name ?? $item->part_name,
                'mat_uom'          => $targetRow->materialItem?->uom ?? $materialItem->uom ?? '',
            ];
        }

        if (empty($lotsPayload)) {
            return response()->json([
                'ok'      => false,
                'message' => "Tidak ada Lot aktif dengan sisa material untuk {$materialItem->part_name} → {$item->part_name}.",
            ]);
        }

        return response()->json([
            'ok'            => true,
            'material_item' => $formatItem($materialItem),
            'item'          => $formatItem($item),
            'lots'          => array_values($lotsPayload),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'lot_number' => strtoupper(trim((string) $request->lot_number)),
        ]);

        $request->validate([
            'material_item_id' => ['required', 'integer', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'target_item_id'   => ['required', 'integer', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'lot_number'       => 'required|string',
            'qty'              => 'required',
        ], [
            'material_item_id.exists' => 'Material Retur tidak ditemukan atau sudah dinonaktifkan.',
            'target_item_id.exists'   => 'Target Output tidak ditemukan atau sudah dinonaktifkan.',
        ]);

        $qtyReturn = round(BomController::evaluateMath($request->qty), 2);
        if ($qtyReturn <= 0) {
            return back()->withInput()->with('error', 'Qty harus lebih dari 0.');
        }
        if ($qtyReturn > Item::MAX_QTY) {
            return back()->withInput()->with('error', 'Qty melebihi batas maksimal 99.999.999,99.');
        }

        $allBoms = Bom::with('inputItem')->get();
        $matUom  = '';

        try {
            DB::transaction(function () use ($request, $qtyReturn, $allBoms, &$matUom) {
                $lotRows = LotTracer::with('materialItem')
                    ->where('lot_number', $request->lot_number)
                    ->where('material_item_id', $request->material_item_id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($lotRows->isEmpty()) {
                    throw new \RuntimeException('Lot dan pasangan Material tidak ditemukan di Lot Tracer.');
                }

                if ($lotRows->contains('status_lot', 'FINISH')) {
                    throw new \RuntimeException("Lot {$request->lot_number} sudah berstatus FINISH.");
                }

                if (!$lotRows->contains('target_item_id', (int) $request->target_item_id)) {
                    throw new \RuntimeException('Target Output tidak sesuai dengan Lot yang dipilih.');
                }

                // Preload konteks sibling & BOM di atas baris yang sudah terkunci (anti-N+1)
                foreach ($lotRows as $lr) {
                    $lr->setPreloadedContext($lotRows, $allBoms);
                }

                $matUom        = $lotRows->first()?->materialItem?->uom ?? '';
                $maxReturnable = $lotRows->min(fn ($lt) => (float) $lt->material_saat_ini);
                if ($qtyReturn > $maxReturnable + 0.0001) {
                    $availTxt = trim(floatval($maxReturnable) . ' ' . $matUom);
                    throw new \RuntimeException("Qty melebihi sisa material di mesin (tersedia {$availTxt}).");
                }

                // Hitung total penambahan komponen pendamping lintas target terlebih dahulu
                $companionAdditions = [];
                $lotMetas           = [];

                foreach ($lotRows as $lot) {
                    $meta                = $this->resolveBomMeta((int) $lot->material_item_id, (int) $lot->target_item_id, $allBoms, $lot->bom_group);
                    $lotMetas[$lot->id]  = $meta;
                    $rawEstRet           = $meta['qty_usage'] > 0 ? ($qtyReturn / $meta['qty_usage']) : 0;
                    $estReturnedTarget   = abs($rawEstRet - round($rawEstRet)) < 0.001 ? round($rawEstRet) : $rawEstRet;

                    foreach ($meta['companions'] as $compBom) {
                        $cAdd = round($estReturnedTarget * (float) $compBom->qty_usage, 2);
                        if ($cAdd > 0) {
                            $cId                      = (int) $compBom->input_item_id;
                            $companionAdditions[$cId] = round(($companionAdditions[$cId] ?? 0.0) + $cAdd, 2);
                        }
                    }
                }

                // Urutkan ID item sebelum lockForUpdate() untuk mencegah deadlock
                $lockItemIds = array_values(array_unique(array_merge(
                    [(int) $request->material_item_id],
                    array_map('intval', array_keys($companionAdditions))
                )));
                sort($lockItemIds, SORT_NUMERIC);

                $lockedStocks = [];
                foreach ($lockItemIds as $itemId) {
                    InventoryStock::firstOrCreate(
                        ['item_id' => $itemId],
                        ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                    );
                    $lockedStocks[$itemId] = InventoryStock::where('item_id', $itemId)->lockForUpdate()->first();
                }

                $matStock     = $lockedStocks[(int) $request->material_item_id];
                $nextMatStock = round((float) $matStock->current_stock + $qtyReturn, 2);
                if ($nextMatStock > Item::MAX_QTY) {
                    throw new \RuntimeException('Total stok Material Trigger melebihi batas maksimal 99.999.999,99.');
                }

                foreach ($companionAdditions as $cId => $cAdd) {
                    $cStock        = $lockedStocks[(int) $cId];
                    $nextCompStock = round((float) $cStock->current_stock + $cAdd, 2);
                    if ($nextCompStock > Item::MAX_QTY) {
                        throw new \RuntimeException('Total stok komponen pendamping melebihi batas maksimal 99.999.999,99.');
                    }
                }

                $matStock->current_stock = $nextMatStock;
                $matStock->status_stock  = $matStock->calculateDynamicStatus();
                $matStock->save();

                foreach ($companionAdditions as $cId => $cAdd) {
                    $cStock                = $lockedStocks[(int) $cId];
                    $cStock->current_stock = round((float) $cStock->current_stock + $cAdd, 2);
                    $cStock->status_stock  = $cStock->calculateDynamicStatus();
                    $cStock->save();
                }

                $returnBatch = 'MR-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));

                foreach ($lotRows as $lot) {
                    $meta = $lotMetas[$lot->id];

                    MaterialReturn::create([
                        'date_time'        => now(),
                        'user_id'          => Auth::id(),
                        'material_item_id' => $lot->material_item_id,
                        'target_item_id'   => $lot->target_item_id,
                        'lot_number'       => $lot->lot_number,
                        'return_batch'     => $returnBatch,
                        'bom_group'        => $lot->bom_group ?: $meta['bom_group'],
                        'qty'              => $qtyReturn,
                        'qty_check'        => 'TERCATAT',
                    ]);

                    $newTotalReturn = round((float) $lot->qty_material_return + $qtyReturn, 2);
                    $newNetMat      = max(0, (float) $lot->qty_material_out - $newTotalReturn);
                    $rawEst         = $meta['qty_usage'] > 0 ? ($newNetMat / $meta['qty_usage']) : 0;
                    $newEst         = abs($rawEst - round($rawEst)) < 0.001 ? round($rawEst) : round($rawEst, 2);
                    $newWip         = $lot->status_lot === 'FINISH' ? 0 : round(max(0, $newEst - (float) $lot->actual_qty_prod), 2);

                    $lot->update([
                        'qty_material_return' => $newTotalReturn,
                        'target_qty_estimasi' => $newEst,
                        'qty_wip'             => $newWip,
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $qtyTxt         = trim('+' . floatval($qtyReturn) . ' ' . $matUom);
        $redirectParams = $request->input('mode') === 'scanner' ? ['mode' => 'scanner'] : [];
        return redirect()->route('material_returns.index', $redirectParams)
            ->with('success', "Material Return Lot {$request->lot_number} ({$qtyTxt}) tersimpan.");
    }

    public function verifyBatch(Request $request)
    {
        $items = $request->input('items', []);
        if (empty($items)) {
            return back()->with('error', 'Tidak ada koreksi yang disimpan.');
        }

        $allBoms   = Bom::all();
        $processed = 0;

        try {
            DB::transaction(function () use ($items, $allBoms, &$processed) {
                foreach ($items as $id => $data) {
                    $newQty = StockAdjustmentController::parseSignedCalculatorValue($data['actual_qty'] ?? null);
                    if ($newQty === null || $newQty <= 0) {
                        throw new \RuntimeException('Format Qty koreksi tidak valid (wajib > 0).');
                    }
                    if ($newQty > Item::MAX_QTY) {
                        throw new \RuntimeException('Qty koreksi melebihi batas maksimal 99.999.999,99.');
                    }

                    $notesRaw = trim((string) ($data['correction_notes'] ?? ''));
                    $notes    = $notesRaw !== '' ? mb_substr($notesRaw, 0, 500) : null;

                    $matRet = MaterialReturn::lockForUpdate()->find($id);
                    if (!$matRet) {
                        throw new \RuntimeException('Data transaksi Material Return tidak ditemukan.');
                    }

                    $lotRows = LotTracer::where('lot_number', $matRet->lot_number)
                        ->where('material_item_id', $matRet->material_item_id)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    if ($lotRows->contains('status_lot', 'FINISH')) {
                        throw new \RuntimeException("Lot {$matRet->lot_number} sudah berstatus FINISH sehingga Material Return tidak dapat dikoreksi.");
                    }

                    $oldQty      = (float) $matRet->qty;
                    $diffTrigger = round($newQty - $oldQty, 2);

                    foreach ($lotRows as $lt) {
                        $meta         = $this->resolveBomMeta((int) $lt->material_item_id, (int) $lt->target_item_id, $allBoms, $lt->bom_group);
                        $usedByProd   = (float) $lt->actual_qty_prod * $meta['qty_usage'];
                        $otherReturns = max(0, (float) $lt->qty_material_return - $oldQty);
                        $maxAllowed   = max(0, round((float) $lt->qty_material_out - $usedByProd - $otherReturns, 2));
                        if ($newQty > $maxAllowed + 0.0001) {
                            throw new \RuntimeException('Qty koreksi melebihi sisa material belum diproduksi (maksimal ' . floatval($maxAllowed) . ').');
                        }
                    }

                    $siblingQuery = MaterialReturn::where('lot_number', $matRet->lot_number)
                        ->where('material_item_id', $matRet->material_item_id);
                    if (!empty($matRet->return_batch)) {
                        $siblingQuery->where('return_batch', $matRet->return_batch);
                    } else {
                        $siblingQuery->whereNull('return_batch');
                    }
                    $siblingRets = $siblingQuery->orderBy('id')->lockForUpdate()->get();

                    if ($diffTrigger != 0) {
                        $companionDiffs = [];
                        $siblingMetas   = [];

                        foreach ($siblingRets as $sib) {
                            $meta                   = $this->resolveBomMeta((int) $sib->material_item_id, (int) $sib->target_item_id, $allBoms, $sib->bom_group);
                            $siblingMetas[$sib->id] = $meta;

                            $rawOldEst = $meta['qty_usage'] > 0 ? ($oldQty / $meta['qty_usage']) : 0;
                            $oldEst    = abs($rawOldEst - round($rawOldEst)) < 0.001 ? round($rawOldEst) : $rawOldEst;
                            $rawNewEst = $meta['qty_usage'] > 0 ? ($newQty / $meta['qty_usage']) : 0;
                            $newEst    = abs($rawNewEst - round($rawNewEst)) < 0.001 ? round($rawNewEst) : $rawNewEst;

                            foreach ($meta['companions'] as $compBom) {
                                $oldComp  = round($oldEst * (float) $compBom->qty_usage, 2);
                                $newComp  = round($newEst * (float) $compBom->qty_usage, 2);
                                $diffComp = round($newComp - $oldComp, 2);
                                if ($diffComp != 0) {
                                    $cId                  = (int) $compBom->input_item_id;
                                    $companionDiffs[$cId] = round(($companionDiffs[$cId] ?? 0.0) + $diffComp, 2);
                                }
                            }
                        }

                        // Urutkan ID item sebelum lockForUpdate() untuk mencegah deadlock
                        $lockItemIds = array_values(array_unique(array_merge(
                            [(int) $matRet->material_item_id],
                            array_map('intval', array_keys($companionDiffs))
                        )));
                        sort($lockItemIds, SORT_NUMERIC);

                        $lockedStocks = [];
                        foreach ($lockItemIds as $itemId) {
                            InventoryStock::firstOrCreate(
                                ['item_id' => $itemId],
                                ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                            );
                            $lockedStocks[$itemId] = InventoryStock::where('item_id', $itemId)->lockForUpdate()->first();
                        }

                        $matStock     = $lockedStocks[(int) $matRet->material_item_id];
                        $nextMatStock = round((float) $matStock->current_stock + $diffTrigger, 2);
                        if ($diffTrigger < 0 && $nextMatStock < 0) {
                            throw new \RuntimeException('Stok gudang tidak cukup untuk koreksi return.');
                        }
                        if ($nextMatStock > Item::MAX_QTY) {
                            throw new \RuntimeException('Stok gudang melebihi batas maksimal 99.999.999,99.');
                        }

                        foreach ($companionDiffs as $cId => $diffComp) {
                            if ($diffComp == 0) {
                                continue;
                            }
                            $cStock        = $lockedStocks[(int) $cId];
                            $nextCompStock = round((float) $cStock->current_stock + $diffComp, 2);
                            if ($diffComp < 0 && $nextCompStock < 0) {
                                throw new \RuntimeException('Stok komponen pendamping di gudang tidak cukup untuk koreksi return.');
                            }
                            if ($nextCompStock > Item::MAX_QTY) {
                                throw new \RuntimeException('Stok komponen pendamping di gudang melebihi batas maksimal 99.999.999,99.');
                            }
                        }

                        $matStock->current_stock = $nextMatStock;
                        $matStock->status_stock  = $matStock->calculateDynamicStatus();
                        $matStock->save();

                        foreach ($companionDiffs as $cId => $diffComp) {
                            if ($diffComp == 0) {
                                continue;
                            }
                            $cStock                = $lockedStocks[(int) $cId];
                            $cStock->current_stock = round((float) $cStock->current_stock + $diffComp, 2);
                            $cStock->status_stock  = $cStock->calculateDynamicStatus();
                            $cStock->save();
                        }

                        foreach ($siblingRets as $sib) {
                            $meta = $siblingMetas[$sib->id];

                            $updatePayload = [
                                'qty'              => $newQty,
                                'qty_check'        => 'DIKOREKSI',
                                'corrected_by'     => Auth::id(),
                                'corrected_at'     => now(),
                                'correction_notes' => $notes,
                            ];
                            if ($sib->qty_original === null) {
                                $updatePayload['qty_original'] = (float) $sib->qty;
                            }
                            $sib->update($updatePayload);

                            $lot = $lotRows->firstWhere('target_item_id', $sib->target_item_id);
                            if ($lot) {
                                $newTotalReturn = max(0, round((float) $lot->qty_material_return + $diffTrigger, 2));
                                $netMat         = max(0, (float) $lot->qty_material_out - $newTotalReturn);
                                $rawEst         = $meta['qty_usage'] > 0 ? ($netMat / $meta['qty_usage']) : 0;
                                $estTarget      = abs($rawEst - round($rawEst)) < 0.001 ? round($rawEst) : round($rawEst, 2);
                                $wip            = $lot->status_lot === 'FINISH' ? 0 : round(max(0, $estTarget - (float) $lot->actual_qty_prod), 2);

                                $lot->update([
                                    'qty_material_return' => $newTotalReturn,
                                    'target_qty_estimasi' => $estTarget,
                                    'qty_wip'             => $wip,
                                ]);
                            }
                        }
                    } else {
                        foreach ($siblingRets as $sib) {
                            $updatePayload = [
                                'qty_check'        => 'DIKOREKSI',
                                'corrected_by'     => Auth::id(),
                                'corrected_at'     => now(),
                                'correction_notes' => $notes,
                            ];
                            if ($sib->qty_original === null) {
                                $updatePayload['qty_original'] = (float) $sib->qty;
                            }
                            $sib->update($updatePayload);
                        }
                    }

                    $processed++;
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan koreksi Material Return.', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);

            return back()->with('error', 'Terjadi kesalahan sistem saat menyimpan koreksi Material Return. Silakan coba lagi.');
        }

        if ($processed === 0) {
            return back()->with('error', 'Tidak ada koreksi yang disimpan.');
        }

        return redirect()->route('material_returns.index')->with('success', 'Koreksi Material Return tersimpan.');
    }
}