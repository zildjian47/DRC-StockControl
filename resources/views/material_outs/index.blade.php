<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Material Out"
                       subtitle="Riwayat pengeluaran material ke produksi (Trigger & Komponen Pendamping) beserta pembukaan Lot Tracer">
            <x-slot name="actions">
                <a href="{{ route('material_outs.index', ['mode' => 'scanner']) }}" class="btn btn-primary">
                    <x-icon name="scan" />
                    <span>Scan</span>
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="matOutPage({{ $groupedOuts->count() }})"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Transaksi Material Out</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>DATA</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Lot / Material / Output / Petugas..."
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

                <select x-model="fBomType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe BOM</option>
                    <option value="SINGLE">SINGLE</option>
                    <option value="MULTI-INPUT">MULTI-INPUT</option>
                    <option value="MULTI-OUTPUT">MULTI-OUTPUT</option>
                    <option value="KOMBINASI">KOMBINASI</option>
                </select>

                <select x-model="fStatus" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Status</option>
                    <option value="TERCATAT">TERCATAT</option>
                    <option value="DIKOREKSI">DIKOREKSI</option>
                </select>

                <button type="button"
                        x-show="q || startDate || endDate || startTime || endTime || fBomType || fStatus" x-cloak
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
                        <th class="w-[12%] col-time">Tanggal &amp; Waktu</th>
                        <th class="w-[11%]">Petugas Scan</th>
                        <th class="w-[12%]">No. Lot &amp; Tipe BOM</th>
                        <th class="w-[23%]">Material &amp; Pendamping</th>
                        <th class="num w-[11%]">Qty Out</th>
                        <th class="w-[16%]">Output Item</th>
                        <th class="w-[6%] text-center">Status</th>
                        <th class="w-[5%] text-center">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($groupedOuts as $idx => $grp)
                        @php
                            $first       = $grp['first'];
                            $dt          = \Carbon\Carbon::parse($first->date_time);
                            $bType       = $grp['bom_type'];
                            $matItem     = $first->materialItem;
                            $uName       = $first->user->name ?? 'Operator';
                            $currStock   = floatval($stockMap[$first->material_item_id] ?? 0);
                            $minRequired = floatval($minRequiredMap[$grp['lot_number'] . '|' . $first->material_item_id] ?? 0);

                            $editPayload = [
                                'id'               => $first->id,
                                'lot'              => $grp['lot_number'],
                                'part_no'          => $matItem->part_no ?: '-',
                                'part_name'        => $matItem->part_name ?? '-',
                                'qty'              => floatval($grp['qty_trigger']),
                                'uom'              => $matItem->uom ?? '',
                                'stock'            => $currStock,
                                'min_required'     => $minRequired,
                                'lot_is_finish'    => (bool) ($grp['lot_is_finish'] ?? false),
                                'status'           => $grp['status_label'],
                                'qty_original'     => $grp['qty_original'] ?? null,
                                'corrected_by'     => $grp['corrected_by_name'] ?? null,
                                'corrected_at_fmt' => $grp['corrected_at_fmt'] ?? null,
                                'correction_notes' => $grp['correction_notes'] ?? null,
                            ];
                        @endphp
                        <tr data-row
                            data-search="{{ $grp['search_index'] }}"
                            data-date="{{ $grp['date_only'] }}"
                            data-time="{{ $grp['time_only'] }}"
                            data-bomtype="{{ $bType }}"
                            data-status="{{ $grp['status_label'] }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500" data-col-no>{{ $idx + 1 }}</td>
                            <td class="num col-time text-left whitespace-nowrap">
                                <div class="font-semibold text-slate-900">{{ $dt->format('d M Y') }}</div>
                                <div class="text-xs text-slate-500">{{ $dt->format('H:i') }} WIB</div>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900">{{ $uName }}</div>
                                <div class="text-[11px] text-slate-500">{{ $first->user->role ?? 'STAFF' }}</div>
                            </td>
                            <td>
                                <div class="num font-bold text-accent">{{ $grp['lot_number'] }}</div>
                                <div class="mt-1">
                                    @if($bType === 'SINGLE')
                                        <span class="pill pill-neu">SINGLE</span>
                                    @elseif($bType === 'MULTI-INPUT')
                                        <span class="pill pill-info">MULTI-INPUT</span>
                                    @elseif($bType === 'MULTI-OUTPUT')
                                        <span class="pill pill-warn">MULTI-OUTPUT</span>
                                    @else
                                        <span class="pill pill-good">KOMBINASI</span>
                                    @endif
                                </div>
                            </td>

                            {{-- KOLOM MATERIAL & PENDAMPING --}}
                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="pill pill-good w-[88px] justify-center flex-none">TRIGGER</span>
                                            <div class="min-w-0 leading-tight">
                                                <div class="num text-[11px] font-bold text-accent truncate">{{ $matItem->part_no ?: '-' }}</div>
                                                <div class="text-xs font-semibold text-slate-900 truncate">{{ $matItem->part_name ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <span class="num text-[11px] font-semibold text-slate-500 flex-none">{{ $matItem->uom ?? '' }}</span>
                                    </div>

                                    @foreach($grp['companions'] as $comp)
                                        <div class="subrow justify-between gap-2 bg-slate-50/60">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="pill pill-neu w-[88px] justify-center flex-none">PENDAMPING</span>
                                                <div class="min-w-0 leading-tight">
                                                    <div class="num text-[11px] font-bold text-slate-600 truncate">{{ $comp['part_no'] }}</div>
                                                    <div class="text-xs font-medium text-slate-700 truncate">{{ $comp['part_name'] }}</div>
                                                </div>
                                            </div>
                                            <span class="num text-[11px] font-semibold text-slate-500 flex-none">{{ $comp['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM QTY OUT SEJAJAR BARIS DEMI BARIS --}}
                            <td class="p-0 num">
                                <div class="divide-y divide-slate-200">
                                    <div class="subrow-qty">
                                        <span class="text-[13.5px] font-bold text-rose-700">-{{ floatval($grp['qty_trigger']) }}</span>
                                        <span class="text-[11px] text-slate-500">{{ $matItem->uom ?? '' }}</span>
                                    </div>
                                    @foreach($grp['companions'] as $comp)
                                        <div class="subrow-qty bg-slate-50/60">
                                            <span class="text-xs font-semibold text-rose-600">-{{ floatval($comp['qty']) }}</span>
                                            <span class="text-[11px] text-slate-500">{{ $comp['uom'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- KOLOM OUTPUT ITEM --}}
                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['outputs'] as $out)
                                        <div class="px-3 py-2 leading-tight">
                                            <div class="num text-[11px] font-bold text-accent">{{ $out['part_no'] }}</div>
                                            <div class="text-xs font-semibold text-slate-900">{{ $out['part_name'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="text-center">
                                @if($grp['status_label'] === 'DIKOREKSI')
                                    <span class="pill pill-info" title="{{ !empty($grp['corrected_by_name']) ? 'Dikoreksi oleh ' . $grp['corrected_by_name'] : 'DIKOREKSI' }}">DIKOREKSI</span>
                                @else
                                    <span class="pill pill-good">TERCATAT</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="act-btn" title="Koreksi Qty Material Out"
                                        @click="openEdit({{ Js::from($editPayload) }})">
                                    <x-icon name="edit" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty">Belum ada data transaksi Material Out.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL KOREKSI QTY MATERIAL OUT (2 Panel Abu) --}}
        <div x-show="editOpen" x-cloak class="modal-backdrop" @click.self="editOpen = false" @keydown.escape.window="editOpen = false">
            <div class="panel dlg w-full max-w-2xl" role="dialog" aria-label="Koreksi Qty Material Out">
                <div class="panel-head">
                    <h3 class="panel-title">Koreksi Qty Material Out</h3>
                    <button type="button" class="act-btn" @click="editOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('material_outs.verify_batch') }}" method="POST" novalidate
                      @submit.prevent="submitEdit($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    <div class="dlg-split">
                        <aside class="dlg-side">
                            <div class="dlg-k">Lot &amp; Material Trigger</div>
                            <div class="sumc">
                                <div class="pill pill-info w-fit num" x-text="'LOT: ' + editLot"></div>
                                <div class="dlg-code num mt-1" x-text="editPartNo"></div>
                                <div class="dlg-name" x-text="editPartName"></div>
                            </div>
                            <div class="dlg-list">
                                <div><span>Qty Out Awal</span><b class="num" x-text="editOldQty + ' ' + editUom"></b></div>
                                <div><span>Qty Out Koreksi</span><b class="num text-accent" x-text="(evalCalc(editNewQty) ?? editOldQty) + ' ' + editUom"></b></div>
                            </div>

                            <div x-show="editStatus === 'DIKOREKSI'" x-cloak class="sumc text-xs space-y-1">
                                <div class="dlg-k">Riwayat Koreksi</div>
                                <div x-show="editQtyOriginal !== null">
                                    <span class="text-slate-500">Qty Awal:</span>
                                    <b class="num text-slate-900" x-text="editQtyOriginal + ' ' + editUom"></b>
                                </div>
                                <div x-show="editCorrectedBy">
                                    <span class="text-slate-500">Oleh:</span>
                                    <b class="text-slate-900" x-text="editCorrectedBy"></b>
                                </div>
                                <div x-show="editCorrectedAtFmt" class="num text-[11px] text-slate-500" x-text="editCorrectedAtFmt"></div>
                                <div x-show="editSavedNotes" class="text-[11px] text-slate-700 break-words" x-text="'Alasan: ' + editSavedNotes"></div>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <div x-show="editLotIsFinish" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="'Lot ' + editLot + ' sudah berstatus FINISH sehingga Material Out tidak dapat dikoreksi.'"></span>
                            </div>

                            <div x-show="editError && !editLotIsFinish" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="editError"></span>
                            </div>

                            <input type="hidden" :name="'items[' + editId + '][qty_check]'" value="DIKOREKSI">
                            <div>
                                <label class="lbl" for="mo_actual_qty">Qty Material Out (Trigger) Sebenarnya</label>
                                <div class="inp-calc">
                                    <input id="mo_actual_qty" type="text"
                                           :name="'items[' + editId + '][actual_qty]'"
                                           x-model="editNewQty" required
                                           :disabled="editLotIsFinish"
                                           placeholder="Contoh: 25 atau 5*5"
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

                            <div>
                                <label class="lbl" for="mo_corr_notes">Alasan Koreksi <span style="font-weight:400;color:var(--mut)">(Opsional)</span></label>
                                <input id="mo_corr_notes" type="text"
                                       :name="'items[' + editId + '][correction_notes]'"
                                       x-model="editNotes"
                                       :disabled="editLotIsFinish"
                                       placeholder="Contoh: Revisi berat coil / lembar keluar ke mesin"
                                       maxlength="500"
                                       class="w-full h-9 text-xs">
                            </div>

                            <div class="alert alert-info text-xs leading-relaxed">
                                <x-icon name="info" />
                                <div>Koreksi ini otomatis menyesuaikan stok <b>Material Trigger</b>, stok <b>Komponen Pendamping</b> secara proporsional, serta <b>Target Estimasi &amp; WIP</b> di Lot Tracer dan mencatat jejak audit koreksi.</div>
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
        function matOutPage(initialCount) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fBomType: '',
                fStatus: '',
                visibleCount: initialCount,
                editOpen: false,
                editError: '',
                editId: null,
                editLot: '',
                editPartNo: '',
                editPartName: '',
                editOldQty: 0,
                editNewQty: '',
                editUom: '',
                editStock: 0,
                editMinRequired: 0,
                editLotIsFinish: false,
                editStatus: 'TERCATAT',
                editQtyOriginal: null,
                editCorrectedBy: null,
                editCorrectedAtFmt: null,
                editSavedNotes: null,
                editNotes: '',

                openEdit(p) {
                    this.editId = p.id;
                    this.editLot = p.lot;
                    this.editPartNo = p.part_no;
                    this.editPartName = p.part_name;
                    this.editOldQty = Number(p.qty) || 0;
                    this.editNewQty = String(p.qty);
                    this.editUom = p.uom;
                    this.editStock = Number(p.stock) || 0;
                    this.editMinRequired = Number(p.min_required) || 0;
                    this.editLotIsFinish = Boolean(p.lot_is_finish);
                    this.editStatus = p.status || 'TERCATAT';
                    this.editQtyOriginal = p.qty_original ?? null;
                    this.editCorrectedBy = p.corrected_by ?? null;
                    this.editCorrectedAtFmt = p.corrected_at_fmt ?? null;
                    this.editSavedNotes = p.correction_notes ?? null;
                    this.editNotes = p.correction_notes || '';
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
                        this.editError = 'Lot ' + this.editLot + ' sudah berstatus FINISH sehingga Material Out tidak dapat dikoreksi.';
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
                    if (diff < 0 && val < this.editMinRequired - 0.0001) {
                        this.editError = 'Qty koreksi tidak boleh lebih kecil dari total material yang sudah diretur dan terpakai produksi (minimal ' + this.editMinRequired + ' ' + this.editUom + ').';
                        return;
                    }
                    if (diff > 0 && diff > this.editStock + 0.0001) {
                        this.editError = 'Stok Material Trigger (' + this.editPartName + ') tidak cukup untuk koreksi (tersedia ' + this.editStock + ' ' + this.editUom + ').';
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
                    this.fBomType = '';
                    this.fStatus = '';
                    this.recalc();
                },
                matchRow(el) {
                    const s = el.dataset.search || '';
                    const d = el.dataset.date || '';
                    const t = el.dataset.time || '';
                    const bt = el.dataset.bomtype || '';
                    const st = el.dataset.status || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.startDate && d < this.startDate) return false;
                    if (this.endDate && d > this.endDate) return false;
                    if (this.startTime && t < this.startTime) return false;
                    if (this.endTime && t > this.endTime) return false;
                    if (this.fBomType && bt !== this.fBomType) return false;
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