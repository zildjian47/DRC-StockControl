<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Master Item"
                       subtitle="Daftar barang aktif beserta kalkulasi buffer PO, Working Days, Kanban, dan BOM">
            <x-slot name="actions">
                <span class="btn-group">
                    <a href="{{ route('items.trashed') }}" class="btn">
                        <x-icon name="ban" /> <span>Item Nonaktif ({{ $trashedCount }})</span>
                    </a>
                    <button type="button" class="btn btn-primary" @click="$dispatch('open-add-item')">
                        <x-icon name="plus" /> <span>Tambah Item</span>
                    </button>
                </span>
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
         @open-add-item.window="addOpen = true"
         x-init="syncAddCompany(); recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Master Item Aktif</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>Item</span></span>
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
                        <th class="w-[24%]">Part No &amp; Part Name</th>
                        <th class="w-[10%] text-center">Tipe &amp; UOM</th>
                        <th class="w-[13%]">Perusahaan</th>
                        <th class="num w-[8%]">Qty PO</th>
                        <th class="num w-[6%]">WD</th>
                        <th class="num w-[8%]">Kanban</th>
                        <th class="num w-[7%]">Std 1</th>
                        <th class="num w-[7%]">Std 2</th>
                        <th class="num w-[7%]">Std 3</th>
                        <th class="w-[6%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $idx => $item)
                        @php
                            $compCode = $item->company->company_code ?? '-';
                            $compType = $item->company->type_company ?? 'INTERNAL';
                            $std1 = $item->calculated_std_day_1;
                            $std2 = $item->std_day_2;
                            $std3 = $item->std_day_3;
                            $hasHist = $item->hasTransactionHistory();
                            $tyCls = 'ty-' . ($item->type === 'FG/KOMPONEN' ? 'FGK' : $item->type);
                            $editPayload = [
                                'id'           => $item->id,
                                'part_no'      => $item->part_no ?? '',
                                'part_name'    => $item->part_name,
                                'type'         => $item->type,
                                'uom'          => $item->uom,
                                'company_id'   => $item->company_id,
                                'qty_po'       => floatval($item->qty_po),
                                'working_days' => (int) $item->working_days,
                                'qty_kanban'   => floatval($item->qty_kanban),
                                'has_history'  => $hasHist,
                            ];
                        @endphp
                        <tr data-item-row
                            data-search="{{ strtolower(($item->part_no ?? '') . ' ' . $item->part_name . ' ' . $compCode) }}"
                            data-type="{{ $item->type }}"
                            data-comptype="{{ $compType }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $item->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900">{{ $item->part_name }}</div>
                            </td>
                            <td class="text-center whitespace-nowrap">
                                <span class="ty {{ $tyCls }}">{{ $item->type }}</span>
                                <div class="num text-[11px] font-semibold text-slate-500 mt-0.5">{{ $item->uom }}</div>
                            </td>
                            <td>
                                <div class="font-bold text-slate-800">{{ $compCode }}</div>
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
                            <td class="num font-semibold text-slate-800">{{ floatval($std1) }}</td>
                            <td class="num font-semibold text-amber-700">{{ floatval($std2) }}</td>
                            <td class="num font-semibold text-emerald-700">{{ floatval($std3) }}</td>
                            <td class="text-center whitespace-nowrap">
                                <div class="inline-flex items-center justify-center gap-1">
                                    <button type="button" class="act-btn" title="Informasi Material & BOM"
                                            @click="$dispatch('open-material-info', {{ Js::from($item->material_info_payload) }})">
                                        <x-icon name="info" />
                                    </button>
                                    <button type="button" class="act-btn" title="Edit Item"
                                            @click="openEdit({{ Js::from($editPayload) }})">
                                        <x-icon name="edit" />
                                    </button>
                                    <form action="{{ route('items.destroy', $item->id) }}" method="POST" class="inline"
                                          @submit.prevent="$dispatch('open-confirm', {
                                              form: $el,
                                              title: '{{ $hasHist ? 'Nonaktifkan Master Item' : 'Hapus Permanen Master Item' }}',
                                              message: '{{ $hasHist ? 'Item ' . addslashes($item->part_name) . ' sudah memiliki riwayat transaksi/BOM sehingga akan dipindahkan ke daftar Item Nonaktif. Lanjutkan?' : 'Item ' . addslashes($item->part_name) . ' belum memiliki riwayat transaksi dan akan dihapus permanen. Lanjutkan?' }}'
                                          })">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="act-btn {{ $hasHist ? 'warn' : 'danger' }}"
                                                title="{{ $hasHist ? 'Nonaktifkan Item' : 'Hapus Permanen' }}">
                                            <x-icon :name="$hasHist ? 'ban' : 'trash'" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><div class="empty">Belum ada data Master Item.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL TAMBAH MASTER ITEM BARU (Dua Panel, Panel Kiri Abu, Bebas Scroll Kanan-Kiri) --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false" @keydown.escape.window="addOpen = false">
            <div class="panel dlg w-full max-w-3xl" role="dialog" aria-label="Tambah Master Item Baru">
                <div class="panel-head">
                    <h3 class="panel-title">Tambah Master Item Baru</h3>
                    <button type="button" class="act-btn" @click="addOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('items.store') }}" method="POST" class="flex flex-col min-h-0 flex-1">
                    @csrf
                    <div class="dlg-split">
                        {{-- Panel Kiri: Pratinjau Hidup --}}
                        <aside class="dlg-side hide-sm">
                            <div class="dlg-k">Pratinjau</div>
                            <div>
                                <div class="dlg-code num" x-text="addPartNo || '–'"></div>
                                <div class="dlg-name uppercase" x-text="addPartName || 'Nama part'"></div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="ty" :class="tyClass(addType)" x-text="addType"></span>
                                <span class="num text-xs font-semibold" style="color: var(--ink2)" x-text="addUom"></span>
                            </div>
                            <div>
                                <div class="dlg-k" style="margin-bottom: 8px">Standar buffer</div>
                                <template x-if="allowsPo(addType)">
                                    <div class="dlg-list">
                                        <div><span>Std Day 1</span><b class="num" x-text="stdPreview(addPo, addWd, addKb)[0]"></b></div>
                                        <div><span>Std Day 2</span><b class="num" x-text="stdPreview(addPo, addWd, addKb)[1]"></b></div>
                                        <div><span>Std Day 3</span><b class="num" x-text="stdPreview(addPo, addWd, addKb)[2]"></b></div>
                                    </div>
                                </template>
                                <template x-if="!allowsPo(addType)">
                                    <p class="hint">Dihitung otomatis dari resep BOM, bukan dari PO.</p>
                                </template>
                            </div>
                        </aside>

                        {{-- Panel Kanan: Field Input --}}
                        <div class="dlg-main">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="lbl" for="add_part_no">Part No <span style="font-weight:400;color:var(--mut)">(opsional untuk RM Sheet/Coil)</span></label>
                                    <input id="add_part_no" type="text" name="part_no" x-model="addPartNo"
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
                                    <label class="lbl" for="add_po">Qty PO Sebulan</label>
                                    <input id="add_po" type="number" step="0.01" min="0" name="qty_po" x-model="addPo" class="num w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl" for="add_wd">Working Days (WD)</label>
                                    <input id="add_wd" type="number" min="0" name="working_days" x-model="addWd" class="num w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl" for="add_kb">Qty per Kanban</label>
                                    <input id="add_kb" type="number" step="0.01" min="0" name="qty_kanban" x-model="addKb" class="num w-full h-9">
                                </div>
                            </div>

                            <div x-show="!allowsPo(addType)" x-cloak class="alert alert-info text-xs">
                                Untuk tipe <b x-text="addType"></b>, parameter PO/WD/Kanban dikunci <b class="num">0</b> karena kebutuhan stok hariannya (<b class="num">Std Day 1/2/3</b>) dihitung otomatis dari resep BOM.
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

        {{-- POP-UP MODAL EDIT MASTER ITEM (Dua Panel, Panel Kiri Abu, Bebas Scroll Kanan-Kiri) --}}
        <div x-show="editOpen" x-cloak class="modal-backdrop" @click.self="editOpen = false" @keydown.escape.window="editOpen = false">
            <div class="panel dlg w-full max-w-3xl" role="dialog" aria-label="Edit Master Item">
                <div class="panel-head">
                    <h3 class="panel-title">Edit Master Item</h3>
                    <button type="button" class="act-btn" @click="editOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form :action="'{{ url('items') }}/' + editData.id" method="POST" class="flex flex-col min-h-0 flex-1">
                    @csrf
                    @method('PUT')
                    <div class="dlg-split">
                        <aside class="dlg-side hide-sm">
                            <div class="dlg-k">Pratinjau</div>
                            <div>
                                <div class="dlg-code num" x-text="editData.part_no || '–'"></div>
                                <div class="dlg-name uppercase" x-text="editData.part_name || 'Nama part'"></div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="ty" :class="tyClass(editData.type)" x-text="editData.type"></span>
                                <span class="num text-xs font-semibold" style="color: var(--ink2)" x-text="editData.uom"></span>
                            </div>
                            <div>
                                <div class="dlg-k" style="margin-bottom: 8px">Standar buffer</div>
                                <template x-if="allowsPo(editData.type)">
                                    <div class="dlg-list">
                                        <div><span>Std Day 1</span><b class="num" x-text="stdPreview(editData.qty_po, editData.working_days, editData.qty_kanban)[0]"></b></div>
                                        <div><span>Std Day 2</span><b class="num" x-text="stdPreview(editData.qty_po, editData.working_days, editData.qty_kanban)[1]"></b></div>
                                        <div><span>Std Day 3</span><b class="num" x-text="stdPreview(editData.qty_po, editData.working_days, editData.qty_kanban)[2]"></b></div>
                                    </div>
                                </template>
                                <template x-if="!allowsPo(editData.type)">
                                    <p class="hint">Dihitung otomatis dari resep BOM, bukan dari PO.</p>
                                </template>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <template x-if="editData.has_history">
                                <div class="alert alert-crit text-xs">
                                    Identitas utama terkunci karena item ini sudah memiliki riwayat transaksi / BOM. Anda tetap dapat memperbarui parameter PO, Working Days, dan Kanban (untuk tipe FG, FG/KOMPONEN, &amp; TRANSIT).
                                </div>
                            </template>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="lbl" for="edit_part_no">Part No</label>
                                    <input id="edit_part_no" type="text" name="part_no" x-model="editData.part_no"
                                           :disabled="editData.has_history"
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
                                            <input type="radio" name="type" value="{{ $t }}" x-model="editData.type" :disabled="editData.has_history">
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
                                    <label class="lbl" for="edit_po">Qty PO Sebulan</label>
                                    <input id="edit_po" type="number" step="0.01" min="0" name="qty_po" x-model="editData.qty_po" class="num w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl" for="edit_wd">Working Days (WD)</label>
                                    <input id="edit_wd" type="number" min="0" name="working_days" x-model="editData.working_days" class="num w-full h-9">
                                </div>
                                <div>
                                    <label class="lbl" for="edit_kb">Qty per Kanban</label>
                                    <input id="edit_kb" type="number" step="0.01" min="0" name="qty_kanban" x-model="editData.qty_kanban" class="num w-full h-9">
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
                addOpen: openInitially,
                editOpen: false,
                visibleCount: initialCount,
                companies: companiesList,
                addPartNo: '',
                addPartName: '',
                addType: 'FG',
                addUom: {{ Js::from(collect($uoms)->first() ?? 'PCS') }},
                addCompanyId: '',
                addPo: 0,
                addWd: 20,
                addKb: 0,
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
                    has_history: false,
                },

                tyClass(t) {
                    return 'ty-' + (t === 'FG/KOMPONEN' ? 'FGK' : t);
                },
                stdPreview(po, wd, kb = 0) {
                    const p = parseFloat(po) || 0;
                    const w = parseInt(wd, 10) || 0;
                    const k = parseFloat(kb) || 0;
                    const poWd = (w > 0 && p > 0) ? Math.round((p / w) * 100) / 100 : 0;
                    let s1 = 0;
                    if (poWd > 0) {
                        s1 = k > 0 ? Math.ceil(poWd / k) * k : Math.ceil(poWd);
                    }
                    return [s1, s1 * 2, s1 * 3].map(v => (Math.round(v * 100) / 100).toLocaleString('id-ID'));
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
                        'FG': ['CUSTOMER'],
                    };
                    const allowed = map[type] || ['SUPPLIER', 'INTERNAL', 'CUSTOMER'];
                    return this.companies.filter(c => allowed.includes(c.type));
                },
                companyGuide(type) {
                    const g = {
                        'RM': 'Pilih perusahaan SUPPLIER pemasok bahan baku (RM) ini.',
                        'KOMPONEN': 'Pilih SUPPLIER jika dibeli dari vendor luar, atau INTERNAL jika diproduksi sendiri.',
                        'FG/KOMPONEN': 'Pilih perusahaan INTERNAL (hasil produksi internal yang dapat dikirim maupun dipakai proses lanjut).',
                        'TRANSIT': 'Pilih perusahaan SUPPLIER asal barang Transit.',
                        'FG': 'Pilih perusahaan CUSTOMER tujuan pengiriman produk akhir (FG) ini.',
                    };
                    return g[type] || '';
                },
                syncAddCompany() {
                    const list = this.allowedCompanies(this.addType);
                    this.addCompanyId = list.length ? String(list[0].id) : '';
                },
                openEdit(payload) {
                    this.editData = Object.assign({}, payload);
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
                        this.visibleCount = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>