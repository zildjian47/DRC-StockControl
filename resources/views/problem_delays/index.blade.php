<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Delay Delivery"
                       subtitle="Pencatatan log kendala keterlambatan pengiriman barang ke Customer maupun dari Supplier">
            <x-slot name="actions">
                <button type="button" class="btn btn-primary" @click="$dispatch('open-add-delay')">
                    <x-icon name="plus" /> Input Delay
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $itemsJs = $items->map(fn ($i) => [
            'id'         => $i->id,
            'company_id' => $i->company_id,
            'label'      => ($i->part_no ? $i->part_no . ' — ' : '') . $i->part_name . ' (' . $i->type . ')',
        ])->values();
    @endphp

    <div class="panel"
         x-data="delayPage({{ $delays->count() }}, {{ Js::from($itemsJs) }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-delay.window="addOpen = true"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Log Delay Delivery</span>
                <span class="count-chip"><span x-text="visibleRows"></span><span>Data</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Perusahaan / Part / Petugas..."
                       class="h-8 text-xs px-2.5 w-48">

                <div class="flex items-center gap-1 text-xs text-slate-600">
                    <span>Tgl:</span>
                    <input type="date" x-model="startDate"
                           @change="if(endDate && endDate < startDate) endDate = startDate; recalc()"
                           class="h-8 text-xs px-2 w-32">
                    <span>-</span>
                    <input type="date" x-model="endDate" :min="startDate" @change="recalc()"
                           class="h-8 text-xs px-2 w-32">
                </div>

                <div class="flex items-center gap-1 text-xs text-slate-600">
                    <span>Jam:</span>
                    <input type="time" x-model="startTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                    <span>-</span>
                    <input type="time" x-model="endTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                </div>

                <select x-model="fReason" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Problem Reason</option>
                    @foreach($reasons as $r)
                        <option value="{{ $r }}">{{ $r }}</option>
                    @endforeach
                </select>

                <button type="button"
                        x-show="q || startDate || endDate || startTime || endTime || fReason"
                        x-cloak
                        @click="resetFilter()"
                        class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[5%] text-center">No</th>
                        <th class="w-[16%] text-left col-time">Waktu Pencatatan</th>
                        <th class="w-[16%]">Petugas</th>
                        <th class="w-[20%]">Perusahaan</th>
                        <th class="w-[27%]">Part No &amp; Part Name</th>
                        <th class="w-[16%] text-center">Problem Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($delays as $idx => $d)
                        @php
                            $dt = \Carbon\Carbon::parse($d->date_time);
                            $comp = $d->company;
                            $compType = $comp->type_company ?? 'CUSTOMER';
                            $searchStr = strtolower(
                                ($comp->company_code ?? '') . ' ' .
                                ($comp->company_name ?? '') . ' ' .
                                ($d->item->part_no ?? '') . ' ' .
                                ($d->item->part_name ?? '') . ' ' .
                                ($d->problem_reason ?? '') . ' ' .
                                ($d->user->name ?? 'PPIC')
                            );
                        @endphp
                        <tr data-delay-row
                            data-search="{{ $searchStr }}"
                            data-date="{{ $dt->format('Y-m-d') }}"
                            data-time="{{ $dt->format('H:i') }}"
                            data-reason="{{ $d->problem_reason }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">{{ $dt->format('d M Y, H:i') }}</td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $d->user->name ?? 'PPIC' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $d->user->role ?? 'PPIC' }}</div>
                            </td>
                            <td>
                                <div class="font-bold text-slate-900 text-xs">{{ $comp->company_code ?? '-' }} — {{ $comp->company_name ?? '-' }}</div>
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
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $d->item->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $d->item->part_name ?? '-' }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-crit">{{ $d->problem_reason }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">Belum ada data log Delay Delivery.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT DELAY DELIVERY --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false">
            <div class="panel w-full max-w-lg overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Input Delay Delivery Baru</h3>
                    <button type="button" class="act-btn" @click="addOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>
                <form action="{{ route('problem_delays.store') }}" method="POST">
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
                                <template x-for="it in filteredItems" :key="it.id">
                                    <option :value="it.id" x-text="it.label"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="lbl">Problem Reason</label>
                            <select name="problem_reason" required class="w-full h-9 text-xs">
                                @foreach($reasons as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
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
        function delayPage(initialCount, itemsList, openInitially) {
            return {
                q: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                fReason: '',
                visibleRows: initialCount,
                itemsList: itemsList,
                addOpen: openInitially,
                selectedCompanyId: '',
                selectedItemId: '',

                get filteredItems() {
                    if (!this.selectedCompanyId) return [];
                    return this.itemsList.filter(i => String(i.company_id) === String(this.selectedCompanyId));
                },
                matchRow(el) {
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
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-delay-row]').forEach(tr => {
                            if (this.matchRow(tr)) c++;
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