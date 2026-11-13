<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Master Item"
                       subtitle="Registrasi spesifikasi part, parameter PO/Kanban, dan standar buffer harian (Std Day 1/2/3)">
            <x-slot name="actions">
                <a href="{{ route('items.trashed') }}" class="btn">
                    <x-icon name="ban" /> Item Nonaktif
                    <span class="count-chip count-chip-sub ml-1">{{ $trashedCount }}</span>
                </a>
                <button type="button" class="btn btn-primary" @click="$dispatch('open-add-item')">
                    <x-icon name="plus" /> Tambah Item
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $companiesJs = $companies->map(fn ($c) => [
            'id'   => $c->id,
            'code' => $c->company_code,
            'name' => $c->company_name,
            'type' => $c->type_company,
        ])->values();
    @endphp

    <div class="panel"
         x-data="masterItemPage({{ $items->count() }}, {{ Js::from($companiesJs) }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-item.window="openAdd()"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Master Item Aktif</span>
                <span class="count-chip"><span x-text="visibleRows"></span> Item</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Part No / Part Name..."
                       class="h-8 text-xs px-2.5 w-48">

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe Item</option>
                    @foreach($types as $tp)
                        <option value="{{ $tp }}">{{ $tp }}</option>
                    @endforeach
                </select>

                <select x-model="fCompType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe Perusahaan</option>
                    <option value="SUPPLIER">SUPPLIER</option>
                    <option value="INTERNAL">INTERNAL</option>
                    <option value="CUSTOMER">CUSTOMER</option>
                </select>

                <button type="button"
                        x-show="q || fType || fCompType"
                        x-cloak @click="q=''; fType=''; fCompType=''; recalc()" class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-12 text-center">No</th>
                        <th>Part No &amp; Part Name</th>
                        <th class="w-32 text-center">Tipe &amp; UOM</th>
                        <th class="w-36 text-center">Perusahaan</th>
                        <th class="num w-36">PO / WD / Kanban</th>
                        <th class="num w-24">PO / WD</th>
                        <th class="num w-24">Std Day 1</th>
                        <th class="num w-24">Std Day 2</th>
                        <th class="num w-24">Std Day 3</th>
                        <th class="w-28 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $idx => $item)
                        @php
                            $comp = $item->company;
                            $compType = $comp->type_company ?? 'INTERNAL';
                            $hasHist = $item->hasTransactionHistory();
                            $std1 = (float) $item->calculated_std_day_1;
                            $std2 = (float) $item->std_day_2;
                            $std3 = (float) $item->std_day_3;
                            $searchStr = strtolower(
                                ($item->part_no ?? '') . ' ' .
                                ($item->part_name ?? '') . ' ' .
                                ($item->type ?? '') . ' ' .
                                ($comp->company_code ?? '') . ' ' .
                                ($comp->company_name ?? '') . ' ' .
                                $compType
                            );
                            $editPayload = [
                                'id'           => $item->id,
                                'part_no'      => $item->part_no ?? '',
                                'part_name'    => $item->part_name,
                                'type'         => $item->type,
                                'uom'          => $item->uom,
                                'company_id'   => $item->company_id,
                                'qty_po'       => (float) $item->qty_po,
                                'working_days' => (int) $item->working_days,
                                'qty_kanban'   => (float) $item->qty_kanban,
                                'has_history'  => $hasHist,
                            ];
                        @endphp
                        <tr data-row-item
                            data-search="{{ $searchStr }}"
                            data-type="{{ $item->type }}"
                            data-comptype="{{ $compType }}"
                            x-show="matchFilter($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $item->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $item->part_name }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-neu">{{ $item->type }}</span>
                                <div class="num text-[11px] font-semibold text-slate-500 mt-0.5">{{ $item->uom }}</div>
                            </td>
                            <td class="text-center">
                                <div class="num text-xs font-bold text-accent">{{ $comp->company_code ?? '-' }}</div>
                                <div class="mt-0.5">
                                    @if($compType === 'SUPPLIER')
                                        <span class="pill pill-info text-[10px] h-4 px-1.5">SUPPLIER</span>
                                    @elseif($compType === 'INTERNAL')
                                        <span class="pill pill-warn text-[10px] h-4 px-1.5">INTERNAL</span>
                                    @else
                                        <span class="pill pill-good text-[10px] h-4 px-1.5">CUSTOMER</span>
                                    @endif
                                </div>
                            </td>
                            <td class="num text-xs text-slate-700 whitespace-nowrap">
                                @if(in_array($item->type, ['FG', 'TRANSIT', 'FG/KOMPONEN'], true))
                                    <b>{{ floatval($item->qty_po) }}</b> / {{ $item->working_days }}d / K:{{ floatval($item->qty_kanban) }}
                                @else
                                    <span class="text-slate-400">Ikut BOM</span>
                                @endif
                            </td>
                            <td class="num text-xs font-medium text-slate-700">
                                {{ floatval($item->std_day_1_po_wd) }}
                            </td>
                            <td class="num font-bold text-accent">{{ floatval($std1) }}</td>
                            <td class="num font-semibold text-slate-700">{{ floatval($std2) }}</td>
                            <td class="num font-semibold text-slate-700">{{ floatval($std3) }}</td>
                            <td class="text-center">
                                <div class="inline-flex items-center justify-center gap-1">
                                    <button type="button" class="act-btn"
                                            title="Informasi Material, BOM & Riwayat Mutasi"
                                            @click="$dispatch('open-material-info', {{ Js::from($item->material_info_payload) }})">
                                        <x-icon name="info" />
                                    </button>

                                    <button type="button" class="act-btn"
                                            title="Edit Master Item"
                                            @click="openEdit({{ Js::from($editPayload) }})">
                                        <x-icon name="edit" />
                                    </button>

                                    @if($hasHist)
                                        <form action="{{ route('items.destroy', $item->id) }}" method="POST" class="inline"
                                              @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Nonaktifkan Master Item', message: 'Item {{ addslashes($item->part_name) }} sudah memiliki riwayat transaksi/BOM. Nonaktifkan dan pindahkan ke daftar Item Nonaktif?' })">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="act-btn warn" title="Nonaktifkan Item (Memiliki Riwayat)">
                                                <x-icon name="ban" />
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('items.destroy', $item->id) }}" method="POST" class="inline"
                                              @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Hapus Permanen Master Item', message: 'Item {{ addslashes($item->part_name) }} belum memiliki riwayat transaksi. Hapus permanen dari database?' })">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="act-btn danger" title="Hapus Permanen (Belum Ada Riwayat)">
                                                <x-icon name="trash" />
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="empty">Belum ada Master Item yang terdaftar.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL TAMBAH MASTER ITEM --}}
        <div x-show="addOpen" x-cloak
             class="modal-backdrop"
             @click.self="addOpen = false">
            <div class="panel w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Registrasi Master Item Baru</h3>
                    <button type="button" class="act-btn" @click="addOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>
                <form action="{{ route('items.store') }}" method="POST" class="flex-1 overflow-y-auto">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Part No (Opsional untuk RM Sheet/Coil)</label>
                                <input type="text" name="part_no" x-model="form.part_no"
                                       oninput="this.value = this.value.toUpperCase()"
                                       placeholder="Kosongkan jika RM tidak punya Part No"
                                       class="num uppercase w-full h-9 text-xs">
                            </div>
                            <div>
                                <label class="lbl">Part Name / Spesifikasi (Wajib)</label>
                                <input type="text" name="part_name" x-model="form.part_name" required
                                       oninput="this.value = this.value.toUpperCase()"
                                       placeholder="Contoh: BRACKET MUFFLER ASSY FG"
                                       class="uppercase w-full h-9 text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Tipe Item</label>
                                <select name="type" x-model="form.type" @change="onTypeChange(form)" required class="w-full h-9 text-xs">
                                    @foreach($types as $tp)
                                        <option value="{{ $tp }}">{{ $tp }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Satuan (UOM)</label>
                                <select name="uom" x-model="form.uom" required class="w-full h-9 text-xs">
                                    @foreach($uoms as $u)
                                        <option value="{{ $u }}">{{ $u }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="lbl">Perusahaan</label>
                            <select name="company_id" x-model="form.company_id" required class="w-full h-9 text-xs">
                                <option value="">-- Pilih Perusahaan --</option>
                                <template x-for="c in allowedCompanies(form.type)" :key="c.id">
                                    <option :value="c.id" x-text="c.code + ' — ' + c.name + ' (' + c.type + ')'"></option>
                                </template>
                            </select>
                            <p class="hint" x-text="companyHint(form.type)"></p>
                        </div>

                        <template x-if="allowsPo(form.type)">
                            <div class="p-3 rounded border border-slate-200 bg-slate-50 space-y-3">
                                <div class="text-xs font-bold text-slate-800 uppercase">Parameter Target PO &amp; Kanban</div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label class="lbl">Qty PO Sebulan</label>
                                        <input type="number" step="0.01" min="0" name="qty_po" x-model="form.qty_po" required class="num w-full h-9 text-xs font-semibold">
                                    </div>
                                    <div>
                                        <label class="lbl">Working Days (Hari Kerja)</label>
                                        <input type="number" step="1" min="0" name="working_days" x-model="form.working_days" required class="num w-full h-9 text-xs font-semibold">
                                    </div>
                                    <div>
                                        <label class="lbl">Qty per Kanban / Box</label>
                                        <input type="number" step="0.01" min="0" name="qty_kanban" x-model="form.qty_kanban" required class="num w-full h-9 text-xs font-semibold">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 border-t border-slate-200 text-xs">
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">PO / Working Days:</span>
                                        <b class="num text-slate-900" x-text="calcPreview(form).poWd"></b>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">Std Day 1 (Kanban):</span>
                                        <b class="num text-accent" x-text="calcPreview(form).std1"></b>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">Std Day 2 (Min):</span>
                                        <b class="num text-slate-900" x-text="calcPreview(form).std2"></b>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">Std Day 3 (Max):</span>
                                        <b class="num text-slate-900" x-text="calcPreview(form).std3"></b>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="!allowsPo(form.type)">
                            <div class="p-3 rounded border border-slate-200 bg-slate-50 text-xs text-slate-600">
                                Untuk tipe <b x-text="form.type"></b>, parameter PO, Working Days, dan Kanban dikunci <b>0</b> karena nilai <b>Std Day 1/2/3</b> dihitung otomatis secara <i>on-the-fly</i> dari struktur resep <b>BOM</b>.
                            </div>
                        </template>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Master Item</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- POP-UP MODAL EDIT MASTER ITEM --}}
        <div x-show="editOpen" x-cloak
             class="modal-backdrop"
             @click.self="editOpen = false">
            <div class="panel w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Edit Master Item</h3>
                    <button type="button" class="act-btn" @click="editOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>
                <form :action="'{{ url('items') }}/' + editForm.id" method="POST" class="flex-1 overflow-y-auto">
                    @csrf
                    @method('PUT')
                    <div class="panel-body space-y-4">
                        <div x-show="editForm.has_history" class="p-3 rounded bg-amber-50 border border-amber-200 text-xs text-amber-800">
                            Item ini sudah memiliki riwayat transaksi atau terdaftar di BOM. Identitas utama (Part No, Part Name, Tipe, UOM, Perusahaan) dikunci untuk menjaga integritas histori, namun parameter PO/Kanban tetap dapat diperbarui.
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Part No</label>
                                <input type="text" name="part_no" x-model="editForm.part_no"
                                       :disabled="editForm.has_history"
                                       oninput="this.value = this.value.toUpperCase()"
                                       class="num uppercase w-full h-9 text-xs">
                            </div>
                            <div>
                                <label class="lbl">Part Name / Spesifikasi</label>
                                <input type="text" name="part_name" x-model="editForm.part_name"
                                       :disabled="editForm.has_history" required
                                       oninput="this.value = this.value.toUpperCase()"
                                       class="uppercase w-full h-9 text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Tipe Item</label>
                                <select name="type" x-model="editForm.type"
                                        :disabled="editForm.has_history"
                                        @change="onTypeChange(editForm)" required class="w-full h-9 text-xs">
                                    @foreach($types as $tp)
                                        <option value="{{ $tp }}">{{ $tp }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Satuan (UOM)</label>
                                <select name="uom" x-model="editForm.uom"
                                        :disabled="editForm.has_history" required class="w-full h-9 text-xs">
                                    @foreach($uoms as $u)
                                        <option value="{{ $u }}">{{ $u }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="lbl">Perusahaan</label>
                            <select name="company_id" x-model="editForm.company_id"
                                    :disabled="editForm.has_history" required class="w-full h-9 text-xs">
                                <option value="">-- Pilih Perusahaan --</option>
                                <template x-for="c in allowedCompanies(editForm.type)" :key="c.id">
                                    <option :value="c.id" x-text="c.code + ' — ' + c.name + ' (' + c.type + ')'"></option>
                                </template>
                            </select>
                            <p class="hint" x-text="companyHint(editForm.type)"></p>
                        </div>

                        <template x-if="allowsPo(editForm.type)">
                            <div class="p-3 rounded border border-slate-200 bg-slate-50 space-y-3">
                                <div class="text-xs font-bold text-slate-800 uppercase">Parameter Target PO &amp; Kanban</div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label class="lbl">Qty PO Sebulan</label>
                                        <input type="number" step="0.01" min="0" name="qty_po" x-model="editForm.qty_po" required class="num w-full h-9 text-xs font-semibold">
                                    </div>
                                    <div>
                                        <label class="lbl">Working Days (Hari Kerja)</label>
                                        <input type="number" step="1" min="0" name="working_days" x-model="editForm.working_days" required class="num w-full h-9 text-xs font-semibold">
                                    </div>
                                    <div>
                                        <label class="lbl">Qty per Kanban / Box</label>
                                        <input type="number" step="0.01" min="0" name="qty_kanban" x-model="editForm.qty_kanban" required class="num w-full h-9 text-xs font-semibold">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 border-t border-slate-200 text-xs">
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">PO / Working Days:</span>
                                        <b class="num text-slate-900" x-text="calcPreview(editForm).poWd"></b>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">Std Day 1 (Kanban):</span>
                                        <b class="num text-accent" x-text="calcPreview(editForm).std1"></b>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">Std Day 2 (Min):</span>
                                        <b class="num text-slate-900" x-text="calcPreview(editForm).std2"></b>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">Std Day 3 (Max):</span>
                                        <b class="num text-slate-900" x-text="calcPreview(editForm).std3"></b>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="!allowsPo(editForm.type)">
                            <div class="p-3 rounded border border-slate-200 bg-slate-50 text-xs text-slate-600">
                                Item bertipe <b x-text="editForm.type"></b> mengikuti perhitungan standar stok dari <b>BOM</b> secara otomatis.
                            </div>
                        </template>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="editOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary" x-show="!editForm.has_history || allowsPo(editForm.type)">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-material-info-modal />

    <script>
        function masterItemPage(initialRows, companiesList, openInitially) {
            return {
                q: '',
                fType: '',
                fCompType: '',
                visibleRows: initialRows,
                companies: companiesList,
                addOpen: openInitially,
                editOpen: false,
                form: {
                    part_no: '',
                    part_name: '',
                    type: 'FG',
                    uom: 'PCS',
                    company_id: '',
                    qty_po: 0,
                    working_days: 20,
                    qty_kanban: 0
                },
                editForm: {
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
                openAdd() {
                    this.addOpen = true;
                },
                openEdit(payload) {
                    this.editForm = Object.assign({}, payload);
                    this.editOpen = true;
                },
                allowsPo(type) {
                    return ['FG', 'TRANSIT', 'FG/KOMPONEN'].includes(type);
                },
                allowedCompanies(type) {
                    const map = {
                        'RM': ['SUPPLIER'],
                        'KOMPONEN': ['SUPPLIER', 'INTERNAL'],
                        'FG/KOMPONEN': ['INTERNAL'],
                        'TRANSIT': ['SUPPLIER'],
                        'FG': ['CUSTOMER']
                    };
                    const allowed = map[type] || ['SUPPLIER', 'INTERNAL', 'CUSTOMER'];
                    return this.companies.filter(c => allowed.includes(c.type));
                },
                onTypeChange(targetForm) {
                    const list = this.allowedCompanies(targetForm.type);
                    if (!list.some(c => String(c.id) === String(targetForm.company_id))) {
                        targetForm.company_id = list.length ? list[0].id : '';
                    }
                },
                companyHint(type) {
                    if (type === 'RM') return 'Pilih SUPPLIER pemasok bahan baku (RM) ini.';
                    if (type === 'KOMPONEN') return 'Pilih SUPPLIER jika dibeli dari vendor luar, atau INTERNAL jika diproduksi sendiri.';
                    if (type === 'FG/KOMPONEN') return 'Pilih perusahaan INTERNAL (hasil produksi internal yang dapat dikirim maupun dipakai proses lanjut).';
                    if (type === 'TRANSIT') return 'Pilih SUPPLIER asal barang Transit.';
                    if (type === 'FG') return 'Pilih CUSTOMER tujuan pengiriman produk akhir (FG) ini.';
                    return '';
                },
                calcPreview(f) {
                    const po = parseFloat(f.qty_po) || 0;
                    const wd = parseInt(f.working_days) || 0;
                    const kb = parseFloat(f.qty_kanban) || 0;
                    if (po <= 0 || wd <= 0) return { poWd: 0, std1: 0, std2: 0, std3: 0 };
                    const poWd = Math.round((po / wd) * 100) / 100;
                    const std1 = kb > 0 ? Math.ceil(poWd / kb) * kb : Math.ceil(poWd);
                    return {
                        poWd: poWd,
                        std1: Math.round(std1 * 100) / 100,
                        std2: Math.round(std1 * 2 * 100) / 100,
                        std3: Math.round(std1 * 3 * 100) / 100
                    };
                },
                matchFilter(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const tp = el.dataset.type || '';
                    const ct = el.dataset.comptype || '';
                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.fType && tp !== this.fType) return false;
                    if (this.fCompType && ct !== this.fCompType) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-row-item]').forEach(tr => {
                            if (this.matchFilter(tr)) c++;
                        });
                        this.visibleRows = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>