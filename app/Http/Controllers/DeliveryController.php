<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\InventoryStock;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('mode') === 'scanner') {
            $allScansData = Delivery::with(['item.company', 'user'])
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
                        'part_no'   => $s->item?->part_no ?: '-',
                        'part_name' => $s->item?->part_name ?? '-',
                        'uom'       => $s->item?->uom ?? 'PCS',
                        'company'   => $s->item?->company?->company_code ?? '-',
                        'qty'       => (float) $s->qty,
                        'user_name' => $s->user?->name ?? 'Operator',
                    ];
                })->values();

            return view('deliveries.scanner', compact('allScansData'));
        }

        $deliveries = Delivery::with(['item.company', 'user', 'correctedBy'])
            ->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $stockMap = InventoryStock::whereIn('item_id', $deliveries->pluck('item_id')->unique())
            ->pluck('current_stock', 'item_id');

        return view('deliveries.index', compact('deliveries', 'stockMap'));
    }

    public function lookup(Request $request)
    {
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

        if (!in_array($item->type, ['FG', 'FG/KOMPONEN', 'TRANSIT'], true)) {
            return response()->json([
                'ok'      => false,
                'message' => "{$item->part_name} tidak sesuai untuk Delivery (wajib FG, FG/KOMPONEN, atau TRANSIT).",
            ]);
        }

        $compType = $item->company ? $item->company->type_company : 'INTERNAL';

        if ($item->type === 'FG' && $compType !== 'CUSTOMER') {
            return response()->json([
                'ok'      => false,
                'message' => 'Item FG wajib ber-perusahaan CUSTOMER.',
            ]);
        }
        if ($item->type === 'FG/KOMPONEN' && !in_array($compType, ['INTERNAL', 'CUSTOMER'], true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Item FG/KOMPONEN wajib ber-perusahaan INTERNAL atau CUSTOMER.',
            ]);
        }
        if ($item->type === 'TRANSIT' && !in_array($compType, ['SUPPLIER', 'CUSTOMER'], true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Item TRANSIT wajib ber-perusahaan SUPPLIER atau CUSTOMER.',
            ]);
        }

        $stock = $item->inventoryStock ? (float) $item->inventoryStock->current_stock : 0.0;
        if ($stock <= 0) {
            return response()->json([
                'ok'      => false,
                'message' => "Stok {$item->part_name} kosong (0 {$item->uom}).",
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
                'stock'        => $stock,
            ],
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->filled('item_id') && $request->filled('scanned_code')) {
            $found = Item::findActiveByScanCode((string) $request->scanned_code);
            if ($found) {
                $request->merge(['item_id' => $found->id]);
            }
        }

        $request->validate([
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'qty'     => 'required',
        ], [
            'item_id.exists' => 'Item tidak ditemukan atau sudah dinonaktifkan.',
        ]);

        $qty = round(BomController::evaluateMath($request->qty), 2);
        if ($qty <= 0) {
            return back()->withInput()->with('error', 'Qty harus lebih dari 0.');
        }
        if ($qty > Item::MAX_QTY) {
            return back()->withInput()->with('error', 'Qty melebihi batas maksimal 99.999.999,99.');
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

                $stock->current_stock = round((float) $stock->current_stock - $qty, 2);
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
        $items     = $request->input('items', []);
        $processed = 0;

        try {
            DB::transaction(function () use ($items, &$processed) {
                foreach ($items as $id => $data) {
                    $rawActual = $data['actual_qty'] ?? null;
                    $newQty    = StockAdjustmentController::parseSignedCalculatorValue($rawActual);
                    if ($newQty === null || $newQty <= 0) {
                        throw new \RuntimeException('Format Qty koreksi tidak valid (wajib > 0).');
                    }
                    if ($newQty > Item::MAX_QTY) {
                        throw new \RuntimeException('Qty koreksi melebihi batas maksimal 99.999.999,99.');
                    }

                    $notesRaw = trim((string) ($data['correction_notes'] ?? ''));
                    $notes    = $notesRaw !== '' ? mb_substr($notesRaw, 0, 500) : null;

                    $delivery = Delivery::with('item')->lockForUpdate()->find($id);
                    if (!$delivery) {
                        throw new \RuntimeException('Data transaksi Delivery tidak ditemukan.');
                    }

                    $oldQty   = (float) $delivery->qty;
                    $diff     = round($newQty - $oldQty, 2);
                    $partName = $delivery->item?->part_name ?? 'Item';

                    InventoryStock::firstOrCreate(
                        ['item_id' => $delivery->item_id],
                        ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                    );
                    $stock     = InventoryStock::where('item_id', $delivery->item_id)->lockForUpdate()->first();
                    $nextStock = round((float) $stock->current_stock - $diff, 2);

                    if ($diff > 0 && $nextStock < 0) {
                        throw new \RuntimeException("Stok {$partName} tidak cukup untuk koreksi.");
                    }
                    if ($nextStock > Item::MAX_QTY) {
                        throw new \RuntimeException("Stok {$partName} melebihi batas maksimal 99.999.999,99.");
                    }

                    $stock->current_stock = $nextStock;
                    $stock->status_stock  = $stock->calculateDynamicStatus();
                    $stock->save();

                    if ($delivery->qty_original === null) {
                        $delivery->qty_original = $oldQty;
                    }
                    $delivery->qty              = $newQty;
                    $delivery->qty_check        = 'DIKOREKSI';
                    $delivery->corrected_by     = Auth::id();
                    $delivery->corrected_at     = now();
                    $delivery->correction_notes = $notes;
                    $delivery->save();

                    $processed++;
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan koreksi Delivery.', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);

            return back()->with('error', 'Terjadi kesalahan sistem saat menyimpan koreksi Delivery. Silakan coba lagi.');
        }

        if ($processed === 0) {
            return back()->with('error', 'Tidak ada koreksi yang disimpan.');
        }

        return redirect()->route('deliveries.index')
            ->with('success', 'Koreksi Delivery tersimpan.');
    }
}