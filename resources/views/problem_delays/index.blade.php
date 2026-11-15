<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Delay Delivery"
                       subtitle="Pencatatan kendala keterlambatan pengiriman barang berdasarkan tanggal kejadian, perusahaan, dan kategori masalah">
            <x-slot name="actions">
                <button type="button" class="btn btn-primary" @click="$dispatch('open-add-delay')">
                    <x-icon name="plus" /> <span>Input Delay</span>
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

        $itemsJs = $items->map(fn ($i) => [
            'id'         => $i->id,
            'company_id' => $i->company_id,
            'part_no'    => $i->part_no ?: '-',
            'part_name'  => $i->part_name,
            'type'       => $i->type,
            'uom'        => $i->uom,
            'comp_code'  => $i->company->company_code ?? 'INTERNAL',
        ])->values();
    @endphp

    <div class="panel"
         x-data="delayPage({{ $delays->count() }}, {{ Js::from($companiesJs) }}, {{ Js::from($itemsJs) }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-delay.window="addOpen = true"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Riwayat Log Delay Delivery</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>Log</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Perusahaan / Part / Petugas..."
                       class="h-8 text-xs px-2.5 w-48">

                <select x-model="fReason" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Alasan</option>
                    @foreach($reasons as $r)
                        <option value="{{ $r }}">{{ $r }}</option>
                    @endforeach
                </select>

                <div class="flex items-center gap-1 text-xs text-slate-600" title="Filter Tanggal Kejadian">
                    <span class="font-semibold">Kejadian:</span>
                    <input type="date" x-model="startDate"
                           @change="if(endDate && endDate < startDate) endDate = startDate; recalc()"
                           class="h-8 text-xs px-2 w-32">
                    <span>-</span>
                    <input type="date" x-model="endDate" :min="startDate" @change="recalc()"
                           class="h-8 text-xs px-2 w-32">
                </div>

                <div class="flex items-center gap-1 text-xs text-slate-600" title="Filter Jam Pencatatan">
                    <span>Jam:</span>
                    <input type="time" x-model="startTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                    <span>-</span>
                    <input type="time" x-model="endTime" @change="recalc()" class="h-8 text-xs px-2 w-24">
                </div>

                <button type="button"
                        x-show="q || fReason || startDate || endDate || startTime || endTime" x-cloak
                        @click="q=''; fReason=''; startDate=''; endDate=''; startTime=''; endTime=''; recalc()"
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
                        <th class="w-[13%] col-time">Tanggal Kejadian</th>
                        <th class="w-[15%] col-time">Waktu Pencatatan</th>
                        <th class="w-[14%]">Petugas Input</th>
                        <th class="w-[18%]">Perusahaan / Tujuan</th>
                        <th class="w-[23%]">Part No &amp; Part Name</th>
                        <th class="w-[12%] text-center">Problem Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($delays as $idx => $d)
                        @php
                            $dt = \Carbon\Carbon::parse($d->date_time);
                            $incDate = $d->incident_date ? \Carbon\Carbon::parse($d->incident_date) : $dt;
                            $uName = $d->user->name ?? 'PPIC';
                            $cCode = $d->company->company_code ?? '-';
                            $cName = $d->company->company_name ?? '-';
                            $cType = $d->company->type_company ?? 'CUSTOMER';
                            $pNo = $d->item->part_no ?: '-';
                            $pName = $d->item->part_name ?? '-';
                            $pType = $d->item->type ?? 'FG';
                            $tyCls = 'ty-' . ($pType === 'FG/KOMPONEN' ? 'FGK' : $pType);
                            $searchStr = strtolower($cCode . ' ' . $cName . ' ' . $pNo . ' ' . $pName . ' ' . $uName . ' ' . $d->problem_reason);
                        @endphp
                        <tr data-delay-row
                            data-search="{{ $searchStr }}"
                            data-reason="{{ $d->problem_reason }}"
                            data-date="{{ $incDate->format('Y-m-d') }}"
                            data-time="{{ $dt->format('H:i') }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num col-time whitespace-nowrap font-bold text-slate-900">
                                {{ $incDate->format('d M Y') }}
                            </td>
                            <td class="num col-time whitespace-nowrap text-slate-600">
                                {{ $dt->format('d M Y, H:i') }}
                            </td>
                            <td>
                                <div class="font-semibold text-slate-800">{{ $uName }}</div>
                                <div class="text-[11px] text-slate-500">{{ $d->user->role ?? 'PPIC' }}</div>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="num font-bold text-accent">{{ $cCode }}</span>
                                    @if($cType === 'CUSTOMER')
                                        <span class="pill pill-good">CUSTOMER</span>
                                    @elseif($cType === 'INTERNAL')
                                        <span class="pill pill-warn">INTERNAL</span>
                                    @else
                                        <span class="pill pill-info">SUPPLIER</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-600 mt-0.5">{{ $cName }}</div>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="ty {{ $tyCls }}">{{ $pType }}</span>
                                    <span class="num text-xs font-bold text-accent">{{ $pNo }}</span>
                                </div>
                                <div class="font-semibold text-slate-900 mt-0.5">{{ $pName }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-crit">{{ $d->problem_reason }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty">Belum ada data log Delay Delivery.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT DELAY DELIVERY --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false">
            <div class="panel w-full max-w-xl overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Input Log Delay Delivery</h3>
                    <button type="button" class="act-btn" @click="addOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('problem_delays.store') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl" for="delay_inc_date">Tanggal Kejadian</label>
                                <input id="delay_inc_date" type="date" name="incident_date"
                                       x-model="incidentDate" required class="num w-full h-9">
                            </div>

                            <div>
                                <label class="lbl" for="delay_comp">Pilih Perusahaan / Customer</label>
                                <select id="delay_comp" name="company_id" x-model="selectedCompanyId" @change="onCompanyChange()" required class="w-full h-9">
                                    <option value="">-- Pilih Perusahaan --</option>
                                    @foreach($companies as $comp)
                                        <option value="{{ $comp->id }}">
                                            {{ $comp->company_code }} — {{ $comp->company_name }} ({{ $comp->type_company }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="lbl" for="delay_item">Pilih Item yang Terkendala</label>
                            <select id="delay_item" name="item_id" x-model="selectedItemId" :disabled="!selectedCompanyId" required class="w-full h-9">
                                <option value="">-- Pilih Item --</option>
                                <template x-for="it in availableItems" :key="it.id">
                                    <option :value="it.id" x-text="(it.part_no !== '-' ? it.part_no + ' — ' : '') + it.part_name + ' [' + it.type + ' · ' + it.comp_code + ']'"></option>
                                </template>
                            </select>
                            <p class="hint" x-show="selectedCompanyType === 'CUSTOMER'" x-cloak>
                                Menampilkan item FG milik Customer ini beserta item FG/KOMPONEN dan TRANSIT yang dapat dikirim ke Customer.
                            </p>
                        </div>

                        <div>
                            <span class="lbl">Kategori Masalah (Problem Reason)</span>
                            <div class="opt-chips">
                                @foreach($reasons as $r)
                                    <label>
                                        <input type="radio" name="problem_reason" value="{{ $r }}" x-model="problemReason" required>
                                        <span>{{ $r }}</span>
                                    </label>
                                @endforeach
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
    </div>

    <script>
        function delayPage(initialCount, companiesList, itemsList, openInitially) {
            return {
                q: '',
                fReason: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                addOpen: openInitially,
                visibleCount: initialCount,
                companiesList: companiesList,
                itemsList: itemsList,
                incidentDate: '{{ old('incident_date', date('Y-m-d')) }}',
                selectedCompanyId: '{{ old('company_id', '') }}',
                selectedItemId: '{{ old('item_id', '') }}',
                problemReason: '{{ old('problem_reason', $reasons[0] ?? 'MATERIAL') }}',

                get selectedCompanyType() {
                    const c = this.companiesList.find(x => String(x.id) === String(this.selectedCompanyId));
                    return c ? c.type : '';
                },
                get availableItems() {
                    if (!this.selectedCompanyId) return [];
                    const cType = this.selectedCompanyType;
                    return this.itemsList.filter(it => {
                        if (String(it.company_id) === String(this.selectedCompanyId)) return true;
                        if (cType === 'CUSTOMER' && (it.type === 'FG/KOMPONEN' || it.type === 'TRANSIT')) return true;
                        return false;
                    });
                },
                onCompanyChange() {
                    const valid = this.availableItems.some(i => String(i.id) === String(this.selectedItemId));
                    if (!valid) {
                        this.selectedItemId = this.availableItems.length ? String(this.availableItems[0].id) : '';
                    }
                },
                matchRow(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const r = el.dataset.reason || '';
                    const d = el.dataset.date || '';
                    const t = el.dataset.time || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.fReason && r !== this.fReason) return false;
                    if (this.startDate && d < this.startDate) return false;
                    if (this.endDate && d > this.endDate) return false;
                    if (this.startTime && t < this.startTime) return false;
                    if (this.endTime && t > this.endTime) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-delay-row]').forEach(tr => {
                            if (this.matchRow(tr)) c++;
                        });
                        this.visibleCount = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>