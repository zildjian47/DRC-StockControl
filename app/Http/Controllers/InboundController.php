<?php

namespace App\Http\Controllers;

use App\Models\Inbound;
use App\Models\Item;
use App\Models\InventoryStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InboundController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('mode') === 'scanner') {
            $allScans = Inbound::with(['item.company', 'user'])
                ->orderBy('date_time', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $todayScans = $allScans->filter(fn ($s) => \Carbon\Carbon::parse($s->date_time)->isToday())->values();
            $recentScans = $todayScans;
            $items = Item::with(['company', 'inventoryStock'])->get();

            return view('inbounds.scanner', compact('allScans', 'todayScans', 'recentScans', 'items'));
        }

        $inbounds = Inbound::with(['item.company', 'user'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return view('inbounds.index', compact('inbounds'));
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
            return back()->withInput()->with('error', 'Qty Inbound harus lebih besar dari 0.');
        }

        $item = Item::with('company')->findOrFail($request->item_id);

        if (!in_array($item->type, ['RM', 'KOMPONEN', 'TRANSIT'], true)) {
            return back()->with('error', "Ditolak: Item {$item->part_name} bertipe {$item->type}. Inbound hanya menerima tipe RM, KOMPONEN, atau TRANSIT.");
        }

        if (!$item->company || $item->company->type_company !== 'SUPPLIER') {
            return back()->with('error', "Ditolak: Perusahaan {$item->part_name} bukan bertipe SUPPLIER.");
        }

        DB::transaction(function () use ($item, $qty) {
            Inbound::create([
                'date_time' => now(),
                'item_id'   => $item->id,
                'user_id'   => Auth::id(),
                'qty'       => $qty,
                'qty_check' => 'SESUAI',
            ]);

            $stock = InventoryStock::firstOrCreate(
                ['item_id' => $item->id],
                ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
            );
            $stock->current_stock = (float) $stock->current_stock + $qty;
            $stock->status_stock  = $stock->calculateDynamicStatus();
            $stock->save();
        });

        $msg = "Inbound {$item->part_name} (+" . floatval($qty) . " {$item->uom}) berhasil dicatat.";

        if ($request->input('mode') === 'scanner') {
            return redirect()->route('inbounds.index', ['mode' => 'scanner'])->with('success', $msg);
        }

        return redirect()->route('inbounds.index')->with('success', $msg);
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
                        throw new \RuntimeException("Format Qty koreksi tidak valid (wajib lebih dari 0).");
                    }

                    $inbound = Inbound::with('item')->findOrFail($id);
                    $oldQty  = (float) $inbound->qty;
                    $diff    = $newQty - $oldQty;

                    $stock = InventoryStock::firstOrCreate(
                        ['item_id' => $inbound->item_id],
                        ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                    );

                    if ((float) $stock->current_stock + $diff < 0) {
                        throw new \RuntimeException("Koreksi Inbound untuk {$inbound->item->part_name} ditolak karena membuat Current Stock minus.");
                    }

                    $stock->current_stock = (float) $stock->current_stock + $diff;
                    $stock->status_stock  = $stock->calculateDynamicStatus();
                    $stock->save();

                    $inbound->qty       = $newQty;
                    $inbound->qty_check = 'TIDAK SESUAI';
                    $inbound->save();

                    $processed++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($processed === 0) {
            return back()->with('error', 'Tidak ada perubahan Qty Inbound yang disimpan.');
        }

        return redirect()->route('inbounds.index')
            ->with('success', "Qty Inbound berhasil dikoreksi dan stok gudang telah diperbarui.");
    }
}