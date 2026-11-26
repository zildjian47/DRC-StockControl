<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Form NG (Quality Control)"
                       subtitle="Pencatatan temuan barang cacat (Reject QC) per Nomor Lot aktif beserta koreksi PPIC & pemotongan otomatis Act FG">
            <x-slot name="actions">
                <span class="btn-group">
                    <button type="button" class="btn" @click="$dispatch('export-prod-ng', 'xlsx')"
                            title="Export data Form NG sesuai filter aktif ke Excel (.xlsx)">
                        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        <span>Excel</span>
                    </button>
                    <button type="button" class="btn" @click="$dispatch('export-prod-ng', 'pdf')"
                            title="Export laporan Form NG sesuai filter aktif ke PDF">
                        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                        <span>PDF</span>
                    </button>
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
         x-data="prodNgPage({{ $ngs->count() }}, {{ Js::from($lotsJs) }}, {{ Js::from($reasons[0] ?? 'MATERIAL') }}, {{ $errors->any() ? 'true' : 'false' }}, {{ Js::from(route('exports.production_ngs')) }})"
         @open-add-ng.window="addOpen = true; addError = ''"
         @export-prod-ng.window="exportData($event.detail)"
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

                <select x-model="fStatus" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Status</option>
                    <option value="TERCATAT">TERCATAT</option>
                    <option value="DIKOREKSI">DIKOREKSI</option>
                </select>

                <button type="button"
                        x-show="q || startDate || endDate || startTime || endTime || fReason || fStatus" x-cloak
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
                        <th class="w-[13%] col-time">Waktu Pencatatan</th>
                        <th class="w-[13%]">Petugas QC</th>
                        <th class="w-[11%] text-center">No. Lot</th>
                        <th class="w-[21%]">Part No &amp; Part Name</th>
                        <th class="w-[8%] text-center">Tipe</th>
                        <th class="w-[10%] text-center">Problem Reason</th>
                        <th class="num w-[8%]">Qty NG</th>
                        <th class="w-[7%] text-center">Status</th>
                        <th class="w-[5%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ngs as $idx => $ng)
                        @php
                            $dt            = \Carbon\Carbon::parse($ng->date_time);
                            $statusText    = $ng->qty_check === 'DIKOREKSI' ? 'DIKOREKSI' : 'TERCATAT';
                            $uName         = $ng->user->name ?? 'QC';
                            $partNo        = $ng->item->part_no ?: '-';
                            $partName      = $ng->item->part_name ?? '-';
                            $itemType      = $ng->item->type ?? '-';
                            $uom           = $ng->item->uom ?? 'PCS';
                            $tyCls         = 'ty-' . ($itemType === 'FG/KOMPONEN' ? 'FGK' : $itemType);
                            $currStock     = floatval($stockMap[$ng->item_id] ?? 0);
                            $lotRow        = $lotMap[$ng->lot_number . '|' . $ng->item_id] ?? null;
                            $lotActualProd = $lotRow ? floatval($lotRow->actual_qty_prod) : floatval($ng->qty_ng);
                            $lotQtyNg      = $lotRow ? floatval($lotRow->qty_ng) : floatval($ng->qty_ng);
                            $lotIsFinish   = $lotRow && $lotRow->status_lot === 'FINISH';

                            $editPayload = [
                                'id'               => $ng->id,
                                'lot'              => $ng->lot_number,
                                'part_no'          => $partNo,
                                'part_name'        => $partName,
                                'type'             => $itemType,
                                'qty'              => floatval($ng->qty_ng),
                                'reason'           => $ng->problem_reason,
                                'uom'              => $uom,
                                'stock'            => $currStock,
                                'lot_actual'       => $lotActualProd,
                                'lot_ng'           => $lotQtyNg,
                                'lot_is_finish'    => $lotIsFinish,
                                'status'           => $statusText,
                                'qty_original'     => $ng->qty_original !== null ? floatval($ng->qty_original) : null,
                                'corrected_by'     => $ng->correctedBy->name ?? null,
                                'corrected_at_fmt' => $ng->corrected_at ? \Carbon\Carbon::parse($ng->corrected_at)->format('d M Y, H:i') : null,
                                'correction_notes' => $ng->correction_notes ?: null,
                            ];
                        @endphp
                        <tr data-row
                            data-search="{{ strtolower($ng->lot_number . ' ' . $partNo . ' ' . $partName . ' ' . $uName . ' ' . $ng->problem_reason) }}"
                            data-date="{{ $dt->format('Y-m-d') }}"
                            data-time="{{ $dt->format('H:i') }}"
                            data-reason="{{ $ng->problem_reason }}"
                            data-status="{{ $statusText }}"
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
                            <td class="text-center">
                                @if($statusText === 'DIKOREKSI')
                                    <span class="pill pill-info" title="{{ $ng->correctedBy ? 'Dikoreksi oleh ' . $ng->correctedBy->name : 'DIKOREKSI' }}">DIKOREKSI</span>
                                @else
                                    <span class="pill pill-good">TERCATAT</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="act-btn" title="Koreksi Production NG"
                                        @click="openEdit({{ Js::from($editPayload) }})">
                                    <x-icon name="edit" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="empty">Belum ada data pencatatan Production NG.</div></td></tr>
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

        {{-- POP-UP MODAL KOREKSI PRODUCTION NG (2 Panel Abu) --}}
        <div x-show="editOpen" x-cloak class="modal-backdrop" @click.self="editOpen = false" @keydown.escape.window="editOpen = false">
            <div class="panel dlg w-full max-w-3xl" role="dialog" aria-label="Koreksi Production NG">
                <div class="panel-head">
                    <h3 class="panel-title">Koreksi Production NG</h3>
                    <button type="button" class="act-btn" @click="editOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('production_ngs.verify_batch') }}" method="POST" novalidate
                      @submit.prevent="submitEdit($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    <div class="dlg-split">
                        <aside class="dlg-side">
                            <div class="dlg-k">Lot &amp; Item NG</div>
                            <div class="sumc">
                                <div class="pill pill-info w-fit num" x-text="'LOT: ' + editData.lot"></div>
                                <div class="dlg-code num mt-1" x-text="editData.part_no"></div>
                                <div class="dlg-name" x-text="editData.part_name"></div>
                                <div class="mt-1">
                                    <span class="ty" :class="'ty-' + (editData.type === 'FG/KOMPONEN' ? 'FGK' : editData.type)" x-text="editData.type"></span>
                                </div>
                            </div>
                            <div class="dlg-list">
                                <div><span>Qty NG Tercatat</span><b class="num" x-text="editData.qty + ' ' + editData.uom"></b></div>
                                <div><span>Qty NG Koreksi</span><b class="num text-accent" x-text="(evalCalc(editNewQty) ?? editData.qty) + ' ' + editData.uom"></b></div>
                                <div><span>Maks. Input NG</span><b class="num text-emerald-700" x-text="maxAllowedEditNg + ' ' + editData.uom"></b></div>
                            </div>

                            <div x-show="editData.status === 'DIKOREKSI'" x-cloak class="sumc text-xs space-y-1">
                                <div class="dlg-k">Riwayat Koreksi</div>
                                <div x-show="editData.qty_original !== null">
                                    <span class="text-slate-500">Qty Awal:</span>
                                    <b class="num text-slate-900" x-text="editData.qty_original + ' ' + editData.uom"></b>
                                </div>
                                <div x-show="editData.corrected_by">
                                    <span class="text-slate-500">Oleh:</span>
                                    <b class="text-slate-900" x-text="editData.corrected_by"></b>
                                </div>
                                <div x-show="editData.corrected_at_fmt" class="num text-[11px] text-slate-500" x-text="editData.corrected_at_fmt"></div>
                                <div x-show="editData.correction_notes" class="text-[11px] text-slate-700 break-words" x-text="'Alasan: ' + editData.correction_notes"></div>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <div x-show="editData.lot_is_finish" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="'Lot ' + editData.lot + ' sudah berstatus FINISH sehingga Production NG tidak dapat dikoreksi.'"></span>
                            </div>

                            <div x-show="editError && !editData.lot_is_finish" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="editError"></span>
                            </div>

                            <input type="hidden" :name="'items[' + editData.id + '][qty_check]'" value="DIKOREKSI">

                            <div>
                                <span class="lbl">Problem Reason</span>
                                <div class="opt-chips">
                                    @foreach($reasons as $r)
                                        <label>
                                            <input type="radio"
                                                   :name="'items[' + editData.id + '][problem_reason]'"
                                                   value="{{ $r }}"
                                                   x-model="editReason"
                                                   :disabled="editData.lot_is_finish"
                                                   required>
                                            <span>{{ $r }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label class="lbl" for="ng_edit_qty">Qty NG Sebenarnya</label>
                                <div class="inp-calc">
                                    <input id="ng_edit_qty" type="text"
                                           :name="'items[' + editData.id + '][actual_qty]'"
                                           x-model="editNewQty" required
                                           :disabled="editData.lot_is_finish"
                                           placeholder="Contoh: 2 atau 3+2"
                                           class="num w-full h-9 font-semibold">
                                    <span class="inp-calc-ic" title="Mendukung kalkulator (+, -, *, /)">
                                        <x-icon name="calc" />
                                    </span>
                                </div>
                                <div class="calc-preview" x-show="!editData.lot_is_finish && String(editNewQty || '').trim() !== '' && evalCalc(editNewQty) !== null" x-cloak>
                                    Hasil: <b x-text="evalCalc(editNewQty)"></b> <span x-text="editData.uom"></span>
                                </div>
                                <div class="calc-err" x-show="!editData.lot_is_finish && String(editNewQty || '').trim() !== '' && evalCalc(editNewQty) === null" x-cloak>
                                    <x-icon name="alert" />
                                    <span>Format perhitungan tidak valid</span>
                                </div>
                            </div>

                            <div>
                                <label class="lbl" for="ng_edit_notes">Alasan Koreksi <span style="font-weight:400;color:var(--mut)">(Opsional)</span></label>
                                <input id="ng_edit_notes" type="text"
                                       :name="'items[' + editData.id + '][correction_notes]'"
                                       x-model="editNotes"
                                       :disabled="editData.lot_is_finish"
                                       placeholder="Contoh: Salah hitung jumlah reject saat inspeksi"
                                       maxlength="500"
                                       class="w-full h-9 text-xs">
                            </div>

                            <div class="alert alert-info text-xs leading-relaxed">
                                <x-icon name="info" />
                                <div>Menyimpan koreksi akan otomatis menyesuaikan <b>Qty NG</b>, <b>Act FG</b> pada Lot Tracer, dan <b>Current Stock</b> di gudang serta mencatat jejak audit koreksi.</div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="editOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary" :disabled="editData.lot_is_finish">Simpan Koreksi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function prodNgPage(initialCount, lotsList, defaultReason, openInitially, exportBase) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fReason: '',
                fStatus: '',
                exportBase: exportBase,
                visibleCount: initialCount,
                lotsList: lotsList,
                addOpen: openInitially,
                addError: '',
                selectedLotId: '',
                selectedReason: defaultReason || 'MATERIAL',
                qtyNg: '',

                editOpen: false,
                editError: '',
                editNewQty: '',
                editReason: defaultReason || 'MATERIAL',
                editNotes: '',
                editData: {
                    id: null,
                    lot: '',
                    part_no: '',
                    part_name: '',
                    type: 'FG',
                    qty: 0,
                    reason: 'MATERIAL',
                    uom: 'PCS',
                    stock: 0,
                    lot_actual: 0,
                    lot_ng: 0,
                    lot_is_finish: false,
                    status: 'TERCATAT',
                    qty_original: null,
                    corrected_by: null,
                    corrected_at_fmt: null,
                    correction_notes: null
                },

                exportData(format) {
                    const params = new URLSearchParams();
                    params.set('format', format || 'xlsx');
                    if (this.q.trim()) params.set('q', this.q.trim());
                    if (this.startDate) params.set('start_date', this.startDate);
                    if (this.endDate) params.set('end_date', this.endDate);
                    if (this.startTime) params.set('start_time', this.startTime);
                    if (this.endTime) params.set('end_time', this.endTime);
                    if (this.fReason) params.set('problem_reason', this.fReason);
                    if (this.fStatus) params.set('qty_check', this.fStatus);
                    window.location.href = `${this.exportBase}?${params.toString()}`;
                },
                get selectedLot() {
                    return this.lotsList.find(x => String(x.id) === String(this.selectedLotId)) || null;
                },
                get maxAllowedEditNg() {
                    const remActFg = Math.max(0, (Number(this.editData.lot_actual) || 0) - (Number(this.editData.lot_ng) || 0));
                    return Math.round(((Number(this.editData.qty) || 0) + remActFg) * 100) / 100;
                },
                openEdit(payload) {
                    this.editData = Object.assign({}, payload);
                    this.editNewQty = String(payload.qty);
                    this.editReason = payload.reason || 'MATERIAL';
                    this.editNotes = payload.correction_notes || '';
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
                submitEdit(formEl) {
                    this.editError = '';
                    if (this.editData.lot_is_finish) {
                        this.editError = 'Lot ' + this.editData.lot + ' sudah berstatus FINISH sehingga Production NG tidak dapat dikoreksi.';
                        return;
                    }
                    if (!this.editReason) {
                        this.editError = 'Problem Reason wajib dipilih.';
                        return;
                    }
                    if (String(this.editNewQty || '').trim() === '') {
                        this.editError = 'Qty NG koreksi wajib diisi.';
                        return;
                    }
                    const val = this.evalCalc(this.editNewQty);
                    if (val === null) {
                        this.editError = 'Format perhitungan tidak valid.';
                        return;
                    }
                    if (val <= 0) {
                        this.editError = 'Qty NG koreksi harus lebih dari 0.';
                        return;
                    }
                    if (val > this.maxAllowedEditNg + 0.0001) {
                        this.editError = 'Qty NG melebihi saldo Act FG pada Lot ' + this.editData.lot + ' (maksimal ' + this.maxAllowedEditNg + ' ' + this.editData.uom + ').';
                        return;
                    }
                    const diff = Math.round((val - this.editData.qty) * 100) / 100;
                    if (diff > 0 && diff > this.editData.stock + 0.0001) {
                        this.editError = 'Stok gudang ' + this.editData.part_name + ' tidak cukup untuk kenaikan Qty NG (tersedia ' + this.editData.stock + ' ' + this.editData.uom + ').';
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
                    this.fStatus = '';
                    this.recalc();
                },
                matchRow(el) {
                    const s = el.dataset.search || '';
                    const d = el.dataset.date || '';
                    const t = el.dataset.time || '';
                    const r = el.dataset.reason || '';
                    const st = el.dataset.status || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.startDate && d < this.startDate) return false;
                    if (this.endDate && d > this.endDate) return false;
                    if (this.startTime && t < this.startTime) return false;
                    if (this.endTime && t > this.endTime) return false;
                    if (this.fReason && r !== this.fReason) return false;
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