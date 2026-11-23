<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Form NG (Quality Control)"
                       subtitle="Pencatatan temuan barang cacat (Reject QC) per Nomor Lot aktif beserta pemotongan otomatis Act FG & Stok Gudang">
            <x-slot name="actions">
                <span class="btn-group">
                    <a href="{{ route('production_ngs.index', ['mode' => 'scanner']) }}" class="btn">
                        <x-icon name="scan" />
                        <span>Scan</span>
                    </a>
                    <button type="button" class="btn btn-primary" @click="$dispatch('open-add-ng')">
                        <x-icon name="plus" />
                        <span>Input NG</span>
                    </button>
                </span>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $lotsJs = $activeLots->map(fn ($lt) => [
            'id'        => $lt->id,
            'lot_no'    => $lt->lot_number,
            'part_no'   => $lt->targetItem->part_no ?: '-',
            'part_name' => $lt->targetItem->part_name ?? '-',
            'type'      => $lt->targetItem->type ?? 'FG',
            'uom'       => $lt->targetItem->uom ?? 'PCS',
            'act_prod'  => floatval($lt->actual_qty_prod),
            'qty_ng'    => floatval($lt->qty_ng),
            'act_fg'    => floatval($lt->act_fg),
            'stock'     => floatval($lt->targetItem?->inventoryStock?->current_stock ?? 0),
        ])->values();
    @endphp

    <div class="panel"
         x-data="prodNgPage({{ $ngs->count() }}, {{ Js::from($lotsJs) }}, {{ Js::from($reasons[0] ?? 'MATERIAL') }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-ng.window="addOpen = true; addError = ''"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Riwayat Pencatatan NG</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>DATA</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Lot / Part / Petugas QC..."
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

                <select x-model="fReason" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Problem Reason</option>
                    @foreach($reasons as $r)
                        <option value="{{ $r }}">{{ $r }}</option>
                    @endforeach
                </select>

                <button type="button"
                        x-show="q || startDate || endDate || startTime || endTime || fReason" x-cloak
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
                        <th class="w-[15%] col-time">Waktu Pencatatan</th>
                        <th class="w-[15%]">Petugas QC</th>
                        <th class="w-[12%] text-center">No. Lot</th>
                        <th class="w-[24%]">Part No &amp; Part Name</th>
                        <th class="w-[9%] text-center">Tipe</th>
                        <th class="w-[11%] text-center">Problem Reason</th>
                        <th class="num w-[9%]">Qty NG</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ngs as $idx => $ng)
                        @php
                            $dt       = \Carbon\Carbon::parse($ng->date_time);
                            $uName    = $ng->user->name ?? 'QC';
                            $partNo   = $ng->item->part_no ?: '-';
                            $partName = $ng->item->part_name ?? '-';
                            $itemType = $ng->item->type ?? '-';
                            $uom      = $ng->item->uom ?? 'PCS';
                            $tyCls    = 'ty-' . ($itemType === 'FG/KOMPONEN' ? 'FGK' : $itemType);
                        @endphp
                        <tr data-row
                            data-search="{{ strtolower($ng->lot_number . ' ' . $partNo . ' ' . $partName . ' ' . $uName . ' ' . $ng->problem_reason) }}"
                            data-date="{{ $dt->format('Y-m-d') }}"
                            data-time="{{ $dt->format('H:i') }}"
                            data-reason="{{ $ng->problem_reason }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500" data-col-no>{{ $idx + 1 }}</td>
                            <td class="num col-time text-left whitespace-nowrap">
                                <div class="font-semibold text-slate-900">{{ $dt->format('d M Y') }}</div>
                                <div class="text-xs text-slate-500">{{ $dt->format('H:i') }} WIB</div>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900">{{ $uName }}</div>
                                <div class="text-[11px] text-slate-500">{{ $ng->user->role ?? 'PPIC' }}</div>
                            </td>
                            <td class="text-center num font-bold text-accent">{{ $ng->lot_number }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $partNo }}</div>
                                <div class="font-semibold text-slate-900">{{ $partName }}</div>
                            </td>
                            <td class="text-center">
                                <span class="ty {{ $tyCls }}">{{ $itemType }}</span>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-neu">{{ $ng->problem_reason }}</span>
                            </td>
                            <td class="num whitespace-nowrap">
                                <span class="text-[13.5px] font-bold text-rose-700">-{{ floatval($ng->qty_ng) }}</span>
                                <span class="text-[11px] text-slate-500">{{ $uom }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty">Belum ada data pencatatan Production NG.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT NG (dua panel, panel kiri abu) --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false" @keydown.escape.window="addOpen = false">
            <div class="panel dlg w-full max-w-3xl" role="dialog" aria-label="Input Production NG">
                <div class="panel-head">
                    <h3 class="panel-title">Input Production NG (Reject QC)</h3>
                    <button type="button" class="act-btn" @click="addOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('production_ngs.store') }}" method="POST" novalidate
                      @submit.prevent="submitAdd($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    <div class="dlg-split">
                        <aside class="dlg-side hide-sm">
                            <div class="dlg-k">Ringkasan Lot Aktif</div>
                            <template x-if="selectedLot">
                                <div class="sumc">
                                    <div class="pill pill-info w-fit num" x-text="'LOT: ' + selectedLot.lot_no"></div>
                                    <div class="dlg-code num mt-1" x-text="selectedLot.part_no"></div>
                                    <div class="dlg-name" x-text="selectedLot.part_name"></div>
                                    <div class="mt-1">
                                        <span class="ty" :class="'ty-' + (selectedLot.type === 'FG/KOMPONEN' ? 'FGK' : selectedLot.type)" x-text="selectedLot.type"></span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!selectedLot">
                                <p class="hint">Pilih Nomor Lot yang sedang berjalan untuk melihat batas maksimal input NG (Act FG).</p>
                            </template>

                            <div class="dlg-list" x-show="selectedLot">
                                <div><span>Actual Prod</span><b class="num" x-text="(selectedLot?.act_prod ?? 0) + ' ' + (selectedLot?.uom || '')"></b></div>
                                <div><span>NG Tercatat</span><b class="num text-rose-700" x-text="(selectedLot?.qty_ng ?? 0) + ' ' + (selectedLot?.uom || '')"></b></div>
                                <div><span>Batas Input (Act FG)</span><b class="num text-emerald-700" x-text="(selectedLot?.act_fg ?? 0) + ' ' + (selectedLot?.uom || '')"></b></div>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <div x-show="addError" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="addError"></span>
                            </div>

                            <div>
                                <label class="lbl" for="ng_lot">Pilih Nomor Lot &amp; Target Item (ON PROGRESS)</label>
                                <select id="ng_lot" name="lot_tracer_id" x-model="selectedLotId" required class="w-full h-9 text-xs">
                                    <option value="">-- Pilih Nomor Lot --</option>
                                    @foreach($activeLots as $lt)
                                        <option value="{{ $lt->id }}">
                                            {{ $lt->lot_number }} — {{ $lt->targetItem->part_name ?? '-' }} (Act FG: {{ floatval($lt->act_fg) }} {{ $lt->targetItem->uom ?? 'PCS' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <span class="lbl">Problem Reason (Kategori Penyebab NG)</span>
                                <div class="opt-chips">
                                    @foreach($reasons as $r)
                                        <label>
                                            <input type="radio" name="problem_reason" value="{{ $r }}" x-model="selectedReason" required>
                                            <span>{{ $r }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label class="lbl" for="ng_qty">Qty NG (Maksimal sebesar Act FG)</label>
                                <div class="inp-calc">
                                    <input id="ng_qty" type="text"
                                           name="qty_ng" x-model="qtyNg" required
                                           placeholder="Contoh: 2 atau 3+2"
                                           class="num w-full h-9 font-semibold">
                                    <span class="inp-calc-ic" title="Mendukung kalkulator (+, -, *, /)">
                                        <x-icon name="calc" />
                                    </span>
                                </div>
                                <div class="calc-preview" x-show="String(qtyNg || '').trim() !== '' && evalCalc(qtyNg) !== null" x-cloak>
                                    Hasil: <b x-text="evalCalc(qtyNg)"></b> <span x-text="selectedLot?.uom || ''"></span>
                                </div>
                                <div class="calc-err" x-show="String(qtyNg || '').trim() !== '' && evalCalc(qtyNg) === null" x-cloak>
                                    <x-icon name="alert" />
                                    <span>Format perhitungan tidak valid</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Data NG</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function prodNgPage(initialCount, lotsList, defaultReason, openInitially) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fReason: '',
                visibleCount: initialCount,
                lotsList: lotsList,
                addOpen: openInitially,
                addError: '',
                selectedLotId: '',
                selectedReason: defaultReason || 'MATERIAL',
                qtyNg: '',

                get selectedLot() {
                    return this.lotsList.find(x => String(x.id) === String(this.selectedLotId)) || null;
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
                submitAdd(formEl) {
                    this.addError = '';
                    if (!this.selectedLotId || !this.selectedLot) {
                        this.addError = 'Nomor Lot wajib dipilih terlebih dahulu.';
                        return;
                    }
                    if (!this.selectedReason) {
                        this.addError = 'Problem Reason wajib dipilih.';
                        return;
                    }
                    if (String(this.qtyNg || '').trim() === '') {
                        this.addError = 'Qty NG wajib diisi.';
                        return;
                    }
                    const qty = this.evalCalc(this.qtyNg);
                    if (qty === null) {
                        this.addError = 'Format perhitungan tidak valid.';
                        return;
                    }
                    if (qty <= 0) {
                        this.addError = 'Qty NG harus lebih dari 0.';
                        return;
                    }
                    if (qty > this.selectedLot.act_fg + 0.0001) {
                        this.addError = 'Qty NG (' + qty + ') melebihi saldo Act FG pada Lot ' + this.selectedLot.lot_no + ' (maksimal ' + this.selectedLot.act_fg + ' ' + this.selectedLot.uom + ').';
                        return;
                    }
                    if (qty > this.selectedLot.stock + 0.0001) {
                        this.addError = 'Stok gudang ' + this.selectedLot.part_name + ' kurang (tersedia ' + this.selectedLot.stock + ' ' + this.selectedLot.uom + ').';
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
                    this.fReason = '';
                    this.recalc();
                },
                matchRow(el) {
                    const s = el.dataset.search || '';
                    const d = el.dataset.date || '';
                    const t = el.dataset.time || '';
                    const r = el.dataset.reason || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.startDate && d < this.startDate) return false;
                    if (this.endDate && d > this.endDate) return false;
                    if (this.startTime && t < this.startTime) return false;
                    if (this.endTime && t > this.endTime) return false;
                    if (this.fReason && r !== this.fReason) return false;
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