<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\LotTracer;
use App\Services\StockSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItemCardController extends Controller
{
    public function __construct(
        private readonly StockSnapshotService $snapshot
    ) {
    }

    /**
     * Halaman scanner mobile "Cek Info Stok" (dapat memindai QR URL /item-card/{id}
     * maupun QR stiker transaksi lama berisi Part No / Part Name).
     */
    public function scanner(): View
    {
        return view('item_cards.scanner');
    }

    /**
     * Lookup pintar untuk scanner "Cek Info Stok" via helper terpusat anti-SoftDeletes bypass.
     */
    public function lookup(Request $request): JsonResponse
    {
        $raw = trim((string) $request->query('code', ''));

        if ($raw === '') {
            return response()->json([
                'ok'      => false,
                'message' => 'Kode QR atau Part No / Part Name belum diisi.',
            ]);
        }

        $item = Item::findActiveByScanCode($raw, ['company']);

        if (!$item) {
            return response()->json([
                'ok'      => false,
                'message' => "Item untuk kode \"{$raw}\" tidak ditemukan atau sudah dinonaktifkan.",
            ]);
        }

        return response()->json([
            'ok'   => true,
            'url'  => route('item_cards.show', $item->id),
            'item' => [
                'id'        => $item->id,
                'part_no'   => $item->part_no ?: '-',
                'part_name' => $item->part_name,
                'type'      => $item->type,
                'uom'       => $item->uom,
                'company'   => $item->company->company_code ?? 'INTERNAL',
            ],
        ]);
    }

    /**
     * Kartu Info Item Mobile (read-only) untuk semua role aktif.
     * Parameter Qty PO / WD / Kanban hanya dikirim bila user ber-role PPIC.
     */
    public function show(Request $request, Item $item): View
    {
        $item->load([
            'company',
            'inventoryStock',
            'bomsAsOutput.inputItem.inventoryStock',
            'bomsAsOutput.process',
            'bomsAsInput.outputItem.inventoryStock',
            'bomsAsInput.process',
        ]);

        $info    = $item->material_info_payload;
        $history = collect($info['history'] ?? [])
            ->sortByDesc('timestamp')
            ->values();

        // Hitung Stock 07:00 lewat StockSnapshotService agar 100% identik dengan Dashboard & Export
        $currentStock = round((float) ($info['current_stock'] ?? 0), 2);
        $snap07       = $this->snapshot->computeItemStock07($currentStock, $history);
        $stock07      = $snap07['stock_07'];
        $cutoffLabel  = $snap07['cutoff_label'];

        // Daftar Lot WIP yang sedang ON PROGRESS terkait item ini (preloaded anti-N+1)
        $activeLotModels = LotTracer::with(['materialItem', 'targetItem', 'process'])
            ->where('status_lot', 'ON PROGRESS')
            ->where(function ($q) use ($item) {
                $q->where('target_item_id', $item->id)
                  ->orWhere('material_item_id', $item->id);
            })
            ->orderByDesc('id')
            ->get();

        LotTracer::preloadForCollection($activeLotModels);

        $activeLots = $activeLotModels
            ->map(function ($lt) use ($item) {
                $asTarget = (int) $lt->target_item_id === (int) $item->id;
                $est      = (float) $lt->target_qty_estimasi;
                $actProd  = (float) $lt->actual_qty_prod;
                $pct      = $est > 0 ? min(100, (int) round(($actProd / $est) * 100)) : 0;

                return [
                    'id'                  => $lt->id,
                    'lot_number'          => $lt->lot_number,
                    'role'                => $asTarget ? 'OUTPUT' : 'MATERIAL',
                    'process_name'        => $lt->process?->process_name ?? '-',
                    'material_name'       => $lt->materialItem?->part_name ?? '-',
                    'material_uom'        => $lt->materialItem?->uom ?? '',
                    'target_part_no'      => $lt->targetItem?->part_no ?: '-',
                    'target_name'         => $lt->targetItem?->part_name ?? '-',
                    'target_uom'          => $lt->targetItem?->uom ?? 'PCS',
                    'qty_material_out'    => floatval($lt->qty_material_out),
                    'material_saat_ini'   => floatval($lt->material_saat_ini),
                    'qty_material_return' => floatval($lt->qty_material_return),
                    'target_qty_estimasi' => floatval($est),
                    'actual_qty_prod'     => floatval($actProd),
                    'qty_ng'              => floatval($lt->qty_ng),
                    'act_fg'              => floatval($lt->act_fg),
                    'qty_wip'             => floatval($lt->qty_wip),
                    'progress_pct'        => $pct,
                ];
            })
            ->values();

        // Privasi: Parameter PO / WD / Kanban hanya untuk PPIC
        $canViewPo = $request->user()->isPpic();
        $poParams  = $canViewPo ? [
            'qty_po'       => floatval($item->qty_po),
            'working_days' => intval($item->working_days),
            'qty_kanban'   => floatval($item->qty_kanban),
        ] : null;

        return view('item_cards.show', [
            'item'        => $item,
            'info'        => $info,
            'historyList' => $history,
            'stock07'     => $stock07,
            'cutoffLabel' => $cutoffLabel,
            'activeLots'  => $activeLots,
            'canViewPo'   => $canViewPo,
            'poParams'    => $poParams,
        ]);
    }
}