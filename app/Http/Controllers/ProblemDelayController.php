<?php

namespace App\Http\Controllers;

use App\Models\ProblemDelay;
use App\Models\Company;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProblemDelayController extends Controller
{
    public const REASONS = [
        'MATERIAL',
        'PROSES',
        'QC',
        'DIES',
        'BOX/PALET',
        'PLANT 2',
    ];

    public function index(Request $request)
    {
        $companies = Company::orderBy('company_code')->get();
        $items     = Item::with('company')->orderBy('part_name')->get();

        $query = ProblemDelay::with(['company', 'item', 'user']);

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->whereHas('company', function ($cq) use ($s) {
                    $cq->where('company_code', 'like', "%{$s}%")
                       ->orWhere('company_name', 'like', "%{$s}%");
                })->orWhereHas('item', function ($iq) use ($s) {
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

        $delays  = $query->orderBy('id', 'desc')->get();
        $reasons = self::REASONS;

        return view('problem_delays.index', compact('delays', 'companies', 'items', 'reasons'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id'     => 'required|exists:companies,id',
            'item_id'        => 'required|exists:items,id',
            'problem_reason' => 'required|in:' . implode(',', self::REASONS),
        ]);

        $item = Item::findOrFail($request->item_id);
        if ((int) $item->company_id !== (int) $request->company_id) {
            return back()->with('error', 'Item yang dipilih tidak terdaftar pada perusahaan tersebut.');
        }

        ProblemDelay::create([
            'date_time'      => now(),
            'company_id'     => $request->company_id,
            'item_id'        => $request->item_id,
            'user_id'        => Auth::id(),
            'problem_reason' => strtoupper(trim($request->problem_reason)),
        ]);

        return redirect()->route('problem_delays.index')
            ->with('success', "Log kendala pengiriman untuk {$item->part_name} berhasil dicatat.");
    }
}