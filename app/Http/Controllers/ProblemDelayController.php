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
            $startDate = $request->start_date;
            $query->where(function ($q) use ($startDate) {
                $q->whereDate('incident_date', '>=', $startDate)
                  ->orWhere(function ($sub) use ($startDate) {
                      $sub->whereNull('incident_date')
                          ->whereDate('date_time', '>=', $startDate);
                  });
            });
        }

        if ($request->filled('end_date')) {
            $endDate = $request->end_date;
            $query->where(function ($q) use ($endDate) {
                $q->whereDate('incident_date', '<=', $endDate)
                  ->orWhere(function ($sub) use ($endDate) {
                      $sub->whereNull('incident_date')
                          ->whereDate('date_time', '<=', $endDate);
                  });
            });
        }

        if ($request->filled('start_time')) {
            $query->whereTime('date_time', '>=', $request->start_time . ':00');
        }

        if ($request->filled('end_time')) {
            $query->whereTime('date_time', '<=', $request->end_time . ':59');
        }

        $delays  = $query->orderByDesc('incident_date')->orderByDesc('id')->get();
        $reasons = self::REASONS;

        return view('problem_delays.index', compact('delays', 'companies', 'items', 'reasons'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'incident_date'  => 'required|date',
            'company_id'     => 'required|exists:companies,id',
            'item_id'        => 'required|exists:items,id',
            'problem_reason' => 'required|in:' . implode(',', self::REASONS),
        ]);

        $company = Company::findOrFail($request->company_id);
        $item    = Item::findOrFail($request->item_id);

        if ($company->type_company === 'CUSTOMER') {
            $allowedForCustomer = ((int) $item->company_id === (int) $company->id)
                || in_array($item->type, ['FG/KOMPONEN', 'TRANSIT'], true);

            if (!$allowedForCustomer) {
                return back()->withInput()->with('error', 'Item yang dipilih tidak sesuai untuk pelanggan (Customer) tersebut.');
            }
        } else {
            if ((int) $item->company_id !== (int) $company->id) {
                return back()->withInput()->with('error', 'Item yang dipilih tidak terdaftar pada perusahaan tersebut.');
            }
        }

        ProblemDelay::create([
            'date_time'      => now(),
            'incident_date'  => $request->incident_date,
            'company_id'     => $request->company_id,
            'item_id'        => $request->item_id,
            'user_id'        => Auth::id(),
            'problem_reason' => strtoupper(trim($request->problem_reason)),
        ]);

        return redirect()->route('problem_delays.index')
            ->with('success', "Log kendala pengiriman untuk {$item->part_name} berhasil dicatat.");
    }
}