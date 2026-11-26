<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\InventoryStock;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        ])->withCount([
            'inbounds',
            'materialOutsAsMaterial',
            'materialOutsAsTarget',
            'materialReturnsAsMaterial',
            'materialReturnsAsTarget',
            'productions',
            'productionNgs',
            'deliveries',
            'returnToSuppliers',
            'stockAdjustments',
            'problemDelays',
            'lotTracersAsMaterial',
            'lotTracersAsTarget',
            'bomsAsOutput',
            'bomsAsInput',
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

    public function info(Item $item)
    {
        $item->load([
            'company',
            'inventoryStock',
            'bomsAsOutput.inputItem.inventoryStock',
            'bomsAsOutput.process',
            'bomsAsInput.outputItem.inventoryStock',
            'bomsAsInput.process',
        ]);

        return response()->json($item->material_info_payload);
    }

    /**
     * Halaman cetak stiker QR Info Rak (satuan, centang massal, atau semua item aktif).
     * URL QR otomatis mengikuti domain aktif saat dicetak (route item_cards.show).
     */
    public function qrPrint(Request $request)
    {
        $layout = (string) $request->query('layout', 'a4_3');
        if (!in_array($layout, ['a4_3', 'a4_4', 'thermal'], true)) {
            $layout = 'a4_3';
        }

        $ids    = [];
        $rawIds = $request->input('ids');
        if (is_array($rawIds)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $rawIds), fn ($v) => $v > 0)));
        } elseif (is_string($rawIds) && trim($rawIds) !== '') {
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $rawIds)), fn ($v) => $v > 0)));
        }

        if (empty($ids) && $request->filled('item_id')) {
            $singleId = (int) $request->input('item_id');
            if ($singleId > 0) {
                $ids = [$singleId];
            }
        }

        $query = Item::with('company')->orderBy('type')->orderBy('part_name');

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } elseif ($request->boolean('all')) {
            if ($request->filled('type')) {
                $query->where('type', $request->query('type'));
            }
        } else {
            return redirect()->route('items.index')
                ->with('error', 'Pilih minimal 1 item untuk mencetak label QR.');
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            return redirect()->route('items.index')
                ->with('error', 'Tidak ada item aktif yang cocok untuk dicetak.');
        }

        $labels = $items->map(function ($it) {
            return [
                'id'           => $it->id,
                'part_no'      => $it->part_no ?: '-',
                'part_name'    => $it->part_name,
                'type'         => $it->type,
                'uom'          => $it->uom,
                'company_code' => $it->company->company_code ?? 'INTERNAL',
                'company_type' => $it->company->type_company ?? 'INTERNAL',
                'qr_url'       => route('item_cards.show', $it->id),
            ];
        })->values();

        return view('items.qr-print', [
            'labels'     => $labels,
            'layout'     => $layout,
            'currentUrl' => $request->getSchemeAndHttpHost(),
            'isLocalUrl' => str_contains($request->getHost(), 'trycloudflare')
                || str_contains($request->getHost(), 'ngrok')
                || in_array($request->getHost(), ['127.0.0.1', 'localhost'], true)
                || str_ends_with($request->getHost(), '.test'),
        ]);
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
            'part_no' => [
                $type === 'RM' ? 'nullable' : 'required',
                'string',
                'max:100',
                Rule::unique('items', 'part_no')->whereNull('deleted_at'),
            ],
            'part_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('items', 'part_name')->whereNull('deleted_at'),
            ],
            'type'       => 'required|in:RM,FG,FG/KOMPONEN,KOMPONEN,TRANSIT',
            'uom'        => 'required|in:PCS,KG,SHEET,BATANG,MTR,LTR,SET',
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
        ];

        if ($allowsPo) {
            $rules['qty_po']       = 'nullable|numeric|min:0|max:' . Item::MAX_QTY;
            $rules['working_days'] = 'nullable|integer|min:0|max:366';
            $rules['qty_kanban']   = 'nullable|numeric|min:0|max:' . Item::MAX_QTY;
        }

        $request->validate($rules, [
            'part_no.required'  => 'Part No wajib diisi untuk tipe item selain RM.',
            'part_no.unique'    => "Part No {$request->part_no} sudah terdaftar pada item aktif lain.",
            'part_name.unique'  => "Part Name {$request->part_name} sudah terdaftar pada item aktif lain.",
            'company_id.exists' => 'Perusahaan tidak ditemukan atau sudah dinonaktifkan.',
            'qty_po.max'        => 'Qty PO maksimal 99.999.999,99.',
            'qty_kanban.max'    => 'Qty Kanban maksimal 99.999.999,99.',
        ]);

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
            $qtyPo       = ($allowsPo && $request->filled('qty_po')) ? round((float) $request->qty_po, 2) : 0;
            $workingDays = ($allowsPo && $request->filled('working_days')) ? (int) $request->working_days : 0;
            $qtyKanban   = ($allowsPo && $request->filled('qty_kanban')) ? round((float) $request->qty_kanban, 2) : 0;

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
                'part_no' => [
                    $type === 'RM' ? 'nullable' : 'required',
                    'string',
                    'max:100',
                    Rule::unique('items', 'part_no')->ignore($item->id)->whereNull('deleted_at'),
                ],
                'part_name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('items', 'part_name')->ignore($item->id)->whereNull('deleted_at'),
                ],
                'type'       => 'required|in:RM,FG,FG/KOMPONEN,KOMPONEN,TRANSIT',
                'uom'        => 'required|in:PCS,KG,SHEET,BATANG,MTR,LTR,SET',
                'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            ];

            if ($allowsPo) {
                $rules['qty_po']       = 'nullable|numeric|min:0|max:' . Item::MAX_QTY;
                $rules['working_days'] = 'nullable|integer|min:0|max:366';
                $rules['qty_kanban']   = 'nullable|numeric|min:0|max:' . Item::MAX_QTY;
            }

            $request->validate($rules, [
                'part_no.required'  => 'Part No wajib diisi untuk tipe item selain RM.',
                'part_no.unique'    => "Part No {$request->part_no} sudah terdaftar pada item aktif lain.",
                'part_name.unique'  => "Part Name {$request->part_name} sudah terdaftar pada item aktif lain.",
                'company_id.exists' => 'Perusahaan tidak ditemukan atau sudah dinonaktifkan.',
                'qty_po.max'        => 'Qty PO maksimal 99.999.999,99.',
                'qty_kanban.max'    => 'Qty Kanban maksimal 99.999.999,99.',
            ]);

            $company      = Company::findOrFail($request->company_id);
            $allowedTypes = Item::allowedCompanyTypesForItemType($type);

            if (!in_array($company->type_company, $allowedTypes, true)) {
                return back()->withInput()->with(
                    'error',
                    "Perusahaan bertipe {$company->type_company} tidak sesuai untuk item bertipe {$type}. Tipe perusahaan yang diizinkan: " . implode(' / ', $allowedTypes) . '.'
                );
            }

            $qtyPo       = ($allowsPo && $request->filled('qty_po')) ? round((float) $request->qty_po, 2) : 0;
            $workingDays = ($allowsPo && $request->filled('working_days')) ? (int) $request->working_days : 0;
            $qtyKanban   = ($allowsPo && $request->filled('qty_kanban')) ? round((float) $request->qty_kanban, 2) : 0;

            $calc = $allowsPo
                ? Item::calculatePoKanbanStd($qtyPo, $workingDays, $qtyKanban)
                : ['std_day_1_po_wd' => 0, 'std_day_1' => 0];

            DB::transaction(function () use ($item, $request, $type, $qtyPo, $workingDays, $qtyKanban, $calc) {
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

                InventoryStock::with('item')->get()->each(function ($st) {
                    $st->update(['status_stock' => $st->calculateDynamicStatus()]);
                });
            });
        } else {
            if (in_array($item->type, ['RM', 'KOMPONEN'], true)) {
                return back()->with('error', 'Parameter item bertipe ' . $item->type . ' yang sudah memiliki riwayat transaksi mengikuti perhitungan BOM secara otomatis.');
            }

            $request->validate([
                'qty_po'       => 'nullable|numeric|min:0|max:' . Item::MAX_QTY,
                'working_days' => 'nullable|integer|min:0|max:366',
                'qty_kanban'   => 'nullable|numeric|min:0|max:' . Item::MAX_QTY,
            ], [
                'qty_po.max'     => 'Qty PO maksimal 99.999.999,99.',
                'qty_kanban.max' => 'Qty Kanban maksimal 99.999.999,99.',
            ]);

            $qtyPo       = $request->filled('qty_po') ? round((float) $request->qty_po, 2) : 0;
            $workingDays = $request->filled('working_days') ? (int) $request->working_days : 0;
            $qtyKanban   = $request->filled('qty_kanban') ? round((float) $request->qty_kanban, 2) : 0;

            $calc = Item::calculatePoKanbanStd($qtyPo, $workingDays, $qtyKanban);

            DB::transaction(function () use ($item, $qtyPo, $workingDays, $qtyKanban, $calc) {
                $item->update([
                    'qty_po'          => $qtyPo,
                    'working_days'    => $workingDays,
                    'qty_kanban'      => $qtyKanban,
                    'std_day_1_po_wd' => $calc['std_day_1_po_wd'],
                    'std_day_1'       => $calc['std_day_1'],
                ]);

                InventoryStock::with('item')->get()->each(function ($st) {
                    $st->update(['status_stock' => $st->calculateDynamicStatus()]);
                });
            });
        }

        return redirect()->route('items.index')->with('success', 'Data Master Item berhasil diperbarui.');
    }

    public function destroy(Item $item)
    {
        if ($item->hasTransactionHistory()) {
            DB::transaction(function () use ($item) {
                $item->delete();

                // Segarkan status_stock seluruh item aktif (termasuk RM & KOMPONEN yang terhubung via BOM)
                InventoryStock::with('item')->whereHas('item')->get()->each(function ($st) {
                    $st->update(['status_stock' => $st->calculateDynamicStatus()]);
                });
            });

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

        if (!empty($item->part_no) && Item::where('part_no', $item->part_no)->exists()) {
            return back()->with('error', "Gagal mengaktifkan kembali: Part No {$item->part_no} sudah digunakan oleh item aktif lain.");
        }

        if (Item::where('part_name', $item->part_name)->exists()) {
            return back()->with('error', "Gagal mengaktifkan kembali: Part Name {$item->part_name} sudah digunakan oleh item aktif lain.");
        }

        DB::transaction(function () use ($item) {
            $item->restore();

            InventoryStock::firstOrCreate(
                ['item_id' => $item->id],
                ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
            );

            // Segarkan status_stock seluruh item aktif (termasuk RM & KOMPONEN pembentuknya di BOM)
            InventoryStock::with('item')->whereHas('item')->get()->each(function ($st) {
                $st->update(['status_stock' => $st->calculateDynamicStatus()]);
            });
        });

        return redirect()->route('items.trashed')->with('success', 'Item berhasil diaktifkan kembali.');
    }
}