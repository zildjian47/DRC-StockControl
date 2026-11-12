<x-app-layout>
    @php
        $reasons = $reasons ?? ['MATERIAL', 'PROSES', 'DIES/TOOLING', 'SETTING/TRIAL', 'HANDLING/OPERATOR'];
        $lotOptions = $activeLots->mapWithKeys(function ($l) {
            return [
                $l->id => [
                    'lot_number'  => $l->lot_number,
                    'part_no'     => $l->targetItem->part_no ?: '-',
                    'part_name'   => $l->targetItem->part_name ?? '-',
                    'uom'         => $l->targetItem->uom ?? 'PCS',
                    'actual_prod' => (float) $l->actual_qty_prod,
                    'qty_ng'      => (float) $l->qty_ng,
                    'act_fg'      => (float) $l->act_fg,
                ]
            ];
        });
    @endphp

    <div x-data="{
            openCreate: {{ $errors->any() ? 'true' : 'false' }},
            lots: {{ Js::from($lotOptions) }},
            selectedLotId: '{{ old('lot_tracer_id', '') }}',
            qtyNg: '',
            startDate: '{{ request('start_date') }}',
            endDate: '{{ request('end_date') }}',

            get currentLot() {
                return this.selectedLotId && this.lots[this.selectedLotId] ? this.lots[this.selectedLotId] : null;
            }
         }" class="space-y-[18px]">

        <div class="page-head">
            <x-page-header title="Form Production NG (QC Reject)"
                           subtitle="Pencatatan temuan barang cacat (NG) oleh QC (2 Jalur Input: Desktop & Mobile Scanner)">
                <x-slot name="actions">
                    <a href="{{ route('production_ngs.index', ['mode' => 'scanner']) }}" class="btn">
                        <x-icon name="scan" /> Buka Mobile Scanner
                    </a>
                    <button type="button" @click="openCreate = true" class="btn btn-primary">
                        <x-icon name="plus" /> Catat NG
                    </button>
                </x-slot>
            </x-page-header>
        </div>

        <x-flash />

        <div class="panel">
            <div class="panel-head">
                <h3 class="panel-title">Daftar Riwayat Production NG ({{ $ngs->count() }} Data)</h3>

                <form action="{{ route('production_ngs.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Cari No. Lot / Part No / Part Name..."
                           class="filter-input w-52">

                    <div class="flex items-center gap-1">
                        <span class="text-xs font-semibold text-slate-500">Tgl:</span>
                        <input type="date" name="start_date" x-model="startDate"
                               @change="if(endDate && endDate < startDate) endDate = startDate;"
                               class="filter-input num">
                        <span class="text-xs text-slate-400">-</span>
                        <input type="date" name="end_date" x-model="endDate" :min="startDate"
                               class="filter-input num">
                    </div>

                    <div class="flex items-center gap-1">
                        <span class="text-xs font-semibold text-slate-500">Jam:</span>
                        <input type="time" name="start_time" value="{{ request('start_time') }}"
                               class="filter-input num">
                        <span class="text-xs text-slate-400">-</span>
                        <input type="time" name="end_time" value="{{ request('end_time') }}"
                               class="filter-input num">
                    </div>

                    <select name="problem_reason" class="filter-input w-48">
                        <option value="">Semua Alasan NG</option>
                        @foreach ($reasons as $r)
                            <option value="{{ $r }}" @selected(request('problem_reason') === $r)>{{ $r }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th class="w-14 text-center">No</th>
                            <th class="text-left">Waktu Pencatatan</th>
                            <th>Petugas QC</th>
                            <th>No. Lot</th>
                            <th>Target Part (Output Item)</th>
                            <th class="num">Qty NG</th>
                            <th>Problem Reason (Alasan NG)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ngs as $ng)
                            <tr>
                                <td class="num text-center text-slate-500 font-semibold">{{ $loop->iteration }}</td>
                                <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">
                                    {{ \Carbon\Carbon::parse($ng->date_time)->format('d M Y, H:i') }}
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="font-semibold text-slate-900 text-xs">{{ $ng->user->name ?? 'QC' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $ng->user->role ?? 'STAFF' }}</div>
                                </td>
                                <td class="num text-left font-bold text-accent">{{ $ng->lot_number }}</td>
                                <td>
                                    <div class="num text-xs font-semibold text-accent">{{ $ng->item->part_no ?: '-' }}</div>
                                    <div class="font-semibold text-slate-900">{{ $ng->item->part_name ?? '-' }}</div>
                                </td>
                                <td class="num font-bold text-rose-700 whitespace-nowrap">
                                    -{{ floatval($ng->qty_ng) }} {{ $ng->item->uom ?? '' }}
                                </td>
                                <td>
                                    <span class="pill pill-crit">{{ $ng->problem_reason }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7"><div class="empty">Belum ada data temuan Production NG.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="openCreate" x-cloak class="modal-backdrop">
            <div class="panel w-full max-w-xl" @click.away="openCreate = false">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Catat Temuan Production NG (Desktop)</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Mengurangi saldo Act FG pada Lot Tracer dan memotong Current Stock gudang.</p>
                    </div>
                    <button type="button" @click="openCreate = false" class="act-btn" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('production_ngs.store') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div>
                            <label class="lbl">Pilih Nomor Lot &amp; Target Item (ON PROGRESS)</label>
                            <select name="lot_tracer_id" x-model="selectedLotId" required class="w-full h-10">
                                <option value="">-- Pilih Lot Berstatus ON PROGRESS --</option>
                                @foreach ($activeLots as $lot)
                                    <option value="{{ $lot->id }}">
                                        {{ $lot->lot_number }} — {{ $lot->targetItem->part_no ? $lot->targetItem->part_no . ' · ' : '' }}{{ $lot->targetItem->part_name }} (Act FG: {{ floatval($lot->act_fg) }} {{ $lot->targetItem->uom }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="currentLot" x-cloak class="p-3.5 rounded border border-slate-200 bg-slate-50 grid grid-cols-3 gap-2 text-xs">
                            <div>
                                <span class="text-slate-500 block">Actual Prod:</span>
                                <b class="num text-slate-900" x-text="(currentLot?.actual_prod ?? 0) + ' ' + (currentLot?.uom ?? '')"></b>
                            </div>
                            <div>
                                <span class="text-slate-500 block">NG Tercatat:</span>
                                <b class="num text-rose-700" x-text="(currentLot?.qty_ng ?? 0) + ' ' + (currentLot?.uom ?? '')"></b>
                            </div>
                            <div>
                                <span class="text-slate-500 block">Batas Maks Input (Act FG):</span>
                                <b class="num text-emerald-700" x-text="(currentLot?.act_fg ?? 0) + ' ' + (currentLot?.uom ?? '')"></b>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Qty NG</label>
                                <input type="number" step="0.01" min="0.01"
                                       :max="currentLot ? currentLot.act_fg : undefined"
                                       name="qty_ng" x-model="qtyNg" required
                                       placeholder="Contoh: 2"
                                       class="num w-full h-10 font-semibold">
                            </div>
                            <div>
                                <label class="lbl">Alasan NG (Problem Reason)</label>
                                <select name="problem_reason" required class="w-full h-10">
                                    <option value="">-- Pilih Alasan NG --</option>
                                    @foreach ($reasons as $r)
                                        <option value="{{ $r }}">{{ $r }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="panel-foot">
                        <button type="button" @click="openCreate = false" class="btn">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Data NG</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>