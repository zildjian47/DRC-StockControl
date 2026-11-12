<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use App\Models\Item;
use App\Models\InventoryStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public const TYPES = ['STOCK OPNAME', 'LOSS', 'SURPLUS', 'FOUND', 'REPAIR'];

    /**
     * Evaluasi ekspresi kalkulator (+, -, *, /) dengan dukungan hasil positif, nol, maupun negatif (untuk Qty Diff).
     */
    public static function parseSignedCalculatorValue($raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $expr = trim(str_replace([',', 'x', 'X', ':'], ['.', '*', '*', '/'], (string) $raw));
        $expr = preg_replace('/\s+/', '', $expr);

        if ($expr === '' || !preg_match('/^[0-9.+\-*\/()]+$/', $expr)) {
            return null;
        }

        preg_match_all('/\d+(?:\.\d+)?|[+\-*\/()]/', $expr, $matches);
        $tokens = $matches[0] ?? [];
        if (empty($tokens) || implode('', $tokens) !== $expr) {
            return null;
        }

        $prec   = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];
        $output = [];
        $ops    = [];

        foreach ($tokens as $idx => $t) {
            if (is_numeric($t)) {
                $output[] = (float) $t;
            } elseif ($t === '(') {
                $ops[] = $t;
            } elseif ($t === ')') {
                while (!empty($ops) && end($ops) !== '(') {
                    $output[] = array_pop($ops);
                }
                if (empty($ops)) {
                    return null;
                }
                array_pop($ops);
            } elseif (isset($prec[$t])) {
                if (($t === '-' || $t === '+') && ($idx === 0 || in_array($tokens[$idx - 1], ['(', '+', '-', '*', '/'], true))) {
                    $output[] = 0.0;
                }
                while (!empty($ops) && end($ops) !== '(' && $prec[end($ops)] >= $prec[$t]) {
                    $output[] = array_pop($ops);
                }
                $ops[] = $t;
            }
        }

        while (!empty($ops)) {
            $op = array_pop($ops);
            if ($op === '(' || $op === ')') {
                return null;
            }
            $output[] = $op;
        }

        $stack = [];
        foreach ($output as $token) {
            if (is_float($token) || is_int($token)) {
                $stack[] = (float) $token;
            } else {
                if (count($stack) < 2) {
                    return null;
                }
                $b = array_pop($stack);
                $a = array_pop($stack);
                if ($token === '+') $stack[] = $a + $b;
                elseif ($token === '-') $stack[] = $a - $b;
                elseif ($token === '*') $stack[] = $a * $b;
                elseif ($token === '/') {
                    if (abs($b) < 1e-12) {
                        return null;
                    }
                    $stack[] = $a / $b;
                }
            }
        }

        if (count($stack) !== 1 || !is_finite($stack[0])) {
            return null;
        }

        return round((float) $stack[0], 2);
    }

    public function index(Request $request)
    {
        $items = Item::with(['company', 'inventoryStock'])->orderBy('part_name')->get();
        $types = self::TYPES;

        if ($request->query('mode') === 'scanner') {
            $recentScans = StockAdjustment::with(['item.company', 'user'])
                ->whereDate('date_time', today())
                ->orderBy('id', 'desc')
                ->get();

            return view('stock_adjustments.scanner', compact('items', 'recentScans', 'types'));
        }

        $query = StockAdjustment::with(['item.company', 'user']);

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->whereHas('item', function ($q) use ($s) {
                $q->where('part_no', 'like', "%{$s}%")
                  ->orWhere('part_name', 'like', "%{$s}%");
            });
        }

        if ($request->filled('adjustment_type')) {
            $query->where('adjustment_type', $request->adjustment_type);
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

        $adjustments = $query->orderBy('id', 'desc')->get();

        return view('stock_adjustments.index', compact('adjustments', 'items', 'types'));
    }

    public function store(Request $request)
    {
        if (!$request->filled('item_id') && $request->filled('scanned_code')) {
            $code = strtoupper(trim((string) $request->scanned_code));
            $found = Item::where('part_no', $code)->orWhere('part_name', $code)->first();
            if ($found) {
                $request->merge(['item_id' => $found->id]);
            }
        }

        $request->validate([
            'item_id'         => 'required|exists:items,id',
            'adjustment_type' => 'required|in:LOSS,SURPLUS,STOCK OPNAME,FOUND,REPAIR',
            'qty_actual'      => 'nullable|string',
            'qty_diff'        => 'nullable|string',
            'notes'           => 'nullable|string|max:500',
        ]);

        $item  = Item::findOrFail($request->item_id);
        $stock = InventoryStock::firstOrCreate(
            ['item_id' => $item->id],
            ['current_stock' => 0, 'status_stock' => 'EMPTY STOCK']
        );

        $qtySystem = (float) $stock->current_stock;

        $parsedActual = $request->filled('qty_actual') ? self::parseSignedCalculatorValue($request->qty_actual) : null;
        $parsedDiff   = $request->filled('qty_diff') ? self::parseSignedCalculatorValue($request->qty_diff) : null;

        if ($parsedActual !== null) {
            $qtyActual = $parsedActual;
            $qtyDiff   = round($qtyActual - $qtySystem, 2);
        } elseif ($parsedDiff !== null) {
            $qtyDiff   = $parsedDiff;
            $qtyActual = round($qtySystem + $qtyDiff, 2);
        } else {
            return back()->withInput()->with('error', 'Format Qty Actual atau Qty Diff tidak valid.');
        }

        if ($qtyActual < 0) {
            return back()->withInput()->with('error', 'Ditolak: Qty Actual tidak boleh bernilai minus.');
        }

        $type = $request->adjustment_type;

        if ($type === 'LOSS' && $qtyActual >= $qtySystem) {
            return back()->withInput()->with('error', "Ditolak: Untuk kategori LOSS, Qty Actual ({$qtyActual}) wajib lebih kecil dari Qty System ({$qtySystem}).");
        }

        if (in_array($type, ['SURPLUS', 'FOUND', 'REPAIR'], true) && $qtyActual <= $qtySystem) {
            return back()->withInput()->with('error', "Ditolak: Untuk kategori {$type}, Qty Actual ({$qtyActual}) wajib lebih besar dari Qty System ({$qtySystem}).");
        }

        if ($type === 'STOCK OPNAME' && abs($qtyDiff) < 0.0001) {
            return back()->withInput()->with('error', 'Ditolak: Tidak ada perubahan kuantitas (Qty Diff = 0).');
        }

        DB::transaction(function () use ($item, $stock, $qtySystem, $qtyActual, $qtyDiff, $type, $request) {
            StockAdjustment::create([
                'date_time'       => now(),
                'item_id'         => $item->id,
                'user_id'         => Auth::id(),
                'qty_system'      => $qtySystem,
                'qty_actual'      => $qtyActual,
                'qty_diff'        => $qtyDiff,
                'adjustment_type' => $type,
                'notes'           => $request->notes,
            ]);

            $stock->current_stock = $qtyActual;
            $stock->status_stock  = $stock->calculateDynamicStatus();
            $stock->save();
        });

        $sign = $qtyDiff > 0 ? '+' : '';
        $msg  = "Stock Adjustment ({$type}) untuk {$item->part_name} berhasil disimpan (Selisih: {$sign}" . floatval($qtyDiff) . " {$item->uom}).";

        if ($request->input('mode') === 'scanner') {
            return redirect()->route('stock_adjustments.index', ['mode' => 'scanner'])->with('success', $msg);
        }

        return redirect()->route('stock_adjustments.index')->with('success', $msg);
    }
}