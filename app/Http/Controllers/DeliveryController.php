<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Item;
use App\Models\InventoryStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('mode') === 'scanner') {
            $allScans = Delivery::with(['item.company', 'user'])
                ->orderBy('date_time', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $todayScans = $allScans->filter(fn ($s) => \Carbon\Carbon::parse($s->date_time)->isToday())->values();
            $recentScans = $todayScans;
            $items = Item::with(['company', 'inventoryStock'])->get();

            return view('deliveries.scanner', compact('allScans', 'todayScans', 'recentScans', 'items'));
        }

        $deliveries = Delivery::with(['item.company', 'user'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return view('deliveries.index', compact('deliveries'));
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

        $request->validate([
            'item_id' => 'required|exists:items,id',
            'qty'     => 'required',
        ]);

        $qty = BomController::evaluateMath($request->qty);
        if ($qty <= 0) {
            return back()->withInput()->with('error', 'Qty harus lebih dari 0.');
        }

        $item = Item::with('company')->findOrFail($request->item_id);

        if (!in_array($item->type, ['FG', 'FG/KOMPONEN', 'TRANSIT'], true)) {
            return back()->with('error', 'Item tidak sesuai untuk Delivery (wajib FG, FG/KOMPONEN, atau TRANSIT).');
        }

        $compType = $item->company ? $item->company->type_company : 'INTERNAL';

        if ($item->type === 'FG' && $compType !== 'CUSTOMER') {
            return back()->with('error', 'Item FG wajib ber-perusahaan CUSTOMER.');
        }
        if ($item->type === 'FG/KOMPONEN' && !in_array($compType, ['INTERNAL', 'CUSTOMER'], true)) {
            return back()->with('error', 'Item FG/KOMPONEN wajib ber-perusahaan INTERNAL atau CUSTOMER.');
        }
        if ($item->type === 'TRANSIT' && !in_array($compType, ['SUPPLIER', 'CUSTOMER'], true)) {
            return back()->with('error', 'Item TRANSIT wajib ber-perusahaan SUPPLIER atau CUSTOMER.');
        }

        try {
            DB::transaction(function () use ($item, $qty) {
                InventoryStock::firstOrCreate(
                    ['item_id' => $item->id],
                    ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                );
                $stock = InventoryStock::where('item_id', $item->id)->lockForUpdate()->first();

                if ($qty > (float) $stock->current_stock) {
                    throw new \RuntimeException('Stok kurang. Tersedia: ' . floatval($stock->current_stock) . " {$item->uom}.");
                }

                Delivery::create([
                    'date_time' => now(),
                    'item_id'   => $item->id,
                    'user_id'   => Auth::id(),
                    'qty'       => $qty,
                    'qty_check' => 'TERCATAT',
                ]);

                $stock->current_stock = (float) $stock->current_stock - $qty;
                $stock->status_stock  = $stock->calculateDynamicStatus();
                $stock->save();
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $msg = "Delivery {$item->part_name} (-" . floatval($qty) . " {$item->uom}) tersimpan.";

        if ($request->input('mode') === 'scanner') {
            return redirect()->route('deliveries.index', ['mode' => 'scanner'])->with('success', $msg);
        }

        return redirect()->route('deliveries.index')->with('success', $msg);
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

                    $delivery = Delivery::with('item')->lockForUpdate()->findOrFail($id);
                    $oldQty   = (float) $delivery->qty;
                    $diff     = $newQty - $oldQty;

                    InventoryStock::firstOrCreate(
                        ['item_id' => $delivery->item_id],
                        ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                    );
                    $stock = InventoryStock::where('item_id', $delivery->item_id)->lockForUpdate()->first();

                    if ($diff > 0 && $diff > (float) $stock->current_stock) {
                        throw new \RuntimeException("Stok {$delivery->item->part_name} tidak cukup untuk koreksi.");
                    }

                    $stock->current_stock = (float) $stock->current_stock - $diff;
                    $stock->status_stock  = $stock->calculateDynamicStatus();
                    $stock->save();

                    $delivery->qty       = $newQty;
                    $delivery->qty_check = 'DIKOREKSI';
                    $delivery->save();

                    $processed++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($processed === 0) {
            return back()->with('error', 'Tidak ada koreksi yang disimpan.');
        }

        return redirect()->route('deliveries.index')
            ->with('success', 'Koreksi Delivery tersimpan.');
    }
}