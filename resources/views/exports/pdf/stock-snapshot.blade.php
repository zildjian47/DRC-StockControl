<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} · DRC Stock Control</title>
    @include('exports.pdf._styles')
</head>
<body>
    <div class="pdf-footer">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td>PT. Dian Raya Cipta · DRC Stock Control</td>
                <td style="text-align:right;"><span class="page-num"></span></td>
            </tr>
        </table>
    </div>

    <table class="header-tbl">
        <tr>
            <td>
                <div class="brand">PT. Dian Raya Cipta · DRC Stock Control</div>
                <div class="doc-title">{{ $title }}</div>
                <div class="meta-line">Cut-off Snapshot Pagi: <strong>{{ $cutoffLabel }}</strong></div>
            </td>
            <td class="meta-right">
                <div><strong>Dicetak:</strong> {{ $exportedAt }}</div>
                <div><strong>Oleh:</strong> {{ $exportedBy }}</div>
                <div><strong>Total Item:</strong> {{ $summary['total'] }} item</div>
            </td>
        </tr>
    </table>

    <div class="filter-box">
        <strong>Filter Aktif:</strong> {{ $filterSummary }}
    </div>

    <table class="kpi-tbl">
        <tr>
            <td class="kpi-card">
                <div class="kpi-lbl">Total Item</div>
                <div class="kpi-val num">{{ $summary['total'] }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-lbl">Empty Stock</div>
                <div class="kpi-val num" style="color:#0F172A;">{{ $summary['empty'] }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-lbl">Very Low</div>
                <div class="kpi-val num" style="color:#B91C1C;">{{ $summary['very_low'] }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-lbl">Low Stock</div>
                <div class="kpi-val num" style="color:#B45309;">{{ $summary['low'] }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-lbl">Normal Stock</div>
                <div class="kpi-val num" style="color:#15803D;">{{ $summary['normal'] }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-lbl">Overstock</div>
                <div class="kpi-val num" style="color:#1D4ED8;">{{ $summary['overstock'] }}</div>
            </td>
        </tr>
    </table>

    @php
        $badgeFor = fn ($st) => match ($st) {
            'EMPTY STOCK'  => 'b-empty',
            'VERY LOW'     => 'b-crit',
            'LOW STOCK'    => 'b-warn',
            'NORMAL STOCK' => 'b-good',
            'OVERSTOCK'    => 'b-info',
            default        => 'b-neu',
        };
        $fmtNum = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
    @endphp

    <table class="data-tbl">
        <thead>
            <tr>
                <th class="text-center" style="width:3%;">No</th>
                <th style="width:11%;">Part No</th>
                <th style="width:19%;">Part Name</th>
                <th class="text-center" style="width:7%;">Tipe</th>
                <th class="text-center" style="width:5%;">UOM</th>
                <th style="width:8%;">Perusahaan</th>
                <th class="text-right" style="width:7%;">Stock 07:00</th>
                <th class="text-right" style="width:7%;">Current</th>
                <th class="text-right" style="width:6%;">WIP</th>
                <th class="text-right" style="width:6%;">Std 1</th>
                <th class="text-right" style="width:6%;">Std 2</th>
                <th class="text-right" style="width:6%;">Std 3</th>
                <th class="text-right" style="width:5%;">Ratio</th>
                <th class="text-center" style="width:10%;">{{ $snapshotMode === '07' ? 'Status 07:00' : 'Status Kini' }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $idx => $r)
                @php
                    $activeStatus = $snapshotMode === '07' ? $r['status_07'] : $r['status_stock'];
                    $activeRatio  = $snapshotMode === '07' ? $r['ratio_07'] : $r['ratio_stock'];
                @endphp
                <tr>
                    <td class="text-center num">{{ $idx + 1 }}</td>
                    <td class="num bold">{{ $r['part_no'] }}</td>
                    <td>{{ $r['part_name'] }}</td>
                    <td class="text-center">{{ $r['type'] }}</td>
                    <td class="text-center">{{ $r['uom'] }}</td>
                    <td>{{ $r['company_code'] }}</td>
                    <td class="text-right num {{ $snapshotMode === '07' ? 'bold' : '' }}">{{ $fmtNum($r['stock_07']) }}</td>
                    <td class="text-right num {{ $snapshotMode === 'now' ? 'bold' : '' }}">{{ $fmtNum($r['current_stock']) }}</td>
                    <td class="text-right num">{{ $fmtNum($r['wip_active']) }}</td>
                    <td class="text-right num">{{ $fmtNum($r['std_day_1']) }}</td>
                    <td class="text-right num">{{ $fmtNum($r['std_day_2']) }}</td>
                    <td class="text-right num">{{ $fmtNum($r['std_day_3']) }}</td>
                    <td class="text-right num">{{ $fmtNum($activeRatio) }} Hr</td>
                    <td class="text-center">
                        <span class="badge {{ $badgeFor($activeStatus) }}">{{ $activeStatus }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="14" class="text-center muted" style="padding:14px;">
                        Tidak ada data item yang cocok dengan filter aktif.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>