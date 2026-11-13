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
            return back()->withInput()->with('error', 'Qty Production harus lebih besar dari 0.');
        }

        $item = Item::with('company')->findOrFail($request->item_id);

        if (!in_array($item->type, ['FG', 'KOMPONEN', 'FG/KOMPONEN'], true)) {
            return back()->withInput()->with('error', "Ditolak: Item {$item->part_name} bertipe {$item->type}. Production hanya menerima FG, KOMPONEN, atau FG/KOMPONEN.");
        }

        $compType = $item->company ? $item->company->type_company : 'INTERNAL';
        if (!in_array($compType, ['INTERNAL', 'CUSTOMER'], true)) {
            return back()->withInput()->with('error', "Ditolak: Perusahaan {$item->part_name} harus bertipe INTERNAL atau CUSTOMER.");
        }

        $lotNumber = $request->lot_number;
        $lot = LotTracer::where('lot_number', $lotNumber)
            ->where('target_item_id', $item->id)
            ->first();

        if (!$lot) {
            return back()->withInput()->with('error', "Ditolak: Nomor Lot {$lotNumber} untuk item {$item->part_name} tidak ditemukan di Lot Tracer.");
        }

        if ($lot->status_lot !== 'ON PROGRESS') {
            return back()->withInput()->with('error', "Ditolak: Nomor Lot {$lotNumber} sudah berstatus FINISH.");
        }

        if ($qtyProd > (float) $lot->qty_wip + 0.0001) {
            return back()->withInput()->with('error', "Ditolak: Qty Production ({$qtyProd}) melebihi sisa Qty WIP pada Lot {$lotNumber} (Sisa WIP: {$lot->qty_wip} {$item->uom}).");
        }

        DB::transaction(function () use ($item, $lot, $lotNumber, $qtyProd) {
            Production::create([
                'date_time'  => now(),
                'item_id'    => $item->id,
                'user_id'    => Auth::id(),
                'lot_number' => $lotNumber,
                'qty'        => $qtyProd,
                'qty_check'  => 'SESUAI',
            ]);

            $lot->actual_qty_prod = (float) $lot->actual_qty_prod + $qtyProd;
            $lot->recalculateLotMetrics();

            $stock = InventoryStock::firstOrCreate(
                ['item_id' => $item->id],
                ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
            );
            $stock->current_stock = (float) $stock->current_stock + $qtyProd;
            $stock->status_stock  = $stock->calculateDynamicStatus();
            $stock->save();
        });

        $msg = "Hasil Production Lot {$lotNumber} (+" . floatval($qtyProd) . " {$item->uom} {$item->part_name}) berhasil dicatat.";

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
                        throw new \RuntimeException("Format Qty koreksi Production tidak valid (wajib > 0).");
                    }

                    $prod = Production::with('item')->findOrFail($id);
                    $lot  = LotTracer::where('lot_number', $prod->lot_number)
                        ->where('target_item_id', $prod->item_id)
                        ->firstOrFail();

                    $oldQty = (float) $prod->qty;
                    $diff   = $newQty - $oldQty;

                    $newActualLot = (float) $lot->actual_qty_prod + $diff;
                    if ($newActualLot < (float) $lot->qty_ng) {
                        throw new \RuntimeException("Koreksi Production Lot {$prod->lot_number} tidak boleh lebih kecil dari Qty NG yang sudah tercatat ({$lot->qty_ng}).");
                    }

                    $stock = InventoryStock::firstOrCreate(
                        ['item_id' => $prod->item_id],
                        ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                    );

                    if ((float) $stock->current_stock + $diff < 0) {
                        throw new \RuntimeException("Koreksi Production ditolak karena membuat Current Stock {$prod->item->part_name} minus.");
                    }

                    $stock->current_stock = (float) $stock->current_stock + $diff;
                    $stock->status_stock  = $stock->calculateDynamicStatus();
                    $stock->save();

                    $prod->qty       = $newQty;
                    $prod->qty_check = 'TIDAK SESUAI';
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
            return back()->with('error', 'Tidak ada koreksi Production yang disimpan.');
        }

        return redirect()->route('productions.index')
            ->with('success', "Qty Production berhasil dikoreksi beserta saldo stok dan Lot Tracer.");
    }
}