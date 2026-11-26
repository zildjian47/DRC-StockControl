<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Inbound;
use App\Models\InventoryStock;
use App\Models\LotTracer;
use App\Models\ProblemDelay;
use App\Models\Production;
use App\Models\ProductionNg;
use App\Services\StockSnapshotService;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        private readonly StockSnapshotService $snapshot
    ) {
    }

    public function index()
    {
        $today = Carbon::today();

        $outputToday  = Production::whereDate('date_time', $today)->sum('qty');
        $totalStock   = InventoryStock::whereHas('item', fn ($q) => $q->whereNull('deleted_at'))->sum('current_stock');
        $inboundToday = Inbound::whereDate('date_time', $today)->count();

        $totalProdAll = Production::sum('qty');
        $totalNgAll   = ProductionNg::sum('qty_ng');
        $ngRate       = $totalProdAll > 0 ? round(($totalNgAll / $totalProdAll) * 100, 2) : 0;

        // Diselaraskan dengan incident_date (fallback date_time bila data lama null)
        $delayCount = ProblemDelay::where(function ($q) use ($today) {
            $q->whereDate('incident_date', $today)
                ->orWhere(function ($sub) use ($today) {
                    $sub->whereNull('incident_date')
                        ->whereDate('date_time', $today);
                });
        })->count();

        $activeLots = LotTracer::with(['process', 'materialItem', 'targetItem'])
            ->where('status_lot', 'ON PROGRESS')
            ->orderBy('updated_at', 'desc')
            ->take(8)
            ->get();

        $outByDay = Production::selectRaw('DATE(date_time) as d, SUM(qty) as q')
            ->whereDate('date_time', '>=', $today->copy()->subDays(6))
            ->groupBy('d')
            ->pluck('q', 'd');

        $runningLots   = LotTracer::where('status_lot', 'ON PROGRESS')->count();
        $lowStockItems = InventoryStock::whereHas('item', fn ($q) => $q->whereNull('deleted_at'))
            ->whereIn('status_stock', ['EMPTY STOCK', 'VERY LOW', 'LOW STOCK'])
            ->count();
        $veryLowItems = InventoryStock::whereHas('item', fn ($q) => $q->whereNull('deleted_at'))
            ->whereIn('status_stock', ['EMPTY STOCK', 'VERY LOW'])
            ->count();

        // Sumber tunggal snapshot Stock Saat Ini, Stock 07:00, WIP, & Potensi Max (sama dengan Export & Kartu Mobile)
        $built               = $this->snapshot->buildAll();
        $dashboardItems      = $built['items'];
        $sharedMaterialsList = $built['shared_materials_list'];

        $allCompaniesList = Company::orderByRaw("CASE type_company WHEN 'CUSTOMER' THEN 1 WHEN 'INTERNAL' THEN 2 ELSE 3 END")
            ->orderBy('company_code')
            ->get()
            ->map(fn ($c) => [
                'id'   => $c->id,
                'code' => $c->company_code,
                'name' => $c->company_name,
                'type' => $c->type_company,
            ])->values();

        $defaultFgCustomer     = $allCompaniesList->firstWhere('type', 'CUSTOMER');
        $defaultStockCompanyId = $defaultFgCustomer ? (string) $defaultFgCustomer['id'] : '';

        $monthNamesShort = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];
        $monthNamesFull = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $allDelays = ProblemDelay::with(['company', 'item.company'])
            ->orderByDesc('incident_date')
            ->orderByDesc('date_time')
            ->get()
            ->map(function ($d) use ($monthNamesShort, $monthNamesFull) {
                $dt   = $d->incident_date ? Carbon::parse($d->incident_date) : Carbon::parse($d->date_time);
                $mNum = (int) $dt->format('n');
                $yr   = (int) $dt->format('Y');

                return [
                    'id'             => $d->id,
                    'year'           => (string) $yr,
                    'month_num'      => $mNum,
                    'month_key'      => $dt->format('Y-m'),
                    'month_short'    => ($monthNamesShort[$mNum] ?? $dt->format('M')) . ' ' . substr((string) $yr, -2),
                    'month_label'    => ($monthNamesFull[$mNum] ?? $dt->format('M')) . ' ' . $yr,
                    'company_id'     => $d->company_id,
                    'company_code'   => $d->company->company_code ?? '-',
                    'company_name'   => $d->company->company_name ?? '-',
                    'company_type'   => $d->company->type_company ?? 'CUSTOMER',
                    'item_id'        => $d->item_id,
                    'part_no'        => $d->item?->part_no ?: '-',
                    'part_name'      => $d->item?->part_name ?? '-',
                    'item_type'      => $d->item?->type ?? 'FG',
                    'problem_reason' => $d->problem_reason,
                ];
            })->values();

        $delayYears = $allDelays->pluck('year')->unique()->sortDesc()->values()->all();
        if (empty($delayYears)) {
            $delayYears = [(string) date('Y')];
        }
        $delayReasonsList = ProblemDelayController::REASONS;

        return view('dashboard', compact(
            'outputToday',
            'totalStock',
            'inboundToday',
            'totalNgAll',
            'ngRate',
            'delayCount',
            'activeLots',
            'outByDay',
            'runningLots',
            'lowStockItems',
            'veryLowItems',
            'sharedMaterialsList',
            'dashboardItems',
            'allCompaniesList',
            'defaultStockCompanyId',
            'allDelays',
            'delayYears',
            'delayReasonsList'
        ));
    }
}