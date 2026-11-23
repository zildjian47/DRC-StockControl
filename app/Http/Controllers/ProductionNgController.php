<?php

namespace App\Http\Controllers;

use App\Models\ProductionNg;
use App\Models\LotTracer;
use App\Models\Item;
use App\Models\InventoryStock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
                        'part_no'    => $s->item->part_no ?: '-',
                        'part_name'  => $s->item->part_name ?? '-',
                        'uom'        => $s->item->uom ?? 'PCS',
                        'reason'     => $s->problem_reason,
                        'qty_ng'     => (float) $s->qty_ng,
                        'user_name'  => $s->user->name ?? 'QC',
                    ];
                })->values();

            return view('production_ngs.scanner', compact('allScansData', 'reasons'));
        }

        $activeLots = LotTracer::with(['targetItem.inventoryStock', 'process'])
            ->where('status_lot', 'ON PROGRESS')
            ->whereColumn('actual_qty_prod', '>', 'qty_ng')
            ->orderBy('id', 'desc')
            ->get();

        $query = ProductionNg::with(['item.company', 'user']);

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

        return view('production_ngs.index', compact('ngs', 'activeLots', 'reasons'));
    }

    public function lookup(Request $request)
    {
        $rawCode = trim((string) $request->query('code', ''));
        $code    = strtoupper($rawCode);

        if ($code === '') {
            return response()->json([
                'ok'      => false,
                'message' => 'Kode QR / Part wajib diisi.',
            ]);
        }

        $item = Item::with('inventoryStock')
            ->where('part_no', $code)
            ->orWhere('part_name', $code)
            ->first();

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
                'process_name'   => $lt->process->process_name ?? '-',
                'actual_prod'    => (float) $lt->actual_qty_prod,
                'qty_ng'         => (float) $lt->qty_ng,
                'act_fg'         => (float) $lt->act_fg,
                'uom'            => $lt->targetItem->uom ?? $item->uom ?? 'PCS',
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

        $lotNumber = '';
        $targetUom = '';

        try {
            DB::transaction(function () use ($request, $qtyNg, &$lotNumber, &$targetUom) {
                $lot = LotTracer::with('targetItem')->lockForUpdate()->findOrFail($request->lot_tracer_id);

                if ($lot->status_lot !== 'ON PROGRESS') {
                    throw new \RuntimeException("Lot {$lot->lot_number} sudah berstatus FINISH.");
                }

                $maxAllow = max(0, (float) $lot->actual_qty_prod - (float) $lot->qty_ng);
                if ($qtyNg > $maxAllow) {
                    throw new \RuntimeException('Qty NG melebihi saldo Act FG (maksimal ' . floatval($maxAllow) . " {$lot->targetItem->uom}).");
                }

                InventoryStock::firstOrCreate(
                    ['item_id' => $lot->target_item_id],
                    ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
                );
                $stock = InventoryStock::where('item_id', $lot->target_item_id)->lockForUpdate()->first();

                if ($qtyNg > (float) $stock->current_stock) {
                    throw new \RuntimeException('Stok gudang kurang. Tersedia: ' . floatval($stock->current_stock) . " {$lot->targetItem->uom}.");
                }

                ProductionNg::create([
                    'date_time'      => now(),
                    'lot_number'     => $lot->lot_number,
                    'item_id'        => $lot->target_item_id,
                    'user_id'        => Auth::id(),
                    'qty_ng'         => $qtyNg,
                    'problem_reason' => strtoupper(trim($request->problem_reason)),
                ]);

                $lot->qty_ng = (float) $lot->qty_ng + $qtyNg;
                $lot->recalculateLotMetrics();

                $stock->current_stock = (float) $stock->current_stock - $qtyNg;
                $stock->status_stock  = $stock->calculateDynamicStatus();
                $stock->save();

                $lotNumber = $lot->lot_number;
                $targetUom = $lot->targetItem->uom ?? '';
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
}