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
                <td>PT. Dian Raya Cipta · Laporan Form NG / Reject QC</td>
                <td style="text-align:right;"><span class="page-num"></span></td>
            </tr>
        </table>
    </div>

    <table class="header-tbl">
        <tr>
            <td>
                <div class="brand">PT. Dian Raya Cipta · DRC Stock Control</div>
                <div class="doc-title">{{ $title }}</div>
                <div class="meta-line">Rekapitulasi temuan cacat produksi / reject QC beserta analisis penyebab utama.</div>
            </td>
            <td class="meta-right">
                <div><strong>Dicetak:</strong> {{ $exportedAt }}</div>
                <div><strong>Oleh:</strong> {{ $exportedBy }}</div>
                <div><strong>Total Kasus:</strong> {{ $records->count() }} kasus</div>
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
        <div class="sec-title">Rekapitulasi Berdasarkan Penyebab NG</div>
        <table class="data-tbl" style="width:60%;">
            <thead>
                <tr>
                    <th class="text-center" style="width:8%;">No</th>
                    <th style="width:42%;">Penyebab NG</th>
                    <th class="text-right" style="width:16%;">Kasus</th>
                    <th class="text-right" style="width:18%;">Total Qty NG</th>
                    <th class="text-right" style="width:16%;">Kontribusi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rekapReason as $idx => $rk)
                    <tr>
                        <td class="text-center num">{{ $idx + 1 }}</td>
                        <td class="bold">{{ $rk['reason'] }}</td>
                        <td class="text-right num">{{ $rk['cases'] }}</td>
                        <td class="text-right num bold" style="color:#991B1B;">{{ $fmtNum($rk['qty_ng']) }}</td>
                        <td class="text-right num">{{ $fmtNum($rk['pct']) }}%</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td></td>
                    <td>TOTAL</td>
                    <td class="text-right num">{{ $records->count() }}</td>
                    <td class="text-right num">{{ $fmtNum($totalNgQty) }}</td>
                    <td class="text-right num">{{ $totalNgQty > 0 ? '100%' : '0%' }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="sec-title">Rincian Kejadian Form NG</div>
    <table class="data-tbl">
        <thead>
            <tr>
                <th class="text-center" style="width:3%;">No</th>
                <th class="text-center" style="width:11%;">Tanggal &amp; Jam</th>
                <th class="text-center" style="width:8%;">Lot</th>
                <th style="width:12%;">Part No</th>
                <th style="width:20%;">Part Name</th>
                <th class="text-right" style="width:7%;">Qty NG</th>
                <th class="text-center" style="width:5%;">UOM</th>
                <th class="text-center" style="width:11%;">Penyebab</th>
                <th style="width:11%;">Petugas</th>
                <th style="width:12%;">Audit Koreksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $idx => $r)
                <tr>
                    <td class="text-center num">{{ $idx + 1 }}</td>
                    <td class="text-center num">{{ \Carbon\Carbon::parse($r->date_time)->format('Y-m-d H:i') }}</td>
                    <td class="text-center num bold">{{ $r->lot_number }}</td>
                    <td class="num bold">{{ $r->item?->part_no ?: '-' }}</td>
                    <td>{{ $r->item?->part_name ?? '-' }}</td>
                    <td class="text-right num bold" style="color:#991B1B;">{{ $fmtNum($r->qty_ng) }}</td>
                    <td class="text-center">{{ $r->item?->uom ?? '-' }}</td>
                    <td class="text-center"><span class="badge b-crit">{{ $r->problem_reason ?: '-' }}</span></td>
                    <td>{{ $r->user?->name ?? '-' }}</td>
                    <td>
                        @if($r->qty_check === 'DIKOREKSI')
                            <span class="badge b-info">DIKOREKSI</span>
                            @if($r->qty_original !== null)
                                <div class="muted" style="font-size:6.8pt;">Awal: {{ $fmtNum($r->qty_original) }}</div>
                            @endif
                            @if($r->correction_notes)
                                <div class="muted" style="font-size:6.8pt;">{{ $r->correction_notes }}</div>
                            @endif
                        @else
                            <span class="muted">{{ $r->qty_check ?: 'TERCATAT' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center muted" style="padding:14px;">
                        Tidak ada data Form NG untuk filter yang dipilih.
                    </td>
                </tr>
            @endforelse
            @if($records->isNotEmpty())
                <tr class="total-row">
                    <td></td>
                    <td colspan="4">TOTAL QTY NG ({{ $records->count() }} KASUS)</td>
                    <td class="text-right num">{{ $fmtNum($totalNgQty) }}</td>
                    <td colspan="4"></td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>