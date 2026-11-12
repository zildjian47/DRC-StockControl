<?php

namespace App\Http\Controllers;

use App\Models\ProductionNg;
use App\Models\LotTracer;
use App\Models\Item;
use App\Models\InventoryStock;
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
            $recentScans = ProductionNg::with(['item.company', 'user'])
                ->whereDate('date_time', today())
                ->orderBy('id', 'desc')
                ->get();

            return view('production_ngs.scanner', compact('recentScans', 'reasons'));
        }

        $activeLots = LotTracer::with(['targetItem', 'process'])
            ->where('status_lot', 'ON PROGRESS')
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
            'qty_ng'         => 'required|numeric|gt:0',
            'problem_reason' => 'required|in:' . implode(',', self::REASONS),
        ]);

        $lot = LotTracer::with('targetItem')->findOrFail($request->lot_tracer_id);

        if ($lot->status_lot !== 'ON PROGRESS') {
            return back()->with('error', "Ditolak: Nomor Lot {$lot->lot_number} sudah berstatus FINISH.");
        }

        $qtyNg    = (float) $request->qty_ng;
        $maxAllow = max(0, (float) $lot->actual_qty_prod - (float) $lot->qty_ng);

        if ($qtyNg > $maxAllow) {
            return back()->with('error', "Ditolak: Qty NG ({$qtyNg}) melebihi saldo Act FG yang sudah diproduksi pada Lot {$lot->lot_number} (Maksimal: {$maxAllow} {$lot->targetItem->uom}).");
        }

        $stock = InventoryStock::firstOrCreate(
            ['item_id' => $lot->target_item_id],
            ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
        );

        if ($qtyNg > (float) $stock->current_stock) {
            return back()->with('error', "Ditolak: Qty NG ({$qtyNg}) melebihi Current Stock {$lot->targetItem->part_name} di gudang ({$stock->current_stock}).");
        }

        DB::transaction(function () use ($lot, $stock, $qtyNg, $request) {
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
        });

        $msg = "Data NG Lot {$lot->lot_number} ({$lot->targetItem->part_name}) sebanyak -" . floatval($qtyNg) . " {$lot->targetItem->uom} berhasil disimpan.";

        if ($request->input('mode') === 'scanner') {
            return redirect()->route('production_ngs.index', ['mode' => 'scanner'])->with('success', $msg);
        }

        return redirect()->route('production_ngs.index')->with('success', $msg);
    }
}