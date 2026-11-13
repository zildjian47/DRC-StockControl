<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Production"
                       subtitle="Riwayat pencatatan hasil produksi per Nomor Lot (FG, FG/KOMPONEN, & KOMPONEN)">
            <x-slot name="actions">
                <a href="{{ route('productions.index', ['mode' => 'scanner']) }}" class="btn btn-primary">
                    <x-icon name="scan" /> Buka Mobile Scanner
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="productionPage({{ $productions->count() }})"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Transaksi Production</span>
                <span class="count-chip"><span x-text="visibleRows"></span> Data</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Lot / Part / Petugas Scan..."
                       class="h-8 text-xs px-2.5 w-48">

                <div class="flex items-center gap-1 text-xs text-slate-600">
                    <span>Tgl:</span>
                    <input type="date" x-model="startDate" @change="recalc()" class="h-8 text-xs px-2 w-32">
                    <span>-</span>
                    <input type="date" x-model="endDate" :min="startDate" @change="recalc()" class="h-8 text-xs px-2 w-32">
                </div>

                <div class="flex items-center gap-1 text-xs text-slate-600">
                    <span>Jam:</span>
                    <input type="time" x-model="startTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                    <span>-</span>
                    <input type="time" x-model="endTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                </div>

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe</option>
                    <option value="FG">FG</option>
                    <option value="FG/KOMPONEN">FG/KOMPONEN</option>
                    <option value="KOMPONEN">KOMPONEN</option>
                </select>

                <select x-model="fStatus" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Status</option>
                    <option value="TERCATAT">TERCATAT</option>
                    <option value="DIKOREKSI">DIKOREKSI</option>
                </select>

                <button type="button"
                        x-show="q || startDate || endDate || startTime || endTime || fType || fStatus"
                        x-cloak @click="resetFilter()" class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-12 text-center">No</th>
                        <th class="w-36 text-left col-time">Tanggal &amp; Waktu</th>
                        <th class="w-40">Petugas Scan</th>
                        <th class="w-32 text-center">No. Lot</th>
                        <th>Item Hasil Produksi</th>
                        <th class="w-32 text-center">Tipe</th>
                        <th class="num w-36">Qty Production</th>
                        <th class="w-28 text-center">Status</th>
                        <th class="w-16 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productions as $idx => $prod)
                        @php
                            $it = $prod->item;
                            $stLabel = $prod->qty_check === 'TIDAK SESUAI' ? 'DIKOREKSI' : 'TERCATAT';
                            $searchStr = strtolower(
                                ($prod->lot_number ?? '') . ' ' .
                                ($it->part_no ?? '') . ' ' .
                                ($it->part_name ?? '') . ' ' .
                                ($it->type ?? '') . ' ' .
                                ($prod->user->name ?? 'Operator')
                            );
                        @endphp
                        <tr data-row-item
                            data-search="{{ $searchStr }}"
                            data-date="{{ \Carbon\Carbon::parse($prod->date_time)->format('Y-m-d') }}"
                            data-time="{{ \Carbon\Carbon::parse($prod->date_time)->format('H:i') }}"
                            data-type="{{ $it->type ?? '' }}"
                            data-status="{{ $stLabel }}"
                            x-show="matchFilter($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">
                                {{ \Carbon\Carbon::parse($prod->date_time)->format('d M Y, H:i') }}
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $prod->user->name ?? 'Operator' }}</div>
                                <div class="text-[11px] text-slate-500 uppercase">{{ $prod->user->role ?? 'STAFF' }}</div>
                            </td>
                            <td class="text-center num font-bold text-accent">
                                {{ $prod->lot_number }}
                            </td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $it->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $it->part_name ?? '-' }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-neu">{{ $it->type ?? '-' }}</span>
                            </td>
                            <td class="num whitespace-nowrap">
                                <span class="font-bold text-emerald-700">+{{ floatval($prod->qty) }}</span>
                                <span class="text-xs text-slate-500 ml-1">{{ $it->uom ?? '' }}</span>
                            </td>
                            <td class="text-center">
                                @if($stLabel === 'DIKOREKSI')
                                    <span class="pill pill-info">DIKOREKSI</span>
                                @else
                                    <span class="pill pill-good">TERCATAT</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="act-btn" title="Koreksi Qty Production"
                                        @click="openEdit({{ $prod->id }}, '{{ $prod->lot_number }}', '{{ addslashes($it->part_name ?? '-') }}', '{{ $it->uom ?? '' }}', {{ floatval($prod->qty) }})">
                                    <x-icon name="edit" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty">Belum ada data transaksi Production.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL EDIT QTY PRODUCTION --}}
        <div x-show="editOpen" x-cloak
             class="modal-backdrop"
             @click.self="editOpen = false">
            <div class="panel w-full max-w-md overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Koreksi Qty Production</h3>
                    <button type="button" class="act-btn" @click="editOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>
                <form action="{{ route('productions.verify_batch') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-1">
                            <div>No. Lot: <b class="num text-accent" x-text="editLot"></b></div>
                            <div>Item Hasil Produksi: <b class="text-slate-900" x-text="editPartName"></b></div>
                            <div>Qty Tercatat Saat Ini: <b class="num text-slate-900" x-text="editOldQty + ' ' + editUom"></b></div>
                        </div>

                        <input type="hidden" :name="'items[' + editId + '][qty_check]'" value="TIDAK SESUAI">

                        <div>
                            <label class="lbl">Qty Production Sebenarnya (Kalkulator + - * /)</label>
                            <input type="text"
                                   :name="'items[' + editId + '][actual_qty]'"
                                   x-model="editNewQty"
                                   required
                                   class="num w-full h-9 text-xs font-semibold">
                            <div class="text-xs text-slate-500 mt-1" x-show="evalCalc(editNewQty) !== null">
                                Hasil Kalkulasi: <b class="num text-slate-900" x-text="evalCalc(editNewQty) + ' ' + editUom"></b>
                            </div>
                        </div>
                    </div>
                    <div class="panel-foot">
                        <button type="button" class="btn" @click="editOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Koreksi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function productionPage(initialRows) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fType: '',
                fStatus: '',
                editOpen: false,
                editId: null,
                editLot: '',
                editPartName: '',
                editUom: '',
                editOldQty: 0,
                editNewQty: '',
                visibleRows: initialRows,
                openEdit(id, lot, partName, uom, qty) {
                    this.editId = id;
                    this.editLot = lot;
                    this.editPartName = partName;
                    this.editUom = uom;
                    this.editOldQty = qty;
                    this.editNewQty = String(qty);
                    this.editOpen = true;
                },
                evalCalc(val) {
                    if (!val) return null;
                    const expr = String(val).replace(/,/g, '.').replace(/\s+/g, '');
                    if (!/^[0-9\.\+\-\*\/\(\)]+$/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r) && r > 0) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                matchFilter(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const d = el.dataset.date || '';
                    const t = el.dataset.time || '';
                    const tp = el.dataset.type || '';
                    const st = el.dataset.status || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.startDate && d < this.startDate) return false;
                    if (this.endDate && d > this.endDate) return false;
                    if (this.startTime && t < this.startTime) return false;
                    if (this.endTime && t > this.endTime) return false;
                    if (this.fType && tp !== this.fType) return false;
                    if (this.fStatus && st !== this.fStatus) return false;
                    return true;
                },
                recalc() {
                    if (this.startDate && this.endDate && this.endDate < this.startDate) {
                        this.endDate = this.startDate;
                    }
                    this.$nextTick(() => {
                        let cr = 0;
                        this.$el.querySelectorAll('tbody tr[data-row-item]').forEach(tr => {
                            if (this.matchFilter(tr)) cr++;
                        });
                        this.visibleRows = cr;
                    });
                },
                resetFilter() {
                    this.q = '';
                    this.startDate = '';
                    this.endDate = '';
                    this.startTime = '';
                    this.endTime = '';
                    this.fType = '';
                    this.fStatus = '';
                    this.recalc();
                }
            };
        }
    </script>
</x-app-layout>