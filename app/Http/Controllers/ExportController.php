<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Inbound;
use App\Models\Item;
use App\Models\LotTracer;
use App\Models\MaterialOut;
use App\Models\MaterialReturn;
use App\Models\ProblemDelay;
use App\Models\Process;
use App\Models\Production;
use App\Models\ProductionNg;
use App\Models\ReturnToSupplier;
use App\Models\StockAdjustment;
use App\Services\ExcelExportService;
use App\Services\PdfExportService;
use App\Services\StockSnapshotService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExcelExportService $excel,
        private readonly PdfExportService $pdf,
        private readonly StockSnapshotService $snapshot
    ) {
    }

    /**
     * 1. Export Dashboard / Inventory Stock (Stock 07:00 & Stock Saat Ini) — .xlsx & .pdf
     */
    public function stock(Request $request)
    {
        $format       = strtolower((string) $request->query('format', 'xlsx'));
        $snapshotMode = (string) $request->query('snapshot_mode', 'now') === '07' ? '07' : 'now';

        $built    = $this->snapshot->buildAll();
        $filtered = $this->snapshot->applyFilters($built['items'], [
            'q'             => $request->query('q', ''),
            'type'          => $request->query('type', ''),
            'company_type'  => $request->query('company_type', ''),
            'company_id'    => $request->query('company_id', ''),
            'status'        => $request->query('status', ''),
            'material_id'   => $request->query('material_id', ''),
            'snapshot_mode' => $snapshotMode,
        ]);

        $filterParts = [];
        $filterParts[] = $snapshotMode === '07'
            ? 'Mode: Stock 07:00 (' . $built['cutoff_label'] . ')'
            : 'Mode: Stock Saat Ini (Real-Time)';

        if ($request->filled('q')) {
            $filterParts[] = 'Pencarian: "' . trim((string) $request->query('q')) . '"';
        }
        if ($request->filled('type')) {
            $filterParts[] = 'Tipe: ' . $request->query('type');
        }
        if ($request->filled('company_type')) {
            $filterParts[] = 'Kategori Perusahaan: ' . $request->query('company_type');
        }
        if ($request->filled('company_id') && is_numeric($request->query('company_id'))) {
            $comp = Company::find((int) $request->query('company_id'));
            if ($comp) {
                $filterParts[] = 'Perusahaan: ' . $comp->company_code . ' — ' . $comp->company_name;
            }
        }
        if ($request->filled('status')) {
            $filterParts[] = 'Status Stok: ' . $request->query('status');
        }
        if ($request->filled('material_id') && is_numeric($request->query('material_id'))) {
            $mat = Item::find((int) $request->query('material_id'));
            if ($mat) {
                $filterParts[] = 'Material Pembentuk: ' . $mat->part_name;
            }
        }

        $filterSummary = $this->buildFilterSummary($filterParts);
        $stamp         = Carbon::now()->format('Ymd_His');

        if ($format === 'pdf') {
            $summary = [
                'total'     => $filtered->count(),
                'empty'     => $filtered->filter(fn ($r) => ($snapshotMode === '07' ? $r['status_07'] : $r['status_stock']) === 'EMPTY STOCK')->count(),
                'very_low'  => $filtered->filter(fn ($r) => ($snapshotMode === '07' ? $r['status_07'] : $r['status_stock']) === 'VERY LOW')->count(),
                'low'       => $filtered->filter(fn ($r) => ($snapshotMode === '07' ? $r['status_07'] : $r['status_stock']) === 'LOW STOCK')->count(),
                'normal'    => $filtered->filter(fn ($r) => ($snapshotMode === '07' ? $r['status_07'] : $r['status_stock']) === 'NORMAL STOCK')->count(),
                'overstock' => $filtered->filter(fn ($r) => ($snapshotMode === '07' ? $r['status_07'] : $r['status_stock']) === 'OVERSTOCK')->count(),
            ];

            return $this->pdf->renderView(
                'exports.pdf.stock-snapshot',
                [
                    'title'         => $snapshotMode === '07' ? 'Laporan Posisi Stock 07:00' : 'Laporan Posisi Stock Gudang',
                    'filterSummary' => $filterSummary,
                    'cutoffLabel'   => $built['cutoff_label'],
                    'snapshotMode'  => $snapshotMode,
                    'items'         => $filtered,
                    'summary'       => $summary,
                    'exportedAt'    => Carbon::now()->format('d M Y, H:i') . ' WIB',
                    'exportedBy'    => $request->user()?->name ?? 'PPIC',
                ],
                "DRC_Stock_{$snapshotMode}_{$stamp}.pdf",
                'a4',
                'landscape'
            );
        }

        $columns = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Part No', 'type' => 'text', 'width' => 18],
            ['label' => 'Part Name', 'type' => 'text', 'width' => 30],
            ['label' => 'Tipe', 'type' => 'center', 'width' => 13],
            ['label' => 'UOM', 'type' => 'center', 'width' => 9],
            ['label' => 'Perusahaan', 'type' => 'text', 'width' => 16],
            ['label' => 'Stock 07:00', 'type' => 'number', 'width' => 14],
            ['label' => 'Status 07:00', 'type' => 'center', 'width' => 15],
            ['label' => 'Current Stock', 'type' => 'number', 'width' => 15],
            ['label' => 'WIP Aktif', 'type' => 'number', 'width' => 13],
            ['label' => 'Potensi Max', 'type' => 'number', 'width' => 13],
            ['label' => 'Std Day 1', 'type' => 'number', 'width' => 12],
            ['label' => 'Std Day 2', 'type' => 'number', 'width' => 12],
            ['label' => 'Std Day 3', 'type' => 'number', 'width' => 12],
            ['label' => 'Ratio (Hr)', 'type' => 'number', 'width' => 12],
            ['label' => 'Status Saat Ini', 'type' => 'center', 'width' => 16],
        ];

        $rows = [];
        foreach ($filtered as $idx => $r) {
            $rows[] = [
                $idx + 1,
                $r['part_no'],
                $r['part_name'],
                $r['type'],
                $r['uom'],
                $r['company_code'],
                $r['stock_07'],
                $r['status_07'],
                $r['current_stock'],
                $r['wip_active'],
                $r['has_bom_inputs'] ? $r['pot_max'] : '-',
                $r['std_day_1'],
                $r['std_day_2'],
                $r['std_day_3'],
                $snapshotMode === '07' ? $r['ratio_07'] : $r['ratio_stock'],
                $r['status_stock'],
            ];
        }

        $totals = [
            '',
            'TOTAL',
            $filtered->count() . ' Item',
            '',
            '',
            '',
            round((float) $filtered->sum('stock_07'), 2),
            '',
            round((float) $filtered->sum('current_stock'), 2),
            round((float) $filtered->sum('wip_active'), 2),
            '',
            '',
            '',
            '',
            '',
            '',
        ];

        return $this->excel->downloadSingleSheet(
            "DRC_Stock_{$snapshotMode}_{$stamp}.xlsx",
            'Posisi Stock',
            'Laporan Posisi Stock & Snapshot 07:00',
            $filterSummary,
            $columns,
            $rows,
            $totals
        );
    }

    /**
     * 2. Export Lot Tracer (WIP & Top/End Coil) — .xlsx
     */
    public function lotTracers(Request $request)
    {
        $query = LotTracer::with(['process', 'materialItem', 'targetItem'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status_lot', $request->query('status'));
        }
        if ($request->filled('process_id') && is_numeric($request->query('process_id'))) {
            $query->where('process_id', (int) $request->query('process_id'));
        }

        $records = $query->get();

        $q = mb_strtolower(trim((string) $request->query('q', '')), 'UTF-8');
        if ($q !== '') {
            $records = $records->filter(function ($lt) use ($q) {
                $haystack = mb_strtolower(implode(' ', [
                    $lt->lot_number,
                    $lt->bom_group,
                    $lt->process->process_name ?? '',
                    $lt->materialItem->part_no ?? '',
                    $lt->materialItem->part_name ?? '',
                    $lt->targetItem->part_no ?? '',
                    $lt->targetItem->part_name ?? '',
                ]), 'UTF-8');
                return str_contains($haystack, $q);
            })->values();
        }

        if ($request->boolean('coil_only')) {
            $records = $records->filter(fn ($lt) => $lt->is_coil)->values();
        }

        $filterParts = [];
        if ($q !== '') {
            $filterParts[] = 'Pencarian: "' . trim((string) $request->query('q')) . '"';
        }
        if ($request->filled('status')) {
            $filterParts[] = 'Status Lot: ' . $request->query('status');
        }
        if ($request->filled('process_id') && is_numeric($request->query('process_id'))) {
            $proc = Process::find((int) $request->query('process_id'));
            if ($proc) {
                $filterParts[] = 'Proses: ' . $proc->process_name;
            }
        }
        if ($request->boolean('coil_only')) {
            $filterParts[] = 'Khusus Material Coil';
        }

        $columns = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Nomor Lot', 'type' => 'center', 'width' => 14],
            ['label' => 'Status Lot', 'type' => 'center', 'width' => 14],
            ['label' => 'Proses', 'type' => 'text', 'width' => 16],
            ['label' => 'Material (Trigger)', 'type' => 'text', 'width' => 26],
            ['label' => 'UOM Mat', 'type' => 'center', 'width' => 9],
            ['label' => 'Qty Out', 'type' => 'number', 'width' => 11],
            ['label' => 'Qty Return', 'type' => 'number', 'width' => 12],
            ['label' => 'Mat Saat Ini', 'type' => 'number', 'width' => 13],
            ['label' => 'Top/End Coil', 'type' => 'number', 'width' => 14],
            ['label' => 'Target Part No', 'type' => 'text', 'width' => 16],
            ['label' => 'Target Part Name', 'type' => 'text', 'width' => 26],
            ['label' => 'Est Output', 'type' => 'number', 'width' => 12],
            ['label' => 'Act Prod', 'type' => 'number', 'width' => 11],
            ['label' => 'Qty NG', 'type' => 'number', 'width' => 10],
            ['label' => 'Act FG', 'type' => 'number', 'width' => 11],
            ['label' => 'Sisa WIP', 'type' => 'number', 'width' => 11],
            ['label' => 'Komponen Pendamping', 'type' => 'text', 'width' => 34],
        ];

        $rows = [];
        $sumTopEnd = 0.0;
        $seenCoilLots = [];

        foreach ($records as $idx => $lt) {
            $topEnd = $lt->top_end_coil;
            if ($topEnd !== null && !isset($seenCoilLots[$lt->lot_number])) {
                $seenCoilLots[$lt->lot_number] = true;
                $sumTopEnd += (float) $topEnd;
            }

            $compStr = collect($lt->companion_items)
                ->map(fn ($c) => $c['part_name'] . ' (Out:' . $c['qty_out'] . ', Ret:' . $c['qty_return'] . ', Sisa:' . $c['qty_saat_ini'] . ' ' . $c['uom'] . ')')
                ->implode('; ');

            $rows[] = [
                $idx + 1,
                $lt->lot_number,
                $lt->status_lot,
                $lt->process->process_name ?? '-',
                $lt->materialItem->part_name ?? '-',
                $lt->materialItem->uom ?? '-',
                (float) $lt->qty_material_out,
                (float) $lt->qty_material_return,
                (float) $lt->material_saat_ini,
                $topEnd !== null ? (float) $topEnd : '-',
                $lt->targetItem?->part_no ?: '-',
                $lt->targetItem?->part_name ?? '-',
                (float) $lt->target_qty_estimasi,
                (float) $lt->actual_qty_prod,
                (float) $lt->qty_ng,
                (float) $lt->act_fg,
                (float) $lt->qty_wip,
                $compStr !== '' ? $compStr : '-',
            ];
        }

        $totals = [
            '', 'TOTAL', $records->count() . ' Baris', '', '', '', '', '', '',
            round($sumTopEnd, 2),
            '', '',
            round((float) $records->sum('target_qty_estimasi'), 2),
            round((float) $records->sum('actual_qty_prod'), 2),
            round((float) $records->sum('qty_ng'), 2),
            round((float) $records->sum(fn ($lt) => $lt->act_fg), 2),
            round((float) $records->sum('qty_wip'), 2),
            '',
        ];

        return $this->excel->downloadSingleSheet(
            'DRC_LotTracer_' . Carbon::now()->format('Ymd_His') . '.xlsx',
            'Lot Tracer',
            'Laporan Lot Tracer, WIP, & Top/End Coil',
            $this->buildFilterSummary($filterParts),
            $columns,
            $rows,
            $totals
        );
    }

    /**
     * 3. Export Inbound — .xlsx
     */
    public function inbounds(Request $request)
    {
        $query = Inbound::with(['item.company', 'user', 'correctedBy'])->orderByDesc('date_time');
        $this->applySqlDateRange($query, 'date_time', $request);

        if ($request->filled('qty_check')) {
            $query->where('qty_check', $request->query('qty_check'));
        }

        $records = $this->applyTimeRangeFilter($query->get(), 'date_time', $request);
        $records = $this->applySimpleItemTxFilters($records, $request);

        $columns = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Tanggal', 'type' => 'center', 'width' => 13],
            ['label' => 'Jam', 'type' => 'center', 'width' => 9],
            ['label' => 'Part No', 'type' => 'text', 'width' => 18],
            ['label' => 'Part Name', 'type' => 'text', 'width' => 28],
            ['label' => 'Tipe', 'type' => 'center', 'width' => 12],
            ['label' => 'Supplier', 'type' => 'text', 'width' => 18],
            ['label' => 'Qty', 'type' => 'number', 'width' => 12],
            ['label' => 'UOM', 'type' => 'center', 'width' => 9],
            ['label' => 'Petugas', 'type' => 'text', 'width' => 18],
            ['label' => 'Status Check', 'type' => 'center', 'width' => 14],
            ['label' => 'Qty Awal', 'type' => 'number', 'width' => 12],
            ['label' => 'Dikoreksi Oleh', 'type' => 'text', 'width' => 18],
            ['label' => 'Waktu Koreksi', 'type' => 'center', 'width' => 18],
            ['label' => 'Alasan Koreksi', 'type' => 'text', 'width' => 26],
        ];

        $rows = [];
        foreach ($records as $idx => $r) {
            $dt = Carbon::parse($r->date_time);
            $rows[] = [
                $idx + 1,
                $dt->format('Y-m-d'),
                $dt->format('H:i'),
                $r->item?->part_no ?: '-',
                $r->item?->part_name ?? '-',
                $r->item?->type ?? '-',
                $r->item?->company?->company_code ?? '-',
                (float) $r->qty,
                $r->item?->uom ?? '-',
                $r->user?->name ?? '-',
                $r->qty_check ?? '-',
                $r->qty_original !== null ? (float) $r->qty_original : '-',
                $r->correctedBy?->name ?? '-',
                $r->corrected_at ? Carbon::parse($r->corrected_at)->format('Y-m-d H:i') : '-',
                $r->correction_notes ?: '-',
            ];
        }

        $totals = [
            '', 'TOTAL', $records->count() . ' Data', '', '', '', '',
            round((float) $records->sum('qty'), 2),
            '', '', '', '', '', '', '',
        ];

        return $this->excel->downloadSingleSheet(
            'DRC_Inbound_' . Carbon::now()->format('Ymd_His') . '.xlsx',
            'Inbound',
            'Laporan Transaksi Inbound (Penerimaan Barang)',
            $this->buildTxFilterSummary($request),
            $columns,
            $rows,
            $totals
        );
    }

    /**
     * 4. Export Material Out — .xlsx
     */
    public function materialOuts(Request $request)
    {
        $query = MaterialOut::with(['materialItem', 'targetItem', 'user', 'correctedBy'])->orderByDesc('date_time');
        $this->applySqlDateRange($query, 'date_time', $request);

        if ($request->filled('qty_check')) {
            $query->where('qty_check', $request->query('qty_check'));
        }

        $records = $this->applyTimeRangeFilter($query->get(), 'date_time', $request);
        $records = $this->applyMaterialLotTxFilters($records, $request);

        $columns = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Tanggal', 'type' => 'center', 'width' => 13],
            ['label' => 'Jam', 'type' => 'center', 'width' => 9],
            ['label' => 'Nomor Lot', 'type' => 'center', 'width' => 13],
            ['label' => 'BOM Group', 'type' => 'center', 'width' => 15],
            ['label' => 'Material Part No', 'type' => 'text', 'width' => 16],
            ['label' => 'Material Name', 'type' => 'text', 'width' => 26],
            ['label' => 'Qty Out', 'type' => 'number', 'width' => 12],
            ['label' => 'UOM', 'type' => 'center', 'width' => 9],
            ['label' => 'Target Part No', 'type' => 'text', 'width' => 16],
            ['label' => 'Target Name', 'type' => 'text', 'width' => 26],
            ['label' => 'Petugas', 'type' => 'text', 'width' => 18],
            ['label' => 'Status Check', 'type' => 'center', 'width' => 14],
            ['label' => 'Qty Awal', 'type' => 'number', 'width' => 12],
            ['label' => 'Dikoreksi Oleh', 'type' => 'text', 'width' => 18],
            ['label' => 'Alasan Koreksi', 'type' => 'text', 'width' => 26],
        ];

        $rows = [];
        foreach ($records as $idx => $r) {
            $dt = Carbon::parse($r->date_time);
            $rows[] = [
                $idx + 1,
                $dt->format('Y-m-d'),
                $dt->format('H:i'),
                $r->lot_number,
                $r->bom_group ?: '-',
                $r->materialItem?->part_no ?: '-',
                $r->materialItem?->part_name ?? '-',
                (float) $r->qty,
                $r->materialItem?->uom ?? '-',
                $r->targetItem?->part_no ?: '-',
                $r->targetItem?->part_name ?? '-',
                $r->user?->name ?? '-',
                $r->qty_check ?? '-',
                $r->qty_original !== null ? (float) $r->qty_original : '-',
                $r->correctedBy?->name ?? '-',
                $r->correction_notes ?: '-',
            ];
        }

        // Hitung total fisik per Lot unik (agar Multi-Output tidak dijumlah ganda)
        $uniqueLotQty = $records->groupBy('lot_number')->sum(fn ($grp) => (float) $grp->first()->qty);

        $totals = [
            '', 'TOTAL', $records->count() . ' Baris', '', '', '', 'Total Fisik Lot Unik:',
            round((float) $uniqueLotQty, 2),
            '', '', '', '', '', '', '', '',
        ];

        return $this->excel->downloadSingleSheet(
            'DRC_MaterialOut_' . Carbon::now()->format('Ymd_His') . '.xlsx',
            'Material Out',
            'Laporan Transaksi Material Out',
            $this->buildTxFilterSummary($request),
            $columns,
            $rows,
            $totals
        );
    }

    /**
     * 5. Export Material Return — .xlsx
     */
    public function materialReturns(Request $request)
    {
        $query = MaterialReturn::with(['materialItem', 'targetItem', 'user', 'correctedBy'])->orderByDesc('date_time');
        $this->applySqlDateRange($query, 'date_time', $request);

        if ($request->filled('qty_check')) {
            $query->where('qty_check', $request->query('qty_check'));
        }

        $records = $this->applyTimeRangeFilter($query->get(), 'date_time', $request);
        $records = $this->applyMaterialLotTxFilters($records, $request);

        $columns = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Tanggal', 'type' => 'center', 'width' => 13],
            ['label' => 'Jam', 'type' => 'center', 'width' => 9],
            ['label' => 'Batch Retur', 'type' => 'center', 'width' => 16],
            ['label' => 'Nomor Lot', 'type' => 'center', 'width' => 13],
            ['label' => 'Material Part No', 'type' => 'text', 'width' => 16],
            ['label' => 'Material Name', 'type' => 'text', 'width' => 26],
            ['label' => 'Qty Return', 'type' => 'number', 'width' => 12],
            ['label' => 'UOM', 'type' => 'center', 'width' => 9],
            ['label' => 'Target Part No', 'type' => 'text', 'width' => 16],
            ['label' => 'Target Name', 'type' => 'text', 'width' => 26],
            ['label' => 'Petugas', 'type' => 'text', 'width' => 18],
            ['label' => 'Status Check', 'type' => 'center', 'width' => 14],
            ['label' => 'Qty Awal', 'type' => 'number', 'width' => 12],
            ['label' => 'Dikoreksi Oleh', 'type' => 'text', 'width' => 18],
            ['label' => 'Alasan Koreksi', 'type' => 'text', 'width' => 26],
        ];

        $rows = [];
        foreach ($records as $idx => $r) {
            $dt = Carbon::parse($r->date_time);
            $rows[] = [
                $idx + 1,
                $dt->format('Y-m-d'),
                $dt->format('H:i'),
                $r->return_batch ?: '-',
                $r->lot_number,
                $r->materialItem?->part_no ?: '-',
                $r->materialItem?->part_name ?? '-',
                (float) $r->qty,
                $r->materialItem?->uom ?? '-',
                $r->targetItem?->part_no ?: '-',
                $r->targetItem?->part_name ?? '-',
                $r->user?->name ?? '-',
                $r->qty_check ?? '-',
                $r->qty_original !== null ? (float) $r->qty_original : '-',
                $r->correctedBy?->name ?? '-',
                $r->correction_notes ?: '-',
            ];
        }

        $uniqueBatchQty = $records
            ->groupBy(fn ($r) => !empty($r->return_batch) ? $r->return_batch : ('LEGACY-' . $r->lot_number . '-' . $r->id))
            ->sum(fn ($grp) => (float) $grp->first()->qty);

        $totals = [
            '', 'TOTAL', $records->count() . ' Baris', '', '', '', 'Total Fisik Batch Unik:',
            round((float) $uniqueBatchQty, 2),
            '', '', '', '', '', '', '', '',
        ];

        return $this->excel->downloadSingleSheet(
            'DRC_MaterialReturn_' . Carbon::now()->format('Ymd_His') . '.xlsx',
            'Material Return',
            'Laporan Transaksi Material Return',
            $this->buildTxFilterSummary($request),
            $columns,
            $rows,
            $totals
        );
    }

    /**
     * 6. Export Production — .xlsx
     */
    public function productions(Request $request)
    {
        $query = Production::with(['item.company', 'user', 'correctedBy'])->orderByDesc('date_time');
        $this->applySqlDateRange($query, 'date_time', $request);

        if ($request->filled('qty_check')) {
            $query->where('qty_check', $request->query('qty_check'));
        }

        $records = $this->applyTimeRangeFilter($query->get(), 'date_time', $request);
        $records = $this->applySimpleItemTxFilters($records, $request, true);

        $columns = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Tanggal', 'type' => 'center', 'width' => 13],
            ['label' => 'Jam', 'type' => 'center', 'width' => 9],
            ['label' => 'Nomor Lot', 'type' => 'center', 'width' => 13],
            ['label' => 'Part No', 'type' => 'text', 'width' => 18],
            ['label' => 'Part Name', 'type' => 'text', 'width' => 28],
            ['label' => 'Tipe', 'type' => 'center', 'width' => 12],
            ['label' => 'Qty Produksi', 'type' => 'number', 'width' => 13],
            ['label' => 'UOM', 'type' => 'center', 'width' => 9],
            ['label' => 'Petugas', 'type' => 'text', 'width' => 18],
            ['label' => 'Status Check', 'type' => 'center', 'width' => 14],
            ['label' => 'Qty Awal', 'type' => 'number', 'width' => 12],
            ['label' => 'Dikoreksi Oleh', 'type' => 'text', 'width' => 18],
            ['label' => 'Waktu Koreksi', 'type' => 'center', 'width' => 18],
            ['label' => 'Alasan Koreksi', 'type' => 'text', 'width' => 26],
        ];

        $rows = [];
        foreach ($records as $idx => $r) {
            $dt = Carbon::parse($r->date_time);
            $rows[] = [
                $idx + 1,
                $dt->format('Y-m-d'),
                $dt->format('H:i'),
                $r->lot_number,
                $r->item?->part_no ?: '-',
                $r->item?->part_name ?? '-',
                $r->item?->type ?? '-',
                (float) $r->qty,
                $r->item?->uom ?? '-',
                $r->user?->name ?? '-',
                $r->qty_check ?? '-',
                $r->qty_original !== null ? (float) $r->qty_original : '-',
                $r->correctedBy?->name ?? '-',
                $r->corrected_at ? Carbon::parse($r->corrected_at)->format('Y-m-d H:i') : '-',
                $r->correction_notes ?: '-',
            ];
        }

        $totals = [
            '', 'TOTAL', $records->count() . ' Data', '', '', '', '',
            round((float) $records->sum('qty'), 2),
            '', '', '', '', '', '', '',
        ];

        return $this->excel->downloadSingleSheet(
            'DRC_Production_' . Carbon::now()->format('Ymd_His') . '.xlsx',
            'Production',
            'Laporan Hasil Produksi',
            $this->buildTxFilterSummary($request),
            $columns,
            $rows,
            $totals
        );
    }

    /**
     * 7. Export Delivery — .xlsx
     */
    public function deliveries(Request $request)
    {
        $query = Delivery::with(['item.company', 'user', 'correctedBy'])->orderByDesc('date_time');
        $this->applySqlDateRange($query, 'date_time', $request);

        if ($request->filled('qty_check')) {
            $query->where('qty_check', $request->query('qty_check'));
        }

        $records = $this->applyTimeRangeFilter($query->get(), 'date_time', $request);
        $records = $this->applySimpleItemTxFilters($records, $request);

        $columns = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Tanggal', 'type' => 'center', 'width' => 13],
            ['label' => 'Jam', 'type' => 'center', 'width' => 9],
            ['label' => 'Part No', 'type' => 'text', 'width' => 18],
            ['label' => 'Part Name', 'type' => 'text', 'width' => 28],
            ['label' => 'Tipe', 'type' => 'center', 'width' => 12],
            ['label' => 'Customer / Asal', 'type' => 'text', 'width' => 18],
            ['label' => 'Qty Delivery', 'type' => 'number', 'width' => 13],
            ['label' => 'UOM', 'type' => 'center', 'width' => 9],
            ['label' => 'Petugas', 'type' => 'text', 'width' => 18],
            ['label' => 'Status Check', 'type' => 'center', 'width' => 14],
            ['label' => 'Qty Awal', 'type' => 'number', 'width' => 12],
            ['label' => 'Dikoreksi Oleh', 'type' => 'text', 'width' => 18],
            ['label' => 'Waktu Koreksi', 'type' => 'center', 'width' => 18],
            ['label' => 'Alasan Koreksi', 'type' => 'text', 'width' => 26],
        ];

        $rows = [];
        foreach ($records as $idx => $r) {
            $dt = Carbon::parse($r->date_time);
            $rows[] = [
                $idx + 1,
                $dt->format('Y-m-d'),
                $dt->format('H:i'),
                $r->item?->part_no ?: '-',
                $r->item?->part_name ?? '-',
                $r->item?->type ?? '-',
                $r->item?->company?->company_code ?? '-',
                (float) $r->qty,
                $r->item?->uom ?? '-',
                $r->user?->name ?? '-',
                $r->qty_check ?? '-',
                $r->qty_original !== null ? (float) $r->qty_original : '-',
                $r->correctedBy?->name ?? '-',
                $r->corrected_at ? Carbon::parse($r->corrected_at)->format('Y-m-d H:i') : '-',
                $r->correction_notes ?: '-',
            ];
        }

        $totals = [
            '', 'TOTAL', $records->count() . ' Data', '', '', '', '',
            round((float) $records->sum('qty'), 2),
            '', '', '', '', '', '', '',
        ];

        return $this->excel->downloadSingleSheet(
            'DRC_Delivery_' . Carbon::now()->format('Ymd_His') . '.xlsx',
            'Delivery',
            'Laporan Transaksi Delivery (Pengiriman Barang)',
            $this->buildTxFilterSummary($request),
            $columns,
            $rows,
            $totals
        );
    }

    /**
     * 8. Export Return to Supplier — .xlsx
     */
    public function returnToSuppliers(Request $request)
    {
        $query = ReturnToSupplier::with(['item.company', 'user', 'correctedBy'])->orderByDesc('date_time');
        $this->applySqlDateRange($query, 'date_time', $request);

        if ($request->filled('qty_check')) {
            $query->where('qty_check', $request->query('qty_check'));
        }

        $records = $this->applyTimeRangeFilter($query->get(), 'date_time', $request);
        $records = $this->applySimpleItemTxFilters($records, $request);

        $columns = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Tanggal', 'type' => 'center', 'width' => 13],
            ['label' => 'Jam', 'type' => 'center', 'width' => 9],
            ['label' => 'Part No', 'type' => 'text', 'width' => 18],
            ['label' => 'Part Name', 'type' => 'text', 'width' => 28],
            ['label' => 'Tipe', 'type' => 'center', 'width' => 12],
            ['label' => 'Supplier', 'type' => 'text', 'width' => 18],
            ['label' => 'Qty Retur', 'type' => 'number', 'width' => 12],
            ['label' => 'UOM', 'type' => 'center', 'width' => 9],
            ['label' => 'Petugas', 'type' => 'text', 'width' => 18],
            ['label' => 'Status Check', 'type' => 'center', 'width' => 14],
            ['label' => 'Qty Awal', 'type' => 'number', 'width' => 12],
            ['label' => 'Dikoreksi Oleh', 'type' => 'text', 'width' => 18],
            ['label' => 'Waktu Koreksi', 'type' => 'center', 'width' => 18],
            ['label' => 'Alasan Koreksi', 'type' => 'text', 'width' => 26],
        ];

        $rows = [];
        foreach ($records as $idx => $r) {
            $dt = Carbon::parse($r->date_time);
            $rows[] = [
                $idx + 1,
                $dt->format('Y-m-d'),
                $dt->format('H:i'),
                $r->item?->part_no ?: '-',
                $r->item?->part_name ?? '-',
                $r->item?->type ?? '-',
                $r->item?->company?->company_code ?? '-',
                (float) $r->qty,
                $r->item?->uom ?? '-',
                $r->user?->name ?? '-',
                $r->qty_check ?? '-',
                $r->qty_original !== null ? (float) $r->qty_original : '-',
                $r->correctedBy?->name ?? '-',
                $r->corrected_at ? Carbon::parse($r->corrected_at)->format('Y-m-d H:i') : '-',
                $r->correction_notes ?: '-',
            ];
        }

        $totals = [
            '', 'TOTAL', $records->count() . ' Data', '', '', '', '',
            round((float) $records->sum('qty'), 2),
            '', '', '', '', '', '', '',
        ];

        return $this->excel->downloadSingleSheet(
            'DRC_ReturnToSupplier_' . Carbon::now()->format('Ymd_His') . '.xlsx',
            'Return to Supplier',
            'Laporan Return to Supplier',
            $this->buildTxFilterSummary($request),
            $columns,
            $rows,
            $totals
        );
    }

    /**
     * 9. Export Stock Adjustment — .xlsx & .pdf (Berita Acara Penyesuaian Stok)
     */
    public function stockAdjustments(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));

        $query = StockAdjustment::with(['item.company', 'user'])->orderByDesc('date_time');
        $this->applySqlDateRange($query, 'date_time', $request);

        if ($request->filled('adjustment_type')) {
            $query->where('adjustment_type', $request->query('adjustment_type'));
        }

        $records = $this->applyTimeRangeFilter($query->get(), 'date_time', $request);
        $records = $this->applySimpleItemTxFilters($records, $request, false, true);

        $filterParts = [];
        if ($request->filled('adjustment_type')) {
            $filterParts[] = 'Kategori: ' . $request->query('adjustment_type');
        }
        $filterSummary = $this->buildTxFilterSummary($request, $filterParts);
        $stamp         = Carbon::now()->format('Ymd_His');

        if ($format === 'pdf') {
            $byType = $records->groupBy('adjustment_type')->map(fn ($grp, $key) => [
                'type'     => $key,
                'count'    => $grp->count(),
                'net_diff' => round((float) $grp->sum('qty_diff'), 2),
            ])->values();

            return $this->pdf->renderView(
                'exports.pdf.stock-adjustments',
                [
                    'title'         => 'Berita Acara Penyesuaian Stock (Stock Adjustment)',
                    'filterSummary' => $filterSummary,
                    'records'       => $records,
                    'byType'        => $byType,
                    'netDiffTotal'  => round((float) $records->sum('qty_diff'), 2),
                    'exportedAt'    => Carbon::now()->format('d M Y, H:i') . ' WIB',
                    'exportedBy'    => $request->user()?->name ?? 'PPIC',
                ],
                "DRC_StockAdjustment_{$stamp}.pdf",
                'a4',
                'landscape'
            );
        }

        $columns = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Tanggal', 'type' => 'center', 'width' => 13],
            ['label' => 'Jam', 'type' => 'center', 'width' => 9],
            ['label' => 'Kategori', 'type' => 'center', 'width' => 16],
            ['label' => 'Part No', 'type' => 'text', 'width' => 18],
            ['label' => 'Part Name', 'type' => 'text', 'width' => 28],
            ['label' => 'Tipe', 'type' => 'center', 'width' => 12],
            ['label' => 'Stock Sistem', 'type' => 'number', 'width' => 13],
            ['label' => 'Stock Aktual', 'type' => 'number', 'width' => 13],
            ['label' => 'Selisih (+/-)', 'type' => 'number', 'width' => 13],
            ['label' => 'UOM', 'type' => 'center', 'width' => 9],
            ['label' => 'Catatan / Keterangan', 'type' => 'text', 'width' => 30],
            ['label' => 'Petugas PPIC', 'type' => 'text', 'width' => 18],
        ];

        $rows = [];
        foreach ($records as $idx => $r) {
            $dt = Carbon::parse($r->date_time);
            $rows[] = [
                $idx + 1,
                $dt->format('Y-m-d'),
                $dt->format('H:i'),
                $r->adjustment_type,
                $r->item?->part_no ?: '-',
                $r->item?->part_name ?? '-',
                $r->item?->type ?? '-',
                (float) $r->qty_system,
                (float) $r->qty_actual,
                (float) $r->qty_diff,
                $r->item?->uom ?? '-',
                $r->notes ?: '-',
                $r->user?->name ?? '-',
            ];
        }

        $totals = [
            '', 'TOTAL', $records->count() . ' Data', '', '', '', '', '', 'Netto Selisih:',
            round((float) $records->sum('qty_diff'), 2),
            '', '', '',
        ];

        return $this->excel->downloadSingleSheet(
            "DRC_StockAdjustment_{$stamp}.xlsx",
            'Stock Adjustment',
            'Laporan Stock Adjustment (Penyesuaian Stok)',
            $filterSummary,
            $columns,
            $rows,
            $totals
        );
    }

    /**
     * 10. Export Form NG (Production NG) — .xlsx (2 sheet: Detail + Rekap Penyebab) & .pdf
     */
    public function productionNgs(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));

        $query = ProductionNg::with(['item.company', 'user', 'correctedBy'])->orderByDesc('date_time');
        $this->applySqlDateRange($query, 'date_time', $request);

        if ($request->filled('problem_reason')) {
            $query->where('problem_reason', $request->query('problem_reason'));
        }
        if ($request->filled('qty_check')) {
            $query->where('qty_check', $request->query('qty_check'));
        }

        $records = $this->applyTimeRangeFilter($query->get(), 'date_time', $request);
        $records = $this->applySimpleItemTxFilters($records, $request, true);

        $filterParts = [];
        if ($request->filled('problem_reason')) {
            $filterParts[] = 'Penyebab NG: ' . $request->query('problem_reason');
        }
        $filterSummary = $this->buildTxFilterSummary($request, $filterParts);
        $stamp         = Carbon::now()->format('Ymd_His');

        $totalNgQty = round((float) $records->sum('qty_ng'), 2);
        $rekapReason = $records->groupBy('problem_reason')
            ->map(function ($grp, $reason) use ($totalNgQty) {
                $qty = round((float) $grp->sum('qty_ng'), 2);
                $pct = $totalNgQty > 0 ? round(($qty / $totalNgQty) * 100, 1) : 0.0;
                return [
                    'reason' => $reason ?: '-',
                    'cases'  => $grp->count(),
                    'qty_ng' => $qty,
                    'pct'    => $pct,
                ];
            })
            ->sortByDesc('qty_ng')
            ->values();

        if ($format === 'pdf') {
            return $this->pdf->renderView(
                'exports.pdf.production-ngs',
                [
                    'title'         => 'Laporan Form NG / Temuan Reject QC',
                    'filterSummary' => $filterSummary,
                    'records'       => $records,
                    'rekapReason'   => $rekapReason,
                    'totalNgQty'    => $totalNgQty,
                    'exportedAt'    => Carbon::now()->format('d M Y, H:i') . ' WIB',
                    'exportedBy'    => $request->user()?->name ?? 'PPIC',
                ],
                "DRC_FormNG_{$stamp}.pdf",
                'a4',
                'landscape'
            );
        }

        $detailCols = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Tanggal', 'type' => 'center', 'width' => 13],
            ['label' => 'Jam', 'type' => 'center', 'width' => 9],
            ['label' => 'Nomor Lot', 'type' => 'center', 'width' => 13],
            ['label' => 'Part No', 'type' => 'text', 'width' => 18],
            ['label' => 'Part Name', 'type' => 'text', 'width' => 28],
            ['label' => 'Qty NG', 'type' => 'number', 'width' => 11],
            ['label' => 'UOM', 'type' => 'center', 'width' => 9],
            ['label' => 'Penyebab NG', 'type' => 'center', 'width' => 18],
            ['label' => 'Petugas', 'type' => 'text', 'width' => 18],
            ['label' => 'Status Check', 'type' => 'center', 'width' => 14],
            ['label' => 'Qty Awal', 'type' => 'number', 'width' => 11],
            ['label' => 'Dikoreksi Oleh', 'type' => 'text', 'width' => 18],
            ['label' => 'Alasan Koreksi', 'type' => 'text', 'width' => 26],
        ];

        $detailRows = [];
        foreach ($records as $idx => $r) {
            $dt = Carbon::parse($r->date_time);
            $detailRows[] = [
                $idx + 1,
                $dt->format('Y-m-d'),
                $dt->format('H:i'),
                $r->lot_number,
                $r->item?->part_no ?: '-',
                $r->item?->part_name ?? '-',
                (float) $r->qty_ng,
                $r->item?->uom ?? '-',
                $r->problem_reason ?: '-',
                $r->user?->name ?? '-',
                $r->qty_check ?? '-',
                $r->qty_original !== null ? (float) $r->qty_original : '-',
                $r->correctedBy?->name ?? '-',
                $r->correction_notes ?: '-',
            ];
        }

        $rekapCols = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Penyebab NG', 'type' => 'text', 'width' => 24],
            ['label' => 'Jumlah Kejadian', 'type' => 'int', 'width' => 18],
            ['label' => 'Total Qty NG', 'type' => 'number', 'width' => 16],
            ['label' => 'Persentase (%)', 'type' => 'number', 'width' => 16],
        ];

        $rekapRows = [];
        foreach ($rekapReason as $idx => $rk) {
            $rekapRows[] = [
                $idx + 1,
                $rk['reason'],
                $rk['cases'],
                $rk['qty_ng'],
                $rk['pct'],
            ];
        }

        return $this->excel->downloadMultiSheet("DRC_FormNG_{$stamp}.xlsx", [
            [
                'sheet_title'    => 'Detail NG',
                'report_title'   => 'Laporan Detail Form NG (Production NG)',
                'filter_summary' => $filterSummary,
                'columns'        => $detailCols,
                'rows'           => $detailRows,
                'totals_row'     => ['', 'TOTAL', $records->count() . ' Kasus', '', '', '', $totalNgQty, '', '', '', '', '', '', ''],
            ],
            [
                'sheet_title'    => 'Rekap Penyebab',
                'report_title'   => 'Rekapitulasi NG Berdasarkan Penyebab',
                'filter_summary' => $filterSummary,
                'columns'        => $rekapCols,
                'rows'           => $rekapRows,
                'totals_row'     => ['', 'TOTAL', $records->count(), $totalNgQty, $totalNgQty > 0 ? 100.0 : 0.0],
            ],
        ]);
    }

    /**
     * 11. Export Delay Delivery (Problem Delay) — .xlsx (2 sheet) & .pdf
     */
    public function problemDelays(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));

        $records = ProblemDelay::with(['company', 'item', 'user'])
            ->orderByDesc('incident_date')
            ->orderByDesc('date_time')
            ->get();

        $startDate = trim((string) $request->query('start_date', ''));
        $endDate   = trim((string) $request->query('end_date', ''));
        $year      = trim((string) $request->query('year', ''));
        $month     = trim((string) $request->query('month', ''));
        $companyId = trim((string) $request->query('company_id', ''));
        $reason    = trim((string) $request->query('problem_reason', ''));
        $q         = mb_strtolower(trim((string) $request->query('q', '')), 'UTF-8');

        $records = $records->filter(function ($d) use ($startDate, $endDate, $year, $month, $companyId, $reason, $q) {
            $dt   = $d->incident_date ? Carbon::parse($d->incident_date) : Carbon::parse($d->date_time);
            $dStr = $dt->format('Y-m-d');

            if ($startDate !== '' && $dStr < $startDate) {
                return false;
            }
            if ($endDate !== '' && $dStr > $endDate) {
                return false;
            }
            if ($year !== '' && $dt->format('Y') !== $year) {
                return false;
            }
            if ($month !== '' && (int) $dt->format('n') !== (int) $month) {
                return false;
            }
            if ($companyId !== '' && (string) $d->company_id !== $companyId) {
                return false;
            }
            if ($reason !== '' && (string) $d->problem_reason !== $reason) {
                return false;
            }
            if ($q !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    $d->company?->company_code ?? '',
                    $d->company?->company_name ?? '',
                    $d->item?->part_no ?? '',
                    $d->item?->part_name ?? '',
                    $d->problem_reason ?? '',
                    $d->user?->name ?? '',
                ]), 'UTF-8');
                if (!str_contains($haystack, $q)) {
                    return false;
                }
            }
            return true;
        })->values();

        $filterParts = [];
        if ($q !== '') {
            $filterParts[] = 'Pencarian: "' . trim((string) $request->query('q')) . '"';
        }
        if ($startDate !== '' || $endDate !== '') {
            $filterParts[] = 'Tanggal: ' . ($startDate ?: 'Awal') . ' s/d ' . ($endDate ?: 'Sekarang');
        }
        if ($year !== '') {
            $filterParts[] = 'Tahun: ' . $year;
        }
        if ($month !== '') {
            $filterParts[] = 'Bulan: ' . $month;
        }
        if ($companyId !== '' && is_numeric($companyId)) {
            $comp = Company::find((int) $companyId);
            if ($comp) {
                $filterParts[] = 'Customer: ' . $comp->company_code . ' — ' . $comp->company_name;
            }
        }
        if ($reason !== '') {
            $filterParts[] = 'Penyebab: ' . $reason;
        }

        $filterSummary = $this->buildFilterSummary($filterParts);
        $stamp         = Carbon::now()->format('Ymd_His');
        $totalCases    = $records->count();

        $rekapReason = $records->groupBy('problem_reason')
            ->map(function ($grp, $key) use ($totalCases) {
                $cnt = $grp->count();
                $pct = $totalCases > 0 ? round(($cnt / $totalCases) * 100, 1) : 0.0;
                return [
                    'reason' => $key ?: '-',
                    'cases'  => $cnt,
                    'pct'    => $pct,
                ];
            })
            ->sortByDesc('cases')
            ->values();

        if ($format === 'pdf') {
            return $this->pdf->renderView(
                'exports.pdf.problem-delays',
                [
                    'title'         => 'Laporan Delay Delivery (Keterlambatan Pengiriman)',
                    'filterSummary' => $filterSummary,
                    'records'       => $records,
                    'rekapReason'   => $rekapReason,
                    'totalCases'    => $totalCases,
                    'exportedAt'    => Carbon::now()->format('d M Y, H:i') . ' WIB',
                    'exportedBy'    => $request->user()?->name ?? 'PPIC',
                ],
                "DRC_DelayDelivery_{$stamp}.pdf",
                'a4',
                'landscape'
            );
        }

        $detailCols = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Tanggal Kejadian', 'type' => 'center', 'width' => 17],
            ['label' => 'Customer Code', 'type' => 'text', 'width' => 15],
            ['label' => 'Nama Customer', 'type' => 'text', 'width' => 26],
            ['label' => 'Part No', 'type' => 'text', 'width' => 18],
            ['label' => 'Part Name', 'type' => 'text', 'width' => 28],
            ['label' => 'Penyebab Delay', 'type' => 'center', 'width' => 20],
            ['label' => 'Dicatat Oleh', 'type' => 'text', 'width' => 18],
            ['label' => 'Waktu Input', 'type' => 'center', 'width' => 18],
        ];

        $detailRows = [];
        foreach ($records as $idx => $d) {
            $incDt = $d->incident_date ? Carbon::parse($d->incident_date) : Carbon::parse($d->date_time);
            $detailRows[] = [
                $idx + 1,
                $incDt->format('Y-m-d'),
                $d->company?->company_code ?? '-',
                $d->company?->company_name ?? '-',
                $d->item?->part_no ?: '-',
                $d->item?->part_name ?? '-',
                $d->problem_reason ?: '-',
                $d->user?->name ?? '-',
                $d->date_time ? Carbon::parse($d->date_time)->format('Y-m-d H:i') : '-',
            ];
        }

        $rekapCols = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Penyebab Delay', 'type' => 'text', 'width' => 26],
            ['label' => 'Jumlah Kasus', 'type' => 'int', 'width' => 16],
            ['label' => 'Persentase (%)', 'type' => 'number', 'width' => 16],
        ];

        $rekapRows = [];
        foreach ($rekapReason as $idx => $rk) {
            $rekapRows[] = [
                $idx + 1,
                $rk['reason'],
                $rk['cases'],
                $rk['pct'],
            ];
        }

        return $this->excel->downloadMultiSheet("DRC_DelayDelivery_{$stamp}.xlsx", [
            [
                'sheet_title'    => 'Detail Delay',
                'report_title'   => 'Laporan Detail Delay Delivery',
                'filter_summary' => $filterSummary,
                'columns'        => $detailCols,
                'rows'           => $detailRows,
                'totals_row'     => ['', 'TOTAL', $totalCases . ' Kejadian', '', '', '', '', '', ''],
            ],
            [
                'sheet_title'    => 'Rekap Penyebab',
                'report_title'   => 'Rekapitulasi Delay Berdasarkan Penyebab',
                'filter_summary' => $filterSummary,
                'columns'        => $rekapCols,
                'rows'           => $rekapRows,
                'totals_row'     => ['', 'TOTAL', $totalCases, $totalCases > 0 ? 100.0 : 0.0],
            ],
        ]);
    }

    /**
     * 12. Export Master Item Aktif — .xlsx
     */
    public function items(Request $request)
    {
        $items = Item::with(['company', 'inventoryStock', 'bomsAsInput.outputItem'])
            ->orderByDesc('id')
            ->get();

        $q        = mb_strtolower(trim((string) $request->query('q', '')), 'UTF-8');
        $type     = trim((string) $request->query('type', ''));
        $compType = trim((string) $request->query('company_type', ''));

        $items = $items->filter(function ($it) use ($q, $type, $compType) {
            $cCode = $it->company->company_code ?? 'INTERNAL';
            $cType = $it->company->type_company ?? 'INTERNAL';

            if ($q !== '') {
                $haystack = mb_strtolower(($it->part_no ?? '') . ' ' . $it->part_name . ' ' . $cCode, 'UTF-8');
                if (!str_contains($haystack, $q)) {
                    return false;
                }
            }
            if ($type !== '' && $it->type !== $type) {
                return false;
            }
            if ($compType !== '' && $cType !== $compType) {
                return false;
            }
            return true;
        })->values();

        $filterParts = [];
        if ($q !== '') {
            $filterParts[] = 'Pencarian: "' . trim((string) $request->query('q')) . '"';
        }
        if ($type !== '') {
            $filterParts[] = 'Tipe Item: ' . $type;
        }
        if ($compType !== '') {
            $filterParts[] = 'Tipe Perusahaan: ' . $compType;
        }

        $columns = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'ID', 'type' => 'center', 'width' => 8],
            ['label' => 'Part No', 'type' => 'text', 'width' => 18],
            ['label' => 'Part Name', 'type' => 'text', 'width' => 30],
            ['label' => 'Tipe', 'type' => 'center', 'width' => 13],
            ['label' => 'UOM', 'type' => 'center', 'width' => 9],
            ['label' => 'Kode Perusahaan', 'type' => 'text', 'width' => 16],
            ['label' => 'Tipe Perusahaan', 'type' => 'center', 'width' => 15],
            ['label' => 'Qty PO', 'type' => 'number', 'width' => 12],
            ['label' => 'WD', 'type' => 'int', 'width' => 8],
            ['label' => 'Kanban', 'type' => 'number', 'width' => 11],
            ['label' => 'Std Day 1', 'type' => 'number', 'width' => 12],
            ['label' => 'Std Day 2', 'type' => 'number', 'width' => 12],
            ['label' => 'Std Day 3', 'type' => 'number', 'width' => 12],
            ['label' => 'Current Stock', 'type' => 'number', 'width' => 14],
            ['label' => 'Status Stock', 'type' => 'center', 'width' => 15],
        ];

        $rows = [];
        foreach ($items as $idx => $it) {
            $st = $it->inventoryStock;
            $rows[] = [
                $idx + 1,
                $it->id,
                $it->part_no ?: '-',
                $it->part_name,
                $it->type,
                $it->uom,
                $it->company->company_code ?? 'INTERNAL',
                $it->company->type_company ?? 'INTERNAL',
                (float) $it->qty_po,
                (int) $it->working_days,
                (float) $it->qty_kanban,
                (float) $it->calculated_std_day_1,
                (float) $it->std_day_2,
                (float) $it->std_day_3,
                $st ? (float) $st->current_stock : 0.0,
                $st ? $st->calculateDynamicStatus() : 'EMPTY STOCK',
            ];
        }

        return $this->excel->downloadSingleSheet(
            'DRC_MasterItem_' . Carbon::now()->format('Ymd_His') . '.xlsx',
            'Master Item',
            'Daftar Master Item Aktif & Standar Stok Harian',
            $this->buildFilterSummary($filterParts),
            $columns,
            $rows
        );
    }

    /**
     * 13. Export Bill of Materials (BOM) — .xlsx
     */
    public function boms(Request $request)
    {
        $boms = Bom::with(['process', 'outputItem', 'inputItem'])
            ->orderBy('bom_group')
            ->orderByDesc('is_trigger')
            ->orderBy('id')
            ->get();

        $q         = mb_strtolower(trim((string) $request->query('q', '')), 'UTF-8');
        $processId = trim((string) $request->query('process_id', ''));
        $groupType = trim((string) $request->query('group_type', ''));

        $boms = $boms->filter(function ($b) use ($q, $processId, $groupType) {
            if ($processId !== '' && (string) $b->process_id !== $processId) {
                return false;
            }
            if ($groupType !== '' && $b->group_type !== $groupType) {
                return false;
            }
            if ($q !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    $b->bom_group ?? '',
                    $b->process->process_name ?? '',
                    $b->outputItem->part_no ?? '',
                    $b->outputItem->part_name ?? '',
                    $b->inputItem->part_no ?? '',
                    $b->inputItem->part_name ?? '',
                ]), 'UTF-8');
                if (!str_contains($haystack, $q)) {
                    return false;
                }
            }
            return true;
        })->values();

        $filterParts = [];
        if ($q !== '') {
            $filterParts[] = 'Pencarian: "' . trim((string) $request->query('q')) . '"';
        }
        if ($processId !== '' && is_numeric($processId)) {
            $proc = Process::find((int) $processId);
            if ($proc) {
                $filterParts[] = 'Proses: ' . $proc->process_name;
            }
        }
        if ($groupType !== '') {
            $filterParts[] = 'Tipe BOM: ' . $groupType;
        }

        $columns = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'BOM Group', 'type' => 'center', 'width' => 16],
            ['label' => 'Tipe BOM', 'type' => 'center', 'width' => 14],
            ['label' => 'Proses', 'type' => 'text', 'width' => 18],
            ['label' => 'Output Part No', 'type' => 'text', 'width' => 18],
            ['label' => 'Output Part Name', 'type' => 'text', 'width' => 28],
            ['label' => 'Output Tipe', 'type' => 'center', 'width' => 13],
            ['label' => 'Input Part No', 'type' => 'text', 'width' => 18],
            ['label' => 'Input Part Name', 'type' => 'text', 'width' => 28],
            ['label' => 'Peran Input', 'type' => 'center', 'width' => 13],
            ['label' => 'Qty Usage', 'type' => 'text', 'width' => 12],
            ['label' => 'UOM Input', 'type' => 'center', 'width' => 11],
        ];

        $rows = [];
        foreach ($boms as $idx => $b) {
            $rows[] = [
                $idx + 1,
                $b->bom_group ?: ('SG-' . $b->id),
                $b->group_type,
                $b->process->process_name ?? '-',
                $b->outputItem?->part_no ?: '-',
                $b->outputItem?->part_name ?? '-',
                $b->outputItem?->type ?? '-',
                $b->inputItem?->part_no ?: '-',
                $b->inputItem?->part_name ?? '-',
                $b->is_trigger ? 'TRIGGER' : 'PENDAMPING',
                $b->formatted_qty_usage,
                $b->inputItem?->uom ?? '-',
            ];
        }

        return $this->excel->downloadSingleSheet(
            'DRC_BOM_' . Carbon::now()->format('Ymd_His') . '.xlsx',
            'Bill of Materials',
            'Daftar Resep Bill of Materials (BOM)',
            $this->buildFilterSummary($filterParts),
            $columns,
            $rows
        );
    }

    /**
     * 14. Export Kartu Mutasi per Item (dari Modal Informasi Item) — .xlsx
     */
    public function itemHistory(Request $request, Item $item)
    {
        $item->load(['company', 'inventoryStock']);

        $q         = mb_strtolower(trim((string) $request->query('q', '')), 'UTF-8');
        $startDate = trim((string) $request->query('start_date', ''));
        $endDate   = trim((string) $request->query('end_date', ''));
        $startTime = trim((string) $request->query('start_time', ''));
        $endTime   = trim((string) $request->query('end_time', ''));

        $history = collect($item->getStockMutationHistory())
            ->filter(function (array $h) use ($q, $startDate, $endDate, $startTime, $endTime) {
                if ($q !== '') {
                    $hit = collect([
                        $h['type_label'] ?? '',
                        $h['reference'] ?? '',
                        $h['user_name'] ?? '',
                        $h['status'] ?? '',
                        $h['corrected_by'] ?? '',
                        $h['correction_notes'] ?? '',
                    ])->contains(fn ($v) => str_contains(mb_strtolower((string) $v, 'UTF-8'), $q));
                    if (!$hit) {
                        return false;
                    }
                }
                if ($startDate !== '' && ($h['date'] ?? '') < $startDate) {
                    return false;
                }
                if ($endDate !== '' && ($h['date'] ?? '') > $endDate) {
                    return false;
                }
                if ($startTime !== '' && ($h['time'] ?? '') < $startTime) {
                    return false;
                }
                if ($endTime !== '' && ($h['time'] ?? '') > $endTime) {
                    return false;
                }
                return true;
            })
            ->values();

        $filterParts = [
            'Item: ' . ($item->part_no ? $item->part_no . ' · ' : '') . $item->part_name,
            'Saldo Saat Ini: ' . floatval($item->inventoryStock->current_stock ?? 0) . ' ' . $item->uom,
        ];
        if ($q !== '') {
            $filterParts[] = 'Pencarian: "' . trim((string) $request->query('q')) . '"';
        }
        if ($startDate !== '' || $endDate !== '') {
            $filterParts[] = 'Tanggal: ' . ($startDate ?: 'Awal') . ' s/d ' . ($endDate ?: 'Sekarang');
        }
        if ($startTime !== '' || $endTime !== '') {
            $filterParts[] = 'Jam: ' . ($startTime ?: '00:00') . '–' . ($endTime ?: '23:59');
        }

        $columns = [
            ['label' => 'No', 'type' => 'center', 'width' => 6],
            ['label' => 'Tanggal', 'type' => 'center', 'width' => 13],
            ['label' => 'Jam', 'type' => 'center', 'width' => 9],
            ['label' => 'Jenis Transaksi', 'type' => 'text', 'width' => 26],
            ['label' => 'Referensi', 'type' => 'text', 'width' => 30],
            ['label' => 'Petugas', 'type' => 'text', 'width' => 18],
            ['label' => 'Mutasi (+/-)', 'type' => 'number', 'width' => 14],
            ['label' => 'UOM', 'type' => 'center', 'width' => 9],
            ['label' => 'Status', 'type' => 'center', 'width' => 14],
            ['label' => 'Qty Awal', 'type' => 'number', 'width' => 12],
            ['label' => 'Dikoreksi Oleh', 'type' => 'text', 'width' => 18],
            ['label' => 'Waktu Koreksi', 'type' => 'center', 'width' => 18],
            ['label' => 'Alasan Koreksi', 'type' => 'text', 'width' => 26],
        ];

        $rows = [];
        foreach ($history as $idx => $h) {
            $rows[] = [
                $idx + 1,
                $h['date'] ?? '-',
                $h['time'] ?? '-',
                $h['type_label'] ?? '-',
                $h['reference'] ?? '-',
                $h['user_name'] ?? '-',
                (float) ($h['qty_change'] ?? 0),
                $h['uom'] ?? $item->uom,
                $h['status'] ?? '-',
                $h['qty_original'] !== null ? (float) $h['qty_original'] : '-',
                $h['corrected_by'] ?? '-',
                $h['corrected_at_fmt'] ?? '-',
                $h['correction_notes'] ?? '-',
            ];
        }

        $totals = [
            '', 'TOTAL', $history->count() . ' Mutasi', '', '', 'Netto Mutasi Filter:',
            round((float) $history->sum('qty_change'), 2),
            $item->uom, '', '', '', '', '',
        ];

        $safeCode = preg_replace('/[^A-Za-z0-9\-_]+/', '_', $item->part_no ?: ('ITEM_' . $item->id));

        return $this->excel->downloadSingleSheet(
            "DRC_KartuMutasi_{$safeCode}_" . Carbon::now()->format('Ymd_His') . '.xlsx',
            'Kartu Mutasi',
            'Kartu Riwayat Mutasi Stock: ' . ($item->part_no ? $item->part_no . ' — ' : '') . $item->part_name,
            $this->buildFilterSummary($filterParts),
            $columns,
            $rows,
            $totals
        );
    }

    // =========================================================================
    // HELPER FILTER KONSISTEN (KOMPATIBEL MYSQL & SQLITE)
    // =========================================================================

    private function applySqlDateRange($query, string $column, Request $request): void
    {
        $startDate = trim((string) $request->query('start_date', ''));
        $endDate   = trim((string) $request->query('end_date', ''));

        if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $query->whereDate($column, '>=', $startDate);
        }
        if ($endDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $query->whereDate($column, '<=', $endDate);
        }
    }

    private function applyTimeRangeFilter(Collection $records, string $column, Request $request): Collection
    {
        $startTime = trim((string) $request->query('start_time', ''));
        $endTime   = trim((string) $request->query('end_time', ''));

        if ($startTime === '' && $endTime === '') {
            return $records;
        }

        return $records->filter(function ($row) use ($column, $startTime, $endTime) {
            if (empty($row->{$column})) {
                return false;
            }
            $hm = Carbon::parse($row->{$column})->format('H:i');
            if ($startTime !== '' && $hm < $startTime) {
                return false;
            }
            if ($endTime !== '' && $hm > $endTime) {
                return false;
            }
            return true;
        })->values();
    }

    private function applySimpleItemTxFilters(
        Collection $records,
        Request $request,
        bool $includeLot = false,
        bool $includeNotes = false
    ): Collection {
        $q         = mb_strtolower(trim((string) $request->query('q', '')), 'UTF-8');
        $type      = trim((string) $request->query('type', ''));
        $companyId = trim((string) $request->query('company_id', ''));

        if ($q === '' && $type === '' && $companyId === '') {
            return $records;
        }

        return $records->filter(function ($r) use ($q, $type, $companyId, $includeLot, $includeNotes) {
            if ($type !== '' && ($r->item?->type ?? '') !== $type) {
                return false;
            }
            if ($companyId !== '' && (string) ($r->item?->company_id ?? '') !== $companyId) {
                return false;
            }
            if ($q !== '') {
                $parts = [
                    $r->item?->part_no ?? '',
                    $r->item?->part_name ?? '',
                    $r->item?->company?->company_code ?? '',
                    $r->item?->company?->company_name ?? '',
                    $r->user?->name ?? '',
                    $r->correction_notes ?? '',
                ];
                if ($includeLot) {
                    $parts[] = $r->lot_number ?? '';
                }
                if ($includeNotes) {
                    $parts[] = $r->notes ?? '';
                    $parts[] = $r->adjustment_type ?? '';
                }
                if (!str_contains(mb_strtolower(implode(' ', $parts), 'UTF-8'), $q)) {
                    return false;
                }
            }
            return true;
        })->values();
    }

    private function applyMaterialLotTxFilters(Collection $records, Request $request): Collection
    {
        $q = mb_strtolower(trim((string) $request->query('q', '')), 'UTF-8');
        if ($q === '') {
            return $records;
        }

        return $records->filter(function ($r) use ($q) {
            $haystack = mb_strtolower(implode(' ', [
                $r->lot_number ?? '',
                $r->return_batch ?? '',
                $r->bom_group ?? '',
                $r->materialItem?->part_no ?? '',
                $r->materialItem?->part_name ?? '',
                $r->targetItem?->part_no ?? '',
                $r->targetItem?->part_name ?? '',
                $r->user?->name ?? '',
                $r->correction_notes ?? '',
            ]), 'UTF-8');
            return str_contains($haystack, $q);
        })->values();
    }

    private function buildTxFilterSummary(Request $request, array $extra = []): string
    {
        $parts = [];
        if ($request->filled('q')) {
            $parts[] = 'Pencarian: "' . trim((string) $request->query('q')) . '"';
        }
        if ($request->filled('start_date') || $request->filled('end_date')) {
            $parts[] = 'Tanggal: ' . ($request->query('start_date') ?: 'Awal') . ' s/d ' . ($request->query('end_date') ?: 'Sekarang');
        }
        if ($request->filled('start_time') || $request->filled('end_time')) {
            $parts[] = 'Jam: ' . ($request->query('start_time') ?: '00:00') . '–' . ($request->query('end_time') ?: '23:59');
        }
        if ($request->filled('type')) {
            $parts[] = 'Tipe Item: ' . $request->query('type');
        }
        if ($request->filled('qty_check')) {
            $parts[] = 'Status Check: ' . $request->query('qty_check');
        }

        return $this->buildFilterSummary(array_merge($parts, $extra));
    }

    private function buildFilterSummary(array $parts): string
    {
        $clean = array_values(array_filter(array_map('trim', $parts), fn ($v) => $v !== ''));
        return empty($clean) ? 'Semua Data (Tanpa Filter)' : implode('  ·  ', $clean);
    }
}