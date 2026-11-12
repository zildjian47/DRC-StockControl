<x-app-layout>
    @php
        $types        = $types ?? ['RM', 'FG', 'FG/KOMPONEN', 'KOMPONEN', 'TRANSIT'];
        $statuses     = $statuses ?? ['EMPTY STOCK', 'VERY LOW', 'LOW STOCK', 'NORMAL STOCK', 'OVERSTOCK'];
        $companyTypes = $companyTypes ?? ['SUPPLIER', 'INTERNAL', 'CUSTOMER'];
    @endphp

    <div class="space-y-[18px]">
        <div class="page-head">
            <x-page-header title="Inventory Stock"
                           subtitle="Saldo stok gudang real-time, standar buffer 3 hari, Ratio Stock (Hari), dan 5 tingkatan Status Stock">
                <x-slot name="actions">
                    <a href="{{ route('stock_adjustments.index') }}" class="btn">
                        <x-icon name="sliders" /> Stock Adjustment
                    </a>
                    <a href="{{ route('items.index') }}" class="btn btn-primary">
                        <x-icon name="list" /> Master Item
                    </a>
                </x-slot>
            </x-page-header>
        </div>

        <x-flash />

        <div class="panel">
            <div class="panel-head">
                <h3 class="panel-title">Daftar Saldo Stok Gudang ({{ $stocks->count() }} Item)</h3>
                <form action="{{ route('inventory_stocks.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Cari Part No / Part Name..."
                           class="filter-input w-52">

                    <select name="type" class="filter-input w-40">
                        <option value="">Semua Tipe Item</option>
                        @foreach ($types as $t)
                            <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>

                    <select name="type_company" class="filter-input w-44">
                        <option value="">Semua Tipe Perusahaan</option>
                        @foreach ($companyTypes as $ct)
                            <option value="{{ $ct }}" @selected(request('type_company') === $ct)>{{ $ct }}</option>
                        @endforeach
                    </select>

                    <select name="status_stock" class="filter-input w-44">
                        <option value="">Semua Status Stock</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}" @selected(request('status_stock') === $st)>{{ $st }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th class="w-14 text-center">No</th>
                            <th>Part No &amp; Part Name</th>
                            <th class="text-center">Type Item</th>
                            <th>Perusahaan</th>
                            <th class="num">Std Day 1</th>
                            <th class="num">Std Day 2 (Min)</th>
                            <th class="num">Std Day 3 (Max)</th>
                            <th class="num">Ratio Stock</th>
                            <th class="num">Current Stock</th>
                            <th class="text-center">Status Stock</th>
                            <th class="w-16 text-center">Info</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stocks as $st)
                            @php
                                $item   = $st->item;
                                $std1   = $item ? $item->calculated_std_day_1 : 0;
                                $std2   = $item ? $item->std_day_2 : 0;
                                $std3   = $item ? $item->std_day_3 : 0;
                                $ratio  = $st->ratio_stock;
                                $status = $st->status_stock;
                                $pill   = match ($status) {
                                    'EMPTY STOCK'  => 'pill-empty',
                                    'VERY LOW'     => 'pill-crit',
                                    'LOW STOCK'    => 'pill-warn',
                                    'NORMAL STOCK' => 'pill-good',
                                    'OVERSTOCK'    => 'pill-info',
                                    default        => 'pill-neu',
                                };
                                $compType = $item && $item->company ? $item->company->type_company : 'INTERNAL';
                                $infoPayload = $item ? $item->material_info_payload : null;
                            @endphp
                            <tr>
                                <td class="num text-center text-xs font-semibold text-slate-500">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="num text-xs font-semibold text-accent">{{ $item->part_no ?: '-' }}</div>
                                    <div class="font-semibold text-slate-900">{{ $item->part_name ?? '-' }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="pill pill-neu">{{ $item->type ?? '-' }}</span>
                                </td>
                                <td>
                                    @if ($item && $item->company)
                                        <div class="num text-xs font-semibold text-slate-900">{{ $item->company->company_code }}</div>
                                        <span class="pill {{ $compType === 'SUPPLIER' ? 'pill-warn' : ($compType === 'INTERNAL' ? 'pill-info' : 'pill-good') }} mt-0.5">
                                            {{ $compType }}
                                        </span>
                                    @else
                                        <span class="pill pill-info">INTERNAL</span>
                                    @endif
                                </td>
                                <td class="num text-slate-700">{{ floatval($std1) }}</td>
                                <td class="num text-amber-700 font-semibold">{{ floatval($std2) }}</td>
                                <td class="num text-emerald-700 font-semibold">{{ floatval($std3) }}</td>
                                <td class="num font-bold text-accent whitespace-nowrap">
                                    {{ floatval($ratio) }} <span class="text-xs font-normal text-slate-500">Hari</span>
                                </td>
                                <td class="num font-bold text-slate-900 text-sm whitespace-nowrap">
                                    {{ floatval($st->current_stock) }}
                                    <span class="text-xs font-normal text-slate-500">{{ $item->uom ?? '' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="pill {{ $pill }}">{{ $status }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($infoPayload)
                                        <button type="button"
                                                @click="$dispatch('open-material-info', {{ Js::from($infoPayload) }})"
                                                class="act-btn" title="Informasi Material, BOM & Riwayat Stok">
                                            <x-icon name="info" />
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11"><div class="empty">Data stok gudang tidak ditemukan.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-material-info-modal />
    </div>
</x-app-layout>