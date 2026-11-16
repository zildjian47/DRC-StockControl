<div x-data="materialInfo()"
     @open-material-info.window="showModal($event.detail)"
     @keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     class="modal-backdrop"
     @click.self="open = false">

    <div class="panel dlg w-full max-w-5xl" role="dialog" aria-label="Informasi Material">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Informasi Material, Keterkaitan BOM &amp; Riwayat Mutasi Stok</h3>
                <p class="hint" style="margin-top: 2px"
                   x-text="(info?.part_no && info?.part_no !== '-' ? info.part_no + ' · ' : '') + (info?.part_name ?? '')"></p>
            </div>
            <button type="button" @click="open = false" class="act-btn" aria-label="Tutup">
                <x-icon name="x" />
            </button>
        </div>

        <div class="dlg-split dlg-split-wide" x-show="info">
            {{-- PANEL KIRI (abu): Identitas Lengkap + Ringkasan Parameter & Buffer --}}
            <aside class="dlg-side">
                <div>
                    <div class="dlg-k">Identitas Item</div>
                    <div class="sumc mt-1.5">
                        <div class="dlg-code num" x-text="info?.part_no !== '-' ? info?.part_no : 'TANPA PART NO'"></div>
                        <div class="dlg-name" x-text="info?.part_name"></div>
                        <div class="flex items-center gap-1.5 flex-wrap mt-1">
                            <span class="ty" :class="tyClass(info?.type)" x-text="info?.type"></span>
                            <span class="pill pill-neu num" x-text="'UOM: ' + (info?.uom || '-')"></span>
                            <span class="pill" :class="compPill(info?.company_type)" x-text="info?.company_type || 'INTERNAL'"></span>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-1 break-words" x-text="info?.company"></div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="dlg-k">Stok Gudang Saat Ini</span>
                        <span class="pill" :class="pillClass(info?.status_stock)" x-text="info?.status_stock"></span>
                    </div>
                    <div class="num mt-1" style="font-size: 22px; font-weight: 700; line-height: 1.2"
                         x-text="(info?.current_stock ?? 0) + ' ' + (info?.uom ?? '')"></div>
                    <div class="sbar">
                        <i :style="'width:' + pct(info?.current_stock) + '%; background:' + barColor(info?.status_stock)"></i>
                        <u :style="'left:' + pct(info?.std_day_2) + '%'" title="Batas Min (Std Day 2)"></u>
                        <u :style="'left:' + pct(info?.std_day_3) + '%'" title="Batas Max (Std Day 3)"></u>
                    </div>
                    <div class="sbar-lbl">
                        <span x-text="'Min (Std 2): ' + (info?.std_day_2 ?? 0)"></span>
                        <span x-text="'Max (Std 3): ' + (info?.std_day_3 ?? 0)"></span>
                    </div>
                </div>

                <div>
                    <div class="dlg-k" style="margin-bottom: 6px">Parameter Standar &amp; Ketahanan</div>
                    <div class="dlg-list">
                        <div>
                            <span>WIP Aktif di Lot</span>
                            <b class="num text-amber-700" x-text="(info?.wip_qty ?? 0) + ' ' + (info?.uom ?? '')"></b>
                        </div>
                        <div>
                            <span>Std Day 1 (1 Hari)</span>
                            <b class="num" x-text="(info?.std_day_1 ?? 0) + ' ' + (info?.uom ?? '')"></b>
                        </div>
                        <div>
                            <span>Std Day 2 (Min 2 Hr)</span>
                            <b class="num" x-text="(info?.std_day_2 ?? 0) + ' ' + (info?.uom ?? '')"></b>
                        </div>
                        <div>
                            <span>Std Day 3 (Max 3 Hr)</span>
                            <b class="num" x-text="(info?.std_day_3 ?? 0) + ' ' + (info?.uom ?? '')"></b>
                        </div>
                        <div>
                            <span>Ratio Ketahanan Stok</span>
                            <b class="num text-accent" x-text="(info?.ratio_stock ?? 0) + ' HARI'"></b>
                        </div>
                    </div>
                </div>

                <div class="p-2.5 rounded border border-slate-200 bg-white text-[11.5px] text-slate-600 leading-relaxed">
                    <b class="text-slate-800 block mb-0.5">Keterangan Status:</b>
                    <span x-text="statusExplain(info)"></span>
                </div>
            </aside>

            {{-- PANEL KANAN --}}
            <div class="dlg-main">
                {{-- ALUR BOM --}}
                <section>
                    <h4 class="sec-h">Alur Keterkaitan Resep BOM</h4>
                    <div class="flow">
                        {{-- KOLOM KIRI: MATERIAL PEMBENTUK (Dikelompokkan per BOM ID) --}}
                        <div>
                            <div class="flow-cap">
                                <span>MATERIAL PEMBENTUK</span>
                                <span class="count-chip"><span x-text="(info?.inputs?.length || 0) + ' MATERIAL'"></span></span>
                            </div>
                            <div class="flow-col">
                                <template x-for="grp in inputGroups" :key="'in-grp-' + grp.bom_group">
                                    <div class="bom-grp-box">
                                        <div class="bom-grp-head">
                                            <span class="num text-accent" x-text="'BOM ID: ' + grp.bom_group"></span>
                                            <span class="pill pill-info" x-text="grp.process + ' · ' + grp.group_type"></span>
                                        </div>
                                        <template x-for="(inp, idx) in grp.items" :key="'inp-' + grp.bom_group + '-' + idx">
                                            <div class="rel">
                                                <div class="rel-t">
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <span class="ty" :class="tyClass(inp.type)" x-text="inp.type"></span>
                                                        <span class="rel-part num" x-text="inp.part_no !== '-' ? inp.part_no : ''"></span>
                                                    </div>
                                                    <span class="pill" :class="inp.is_trigger ? 'pill-good' : 'pill-neu'"
                                                          x-text="inp.is_trigger ? 'TRIGGER' : 'PENDAMPING'"></span>
                                                </div>
                                                <div class="font-medium break-words text-slate-900" x-text="inp.part_name"></div>
                                                <div class="rel-m num">
                                                    <span x-text="'Usage: ' + inp.qty_usage + ' ' + inp.uom"></span>
                                                    <span x-text="'Stok: ' + inp.current_stock + ' ' + inp.uom + ' (' + inp.ratio_stock + ' Hr)'"></span>
                                                </div>
                                                <div><span class="pill" :class="pillClass(inp.status_stock)" x-text="inp.status_stock"></span></div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!info?.inputs || info.inputs.length === 0">
                                    <div class="rel hint">Tidak memiliki material pembentuk di BOM (item awal / beli langsung).</div>
                                </template>
                            </div>
                        </div>

                        <div class="flow-ar"><x-icon name="chevron-right" /></div>

                        {{-- KOLOM TENGAH: ITEM INI --}}
                        <div>
                            <div class="flow-cap">
                                <span>ITEM INI</span>
                                <span class="count-chip count-chip-sub">FOKUS</span>
                            </div>
                            <div class="rel rel-now">
                                <div class="rel-t">
                                    <span class="ty" :class="tyClass(info?.type)" x-text="info?.type"></span>
                                    <span class="num text-accent" x-text="info?.part_no !== '-' ? info?.part_no : ''"></span>
                                </div>
                                <div class="font-semibold break-words text-slate-900" x-text="info?.part_name"></div>
                                <div class="rel-m num">
                                    <span x-text="'Stok: ' + (info?.current_stock ?? 0) + ' ' + (info?.uom ?? '')"></span>
                                    <span x-text="(info?.ratio_stock ?? 0) + ' Hari'"></span>
                                </div>
                                <div><span class="pill" :class="pillClass(info?.status_stock)" x-text="info?.status_stock"></span></div>
                            </div>
                        </div>

                        <div class="flow-ar"><x-icon name="chevron-right" /></div>

                        {{-- KOLOM KANAN: DIPAKAI DI (Dikelompokkan per BOM ID) --}}
                        <div>
                            <div class="flow-cap">
                                <span>DIPAKAI DI</span>
                                <span class="count-chip"><span x-text="(info?.outputs?.length || 0) + ' ITEM'"></span></span>
                            </div>
                            <div class="flow-col">
                                <template x-for="grp in outputGroups" :key="'out-grp-' + grp.bom_group">
                                    <div class="bom-grp-box">
                                        <div class="bom-grp-head">
                                            <span class="num text-accent" x-text="'BOM ID: ' + grp.bom_group"></span>
                                            <span class="pill pill-info" x-text="grp.process + ' · ' + grp.group_type"></span>
                                        </div>
                                        <template x-for="(out, idx) in grp.items" :key="'out-' + grp.bom_group + '-' + idx">
                                            <div class="rel">
                                                <div class="rel-t">
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <span class="ty" :class="tyClass(out.type)" x-text="out.type"></span>
                                                        <span class="rel-part num" x-text="out.part_no !== '-' ? out.part_no : ''"></span>
                                                    </div>
                                                    <span class="pill" :class="out.is_trigger ? 'pill-good' : 'pill-neu'"
                                                          x-text="out.is_trigger ? 'TRIGGER' : 'PENDAMPING'"></span>
                                                </div>
                                                <div class="font-medium break-words text-slate-900" x-text="out.part_name"></div>
                                                <div class="rel-m num">
                                                    <span x-text="'Usage: ' + out.qty_usage + ' ' + (info?.uom || '')"></span>
                                                    <span x-text="'Stok: ' + out.current_stock + ' ' + out.uom + ' (' + out.ratio_stock + ' Hr)'"></span>
                                                </div>
                                                <div><span class="pill" :class="pillClass(out.status_stock)" x-text="out.status_stock"></span></div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!info?.outputs || info.outputs.length === 0">
                                    <div class="rel hint">Produk akhir, tidak dipakai sebagai material untuk item lain.</div>
                                </template>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- RIWAYAT MUTASI STOK (LINIMASA) --}}
                <section>
                    <h4 class="sec-h flex items-center gap-2">
                        <span>Riwayat Penambahan &amp; Pengurangan Stok</span>
                        <span class="count-chip"><span x-text="filteredHistory.length + ' DARI ' + (info?.history?.length || 0) + ' TRANSAKSI'"></span></span>
                    </h4>

                    <div class="fbar">
                        <input type="text" x-model="histSearch" placeholder="Cari transaksi, lot, petugas..."
                               aria-label="Cari riwayat" class="h-8 text-xs px-2.5 flex-1" style="min-width: 160px">
                        <label>Tgl
                            <input type="date" x-model="histStartDate" aria-label="Dari tanggal"
                                   @change="if(histEndDate && histEndDate < histStartDate) histEndDate = histStartDate"
                                   class="h-8 text-xs px-2 w-32">
                            <span>–</span>
                            <input type="date" x-model="histEndDate" :min="histStartDate" aria-label="Sampai tanggal"
                                   class="h-8 text-xs px-2 w-32">
                        </label>
                        <label>Jam
                            <input type="time" x-model="histStartTime" aria-label="Dari jam" class="h-8 text-xs px-2 w-24">
                            <span>–</span>
                            <input type="time" x-model="histEndTime" aria-label="Sampai jam" class="h-8 text-xs px-2 w-24">
                        </label>
                        <button type="button" class="btn btn-sm"
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
                                                <span class="font-semibold" style="color: var(--ink)" x-text="h.type_label"></span>
                                                <span class="pill pill-info" x-show="h.status === 'TIDAK SESUAI'" x-text="'DIKOREKSI'"></span>
                                            </div>
                                            <div class="hint break-words" style="margin-top: 0" x-text="h.reference"></div>
                                            <div class="hint" style="margin-top: 0" x-text="h.user_name || '-'"></div>
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
                            Tidak ada riwayat mutasi stok pada filter ini.
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
                pillClass(status) {
                    if (status === 'EMPTY STOCK') return 'pill-empty';
                    if (status === 'VERY LOW') return 'pill-crit';
                    if (status === 'LOW STOCK') return 'pill-warn';
                    if (status === 'NORMAL STOCK') return 'pill-good';
                    if (status === 'OVERSTOCK') return 'pill-info';
                    return 'pill-neu';
                },
                compPill(t) {
                    if (t === 'CUSTOMER') return 'pill-good';
                    if (t === 'INTERNAL') return 'pill-warn';
                    return 'pill-info';
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
                statusExplain(info) {
                    if (!info) return '';
                    const s = info.status_stock;
                    if (s === 'EMPTY STOCK') return 'Stok fisik di gudang kosong (0). Segera lakukan produksi atau penerimaan material.';
                    if (s === 'VERY LOW') return 'Stok di bawah kebutuhan 1 hari (Std Day 1). Prioritas kritis untuk segera dipenuhi.';
                    if (s === 'LOW STOCK') return 'Stok berada di antara 1 s/d 2 hari (di bawah batas minimum Std Day 2).';
                    if (s === 'NORMAL STOCK') return 'Stok aman berada di rentang target buffer 2 s/d 3 hari (Std Day 2 – Std Day 3).';
                    if (s === 'OVERSTOCK') return 'Stok melebihi batas maksimum 3 hari (di atas Std Day 3).';
                    return '';
                },
                get inputGroups() {
                    if (!this.info || !Array.isArray(this.info.inputs)) return [];
                    const map = new Map();
                    this.info.inputs.forEach(item => {
                        const key = item.bom_group || 'DEFAULT';
                        if (!map.has(key)) {
                            map.set(key, {
                                bom_group: key,
                                process: item.process || '-',
                                group_type: item.group_type || 'SINGLE',
                                items: []
                            });
                        }
                        map.get(key).items.push(item);
                    });
                    return Array.from(map.values());
                },
                get outputGroups() {
                    if (!this.info || !Array.isArray(this.info.outputs)) return [];
                    const map = new Map();
                    this.info.outputs.forEach(item => {
                        const key = item.bom_group || 'DEFAULT';
                        if (!map.has(key)) {
                            map.set(key, {
                                bom_group: key,
                                process: item.process || '-',
                                group_type: item.group_type || 'SINGLE',
                                items: []
                            });
                        }
                        map.get(key).items.push(item);
                    });
                    return Array.from(map.values());
                },
                get scaleMax() {
                    if (!this.info) return 1;
                    return Math.max(Number(this.info.current_stock) || 0, Number(this.info.std_day_3) || 0, 1) * 1.15;
                },
                pct(v) {
                    return Math.min(100, Math.max(0, ((Number(v) || 0) / this.scaleMax) * 100)).toFixed(1);
                },
                dayLabel(d) {
                    const p = String(d).split('-');
                    return p.length === 3 ? (parseInt(p[2], 10) + ' ' + MON[parseInt(p[1], 10) - 1] + ' ' + p[0]) : d;
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