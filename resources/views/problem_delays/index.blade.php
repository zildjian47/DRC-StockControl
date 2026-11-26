<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Delay Delivery"
                       subtitle="Pencatatan kendala pengiriman beserta Tanggal Kejadian, Perusahaan/Customer terdampak, dan Problem Reason">
            <x-slot name="actions">
                <span class="btn-group">
                    <button type="button" class="btn" @click="$dispatch('export-delays', 'xlsx')"
                            title="Export data Delay Delivery sesuai filter aktif ke Excel (.xlsx)">
                        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        <span>Excel</span>
                    </button>
                    <button type="button" class="btn" @click="$dispatch('export-delays', 'pdf')"
                            title="Export laporan Delay Delivery sesuai filter aktif ke PDF">
                        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                        <span>PDF</span>
                    </button>
                    <button type="button" class="btn btn-primary" @click="$dispatch('open-add-delay')">
                        <x-icon name="plus" />
                        <span>Input Delay</span>
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

        $itemsJs = $items->map(fn ($i) => [
            'id'         => $i->id,
            'company_id' => $i->company_id,
            'part_no'    => $i->part_no ?: '-',
            'part_name'  => $i->part_name,
            'type'       => $i->type,
            'label'      => ($i->part_no ? $i->part_no . ' — ' : '') . $i->part_name . ' [' . $i->type . ']',
        ])->values();
    @endphp

    <div class="panel"
         x-data="delayPage({{ $delays->count() }}, {{ Js::from($companiesJs) }}, {{ Js::from($itemsJs) }}, '{{ now()->toDateString() }}', {{ Js::from($reasons[0] ?? 'MATERIAL') }}, {{ $errors->any() ? 'true' : 'false' }}, {{ Js::from(route('exports.problem_delays')) }})"
         @open-add-delay.window="addOpen = true; addError = ''"
         @export-delays.window="exportData($event.detail)"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Riwayat Delay Delivery</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>DATA</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Perusahaan / Part / Petugas..."
                       class="h-8 text-xs px-2.5 w-48">

                <div class="inline-flex items-center gap-1.5">
                    <span class="text-[11px] font-semibold text-slate-500">Tgl Kejadian</span>
                    <input type="date" x-model="startDate"
                           @change="if(endDate && endDate < startDate) endDate = startDate; recalc()"
                           class="h-8 text-xs px-2 w-32">
                    <span class="text-slate-400">–</span>
                    <input type="date" x-model="endDate" :min="startDate" @change="recalc()"
                           class="h-8 text-xs px-2 w-32">
                </div>

                <div class="inline-flex items-center gap-1.5">
                    <span class="text-[11px] font-semibold text-slate-500">Jam Catat</span>
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
                        <th class="w-[4%] text-center">No</th>
                        <th class="w-[11%] col-time">Tanggal Kejadian</th>
                        <th class="w-[12%] col-time">Waktu Pencatatan</th>
                        <th class="w-[12%]">Petugas</th>
                        <th class="w-[14%]">Perusahaan</th>
                        <th class="w-[20%]">Part No &amp; Part Name</th>
                        <th class="w-[8%] text-center">Tipe</th>
                        <th class="w-[11%] text-center">Problem Reason</th>
                        <th class="w-[8%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($delays as $idx => $d)
                        @php
                            $incDt    = $d->incident_date ? \Carbon\Carbon::parse($d->incident_date) : \Carbon\Carbon::parse($d->date_time);
                            $recDt    = \Carbon\Carbon::parse($d->date_time);
                            $uName    = $d->user->name ?? 'PPIC';
                            $compCode = $d->company->company_code ?? '-';
                            $compName = $d->company->company_name ?? '-';
                            $compType = $d->company->type_company ?? 'CUSTOMER';
                            $partNo   = $d->item->part_no ?: '-';
                            $partName = $d->item->part_name ?? '-';
                            $itemType = $d->item->type ?? '-';
                            $tyCls    = 'ty-' . ($itemType === 'FG/KOMPONEN' ? 'FGK' : $itemType);

                            $editPayload = [
                                'id'             => $d->id,
                                'incident_date'  => $incDt->format('Y-m-d'),
                                'company_id'     => $d->company_id,
                                'item_id'        => $d->item_id,
                                'problem_reason' => $d->problem_reason,
                            ];
                        @endphp
                        <tr data-row
                            data-search="{{ strtolower($compCode . ' ' . $compName . ' ' . $partNo . ' ' . $partName . ' ' . $uName . ' ' . $d->problem_reason) }}"
                            data-date="{{ $incDt->format('Y-m-d') }}"
                            data-time="{{ $recDt->format('H:i') }}"
                            data-reason="{{ $d->problem_reason }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500" data-col-no>{{ $idx + 1 }}</td>
                            <td class="num col-time text-left whitespace-nowrap">
                                <span class="font-bold text-slate-900">{{ $incDt->format('d M Y') }}</span>
                            </td>
                            <td class="num col-time text-left whitespace-nowrap">
                                <div class="font-medium text-slate-700">{{ $recDt->format('d M Y') }}</div>
                                <div class="text-xs text-slate-500">{{ $recDt->format('H:i') }} WIB</div>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900">{{ $uName }}</div>
                                <div class="text-[11px] text-slate-500">{{ $d->user->role ?? 'PPIC' }}</div>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-xs text-accent">{{ $compCode }}</span>
                                    @if($compType === 'CUSTOMER')
                                        <span class="pill pill-good">CUSTOMER</span>
                                    @elseif($compType === 'INTERNAL')
                                        <span class="pill pill-warn">INTERNAL</span>
                                    @else
                                        <span class="pill pill-info">SUPPLIER</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-600 truncate mt-0.5">{{ $compName }}</div>
                            </td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $partNo }}</div>
                                <div class="font-semibold text-slate-900">{{ $partName }}</div>
                            </td>
                            <td class="text-center">
                                <span class="ty {{ $tyCls }}">{{ $itemType }}</span>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-neu">{{ $d->problem_reason }}</span>
                            </td>
                            <td class="text-center">
                                <div class="inline-flex items-center justify-center gap-1">
                                    <button type="button" class="act-btn" title="Edit Log Delay"
                                            @click="openEdit({{ Js::from($editPayload) }})">
                                        <x-icon name="edit" />
                                    </button>
                                    <form action="{{ route('problem_delays.destroy', $d->id) }}" method="POST" class="inline"
                                          @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Hapus Log Delay Delivery', message: {{ Js::from('Hapus log delay pengiriman untuk ' . $partName . ' (' . $incDt->format('d M Y') . ')?') }} })">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="act-btn danger" title="Hapus Log Delay">
                                            <x-icon name="trash" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty">Belum ada data log Delay Delivery.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT DELAY DELIVERY (dua panel, panel kiri abu) --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false" @keydown.escape.window="addOpen = false">
            <div class="panel dlg w-full max-w-3xl" role="dialog" aria-label="Input Delay Delivery">
                <div class="panel-head">
                    <h3 class="panel-title">Input Log Delay Delivery</h3>
                    <button type="button" class="act-btn" @click="addOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('problem_delays.store') }}" method="POST" novalidate
                      @submit.prevent="submitAdd($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    <div class="dlg-split">
                        <aside class="dlg-side hide-sm">
                            <div class="dlg-k">Pratinjau Log Delay</div>
                            <div class="sumc">
                                <div class="text-xs font-semibold text-slate-500 num" x-text="'Tgl Kejadian: ' + (incidentDate || '-')"></div>
                                <div class="dlg-code num mt-1" x-text="selectedCompany ? selectedCompany.code : '–'"></div>
                                <div class="dlg-name" x-text="selectedCompany ? selectedCompany.name : 'Pilih Perusahaan'"></div>
                                <div class="mt-1" x-show="selectedCompany">
                                    <span class="pill"
                                          :class="selectedCompany?.type === 'CUSTOMER' ? 'pill-good' : (selectedCompany?.type === 'INTERNAL' ? 'pill-warn' : 'pill-info')"
                                          x-text="selectedCompany?.type"></span>
                                </div>
                            </div>

                            <div class="dlg-list">
                                <div><span>Part</span><b class="num truncate" x-text="selectedItem ? selectedItem.part_name : '-'"></b></div>
                                <div><span>Problem</span><b class="text-rose-700" x-text="selectedReason"></b></div>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <div x-show="addError" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="addError"></span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="lbl" for="delay_inc_date">Tanggal Kejadian</label>
                                    <input id="delay_inc_date" type="date" name="incident_date" x-model="incidentDate" required class="w-full h-9 text-xs num">
                                </div>
                                <div>
                                    <label class="lbl" for="delay_comp">Pilih Perusahaan / Customer</label>
                                    <select id="delay_comp" name="company_id" x-model="selectedCompanyId" @change="selectedItemId = ''" required class="w-full h-9 text-xs">
                                        <option value="">-- Pilih Perusahaan --</option>
                                        @foreach($companies as $c)
                                            <option value="{{ $c->id }}">{{ $c->company_code }} — {{ $c->company_name }} ({{ $c->type_company }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="lbl" for="delay_item">Pilih Item Terdampak</label>
                                <select id="delay_item" name="item_id" x-model="selectedItemId" required class="w-full h-9 text-xs">
                                    <option value="">-- Pilih Item --</option>
                                    <template x-for="it in availableItems" :key="it.id">
                                        <option :value="String(it.id)" x-text="it.label"></option>
                                    </template>
                                </select>
                            </div>

                            <div>
                                <span class="lbl">Problem Reason (Kategori Kendala)</span>
                                <div class="opt-chips">
                                    @foreach($reasons as $r)
                                        <label>
                                            <input type="radio" name="problem_reason" value="{{ $r }}" x-model="selectedReason" required>
                                            <span>{{ $r }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Log Delay</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- POP-UP MODAL EDIT DELAY DELIVERY (dua panel, panel kiri abu) --}}
        <div x-show="editOpen" x-cloak class="modal-backdrop" @click.self="editOpen = false" @keydown.escape.window="editOpen = false">
            <div class="panel dlg w-full max-w-3xl" role="dialog" aria-label="Edit Log Delay Delivery">
                <div class="panel-head">
                    <h3 class="panel-title">Edit Log Delay Delivery</h3>
                    <button type="button" class="act-btn" @click="editOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form :action="'{{ url('problem-delays') }}/' + editId" method="POST" novalidate
                      @submit.prevent="submitEdit($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    @method('PUT')
                    <div class="dlg-split">
                        <aside class="dlg-side hide-sm">
                            <div class="dlg-k">Pratinjau Perubahan</div>
                            <div class="sumc">
                                <div class="text-xs font-semibold text-slate-500 num" x-text="'Tgl Kejadian: ' + (editIncidentDate || '-')"></div>
                                <div class="dlg-code num mt-1" x-text="editSelectedCompany ? editSelectedCompany.code : '–'"></div>
                                <div class="dlg-name" x-text="editSelectedCompany ? editSelectedCompany.name : 'Pilih Perusahaan'"></div>
                                <div class="mt-1" x-show="editSelectedCompany">
                                    <span class="pill"
                                          :class="editSelectedCompany?.type === 'CUSTOMER' ? 'pill-good' : (editSelectedCompany?.type === 'INTERNAL' ? 'pill-warn' : 'pill-info')"
                                          x-text="editSelectedCompany?.type"></span>
                                </div>
                            </div>

                            <div class="dlg-list">
                                <div><span>Part</span><b class="num truncate" x-text="editSelectedItem ? editSelectedItem.part_name : '-'"></b></div>
                                <div><span>Problem</span><b class="text-rose-700" x-text="editReason"></b></div>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <div x-show="editError" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="editError"></span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="lbl" for="edit_delay_inc_date">Tanggal Kejadian</label>
                                    <input id="edit_delay_inc_date" type="date" name="incident_date" x-model="editIncidentDate" required class="w-full h-9 text-xs num">
                                </div>
                                <div>
                                    <label class="lbl" for="edit_delay_comp">Pilih Perusahaan / Customer</label>
                                    <select id="edit_delay_comp" name="company_id" x-model="editCompanyId" @change="editItemId = ''" required class="w-full h-9 text-xs">
                                        <option value="">-- Pilih Perusahaan --</option>
                                        @foreach($companies as $c)
                                            <option value="{{ $c->id }}">{{ $c->company_code }} — {{ $c->company_name }} ({{ $c->type_company }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="lbl" for="edit_delay_item">Pilih Item Terdampak</label>
                                <select id="edit_delay_item" name="item_id" x-model="editItemId" required class="w-full h-9 text-xs">
                                    <option value="">-- Pilih Item --</option>
                                    <template x-for="it in editAvailableItems" :key="'ed-' + it.id">
                                        <option :value="String(it.id)" :selected="String(it.id) === String(editItemId)" x-text="it.label"></option>
                                    </template>
                                </select>
                            </div>

                            <div>
                                <span class="lbl">Problem Reason (Kategori Kendala)</span>
                                <div class="opt-chips">
                                    @foreach($reasons as $r)
                                        <label>
                                            <input type="radio" name="problem_reason" value="{{ $r }}" x-model="editReason" required>
                                            <span>{{ $r }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="editOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function delayPage(initialCount, companiesList, itemsList, todayStr, defaultReason, openInitially, exportBase) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fReason: '',
                exportBase: exportBase,
                visibleCount: initialCount,
                companiesList: companiesList,
                itemsList: itemsList,
                addOpen: openInitially,
                addError: '',
                incidentDate: todayStr,
                selectedCompanyId: '',
                selectedItemId: '',
                selectedReason: defaultReason || 'MATERIAL',

                editOpen: false,
                editError: '',
                editId: null,
                editIncidentDate: todayStr,
                editCompanyId: '',
                editItemId: '',
                editReason: defaultReason || 'MATERIAL',

                exportData(format) {
                    const params = new URLSearchParams();
                    params.set('format', format || 'xlsx');
                    if (this.q.trim()) params.set('q', this.q.trim());
                    if (this.startDate) params.set('start_date', this.startDate);
                    if (this.endDate) params.set('end_date', this.endDate);
                    if (this.startTime) params.set('start_time', this.startTime);
                    if (this.endTime) params.set('end_time', this.endTime);
                    if (this.fReason) params.set('problem_reason', this.fReason);
                    window.location.href = `${this.exportBase}?${params.toString()}`;
                },
                get selectedCompany() {
                    return this.companiesList.find(c => String(c.id) === String(this.selectedCompanyId)) || null;
                },
                get selectedItem() {
                    return this.itemsList.find(i => String(i.id) === String(this.selectedItemId)) || null;
                },
                get availableItems() {
                    const comp = this.selectedCompany;
                    if (!comp) return [];
                    if (comp.type === 'CUSTOMER') {
                        return this.itemsList.filter(i => String(i.company_id) === String(comp.id) || ['FG/KOMPONEN', 'TRANSIT'].includes(i.type));
                    }
                    return this.itemsList.filter(i => String(i.company_id) === String(comp.id));
                },
                get editSelectedCompany() {
                    return this.companiesList.find(c => String(c.id) === String(this.editCompanyId)) || null;
                },
                get editSelectedItem() {
                    return this.itemsList.find(i => String(i.id) === String(this.editItemId)) || null;
                },
                get editAvailableItems() {
                    const comp = this.editSelectedCompany;
                    if (!comp) return [];
                    if (comp.type === 'CUSTOMER') {
                        return this.itemsList.filter(i => String(i.company_id) === String(comp.id) || ['FG/KOMPONEN', 'TRANSIT'].includes(i.type));
                    }
                    return this.itemsList.filter(i => String(i.company_id) === String(comp.id));
                },
                openEdit(payload) {
                    this.editId = payload.id;
                    this.editIncidentDate = payload.incident_date || todayStr;
                    this.editCompanyId = String(payload.company_id || '');
                    this.editReason = payload.problem_reason || 'MATERIAL';
                    this.editError = '';
                    this.editOpen = true;
                    this.$nextTick(() => {
                        this.editItemId = String(payload.item_id || '');
                    });
                },
                submitAdd(formEl) {
                    this.addError = '';
                    if (!this.incidentDate) {
                        this.addError = 'Tanggal Kejadian wajib diisi.';
                        return;
                    }
                    if (!this.selectedCompanyId || !this.selectedCompany) {
                        this.addError = 'Perusahaan / Customer wajib dipilih.';
                        return;
                    }
                    if (!this.selectedItemId || !this.availableItems.some(i => String(i.id) === String(this.selectedItemId))) {
                        this.addError = 'Item Terdampak wajib dipilih sesuai perusahaan yang dipilih.';
                        return;
                    }
                    if (!this.selectedReason) {
                        this.addError = 'Problem Reason wajib dipilih.';
                        return;
                    }
                    formEl.submit();
                },
                submitEdit(formEl) {
                    this.editError = '';
                    if (!this.editIncidentDate) {
                        this.editError = 'Tanggal Kejadian wajib diisi.';
                        return;
                    }
                    if (!this.editCompanyId || !this.editSelectedCompany) {
                        this.editError = 'Perusahaan / Customer wajib dipilih.';
                        return;
                    }
                    if (!this.editItemId || !this.editAvailableItems.some(i => String(i.id) === String(this.editItemId))) {
                        this.editError = 'Item Terdampak wajib dipilih sesuai perusahaan yang dipilih.';
                        return;
                    }
                    if (!this.editReason) {
                        this.editError = 'Problem Reason wajib dipilih.';
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