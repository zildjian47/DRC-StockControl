<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Company;
use App\Models\InventoryStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::orderBy('company_code')->get();
        $uoms      = ['PCS', 'KG', 'SHEET', 'BATANG', 'MTR', 'LTR', 'SET'];
        $types     = ['FG', 'FG/KOMPONEN', 'KOMPONEN', 'RM', 'TRANSIT'];

        $query = Item::with([
            'company',
            'inventoryStock',
            'bomsAsInput.outputItem',
            'bomsAsOutput.inputItem.inventoryStock',
            'bomsAsOutput.process',
        ]);

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('part_no', 'like', "%{$s}%")
                  ->orWhere('part_name', 'like', "%{$s}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $items        = $query->orderBy('id', 'desc')->get();
        $trashedCount = Item::onlyTrashed()->count();

        return view('items.index', compact('items', 'companies', 'uoms', 'types', 'trashedCount'));
    }

    public function trashed(Request $request)
    {
        $query = Item::onlyTrashed()->with(['company', 'inventoryStock']);

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('part_no', 'like', "%{$s}%")
                  ->orWhere('part_name', 'like', "%{$s}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $items = $query->orderBy('deleted_at', 'desc')->get();
        $types = ['FG', 'FG/KOMPONEN', 'KOMPONEN', 'RM', 'TRANSIT'];

        return view('items.trashed', compact('items', 'types'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'part_no'   => $request->filled('part_no') ? strtoupper(trim((string) $request->part_no)) : null,
            'part_name' => strtoupper(trim((string) $request->part_name)),
        ]);

        $type     = (string) $request->input('type');
        $allowsPo = in_array($type, ['FG', 'TRANSIT', 'FG/KOMPONEN'], true);

        $rules = [
            'part_no'    => 'nullable|string|max:100',
            'part_name'  => 'required|string|max:255',
            'type'       => 'required|in:RM,FG,FG/KOMPONEN,KOMPONEN,TRANSIT',
            'uom'        => 'required|in:PCS,KG,SHEET,BATANG,MTR,LTR,SET',
            'company_id' => 'required|exists:companies,id',
        ];

        if ($allowsPo) {
            $rules['qty_po']       = 'required|numeric|min:0';
            $rules['working_days'] = 'required|integer|min:0';
            $rules['qty_kanban']   = 'required|numeric|min:0';
        }

        $request->validate($rules);

        // Validasi kesesuaian Type Item vs Type Company
        $company      = Company::findOrFail($request->company_id);
        $allowedTypes = Item::allowedCompanyTypesForItemType($type);

        if (!in_array($company->type_company, $allowedTypes, true)) {
            return back()->withInput()->with(
                'error',
                "Perusahaan bertipe {$company->type_company} tidak sesuai untuk item bertipe {$type}. Tipe perusahaan yang diizinkan: " . implode(' / ', $allowedTypes) . '.'
            );
        }

        DB::transaction(function () use ($request, $allowsPo) {
            $qtyPo       = $allowsPo ? (float) $request->qty_po : 0;
            $workingDays = $allowsPo ? (int) $request->working_days : 0;
            $qtyKanban   = $allowsPo ? (float) $request->qty_kanban : 0;

            $calc = $allowsPo
                ? Item::calculatePoKanbanStd($qtyPo, $workingDays, $qtyKanban)
                : ['std_day_1_po_wd' => 0, 'std_day_1' => 0];

            $item = Item::create([
                'part_no'         => $request->part_no,
                'part_name'       => $request->part_name,
                'type'            => $request->type,
                'uom'             => $request->uom,
                'company_id'      => $request->company_id,
                'qty_po'          => $qtyPo,
                'working_days'    => $workingDays,
                'qty_kanban'      => $qtyKanban,
                'std_day_1_po_wd' => $calc['std_day_1_po_wd'],
                'std_day_1'       => $calc['std_day_1'],
            ]);

            InventoryStock::firstOrCreate(
                ['item_id' => $item->id],
                ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
            );
        });

        return redirect()->route('items.index')->with('success', 'Master Item baru berhasil didaftarkan.');
    }

    public function update(Request $request, Item $item)
    {
        $hasHistory = $item->hasTransactionHistory();

        if (!$hasHistory) {
            $request->merge([
                'part_no'   => $request->filled('part_no') ? strtoupper(trim((string) $request->part_no)) : null,
                'part_name' => strtoupper(trim((string) $request->part_name)),
            ]);

            $type     = (string) $request->input('type', $item->type);
            $allowsPo = in_array($type, ['FG', 'TRANSIT', 'FG/KOMPONEN'], true);

            $rules = [
                'part_no'    => 'nullable|string|max:100',
                'part_name'  => 'required|string|max:255',
                'type'       => 'required|in:RM,FG,FG/KOMPONEN,KOMPONEN,TRANSIT',
                'uom'        => 'required|in:PCS,KG,SHEET,BATANG,MTR,LTR,SET',
                'company_id' => 'required|exists:companies,id',
            ];

            if ($allowsPo) {
                $rules['qty_po']       = 'required|numeric|min:0';
                $rules['working_days'] = 'required|integer|min:0';
                $rules['qty_kanban']   = 'required|numeric|min:0';
            }

            $request->validate($rules);

            $company      = Company::findOrFail($request->company_id);
            $allowedTypes = Item::allowedCompanyTypesForItemType($type);

            if (!in_array($company->type_company, $allowedTypes, true)) {
                return back()->withInput()->with(
                    'error',
                    "Perusahaan bertipe {$company->type_company} tidak sesuai untuk item bertipe {$type}. Tipe perusahaan yang diizinkan: " . implode(' / ', $allowedTypes) . '.'
                );
            }

            $qtyPo       = $allowsPo ? (float) $request->qty_po : 0;
            $workingDays = $allowsPo ? (int) $request->working_days : 0;
            $qtyKanban   = $allowsPo ? (float) $request->qty_kanban : 0;

            $calc = $allowsPo
                ? Item::calculatePoKanbanStd($qtyPo, $workingDays, $qtyKanban)
                : ['std_day_1_po_wd' => 0, 'std_day_1' => 0];

            $item->update([
                'part_no'         => $request->part_no,
                'part_name'       => $request->part_name,
                'type'            => $type,
                'uom'             => $request->uom,
                'company_id'      => $request->company_id,
                'qty_po'          => $qtyPo,
                'working_days'    => $workingDays,
                'qty_kanban'      => $qtyKanban,
                'std_day_1_po_wd' => $calc['std_day_1_po_wd'],
                'std_day_1'       => $calc['std_day_1'],
            ]);
        } else {
            if (in_array($item->type, ['RM', 'KOMPONEN'], true)) {
                return back()->with('error', 'Parameter item bertipe ' . $item->type . ' yang sudah memiliki riwayat transaksi mengikuti perhitungan BOM secara otomatis.');
            }

            $request->validate([
                'qty_po'       => 'required|numeric|min:0',
                'working_days' => 'required|integer|min:0',
                'qty_kanban'   => 'required|numeric|min:0',
            ]);

            $calc = Item::calculatePoKanbanStd($request->qty_po, $request->working_days, $request->qty_kanban);

            $item->update([
                'qty_po'          => (float) $request->qty_po,
                'working_days'    => (int) $request->working_days,
                'qty_kanban'      => (float) $request->qty_kanban,
                'std_day_1_po_wd' => $calc['std_day_1_po_wd'],
                'std_day_1'       => $calc['std_day_1'],
            ]);
        }

        InventoryStock::with('item')->get()->each(function ($st) {
            $st->update(['status_stock' => $st->calculateDynamicStatus()]);
        });

        return redirect()->route('items.index')->with('success', 'Data Master Item berhasil diperbarui.');
    }

    public function destroy(Item $item)
    {
        if ($item->hasTransactionHistory()) {
            $item->delete();
            return redirect()->route('items.index')->with('success', 'Item berhasil dinonaktifkan karena sudah memiliki riwayat transaksi.');
        }

        DB::transaction(function () use ($item) {
            InventoryStock::where('item_id', $item->id)->delete();
            $item->forceDelete();
        });

        return redirect()->route('items.index')->with('success', 'Item belum memiliki riwayat transaksi dan berhasil dihapus permanen.');
    }

    public function restore($id)
    {
        $item = Item::onlyTrashed()->findOrFail($id);
        $item->restore();

        if ($item->inventoryStock) {
            $item->inventoryStock->update([
                'status_stock' => $item->inventoryStock->calculateDynamicStatus(),
            ]);
        }

        return redirect()->route('items.trashed')->with('success', 'Item berhasil diaktifkan kembali.');
    }
}