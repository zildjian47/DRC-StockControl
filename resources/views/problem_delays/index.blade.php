<x-app-layout>
    @php
        $reasons = $reasons ?? ['MATERIAL', 'PROSES', 'QC', 'DIES', 'BOX/PALET', 'PLANT 2'];
        $allItems = $items->map(function ($i) {
            return [
                'id'         => $i->id,
                'company_id' => $i->company_id,
                'label'      => ($i->part_no ? $i->part_no . ' - ' : '') . $i->part_name . ' (' . $i->type . ')',
            ];
        })->values();
    @endphp

    <div x-data="{
            openCreate: {{ $errors->any() ? 'true' : 'false' }},
            items: {{ Js::from($allItems) }},
            selectedCompanyId: '{{ old('company_id', '') }}',
            selectedItemId: '{{ old('item_id', '') }}',
            startDate: '{{ request('start_date') }}',
            endDate: '{{ request('end_date') }}',

            get filteredItems() {
                if (!this.selectedCompanyId) return [];
                return this.items.filter(i => String(i.company_id) === String(this.selectedCompanyId));
            }
         }" class="space-y-[18px]">

        <div class="page-head">
            <x-page-header title="Delay Delivery (Kendala Pengiriman & Pasokan)"
                           subtitle="Pencatatan kendala keterlambatan pengiriman Customer maupun pasokan Supplier">
                <x-slot name="actions">
                    <button type="button" @click="openCreate = true" class="btn btn-primary">
                        <x-icon name="plus" /> Catat Delay
                    </button>
                </x-slot>
            </x-page-header>
        </div>

        <x-flash />

        <div class="panel">
            <div class="panel-head">
                <h3 class="panel-title">Daftar Riwayat Delay Delivery ({{ $delays->count() }} Data)</h3>

                <form action="{{ route('problem_delays.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Cari Perusahaan / Part No / Part Name..."
                           class="filter-input w-56">

                    <div class="flex items-center gap-1">
                        <span class="text-xs font-semibold text-slate-500">Tgl:</span>
                        <input type="date" name="start_date" x-model="startDate"
                               @change="if(endDate && endDate < startDate) endDate = startDate;"
                               class="filter-input num">
                        <span class="text-xs text-slate-400">-</span>
                        <input type="date" name="end_date" x-model="endDate" :min="startDate"
                               class="filter-input num">
                    </div>

                    <div class="flex items-center gap-1">
                        <span class="text-xs font-semibold text-slate-500">Jam:</span>
                        <input type="time" name="start_time" value="{{ request('start_time') }}"
                               class="filter-input num">
                        <span class="text-xs text-slate-400">-</span>
                        <input type="time" name="end_time" value="{{ request('end_time') }}"
                               class="filter-input num">
                    </div>

                    <select name="problem_reason" class="filter-input w-48">
                        <option value="">Semua Alasan Delay</option>
                        @foreach ($reasons as $r)
                            <option value="{{ $r }}" @selected(request('problem_reason') === $r)>{{ $r }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th class="w-14 text-center">No</th>
                            <th class="text-left">Waktu Pencatatan</th>
                            <th>Petugas</th>
                            <th>Perusahaan &amp; Tipe</th>
                            <th>Part No &amp; Part Name</th>
                            <th>Problem Reason (Alasan Kendala)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($delays as $d)
                            <tr>
                                <td class="num text-center text-slate-500 font-semibold">{{ $loop->iteration }}</td>
                                <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">
                                    {{ \Carbon\Carbon::parse($d->date_time)->format('d M Y, H:i') }}
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="font-semibold text-slate-900 text-xs">{{ $d->user->name ?? 'PPIC' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $d->user->role ?? 'PPIC' }}</div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-1.5">
                                        <span class="num font-semibold text-slate-900">{{ $d->company->company_code ?? '-' }}</span>
                                        @if ($d->company)
                                            <span class="pill {{ $d->company->type_company === 'SUPPLIER' ? 'pill-warn' : ($d->company->type_company === 'INTERNAL' ? 'pill-info' : 'pill-good') }}">
                                                {{ $d->company->type_company }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-slate-500 mt-0.5">{{ $d->company->company_name ?? '-' }}</div>
                                </td>
                                <td>
                                    <div class="num text-xs font-semibold text-accent">{{ $d->item->part_no ?: '-' }}</div>
                                    <div class="font-semibold text-slate-900">{{ $d->item->part_name ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="pill pill-warn">{{ $d->problem_reason }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6"><div class="empty">Belum ada catatan kendala pengiriman.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="openCreate" x-cloak class="modal-backdrop">
            <div class="panel w-full max-w-xl" @click.away="openCreate = false">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Catat Kendala Pengiriman / Pasokan</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Pilih perusahaan untuk memunculkan daftar item milik perusahaan tersebut.</p>
                    </div>
                    <button type="button" @click="openCreate = false" class="act-btn" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('problem_delays.store') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div>
                            <label class="lbl">Pilih Perusahaan (Menampilkan Tipe Perusahaan)</label>
                            <select name="company_id" x-model="selectedCompanyId" @change="selectedItemId = ''" required class="w-full h-10">
                                <option value="">-- Pilih Perusahaan --</option>
                                @foreach ($companies as $comp)
                                    <option value="{{ $comp->id }}">
                                        [{{ $comp->type_company }}] {{ $comp->company_code }} — {{ $comp->company_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="lbl">Pilih Item Terkait Perusahaan</label>
                            <select name="item_id" x-model="selectedItemId" :disabled="!selectedCompanyId" required class="w-full h-10">
                                <option value="">-- Pilih Item --</option>
                                <template x-for="it in filteredItems" :key="it.id">
                                    <option :value="it.id" x-text="it.label"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="lbl">Problem Reason (Alasan Kendala)</label>
                            <select name="problem_reason" required class="w-full h-10">
                                <option value="">-- Pilih Alasan Kendala --</option>
                                @foreach ($reasons as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="panel-foot">
                        <button type="button" @click="openCreate = false" class="btn">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Log Delay</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>