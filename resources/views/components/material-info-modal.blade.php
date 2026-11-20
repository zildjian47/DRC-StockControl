<div x-data="materialInfo()"
     @open-material-info.window="showModal($event.detail)"
     @keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     class="modal-backdrop"
     @click.self="open = false">

    <div class="panel dlg w-full max-w-6xl" role="dialog" aria-label="Informasi Item">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Informasi Item</h3>
                <p class="hint" style="margin-top: 2px"
                   x-text="(info?.part_no && info?.part_no !== '-' ? info.part_no + ' · ' : '') + (info?.part_name ?? '')"></p>
            </div>
            <button type="button" @click="open = false" class="act-btn" aria-label="Tutup">
                <x-icon name="x" />
            </button>
        </div>

        <div class="dlg-split dlg-split-wide" x-show="info">
            {{-- PANEL KIRI (ABU): IDENTITAS & STANDAR BUFFER --}}
            <aside class="dlg-side">
                <div>
                    <div class="dlg-code num" x-text="info?.part_no !== '-' ? info?.part_no : ''"></div>
                    <div class="dlg-name" x-text="info?.part_name"></div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="ty" :class="tyClass(info?.type)" x-text="info?.type"></span>
                    <span class="num text-xs font-semibold" style="color: var(--ink2)" x-text="info?.uom"></span>
                    <span class="pill"
                          :class="info?.company_type === 'CUSTOMER' ? 'pill-good' : (info?.company_type === 'INTERNAL' ? 'pill-warn' : 'pill-info')"
                          x-text="info?.company_type || 'INTERNAL'"></span>
                </div>
                <div class="text-xs font-semibold text-slate-700" x-text="info?.company"></div>

                <div>
                    <div class="dlg-k">Stock Saat Ini</div>
                    <div class="num" style="font-size: 22px; font-weight: 700; line-height: 1.2"
                         x-text="(info?.current_stock ?? 0) + ' ' + (info?.uom ?? '')"></div>
                    <div class="sbar">
                        <i :style="'width:' + barWidth() + '%; background:' + barColor(info?.status_stock)"></i>
                        <template x-for="tk in stdTicks" :key="'ln-' + tk.key">
                            <u :style="'left:' + tk.p + '%'" :title="tk.title"></u>
                        </template>
                    </div>
                    <div class="sbar-lbl" x-show="stdTicks.length > 0">
                        <template x-for="tk in stdTicks" :key="'lb-' + tk.key">
                            <span class="num" :style="'left:' + tk.p + '%'" x-text="tk.val"></span>
                        </template>
                    </div>
                </div>

                <div>
                    <span class="pill" :class="pillClass(info?.status_stock)" x-text="info?.status_stock"></span>
                </div>

                <div>
                    <div class="dlg-k" style="margin-bottom: 6px">Standar Buffer</div>
                    <div class="dlg-list">
                        <div>
                            <span>Stock Gudang</span>
                            <b class="num" x-text="(info?.current_stock ?? 0) + ' ' + (info?.uom ?? '')"></b>
                        </div>
                        <div>
                            <span>WIP</span>
                            <b class="num" x-text="(info?.wip_qty ?? 0) + ' ' + (info?.uom ?? '')"></b>
                        </div>
                        <div>
                            <span>Std Day 1</span>
                            <b class="num" x-text="(info?.std_day_1 ?? 0) + ' ' + (info?.uom ?? '')"></b>
                        </div>
                        <div>
                            <span>Std Day 2</span>
                            <b class="num" x-text="(info?.std_day_2 ?? 0) + ' ' + (info?.uom ?? '')"></b>
                        </div>
                        <div>
                            <span>Std Day 3</span>
                            <b class="num" x-text="(info?.std_day_3 ?? 0) + ' ' + (info?.uom ?? '')"></b>
                        </div>
                        <div>
                            <span>Ratio Stock</span>
                            <b class="num text-accent" x-text="(info?.ratio_stock ?? 0) + ' Hari'"></b>
                        </div>
                    </div>
                </div>
            </aside>

            {{-- PANEL KANAN: BOM & RIWAYAT STOCK --}}
            <div class="dlg-main">
                {{-- BOM --}}
                <section>
                    <h4 class="sec-h">BOM</h4>
                    <div class="flow">
                        {{-- Kolom 1: Material Pembentuk --}}
                        <div class="min-w-0">
                            <div class="flow-cap">
                                <span>MATERIAL PEMBENTUK</span>
                                <span class="count-chip">
                                    <span x-text="info?.inputs?.length || 0"></span>
                                    <span>MATERIAL</span>
                                </span>
                            </div>
                            <div class="flow-col">
                                <template x-for="grp in groupedInputs" :key="'in-grp-' + grp.bom_group">
                                    <div class="bom-grp-box">
                                        <div class="bom-grp-head">
                                            <span class="num text-[11px] font-bold text-slate-800 break-all"
                                                  x-text="'BOM ID: ' + grp.bom_group"></span>
                                            <span class="pill pill-info" x-text="grp.group_type"></span>
                                        </div>

                                        <template x-for="(inp, idx) in grp.items" :key="'inp-' + grp.bom_group + '-' + idx">
                                            <div class="rel">
                                                <div class="rel-t">
                                                    <span class="text-slate-700" x-text="inp.process"></span>
                                                    <span class="pill"
                                                          :class="inp.is_trigger ? 'pill-good' : 'pill-neu'"
                                                          x-text="inp.is_trigger ? 'TRIGGER' : 'PENDAMPING'"></span>
                                                </div>
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="ty" :class="tyClass(inp.type)" x-text="inp.type"></span>
                                                    <span class="rel-part num" x-text="inp.part_no !== '-' ? inp.part_no : ''"></span>
                                                </div>
                                                <div class="font-medium text-slate-900 break-words" x-text="inp.part_name"></div>
                                                <div class="rel-m num">
                                                    <span x-text="'Usage: ' + inp.qty_usage + ' ' + inp.uom"></span>
                                                    <span x-text="'Stock: ' + inp.current_stock + ' ' + inp.uom + ' · ' + inp.ratio_stock + ' Hr'"></span>
                                                </div>
                                                <div>
                                                    <span class="pill" :class="pillClass(inp.status_stock)" x-text="inp.status_stock"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="groupedInputs.length === 0">
                                    <div class="rel hint">Tidak memiliki material pembentuk.</div>
                                </template>
                            </div>
                        </div>

                        <div class="flow-ar"><x-icon name="chevron-right" /></div>

                        {{-- Kolom 2: Item Ini --}}
                        <div class="min-w-0">
                            <div class="flow-cap">
                                <span>ITEM INI</span>
                            </div>
                            <div class="rel rel-now">
                                <div class="rel-t">
                                    <span class="ty" :class="tyClass(info?.type)" x-text="info?.type"></span>
                                    <span class="num text-accent font-bold" x-text="info?.part_no !== '-' ? info?.part_no : ''"></span>
                                </div>
                                <div class="font-semibold text-slate-900 break-words" x-text="info?.part_name"></div>
                                <div class="rel-m num">
                                    <span x-text="'Stock: ' + (info?.current_stock ?? 0) + ' ' + (info?.uom ?? '')"></span>
                                    <span x-text="(info?.ratio_stock ?? 0) + ' Hr'"></span>
                                </div>
                                <div>
                                    <span class="pill" :class="pillClass(info?.status_stock)" x-text="info?.status_stock"></span>
                                </div>
                            </div>
                        </div>

                        <div class="flow-ar"><x-icon name="chevron-right" /></div>

                        {{-- Kolom 3: Dipakai Di --}}
                        <div class="min-w-0">
                            <div class="flow-cap">
                                <span>DIPAKAI DI</span>
                                <span class="count-chip">
                                    <span x-text="uniqueOutputsCount"></span>
                                    <span>ITEM</span>
                                </span>
                            </div>
                            <div class="flow-col">
                                <template x-for="grp in groupedOutputs" :key="'out-grp-' + grp.bom_group">
                                    <div class="bom-grp-box">
                                        <div class="bom-grp-head">
                                            <span class="num text-[11px] font-bold text-slate-800 break-all"
                                                  x-text="'BOM ID: ' + grp.bom_group"></span>
                                            <span class="pill pill-info" x-text="grp.group_type"></span>
                                        </div>

                                        <template x-for="(out, idx) in grp.items" :key="'out-' + grp.bom_group + '-' + idx">
                                            <div class="rel">
                                                <div class="rel-t">
                                                    <span class="text-slate-700" x-text="out.process"></span>
                                                    <span class="pill"
                                                          :class="out.is_trigger ? 'pill-good' : 'pill-neu'"
                                                          x-text="out.is_trigger ? 'TRIGGER' : 'PENDAMPING'"></span>
                                                </div>
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="ty" :class="tyClass(out.type)" x-text="out.type"></span>
                                                    <span class="rel-part num" x-text="out.part_no !== '-' ? out.part_no : ''"></span>
                                                </div>
                                                <div class="font-medium text-slate-900 break-words" x-text="out.part_name"></div>
                                                <div class="rel-m num">
                                                    <span x-text="'Usage: ' + out.qty_usage + ' ' + (info?.uom ?? '')"></span>
                                                    <span x-text="'Stock: ' + out.current_stock + ' ' + out.uom + ' · ' + out.ratio_stock + ' Hr'"></span>
                                                </div>
                                                <div>
                                                    <span class="pill" :class="pillClass(out.status_stock)" x-text="out.status_stock"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="groupedOutputs.length === 0">
                                    <div class="rel hint">Tidak dipakai pada item lain.</div>
                                </template>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- RIWAYAT PENAMBAHAN & PENGURANGAN STOCK --}}
                <section>
                    <h4 class="sec-h flex items-center gap-2">
                        <span>Riwayat Penambahan &amp; Pengurangan Stock</span>
                        <span class="count-chip">
                            <span x-text="filteredHistory.length"></span>
                            <span>TRANSAKSI</span>
                        </span>
                    </h4>

                    <div class="fbar">
                        <input type="text" x-model="histSearch" placeholder="Cari transaksi, lot, petugas..."
                               aria-label="Cari riwayat" class="h-8 text-xs px-2.5 flex-1 min-w-[150px]">
                        <label>Tanggal
                            <input type="date" x-model="histStartDate" aria-label="Dari tanggal"
                                   @change="if(histEndDate && histEndDate < histStartDate) histEndDate = histStartDate"
                                   class="h-8 text-xs px-2 w-[124px]">
                            <span>–</span>
                            <input type="date" x-model="histEndDate" :min="histStartDate" aria-label="Sampai tanggal"
                                   class="h-8 text-xs px-2 w-[124px]">
                        </label>
                        <label>Jam
                            <input type="time" x-model="histStartTime" aria-label="Dari jam" class="h-8 text-xs px-2 w-[92px]">
                            <span>–</span>
                            <input type="time" x-model="histEndTime" aria-label="Sampai jam" class="h-8 text-xs px-2 w-[92px]">
                        </label>
                        <button type="button" class="btn btn-sm flex-none"
                                x-show="histSearch || histStartDate || histEndDate || histStartTime || histEndTime" x-cloak
                                @click="resetHistoryFilter()">Reset</button>
                    </div>

                    <div class="tl-box" style="margin-top: 10px">
                        <template x-for="g in historyGroups" :key="g.date">
                            <div>
                                <div class="tl-day">
                                    <span style="font-weight: 600; letter-spacing: .08em; text-transform: uppercase" x-text="g.label"></span>
                                    <span class="count-chip count-chip-sub" x-text="g.rows.length + ' TRANSAKSI'"></span>
                                </div>
                                <template x-for="(h, i) in g.rows" :key="g.date + '-' + i">
                                    <div class="tl-item" :class="h.qty_change >= 0 ? 'tl-in' : 'tl-out'">
                                        <div class="tl-time" x-text="h.time"></div>
                                        <div class="tl-dot"><i></i></div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-semibold" style="color: var(--ink)" x-text="cleanLabel(h.type_label)"></span>
                                                <span class="pill pill-info" x-show="h.status === 'DIKOREKSI'" x-text="'DIKOREKSI'"></span>
                                            </div>
                                            <div class="hint break-words" style="margin-top: 0" x-text="cleanLabel(h.reference)"></div>
                                            <div class="hint" style="margin-top: 0" x-text="cleanLabel(h.user_name || '-')"></div>
                                        </div>
                                        <div class="tl-qty num">
                                            <span x-text="(h.qty_change >= 0 ? '+' : '') + h.qty_change"></span>
                                            <small x-text="h.uom"></small>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <div x-show="filteredHistory.length === 0" class="empty" style="padding-bottom: 24px">
                            Tidak ada riwayat transaksi.
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="panel-foot">
            <button type="button" class="btn" @click="open = false">Tutup</button>
        </div>
    </div>

    <script>
        function materialInfo() {
            const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return {
                open: false,
                info: null,
                histSearch: '',
                histStartDate: '',
                histEndDate: '',
                histStartTime: '',
                histEndTime: '',

                showModal(payload) {
                    this.info = payload;
                    this.resetHistoryFilter();
                    this.open = true;
                },
                resetHistoryFilter() {
                    this.histSearch = '';
                    this.histStartDate = '';
                    this.histEndDate = '';
                    this.histStartTime = '';
                    this.histEndTime = '';
                },
                cleanLabel(str) {
                    return String(str || '').replace(/\s*\([^)]*\)/g, '').trim();
                },
                pillClass(status) {
                    if (status === 'EMPTY STOCK') return 'pill-empty';
                    if (status === 'VERY LOW') return 'pill-crit';
                    if (status === 'LOW STOCK') return 'pill-warn';
                    if (status === 'NORMAL STOCK') return 'pill-good';
                    if (status === 'OVERSTOCK') return 'pill-info';
                    return 'pill-neu';
                },
                tyClass(t) {
                    return 'ty-' + (t === 'FG/KOMPONEN' ? 'FGK' : t);
                },
                barColor(status) {
                    const m = {
                        'EMPTY STOCK': 'var(--ink)',
                        'VERY LOW': 'var(--crit)',
                        'LOW STOCK': 'var(--warn)',
                        'NORMAL STOCK': 'var(--good)',
                        'OVERSTOCK': 'var(--accent)'
                    };
                    return m[status] || 'var(--mut)';
                },
                barWidth() {
                    if (!this.info) return '0';
                    const stock = Number(this.info.current_stock) || 0;
                    if (stock <= 0) return '0';

                    const s1 = Number(this.info.std_day_1) || 0;
                    const s3 = Number(this.info.std_day_3) || 0;
                    if (s1 <= 0 || s3 <= 0) return '50';

                    if (stock <= s3) {
                        return Math.min(75, Math.max(2, (stock / s3) * 75)).toFixed(1);
                    }

                    const overPct = 78 + ((stock - s3) / (stock + s3)) * 12;
                    return Math.min(90, overPct).toFixed(1);
                },
                get stdTicks() {
                    if (!this.info) return [];
                    const s1 = Number(this.info.std_day_1) || 0;
                    const s2 = Number(this.info.std_day_2) || 0;
                    const s3 = Number(this.info.std_day_3) || 0;
                    if (s1 <= 0) return [];

                    return [
                        { key: 's1', title: 'Std Day 1: ' + s1, val: s1, p: 25 },
                        { key: 's2', title: 'Std Day 2: ' + s2, val: s2, p: 50 },
                        { key: 's3', title: 'Std Day 3: ' + s3, val: s3, p: 75 },
                    ];
                },
                dayLabel(d) {
                    const p = String(d).split('-');
                    return p.length === 3 ? (parseInt(p[2], 10) + ' ' + MON[parseInt(p[1], 10) - 1] + ' ' + p[0]) : d;
                },
                get groupedInputs() {
                    if (!this.info || !Array.isArray(this.info.inputs)) return [];
                    const map = {};
                    const out = [];
                    this.info.inputs.forEach(inp => {
                        const k = inp.bom_group || 'DEFAULT';
                        if (!(k in map)) {
                            map[k] = out.length;
                            out.push({
                                bom_group: k,
                                group_type: inp.group_type || 'SINGLE',
                                process: inp.process || '-',
                                items: []
                            });
                        }
                        out[map[k]].items.push(inp);
                    });
                    return out;
                },
                get groupedOutputs() {
                    if (!this.info || !Array.isArray(this.info.outputs)) return [];
                    const map = {};
                    const out = [];
                    this.info.outputs.forEach(o => {
                        const k = o.bom_group || 'DEFAULT';
                        if (!(k in map)) {
                            map[k] = out.length;
                            out.push({
                                bom_group: k,
                                group_type: o.group_type || 'SINGLE',
                                process: o.process || '-',
                                items: []
                            });
                        }
                        const grp = out[map[k]];
                        const exists = grp.items.some(ex => ex.part_no === o.part_no && ex.part_name === o.part_name);
                        if (!exists) {
                            grp.items.push(o);
                        }
                    });
                    return out;
                },
                get uniqueOutputsCount() {
                    return this.groupedOutputs.reduce((acc, g) => acc + g.items.length, 0);
                },
                get filteredHistory() {
                    if (!this.info || !this.info.history) return [];
                    return this.info.history
                        .slice()
                        .sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0))
                        .filter(h => {
                            if (this.histSearch) {
                                const q = this.histSearch.toLowerCase();
                                const hit = [h.type_label, h.reference, h.user_name, h.status]
                                    .some(v => String(v || '').toLowerCase().includes(q));
                                if (!hit) return false;
                            }
                            if (this.histStartDate && h.date < this.histStartDate) return false;
                            if (this.histEndDate && h.date > this.histEndDate) return false;
                            if (this.histStartTime && h.time < this.histStartTime) return false;
                            if (this.histEndTime && h.time > this.histEndTime) return false;
                            return true;
                        });
                },
                get historyGroups() {
                    const out = [];
                    const idx = {};
                    this.filteredHistory.forEach(h => {
                        if (!(h.date in idx)) {
                            idx[h.date] = out.length;
                            out.push({ date: h.date, label: this.dayLabel(h.date), rows: [] });
                        }
                        out[idx[h.date]].rows.push(h);
                    });
                    return out;
                }
            };
        }
    </script>
</div>