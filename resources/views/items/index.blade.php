<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Master Item"
                       subtitle="Daftar barang aktif beserta kalkulasi standar stok harian (PO/WD/Kanban & Resep BOM)">
            <x-slot name="actions">
                <span class="btn-group">
                    <a href="{{ route('items.trashed') }}" class="btn">
                        <x-icon name="ban" />
                        <span>Item Nonaktif</span>
                        <span class="count-chip">{{ $trashedCount }}</span>
                    </a>
                    <button type="button" class="btn btn-primary" @click="$dispatch('open-add-item')">
                        <x-icon name="plus" />
                        <span>Input Item</span>
                    </button>
                </span>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $companiesJs = $companies->map(fn($c) => [
            'id'   => $c->id,
            'code' => $c->company_code,
            'name' => $c->company_name,
            'type' => $c->type_company,
        ])->values();
    @endphp

    <div class="panel"
         x-data="masterItemPage({{ $items->count() }}, {{ Js::from($companiesJs) }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-item.window="addOpen = true; addError = ''; syncAddCompany()"
         x-init="syncAddCompany(); recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Master Item Aktif</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>ITEM</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Part No / Part Name..."
                       class="h-8 text-xs px-2.5 w-48">

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe Item</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>

                <select x-model="fCompType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe Perusahaan</option>
                    <option value="SUPPLIER">SUPPLIER</option>
                    <option value="INTERNAL">INTERNAL</option>
                    <option value="CUSTOMER">CUSTOMER</option>
                </select>

                <button type="button" x-show="q || fType || fCompType" x-cloak
                        @click="q=''; fType=''; fCompType=''; recalc()" class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[4%] text-center">No</th>
                        <th class="w-[22%]">Part No &amp; Part Name</th>
                        <th class="w-[9%] text-center">Tipe &amp; UOM</th>
                        <th class="w-[11%]">Perusahaan</th>
                        <th class="num w-[8%]">Qty PO</th>
                        <th class="num w-[5%]">WD</th>
                        <th class="num w-[7%]">Kanban</th>
                        <th class="num w-[8%]">Std Day 1</th>
                        <th class="num w-[8%]">Std Day 2</th>
                        <th class="num w-[8%]">Std Day 3</th>
                        <th class="w-[10%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $idx => $item)
                        @php
                            $std1     = $item->calculated_std_day_1;
                            $std2     = $item->std_day_2;
                            $std3     = $item->std_day_3;
                            $hasHist  = $item->hasTransactionHistory();
                            $compCode = $item->company->company_code ?? 'INTERNAL';
                            $compType = $item->company->type_company ?? 'INTERNAL';
                            $tyCls    = 'ty-' . ($item->type === 'FG/KOMPONEN' ? 'FGK' : $item->type);

                            $editPayload = [
                                'id'           => $item->id,
                                'part_no'      => $item->part_no ?? '',
                                'part_name'    => $item->part_name,
                                'type'         => $item->type,
                                'uom'          => $item->uom,
                                'company_id'   => $item->company_id,
                                'qty_po'       => floatval($item->qty_po),
                                'working_days' => intval($item->working_days),
                                'qty_kanban'   => floatval($item->qty_kanban),
                                'has_history'  => $hasHist,
                            ];

                            $txCount = (int) ($item->inbounds_count ?? 0)
                                + (int) ($item->material_outs_as_material_count ?? 0)
                                + (int) ($item->material_returns_as_material_count ?? 0)
                                + (int) ($item->productions_count ?? 0)
                                + (int) ($item->production_ngs_count ?? 0)
                                + (int) ($item->deliveries_count ?? 0)
                                + (int) ($item->return_to_suppliers_count ?? 0)
                                + (int) ($item->stock_adjustments_count ?? 0)
                                + (int) ($item->problem_delays_count ?? 0);

                            $bomCount = (int) ($item->boms_as_output_count ?? 0)
                                + (int) ($item->boms_as_input_count ?? 0);

                            $delPayload = [
                                'id'          => $item->id,
                                'part_no'     => $item->part_no ?? '',
                                'part_name'   => $item->part_name,
                                'type'        => $item->type,
                                'uom'         => $item->uom,
                                'company'     => $compCode,
                                'stock'       => floatval($item->inventoryStock->current_stock ?? 0),
                                'history'     => $txCount,
                                'bom'         => $bomCount,
                                'has_history' => $hasHist,
                            ];
                        @endphp
                        <tr data-item-row
                            data-search="{{ strtolower(($item->part_no ?? '') . ' ' . $item->part_name . ' ' . $compCode) }}"
                            data-type="{{ $item->type }}"
                            data-comptype="{{ $compType }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500" data-col-no>{{ $idx + 1 }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $item->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900">{{ $item->part_name }}</div>
                            </td>
                            <td class="text-center">
                                <span class="ty {{ $tyCls }}">{{ $item->type }}</span>
                                <div class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ $item->uom }}</div>
                            </td>
                            <td>
                                <div class="font-bold text-xs text-accent">{{ $compCode }}</div>
                                <div class="mt-0.5">
                                    @if($compType === 'SUPPLIER')
                                        <span class="pill pill-info">SUPPLIER</span>
                                    @elseif($compType === 'INTERNAL')
                                        <span class="pill pill-warn">INTERNAL</span>
                                    @else
                                        <span class="pill pill-good">CUSTOMER</span>
                                    @endif
                                </div>
                            </td>
                            <td class="num text-slate-700">{{ floatval($item->qty_po) }}</td>
                            <td class="num text-slate-700">{{ $item->working_days }}</td>
                            <td class="num text-slate-700">{{ floatval($item->qty_kanban) }}</td>
                            <td class="num font-bold text-slate-900">{{ floatval($std1) }}</td>
                            <td class="num text-slate-700">{{ floatval($std2) }}</td>
                            <td class="num text-slate-700">{{ floatval($std3) }}</td>
                            <td class="text-center">
                                <div class="inline-flex items-center justify-center gap-1">
                                    <button type="button" class="act-btn" title="Informasi Material & BOM"
                                            @click="$dispatch('open-material-info', {{ Js::from(['id' => $item->id, 'part_no' => $item->part_no ?: '-', 'part_name' => $item->part_name]) }})">
                                        <x-icon name="info" />
                                    </button>
                                    <button type="button" class="act-btn" title="Edit Item"
                                            @click="openEdit({{ Js::from($editPayload) }})">
                                        <x-icon name="edit" />
                                    </button>
                                    <button type="button" class="act-btn {{ $hasHist ? 'warn' : 'danger' }}"
                                            title="{{ $hasHist ? 'Nonaktifkan Item' : 'Hapus Permanen' }}"
                                            @click="openDelete({{ Js::from($delPayload) }})">
                                        <x-icon :name="$hasHist ? 'ban' : 'trash'" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><div class="empty">Belum ada data Master Item.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT MASTER ITEM BARU (dua panel, panel kiri abu) --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false" @keydown.escape.window="addOpen = false">
            <div class="panel dlg w-full max-w-3xl" role="dialog" aria-label="Input Master Item Baru">
                <div class="panel-head">
                    <h3 class="panel-title">Input Master Item Baru</h3>
                    <button type="button" class="act-btn" @click="addOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('items.store') }}" method="POST" novalidate
                      @submit.prevent="submitAdd($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    <div class="dlg-split">
                        {{-- Panel kiri: pratinjau hidup --}}
                        <aside class="dlg-side hide-sm">
                            <div class="dlg-k">Pratinjau Item</div>
                            <div class="sumc">
                                <div class="dlg-code num" x-text="addPartNo || '–'"></div>
                                <div class="dlg-name uppercase" x-text="addPartName || 'NAMA PART'"></div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="ty" :class="tyClass(addType)" x-text="addType"></span>
                                    <span class="num text-xs font-semibold text-slate-600" x-text="addUom"></span>
                                </div>
                            </div>
                            <div>
                                <div class="dlg-k" style="margin-bottom: 8px">Standar Buffer</div>
                                <template x-if="allowsPo(addType)">
                                    <div class="dlg-list">
                                        <div><span>Std Day 1</span><b class="num" x-text="stdPreview(addPo, addWd, addKb)[0]"></b></div>
                                        <div><span>Std Day 2 (Min)</span><b class="num" x-text="stdPreview(addPo, addWd, addKb)[1]"></b></div>
                                        <div><span>Std Day 3 (Max)</span><b class="num" x-text="stdPreview(addPo, addWd, addKb)[2]"></b></div>
                                    </div>
                                </template>
                                <template x-if="!allowsPo(addType)">
                                    <div class="sumc text-xs text-slate-600 leading-relaxed">
                                        Dihitung otomatis dari akumulasi resep BOM, bukan dari parameter PO.
                                    </div>
                                </template>
                            </div>
                        </aside>

                        {{-- Panel kanan: field --}}
                        <div class="dlg-main">
                            <div x-show="addError" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="addError"></span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="lbl" for="add_part_no">
                                        Part No <span style="font-weight:400;color:var(--mut)" x-text="addType === 'RM' ? '(Opsional untuk RM)' : '(Wajib)'"></span>
                                    </label>
                                    <input id="add_part_no" type="text" name="part_no" x-model="addPartNo"
                                           :required="addType !== 'RM'"
                                           placeholder="Contoh: FG-BKT-001"
                                           oninput="this.value = this.value.toUpperCase()"
                                           class="num uppercase w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl" for="add_part_name">Part Name (Wajib)</label>
                                    <input id="add_part_name" type="text" name="part_name" x-model="addPartName" required
                                           placeholder="Contoh: BRACKET MUFFLER ASSY"
                                           oninput="this.value = this.value.toUpperCase()"
                                           class="uppercase w-full h-9">
                                </div>
                            </div>

                            <div>
                                <span class="lbl">Tipe Item</span>
                                <div class="opt-chips">
                                    @foreach($types as $t)
                                        <label>
                                            <input type="radio" name="type" value="{{ $t }}" x-model="addType" @change="syncAddCompany()" required>
                                            <span>{{ $t }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="lbl" for="add_uom">Satuan (UOM)</label>
                                    <select id="add_uom" name="uom" x-model="addUom" required class="w-full h-9">
                                        @foreach($uoms as $u)
                                            <option value="{{ $u }}">{{ $u }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="lbl" for="add_company">Perusahaan</label>
                                    <select id="add_company" name="company_id" x-model="addCompanyId" required class="w-full h-9">
                                        <option value="">-- Pilih Perusahaan --</option>
                                        <template x-for="c in allowedCompanies(addType)" :key="c.id">
                                            <option :value="c.id" x-text="c.code + ' — ' + c.name + ' (' + c.type + ')'"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                            <p class="hint" style="margin-top: -6px" x-text="companyGuide(addType)"></p>

                            <div x-show="allowsPo(addType)" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="lbl" for="add_po">Qty PO Sebulan <span style="font-weight:400;color:var(--mut)">(Opsional)</span></label>
                                    <input id="add_po" type="number" step="0.01" min="0" name="qty_po" x-model="addPo" placeholder="0" class="num w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl" for="add_wd">Working Days (WD) <span style="font-weight:400;color:var(--mut)">(Opsional)</span></label>
                                    <input id="add_wd" type="number" min="0" name="working_days" x-model="addWd" placeholder="0" class="num w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl" for="add_kb">Qty per Kanban <span style="font-weight:400;color:var(--mut)">(Opsional)</span></label>
                                    <input id="add_kb" type="number" step="0.01" min="0" name="qty_kanban" x-model="addKb" placeholder="0" class="num w-full h-9">
                                </div>
                            </div>

                            <div x-show="!allowsPo(addType)" x-cloak class="alert alert-info text-xs leading-relaxed">
                                <x-icon name="info" />
                                <div>
                                    Untuk tipe <b class="font-bold" x-text="addType"></b>, parameter PO, Working Days, dan Kanban dikunci <b class="num font-bold">0</b> karena kebutuhan stok hariannya (<b class="num font-bold">Std Day 1 / 2 / 3</b>) dihitung otomatis dari resep BOM.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Master Item</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- POP-UP MODAL EDIT MASTER ITEM (dua panel, panel kiri abu) --}}
        <div x-show="editOpen" x-cloak class="modal-backdrop" @click.self="editOpen = false" @keydown.escape.window="editOpen = false">
            <div class="panel dlg w-full max-w-3xl" role="dialog" aria-label="Edit Master Item">
                <div class="panel-head">
                    <h3 class="panel-title">Edit Master Item</h3>
                    <button type="button" class="act-btn" @click="editOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form :action="'{{ url('items') }}/' + editData.id" method="POST" novalidate
                      @submit.prevent="submitEdit($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    @method('PUT')
                    <div class="dlg-split">
                        <aside class="dlg-side hide-sm">
                            <div class="dlg-k">Pratinjau Item</div>
                            <div class="sumc">
                                <div class="dlg-code num" x-text="editData.part_no || '–'"></div>
                                <div class="dlg-name uppercase" x-text="editData.part_name || 'NAMA PART'"></div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="ty" :class="tyClass(editData.type)" x-text="editData.type"></span>
                                    <span class="num text-xs font-semibold text-slate-600" x-text="editData.uom"></span>
                                </div>
                            </div>
                            <div>
                                <div class="dlg-k" style="margin-bottom: 8px">Standar Buffer</div>
                                <template x-if="allowsPo(editData.type)">
                                    <div class="dlg-list">
                                        <div><span>Std Day 1</span><b class="num" x-text="stdPreview(editData.qty_po, editData.working_days, editData.qty_kanban)[0]"></b></div>
                                        <div><span>Std Day 2 (Min)</span><b class="num" x-text="stdPreview(editData.qty_po, editData.working_days, editData.qty_kanban)[1]"></b></div>
                                        <div><span>Std Day 3 (Max)</span><b class="num" x-text="stdPreview(editData.qty_po, editData.working_days, editData.qty_kanban)[2]"></b></div>
                                    </div>
                                </template>
                                <template x-if="!allowsPo(editData.type)">
                                    <div class="sumc text-xs text-slate-600 leading-relaxed">
                                        Dihitung otomatis dari akumulasi resep BOM, bukan dari parameter PO.
                                    </div>
                                </template>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <div x-show="editError" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="editError"></span>
                            </div>

                            <template x-if="editData.has_history">
                                <div class="alert alert-crit text-xs leading-relaxed">
                                    <x-icon name="alert" />
                                    <div>Identitas utama terkunci karena item ini sudah memiliki riwayat transaksi / BOM. Anda tetap dapat memperbarui parameter PO, Working Days, dan Kanban (untuk tipe FG, FG/KOMPONEN, &amp; TRANSIT).</div>
                                </div>
                            </template>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="lbl" for="edit_part_no">
                                        Part No <span style="font-weight:400;color:var(--mut)" x-text="editData.type === 'RM' ? '(Opsional untuk RM)' : '(Wajib)'"></span>
                                    </label>
                                    <input id="edit_part_no" type="text" name="part_no" x-model="editData.part_no"
                                           :disabled="editData.has_history"
                                           :required="!editData.has_history && editData.type !== 'RM'"
                                           oninput="this.value = this.value.toUpperCase()"
                                           class="num uppercase w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl" for="edit_part_name">Part Name</label>
                                    <input id="edit_part_name" type="text" name="part_name" x-model="editData.part_name"
                                           :disabled="editData.has_history" required
                                           oninput="this.value = this.value.toUpperCase()"
                                           class="uppercase w-full h-9">
                                </div>
                            </div>

                            <div>
                                <span class="lbl">Tipe Item</span>
                                <div class="opt-chips">
                                    @foreach($types as $t)
                                        <label>
                                            <input type="radio" name="type" value="{{ $t }}" x-model="editData.type" @change="syncEditCompany()" :disabled="editData.has_history">
                                            <span>{{ $t }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="lbl" for="edit_uom">Satuan (UOM)</label>
                                    <select id="edit_uom" name="uom" x-model="editData.uom" :disabled="editData.has_history" class="w-full h-9">
                                        @foreach($uoms as $u)
                                            <option value="{{ $u }}">{{ $u }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="lbl" for="edit_company">Perusahaan</label>
                                    <select id="edit_company" name="company_id" x-model="editData.company_id" :disabled="editData.has_history" class="w-full h-9">
                                        <template x-for="c in allowedCompanies(editData.type)" :key="c.id">
                                            <option :value="c.id" x-text="c.code + ' — ' + c.name + ' (' + c.type + ')'"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>

                            <div x-show="allowsPo(editData.type)" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="lbl" for="edit_po">Qty PO Sebulan <span style="font-weight:400;color:var(--mut)">(Opsional)</span></label>
                                    <input id="edit_po" type="number" step="0.01" min="0" name="qty_po" x-model="editData.qty_po" placeholder="0" class="num w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl" for="edit_wd">Working Days (WD) <span style="font-weight:400;color:var(--mut)">(Opsional)</span></label>
                                    <input id="edit_wd" type="number" min="0" name="working_days" x-model="editData.working_days" placeholder="0" class="num w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl" for="edit_kb">Qty per Kanban <span style="font-weight:400;color:var(--mut)">(Opsional)</span></label>
                                    <input id="edit_kb" type="number" step="0.01" min="0" name="qty_kanban" x-model="editData.qty_kanban" placeholder="0" class="num w-full h-9">
                                </div>
                            </div>

                            <div x-show="!allowsPo(editData.type)" x-cloak class="alert alert-info text-xs leading-relaxed">
                                <x-icon name="info" />
                                <div>
                                    Untuk tipe <b class="font-bold" x-text="editData.type"></b>, parameter PO, Working Days, dan Kanban dikunci <b class="num font-bold">0</b> karena kebutuhan stok hariannya (<b class="num font-bold">Std Day 1 / 2 / 3</b>) dihitung otomatis dari resep BOM.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="editOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary"
                                :disabled="editData.has_history && !allowsPo(editData.type)">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- DIALOG HAPUS / NONAKTIFKAN MASTER ITEM (dua panel, panel kiri abu) --}}
        <div x-show="delOpen" x-cloak class="modal-backdrop" @click.self="delOpen = false" @keydown.escape.window="delOpen = false">
            <div class="panel dlg w-full max-w-2xl" role="dialog"
                 :aria-label="delData.has_history ? 'Nonaktifkan Master Item' : 'Hapus Permanen Master Item'">
                <div class="panel-head">
                    <h3 class="panel-title" x-text="delData.has_history ? 'Nonaktifkan Master Item' : 'Hapus Permanen Master Item'"></h3>
                    <button type="button" class="act-btn" @click="delOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form :action="'{{ url('items') }}/' + delData.id" method="POST" class="flex flex-col min-h-0 flex-1">
                    @csrf
                    @method('DELETE')
                    <div class="dlg-split">
                        <aside class="dlg-side">
                            <div class="dlg-k">Item yang dipilih</div>
                            <div class="sumc">
                                <div class="num" style="font-weight: 600; color: var(--accent)" x-text="delData.part_no || '–'"></div>
                                <div style="font-weight: 500" x-text="delData.part_name"></div>
                                <div class="flex items-center gap-2" style="margin-top: 2px">
                                    <span class="ty" :class="tyClass(delData.type)" x-text="delData.type"></span>
                                    <span class="hint" style="margin: 0" x-text="delData.uom + ' · ' + delData.company"></span>
                                </div>
                            </div>
                            <div class="dlg-list">
                                <div><span>Stok</span><b class="num" x-text="delData.stock + ' ' + delData.uom"></b></div>
                                <div><span>Riwayat</span><b class="num" x-text="delData.history + ' transaksi'"></b></div>
                                <div><span>Dipakai di BOM</span><b class="num" x-text="delData.bom"></b></div>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <p style="margin: 0; color: var(--ink2); line-height: 1.5">
                                <template x-if="delData.has_history">
                                    <span x-text="'Item ' + delData.part_name + ' sudah memiliki riwayat transaksi/BOM sehingga akan dipindahkan ke daftar Item Nonaktif.'"></span>
                                </template>
                                <template x-if="!delData.has_history">
                                    <span x-text="'Item ' + delData.part_name + ' belum memiliki riwayat transaksi dan akan dihapus permanen.'"></span>
                                </template>
                            </p>

                            <div class="dlg-k">Akibatnya</div>

                            <ul class="cons" x-show="delData.has_history">
                                <li class="k"><i></i><span>Riwayat transaksi dan BOM tetap tersimpan</span></li>
                                <li class="k"><i></i><span>Bisa diaktifkan kembali dari daftar Item Nonaktif</span></li>
                                <li class="x"><i></i><span>Tidak muncul lagi di scan dan pilihan item</span></li>
                            </ul>
                            <ul class="cons" x-show="!delData.has_history" x-cloak>
                                <li class="x"><i></i><span>Data item dihapus dan tidak bisa dikembalikan</span></li>
                                <li class="k"><i></i><span>Tidak ada riwayat yang ikut hilang</span></li>
                            </ul>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="delOpen = false">Batal</button>
                        <button type="submit" class="btn"
                                :class="delData.has_history ? 'btn-primary' : 'btn-crit'"
                                x-text="delData.has_history ? 'Nonaktifkan' : 'Hapus Permanen'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-material-info-modal />

    <script>
        function masterItemPage(initialCount, companiesList, openInitially) {
            return {
                q: '',
                fType: '',
                fCompType: '',
                visibleCount: initialCount,
                companiesList: companiesList,
                addOpen: openInitially,
                addType: 'FG',
                addCompanyId: '',
                addPartNo: '',
                addPartName: '',
                addUom: {{ Js::from(collect($uoms)->first() ?? 'PCS') }},
                addPo: '',
                addWd: '',
                addKb: '',
                addError: '',
                editOpen: false,
                editError: '',
                editData: {
                    id: null,
                    part_no: '',
                    part_name: '',
                    type: 'FG',
                    uom: 'PCS',
                    company_id: '',
                    qty_po: 0,
                    working_days: 0,
                    qty_kanban: 0,
                    has_history: false
                },
                delOpen: false,
                delData: {
                    id: null,
                    part_no: '',
                    part_name: '',
                    type: '',
                    uom: '',
                    company: '',
                    stock: 0,
                    history: 0,
                    bom: 0,
                    has_history: false
                },

                tyClass(t) {
                    return 'ty-' + (t === 'FG/KOMPONEN' ? 'FGK' : t);
                },
                stdPreview(po, wd, kb = 0) {
                    const p = parseFloat(po) || 0;
                    const w = parseInt(wd, 10) || 0;
                    const k = parseFloat(kb) || 0;
                    if (w <= 0 || p <= 0) return ['0', '0', '0'];
                    const poWd = Math.round((p / w) * 100) / 100;
                    const s1 = k > 0 ? Math.ceil(poWd / k) * k : Math.ceil(poWd);
                    return [s1, s1 * 2, s1 * 3].map(v => (Math.round(v * 100) / 100).toLocaleString('id-ID'));
                },
                allowsPo(t) {
                    return ['FG', 'FG/KOMPONEN', 'TRANSIT'].includes(t);
                },
                allowedCompanies(itemType) {
                    const map = {
                        'RM': ['SUPPLIER'],
                        'KOMPONEN': ['SUPPLIER', 'INTERNAL'],
                        'FG/KOMPONEN': ['INTERNAL'],
                        'TRANSIT': ['SUPPLIER'],
                        'FG': ['CUSTOMER']
                    };
                    const allowed = map[itemType] || ['SUPPLIER', 'INTERNAL', 'CUSTOMER'];
                    return this.companiesList.filter(c => allowed.includes(c.type));
                },
                companyGuide(itemType) {
                    const guides = {
                        'RM': 'Pilih Supplier pemasok bahan baku (RM) ini.',
                        'KOMPONEN': 'Pilih SUPPLIER jika dibeli dari vendor luar, atau INTERNAL jika diproduksi sendiri.',
                        'FG/KOMPONEN': 'Pilih perusahaan INTERNAL (hasil produksi internal yang dapat dikirim ke Customer maupun dipakai proses lanjut).',
                        'TRANSIT': 'Pilih Supplier asal barang Transit (masuk dari Inbound lalu dikirim langsung lewat Delivery).',
                        'FG': 'Pilih Customer tujuan pengiriman produk akhir (FG) ini.'
                    };
                    return guides[itemType] || '';
                },
                syncAddCompany() {
                    const list = this.allowedCompanies(this.addType);
                    if (list.length > 0 && !list.some(c => String(c.id) === String(this.addCompanyId))) {
                        this.addCompanyId = list[0].id;
                    } else if (list.length === 0) {
                        this.addCompanyId = '';
                    }
                },
                syncEditCompany() {
                    if (this.editData.has_history) return;
                    const list = this.allowedCompanies(this.editData.type);
                    if (list.length > 0 && !list.some(c => String(c.id) === String(this.editData.company_id))) {
                        this.editData.company_id = list[0].id;
                    } else if (list.length === 0) {
                        this.editData.company_id = '';
                    }
                },
                validatePoParams(po, wd, kb) {
                    if (po !== '' && po !== null && (isNaN(Number(po)) || Number(po) < 0)) {
                        return 'Qty PO Sebulan tidak boleh minus.';
                    }
                    if (wd !== '' && wd !== null && (isNaN(Number(wd)) || Number(wd) < 0 || !Number.isInteger(Number(wd)))) {
                        return 'Working Days (WD) wajib bilangan bulat minimal 0.';
                    }
                    if (kb !== '' && kb !== null && (isNaN(Number(kb)) || Number(kb) < 0)) {
                        return 'Qty per Kanban tidak boleh minus.';
                    }
                    return '';
                },
                submitAdd(formEl) {
                    this.addError = '';
                    if (this.addType !== 'RM' && !(this.addPartNo || '').trim()) {
                        this.addError = 'Part No wajib diisi untuk tipe item ' + this.addType + '.';
                        return;
                    }
                    if (!(this.addPartName || '').trim()) {
                        this.addError = 'Part Name wajib diisi.';
                        return;
                    }
                    if (!this.addType) {
                        this.addError = 'Tipe Item wajib dipilih.';
                        return;
                    }
                    if (!this.addUom) {
                        this.addError = 'Satuan (UOM) wajib dipilih.';
                        return;
                    }
                    const allowed = this.allowedCompanies(this.addType);
                    if (!this.addCompanyId || !allowed.some(c => String(c.id) === String(this.addCompanyId))) {
                        this.addError = 'Perusahaan wajib dipilih dan sesuai dengan tipe item ' + this.addType + '.';
                        return;
                    }
                    if (this.allowsPo(this.addType)) {
                        const poErr = this.validatePoParams(this.addPo, this.addWd, this.addKb);
                        if (poErr) {
                            this.addError = poErr;
                            return;
                        }
                    }
                    formEl.submit();
                },
                submitEdit(formEl) {
                    this.editError = '';
                    if (this.editData.has_history) {
                        if (!this.allowsPo(this.editData.type)) {
                            this.editError = 'Parameter item bertipe ' + this.editData.type + ' yang sudah memiliki riwayat transaksi mengikuti perhitungan BOM secara otomatis.';
                            return;
                        }
                        const poErr = this.validatePoParams(this.editData.qty_po, this.editData.working_days, this.editData.qty_kanban);
                        if (poErr) {
                            this.editError = poErr;
                            return;
                        }
                        formEl.submit();
                        return;
                    }

                    if (this.editData.type !== 'RM' && !(this.editData.part_no || '').trim()) {
                        this.editError = 'Part No wajib diisi untuk tipe item ' + this.editData.type + '.';
                        return;
                    }
                    if (!(this.editData.part_name || '').trim()) {
                        this.editError = 'Part Name wajib diisi.';
                        return;
                    }
                    if (!this.editData.type) {
                        this.editError = 'Tipe Item wajib dipilih.';
                        return;
                    }
                    if (!this.editData.uom) {
                        this.editError = 'Satuan (UOM) wajib dipilih.';
                        return;
                    }
                    const allowed = this.allowedCompanies(this.editData.type);
                    if (!this.editData.company_id || !allowed.some(c => String(c.id) === String(this.editData.company_id))) {
                        this.editError = 'Perusahaan wajib dipilih dan sesuai dengan tipe item ' + this.editData.type + '.';
                        return;
                    }
                    if (this.allowsPo(this.editData.type)) {
                        const poErr = this.validatePoParams(this.editData.qty_po, this.editData.working_days, this.editData.qty_kanban);
                        if (poErr) {
                            this.editError = poErr;
                            return;
                        }
                    }
                    formEl.submit();
                },
                openEdit(payload) {
                    this.editData = Object.assign({}, payload);
                    this.editError = '';
                    this.editOpen = true;
                },
                openDelete(d) {
                    this.delData = Object.assign({}, d);
                    this.delOpen = true;
                },
                matchRow(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const t = el.dataset.type || '';
                    const ct = el.dataset.comptype || '';
                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.fType && t !== this.fType) return false;
                    if (this.fCompType && ct !== this.fCompType) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-item-row]').forEach(tr => {
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