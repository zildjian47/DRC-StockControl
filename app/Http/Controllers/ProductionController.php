<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\Item;
use App\Models\LotTracer;
use App\Models\InventoryStock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('mode') === 'scanner') {
            $allScansData = Production::with(['item.company', 'user'])
                ->orderBy('date_time', 'desc')
                ->orderBy('id', 'desc')
                ->limit(300)
                ->get()
                ->map(function ($s) {
                    $dt = Carbon::parse($s->date_time);
                    return [
                        'id'         => $s->id,
                        'date'       => $dt->format('Y-m-d'),
                        'time'       => $dt->format('H:i'),
                        'date_fmt'   => $dt->format('d M Y, H:i'),
                        'lot_number' => $s->lot_number,
                        'part_no'    => $s->item->part_no ?: '-',
                        'part_name'  => $s->item->part_name ?? '-',
                        'uom'        => $s->item->uom ?? 'PCS',
                        'qty'        => (float) $s->qty,
                        'user_name'  => $s->user->name ?? 'Operator',
                    ];
                })->values();

            return view('productions.scanner', compact('allScansData'));
        }

        $productions = Production::with(['item.company', 'user', 'correctedBy'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $stockMap = InventoryStock::whereIn('item_id', $productions->pluck('item_id')->unique())
            ->pluck('current_stock', 'item_id');

        $lotMap = LotTracer::whereIn('lot_number', $productions->pluck('lot_number')->unique())
            ->get()
            ->keyBy(fn ($l) => $l->lot_number . '|' . $l->target_item_id);

        return view('productions.index', compact('productions', 'stockMap', 'lotMap'));
    }

    public function lookup(Request $request)
    {
        $rawCode = trim((string) $request->query('code', ''));
        $code = strtoupper($rawCode);

        if ($code === '') {
            return response()->json([
                'ok'      => false,
                'message' => 'Kode QR / Part wajib diisi.',
            ]);
        }

        $item = Item::with(['company', 'inventoryStock'])
            ->where('part_no', $code)
            ->orWhere('part_name', $code)
            ->first();

        if (!$item) {
            return response()->json([
                'ok'      => false,
                'message' => "Kode \"{$rawCode}\" tidak ditemukan.",
            ]);
        }

        $compType = $item->company ? $item->company->type_company : 'INTERNAL';
        if (!in_array($item->type, ['FG', 'FG/KOMPONEN', 'KOMPONEN'], true) || !in_array($compType, ['INTERNAL', 'CUSTOMER'], true)) {
            return response()->json([
                'ok'      => false,
                'message' => "{$item->part_name} tidak sesuai untuk Production.",
            ]);
        }

        $lots = LotTracer::with(['process', 'targetItem'])
            ->where('target_item_id', $item->id)
            ->where('status_lot', 'ON PROGRESS')
            ->where('qty_wip', '>', 0)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($lt) => [
                'id'             => $lt->id,
                'lot_number'     => $lt->lot_number,
                'target_item_id' => $lt->target_item_id,
                'process_name'   => $lt->process->process_name ?? '-',
                'target_est'     => (float) $lt->target_qty_estimasi,
                'actual_prod'    => (float) $lt->actual_qty_prod,
                'qty_wip'        => (float) $lt->qty_wip,
                'uom'            => $lt->targetItem->uom ?? $item->uom ?? 'PCS',
            ])->values();

        if ($lots->isEmpty()) {
            return response()->json([
                'ok'      => false,
                'message' => "Tidak ada Lot aktif dengan sisa WIP untuk {$item->part_name}.",
            ]);
        }

        return response()->json([
            'ok'   => true,
            'item' => [
                'id'           => $item->id,
                'part_no'      => $item->part_no ? strtoupper(trim($item->part_no)) : '',
                'part_name'    => strtoupper(trim($item->part_name)),
                'type'         => $item->type,
                'uom'          => $item->uom,
                'company_type' => $compType,
                'stock'        => $item->inventoryStock ? (float) $item->inventoryStock->current_stock : 0.0,
            ],
            'lots' => $lots,
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->filled('item_id') && $request->filled('scanned_code')) {
            $code = strtoupper(trim((string) $request->scanned_code));
            $found = Item::where('part_no', $code)->orWhere('part_name', $code)->first();
            if ($found) {
                $request->merge(['item_id' => $found->id]);
            }
        }

        $request->merge([
            'lot_number' => strtoupper(trim((string) $request->lot_number)),
        ]);

        $request->validate([
            'item_id'    => 'required|exists:items,id',
            'lot_number' => ['required', 'string', 'regex:/^[A-Z][0-9]{6}$/'],
            'qty'        => 'required',
        ]);

        $qtyProd = round(BomController::evaluateMath($request->qty), 2);
        if ($qtyProd <= 0) {
            return back()->withInput()->with('error', 'Qty harus lebih dari 0.');
        }

        $item = Item::with('company')->findOrFail($request->item_id);

        if (!in_array($item->type, ['FG', 'KOMPONEN', 'FG/KOMPONEN'], true)) {
            return back()->withInput()->with('error', 'Item tidak sesuai untuk Production (wajib FG, KOMPONEN, atau FG/KOMPONEN).');
        }

        $compType = $item->company ? $item->company->type_company : 'INTERNAL';
        if (!in_array($compType, ['INTERNAL', 'CUSTOMER'], true)) {
            return back()->withInput()->with('error', 'Perusahaan item ini wajib bertipe INTERNAL atau CUSTOMER.');
        }

        $lotNumber = $request->lot_number;

        try {
            DB::transaction(function () use ($item, $lotNumber, $qtyProd) {
                $lot = LotTracer::where('lot_number', $lotNumber)
                    ->where('target_item_id', $item->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lot) {
                    throw new \RuntimeException("Lot {$lotNumber} untuk {$item->part_name} tidak ditemukan.");
                }

                if ($lot->status_lot !== 'ON PROGRESS') {
                    throw new \RuntimeException("Lot {$lotNumber} sudah berstatus FINISH.");
                }

                if ($qtyProd > (float) $lot->qty_wip + 0.0001) {
                    throw new \RuntimeException('Qty melebihi sisa WIP (tersedia ' . floatval($lot->qty_wip) . " {$item->uom}).");
                }

                Production::create([
                    'date_time'  => now(),
                    'item_id'    => $item->id,
                    'user_id'    => Auth::id(),
                    'lot_number' => $lotNumber,
                    'qty'        => $qtyProd,
                    'qty_check'  => 'TERCATAT',
                ]);

                $lot->actual_qty_prod = (float) $lot->actual_qty_prod + $qtyProd;
                $lot->recalculateLotMetrics();

                InventoryStock::firstOrCreate(
                    ['item_id' => $item->id],
                    ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                );
                $stock = InventoryStock::where('item_id', $item->id)->lockForUpdate()->first();
                $stock->current_stock = (float) $stock->current_stock + $qtyProd;
                $stock->status_stock  = $stock->calculateDynamicStatus();
                $stock->save();
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $msg = "Production Lot {$lotNumber} (+" . floatval($qtyProd) . " {$item->uom}) tersimpan.";

        if ($request->input('mode') === 'scanner') {
            return redirect()->route('productions.index', ['mode' => 'scanner'])->with('success', $msg);
        }

        return redirect()->route('productions.index')->with('success', $msg);
    }

    public function verifyBatch(Request $request)
    {
        $items = $request->input('items', []);
        $processed = 0;

        try {
            DB::transaction(function () use ($items, &$processed) {
                foreach ($items as $id => $data) {
                    $rawActual = $data['actual_qty'] ?? null;
                    $newQty = StockAdjustmentController::parseSignedCalculatorValue($rawActual);
                    if ($newQty === null || $newQty <= 0) {
                        throw new \RuntimeException('Format Qty koreksi tidak valid (wajib > 0).');
                    }

                    $notesRaw = trim((string) ($data['correction_notes'] ?? ''));
                    $notes    = $notesRaw !== '' ? mb_substr($notesRaw, 0, 500) : null;

                    $prod = Production::with('item')->lockForUpdate()->findOrFail($id);
                    $lot  = LotTracer::where('lot_number', $prod->lot_number)
                        ->where('target_item_id', $prod->item_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($lot->status_lot === 'FINISH') {
                        throw new \RuntimeException("Lot {$lot->lot_number} sudah berstatus FINISH sehingga tidak dapat dikoreksi.");
                    }

                    $oldQty = (float) $prod->qty;
                    $diff   = $newQty - $oldQty;

                    $newActualLot = (float) $lot->actual_qty_prod + $diff;
                    if ($newActualLot < (float) $lot->qty_ng) {
                        throw new \RuntimeException('Qty koreksi tidak boleh lebih kecil dari Qty NG (' . floatval($lot->qty_ng) . ').');
                    }

                    InventoryStock::firstOrCreate(
                        ['item_id' => $prod->item_id],
                        ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                    );
                    $stock = InventoryStock::where('item_id', $prod->item_id)->lockForUpdate()->first();

                    if ((float) $stock->current_stock + $diff < 0) {
                        throw new \RuntimeException("Stok {$prod->item->part_name} tidak boleh minus akibat koreksi.");
                    }

                    $stock->current_stock = (float) $stock->current_stock + $diff;
                    $stock->status_stock  = $stock->calculateDynamicStatus();
                    $stock->save();

                    if ($prod->qty_original === null) {
                        $prod->qty_original = $oldQty;
                    }
                    $prod->qty              = $newQty;
                    $prod->qty_check        = 'DIKOREKSI';
                    $prod->corrected_by     = Auth::id();
                    $prod->corrected_at     = now();
                    $prod->correction_notes = $notes;
                    $prod->save();

                    $lot->actual_qty_prod = $newActualLot;
                    $lot->recalculateLotMetrics();

                    $processed++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($processed === 0) {
            return back()->with('error', 'Tidak ada koreksi yang disimpan.');
        }

        return redirect()->route('productions.index')
            ->with('success', 'Koreksi Production tersimpan.');
    }
}