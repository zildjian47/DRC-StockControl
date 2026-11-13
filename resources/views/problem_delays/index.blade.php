<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Delay Delivery"
                       subtitle="Log pencatatan kendala keterlambatan pengiriman barang ke pelanggan maupun dari pemasok">
            <x-slot name="actions">
                <button type="button" class="btn btn-primary" @click="$dispatch('open-add-delay')">
                    <x-icon name="plus" /> Input Delay
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $itemsByCompanyJs = $items->map(fn ($i) => [
            'id'         => $i->id,
            'company_id' => $i->company_id,
            'label'      => ($i->part_no ? $i->part_no . ' — ' : '') . $i->part_name . ' (' . $i->type . ')',
        ])->values();
    @endphp

    <div class="panel"
         x-data="problemDelayPage({{ $delays->count() }}, {{ Js::from($itemsByCompanyJs) }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-delay.window="addOpen = true"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Log Delay Delivery</span>
                <span class="count-chip"><span x-text="visibleRows"></span> Data</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Perusahaan / Part / Petugas..."
                       class="h-8 text-xs px-2.5 w-48">

                <div class="flex items-center gap-1 text-xs text-slate-600">
                    <span>Tgl:</span>
                    <input type="date" x-model="startDate" @change="recalc()" class="h-8 text-xs px-2 w-32">
                    <span>-</span>
                    <input type="date" x-model="endDate" :min="startDate" @change="recalc()" class="h-8 text-xs px-2 w-32">
                </div>

                <div class="flex items-center gap-1 text-xs text-slate-600">
                    <span>Jam:</span>
                    <input type="time" x-model="startTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                    <span>-</span>
                    <input type="time" x-model="endTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                </div>

                <select x-model="fReason" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Alasan Delay</option>
                    @foreach($reasons as $rs)
                        <option value="{{ $rs }}">{{ $rs }}</option>
                    @endforeach
                </select>

                <button type="button"
                        x-show="q || startDate || endDate || startTime || endTime || fReason"
                        x-cloak @click="resetFilter()" class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-12 text-center">No</th>
                        <th class="w-36 text-left col-time">Tanggal &amp; Waktu</th>
                        <th class="w-40">Petugas</th>
                        <th class="w-56">Perusahaan</th>
                        <th>Part No &amp; Part Name</th>
                        <th class="w-40 text-center">Problem Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($delays as $idx => $dl)
                        @php
                            $comp = $dl->company;
                            $it = $dl->item;
                            $searchStr = strtolower(
                                ($comp->company_code ?? '') . ' ' .
                                ($comp->company_name ?? '') . ' ' .
                                ($it->part_no ?? '') . ' ' .
                                ($it->part_name ?? '') . ' ' .
                                ($dl->problem_reason ?? '') . ' ' .
                                ($dl->user->name ?? 'PPIC')
                            );
                        @endphp
                        <tr data-row-item
                            data-search="{{ $searchStr }}"
                            data-date="{{ \Carbon\Carbon::parse($dl->date_time)->format('Y-m-d') }}"
                            data-time="{{ \Carbon\Carbon::parse($dl->date_time)->format('H:i') }}"
                            data-reason="{{ $dl->problem_reason }}"
                            x-show="matchFilter($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">
                                {{ \Carbon\Carbon::parse($dl->date_time)->format('d M Y, H:i') }}
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $dl->user->name ?? 'PPIC' }}</div>
                                <div class="text-[11px] text-slate-500 uppercase">{{ $dl->user->role ?? 'PPIC' }}</div>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <span class="num text-xs font-bold text-accent">{{ $comp->company_code ?? '-' }}</span>
                                    <span class="pill pill-neu text-[10px] h-4 px-1.5">{{ $comp->type_company ?? '-' }}</span>
                                </div>
                                <div class="text-xs font-medium text-slate-800 mt-0.5">{{ $comp->company_name ?? '-' }}</div>
                            </td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $it->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $it->part_name ?? '-' }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-crit">{{ $dl->problem_reason }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">Belum ada log kendala pengiriman (Delay Delivery).</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT DELAY --}}
        <div x-show="addOpen" x-cloak
             class="modal-backdrop"
             @click.self="addOpen = false">
            <div class="panel w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Input Log Delay Delivery</h3>
                    <button type="button" class="act-btn" @click="addOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('problem_delays.store') }}" method="POST" class="flex-1 overflow-y-auto">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div>
                            <label class="lbl">Pilih Perusahaan</label>
                            <select name="company_id" x-model="selectedCompanyId" @change="selectedItemId = ''" required class="w-full h-9 text-xs">
                                <option value="">-- Pilih Perusahaan --</option>
                                @foreach($companies as $c)
                                    <option value="{{ $c->id }}">{{ $c->company_code }} — {{ $c->company_name }} ({{ $c->type_company }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="lbl">Pilih Item Terkait Perusahaan</label>
                            <select name="item_id" x-model="selectedItemId" :disabled="!selectedCompanyId" required class="w-full h-9 text-xs">
                                <option value="">-- Pilih Item --</option>
                                <template x-for="it in companyItems" :key="it.id">
                                    <option :value="it.id" x-text="it.label"></option>
                                </template>
                            </select>
                            <p class="hint" x-show="selectedCompanyId && companyItems.length === 0">
                                Belum ada Master Item yang terdaftar pada perusahaan ini.
                            </p>
                        </div>

                        <div>
                            <label class="lbl">Problem Reason (Alasan Kendala)</label>
                            <select name="problem_reason" required class="w-full h-9 text-xs">
                                @foreach($reasons as $rs)
                                    <option value="{{ $rs }}">{{ $rs }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Log Delay</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function problemDelayPage(initialRows, itemsList, openInitially) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fReason: '',
                visibleRows: initialRows,
                items: itemsList,
                addOpen: openInitially,
                selectedCompanyId: '',
                selectedItemId: '',

                get companyItems() {
                    if (!this.selectedCompanyId) return [];
                    return this.items.filter(i => String(i.company_id) === String(this.selectedCompanyId));
                },
                matchFilter(el) {
                    const s = (el.dataset.search || '').toLowerCase();
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
                    if (this.startDate && this.endDate && this.endDate < this.startDate) {
                        this.endDate = this.startDate;
                    }
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-row-item]').forEach(tr => {
                            if (this.matchFilter(tr)) c++;
                        });
                        this.visibleRows = c;
                    });
                },
                resetFilter() {
                    this.q = '';
                    this.startDate = '';
                    this.endDate = '';
                    this.startTime = '';
                    this.endTime = '';
                    this.fReason = '';
                    this.recalc();
                }
            };
        }
    </script>
</x-app-layout>