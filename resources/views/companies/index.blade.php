<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Master Companies"
                       subtitle="Daftar perusahaan pemasok (Supplier), lini internal, dan pelanggan (Customer)">
            <x-slot name="actions">
                <button type="button" class="btn btn-primary" @click="$dispatch('open-add-company')">
                    <x-icon name="plus" />
                    <span>Input Perusahaan</span>
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $companiesJs = $companies->map(fn ($c) => [
            'id'   => $c->id,
            'code' => strtoupper(trim((string) $c->company_code)),
            'type' => strtoupper(trim((string) $c->type_company)),
        ])->values();
    @endphp

    <div class="panel"
         x-data="companyPage({{ $companies->count() }}, {{ Js::from($companiesJs) }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-company.window="addOpen = true; addError = ''"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Perusahaan Terdaftar</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>PERUSAHAAN</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Kode / Nama Perusahaan..."
                       class="h-8 text-xs px-2.5 w-48">

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe</option>
                    <option value="SUPPLIER">SUPPLIER</option>
                    <option value="INTERNAL">INTERNAL</option>
                    <option value="CUSTOMER">CUSTOMER</option>
                </select>

                <button type="button" x-show="q || fType" x-cloak
                        @click="q=''; fType=''; recalc()" class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[6%] text-center">No</th>
                        <th class="w-[18%] text-left">Kode Perusahaan</th>
                        <th class="w-[46%] text-left">Nama Perusahaan</th>
                        <th class="w-[18%] text-center">Tipe Perusahaan</th>
                        <th class="w-[12%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($companies as $idx => $comp)
                        <tr data-comp-row
                            data-search="{{ strtolower($comp->company_code . ' ' . $comp->company_name . ' ' . $comp->type_company) }}"
                            data-type="{{ $comp->type_company }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500" data-col-no>{{ $idx + 1 }}</td>
                            <td class="num text-left font-bold text-accent">{{ $comp->company_code }}</td>
                            <td class="text-left font-semibold text-slate-900">{{ $comp->company_name }}</td>
                            <td class="text-center">
                                @if($comp->type_company === 'SUPPLIER')
                                    <span class="pill pill-info">SUPPLIER</span>
                                @elseif($comp->type_company === 'INTERNAL')
                                    <span class="pill pill-warn">INTERNAL</span>
                                @else
                                    <span class="pill pill-good">CUSTOMER</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="inline-flex items-center justify-center gap-1">
                                    <button type="button" class="act-btn" title="Edit Perusahaan"
                                            @click="openEditModal({{ $comp->id }}, {{ Js::from($comp->company_code) }}, {{ Js::from($comp->company_name) }}, {{ Js::from($comp->type_company) }})">
                                        <x-icon name="edit" />
                                    </button>
                                    <form action="{{ route('companies.destroy', $comp->id) }}" method="POST" class="inline"
                                          @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Hapus Perusahaan', message: {{ Js::from('Hapus perusahaan ' . $comp->company_name . '?') }} })">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="act-btn danger" title="Hapus Perusahaan">
                                            <x-icon name="trash" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">Belum ada data perusahaan.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT PERUSAHAAN (dua panel, panel kiri abu) --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false" @keydown.escape.window="addOpen = false">
            <div class="panel dlg w-full max-w-2xl" role="dialog" aria-label="Input Perusahaan Baru">
                <div class="panel-head">
                    <h3 class="panel-title">Input Perusahaan Baru</h3>
                    <button type="button" class="act-btn" @click="addOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('companies.store') }}" method="POST" novalidate
                      @submit.prevent="submitAdd($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    <div class="dlg-split">
                        <aside class="dlg-side">
                            <div class="dlg-k">Pratinjau</div>
                            <div class="mono-tile num" x-text="(addCode || '?').slice(0, 3)"></div>
                            <div>
                                <div class="dlg-code num" x-text="addCode || '–'"></div>
                                <div class="dlg-name" x-text="addName || 'Nama perusahaan'"></div>
                            </div>
                            <div>
                                <span class="pill" :class="typePill(addType)" x-text="addType"></span>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <div x-show="addError" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="addError"></span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="lbl" for="add_co_code">Kode Perusahaan (Otomatis Kapital)</label>
                                    <input id="add_co_code" type="text" name="company_code" x-model="addCode" required
                                           placeholder="Contoh: DPM"
                                           oninput="this.value = this.value.toUpperCase()"
                                           class="num uppercase w-full h-9">
                                </div>
                                <div>
                                    <span class="lbl">Tipe Perusahaan</span>
                                    <div class="opt-chips">
                                        @foreach(['SUPPLIER', 'INTERNAL', 'CUSTOMER'] as $t)
                                            <label>
                                                <input type="radio" name="type_company" value="{{ $t }}" x-model="addType" required>
                                                <span>{{ $t }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="lbl" for="add_co_name">Nama Lengkap Perusahaan</label>
                                <input id="add_co_name" type="text" name="company_name" x-model="addName" required
                                       placeholder="Contoh: PT. DHARMA POLIMETAL Tbk."
                                       class="w-full h-9">
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perusahaan</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- POP-UP MODAL EDIT PERUSAHAAN (dua panel, panel kiri abu) --}}
        <div x-show="editOpen" x-cloak class="modal-backdrop" @click.self="editOpen = false" @keydown.escape.window="editOpen = false">
            <div class="panel dlg w-full max-w-2xl" role="dialog" aria-label="Edit Data Perusahaan">
                <div class="panel-head">
                    <h3 class="panel-title">Edit Data Perusahaan</h3>
                    <button type="button" class="act-btn" @click="editOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form :action="'{{ url('companies') }}/' + editId" method="POST" novalidate
                      @submit.prevent="submitEdit($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    @method('PUT')
                    <div class="dlg-split">
                        <aside class="dlg-side">
                            <div class="dlg-k">Pratinjau</div>
                            <div class="mono-tile num" x-text="(editCode || '?').slice(0, 3)"></div>
                            <div>
                                <div class="dlg-code num" x-text="editCode || '–'"></div>
                                <div class="dlg-name" x-text="editName || 'Nama perusahaan'"></div>
                            </div>
                            <div>
                                <span class="pill" :class="typePill(editType)" x-text="editType"></span>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <div x-show="editError" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="editError"></span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="lbl" for="edit_co_code">Kode Perusahaan</label>
                                    <input id="edit_co_code" type="text" name="company_code" x-model="editCode" required
                                           oninput="this.value = this.value.toUpperCase()"
                                           class="num uppercase w-full h-9">
                                </div>
                                <div>
                                    <span class="lbl">Tipe Perusahaan</span>
                                    <div class="opt-chips">
                                        @foreach(['SUPPLIER', 'INTERNAL', 'CUSTOMER'] as $t)
                                            <label>
                                                <input type="radio" name="type_company" value="{{ $t }}" x-model="editType" required>
                                                <span>{{ $t }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="lbl" for="edit_co_name">Nama Lengkap Perusahaan</label>
                                <input id="edit_co_name" type="text" name="company_name" x-model="editName" required class="w-full h-9">
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
        function companyPage(initialCount, existingCompanies, openInitially) {
            return {
                q: '',
                fType: '',
                existingCompanies: existingCompanies || [],
                addOpen: openInitially,
                addCode: '',
                addName: '',
                addType: 'SUPPLIER',
                addError: '',
                editOpen: false,
                editId: null,
                editCode: '',
                editName: '',
                editType: 'SUPPLIER',
                editError: '',
                visibleCount: initialCount,
                typePill(t) {
                    return t === 'CUSTOMER' ? 'pill-good' : (t === 'INTERNAL' ? 'pill-warn' : 'pill-info');
                },
                openEditModal(id, code, name, type) {
                    this.editId = id;
                    this.editCode = code;
                    this.editName = name;
                    this.editType = type;
                    this.editError = '';
                    this.editOpen = true;
                },
                submitAdd(formEl) {
                    this.addError = '';
                    const code = (this.addCode || '').trim().toUpperCase();
                    const name = (this.addName || '').trim();
                    const type = (this.addType || '').trim().toUpperCase();

                    if (!code) {
                        this.addError = 'Kode Perusahaan wajib diisi.';
                        return;
                    }
                    if (!['SUPPLIER', 'INTERNAL', 'CUSTOMER'].includes(type)) {
                        this.addError = 'Tipe Perusahaan wajib dipilih.';
                        return;
                    }
                    if (!name) {
                        this.addError = 'Nama Lengkap Perusahaan wajib diisi.';
                        return;
                    }
                    const isDup = this.existingCompanies.some(c => c.code === code && c.type === type);
                    if (isDup) {
                        this.addError = 'Kode perusahaan ' + code + ' dengan tipe ' + type + ' sudah terdaftar.';
                        return;
                    }
                    formEl.submit();
                },
                submitEdit(formEl) {
                    this.editError = '';
                    const code = (this.editCode || '').trim().toUpperCase();
                    const name = (this.editName || '').trim();
                    const type = (this.editType || '').trim().toUpperCase();

                    if (!code) {
                        this.editError = 'Kode Perusahaan wajib diisi.';
                        return;
                    }
                    if (!['SUPPLIER', 'INTERNAL', 'CUSTOMER'].includes(type)) {
                        this.editError = 'Tipe Perusahaan wajib dipilih.';
                        return;
                    }
                    if (!name) {
                        this.editError = 'Nama Lengkap Perusahaan wajib diisi.';
                        return;
                    }
                    const isDup = this.existingCompanies.some(
                        c => String(c.id) !== String(this.editId) && c.code === code && c.type === type
                    );
                    if (isDup) {
                        this.editError = 'Kode perusahaan ' + code + ' dengan tipe ' + type + ' sudah terdaftar.';
                        return;
                    }
                    formEl.submit();
                },
                matchRow(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const t = el.dataset.type || '';
                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.fType && t !== this.fType) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-comp-row]').forEach(tr => {
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