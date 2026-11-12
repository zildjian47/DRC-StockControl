<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Master Companies"
                       subtitle="Daftar perusahaan pemasok (Supplier), lini internal, dan pelanggan (Customer)">
            <x-slot name="actions">
                <button type="button" class="btn btn-primary" @click="$dispatch('open-add-company')">
                    <x-icon name="plus" /> Tambah Perusahaan
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="{
            q: '',
            fType: '',
            addOpen: {{ $errors->any() ? 'true' : 'false' }},
            editOpen: false,
            editId: null,
            editCode: '',
            editName: '',
            editType: 'SUPPLIER',
            visibleCount: {{ $companies->count() }},
            openEditModal(id, code, name, type) {
                this.editId = id;
                this.editCode = code;
                this.editName = name;
                this.editType = type;
                this.editOpen = true;
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
                        if (this.matchRow(tr)) c++;
                    });
                    this.visibleCount = c;
                });
            }
         }"
         @open-add-company.window="addOpen = true"
         x-init="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Perusahaan Terdaftar</span>
                <span class="count-chip"><span x-text="visibleCount"></span> Perusahaan</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Kode / Nama Perusahaan..."
                       class="h-8 text-xs px-2.5 w-56">

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2.5 pr-7">
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
                        <th class="w-14 text-center">No</th>
                        <th class="w-36">Kode Perusahaan</th>
                        <th>Nama Perusahaan</th>
                        <th class="w-40 text-center">Tipe Perusahaan</th>
                        <th class="w-24 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($companies as $idx => $comp)
                        <tr data-comp-row
                            data-search="{{ strtolower($comp->company_code . ' ' . $comp->company_name . ' ' . $comp->type_company) }}"
                            data-type="{{ $comp->type_company }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num font-bold text-accent">{{ $comp->company_code }}</td>
                            <td class="font-semibold text-slate-900">{{ $comp->company_name }}</td>
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
                                <div class="inline-flex items-center justify-center gap-1.5">
                                    <button type="button" class="act-icon" title="Edit Perusahaan"
                                            @click="openEditModal({{ $comp->id }}, '{{ addslashes($comp->company_code) }}', '{{ addslashes($comp->company_name) }}', '{{ $comp->type_company }}')">
                                        <x-icon name="edit" class="w-4 h-4" />
                                    </button>
                                    <form action="{{ route('companies.destroy', $comp->id) }}" method="POST"
                                          @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Hapus Perusahaan', message: 'Hapus perusahaan {{ addslashes($comp->company_name) }}?' })">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="act-icon act-icon-danger" title="Hapus Perusahaan">
                                            <x-icon name="trash" class="w-4 h-4" />
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

        {{-- POP-UP MODAL TAMBAH PERUSAHAAN --}}
        <div x-show="addOpen" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 !mt-0"
             @click.self="addOpen = false">
            <div class="panel w-full max-w-lg overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Tambah Perusahaan Baru</h3>
                    <button type="button" class="btn btn-sm" @click="addOpen = false">Tutup</button>
                </div>
                <form action="{{ route('companies.store') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Kode Perusahaan (Otomatis Kapital)</label>
                                <input type="text" name="company_code" required placeholder="Contoh: DPM"
                                       oninput="this.value = this.value.toUpperCase()" class="num uppercase w-full h-10">
                            </div>
                            <div>
                                <label class="lbl">Tipe Perusahaan</label>
                                <select name="type_company" required class="w-full h-10">
                                    <option value="SUPPLIER">SUPPLIER</option>
                                    <option value="INTERNAL">INTERNAL</option>
                                    <option value="CUSTOMER">CUSTOMER</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="lbl">Nama Lengkap Perusahaan</label>
                            <input type="text" name="company_name" required placeholder="Contoh: PT. DHARMA POLIMETAL Tbk." class="w-full h-10">
                        </div>
                    </div>
                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perusahaan</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- POP-UP MODAL EDIT PERUSAHAAN --}}
        <div x-show="editOpen" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 !mt-0"
             @click.self="editOpen = false">
            <div class="panel w-full max-w-lg overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Edit Data Perusahaan</h3>
                    <button type="button" class="btn btn-sm" @click="editOpen = false">Tutup</button>
                </div>
                <form :action="'{{ url('companies') }}/' + editId" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="panel-body space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Kode Perusahaan</label>
                                <input type="text" name="company_code" x-model="editCode" required
                                       oninput="this.value = this.value.toUpperCase()" class="num uppercase w-full h-10">
                            </div>
                            <div>
                                <label class="lbl">Tipe Perusahaan</label>
                                <select name="type_company" x-model="editType" required class="w-full h-10">
                                    <option value="SUPPLIER">SUPPLIER</option>
                                    <option value="INTERNAL">INTERNAL</option>
                                    <option value="CUSTOMER">CUSTOMER</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="lbl">Nama Lengkap Perusahaan</label>
                            <input type="text" name="company_name" x-model="editName" required class="w-full h-10">
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
</x-app-layout>