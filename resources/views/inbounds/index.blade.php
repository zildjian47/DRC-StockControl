<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Inbound"
                       subtitle="Riwayat penerimaan barang dari Supplier (RM, KOMPONEN, TRANSIT) beserta koreksi aktual PPIC">
            <x-slot name="actions">
                <a href="{{ route('inbounds.index', ['mode' => 'scanner']) }}" class="btn btn-primary">
                    <x-icon name="scan" />
                    <span>Scan</span>
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
                <span class="count-chip"><span x-text="visibleCount"></span><span>DATA</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Part / Petugas Scan..."
                       class="h-8 text-xs px-2.5 w-48">

                <div class="inline-flex items-center gap-1.5">
                    <span class="text-[11px] font-semibold text-slate-500">Tgl</span>
                    <input type="date" x-model="startDate"
                           @change="if(endDate && endDate < startDate) endDate = startDate; recalc()"
                           class="h-8 text-xs px-2 w-32">
                    <span class="text-slate-400">–</span>
                    <input type="date" x-model="endDate" :min="startDate" @change="recalc()"
                           class="h-8 text-xs px-2 w-32">
                </div>

                <div class="inline-flex items-center gap-1.5">
                    <span class="text-[11px] font-semibold text-slate-500">Jam</span>
                    <input type="time" x-model="startTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                    <span class="text-slate-400">–</span>
                    <input type="time" x-model="endTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                </div>

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe</option>
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
                        x-show="q || startDate || endDate || startTime || endTime || fType || fStatus" x-cloak
                        @click="resetFilter()" class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[5%] text-center">No</th>
                        <th class="w-[15%] col-time">Tanggal &amp; Waktu</th>
                        <th class="w-[14%]">Petugas Scan</th>
                        <th class="w-[24%]">Part No &amp; Part Name</th>
                        <th class="w-[9%] text-center">Tipe</th>
                        <th class="w-[11%]">Supplier</th>
                        <th class="num w-[10%]">Qty In</th>
                        <th class="w-[7%] text-center">Status</th>
                        <th class="w-[5%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inbounds as $idx => $inb)
                        @php
                            $dt         = \Carbon\Carbon::parse($inb->date_time);
                            $statusText = $inb->qty_check === 'TIDAK SESUAI' ? 'DIKOREKSI' : 'TERCATAT';
                            $uName      = $inb->user->name ?? 'Operator';
                            $partNo     = $inb->item->part_no ?: '-';
                            $partName   = $inb->item->part_name ?? '-';
                            $itemType   = $inb->item->type ?? '-';
                            $uom        = $inb->item->uom ?? '';
                            $compCode   = $inb->item->company->company_code ?? '-';
                            $tyCls      = 'ty-' . ($itemType === 'FG/KOMPONEN' ? 'FGK' : $itemType);
                        @endphp
                        <tr data-row
                            data-search="{{ strtolower($partNo . ' ' . $partName . ' ' . $uName . ' ' . $compCode) }}"
                            data-date="{{ $dt->format('Y-m-d') }}"
                            data-time="{{ $dt->format('H:i') }}"
                            data-type="{{ $itemType }}"
                            data-status="{{ $statusText }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num col-time text-left whitespace-nowrap">
                                <div class="font-semibold text-slate-900">{{ $dt->format('d M Y') }}</div>
                                <div class="text-xs text-slate-500">{{ $dt->format('H:i') }} WIB</div>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900">{{ $uName }}</div>
                                <div class="text-[11px] text-slate-500">{{ $inb->user->role ?? 'STAFF' }}</div>
                            </td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $partNo }}</div>
                                <div class="font-semibold text-slate-900">{{ $partName }}</div>
                            </td>
                            <td class="text-center">
                                <span class="ty {{ $tyCls }}">{{ $itemType }}</span>
                            </td>
                            <td>
                                <div class="font-bold text-xs text-accent">{{ $compCode }}</div>
                                <span class="pill pill-info mt-0.5">SUPPLIER</span>
                            </td>
                            <td class="num whitespace-nowrap">
                                <span class="text-[13.5px] font-bold text-emerald-700">+{{ floatval($inb->qty) }}</span>
                                <span class="text-[11px] text-slate-500">{{ $uom }}</span>
                            </td>
                            <td class="text-center">
                                @if($statusText === 'DIKOREKSI')
                                    <span class="pill pill-info">DIKOREKSI</span>
                                @else
                                    <span class="pill pill-good">TERCATAT</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="act-btn" title="Koreksi Qty Inbound"
                                        @click="openEdit({{ $inb->id }}, '{{ addslashes($partNo) }}', '{{ addslashes($partName) }}', '{{ $itemType }}', {{ floatval($inb->qty) }}, '{{ $uom }}')">
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

        {{-- POP-UP MODAL KOREKSI QTY INBOUND (2 Panel Abu) --}}
        <div x-show="editOpen" x-cloak class="modal-backdrop" @click.self="editOpen = false" @keydown.escape.window="editOpen = false">
            <div class="panel dlg w-full max-w-2xl" role="dialog" aria-label="Koreksi Qty Inbound">
                <div class="panel-head">
                    <h3 class="panel-title">Koreksi Qty Inbound</h3>
                    <button type="button" class="act-btn" @click="editOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('inbounds.verify_batch') }}" method="POST" class="flex flex-col min-h-0 flex-1">
                    @csrf
                    <div class="dlg-split">
                        <aside class="dlg-side">
                            <div class="dlg-k">Transaksi dipilih</div>
                            <div class="sumc">
                                <div class="dlg-code num" x-text="editPartNo"></div>
                                <div class="dlg-name" x-text="editPartName"></div>
                                <div class="mt-1">
                                    <span class="ty" :class="'ty-' + (editType === 'FG/KOMPONEN' ? 'FGK' : editType)" x-text="editType"></span>
                                </div>
                            </div>
                            <div class="dlg-list">
                                <div><span>Qty Tercatat</span><b class="num" x-text="editOldQty + ' ' + editUom"></b></div>
                                <div><span>Qty Koreksi</span><b class="num text-accent" x-text="(evalCalc(editNewQty) ?? editOldQty) + ' ' + editUom"></b></div>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <input type="hidden" :name="'items[' + editId + '][qty_check]'" value="TIDAK SESUAI">
                            <div>
                                <label class="lbl" for="inb_actual_qty">Qty Inbound Sebenarnya</label>
                                <div class="inp-calc">
                                    <input id="inb_actual_qty" type="text"
                                           :name="'items[' + editId + '][actual_qty]'"
                                           x-model="editNewQty" required
                                           placeholder="Contoh: 120 atau 3*40"
                                           class="num w-full h-9 font-semibold">
                                    <span class="inp-calc-ic" title="Mendukung perhitungan kalkulator (+, -, *, /)">
                                        <x-icon name="calc" />
                                    </span>
                                </div>
                                <div class="hasil mt-1" x-show="evalCalc(editNewQty) !== null">
                                    Hasil: <b x-text="evalCalc(editNewQty) + ' ' + editUom"></b>
                                </div>
                            </div>
                            <div class="alert alert-info text-xs leading-relaxed">
                                Menyimpan koreksi akan otomatis menyesuaikan <b>Current Stock</b> di gudang dan menandai status transaksi menjadi <b>DIKOREKSI</b>.
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
        function inboundPage(initialCount) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fType: '',
                fStatus: '',
                visibleCount: initialCount,
                editOpen: false,
                editId: null,
                editPartNo: '',
                editPartName: '',
                editType: 'RM',
                editOldQty: 0,
                editNewQty: '',
                editUom: '',

                openEdit(id, partNo, partName, type, qty, uom) {
                    this.editId = id;
                    this.editPartNo = partNo;
                    this.editPartName = partName;
                    this.editType = type;
                    this.editOldQty = qty;
                    this.editNewQty = String(qty);
                    this.editUom = uom;
                    this.editOpen = true;
                },
                evalCalc(raw) {
                    if (raw === null || raw === undefined) return null;
                    const expr = String(raw).replace(/,/g, '.').replace(/\s+/g, '');
                    if (!expr || !/^[0-9.+\-*\/()]+$/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r) && r > 0) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
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
                },
                matchRow(el) {
                    const s = el.dataset.search || '';
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
                        this.$el.querySelectorAll('tbody tr[data-row]').forEach(tr => {
                            if (this.matchRow(tr)) c++;
                        });
                        this.visibleCount = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>