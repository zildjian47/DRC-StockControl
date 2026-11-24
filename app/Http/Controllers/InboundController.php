<?php

namespace App\Http\Controllers;

use App\Models\Inbound;
use App\Models\Item;
use App\Models\InventoryStock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InboundController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('mode') === 'scanner') {
            $allScansData = Inbound::with(['item.company', 'user'])
                ->orderBy('date_time', 'desc')
                ->orderBy('id', 'desc')
                ->limit(300)
                ->get()
                ->map(function ($s) {
                    $dt = Carbon::parse($s->date_time);
                    return [
                        'id'        => $s->id,
                        'date'      => $dt->format('Y-m-d'),
                        'time'      => $dt->format('H:i'),
                        'date_fmt'  => $dt->format('d M Y, H:i'),
                        'part_no'   => $s->item->part_no ?: '-',
                        'part_name' => $s->item->part_name ?? '-',
                        'uom'       => $s->item->uom ?? '',
                        'supplier'  => $s->item->company->company_code ?? '-',
                        'qty'       => (float) $s->qty,
                        'user_name' => $s->user->name ?? 'Operator',
                    ];
                })->values();

            return view('inbounds.scanner', compact('allScansData'));
        }

        $inbounds = Inbound::with(['item.company', 'user', 'correctedBy'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $stockMap = InventoryStock::whereIn('item_id', $inbounds->pluck('item_id')->unique())
            ->pluck('current_stock', 'item_id');

        return view('inbounds.index', compact('inbounds', 'stockMap'));
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
        if (!in_array($item->type, ['RM', 'KOMPONEN', 'TRANSIT'], true) || $compType !== 'SUPPLIER') {
            return response()->json([
                'ok'      => false,
                'message' => "{$item->part_name} tidak sesuai untuk Inbound (wajib RM, KOMPONEN, atau TRANSIT dari SUPPLIER).",
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
                'company_code' => $item->company->company_code ?? '-',
                'company_type' => $compType,
                'stock'        => $item->inventoryStock ? (float) $item->inventoryStock->current_stock : 0.0,
            ],
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

        $request->validate([
            'item_id' => 'required|exists:items,id',
            'qty'     => 'required',
        ]);

        $qty = round(BomController::evaluateMath($request->qty), 2);
        if ($qty <= 0) {
            return back()->withInput()->with('error', 'Qty harus lebih dari 0.');
        }

        $item = Item::with('company')->findOrFail($request->item_id);

        if (!in_array($item->type, ['RM', 'KOMPONEN', 'TRANSIT'], true)) {
            return back()->with('error', 'Item tidak sesuai untuk Inbound (wajib RM, KOMPONEN, atau TRANSIT).');
        }

        if (!$item->company || $item->company->type_company !== 'SUPPLIER') {
            return back()->with('error', 'Perusahaan item ini wajib bertipe SUPPLIER.');
        }

        DB::transaction(function () use ($item, $qty) {
            Inbound::create([
                'date_time' => now(),
                'item_id'   => $item->id,
                'user_id'   => Auth::id(),
                'qty'       => $qty,
                'qty_check' => 'TERCATAT',
            ]);

            InventoryStock::firstOrCreate(
                ['item_id' => $item->id],
                ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
            );
            $stock = InventoryStock::where('item_id', $item->id)->lockForUpdate()->first();
            $stock->current_stock = (float) $stock->current_stock + $qty;
            $stock->status_stock  = $stock->calculateDynamicStatus();
            $stock->save();
        });

        $msg = "Inbound {$item->part_name} (+" . floatval($qty) . " {$item->uom}) tersimpan.";

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
                        throw new \RuntimeException('Format Qty koreksi tidak valid (wajib > 0).');
                    }

                    $notesRaw = trim((string) ($data['correction_notes'] ?? ''));
                    $notes    = $notesRaw !== '' ? mb_substr($notesRaw, 0, 500) : null;

                    $inbound = Inbound::with('item')->lockForUpdate()->findOrFail($id);
                    $oldQty  = (float) $inbound->qty;
                    $diff    = $newQty - $oldQty;

                    InventoryStock::firstOrCreate(
                        ['item_id' => $inbound->item_id],
                        ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                    );
                    $stock = InventoryStock::where('item_id', $inbound->item_id)->lockForUpdate()->first();

                    if ((float) $stock->current_stock + $diff < 0) {
                        throw new \RuntimeException("Stok {$inbound->item->part_name} tidak boleh minus akibat koreksi.");
                    }

                    $stock->current_stock = (float) $stock->current_stock + $diff;
                    $stock->status_stock  = $stock->calculateDynamicStatus();
                    $stock->save();

                    if ($inbound->qty_original === null) {
                        $inbound->qty_original = $oldQty;
                    }
                    $inbound->qty              = $newQty;
                    $inbound->qty_check        = 'DIKOREKSI';
                    $inbound->corrected_by     = Auth::id();
                    $inbound->corrected_at     = now();
                    $inbound->correction_notes = $notes;
                    $inbound->save();

                    $processed++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($processed === 0) {
            return back()->with('error', 'Tidak ada koreksi yang disimpan.');
        }

        return redirect()->route('inbounds.index')
            ->with('success', 'Koreksi Inbound tersimpan.');
    }
}