<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Production"
                       subtitle="Riwayat hasil produksi per Nomor Lot (FG, FG/KOMPONEN, KOMPONEN) beserta sinkronisasi WIP Lot Tracer">
            <x-slot name="actions">
                <a href="{{ route('productions.index', ['mode' => 'scanner']) }}" class="btn btn-primary">
                    <x-icon name="scan" />
                    <span>Scan</span>
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
                <span class="count-chip"><span x-text="visibleCount"></span><span>DATA</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Lot / Item / Petugas Scan..."
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
                        <th class="w-[12%] text-center">No. Lot</th>
                        <th class="w-[24%]">Item Hasil Produksi</th>
                        <th class="w-[9%] text-center">Tipe</th>
                        <th class="num w-[9%]">Qty Production</th>
                        <th class="w-[7%] text-center">Status</th>
                        <th class="w-[5%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productions as $idx => $prod)
                        @php
                            $dt            = \Carbon\Carbon::parse($prod->date_time);
                            $statusText    = $prod->qty_check === 'DIKOREKSI' ? 'DIKOREKSI' : 'TERCATAT';
                            $uName         = $prod->user->name ?? 'Operator';
                            $partNo        = $prod->item->part_no ?: '-';
                            $partName      = $prod->item->part_name ?? '-';
                            $itemType      = $prod->item->type ?? '-';
                            $uom           = $prod->item->uom ?? '';
                            $tyCls         = 'ty-' . ($itemType === 'FG/KOMPONEN' ? 'FGK' : $itemType);
                            $currStock     = floatval($stockMap[$prod->item_id] ?? 0);
                            $lotRow        = $lotMap[$prod->lot_number . '|' . $prod->item_id] ?? null;
                            $lotActualProd = $lotRow ? floatval($lotRow->actual_qty_prod) : floatval($prod->qty);
                            $lotQtyNg      = $lotRow ? floatval($lotRow->qty_ng) : 0;
                            $lotIsFinish   = $lotRow && $lotRow->status_lot === 'FINISH';
                        @endphp
                        <tr data-row
                            data-search="{{ strtolower($prod->lot_number . ' ' . $partNo . ' ' . $partName . ' ' . $uName) }}"
                            data-date="{{ $dt->format('Y-m-d') }}"
                            data-time="{{ $dt->format('H:i') }}"
                            data-type="{{ $itemType }}"
                            data-status="{{ $statusText }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500" data-col-no>{{ $idx + 1 }}</td>
                            <td class="num col-time text-left whitespace-nowrap">
                                <div class="font-semibold text-slate-900">{{ $dt->format('d M Y') }}</div>
                                <div class="text-xs text-slate-500">{{ $dt->format('H:i') }} WIB</div>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900">{{ $uName }}</div>
                                <div class="text-[11px] text-slate-500">{{ $prod->user->role ?? 'STAFF' }}</div>
                            </td>
                            <td class="text-center num font-bold text-accent">{{ $prod->lot_number }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $partNo }}</div>
                                <div class="font-semibold text-slate-900">{{ $partName }}</div>
                            </td>
                            <td class="text-center">
                                <span class="ty {{ $tyCls }}">{{ $itemType }}</span>
                            </td>
                            <td class="num whitespace-nowrap">
                                <span class="text-[13.5px] font-bold text-emerald-700">+{{ floatval($prod->qty) }}</span>
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
                                <button type="button" class="act-btn" title="Koreksi Qty Production"
                                        @click="openEdit({{ $prod->id }}, {{ Js::from($prod->lot_number) }}, {{ Js::from($partNo) }}, {{ Js::from($partName) }}, {{ Js::from($itemType) }}, {{ floatval($prod->qty) }}, {{ Js::from($uom) }}, {{ $currStock }}, {{ $lotActualProd }}, {{ $lotQtyNg }}, {{ Js::from($lotIsFinish) }})">
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

        {{-- POP-UP MODAL KOREKSI QTY PRODUCTION (2 Panel Abu) --}}
        <div x-show="editOpen" x-cloak class="modal-backdrop" @click.self="editOpen = false" @keydown.escape.window="editOpen = false">
            <div class="panel dlg w-full max-w-2xl" role="dialog" aria-label="Koreksi Qty Production">
                <div class="panel-head">
                    <h3 class="panel-title">Koreksi Qty Production</h3>
                    <button type="button" class="act-btn" @click="editOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('productions.verify_batch') }}" method="POST" novalidate
                      @submit.prevent="submitEdit($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    <div class="dlg-split">
                        <aside class="dlg-side">
                            <div class="dlg-k">Lot &amp; Item Produksi</div>
                            <div class="sumc">
                                <div class="pill pill-info w-fit num" x-text="'LOT: ' + editLot"></div>
                                <div class="dlg-code num mt-1" x-text="editPartNo"></div>
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
                            <div x-show="editLotIsFinish" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="'Lot ' + editLot + ' sudah berstatus FINISH sehingga tidak dapat dikoreksi.'"></span>
                            </div>

                            <div x-show="editError && !editLotIsFinish" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="editError"></span>
                            </div>

                            <input type="hidden" :name="'items[' + editId + '][qty_check]'" value="DIKOREKSI">
                            <div>
                                <label class="lbl" for="prod_actual_qty">Qty Production Sebenarnya</label>
                                <div class="inp-calc">
                                    <input id="prod_actual_qty" type="text"
                                           :name="'items[' + editId + '][actual_qty]'"
                                           x-model="editNewQty" required
                                           :disabled="editLotIsFinish"
                                           placeholder="Contoh: 60 atau 2*30"
                                           class="num w-full h-9 font-semibold">
                                    <span class="inp-calc-ic" title="Mendukung kalkulator (+, -, *, /)">
                                        <x-icon name="calc" />
                                    </span>
                                </div>
                                <div class="calc-preview" x-show="!editLotIsFinish && String(editNewQty || '').trim() !== '' && evalCalc(editNewQty) !== null" x-cloak>
                                    Hasil: <b x-text="evalCalc(editNewQty)"></b> <span x-text="editUom"></span>
                                </div>
                                <div class="calc-err" x-show="!editLotIsFinish && String(editNewQty || '').trim() !== '' && evalCalc(editNewQty) === null" x-cloak>
                                    <x-icon name="alert" />
                                    <span>Format perhitungan tidak valid</span>
                                </div>
                            </div>
                            <div class="alert alert-info text-xs leading-relaxed">
                                <x-icon name="info" />
                                <div>Menyimpan koreksi akan otomatis menyesuaikan <b>Current Stock</b> di gudang serta <b>Actual Prod, Act FG, &amp; Qty WIP</b> pada Lot Tracer (mendukung koreksi hasil melebihi estimasi tanpa membuat WIP minus).</div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="editOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary" :disabled="editLotIsFinish">Simpan Koreksi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function productionPage(initialCount) {
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
                editError: '',
                editId: null,
                editLot: '',
                editPartNo: '',
                editPartName: '',
                editType: 'FG',
                editOldQty: 0,
                editNewQty: '',
                editUom: '',
                editStock: 0,
                editLotActual: 0,
                editLotNg: 0,
                editLotIsFinish: false,

                openEdit(id, lot, partNo, partName, type, qty, uom, stock, lotActual, lotNg, lotIsFinish) {
                    this.editId = id;
                    this.editLot = lot;
                    this.editPartNo = partNo;
                    this.editPartName = partName;
                    this.editType = type;
                    this.editOldQty = qty;
                    this.editNewQty = String(qty);
                    this.editUom = uom;
                    this.editStock = Number(stock) || 0;
                    this.editLotActual = Number(lotActual) || 0;
                    this.editLotNg = Number(lotNg) || 0;
                    this.editLotIsFinish = Boolean(lotIsFinish);
                    this.editError = '';
                    this.editOpen = true;
                },
                evalCalc(raw) {
                    if (raw === null || raw === undefined) return null;
                    const expr = String(raw).replace(/,/g, '.').replace(/[xX]/g, '*').replace(/:/g, '/').replace(/\s+/g, '');
                    if (!expr || !/^[0-9.+\-*\/()]+$/.test(expr) || /[\+\-\*\/]{2,}/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r)) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                submitEdit(formEl) {
                    this.editError = '';
                    if (this.editLotIsFinish) {
                        this.editError = 'Lot ' + this.editLot + ' sudah berstatus FINISH sehingga tidak dapat dikoreksi.';
                        return;
                    }
                    if (String(this.editNewQty || '').trim() === '') {
                        this.editError = 'Qty koreksi wajib diisi.';
                        return;
                    }
                    const val = this.evalCalc(this.editNewQty);
                    if (val === null) {
                        this.editError = 'Format perhitungan tidak valid.';
                        return;
                    }
                    if (val <= 0) {
                        this.editError = 'Qty koreksi harus lebih dari 0.';
                        return;
                    }
                    const diff = Math.round((val - this.editOldQty) * 100) / 100;
                    if (this.editLotActual + diff < this.editLotNg - 0.0001) {
                        this.editError = 'Qty koreksi Lot ' + this.editLot + ' tidak boleh lebih kecil dari Qty NG (' + this.editLotNg + ' ' + this.editUom + ').';
                        return;
                    }
                    if (this.editStock + diff < -0.0001) {
                        this.editError = 'Stok ' + this.editPartName + ' tidak boleh minus akibat koreksi (stok saat ini: ' + this.editStock + ' ' + this.editUom + ').';
                        return;
                    }
                    formEl.submit();
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
                            if (this.matchRow(tr)) {
                                c++;
                                const noCell = tr.querySelector('[data-col-no]');
                                if (noCell) noCell.textContent = c;
                            }
                        });
                        this.visibleCount = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>