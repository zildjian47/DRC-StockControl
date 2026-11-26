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
                <td>PT. Dian Raya Cipta · Laporan Delay Delivery</td>
                <td style="text-align:right;"><span class="page-num"></span></td>
            </tr>
        </table>
    </div>

    <table class="header-tbl">
        <tr>
            <td>
                <div class="brand">PT. Dian Raya Cipta · DRC Stock Control</div>
                <div class="doc-title">{{ $title }}</div>
                <div class="meta-line">Evaluasi kejadian keterlambatan pengiriman barang ke Customer beserta distribusi akar masalah.</div>
            </td>
            <td class="meta-right">
                <div><strong>Dicetak:</strong> {{ $exportedAt }}</div>
                <div><strong>Oleh:</strong> {{ $exportedBy }}</div>
                <div><strong>Total Kejadian:</strong> {{ $totalCases }} kasus</div>
            </td>
        </tr>
    </table>

    <div class="filter-box">
        <strong>Filter Aktif:</strong> {{ $filterSummary }}
    </div>

    @php
        $fmtNum = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
    @endphp

    @if($rekapReason->isNotEmpty())
        <div class="sec-title">Rekapitulasi Penyebab Delay</div>
        <table class="data-tbl" style="width:55%;">
            <thead>
                <tr>
                    <th class="text-center" style="width:10%;">No</th>
                    <th style="width:48%;">Penyebab Delay</th>
                    <th class="text-right" style="width:20%;">Jumlah Kasus</th>
                    <th class="text-right" style="width:22%;">Persentase</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rekapReason as $idx => $rk)
                    <tr>
                        <td class="text-center num">{{ $idx + 1 }}</td>
                        <td class="bold">{{ $rk['reason'] }}</td>
                        <td class="text-right num bold">{{ $rk['cases'] }}</td>
                        <td class="text-right num">{{ $fmtNum($rk['pct']) }}%</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td></td>
                    <td>TOTAL</td>
                    <td class="text-right num">{{ $totalCases }}</td>
                    <td class="text-right num">{{ $totalCases > 0 ? '100%' : '0%' }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="sec-title">Rincian Kejadian Delay Delivery</div>
    <table class="data-tbl">
        <thead>
            <tr>
                <th class="text-center" style="width:4%;">No</th>
                <th class="text-center" style="width:11%;">Tgl Kejadian</th>
                <th style="width:10%;">Customer</th>
                <th style="width:17%;">Nama Customer</th>
                <th style="width:12%;">Part No</th>
                <th style="width:21%;">Part Name</th>
                <th class="text-center" style="width:13%;">Penyebab</th>
                <th style="width:12%;">Dicatat Oleh</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $idx => $d)
                @php
                    $incDt = $d->incident_date ? \Carbon\Carbon::parse($d->incident_date) : \Carbon\Carbon::parse($d->date_time);
                @endphp
                <tr>
                    <td class="text-center num">{{ $idx + 1 }}</td>
                    <td class="text-center num bold">{{ $incDt->format('Y-m-d') }}</td>
                    <td class="num bold">{{ $d->company?->company_code ?? '-' }}</td>
                    <td>{{ $d->company?->company_name ?? '-' }}</td>
                    <td class="num bold">{{ $d->item?->part_no ?: '-' }}</td>
                    <td>{{ $d->item?->part_name ?? '-' }}</td>
                    <td class="text-center"><span class="badge b-warn">{{ $d->problem_reason ?: '-' }}</span></td>
                    <td>{{ $d->user?->name ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center muted" style="padding:14px;">
                        Tidak ada catatan keterlambatan pengiriman untuk filter yang dipilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>