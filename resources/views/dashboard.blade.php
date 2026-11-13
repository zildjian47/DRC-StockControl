<x-app-layout>
    @php
        $today = \Carbon\Carbon::today();
        $outputToday = \App\Models\Production::whereDate('date_time', $today)->sum('qty');
        $totalStock = \App\Models\InventoryStock::whereHas('item', fn($q) => $q->whereNull('deleted_at'))->sum('current_stock');
        $inboundToday = \App\Models\Inbound::whereDate('date_time', $today)->count();
        $pendingInbound = \App\Models\Inbound::where('qty_check', 'PENDING')->count();

        $totalProdAll = \App\Models\Production::sum('qty');
        $totalNgAll = \App\Models\ProductionNg::sum('qty_ng');
        $ngRate = $totalProdAll > 0 ? round(($totalNgAll / $totalProdAll) * 100, 2) : 0;

        $delayCount = \App\Models\ProblemDelay::whereDate('date_time', $today)->count();
        $activeLots = \App\Models\LotTracer::with(['process', 'materialItem', 'targetItem'])
            ->where('status_lot', 'ON PROGRESS')
            ->orderBy('updated_at', 'desc')
            ->take(8)
            ->get();

        $outByDay = \App\Models\Production::selectRaw('DATE(date_time) as d, SUM(qty) as q')
            ->whereDate('date_time', '>=', $today->copy()->subDays(6))
            ->groupBy('d')
            ->pluck('q', 'd');

        $runningLots = \App\Models\LotTracer::where('status_lot', 'ON PROGRESS')->count();
        $lowStockItems = \App\Models\InventoryStock::whereHas('item', fn($q) => $q->whereNull('deleted_at'))
            ->whereIn('status_stock', ['EMPTY STOCK', 'VERY LOW', 'LOW STOCK'])
            ->count();
        $veryLowItems = \App\Models\InventoryStock::whereHas('item', fn($q) => $q->whereNull('deleted_at'))
            ->whereIn('status_stock', ['EMPTY STOCK', 'VERY LOW'])
            ->count();
    @endphp

    <x-slot name="header">
        <x-page-header title="Dashboard Produksi"
                       subtitle="{{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }} · Monitoring real-time">
            <x-slot name="actions">
                <a href="{{ route('lot_tracers.index') }}" class="btn btn-primary">
                    <x-icon name="route" /> Buka Lot Tracer
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="kpis">
        <div class="kpi">
            <div class="kpi-l">Output Hari Ini</div>
            <div class="kpi-v">{{ number_format($outputToday, 0, ',', '.') }}<small>unit</small></div>
            <div class="kpi-s">Hasil scan produksi hari ini</div>
        </div>
        <div class="kpi">
            <div class="kpi-l">Stok Tersedia</div>
            <div class="kpi-v">{{ number_format($totalStock, 0, ',', '.') }}</div>
            <div class="kpi-s">Total saldo seluruh part aktif</div>
        </div>
        <div class="kpi">
            <div class="kpi-l">Inbound Hari Ini</div>
            <div class="kpi-v">{{ $inboundToday }}<small>dokumen</small></div>
            <div class="kpi-s">
                <span class="pill pill-good">Tercatat di sistem</span>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-l">NG Rate</div>
            <div class="kpi-v">{{ $ngRate }}<small>%</small></div>
            <div class="kpi-s">
                <span class="pill {{ $ngRate <= 2 ? 'pill-good' : 'pill-crit' }}">Total NG: {{ floatval($totalNgAll) }}</span>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-l">Delay Delivery</div>
            <div class="kpi-v">{{ $delayCount }}<small>log hari ini</small></div>
            <div class="kpi-s">
                <span class="pill {{ $delayCount > 0 ? 'pill-crit' : 'pill-good' }}">
                    {{ $delayCount > 0 ? 'Perlu tindak lanjut' : 'Tidak ada kendala' }}
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-[18px] items-stretch">
        <section class="panel xl:col-span-2 flex flex-col">
            <div class="panel-head">
                <h3 class="panel-title">Output produksi, 7 hari terakhir</h3>
            </div>
            <div class="panel-body flex-1 flex flex-col justify-between">
                @php
                    $series = collect(range(6, 0))->map(function ($i) use ($outByDay) {
                        $d = \Carbon\Carbon::today()->subDays($i);
                        return ['label' => $d->translatedFormat('D d'), 'v' => (float) ($outByDay[$d->toDateString()] ?? 0)];
                    });
                    $max = max(1, $series->max('v'));
                    $W = 560; $H = 225; $L = 46; $R = 12; $T = 16; $B = 28; $step = ($W - $L - $R) / 7; $bw = 36;
                @endphp
                <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full h-auto min-h-[260px]" role="img" aria-label="Output produksi 7 hari terakhir">
                    @foreach ([0, 0.5, 1] as $g)
                        @php $y = $T + ($H - $T - $B) * (1 - $g); @endphp
                        <line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $y }}" y2="{{ $y }}" stroke="var(--line)" stroke-width="1"/>
                        <text x="{{ $L - 8 }}" y="{{ $y + 4 }}" text-anchor="end" fill="var(--mut)" font-size="11" font-family="Inter, sans-serif">{{ number_format($max * $g, 0, ',', '.') }}</text>
                    @endforeach
                    @foreach ($series as $i => $s)
                        @php
                            $h = $s['v'] > 0 ? (($H - $T - $B) * $s['v'] / $max) : 0;
                            $x = $L + $step * $i + ($step - $bw) / 2;
                        @endphp
                        @if($s['v'] > 0 && $h > 0)
                            <rect x="{{ $x }}" y="{{ $H - $B - $h }}" width="{{ $bw }}" height="{{ $h }}" rx="2" fill="var(--c1)" @if($i === 6) opacity=".85" @endif>
                                <title>{{ $s['label'] }}: {{ number_format($s['v'], 0, ',', '.') }}</title>
                            </rect>
                        @endif
                        <text x="{{ $x + $bw / 2 }}" y="{{ $H - 8 }}" text-anchor="middle" fill="var(--mut)" font-size="11" font-family="Inter, sans-serif">{{ $s['label'] }}</text>
                    @endforeach
                </svg>
                <p class="hint mt-2">Batang paling kanan adalah akumulasi output hari ini dan masih berjalan.</p>
            </div>
        </section>

        <section class="panel flex flex-col">
            <div class="panel-head">
                <h3 class="panel-title">Perlu tindakan</h3>
            </div>
            <div class="panel-body flex-1 flex flex-col justify-between gap-4">
                <div class="divide-y divide-slate-200 text-[13px]">
                    <div class="flex items-center justify-between py-3.5 first:pt-1">
                        <div>
                            <div class="font-semibold text-slate-900">Stok Kritis (Empty / Very Low)</div>
                            <div class="text-xs text-slate-500 mt-0.5">Item dengan ketahanan stok &lt; 1 hari</div>
                        </div>
                        <span class="pill {{ $veryLowItems > 0 ? 'pill-crit' : 'pill-good' }} num">{{ $veryLowItems }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3.5">
                        <div>
                            <div class="font-semibold text-slate-900">Total Item Stok Rendah</div>
                            <div class="text-xs text-slate-500 mt-0.5">Termasuk status Low Stock (&lt; Std Day 2)</div>
                        </div>
                        <span class="pill {{ $lowStockItems > 0 ? 'pill-warn' : 'pill-good' }} num">{{ $lowStockItems }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3.5">
                        <div>
                            <div class="font-semibold text-slate-900">Log Delay Delivery Hari Ini</div>
                            <div class="text-xs text-slate-500 mt-0.5">Kendala pengiriman tercatat hari ini</div>
                        </div>
                        <span class="pill {{ $delayCount ? 'pill-crit' : 'pill-good' }} num">{{ $delayCount }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3.5 last:pb-1">
                        <div>
                            <div class="font-semibold text-slate-900">Lot Sedang Berjalan</div>
                            <div class="text-xs text-slate-500 mt-0.5">Status pemantauan WIP ON PROGRESS</div>
                        </div>
                        <span class="pill pill-info num">{{ $runningLots }}</span>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-200 flex items-center justify-between gap-2">
                    <a href="{{ route('inventory_stocks.index') }}" class="btn btn-sm flex-1">Cek Inventory Stock</a>
                    <a href="{{ route('problem_delays.index') }}" class="btn btn-sm flex-1">Buka Delay Delivery</a>
                </div>
            </div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span>Lot Sedang Berjalan (Live WIP)</span>
                <span class="count-chip">{{ $activeLots->count() }} Lot</span>
            </div>
            <a href="{{ route('lot_tracers.index') }}" class="btn btn-sm">Buka Lot Tracer</a>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-28">Lot</th>
                        <th>Produk (Target)</th>
                        <th class="w-44">Proses</th>
                        <th class="w-48">Progres Produksi</th>
                        <th class="num w-40">Act FG / WIP</th>
                        <th class="w-32 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeLots as $lot)
                        @php
                            $pct = $lot->target_qty_estimasi > 0
                                ? min(100, round(($lot->actual_qty_prod / $lot->target_qty_estimasi) * 100))
                                : 0;
                        @endphp
                        <tr>
                            <td class="num font-semibold text-accent">{{ $lot->lot_number }}</td>
                            <td class="font-medium text-slate-900">{{ $lot->targetItem->part_name ?? '-' }}</td>
                            <td class="text-slate-600 text-xs">{{ $lot->process->process_name ?? '-' }}</td>
                            <td class="w-48">
                                <div class="inline-flex items-center gap-2.5" style="width: 164px;">
                                    <span class="track"><i style="width:{{ $pct }}%"></i></span>
                                    <span class="num text-xs font-semibold text-slate-700" style="width: 34px; text-align: right; flex: 0 0 34px;">{{ $pct }}%</span>
                                </div>
                            </td>
                            <td class="num text-xs whitespace-nowrap">
                                <b class="text-emerald-700">{{ floatval($lot->act_fg) }}</b> / <b class="text-amber-700">WIP {{ floatval($lot->qty_wip) }}</b>
                            </td>
                            <td class="text-center">
                                @if($lot->status_lot === 'ON PROGRESS')
                                    <span class="pill pill-good">ON PROGRESS</span>
                                @else
                                    <span class="pill pill-neu">FINISH</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">Belum ada Lot yang berjalan.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-app-layout>