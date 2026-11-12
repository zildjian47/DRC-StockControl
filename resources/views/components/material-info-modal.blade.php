<div x-data="{
        open: false,
        info: null,
        histSearch: '',
        histStartDate: '',
        histEndDate: '',
        histStartTime: '',
        histEndTime: '',

        showModal(payload) {
            this.info = payload;
            this.histSearch = '';
            this.histStartDate = '';
            this.histEndDate = '';
            this.histStartTime = '';
            this.histEndTime = '';
            this.open = true;
        },
        pillClass(status) {
            if (status === 'EMPTY STOCK') return 'pill-empty';
            if (status === 'VERY LOW') return 'pill-crit';
            if (status === 'LOW STOCK') return 'pill-warn';
            if (status === 'NORMAL STOCK') return 'pill-good';
            if (status === 'OVERSTOCK') return 'pill-info';
            return 'pill-neu';
        },
        get filteredHistory() {
            if (!this.info || !this.info.history) return [];
            return this.info.history.filter(h => {
                if (this.histSearch) {
                    let q = this.histSearch.toLowerCase();
                    let matchType = String(h.type_label || '').toLowerCase().includes(q);
                    let matchRef  = String(h.reference || '').toLowerCase().includes(q);
                    let matchUser = String(h.user_name || '').toLowerCase().includes(q);
                    let matchStat = String(h.status || '').toLowerCase().includes(q);
                    if (!matchType && !matchRef && !matchUser && !matchStat) return false;
                }
                if (this.histStartDate && h.date < this.histStartDate) return false;
                if (this.histEndDate && h.date > this.histEndDate) return false;
                if (this.histStartTime && h.time < this.histStartTime) return false;
                if (this.histEndTime && h.time > this.histEndTime) return false;
                return true;
            });
        },
        resetHistoryFilter() {
            this.histSearch = '';
            this.histStartDate = '';
            this.histEndDate = '';
            this.histStartTime = '';
            this.histEndTime = '';
        }
     }"
     @open-material-info.window="showModal($event.detail)"
     @keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     class="modal-backdrop">

    <div class="panel w-full max-w-5xl max-h-[92vh] flex flex-col overflow-hidden" @click.away="open = false">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Informasi Material, Keterkaitan BOM &amp; Riwayat Mutasi Stok</h3>
                <p class="text-xs text-slate-500 mt-0.5" x-text="(info?.part_no && info?.part_no !== '-' ? info.part_no + ' · ' : '') + (info?.part_name ?? '')"></p>
            </div>
            <button type="button" @click="open = false" class="act-btn" aria-label="Tutup">
                <x-icon name="x" />
            </button>
        </div>

        <div class="panel-body overflow-y-auto overflow-x-hidden space-y-5" x-show="info">
            {{-- 1. Ringkasan Parameter & Status Stok --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2.5 p-3.5 rounded border border-slate-200 bg-slate-50">
                <div class="col-span-2">
                    <div class="text-[11px] font-semibold uppercase text-slate-500">Part No &amp; Nama Item</div>
                    <div class="num text-xs font-semibold text-accent mt-0.5" x-text="info?.part_no"></div>
                    <div class="font-semibold text-slate-900 text-xs break-words" x-text="info?.part_name"></div>
                </div>
                <div>
                    <div class="text-[11px] font-semibold uppercase text-slate-500">Tipe &amp; UOM</div>
                    <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                        <span class="pill pill-neu" x-text="info?.type"></span>
                        <span class="num text-xs font-semibold text-slate-700" x-text="info?.uom"></span>
                    </div>
                </div>
                <div>
                    <div class="text-[11px] font-semibold uppercase text-slate-500">Perusahaan</div>
                    <div class="text-xs font-semibold text-slate-800 mt-1 break-words" x-text="info?.company"></div>
                </div>
                <div>
                    <div class="text-[11px] font-semibold uppercase text-slate-500">Std Day 1 / 2 / 3</div>
                    <div class="num text-xs font-semibold text-slate-800 mt-1"
                         x-text="(info?.std_day_1 ?? 0) + ' / ' + (info?.std_day_2 ?? 0) + ' / ' + (info?.std_day_3 ?? 0)"></div>
                </div>
                <div>
                    <div class="text-[11px] font-semibold uppercase text-slate-500">Ratio Stock</div>
                    <div class="num text-base font-bold text-accent mt-0.5" x-text="(info?.ratio_stock ?? 0) + ' Hari'"></div>
                </div>
                <div>
                    <div class="text-[11px] font-semibold uppercase text-slate-500">Current Stock</div>
                    <div class="num text-base font-bold text-slate-900 mt-0.5" x-text="(info?.current_stock ?? 0) + ' ' + (info?.uom ?? '')"></div>
                </div>
                <div>
                    <div class="text-[11px] font-semibold uppercase text-slate-500">Status Stock</div>
                    <div class="mt-1">
                        <span class="pill" :class="pillClass(info?.status_stock)" x-text="info?.status_stock"></span>
                    </div>
                </div>
            </div>

            {{-- 2. Resep Input BOM & Where-Used --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="border border-slate-200 rounded flex flex-col">
                    <div class="px-3.5 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-700">
                            Material / Komponen Pembentuk Item Ini
                        </h4>
                        <span class="count-chip" x-text="(info?.inputs?.length || 0) + ' Material'"></span>
                    </div>
                    <template x-if="info?.inputs && info.inputs.length > 0">
                        <div class="divide-y divide-slate-200 max-h-48 overflow-y-auto">
                            <template x-for="(inp, idx) in info.inputs" :key="'inp-' + idx">
                                <div class="p-3 flex items-center justify-between gap-3 text-xs hover:bg-slate-50">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-semibold text-slate-700" x-text="inp.process"></span>
                                            <span class="pill" :class="inp.is_trigger ? 'pill-good' : 'pill-neu'" x-text="inp.is_trigger ? 'Trigger' : 'Pendamping'"></span>
                                        </div>
                                        <div class="num text-accent font-semibold mt-0.5" x-text="inp.part_no !== '-' ? inp.part_no : inp.type"></div>
                                        <div class="font-semibold text-slate-900 break-words" x-text="inp.part_name"></div>
                                    </div>
                                    <div class="text-right whitespace-nowrap">
                                        <div class="num font-semibold text-slate-900" x-text="'Usage: ' + inp.qty_usage + ' ' + inp.uom"></div>
                                        <div class="num text-slate-600 mt-0.5" x-text="'Stok: ' + inp.current_stock + ' ' + inp.uom + ' (' + inp.ratio_stock + ' Hr)'"></div>
                                        <div class="mt-1">
                                            <span class="pill" :class="pillClass(inp.status_stock)" x-text="inp.status_stock"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="!info?.inputs || info.inputs.length === 0">
                        <div class="p-4 text-xs text-slate-500 text-center">
                            Item ini tidak memiliki komponen pembentuk di BOM.
                        </div>
                    </template>
                </div>

                <div class="border border-slate-200 rounded flex flex-col">
                    <div class="px-3.5 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-700">
                            Digunakan Untuk Produksi Part (Where-Used)
                        </h4>
                        <span class="count-chip" x-text="(info?.outputs?.length || 0) + ' Output'"></span>
                    </div>
                    <template x-if="info?.outputs && info.outputs.length > 0">
                        <div class="divide-y divide-slate-200 max-h-48 overflow-y-auto">
                            <template x-for="(out, idx) in info.outputs" :key="'out-' + idx">
                                <div class="p-3 flex items-center justify-between gap-3 text-xs hover:bg-slate-50">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-semibold text-slate-700" x-text="out.process"></span>
                                            <span class="pill pill-info" x-text="out.group_type"></span>
                                        </div>
                                        <div class="num text-accent font-semibold mt-0.5" x-text="out.part_no"></div>
                                        <div class="font-semibold text-slate-900 break-words" x-text="out.part_name"></div>
                                    </div>
                                    <div class="text-right whitespace-nowrap">
                                        <div class="num font-semibold text-slate-900" x-text="'Usage: ' + out.qty_usage"></div>
                                        <div class="num text-slate-600 mt-0.5" x-text="'Stok: ' + out.current_stock + ' ' + out.uom + ' (' + out.ratio_stock + ' Hr)'"></div>
                                        <div class="mt-1">
                                            <span class="pill" :class="pillClass(out.status_stock)" x-text="out.status_stock"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="!info?.outputs || info.outputs.length === 0">
                        <div class="p-4 text-xs text-slate-500 text-center">
                            Item ini merupakan produk akhir (tidak dipakai sebagai material input untuk part lain).
                        </div>
                    </template>
                </div>
            </div>

            {{-- 3. Riwayat Penambahan & Pengurangan Stok (Freeze Header Saat Di-scroll + Nama Petugas) --}}
            <div class="border border-slate-200 rounded overflow-hidden">
                <div class="px-3.5 py-2.5 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-700">
                            Riwayat Penambahan &amp; Pengurangan Stok
                        </h4>
                        <span class="count-chip" x-text="filteredHistory.length + ' Transaksi'"></span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" x-model="histSearch"
                               placeholder="Cari transaksi / Lot / Petugas..."
                               class="filter-input w-48">

                        <div class="flex items-center gap-1">
                            <span class="text-[11px] font-semibold text-slate-500">Tgl:</span>
                            <input type="date" x-model="histStartDate"
                                   @change="if(histEndDate && histEndDate < histStartDate) histEndDate = histStartDate"
                                   class="filter-input num">
                            <span class="text-xs text-slate-400">-</span>
                            <input type="date" x-model="histEndDate" :min="histStartDate"
                                   class="filter-input num">
                        </div>

                        <div class="flex items-center gap-1">
                            <span class="text-[11px] font-semibold text-slate-500">Jam:</span>
                            <input type="time" x-model="histStartTime" class="filter-input num">
                            <span class="text-xs text-slate-400">-</span>
                            <input type="time" x-model="histEndTime" class="filter-input num">
                        </div>

                        <button type="button"
                                x-show="histSearch || histStartDate || histEndDate || histStartTime || histEndTime"
                                @click="resetHistoryFilter()"
                                class="btn btn-sm">
                            Reset
                        </button>
                    </div>
                </div>

                <div class="max-h-64 overflow-y-auto relative">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th class="w-12 text-center">No</th>
                                <th>Waktu Transaksi</th>
                                <th>Jenis Transaksi</th>
                                <th>Keterangan / Referensi</th>
                                <th>Petugas</th>
                                <th class="text-center">Status</th>
                                <th class="num">Mutasi Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(h, i) in filteredHistory" :key="'hist-' + i">
                                <tr>
                                    <td class="num text-center text-xs text-slate-500" x-text="i + 1"></td>
                                    <td class="num text-xs text-slate-600 whitespace-nowrap" x-text="h.datetime_fmt"></td>
                                    <td class="font-semibold text-slate-900 text-xs" x-text="h.type_label"></td>
                                    <td class="text-xs text-slate-600 break-words" x-text="h.reference"></td>
                                    <td class="text-xs font-medium text-slate-700 whitespace-nowrap" x-text="h.user_name || '-'"></td>
                                    <td class="text-center">
                                        <span class="pill pill-neu" x-text="h.status"></span>
                                    </td>
                                    <td class="num font-semibold whitespace-nowrap"
                                        :class="h.qty_change >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                        x-text="(h.qty_change > 0 ? '+' : '') + h.qty_change + ' ' + h.uom"></td>
                                </tr>
                            </template>
                            <template x-if="filteredHistory.length === 0">
                                <tr>
                                    <td colspan="7">
                                        <div class="empty py-6 text-xs">Tidak ada riwayat mutasi stok pada rentang filter ini.</div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel-foot">
            <button type="button" @click="open = false" class="btn btn-primary">Tutup</button>
        </div>
    </div>
</div>