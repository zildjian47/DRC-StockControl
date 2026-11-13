<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Manufacture Process"
                       subtitle="Daftar nama proses produksi internal pabrik (otomatis huruf kapital)">
            <x-slot name="actions">
                <button type="button" class="btn btn-primary" @click="$dispatch('open-add-process')">
                    <x-icon name="plus" /> Tambah Proses
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="processPage({{ $processes->count() }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-process.window="addOpen = true"
         x-init="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Proses Manufaktur</span>
                <span class="count-chip"><span x-text="visibleCount"></span> Proses</span>
            </div>

            <div class="flex items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Nama Proses..."
                       class="h-8 text-xs px-2.5 w-48">
                <button type="button" x-show="q" x-cloak @click="q=''; recalc()" class="btn btn-sm">Reset</button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-14 text-center">No</th>
                        <th>Nama Proses Manufaktur</th>
                        <th class="w-24 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($processes as $idx => $proc)
                        <tr data-proc-row
                            data-search="{{ strtolower($proc->process_name) }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="font-semibold text-slate-900">{{ $proc->process_name }}</td>
                            <td class="text-center">
                                <div class="inline-flex items-center justify-center gap-1">
                                    <button type="button" class="act-btn" title="Edit Proses"
                                            @click="openEditModal({{ $proc->id }}, '{{ addslashes($proc->process_name) }}')">
                                        <x-icon name="edit" />
                                    </button>
                                    <form action="{{ route('processes.destroy', $proc->id) }}" method="POST" class="inline"
                                          @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Hapus Proses', message: 'Hapus proses {{ addslashes($proc->process_name) }}?' })">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="act-btn danger" title="Hapus Proses">
                                            <x-icon name="trash" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty">Belum ada proses manufaktur yang terdaftar.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL TAMBAH PROSES --}}
        <div x-show="addOpen" x-cloak
             class="modal-backdrop"
             @click.self="addOpen = false">
            <div class="panel w-full max-w-md overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Tambah Proses Manufaktur</h3>
                    <button type="button" class="act-btn" @click="addOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>
                <form action="{{ route('processes.store') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div>
                            <label class="lbl">Nama Proses (Otomatis Huruf Kapital)</label>
                            <input type="text" name="process_name" required placeholder="Contoh: STAMPING"
                                   oninput="this.value = this.value.toUpperCase()" class="uppercase w-full h-9">
                        </div>
                    </div>
                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Proses</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- POP-UP MODAL EDIT PROSES --}}
        <div x-show="editOpen" x-cloak
             class="modal-backdrop"
             @click.self="editOpen = false">
            <div class="panel w-full max-w-md overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Edit Proses Manufaktur</h3>
                    <button type="button" class="act-btn" @click="editOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>
                <form :action="'{{ url('processes') }}/' + editId" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="panel-body space-y-4">
                        <div>
                            <label class="lbl">Nama Proses (Otomatis Huruf Kapital)</label>
                            <input type="text" name="process_name" x-model="editName" required
                                   oninput="this.value = this.value.toUpperCase()" class="uppercase w-full h-9">
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
        function processPage(initialCount, openInitially) {
            return {
                q: '',
                addOpen: openInitially,
                editOpen: false,
                editId: null,
                editName: '',
                visibleCount: initialCount,
                openEditModal(id, name) {
                    this.editId = id;
                    this.editName = name;
                    this.editOpen = true;
                },
                matchRow(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    return !this.q || s.includes(this.q.toLowerCase());
                },
                recalc() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-proc-row]').forEach(tr => {
                            if (this.matchRow(tr)) c++;
                        });
                        this.visibleCount = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>