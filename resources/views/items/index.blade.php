<x-app-layout>
    @php
        $companyList = $companies->map(function ($c) {
            return [
                'id'           => (string) $c->id,
                'company_code' => $c->company_code,
                'company_name' => $c->company_name,
                'type_company' => $c->type_company,
            ];
        })->values();
    @endphp

    <div x-data="{
            openCreate: {{ $errors->any() ? 'true' : 'false' }},
            createType: '{{ old('type', 'FG') }}',
            createCompanyId: '{{ old('company_id', '') }}',
            createPo: {{ (float) old('qty_po', 0) }},
            createWd: {{ (int) old('working_days', 20) }},
            createKb: {{ (float) old('qty_kanban', 0) }},
            companies: {{ Js::from($companyList) }},

            editModal: false,
            editItem: {
                id: null, part_no: '', part_name: '', type: 'FG', uom: 'PCS',
                company_id: '', qty_po: 0, working_days: 0, qty_kanban: 0, has_history: false
            },

            allowedCompanyTypes(itemType) {
                if (itemType === 'RM') return ['SUPPLIER'];
                if (itemType === 'KOMPONEN') return ['SUPPLIER', 'INTERNAL'];
                if (itemType === 'FG/KOMPONEN') return ['INTERNAL'];
                if (itemType === 'TRANSIT') return ['SUPPLIER'];
                if (itemType === 'FG') return ['CUSTOMER'];
                return ['SUPPLIER', 'INTERNAL', 'CUSTOMER'];
            },
            companyGuideText(itemType) {
                if (itemType === 'RM') return 'Menampilkan perusahaan SUPPLIER (pemasok bahan baku RM).';
                if (itemType === 'KOMPONEN') return 'Pilih SUPPLIER jika komponen dibeli dari vendor luar, atau INTERNAL jika diproduksi sendiri.';
                if (itemType === 'FG/KOMPONEN') return 'Menampilkan perusahaan INTERNAL (diproduksi internal, dapat dikirim ke Customer maupun dipakai proses lanjut).';
                if (itemType === 'TRANSIT') return 'Menampilkan perusahaan SUPPLIER (barang masuk dari Supplier untuk dikirim langsung ke Customer).';
                if (itemType === 'FG') return 'Menampilkan perusahaan CUSTOMER (tujuan pengiriman produk akhir FG).';
                return '';
            },
            filteredCompanies(itemType) {
                let allowed = this.allowedCompanyTypes(itemType);
                return this.companies.filter(c => allowed.includes(c.type_company));
            },
            ensureValidCompany(mode) {
                if (mode === 'create') {
                    let list = this.filteredCompanies(this.createType);
                    if (!list.some(c => String(c.id) === String(this.createCompanyId))) {
                        this.createCompanyId = list.length ? String(list[0].id) : '';
                    }
                } else {
                    let list = this.filteredCompanies(this.editItem.type);
                    if (!list.some(c => String(c.id) === String(this.editItem.company_id))) {
                        this.editItem.company_id = list.length ? String(list[0].id) : '';
                    }
                }
            },
            allowsPo(t) {
                return ['FG', 'TRANSIT', 'FG/KOMPONEN'].includes(t);
            },
            calcPoWd(po, wd) {
                let p = parseFloat(po) || 0;
                let w = parseInt(wd) || 0;
                return w > 0 ? (p / w).toFixed(2) : '0.00';
            },
            calcCeilKanban(po, wd, kb) {
                let poWd = parseFloat(this.calcPoWd(po, wd)) || 0;
                if (poWd <= 0) return '0';
                let k = parseFloat(kb) || 0;
                return k > 0 ? (Math.ceil(poWd / k) * k).toFixed(2).replace(/\.00$/, '') : Math.ceil(poWd);
            },
            openEditModal(payload) {
                this.editItem = { ...payload, company_id: String(payload.company_id || '') };
                this.editModal = true;
            },
            init() {
                if (!this.createCompanyId) {
                    this.ensureValidCompany('create');
                }
            }
         }" class="space-y-[18px]">

        <div class="page-head">
            <x-page-header title="Master Item"
                           subtitle="Parameter PO, Working Days, Kanban, dan Standar Stok Harian (FG, FG/KOMPONEN, KOMPONEN, RM, TRANSIT)">
                <x-slot name="actions">
                    <a href="{{ route('items.trashed') }}" class="btn">
                        <x-icon name="ban" /> Item Nonaktif ({{ $trashedCount }})
                    </a>
                    <button type="button" @click="ensureValidCompany('create'); openCreate = true" class="btn btn-primary">
                        <x-icon name="plus" /> Tambah Item
                    </button>
                </x-slot>
            </x-page-header>
        </div>

        <x-flash />

        <div class="panel">
            <div class="panel-head">
                <h3 class="panel-title">Daftar Master Item Aktif ({{ $items->count() }} Item)</h3>
                <form action="{{ route('items.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Cari Part No / Part Name..."
                           class="filter-input w-52">
                    <select name="type" class="filter-input w-40">
                        <option value="">Semua Tipe Item</option>
                        @foreach ($types as $t)
                            <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                    <select name="type_company" class="filter-input w-44">
                        <option value="">Semua Tipe Perusahaan</option>
                        <option value="SUPPLIER" @selected(request('type_company') === 'SUPPLIER')>SUPPLIER</option>
                        <option value="INTERNAL" @selected(request('type_company') === 'INTERNAL')>INTERNAL</option>
                        <option value="CUSTOMER" @selected(request('type_company') === 'CUSTOMER')>CUSTOMER</option>
                    </select>
                </form>
            </div>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th class="w-14 text-center">No</th>
                            <th>Part No &amp; Part Name</th>
                            <th class="text-center">Type &amp; UOM</th>
                            <th>Perusahaan</th>
                            <th class="num">Qty PO</th>
                            <th class="num">WD</th>
                            <th class="num">Kanban</th>
                            <th class="num">PO / WD</th>
                            <th class="num">Std Day 1</th>
                            <th class="num">Std Day 2</th>
                            <th class="num">Std Day 3</th>
                            <th class="w-32 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            @php
                                $hasHist = $item->hasTransactionHistory();
                                $std1    = $item->calculated_std_day_1;
                                $std2    = $item->std_day_2;
                                $std3    = $item->std_day_3;
                                $infoPayload = $item->material_info_payload;
                                $editPayload = [
                                    'id'           => $item->id,
                                    'part_no'      => $item->part_no ?? '',
                                    'part_name'    => $item->part_name,
                                    'type'         => $item->type,
                                    'uom'          => $item->uom,
                                    'company_id'   => $item->company_id ?? '',
                                    'qty_po'       => (float) $item->qty_po,
                                    'working_days' => (int) $item->working_days,
                                    'qty_kanban'   => (float) $item->qty_kanban,
                                    'has_history'  => $hasHist,
                                ];
                            @endphp
                            <tr>
                                <td class="num text-center text-xs font-semibold text-slate-500">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="num text-xs font-semibold text-accent">{{ $item->part_no ?: '-' }}</div>
                                    <div class="font-semibold text-slate-900">{{ $item->part_name }}</div>
                                </td>
                                <td class="text-center whitespace-nowrap">
                                    <span class="pill pill-neu">{{ $item->type }}</span>
                                    <span class="text-xs font-semibold text-slate-500 ml-1">{{ $item->uom }}</span>
                                </td>
                                <td>
                                    @if ($item->company)
                                        <div class="num text-xs font-semibold text-slate-900">{{ $item->company->company_code }}</div>
                                        <span class="pill {{ $item->company->type_company === 'SUPPLIER' ? 'pill-warn' : ($item->company->type_company === 'INTERNAL' ? 'pill-info' : 'pill-good') }} mt-0.5">
                                            {{ $item->company->type_company }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="num">{{ floatval($item->qty_po) }}</td>
                                <td class="num">{{ $item->working_days }}</td>
                                <td class="num">{{ floatval($item->qty_kanban) }}</td>
                                <td class="num text-slate-600">{{ floatval($item->std_day_1_po_wd) }}</td>
                                <td class="num font-semibold text-accent">{{ floatval($std1) }}</td>
                                <td class="num font-semibold text-amber-700">{{ floatval($std2) }}</td>
                                <td class="num font-semibold text-emerald-700">{{ floatval($std3) }}</td>
                                <td class="text-center whitespace-nowrap">
                                    <div class="inline-flex items-center justify-center gap-1.5">
                                        <button type="button"
                                                @click="$dispatch('open-material-info', {{ Js::from($infoPayload) }})"
                                                class="act-btn" title="Informasi Material, BOM & Riwayat Stok">
                                            <x-icon name="info" />
                                        </button>

                                        @if (!$hasHist || in_array($item->type, ['FG', 'TRANSIT', 'FG/KOMPONEN'], true))
                                            <button type="button"
                                                    @click="openEditModal({{ Js::from($editPayload) }})"
                                                    class="act-btn" title="Edit Item">
                                                <x-icon name="edit" />
                                            </button>
                                        @endif

                                        @if (!$hasHist)
                                            <form action="{{ route('items.destroy', $item->id) }}" method="POST" class="inline"
                                                  onsubmit="return window.askConfirm(this, 'Hapus Permanen Master Item', 'Item {{ addslashes($item->part_name) }} belum memiliki riwayat transaksi. Hapus permanen dari sistem?', true)">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="act-btn danger" title="Hapus Permanen">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('items.destroy', $item->id) }}" method="POST" class="inline"
                                                  onsubmit="return window.askConfirm(this, 'Nonaktifkan Master Item', 'Item {{ addslashes($item->part_name) }} sudah memiliki riwayat transaksi. Pindahkan ke daftar Item Nonaktif?', true)">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="act-btn warn" title="Nonaktifkan Item">
                                                    <x-icon name="ban" />
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12"><div class="empty">Belum ada data Master Item.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- POP-UP MODAL TAMBAH ITEM BARU (SIMETRIS MAX-W-3XL) --}}
        <div x-show="openCreate" x-cloak class="modal-backdrop">
            <div class="panel w-full max-w-3xl max-h-[90vh] flex flex-col" @click.away="openCreate = false">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Tambah Master Item Baru</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Daftarkan identitas item, satuan UOM, perusahaan kepemilikan, serta parameter PO &amp; Kanban.</p>
                    </div>
                    <button type="button" @click="openCreate = false" class="act-btn" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('items.store') }}" method="POST" class="flex-1 overflow-y-auto">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Part No (Opsional untuk Sheet Metal / Coil)</label>
                                <input type="text" name="part_no" value="{{ old('part_no') }}"
                                       oninput="this.value = this.value.toUpperCase()"
                                       placeholder="Contoh: FG-BKT-001"
                                       class="num w-full h-10 uppercase font-semibold">
                            </div>
                            <div>
                                <label class="lbl">Part Name (Wajib)</label>
                                <input type="text" name="part_name" value="{{ old('part_name') }}" required
                                       oninput="this.value = this.value.toUpperCase()"
                                       placeholder="Contoh: SHEET METAL SPHC 2.0MM"
                                       class="w-full h-10 uppercase font-semibold">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Type Item</label>
                                <select name="type" x-model="createType" @change="ensureValidCompany('create')" required class="w-full h-10">
                                    @foreach ($types as $t)
                                        <option value="{{ $t }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="lbl">UOM (Satuan Barang)</label>
                                <select name="uom" required class="w-full h-10">
                                    @foreach ($uoms as $u)
                                        <option value="{{ $u }}" @selected(old('uom') === $u)>{{ $u }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="lbl">Perusahaan Mitra / Kepemilikan</label>
                            <select name="company_id" x-model="createCompanyId" required class="w-full h-10">
                                <template x-for="c in filteredCompanies(createType)" :key="c.id">
                                    <option :value="c.id" x-text="'[' + c.type_company + '] ' + c.company_code + ' — ' + c.company_name"></option>
                                </template>
                            </select>
                            <div class="mt-2 px-3 py-2 rounded border border-blue-200 bg-blue-50/70 text-xs font-medium text-accent"
                                 x-text="companyGuideText(createType)"></div>
                        </div>

                        <div class="p-4 rounded border border-slate-200 bg-slate-50 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-700">Parameter Target PO &amp; Kanban</span>
                                <span x-show="!allowsPo(createType)" class="pill pill-neu">Dikunci 0 (Mengikuti BOM)</span>
                                <span x-show="createType === 'FG/KOMPONEN'" class="pill pill-info">Hibrida: Target PO + Kebutuhan BOM</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="lbl">Qty PO Bulanan</label>
                                    <input type="number" step="0.01" min="0" name="qty_po"
                                           x-model="createPo" :disabled="!allowsPo(createType)"
                                           class="num w-full h-10">
                                </div>
                                <div>
                                    <label class="lbl">Working Days (Hari Kerja)</label>
                                    <input type="number" step="1" min="0" name="working_days"
                                           x-model="createWd" :disabled="!allowsPo(createType)"
                                           class="num w-full h-10">
                                </div>
                                <div>
                                    <label class="lbl">Qty Kanban / Box</label>
                                    <input type="number" step="0.01" min="0" name="qty_kanban"
                                           x-model="createKb" :disabled="!allowsPo(createType)"
                                           class="num w-full h-10">
                                </div>
                            </div>

                            <div x-show="allowsPo(createType)" class="grid grid-cols-2 gap-4 pt-2 border-t border-slate-200 text-xs">
                                <div>
                                    <span class="text-slate-500">Estimasi PO / Working Days:</span>
                                    <b class="num text-slate-900 ml-1" x-text="calcPoWd(createPo, createWd)"></b>
                                </div>
                                <div>
                                    <span class="text-slate-500">Estimasi Std Day 1 (Ceiling Kanban):</span>
                                    <b class="num text-accent ml-1" x-text="calcCeilKanban(createPo, createWd, createKb)"></b>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel-foot">
                        <button type="button" @click="openCreate = false" class="btn">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Master Item</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- POP-UP MODAL EDIT ITEM (SIMETRIS MAX-W-3XL) --}}
        <div x-show="editModal" x-cloak class="modal-backdrop">
            <div class="panel w-full max-w-3xl max-h-[90vh] flex flex-col" @click.away="editModal = false">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Edit Master Item</h3>
                        <p class="text-xs text-slate-500 mt-0.5"
                           x-text="editItem.has_history ? 'Item sudah memiliki riwayat transaksi: hanya parameter PO, Working Days, dan Kanban yang dapat diubah.' : 'Item belum memiliki riwayat transaksi: seluruh kolom dapat diubah.'"></p>
                    </div>
                    <button type="button" @click="editModal = false" class="act-btn" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form :action="'{{ url('items') }}/' + editItem.id" method="POST" class="flex-1 overflow-y-auto">
                    @csrf
                    @method('PUT')
                    <div class="panel-body space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Part No</label>
                                <input type="text" name="part_no" x-model="editItem.part_no"
                                       :disabled="editItem.has_history"
                                       oninput="this.value = this.value.toUpperCase()"
                                       class="num w-full h-10 uppercase font-semibold">
                            </div>
                            <div>
                                <label class="lbl">Part Name</label>
                                <input type="text" name="part_name" x-model="editItem.part_name" required
                                       :disabled="editItem.has_history"
                                       oninput="this.value = this.value.toUpperCase()"
                                       class="w-full h-10 uppercase font-semibold">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Type Item</label>
                                <select name="type" x-model="editItem.type" @change="ensureValidCompany('edit')" :disabled="editItem.has_history" class="w-full h-10">
                                    @foreach ($types as $t)
                                        <option value="{{ $t }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="lbl">UOM (Satuan Barang)</label>
                                <select name="uom" x-model="editItem.uom" :disabled="editItem.has_history" class="w-full h-10">
                                    @foreach ($uoms as $u)
                                        <option value="{{ $u }}">{{ $u }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="lbl">Perusahaan Mitra / Kepemilikan</label>
                            <select name="company_id" x-model="editItem.company_id" :disabled="editItem.has_history" required class="w-full h-10">
                                <template x-for="c in filteredCompanies(editItem.type)" :key="c.id">
                                    <option :value="c.id" x-text="'[' + c.type_company + '] ' + c.company_code + ' — ' + c.company_name"></option>
                                </template>
                            </select>
                            <div x-show="!editItem.has_history" class="mt-2 px-3 py-2 rounded border border-blue-200 bg-blue-50/70 text-xs font-medium text-accent"
                                 x-text="companyGuideText(editItem.type)"></div>
                        </div>

                        <div class="p-4 rounded border border-slate-200 bg-slate-50 space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="lbl">Qty PO Bulanan</label>
                                    <input type="number" step="0.01" min="0" name="qty_po"
                                           x-model="editItem.qty_po" :disabled="!allowsPo(editItem.type)"
                                           class="num w-full h-10">
                                </div>
                                <div>
                                    <label class="lbl">Working Days</label>
                                    <input type="number" step="1" min="0" name="working_days"
                                           x-model="editItem.working_days" :disabled="!allowsPo(editItem.type)"
                                           class="num w-full h-10">
                                </div>
                                <div>
                                    <label class="lbl">Qty Kanban / Box</label>
                                    <input type="number" step="0.01" min="0" name="qty_kanban"
                                           x-model="editItem.qty_kanban" :disabled="!allowsPo(editItem.type)"
                                           class="num w-full h-10">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" @click="editModal = false" class="btn">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

        <x-material-info-modal />
    </div>
</x-app-layout>