<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Master Item"
                       subtitle="Pengelolaan identitas part, tipe barang, perusahaan, serta kalkulasi standar stok harian (PO / Kanban & BOM)">
            <x-slot name="actions">
                <a href="{{ route('items.trashed') }}" class="btn">
                    <x-icon name="ban" /> Item Nonaktif ({{ $trashedCount }})
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
         @open-add-item.window="openAddModal()"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Master Item Aktif</span>
                <span class="count-chip"><span x-text="visibleRows"></span><span>Item</span></span>
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

                <button type="button"
                        x-show="q || fType || fCompType"
                        x-cloak
                        @click="q=''; fType=''; fCompType=''; recalc()"
                        class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[4%] text-center">No</th>
                        <th class="w-[21%]">Part No &amp; Part Name</th>
                        <th class="w-[9%] text-center">Tipe &amp; UOM</th>
                        <th class="w-[12%]">Perusahaan</th>
                        <th class="num w-[7%]">Qty PO</th>
                        <th class="num w-[5%]">WD</th>
                        <th class="num w-[7%]">Kanban</th>
                        <th class="num w-[7%]">PO/WD</th>
                        <th class="num w-[7%]">Std Day 1</th>
                        <th class="num w-[7%]">Std Day 2</th>
                        <th class="num w-[7%]">Std Day 3</th>
                        <th class="w-[7%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $idx => $item)
                        @php
                            $comp = $item->company;
                            $compType = $comp->type_company ?? 'INTERNAL';
                            $hasHistory = $item->hasTransactionHistory();
                            $std1 = $item->calculated_std_day_1;
                            $std2 = $item->std_day_2;
                            $std3 = $item->std_day_3;
                            $payload = $item->material_info_payload;
                            $searchStr = strtolower(
                                ($item->part_no ?? '') . ' ' .
                                ($item->part_name ?? '') . ' ' .
                                ($item->type ?? '') . ' ' .
                                ($comp->company_code ?? '') . ' ' .
                                ($comp->company_name ?? '')
                            );
                        @endphp
                        <tr data-item-row
                            data-search="{{ $searchStr }}"
                            data-type="{{ $item->type }}"
                            data-comptype="{{ $compType }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $item->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $item->part_name }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-neu">{{ $item->type }}</span>
                                <div class="num text-[11px] font-semibold text-slate-500 mt-0.5">{{ $item->uom }}</div>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $comp->company_code ?? '-' }}</div>
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
                            <td class="num text-xs">{{ in_array($item->type, ['RM', 'KOMPONEN']) ? '0' : floatval($item->qty_po) }}</td>
                            <td class="num text-xs">{{ in_array($item->type, ['RM', 'KOMPONEN']) ? '0' : $item->working_days }}</td>
                            <td class="num text-xs">{{ in_array($item->type, ['RM', 'KOMPONEN']) ? '0' : floatval($item->qty_kanban) }}</td>
                            <td class="num text-xs">{{ in_array($item->type, ['RM', 'KOMPONEN']) ? '0' : floatval($item->std_day_1_po_wd) }}</td>
                            <td class="num font-bold text-accent text-xs">{{ floatval($std1) }}</td>
                            <td class="num font-semibold text-slate-700 text-xs">{{ floatval($std2) }}</td>
                            <td class="num font-semibold text-slate-700 text-xs">{{ floatval($std3) }}</td>
                            <td class="text-center whitespace-nowrap">
                                <div class="inline-flex items-center justify-center gap-1">
                                    <button type="button" class="act-btn" title="Info Material & BOM"
                                            @click="$dispatch('open-material-info', {{ Js::from($payload) }})">
                                        <x-icon name="info" />
                                    </button>

                                    <button type="button" class="act-btn" title="Edit Master Item"
                                            @click="openEditModal({
                                                id: {{ $item->id }},
                                                part_no: '{{ addslashes($item->part_no ?? '') }}',
                                                part_name: '{{ addslashes($item->part_name) }}',
                                                type: '{{ $item->type }}',
                                                uom: '{{ $item->uom }}',
                                                company_id: '{{ $item->company_id }}',
                                                qty_po: '{{ floatval($item->qty_po) }}',
                                                working_days: '{{ $item->working_days }}',
                                                qty_kanban: '{{ floatval($item->qty_kanban) }}',
                                                has_history: {{ $hasHistory ? 'true' : 'false' }}
                                            })">
                                        <x-icon name="edit" />
                                    </button>

                                    @if($hasHistory)
                                        <form action="{{ route('items.destroy', $item->id) }}" method="POST" class="inline"
                                              @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Nonaktifkan Item', message: 'Item {{ addslashes($item->part_name) }} sudah memiliki riwayat transaksi. Nonaktifkan item ini?' })">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="act-btn warn" title="Nonaktifkan Item">
                                                <x-icon name="ban" />
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('items.destroy', $item->id) }}" method="POST" class="inline"
                                              @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Hapus Permanen Item', message: 'Hapus permanen {{ addslashes($item->part_name) }} dari database?' })">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="act-btn danger" title="Hapus Permanen">
                                                <x-icon name="trash" />
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12"><div class="empty">Belum ada Master Item yang terdaftar.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL TAMBAH MASTER ITEM BARU --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false">
            <div class="panel w-full max-w-2xl overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Tambah Master Item Baru</h3>
                    <button type="button" class="act-btn" @click="addOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>
                <form action="{{ route('items.store') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Part No (Opsional untuk RM Sheet/Coil)</label>
                                <input type="text" name="part_no" placeholder="Contoh: FG-BKT-001"
                                       oninput="this.value = this.value.toUpperCase()"
                                       class="num uppercase w-full h-9">
                            </div>
                            <div>
                                <label class="lbl">Part Name (Wajib)</label>
                                <input type="text" name="part_name" required placeholder="Contoh: BRACKET MUFFLER ASSY"
                                       oninput="this.value = this.value.toUpperCase()"
                                       class="uppercase w-full h-9">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Tipe Item</label>
                                <select name="type" x-model="addType" @change="syncAddCompany()" required class="w-full h-9">
                                    @foreach($types as $t)
                                        <option value="{{ $t }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Satuan (UOM)</label>
                                <select name="uom" required class="w-full h-9">
                                    @foreach($uoms as $u)
                                        <option value="{{ $u }}">{{ $u }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="lbl">Perusahaan</label>
                            <select name="company_id" x-model="addCompanyId" required class="w-full h-9">
                                <option value="">-- Pilih Perusahaan --</option>
                                <template x-for="c in allowedCompanies(addType)" :key="c.id">
                                    <option :value="c.id" x-text="c.code + ' — ' + c.name + ' (' + c.type + ')'"></option>
                                </template>
                            </select>
                            <p class="hint" x-text="companyGuide(addType)"></p>
                        </div>

                        <div x-show="allowsPo(addType)" class="p-3 rounded bg-slate-50 border border-slate-200 space-y-3">
                            <div class="text-xs font-bold text-slate-800">Parameter PO &amp; Kanban (Untuk Kalkulasi Std Day 1/2/3)</div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="lbl">Qty PO Sebulan</label>
                                    <input type="number" step="0.01" min="0" name="qty_po" value="0" class="num w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl">Working Days (WD)</label>
                                    <input type="number" min="0" name="working_days" value="20" class="num w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl">Qty per Kanban</label>
                                    <input type="number" step="0.01" min="0" name="qty_kanban" value="0" class="num w-full h-9">
                                </div>
                            </div>
                        </div>

                        <div x-show="!allowsPo(addType)" x-cloak class="p-3 rounded bg-slate-50 border border-slate-200 text-xs text-slate-600">
                            Untuk tipe <b x-text="addType"></b>, parameter PO/WD/Kanban dikunci <b class="num">0</b> karena kebutuhan stok hariannya (<b class="num">Std Day 1/2/3</b>) dihitung otomatis secara <i>on-the-fly</i> dari resep BOM.
                        </div>
                    </div>
                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Item Baru</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- POP-UP MODAL EDIT MASTER ITEM --}}
        <div x-show="editOpen" x-cloak class="modal-backdrop" @click.self="editOpen = false">
            <div class="panel w-full max-w-2xl overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Edit Master Item</h3>
                    <button type="button" class="act-btn" @click="editOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>
                <form :action="'{{ url('items') }}/' + editData.id" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="panel-body space-y-4">
                        <template x-if="editData.has_history">
                            <div class="alert alert-crit text-xs">
                                Identitas utama terkunci karena item ini sudah memiliki riwayat transaksi / BOM. Anda tetap dapat memperbarui parameter PO, Working Days, dan Kanban (untuk tipe FG, FG/KOMPONEN, &amp; TRANSIT).
                            </div>
                        </template>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Part No</label>
                                <input type="text" name="part_no" x-model="editData.part_no"
                                       :disabled="editData.has_history"
                                       oninput="this.value = this.value.toUpperCase()"
                                       class="num uppercase w-full h-9">
                            </div>
                            <div>
                                <label class="lbl">Part Name</label>
                                <input type="text" name="part_name" x-model="editData.part_name"
                                       :disabled="editData.has_history"
                                       required
                                       oninput="this.value = this.value.toUpperCase()"
                                       class="uppercase w-full h-9">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Tipe Item</label>
                                <select name="type" x-model="editData.type" :disabled="editData.has_history" class="w-full h-9">
                                    @foreach($types as $t)
                                        <option value="{{ $t }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Satuan (UOM)</label>
                                <select name="uom" x-model="editData.uom" :disabled="editData.has_history" class="w-full h-9">
                                    @foreach($uoms as $u)
                                        <option value="{{ $u }}">{{ $u }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="lbl">Perusahaan</label>
                            <select name="company_id" x-model="editData.company_id" :disabled="editData.has_history" class="w-full h-9">
                                <template x-for="c in allowedCompanies(editData.type)" :key="c.id">
                                    <option :value="c.id" x-text="c.code + ' — ' + c.name + ' (' + c.type + ')'"></option>
                                </template>
                            </select>
                        </div>

                        <div x-show="allowsPo(editData.type)" class="p-3 rounded bg-slate-50 border border-slate-200 space-y-3">
                            <div class="text-xs font-bold text-slate-800">Parameter PO &amp; Kanban</div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="lbl">Qty PO Sebulan</label>
                                    <input type="number" step="0.01" min="0" name="qty_po" x-model="editData.qty_po" class="num w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl">Working Days (WD)</label>
                                    <input type="number" min="0" name="working_days" x-model="editData.working_days" class="num w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl">Qty per Kanban</label>
                                    <input type="number" step="0.01" min="0" name="qty_kanban" x-model="editData.qty_kanban" class="num w-full h-9">
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
    </div>

    <x-material-info-modal />

    <script>
        function masterItemPage(initialCount, companiesList, openInitially) {
            return {
                q: '',
                fType: '',
                fCompType: '',
                visibleRows: initialCount,
                companiesList: companiesList,
                addOpen: openInitially,
                addType: 'FG',
                addCompanyId: '',
                editOpen: false,
                editData: {
                    id: null,
                    part_no: '',
                    part_name: '',
                    type: 'FG',
                    uom: 'PCS',
                    company_id: '',
                    qty_po: 0,
                    working_days: 20,
                    qty_kanban: 0,
                    has_history: false
                },

                allowsPo(type) {
                    return ['FG', 'FG/KOMPONEN', 'TRANSIT'].includes(type);
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
                    return this.companiesList.filter(c => allowed.includes(c.type));
                },
                companyGuide(type) {
                    const guides = {
                        'RM': 'Pilih perusahaan SUPPLIER pemasok bahan baku (RM) ini.',
                        'KOMPONEN': 'Pilih SUPPLIER jika dibeli dari vendor luar, atau INTERNAL jika diproduksi sendiri.',
                        'FG/KOMPONEN': 'Pilih perusahaan INTERNAL (hasil produksi internal yang dapat dikirim maupun dipakai proses lanjut).',
                        'TRANSIT': 'Pilih SUPPLIER asal barang Transit (masuk lewat Inbound lalu dikirim lewat Delivery).',
                        'FG': 'Pilih CUSTOMER tujuan pengiriman produk akhir (FG) ini.'
                    };
                    return guides[type] || '';
                },
                syncAddCompany() {
                    const list = this.allowedCompanies(this.addType);
                    if (!list.some(c => String(c.id) === String(this.addCompanyId))) {
                        this.addCompanyId = list.length > 0 ? String(list[0].id) : '';
                    }
                },
                openAddModal() {
                    this.syncAddCompany();
                    this.addOpen = true;
                },
                openEditModal(data) {
                    this.editData = Object.assign({}, data);
                    this.editOpen = true;
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
                            if (this.matchRow(tr)) c++;
                        });
                        this.visibleRows = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>