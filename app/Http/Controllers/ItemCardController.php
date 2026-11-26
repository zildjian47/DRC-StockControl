<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\LotTracer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItemCardController extends Controller
{
    /**
     * Halaman scanner mobile "Cek Info Stok" (dapat memindai QR URL /item-card/{id}
     * maupun QR stiker transaksi lama berisi Part No / Part Name).
     */
    public function scanner(): View
    {
        return view('item_cards.scanner');
    }

    /**
     * Lookup pintar untuk scanner "Cek Info Stok":
     * 1. Jika hasil scan berupa URL (misal https://domain-lama/item-card/42),
     *    domain lama dibuang dan langsung mengambil ID 42.
     * 2. Jika bukan URL, dicocokkan ke Part No atau Part Name aktif.
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

        $item = null;

        // 1. Cek pola URL permanen /item-card/{id} (kebal ganti domain/tunnel & ganti nama)
        if (preg_match('#/item-card/(\d+)(?:[/?\#]|$)#i', $raw, $matches)) {
            $item = Item::with('company')->find((int) $matches[1]);
        }

        // 2. Fallback kode pendek ITEM:{id}
        if (!$item && preg_match('/^ITEM[:\-](\d+)$/i', $raw, $matches)) {
            $item = Item::with('company')->find((int) $matches[1]);
        }

        // 3. Fallback stiker transaksi lama (Part No atau Part Name, case-insensitive)
        if (!$item) {
            $upper = mb_strtoupper($raw, 'UTF-8');
            $item = Item::with('company')
                ->where(function ($q) use ($upper) {
                    $q->whereRaw('UPPER(part_no) = ?', [$upper])
                      ->orWhereRaw('UPPER(part_name) = ?', [$upper]);
                })
                ->first();
        }

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

        // Hitung Stock 07:00 dari saldo saat ini dikurangi mutasi setelah cut-off 07:00 terbaru
        $now    = Carbon::now();
        $cutoff = $now->copy()->setTime(7, 0, 0);
        if ($now->lt($cutoff)) {
            $cutoff->subDay();
        }

        $netAfterCutoff = $history
            ->filter(fn ($h) => (int) ($h['timestamp'] ?? 0) > $cutoff->timestamp)
            ->sum('qty_change');

        $currentStock = round((float) ($info['current_stock'] ?? 0), 2);
        $stock07      = round($currentStock - $netAfterCutoff, 2);
        $cutoffLabel  = $cutoff->format('d M Y, H:i') . ' WIB';

        // Daftar Lot WIP yang sedang ON PROGRESS terkait item ini
        $activeLots = LotTracer::with(['materialItem', 'targetItem', 'process'])
            ->where('status_lot', 'ON PROGRESS')
            ->where(function ($q) use ($item) {
                $q->where('target_item_id', $item->id)
                  ->orWhere('material_item_id', $item->id);
            })
            ->orderByDesc('id')
            ->get()
            ->map(function ($lt) use ($item) {
                $asTarget = (int) $lt->target_item_id === (int) $item->id;
                $est      = (float) $lt->target_qty_estimasi;
                $actProd  = (float) $lt->actual_qty_prod;
                $pct      = $est > 0 ? min(100, (int) round(($actProd / $est) * 100)) : 0;

                return [
                    'id'                  => $lt->id,
                    'lot_number'          => $lt->lot_number,
                    'role'                => $asTarget ? 'OUTPUT' : 'MATERIAL',
                    'process_name'        => $lt->process->process_name ?? '-',
                    'material_name'       => $lt->materialItem->part_name ?? '-',
                    'material_uom'        => $lt->materialItem->uom ?? '',
                    'target_part_no'      => $lt->targetItem->part_no ?: '-',
                    'target_name'         => $lt->targetItem->part_name ?? '-',
                    'target_uom'          => $lt->targetItem->uom ?? 'PCS',
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