<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Manufacture Process"
                       subtitle="Daftar master tahapan proses produksi (Stamping, Shearing, Spot Welding, Assy Welding, dll.)">
            <x-slot name="actions">
                <button type="button" class="btn btn-primary" @click="$dispatch('open-add-process')">
                    <x-icon name="plus" />
                    <span>Input Proses</span>
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $processesJs = $processes->map(fn ($p) => [
            'id'   => $p->id,
            'name' => strtoupper(trim((string) $p->process_name)),
        ])->values();

        $reopenAdd  = $errors->any() && old('_modal') === 'add';
        $oldAddName = $reopenAdd ? strtoupper((string) old('process_name', '')) : '';
    @endphp

    <div class="panel"
         x-data="processPage({{ $processes->count() }}, {{ Js::from($processesJs) }}, {{ $reopenAdd ? 'true' : 'false' }}, {{ Js::from($oldAddName) }})"
         @open-add-process.window="addOpen = true; addError = ''"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Proses Manufaktur</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>PROSES</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Nama Proses..."
                       class="h-8 text-xs px-2.5 w-48">

                <button type="button" x-show="q" x-cloak
                        @click="q=''; recalc()" class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[8%] text-center">No</th>
                        <th class="w-[76%] text-left">Nama Proses Manufaktur</th>
                        <th class="w-[16%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($processes as $idx => $proc)
                        <tr data-proc-row
                            data-search="{{ strtolower($proc->process_name) }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500" data-col-no>{{ $idx + 1 }}</td>
                            <td class="text-left font-bold text-slate-900">{{ $proc->process_name }}</td>
                            <td class="text-center">
                                <div class="inline-flex items-center justify-center gap-1">
                                    <button type="button" class="act-btn" title="Edit Proses"
                                            @click="openEditModal({{ $proc->id }}, {{ Js::from($proc->process_name) }})">
                                        <x-icon name="edit" />
                                    </button>
                                    <form action="{{ route('processes.destroy', $proc->id) }}" method="POST" class="inline"
                                          @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Hapus Proses Manufaktur', message: {{ Js::from('Hapus proses ' . $proc->process_name . '?') }} })">
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
                        <tr><td colspan="3"><div class="empty">Belum ada data proses manufaktur.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT PROSES (dua panel, panel kiri abu) --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false" @keydown.escape.window="addOpen = false">
            <div class="panel dlg w-full max-w-2xl" role="dialog" aria-label="Input Proses Manufaktur">
                <div class="panel-head">
                    <h3 class="panel-title">Input Proses Manufaktur</h3>
                    <button type="button" class="act-btn" @click="addOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('processes.store') }}" method="POST" novalidate
                      @submit.prevent="submitAdd($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    <input type="hidden" name="_modal" value="add">
                    <div class="dlg-split">
                        <aside class="dlg-side">
                            <div class="dlg-k">Proses terdaftar ({{ $processes->count() }})</div>
                            <div class="plist">
                                @foreach($processes as $p)
                                    <span>{{ $p->process_name }}</span>
                                @endforeach
                                <span class="new" x-show="addName" x-cloak x-text="addName"></span>
                            </div>
                            <p class="hint" style="margin: 0">Nama proses harus berbeda dari daftar di atas.</p>
                        </aside>

                        <div class="dlg-main">
                            <div x-show="addError" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="addError"></span>
                            </div>

                            <div>
                                <label class="lbl" for="add_proc_name">Nama Proses (Otomatis Huruf Kapital)</label>
                                <input id="add_proc_name" type="text" name="process_name" x-model="addName" required
                                       placeholder="Contoh: STAMPING"
                                       oninput="this.value = this.value.toUpperCase()"
                                       class="uppercase w-full h-9 font-semibold">
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Proses</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- POP-UP MODAL EDIT PROSES (dua panel, panel kiri abu) --}}
        <div x-show="editOpen" x-cloak class="modal-backdrop" @click.self="editOpen = false" @keydown.escape.window="editOpen = false">
            <div class="panel dlg w-full max-w-2xl" role="dialog" aria-label="Edit Proses Manufaktur">
                <div class="panel-head">
                    <h3 class="panel-title">Edit Proses Manufaktur</h3>
                    <button type="button" class="act-btn" @click="editOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form :action="'{{ url('processes') }}/' + editId" method="POST" novalidate
                      @submit.prevent="submitEdit($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    @method('PUT')
                    <div class="dlg-split">
                        <aside class="dlg-side">
                            <div class="dlg-k">Proses terdaftar ({{ $processes->count() }})</div>
                            <div class="plist">
                                @foreach($processes as $p)
                                    <span x-show="editId !== {{ $p->id }}">{{ $p->process_name }}</span>
                                @endforeach
                                <span class="new" x-show="editName" x-cloak x-text="editName"></span>
                            </div>
                            <p class="hint" style="margin: 0">Nama proses harus berbeda dari daftar di atas.</p>
                        </aside>

                        <div class="dlg-main">
                            <div x-show="editError" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="editError"></span>
                            </div>

                            <div>
                                <label class="lbl" for="edit_proc_name">Nama Proses</label>
                                <input id="edit_proc_name" type="text" name="process_name" x-model="editName" required
                                       oninput="this.value = this.value.toUpperCase()"
                                       class="uppercase w-full h-9 font-semibold">
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
        function processPage(initialCount, existingProcesses, openInitially, oldAddName) {
            return {
                q: '',
                existingProcesses: existingProcesses || [],
                addOpen: openInitially,
                addName: oldAddName || '',
                addError: '',
                editOpen: false,
                editId: null,
                editName: '',
                editError: '',
                visibleCount: initialCount,
                openEditModal(id, name) {
                    this.editId = id;
                    this.editName = name;
                    this.editError = '';
                    this.editOpen = true;
                },
                submitAdd(formEl) {
                    this.addError = '';
                    const name = (this.addName || '').trim().toUpperCase();
                    if (!name) {
                        this.addError = 'Nama proses wajib diisi.';
                        return;
                    }
                    if (this.existingProcesses.some(p => p.name === name)) {
                        this.addError = 'Nama proses ' + name + ' sudah terdaftar.';
                        return;
                    }
                    formEl.submit();
                },
                submitEdit(formEl) {
                    this.editError = '';
                    const name = (this.editName || '').trim().toUpperCase();
                    if (!name) {
                        this.editError = 'Nama proses wajib diisi.';
                        return;
                    }
                    if (this.existingProcesses.some(p => String(p.id) !== String(this.editId) && p.name === name)) {
                        this.editError = 'Nama proses ' + name + ' sudah terdaftar.';
                        return;
                    }
                    formEl.submit();
                },
                matchRow(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-proc-row]').forEach(tr => {
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