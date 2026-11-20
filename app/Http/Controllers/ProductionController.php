<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\Item;
use App\Models\LotTracer;
use App\Models\InventoryStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('mode') === 'scanner') {
            $allScans = Production::with(['item.company', 'user'])
                ->orderBy('date_time', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $todayScans = $allScans->filter(fn ($s) => \Carbon\Carbon::parse($s->date_time)->isToday())->values();
            $recentScans = $todayScans;
            $items = Item::with(['company', 'inventoryStock'])->get();

            $activeLots = LotTracer::with(['targetItem', 'process'])
                ->where('status_lot', 'ON PROGRESS')
                ->where('qty_wip', '>', 0)
                ->orderBy('id', 'desc')
                ->get();

            return view('productions.scanner', compact('allScans', 'todayScans', 'recentScans', 'items', 'activeLots'));
        }

        $productions = Production::with(['item.company', 'user'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return view('productions.index', compact('productions'));
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

        $qtyProd = BomController::evaluateMath($request->qty);
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

                    $prod->qty       = $newQty;
                    $prod->qty_check = 'DIKOREKSI';
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