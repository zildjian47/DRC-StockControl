<?php

namespace App\Http\Controllers;

use App\Models\InventoryStock;
use App\Models\Item;
use Illuminate\Http\Request;

class InventoryStockController extends Controller
{
    public const TYPES         = ['RM', 'FG', 'FG/KOMPONEN', 'KOMPONEN', 'TRANSIT'];
    public const STATUSES      = ['EMPTY STOCK', 'VERY LOW', 'LOW STOCK', 'NORMAL STOCK', 'OVERSTOCK'];
    public const COMPANY_TYPES = ['SUPPLIER', 'INTERNAL', 'CUSTOMER'];

    public function index(Request $request)
    {
        $activeItems = Item::pluck('id');
        foreach ($activeItems as $itemId) {
            InventoryStock::firstOrCreate(
                ['item_id' => $itemId],
                ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
            );
        }

        $allStocks = InventoryStock::with([
            'item.company',
            'item.bomsAsInput.outputItem.inventoryStock',
            'item.bomsAsInput.process',
            'item.bomsAsOutput.inputItem.inventoryStock',
            'item.bomsAsOutput.process',
        ])->whereHas('item')->get();

        foreach ($allStocks as $st) {
            $newStatus = $st->calculateDynamicStatus();
            if ($st->status_stock !== $newStatus) {
                $st->status_stock = $newStatus;
                $st->save();
            }
        }

        $filtered = $allStocks->filter(function ($st) use ($request) {
            $item = $st->item;
            if (!$item) {
                return false;
            }

            if ($request->filled('type') && $item->type !== $request->type) {
                return false;
            }

            if ($request->filled('type_company')) {
                $compType = $item->company->type_company ?? 'INTERNAL';
                if ($compType !== $request->type_company) {
                    return false;
                }
            }

            if ($request->filled('status_stock') && $st->status_stock !== $request->status_stock) {
                return false;
            }

            if ($request->filled('search')) {
                $s = strtolower(trim($request->search));
                $matchNo   = str_contains(strtolower((string) $item->part_no), $s);
                $matchName = str_contains(strtolower((string) $item->part_name), $s);
                if (!$matchNo && !$matchName) {
                    return false;
                }
            }

            return true;
        })->sortBy(fn($st) => $st->item->part_name ?? '')->values();

        $stocks       = $filtered;
        $types        = self::TYPES;
        $statuses     = self::STATUSES;
        $companyTypes = self::COMPANY_TYPES;

        return view('inventory_stocks.index', compact('stocks', 'types', 'statuses', 'companyTypes'));
    }
}