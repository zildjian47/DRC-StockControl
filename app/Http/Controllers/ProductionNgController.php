<?php

namespace App\Http\Controllers;

use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\LotTracer;
use App\Models\ProductionNg;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionNgController extends Controller
{
    public const REASONS = [
        'MATERIAL',
        'PROSES',
        'DIES/TOOLING',
        'SETTING/TRIAL',
        'HANDLING/OPERATOR',
    ];

    public function index(Request $request)
    {
        $reasons = self::REASONS;

        if ($request->query('mode') === 'scanner') {
            $allScansData = ProductionNg::with(['item.company', 'user'])
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
                        'part_no'    => $s->item?->part_no ?: '-',
                        'part_name'  => $s->item?->part_name ?? '-',
                        'uom'        => $s->item?->uom ?? 'PCS',
                        'reason'     => $s->problem_reason,
                        'qty_ng'     => (float) $s->qty_ng,
                        'user_name'  => $s->user?->name ?? 'QC',
                    ];
                })->values();

            return view('production_ngs.scanner', compact('allScansData', 'reasons'));
        }

        $activeLots = LotTracer::with(['targetItem.inventoryStock', 'process'])
            ->where('status_lot', 'ON PROGRESS')
            ->whereColumn('actual_qty_prod', '>', 'qty_ng')
            ->orderBy('id', 'desc')
            ->get();

        $query = ProductionNg::with(['item.company', 'user', 'correctedBy']);

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('lot_number', 'like', "%{$s}%")
                  ->orWhereHas('item', function ($iq) use ($s) {
                      $iq->where('part_no', 'like', "%{$s}%")
                         ->orWhere('part_name', 'like', "%{$s}%");
                  });
            });
        }

        if ($request->filled('problem_reason')) {
            $query->where('problem_reason', $request->problem_reason);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('date_time', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date_time', '<=', $request->end_date);
        }

        if ($request->filled('start_time')) {
            $query->whereTime('date_time', '>=', $request->start_time . ':00');
        }

        if ($request->filled('end_time')) {
            $query->whereTime('date_time', '<=', $request->end_time . ':59');
        }

        $ngs = $query->orderBy('id', 'desc')->get();

        $stockMap = InventoryStock::whereIn('item_id', $ngs->pluck('item_id')->unique())
            ->pluck('current_stock', 'item_id');

        $lotMap = LotTracer::whereIn('lot_number', $ngs->pluck('lot_number')->unique())
            ->get()
            ->keyBy(fn ($l) => $l->lot_number . '|' . $l->target_item_id);

        return view('production_ngs.index', compact('ngs', 'activeLots', 'reasons', 'stockMap', 'lotMap'));
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

        $item = Item::findActiveByScanCode($rawCode, ['inventoryStock']);

        if (!$item) {
            return response()->json([
                'ok'      => false,
                'message' => "Kode \"{$rawCode}\" tidak ditemukan.",
            ]);
        }

        if (!in_array($item->type, ['FG', 'FG/KOMPONEN', 'KOMPONEN'], true)) {
            return response()->json([
                'ok'      => false,
                'message' => "{$item->part_name} tidak sesuai untuk Production NG (wajib FG, FG/KOMPONEN, atau KOMPONEN).",
            ]);
        }

        $lots = LotTracer::with(['targetItem', 'process'])
            ->where('target_item_id', $item->id)
            ->where('status_lot', 'ON PROGRESS')
            ->whereColumn('actual_qty_prod', '>', 'qty_ng')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($lt) => [
                'id'             => $lt->id,
                'lot_number'     => $lt->lot_number,
                'target_item_id' => $lt->target_item_id,
                'process_name'   => $lt->process?->process_name ?? '-',
                'actual_prod'    => (float) $lt->actual_qty_prod,
                'qty_ng'         => (float) $lt->qty_ng,
                'act_fg'         => (float) $lt->act_fg,
                'uom'            => $lt->targetItem?->uom ?? $item->uom ?? 'PCS',
            ])->values();

        if ($lots->isEmpty()) {
            return response()->json([
                'ok'      => false,
                'message' => "Tidak ada Lot aktif dengan saldo Act FG untuk {$item->part_name}.",
            ]);
        }

        return response()->json([
            'ok'   => true,
            'item' => [
                'id'        => $item->id,
                'part_no'   => $item->part_no ? strtoupper(trim($item->part_no)) : '',
                'part_name' => strtoupper(trim($item->part_name)),
                'type'      => $item->type,
                'uom'       => $item->uom,
                'stock'     => $item->inventoryStock ? (float) $item->inventoryStock->current_stock : 0.0,
            ],
            'lots' => $lots,
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

        // Jika dikirim dari Mobile Scanner menggunakan item_id + lot_number
        if (!$request->filled('lot_tracer_id') && $request->filled('lot_number') && $request->filled('item_id')) {
            $foundLot = LotTracer::where('lot_number', strtoupper(trim((string) $request->lot_number)))
                ->where('target_item_id', $request->item_id)
                ->first();
            if ($foundLot) {
                $request->merge(['lot_tracer_id' => $foundLot->id]);
            }
        }

        $request->validate([
            'lot_tracer_id'  => 'required|exists:lot_tracers,id',
            'qty_ng'         => 'required',
            'problem_reason' => 'required|in:' . implode(',', self::REASONS),
        ]);

        $qtyNg = round(BomController::evaluateMath($request->qty_ng), 2);
        if ($qtyNg <= 0) {
            return back()->withInput()->with('error', 'Qty NG harus lebih dari 0.');
        }
        if ($qtyNg > Item::MAX_QTY) {
            return back()->withInput()->with('error', 'Qty NG melebihi batas maksimal 99.999.999,99.');
        }

        $lotNumber = '';
        $targetUom = '';

        try {
            DB::transaction(function () use ($request, $qtyNg, &$lotNumber, &$targetUom) {
                $lot = LotTracer::with('targetItem')->lockForUpdate()->find($request->lot_tracer_id);
                if (!$lot) {
                    throw new \RuntimeException('Data Lot Tracer tidak ditemukan.');
                }

                if ($lot->status_lot !== 'ON PROGRESS') {
                    throw new \RuntimeException("Lot {$lot->lot_number} sudah berstatus FINISH.");
                }

                $targetUom = $lot->targetItem?->uom ?? '';
                $maxAllow  = round(max(0, (float) $lot->actual_qty_prod - (float) $lot->qty_ng), 2);
                if ($qtyNg > $maxAllow) {
                    throw new \RuntimeException('Qty NG melebihi saldo Act FG (maksimal ' . floatval($maxAllow) . " {$targetUom}).");
                }

                InventoryStock::firstOrCreate(
                    ['item_id' => $lot->target_item_id],
                    ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                );
                $stock = InventoryStock::where('item_id', $lot->target_item_id)->lockForUpdate()->first();

                if ($qtyNg > (float) $stock->current_stock) {
                    throw new \RuntimeException('Stok gudang kurang. Tersedia: ' . floatval($stock->current_stock) . " {$targetUom}.");
                }

                ProductionNg::create([
                    'date_time'      => now(),
                    'lot_number'     => $lot->lot_number,
                    'item_id'        => $lot->target_item_id,
                    'user_id'        => Auth::id(),
                    'qty_ng'         => $qtyNg,
                    'problem_reason' => strtoupper(trim($request->problem_reason)),
                    'qty_check'      => 'TERCATAT',
                ]);

                $lot->qty_ng = round((float) $lot->qty_ng + $qtyNg, 2);
                $lot->recalculateLotMetrics();

                $stock->current_stock = round((float) $stock->current_stock - $qtyNg, 2);
                $stock->status_stock  = $stock->calculateDynamicStatus();
                $stock->save();

                $lotNumber = $lot->lot_number;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $msg = "Data NG Lot {$lotNumber} (-" . floatval($qtyNg) . " {$targetUom}) tersimpan.";

        if ($request->input('mode') === 'scanner') {
            return redirect()->route('production_ngs.index', ['mode' => 'scanner'])->with('success', $msg);
        }

        return redirect()->route('production_ngs.index')->with('success', $msg);
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
                        throw new \RuntimeException('Format Qty NG koreksi tidak valid (wajib > 0).');
                    }
                    if ($newQty > Item::MAX_QTY) {
                        throw new \RuntimeException('Qty NG koreksi melebihi batas maksimal 99.999.999,99.');
                    }

                    $newReason = isset($data['problem_reason'])
                        ? strtoupper(trim((string) $data['problem_reason']))
                        : null;
                    if ($newReason !== null && $newReason !== '' && !in_array($newReason, self::REASONS, true)) {
                        throw new \RuntimeException('Kategori Problem Reason tidak valid.');
                    }

                    $notesRaw = trim((string) ($data['correction_notes'] ?? ''));
                    $notes    = $notesRaw !== '' ? mb_substr($notesRaw, 0, 500) : null;

                    $ng = ProductionNg::with('item')->lockForUpdate()->find($id);
                    if (!$ng) {
                        throw new \RuntimeException('Data transaksi Production NG tidak ditemukan.');
                    }

                    $lot = LotTracer::where('lot_number', $ng->lot_number)
                        ->where('target_item_id', $ng->item_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$lot) {
                        throw new \RuntimeException("Data Lot Tracer untuk Lot {$ng->lot_number} tidak ditemukan.");
                    }

                    if ($lot->status_lot === 'FINISH') {
                        throw new \RuntimeException("Lot {$lot->lot_number} sudah berstatus FINISH sehingga Production NG tidak dapat dikoreksi.");
                    }

                    $oldQty   = (float) $ng->qty_ng;
                    $diff     = round($newQty - $oldQty, 2);
                    $newLotNg = round((float) $lot->qty_ng + $diff, 2);
                    $partName = $ng->item?->part_name ?? 'Item';

                    if ($newLotNg > (float) $lot->actual_qty_prod + 0.0001) {
                        $maxThisNg = max(0, round($oldQty + ((float) $lot->actual_qty_prod - (float) $lot->qty_ng), 2));
                        throw new \RuntimeException('Qty NG melebihi saldo Act FG pada Lot ini (maksimal ' . floatval($maxThisNg) . ').');
                    }

                    InventoryStock::firstOrCreate(
                        ['item_id' => $ng->item_id],
                        ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                    );
                    $stock     = InventoryStock::where('item_id', $ng->item_id)->lockForUpdate()->first();
                    $nextStock = round((float) $stock->current_stock - $diff, 2);

                    if ($diff > 0 && $nextStock < -0.0001) {
                        throw new \RuntimeException("Stok gudang {$partName} tidak cukup untuk kenaikan Qty NG (tersedia " . floatval($stock->current_stock) . ').');
                    }
                    if ($nextStock > Item::MAX_QTY) {
                        throw new \RuntimeException("Stok gudang {$partName} melebihi batas maksimal 99.999.999,99.");
                    }

                    $stock->current_stock = max(0, $nextStock);
                    $stock->status_stock  = $stock->calculateDynamicStatus();
                    $stock->save();

                    if ($ng->qty_original === null) {
                        $ng->qty_original = $oldQty;
                    }
                    $ng->qty_ng = $newQty;
                    if ($newReason !== null && $newReason !== '') {
                        $ng->problem_reason = $newReason;
                    }
                    $ng->qty_check        = 'DIKOREKSI';
                    $ng->corrected_by     = Auth::id();
                    $ng->corrected_at     = now();
                    $ng->correction_notes = $notes;
                    $ng->save();

                    $lot->qty_ng = max(0, $newLotNg);
                    $lot->recalculateLotMetrics();

                    $processed++;
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan koreksi Production NG.', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);

            return back()->with('error', 'Terjadi kesalahan sistem saat menyimpan koreksi Production NG. Silakan coba lagi.');
        }

        if ($processed === 0) {
            return back()->with('error', 'Tidak ada koreksi yang disimpan.');
        }

        return redirect()->route('production_ngs.index')
            ->with('success', 'Koreksi Production NG tersimpan.');
    }
}