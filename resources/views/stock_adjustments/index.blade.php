<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Stock Adjustment"
                       subtitle="Penyesuaian saldo stok fisik terhadap stok sistem (Stock Opname, Loss, Surplus, Found, & Repair)">
            <x-slot name="actions">
                <span class="btn-group">
                    <a href="{{ route('stock_adjustments.index', ['mode' => 'scanner']) }}" class="btn">
                        <x-icon name="scan" />
                        <span>Scan</span>
                    </a>
                    <button type="button" class="btn btn-primary" @click="$dispatch('open-add-adj')">
                        <x-icon name="plus" />
                        <span>Input Adjustment</span>
                    </button>
                </span>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $itemsJs = $items->map(fn ($i) => [
            'id'    => $i->id,
            'part'  => ($i->part_no ? $i->part_no . ' — ' : '') . $i->part_name,
            'type'  => $i->type,
            'uom'   => $i->uom,
            'stock' => floatval($i->inventoryStock->current_stock ?? 0),
        ])->values();
    @endphp

    <div class="panel"
         x-data="stockAdjPage({{ $adjustments->count() }}, {{ Js::from($itemsJs) }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-adj.window="addOpen = true; addError = ''"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Riwayat Stock Adjustment</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>DATA</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Part / Petugas / Catatan..."
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

                <select x-model="fAdjType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Kategori</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>

                <button type="button"
                        x-show="q || startDate || endDate || startTime || endTime || fAdjType" x-cloak
                        @click="resetFilter()" class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[4%] text-center">No</th>
                        <th class="w-[14%] col-time">Waktu</th>
                        <th class="w-[13%]">Petugas</th>
                        <th class="w-[22%]">Part No &amp; Part Name</th>
                        <th class="w-[8%] text-center">Tipe</th>
                        <th class="w-[10%] text-center">Kategori</th>
                        <th class="num w-[8%]">Qty System</th>
                        <th class="num w-[8%]">Qty Actual</th>
                        <th class="num w-[8%]">Qty Diff</th>
                        <th class="w-[13%]">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adjustments as $idx => $adj)
                        @php
                            $dt       = \Carbon\Carbon::parse($adj->date_time);
                            $uName    = $adj->user->name ?? 'PPIC';
                            $partNo   = $adj->item->part_no ?: '-';
                            $partName = $adj->item->part_name ?? '-';
                            $itemType = $adj->item->type ?? '-';
                            $uom      = $adj->item->uom ?? '';
                            $diff     = floatval($adj->qty_diff);
                            $tyCls    = 'ty-' . ($itemType === 'FG/KOMPONEN' ? 'FGK' : $itemType);
                        @endphp
                        <tr data-row
                            data-search="{{ strtolower($partNo . ' ' . $partName . ' ' . $uName . ' ' . ($adj->notes ?? '') . ' ' . $adj->adjustment_type) }}"
                            data-date="{{ $dt->format('Y-m-d') }}"
                            data-time="{{ $dt->format('H:i') }}"
                            data-adjtype="{{ $adj->adjustment_type }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num col-time text-left whitespace-nowrap">
                                <div class="font-semibold text-slate-900">{{ $dt->format('d M Y') }}</div>
                                <div class="text-xs text-slate-500">{{ $dt->format('H:i') }} WIB</div>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900">{{ $uName }}</div>
                                <div class="text-[11px] text-slate-500">{{ $adj->user->role ?? 'PPIC' }}</div>
                            </td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $partNo }}</div>
                                <div class="font-semibold text-slate-900">{{ $partName }}</div>
                            </td>
                            <td class="text-center">
                                <span class="ty {{ $tyCls }}">{{ $itemType }}</span>
                            </td>
                            <td class="text-center">
                                @if($adj->adjustment_type === 'LOSS')
                                    <span class="pill pill-crit">LOSS</span>
                                @elseif(in_array($adj->adjustment_type, ['SURPLUS', 'FOUND', 'REPAIR']))
                                    <span class="pill pill-good">{{ $adj->adjustment_type }}</span>
                                @else
                                    <span class="pill pill-info">STOCK OPNAME</span>
                                @endif
                            </td>
                            <td class="num text-slate-600">{{ floatval($adj->qty_system) }} <span class="text-[11px] text-slate-400">{{ $uom }}</span></td>
                            <td class="num font-bold text-slate-900">{{ floatval($adj->qty_actual) }} <span class="text-[11px] font-normal text-slate-500">{{ $uom }}</span></td>
                            <td class="num whitespace-nowrap">
                                <span class="font-bold text-[13.5px] {{ $diff > 0 ? 'text-emerald-700' : ($diff < 0 ? 'text-rose-700' : 'text-slate-700') }}">
                                    {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                                </span>
                                <span class="text-[11px] text-slate-500">{{ $uom }}</span>
                            </td>
                            <td class="text-xs text-slate-600">{{ $adj->notes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="empty">Belum ada data Stock Adjustment.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT STOCK ADJUSTMENT (dua panel, panel kiri abu) --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false" @keydown.escape.window="addOpen = false">
            <div class="panel dlg w-full max-w-3xl" role="dialog" aria-label="Input Stock Adjustment Baru">
                <div class="panel-head">
                    <h3 class="panel-title">Input Stock Adjustment Baru</h3>
                    <button type="button" class="act-btn" @click="addOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('stock_adjustments.store') }}" method="POST" novalidate
                      @submit.prevent="submitAdd($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    <div class="dlg-split">
                        <aside class="dlg-side hide-sm">
                            <div class="dlg-k">Ringkasan Koreksi</div>
                            <template x-if="selectedItem">
                                <div class="sumc">
                                    <div class="dlg-name" x-text="selectedItem.part"></div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="ty" :class="'ty-' + (selectedItem.type === 'FG/KOMPONEN' ? 'FGK' : selectedItem.type)" x-text="selectedItem.type"></span>
                                        <span class="pill pill-neu num" x-text="selectedItem.uom"></span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!selectedItem">
                                <p class="hint">Pilih item untuk melihat selisih stok sistem dan stok fisik.</p>
                            </template>
                            <div class="dlg-list">
                                <div><span>Stok Sistem</span><b class="num" x-text="qtySystem + ' ' + (selectedItem?.uom || '')"></b></div>
                                <div><span>Qty Actual</span><b class="num" x-text="resultActual + ' ' + (selectedItem?.uom || '')"></b></div>
                            </div>
                            <div>
                                <div class="dlg-k">Selisih (Qty Diff)</div>
                                <div class="num" style="font-size: 28px; font-weight: 700; line-height: 1.1; margin-top: 4px;"
                                     :style="resultDiff > 0 ? 'color: var(--good)' : (resultDiff < 0 ? 'color: var(--crit)' : 'color: var(--ink)')"
                                     x-text="(resultDiff > 0 ? '+' : '') + resultDiff + ' ' + (selectedItem?.uom || '')"></div>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <div x-show="addError" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="addError"></span>
                            </div>

                            <div>
                                <label class="lbl" for="adj_item">Pilih Item</label>
                                <select id="adj_item" name="item_id" x-model="selectedItemId" @change="onSelectItem()" required class="w-full h-9 text-xs">
                                    <option value="">-- Pilih Item --</option>
                                    @foreach($items as $it)
                                        <option value="{{ $it->id }}">
                                            {{ ($it->part_no ? $it->part_no . ' — ' : '') . $it->part_name }} (Stok: {{ floatval($it->inventoryStock->current_stock ?? 0) }} {{ $it->uom }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <span class="lbl">Kategori Adjustment</span>
                                <div class="opt-chips">
                                    @foreach($types as $t)
                                        <label>
                                            <input type="radio" name="adjustment_type" value="{{ $t }}" x-model="adjType" required>
                                            <span>{{ $t }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="lbl" for="adj_actual">Qty Actual / Fisik</label>
                                    <div class="inp-calc">
                                        <input id="adj_actual" type="text" name="qty_actual" x-model="rawActual" @input="onInputActual()"
                                               placeholder="Contoh: 120 atau 3*40" class="num w-full h-9 font-semibold">
                                        <span class="inp-calc-ic" title="Mendukung perhitungan kalkulator (+, -, *, /)">
                                            <x-icon name="calc" />
                                        </span>
                                    </div>
                                    <div class="calc-preview" x-show="String(rawActual || '').trim() !== '' && evalCalc(rawActual) !== null" x-cloak>
                                        Hasil: <b x-text="evalCalc(rawActual)"></b> <span x-text="selectedItem?.uom || ''"></span>
                                    </div>
                                    <div class="calc-err" x-show="String(rawActual || '').trim() !== '' && evalCalc(rawActual) === null" x-cloak>
                                        <x-icon name="alert" />
                                        <span>Format perhitungan tidak valid</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="lbl" for="adj_diff">Qty Diff / Selisih (+/-)</label>
                                    <div class="inp-calc">
                                        <input id="adj_diff" type="text" name="qty_diff" x-model="rawDiff" @input="onInputDiff()"
                                               placeholder="Contoh: -5 atau +10" class="num w-full h-9 font-semibold">
                                        <span class="inp-calc-ic" title="Mendukung perhitungan kalkulator (+, -, *, /)">
                                            <x-icon name="calc" />
                                        </span>
                                    </div>
                                    <div class="calc-preview" x-show="String(rawDiff || '').trim() !== '' && evalCalc(rawDiff) !== null" x-cloak>
                                        Hasil: <b x-text="(evalCalc(rawDiff) > 0 ? '+' : '') + evalCalc(rawDiff)"></b> <span x-text="selectedItem?.uom || ''"></span>
                                    </div>
                                    <div class="calc-err" x-show="String(rawDiff || '').trim() !== '' && evalCalc(rawDiff) === null" x-cloak>
                                        <x-icon name="alert" />
                                        <span>Format perhitungan tidak valid</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="lbl" for="adj_notes">Catatan / Keterangan (Opsional)</label>
                                <input id="adj_notes" type="text" name="notes" placeholder="Contoh: Koreksi hasil stock opname bulanan" class="w-full h-9 text-xs">
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Adjustment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function stockAdjPage(initialCount, itemsList, openInitially) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fAdjType: '',
                visibleCount: initialCount,
                itemsList: itemsList,
                addOpen: openInitially,
                addError: '',
                selectedItemId: '',
                adjType: 'STOCK OPNAME',
                qtySystem: 0,
                rawActual: '',
                rawDiff: '',
                previewText: '',

                get selectedItem() {
                    return this.itemsList.find(x => String(x.id) === String(this.selectedItemId)) || null;
                },
                get resultActual() {
                    const a = this.evalCalc(this.rawActual);
                    return a === null ? this.qtySystem : a;
                },
                get resultDiff() {
                    const d = this.evalCalc(this.rawDiff);
                    return d === null ? 0 : d;
                },
                evalCalc(raw) {
                    if (raw === null || raw === undefined) return null;
                    const expr = String(raw).replace(/,/g, '.').replace(/[xX]/g, '*').replace(/:/g, '/').replace(/\s+/g, '');
                    if (!expr || !/^[0-9.+\-*\/()]+$/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r)) ? Math.round(r * 100) / 100 : null;
                    } catch (e) {
                        return null;
                    }
                },
                onSelectItem() {
                    const it = this.selectedItem;
                    this.qtySystem = it ? it.stock : 0;
                    if (this.rawActual !== '') {
                        this.onInputActual();
                    } else if (this.rawDiff !== '') {
                        this.onInputDiff();
                    }
                },
                onInputActual() {
                    const val = this.evalCalc(this.rawActual);
                    if (val !== null) {
                        const diff = Math.round((val - this.qtySystem) * 100) / 100;
                        this.rawDiff = (diff > 0 ? '+' : '') + diff;
                    }
                },
                onInputDiff() {
                    const val = this.evalCalc(this.rawDiff);
                    if (val !== null) {
                        const act = Math.round((this.qtySystem + val) * 100) / 100;
                        this.rawActual = String(act);
                    }
                },
                submitAdd(formEl) {
                    this.addError = '';
                    if (!this.selectedItemId || !this.selectedItem) {
                        this.addError = 'Item wajib dipilih terlebih dahulu.';
                        return;
                    }
                    if (!this.adjType) {
                        this.addError = 'Kategori Adjustment wajib dipilih.';
                        return;
                    }

                    const hasActual = String(this.rawActual || '').trim() !== '';
                    const hasDiff = String(this.rawDiff || '').trim() !== '';
                    if (!hasActual && !hasDiff) {
                        this.addError = 'Qty Actual atau Qty Diff wajib diisi.';
                        return;
                    }

                    const parsedActual = hasActual ? this.evalCalc(this.rawActual) : null;
                    const parsedDiff = hasDiff ? this.evalCalc(this.rawDiff) : null;

                    if (hasActual && parsedActual === null) {
                        this.addError = 'Format perhitungan Qty Actual tidak valid.';
                        return;
                    }
                    if (hasDiff && parsedDiff === null) {
                        this.addError = 'Format perhitungan Qty Diff tidak valid.';
                        return;
                    }

                    let qtyActual, qtyDiff;
                    if (parsedActual !== null) {
                        qtyActual = parsedActual;
                        qtyDiff = Math.round((qtyActual - this.qtySystem) * 100) / 100;
                    } else {
                        qtyDiff = parsedDiff;
                        qtyActual = Math.round((this.qtySystem + qtyDiff) * 100) / 100;
                    }

                    if (qtyActual < 0) {
                        this.addError = 'Ditolak: Qty Actual tidak boleh bernilai minus.';
                        return;
                    }

                    if (this.adjType === 'LOSS' && qtyActual >= this.qtySystem) {
                        this.addError = 'Ditolak: Untuk kategori LOSS, Qty Actual (' + qtyActual + ') wajib lebih kecil dari Qty System (' + this.qtySystem + ').';
                        return;
                    }

                    if (['SURPLUS', 'FOUND', 'REPAIR'].includes(this.adjType) && qtyActual <= this.qtySystem) {
                        this.addError = 'Ditolak: Untuk kategori ' + this.adjType + ', Qty Actual (' + qtyActual + ') wajib lebih besar dari Qty System (' + this.qtySystem + ').';
                        return;
                    }

                    if (this.adjType === 'STOCK OPNAME' && Math.abs(qtyDiff) < 0.0001) {
                        this.addError = 'Ditolak: Tidak ada perubahan kuantitas (Qty Diff = 0).';
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
                    this.fAdjType = '';
                    this.recalc();
                },
                matchRow(el) {
                    const s = el.dataset.search || '';
                    const d = el.dataset.date || '';
                    const t = el.dataset.time || '';
                    const at = el.dataset.adjtype || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.startDate && d < this.startDate) return false;
                    if (this.endDate && d > this.endDate) return false;
                    if (this.startTime && t < this.startTime) return false;
                    if (this.endTime && t > this.endTime) return false;
                    if (this.fAdjType && at !== this.fAdjType) return false;
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