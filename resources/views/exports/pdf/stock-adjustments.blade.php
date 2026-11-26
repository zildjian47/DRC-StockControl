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
                <td>PT. Dian Raya Cipta · Berita Acara Penyesuaian Stock</td>
                <td style="text-align:right;"><span class="page-num"></span></td>
            </tr>
        </table>
    </div>

    <table class="header-tbl">
        <tr>
            <td>
                <div class="brand">PT. Dian Raya Cipta · DRC Stock Control</div>
                <div class="doc-title">{{ $title }}</div>
                <div class="meta-line">Dokumen resmi pencatatan penyesuaian saldo stok fisik gudang (Stock Opname / Loss / Surplus / Found / Repair).</div>
            </td>
            <td class="meta-right">
                <div><strong>Dicetak:</strong> {{ $exportedAt }}</div>
                <div><strong>Oleh:</strong> {{ $exportedBy }}</div>
                <div><strong>Jumlah Transaksi:</strong> {{ $records->count() }} data</div>
            </td>
        </tr>
    </table>

    <div class="filter-box">
        <strong>Filter Aktif:</strong> {{ $filterSummary }}
    </div>

    @php
        $fmtNum = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
    @endphp

    @if($byType->isNotEmpty())
        <div class="sec-title">Ringkasan per Kategori Penyesuaian</div>
        <table class="data-tbl" style="width:55%;">
            <thead>
                <tr>
                    <th style="width:45%;">Kategori</th>
                    <th class="text-right" style="width:25%;">Jumlah Data</th>
                    <th class="text-right" style="width:30%;">Netto Selisih (+/-)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($byType as $row)
                    <tr>
                        <td class="bold">{{ $row['type'] }}</td>
                        <td class="text-right num">{{ $row['count'] }}</td>
                        <td class="text-right num bold" style="color: {{ $row['net_diff'] >= 0 ? '#166534' : '#991B1B' }};">
                            {{ $row['net_diff'] > 0 ? '+' : '' }}{{ $fmtNum($row['net_diff']) }}
                        </td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td class="text-right num">{{ $records->count() }}</td>
                    <td class="text-right num">{{ $netDiffTotal > 0 ? '+' : '' }}{{ $fmtNum($netDiffTotal) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="sec-title">Rincian Penyesuaian Stock</div>
    <table class="data-tbl">
        <thead>
            <tr>
                <th class="text-center" style="width:3%;">No</th>
                <th class="text-center" style="width:11%;">Tanggal &amp; Jam</th>
                <th class="text-center" style="width:11%;">Kategori</th>
                <th style="width:12%;">Part No</th>
                <th style="width:19%;">Part Name</th>
                <th class="text-right" style="width:8%;">Sistem</th>
                <th class="text-right" style="width:8%;">Aktual</th>
                <th class="text-right" style="width:8%;">Selisih</th>
                <th class="text-center" style="width:5%;">UOM</th>
                <th style="width:15%;">Catatan &amp; Petugas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $idx => $r)
                @php
                    $diff = (float) $r->qty_diff;
                    $catCls = match ($r->adjustment_type) {
                        'LOSS'         => 'b-crit',
                        'SURPLUS', 'FOUND', 'REPAIR' => 'b-good',
                        'STOCK OPNAME' => 'b-info',
                        default        => 'b-neu',
                    };
                @endphp
                <tr>
                    <td class="text-center num">{{ $idx + 1 }}</td>
                    <td class="text-center num">{{ \Carbon\Carbon::parse($r->date_time)->format('Y-m-d H:i') }}</td>
                    <td class="text-center"><span class="badge {{ $catCls }}">{{ $r->adjustment_type }}</span></td>
                    <td class="num bold">{{ $r->item?->part_no ?: '-' }}</td>
                    <td>{{ $r->item?->part_name ?? '-' }}</td>
                    <td class="text-right num">{{ $fmtNum($r->qty_system) }}</td>
                    <td class="text-right num bold">{{ $fmtNum($r->qty_actual) }}</td>
                    <td class="text-right num bold" style="color: {{ $diff >= 0 ? '#166534' : '#991B1B' }};">
                        {{ $diff > 0 ? '+' : '' }}{{ $fmtNum($diff) }}
                    </td>
                    <td class="text-center">{{ $r->item?->uom ?? '-' }}</td>
                    <td>
                        <div>{{ $r->notes ?: '-' }}</div>
                        <div class="muted" style="font-size:7pt;">PPIC: {{ $r->user?->name ?? '-' }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center muted" style="padding:14px;">
                        Tidak ada data penyesuaian stok untuk filter yang dipilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- BLOK TANDA TANGAN BERITA ACARA --}}
    <table class="sign-tbl">
        <tr>
            <td>
                <div>Dibuat Oleh (PPIC),</div>
                <div class="sign-space"></div>
                <div class="sign-line"></div>
                <div class="bold">{{ $exportedBy }}</div>
            </td>
            <td>
                <div>Diperiksa Oleh (Leader / Gudang),</div>
                <div class="sign-space"></div>
                <div class="sign-line"></div>
                <div class="muted">( ...................................... )</div>
            </td>
            <td>
                <div>Disetujui Oleh (Manager),</div>
                <div class="sign-space"></div>
                <div class="sign-line"></div>
                <div class="muted">( ...................................... )</div>
            </td>
        </tr>
    </table>
</body>
</html>