<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Form NG"
                       subtitle="Pencatatan temuan produk cacat (Not Good) per Nomor Lot yang mengurangi Act FG dan saldo stok gudang">
            <x-slot name="actions">
                <span class="btn-group">
                    <a href="{{ route('production_ngs.index', ['mode' => 'scanner']) }}" class="btn">
                        <x-icon name="scan" /> <span>Buka Mobile Scanner</span>
                    </a>
                    <button type="button" class="btn btn-primary" @click="$dispatch('open-add-ng')">
                        <x-icon name="plus" /> <span>Input NG</span>
                    </button>
                </span>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="prodNgPage({{ $ngs->count() }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-ng.window="addOpen = true"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Riwayat Pencatatan Production NG</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>Data</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari No. Lot / Part / Petugas..."
                       class="h-8 text-xs px-2.5 w-48">

                <select x-model="fReason" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Alasan NG</option>
                    @foreach($reasons as $r)
                        <option value="{{ $r }}">{{ $r }}</option>
                    @endforeach
                </select>

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
                        <th class="w-[6%] text-center">No</th>
                        <th class="w-[16%] col-time">Waktu Pencatatan</th>
                        <th class="w-[15%]">Petugas QC</th>
                        <th class="w-[12%] text-center">No. Lot</th>
                        <th class="w-[26%]">Part No &amp; Part Name</th>
                        <th class="w-[13%] text-center">Problem Reason</th>
                        <th class="num w-[12%]">Qty NG</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ngs as $idx => $ng)
                        @php
                            $dt = \Carbon\Carbon::parse($ng->date_time);
                            $uName = $ng->user->name ?? 'QC';
                            $pNo = $ng->item->part_no ?: '-';
                            $pName = $ng->item->part_name ?? '-';
                            $pType = $ng->item->type ?? 'FG';
                            $tyCls = 'ty-' . ($pType === 'FG/KOMPONEN' ? 'FGK' : $pType);
                            $uom = $ng->item->uom ?? 'PCS';
                            $searchStr = strtolower($ng->lot_number . ' ' . $pNo . ' ' . $pName . ' ' . $uName . ' ' . $ng->problem_reason);
                        @endphp
                        <tr data-ng-row
                            data-search="{{ $searchStr }}"
                            data-reason="{{ $ng->problem_reason }}"
                            data-date="{{ $dt->format('Y-m-d') }}"
                            data-time="{{ $dt->format('H:i') }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num col-time whitespace-nowrap">{{ $dt->format('d M Y, H:i') }}</td>
                            <td>
                                <div class="font-semibold text-slate-800">{{ $uName }}</div>
                                <div class="text-[11px] text-slate-500">{{ $ng->user->role ?? 'QC' }}</div>
                            </td>
                            <td class="text-center">
                                <span class="num font-bold text-accent">{{ $ng->lot_number }}</span>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="ty {{ $tyCls }}">{{ $pType }}</span>
                                    <span class="num text-xs font-bold text-accent">{{ $pNo }}</span>
                                </div>
                                <div class="font-semibold text-slate-900 mt-0.5">{{ $pName }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-crit">{{ $ng->problem_reason }}</span>
                            </td>
                            <td class="num whitespace-nowrap">
                                <div class="inline-flex items-baseline justify-end gap-1 leading-none">
                                    <span class="font-bold text-rose-700">-{{ floatval($ng->qty_ng) }}</span>
                                    <span class="text-xs font-semibold text-slate-500">{{ $uom }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty">Belum ada data temuan Production NG.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL INPUT NG --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false">
            <div class="panel w-full max-w-lg overflow-hidden">
                <div class="panel-head">
                    <h3 class="panel-title">Input Production NG Baru</h3>
                    <button type="button" class="act-btn" @click="addOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('production_ngs.store') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div>
                            <label class="lbl">Pilih Nomor Lot Aktif (ON PROGRESS)</label>
                            <select name="lot_tracer_id" required class="w-full h-9 text-xs">
                                <option value="">-- Pilih Nomor Lot &amp; Produk --</option>
                                @foreach($activeLots as $lt)
                                    @php $maxNg = max(0, (float)$lt->actual_qty_prod - (float)$lt->qty_ng); @endphp
                                    <option value="{{ $lt->id }}">
                                        {{ $lt->lot_number }} — {{ $lt->targetItem->part_name ?? '-' }} (Act FG: {{ floatval($maxNg) }} {{ $lt->targetItem->uom ?? 'PCS' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <span class="lbl">Problem Reason (Alasan NG)</span>
                            <div class="opt-chips">
                                @foreach($reasons as $r)
                                    <label>
                                        <input type="radio" name="problem_reason" value="{{ $r }}" {{ $loop->first ? 'checked' : '' }} required>
                                        <span>{{ $r }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="lbl">Qty NG (Maksimal sebesar Act FG)</label>
                            <input type="number" step="0.01" min="0.01" name="qty_ng" required placeholder="0" class="num w-full h-9 font-bold">
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Data NG</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function prodNgPage(initialCount, openInitially) {
            return {
                q: '',
                fReason: '',
                startDate: '',
                endDate: '',
                startTime: '',
                endTime: '',
                addOpen: openInitially,
                visibleCount: initialCount,
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
                        this.$el.querySelectorAll('tbody tr[data-ng-row]').forEach(tr => {
                            if (this.matchRow(tr)) c++;
                        });
                        this.visibleCount = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>