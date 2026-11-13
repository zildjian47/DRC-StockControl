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
            return back()->withInput()->with('error', 'Qty Delivery harus lebih besar dari 0.');
        }

        $item = Item::with('company')->findOrFail($request->item_id);

        if (!in_array($item->type, ['FG', 'FG/KOMPONEN', 'TRANSIT'], true)) {
            return back()->with('error', "Ditolak: Item {$item->part_name} bertipe {$item->type}. Delivery hanya menerima FG, FG/KOMPONEN, atau TRANSIT.");
        }

        $compType = $item->company ? $item->company->type_company : 'INTERNAL';

        if ($item->type === 'FG' && $compType !== 'CUSTOMER') {
            return back()->with('error', "Ditolak: Item FG ({$item->part_name}) wajib memiliki perusahaan bertipe CUSTOMER.");
        }
        if ($item->type === 'FG/KOMPONEN' && !in_array($compType, ['INTERNAL', 'CUSTOMER'], true)) {
            return back()->with('error', "Ditolak: Item FG/KOMPONEN ({$item->part_name}) wajib bertipe perusahaan INTERNAL atau CUSTOMER.");
        }
        if ($item->type === 'TRANSIT' && !in_array($compType, ['SUPPLIER', 'CUSTOMER'], true)) {
            return back()->with('error', "Ditolak: Item TRANSIT ({$item->part_name}) wajib bertipe perusahaan SUPPLIER atau CUSTOMER.");
        }

        $stock = InventoryStock::firstOrCreate(
            ['item_id' => $item->id],
            ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
        );

        if ($qty > (float) $stock->current_stock) {
            return back()->with('error', "Ditolak: Qty Delivery ({$qty}) melebihi Current Stock {$item->part_name} ({$stock->current_stock} {$item->uom}).");
        }

        DB::transaction(function () use ($item, $qty, $stock) {
            Delivery::create([
                'date_time' => now(),
                'item_id'   => $item->id,
                'user_id'   => Auth::id(),
                'qty'       => $qty,
                'qty_check' => 'SESUAI',
            ]);

            $stock->current_stock = (float) $stock->current_stock - $qty;
            $stock->status_stock  = $stock->calculateDynamicStatus();
            $stock->save();
        });

        $msg = "Delivery {$item->part_name} (-" . floatval($qty) . " {$item->uom}) berhasil dicatat.";

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
                        throw new \RuntimeException("Format Qty koreksi Delivery tidak valid (wajib > 0).");
                    }

                    $delivery = Delivery::with('item')->findOrFail($id);
                    $oldQty   = (float) $delivery->qty;
                    $diff     = $newQty - $oldQty;

                    $stock = InventoryStock::firstOrCreate(
                        ['item_id' => $delivery->item_id],
                        ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                    );

                    if ($diff > 0 && $diff > (float) $stock->current_stock) {
                        throw new \RuntimeException("Stok {$delivery->item->part_name} tidak mencukupi untuk penambahan koreksi Delivery.");
                    }

                    $stock->current_stock = (float) $stock->current_stock - $diff;
                    $stock->status_stock  = $stock->calculateDynamicStatus();
                    $stock->save();

                    $delivery->qty       = $newQty;
                    $delivery->qty_check = 'TIDAK SESUAI';
                    $delivery->save();

                    $processed++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($processed === 0) {
            return back()->with('error', 'Tidak ada koreksi Delivery yang disimpan.');
        }

        return redirect()->route('deliveries.index')
            ->with('success', "Qty Delivery berhasil dikoreksi dan stok gudang telah diperbarui.");
    }
}