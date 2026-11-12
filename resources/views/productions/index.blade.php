<x-app-layout>
    <div x-data="{
            startDate: '{{ request('start_date') }}',
            endDate: '{{ request('end_date') }}',
            openEdit: false,
            editId: null,
            editLot: '',
            editPartNo: '',
            editPartName: '',
            editUom: '',
            editOldQty: 0,
            editActualQty: '',

            openEditModal(row) {
                this.editId = row.id;
                this.editLot = row.lot_number;
                this.editPartNo = row.part_no;
                this.editPartName = row.part_name;
                this.editUom = row.uom;
                this.editOldQty = row.qty;
                this.editActualQty = String(row.qty);
                this.openEdit = true;
            }
         }" class="space-y-[18px]">

        <div class="page-head">
            <x-page-header title="Production"
                           subtitle="Riwayat hasil produksi aktual per Nomor Lot (FG, FG/KOMPONEN, dan KOMPONEN)">
                <x-slot name="actions">
                    <a href="{{ route('productions.index', ['mode' => 'scanner']) }}" class="btn btn-primary">
                        <x-icon name="scan" /> Buka Mobile Scanner
                    </a>
                </x-slot>
            </x-page-header>
        </div>

        <x-flash />

        <div class="panel">
            <div class="panel-head">
                <h3 class="panel-title">Daftar Transaksi Production ({{ $productions->count() }} Data)</h3>

                <form action="{{ route('productions.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Cari Lot / Part / Petugas Scan..."
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

                    <select name="type" class="filter-input w-40">
                        <option value="">Semua Tipe</option>
                        <option value="FG" @selected(request('type') === 'FG')>FG</option>
                        <option value="FG/KOMPONEN" @selected(request('type') === 'FG/KOMPONEN')>FG/KOMPONEN</option>
                        <option value="KOMPONEN" @selected(request('type') === 'KOMPONEN')>KOMPONEN</option>
                    </select>

                    <select name="qty_check" class="filter-input w-36">
                        <option value="">Semua Status</option>
                        <option value="TERCATAT" @selected(request('qty_check') === 'TERCATAT')>TERCATAT</option>
                        <option value="DIKOREKSI" @selected(request('qty_check') === 'DIKOREKSI')>DIKOREKSI</option>
                    </select>
                </form>
            </div>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th class="w-14 text-center">No</th>
                            <th class="text-left">Tanggal &amp; Waktu</th>
                            <th>Petugas Scan</th>
                            <th class="text-center">No. Lot</th>
                            <th>Item Hasil Produksi</th>
                            <th class="text-center">Tipe</th>
                            <th class="num">Qty Production</th>
                            <th class="text-center">Status</th>
                            <th class="w-20 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($productions as $prod)
                            @php
                                $dt = \Carbon\Carbon::parse($prod->date_time);
                                $isEdited = $prod->qty_check === 'TIDAK SESUAI';
                                $statusLabel = $isEdited ? 'DIKOREKSI' : 'TERCATAT';
                            @endphp
                            <tr data-date="{{ $dt->format('Y-m-d') }}"
                                data-time="{{ $dt->format('H:i') }}"
                                data-type="{{ $prod->item->type ?? '' }}"
                                data-status="{{ $statusLabel }}">
                                <td class="num text-center text-slate-500 font-semibold">{{ $loop->iteration }}</td>
                                <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">
                                    {{ $dt->format('d M Y, H:i') }}
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="font-semibold text-slate-900 text-xs">{{ $prod->user->name ?? 'Operator' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $prod->user->role ?? 'STAFF' }}</div>
                                </td>
                                <td class="num text-center font-bold text-accent whitespace-nowrap">
                                    {{ $prod->lot_number }}
                                </td>
                                <td>
                                    <div class="num text-xs font-semibold text-accent">{{ $prod->item->part_no ?: '-' }}</div>
                                    <div class="font-semibold text-slate-900">{{ $prod->item->part_name ?? '-' }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="pill pill-neu">{{ $prod->item->type ?? '-' }}</span>
                                </td>
                                <td class="num whitespace-nowrap font-bold text-emerald-700">
                                    +{{ floatval($prod->qty) }}
                                    <span class="text-xs font-normal text-slate-500">{{ $prod->item->uom ?? '' }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($isEdited)
                                        <span class="pill pill-info">DIKOREKSI</span>
                                    @else
                                        <span class="pill pill-good">TERCATAT</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <button type="button"
                                            @click="openEditModal({
                                                id: {{ $prod->id }},
                                                lot_number: '{{ addslashes($prod->lot_number) }}',
                                                part_no: '{{ addslashes($prod->item->part_no ?: '-') }}',
                                                part_name: '{{ addslashes($prod->item->part_name ?? '-') }}',
                                                uom: '{{ addslashes($prod->item->uom ?? '') }}',
                                                qty: {{ (float) $prod->qty }}
                                            })"
                                            class="act-btn" title="Edit / Koreksi Qty Production">
                                        <x-icon name="edit" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9"><div class="empty">Belum ada transaksi Production yang tercatat.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- POP-UP MODAL EDIT / KOREKSI QTY PRODUCTION --}}
        <div x-show="openEdit" x-cloak class="modal-backdrop">
            <div class="panel w-full max-w-md" @click.away="openEdit = false">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Koreksi Qty Production</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Mendukung koreksi over-yield (melebihi estimasi) tanpa membuat WIP minus.</p>
                    </div>
                    <button type="button" @click="openEdit = false" class="act-btn" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('productions.verify_batch') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div class="p-3 rounded border border-slate-200 bg-slate-50">
                            <div class="num text-xs font-bold text-accent" x-text="'Lot: ' + editLot + ' · ' + editPartNo"></div>
                            <div class="font-semibold text-slate-900 text-sm mt-0.5" x-text="editPartName"></div>
                            <div class="text-xs text-slate-500 mt-1">
                                Qty Production Saat Ini: <b class="num text-slate-800" x-text="editOldQty + ' ' + editUom"></b>
                            </div>
                        </div>

                        <input type="hidden" :name="'items[' + editId + '][qty_check]'" value="TIDAK SESUAI">

                        <div>
                            <label class="lbl">Qty Production Sebenarnya (`+ - * /`)</label>
                            <input type="text"
                                   :name="'items[' + editId + '][actual_qty]'"
                                   x-model="editActualQty"
                                   oninput="this.value = this.value.replace(/,/g, '.')"
                                   required
                                   placeholder="Contoh: 102"
                                   class="num w-full h-10 font-semibold">
                        </div>
                    </div>
                    <div class="panel-foot">
                        <button type="button" @click="openEdit = false" class="btn">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Koreksi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>