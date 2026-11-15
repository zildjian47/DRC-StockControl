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
            {{-- PANEL KIRI (abu): Identitas + Parameter Stok --}}
            <aside class="dlg-side">
                <div>
                    <div class="dlg-code num" x-text="info?.part_no !== '-' ? info?.part_no : ''"></div>
                    <div class="dlg-name" x-text="info?.part_name"></div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="ty" :class="tyClass(info?.type)" x-text="info?.type"></span>
                    <span class="num text-xs font-semibold" style="color: var(--ink2)" x-text="info?.uom"></span>
                    <span class="text-xs font-semibold" style="color: var(--ink)" x-text="info?.company"></span>
                </div>

                <div>
                    <div class="dlg-k">Stok saat ini</div>
                    <div class="num" style="font-size: 22px; font-weight: 600; line-height: 1.2"
                         x-text="(info?.current_stock ?? 0) + ' ' + (info?.uom ?? '')"></div>
                    <div class="sbar">
                        <i :style="'width:' + pct(info?.current_stock) + '%; background:' + barColor(info?.status_stock)"></i>
                        <u :style="'left:' + pct(info?.std_day_2) + '%'"></u>
                        <u :style="'left:' + pct(info?.std_day_3) + '%'"></u>
                    </div>
                    <div class="sbar-lbl">
                        <span x-text="'Min ' + (info?.std_day_2 ?? 0)"></span>
                        <span x-text="'Max ' + (info?.std_day_3 ?? 0)"></span>
                    </div>
                </div>

                <div>
                    <div class="dlg-k">Std Day 1 / 2 / 3</div>
                    <div class="num text-xs font-semibold mt-1" style="color: var(--ink)"
                         x-text="(info?.std_day_1 ?? 0) + ' / ' + (info?.std_day_2 ?? 0) + ' / ' + (info?.std_day_3 ?? 0)"></div>
                </div>
                <div>
                    <div class="dlg-k">Ratio stock</div>
                    <div class="num text-sm font-bold mt-1" style="color: var(--accent)" x-text="(info?.ratio_stock ?? 0) + ' Hari'"></div>
                </div>
                <div>
                    <span class="pill" :class="pillClass(info?.status_stock)" x-text="info?.status_stock"></span>
                </div>
            </aside>

            {{-- PANEL KANAN: Alur BOM 3 Kolom + Linimasa Riwayat Mutasi --}}
            <div class="dlg-main">
                {{-- ALUR BOM --}}
                <section>
                    <h4 class="sec-h">Alur BOM</h4>
                    <div class="flow">
                        {{-- Material masuk --}}
                        <div>
                            <div class="flow-cap" x-text="'Material masuk (' + (info?.inputs?.length || 0) + ')'"></div>
                            <div class="flow-col">
                                <template x-for="(inp, idx) in (info?.inputs || [])" :key="'inp-' + idx">
                                    <div class="rel">
                                        <div class="rel-t">
                                            <span x-text="inp.process"></span>
                                            <span class="pill" :class="inp.is_trigger ? 'pill-good' : 'pill-neu'" x-text="inp.is_trigger ? 'Trigger' : 'Pendamping'"></span>
                                        </div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="ty" :class="tyClass(inp.type)" x-text="inp.type"></span>
                                            <span class="rel-part num" x-text="inp.part_no !== '-' ? inp.part_no : ''"></span>
                                        </div>
                                        <div class="font-medium break-words text-xs" x-text="inp.part_name"></div>
                                        <div class="rel-m num">
                                            <span x-text="'Usage: ' + inp.qty_usage + ' ' + inp.uom"></span>
                                            <span x-text="'Stok: ' + inp.current_stock + ' ' + inp.uom + ' (' + inp.ratio_stock + ' Hr)'"></span>
                                        </div>
                                        <div><span class="pill" :class="pillClass(inp.status_stock)" x-text="inp.status_stock"></span></div>
                                    </div>
                                </template>
                                <template x-if="!info?.inputs || info.inputs.length === 0">
                                    <div class="rel hint">Tidak memiliki komponen pembentuk di BOM.</div>
                                </template>
                            </div>
                        </div>

                        <div class="flow-ar"><x-icon name="chevron-right" /></div>

                        {{-- Item ini --}}
                        <div>
                            <div class="flow-cap">Item ini</div>
                            <div class="rel rel-now">
                                <div class="rel-t"><span class="num" x-text="info?.part_no !== '-' ? info?.part_no : info?.type"></span></div>
                                <div class="font-medium break-words text-xs" x-text="info?.part_name"></div>
                                <div class="rel-m num">
                                    <span x-text="'Stok ' + (info?.current_stock ?? 0) + ' ' + (info?.uom ?? '')"></span>
                                    <span x-text="(info?.ratio_stock ?? 0) + ' Hr'"></span>
                                </div>
                                <div><span class="pill" :class="pillClass(info?.status_stock)" x-text="info?.status_stock"></span></div>
                            </div>
                        </div>

                        <div class="flow-ar"><x-icon name="chevron-right" /></div>

                        {{-- Dipakai di --}}
                        <div>
                            <div class="flow-cap" x-text="'Dipakai di (' + (info?.outputs?.length || 0) + ')'"></div>
                            <div class="flow-col">
                                <template x-for="(out, idx) in (info?.outputs || [])" :key="'out-' + idx">
                                    <div class="rel">
                                        <div class="rel-t">
                                            <span x-text="out.process"></span>
                                            <span class="pill pill-info" x-text="out.group_type"></span>
                                        </div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="ty" :class="tyClass(out.type)" x-text="out.type"></span>
                                            <span class="rel-part num" x-text="out.part_no !== '-' ? out.part_no : ''"></span>
                                        </div>
                                        <div class="font-medium break-words text-xs" x-text="out.part_name"></div>
                                        <div class="rel-m num">
                                            <span x-text="'Usage: ' + out.qty_usage"></span>
                                            <span x-text="'Stok: ' + out.current_stock + ' ' + out.uom + ' (' + out.ratio_stock + ' Hr)'"></span>
                                        </div>
                                        <div><span class="pill" :class="pillClass(out.status_stock)" x-text="out.status_stock"></span></div>
                                    </div>
                                </template>
                                <template x-if="!info?.outputs || info.outputs.length === 0">
                                    <div class="rel hint">Produk akhir, tidak dipakai sebagai material part lain.</div>
                                </template>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- RIWAYAT MUTASI STOK (LINIMASA) --}}
                <section>
                    <h4 class="sec-h flex items-center gap-2">
                        <span>Riwayat penambahan &amp; pengurangan stok</span>
                        <span class="count-chip"><span x-text="filteredHistory.length"></span><span x-text="'dari ' + (info?.history?.length || 0)"></span></span>
                    </h4>

                    <div class="fbar">
                        <input type="text" x-model="histSearch" placeholder="Cari transaksi, lot, petugas"
                               aria-label="Cari riwayat" class="h-8 text-xs px-2.5 flex-1" style="min-width: 150px">
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
                                    <span x-text="g.rows.length + ' transaksi'"></span>
                                </div>
                                <template x-for="(h, i) in g.rows" :key="g.date + '-' + i">
                                    <div class="tl-item" :class="h.qty_change >= 0 ? 'tl-in' : 'tl-out'">
                                        <div class="tl-time" x-text="h.time"></div>
                                        <div class="tl-dot"><i></i></div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-semibold text-xs" style="color: var(--ink)" x-text="h.type_label"></span>
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
                tyClass(t) {
                    return 'ty-' + (t === 'FG/KOMPONEN' ? 'FGK' : t);
                },
                barColor(status) {
                    const m = {
                        'EMPTY STOCK': '#334155',
                        'VERY LOW': 'var(--crit)',
                        'LOW STOCK': 'var(--warn)',
                        'NORMAL STOCK': 'var(--good)',
                        'OVERSTOCK': 'var(--accent)'
                    };
                    return m[status] || 'var(--mut)';
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