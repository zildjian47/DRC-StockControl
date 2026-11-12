<?php

namespace App\Http\Controllers;

use App\Models\LotTracer;
use App\Models\Process;
use Illuminate\Http\Request;

class LotTracerController extends Controller
{
    public function index(Request $request)
    {
        $processes = Process::orderBy('process_name')->get();
        $query     = LotTracer::with(['process', 'materialItem.company', 'targetItem.company']);

        if ($request->filled('process_id')) {
            $query->where('process_id', $request->process_id);
        }

        if ($request->filled('status_lot')) {
            $query->where('status_lot', $request->status_lot);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('lot_number', 'like', "%{$s}%")
                  ->orWhereHas('materialItem', function ($mq) use ($s) {
                      $mq->where('part_no', 'like', "%{$s}%")
                         ->orWhere('part_name', 'like', "%{$s}%");
                  })
                  ->orWhereHas('targetItem', function ($tq) use ($s) {
                      $tq->where('part_no', 'like', "%{$s}%")
                         ->orWhere('part_name', 'like', "%{$s}%");
                  });
            });
        }

        $lots = $query->orderBy('id', 'desc')->get();

        return view('lot_tracers.index', compact('lots', 'processes'));
    }

    public function finish(LotTracer $lotTracer)
    {
        $lotTracer->status_lot = 'FINISH';
        $lotTracer->qty_wip    = 0;
        $lotTracer->save();

        $topEndMsg = '';
        if ($lotTracer->is_coil && $lotTracer->top_end_coil > 0) {
            $topEndMsg = ' (Top / End Coil tercatat: ' . floatval($lotTracer->top_end_coil) . ' ' . ($lotTracer->materialItem->uom ?? 'KG') . ')';
        }

        return redirect()->route('lot_tracers.index')
            ->with('success', "Nomor Lot {$lotTracer->lot_number} ({$lotTracer->targetItem->part_name}) berhasil ditutup (FINISH){$topEndMsg}.");
    }
}