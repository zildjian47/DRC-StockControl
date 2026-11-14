<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Inbound"
                       subtitle="Riwayat penerimaan barang masuk dari Supplier (RM, KOMPONEN, TRANSIT)">
            <x-slot name="actions">
                <a href="{{ route('inbounds.index', ['mode' => 'scanner']) }}" class="btn btn-primary">
                    <x-icon name="scan" /> Buka Mobile Scanner
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="inboundPage({{ $inbounds->count() }})"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Transaksi Inbound</span>
                <span class="count-chip"><span x-text="visibleRows"></span><span>Data</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Part / Supplier / Petugas..."
                       class="h-8 text-xs px-2.5 w-48">

                <div class="flex items-center gap-1 text-xs text-slate-600">
                    <span>Tgl:</span>
                    <input type="date" x-model="startDate"
                           @change="if(endDate && endDate < startDate) endDate = startDate; recalc()"
                           class="h-8 text-xs px-2 w-32">
                    <span>-</span>
                    <input type="date" x-model="endDate" :min="startDate" @change="recalc()"
                           class="h-8 text-xs px-2 w-32">
                </div>

                <div class="flex items-center gap-1 text-xs text-slate-600">
                    <span>Jam:</span>
                    <input type="time" x-model="startTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                    <span>-</span>
                    <input type="time" x-model="endTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                </div>

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe Item</option>
                    <option value="RM">RM</option>
                    <option value="KOMPONEN">KOMPONEN</option>
                    <option value="TRANSIT">TRANSIT</option>
                </select>

                <select x-model="fStatus" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Status</option>
                    <option value="TERCATAT">TERCATAT</option>
                    <option value="DIKOREKSI">DIKOREKSI</option>
                </select>

                <button type="button"
                        x-show="q || startDate || endDate || startTime || endTime || fType || fStatus"
                        x-cloak
                        @click="resetFilter()"
                        class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[5%] text-center">No</th>
                        <th class="w-[14%] text-left col-time">Tanggal &amp; Waktu</th>
                        <th class="w-[14%]">Petugas Scan</th>
                        <th class="w-[25%]">Part No &amp; Part Name</th>
                        <th class="w-[10%] text-center">Tipe</th>
                        <th class="w-[10%] text-center">Supplier</th>
                        <th class="num w-[10%]">Qty In</th>
                        <th class="w-[7%] text-center">Status</th>
                        <th class="w-[5%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inbounds as $idx => $inb)
                        @php
                            $dt = \Carbon\Carbon::parse($inb->date_time);
                            $stLabel = $inb->qty_check === 'TIDAK SESUAI' ? 'DIKOREKSI' : 'TERCATAT';
                            $searchStr = strtolower(
                                ($inb->item->part_no ?? '') . ' ' .
                                ($inb->item->part_name ?? '') . ' ' .
                                ($inb->item->type ?? '') . ' ' .
                                ($inb->item->company->company_code ?? '') . ' ' .
                                ($inb->user->name ?? 'Operator')
                            );
                        @endphp
                        <tr data-inb-row
                            data-search="{{ $searchStr }}"
                            data-date="{{ $dt->format('Y-m-d') }}"
                            data-time="{{ $dt->format('H:i') }}"
                            data-type="{{ $inb->item->type ?? '' }}"
                            data-status="{{ $stLabel }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">{{ $dt->format('d M Y, H:i') }}</td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $inb->user->name ?? 'Operator' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $inb->user->role ?? 'STAFF' }}</div>
                            </td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $inb->item->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $inb->item->part_name ?? '-' }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-neu">{{ $inb->item->type ?? '-' }}</span>
                            </td>
                            <td class="text-center">
                                <span class="num font-bold text-slate-700 text-xs">{{ $inb->item->company->company_code ?? '-' }}</span>
                            </td>
                            <td class="num whitespace-nowrap">
                                <div class="inline-flex items-baseline justify-end gap-1.5 leading-none">
                                    <span class="num text-sm font-bold text-emerald-700">+{{ floatval($inb->qty) }}</span>
                                    <span class="text-xs font-medium text-slate-500">{{ $inb->item->uom ?? '' }}</span>
                                </div>
                            </td>
                            <td class="text-center">
                                @if($stLabel === 'DIKOREKSI')
                                    <span class="pill pill-info">DIKOREKSI</span>
                                @else
                                    <span class="pill pill-good">TERCATAT</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="act-btn" title="Koreksi Qty Inbound"
                                        @click="openEdit({{ $inb->id }}, '{{ addslashes($inb->item->part_name ?? '-') }}', '{{ floatval($inb->qty) }}', '{{ $inb->item->uom ?? '' }}')">
                                    <x-icon name="edit" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty">Belum ada data transaksi Inbound.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL KOREKSI QTY INBOUND --}}
        <div x-show="editOpen" x-cloak class="modal-backdrop" @click.self="editOpen = false">
            <div class="panel w-full max-w-md overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Koreksi Qty Inbound</h3>
                    <button type="button" class="act-btn" @click="editOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>
                <form action="{{ route('inbounds.verify_batch') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-1">
                            <div class="font-bold text-slate-900" x-text="editPart"></div>
                            <div class="text-slate-500">Qty Tercatat Saat Ini: <b class="num text-slate-800" x-text="editOldQty + ' ' + editUom"></b></div>
                        </div>
                        <div>
                            <label class="lbl">Qty Inbound Sebenarnya (Mendukung Kalkulator)</label>
                            <input type="text"
                                   :name="'items[' + editId + '][actual_qty]'"
                                   x-model="editNewQty"
                                   required
                                   class="num w-full h-10 text-sm font-semibold"
                                   placeholder="Contoh: 100 atau 50*2">
                            <p class="hint">Selisih koreksi otomatis menyesuaikan Current Stock barang ini di gudang.</p>
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
        function inboundPage(initialCount) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fType: '',
                fStatus: '',
                visibleRows: initialCount,
                editOpen: false,
                editId: null,
                editPart: '',
                editOldQty: '',
                editNewQty: '',
                editUom: '',

                openEdit(id, part, qty, uom) {
                    this.editId = id;
                    this.editPart = part;
                    this.editOldQty = qty;
                    this.editNewQty = qty;
                    this.editUom = uom;
                    this.editOpen = true;
                },
                matchRow(el) {
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
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-inb-row]').forEach(tr => {
                            if (this.matchRow(tr)) c++;
                        });
                        this.visibleRows = c;
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