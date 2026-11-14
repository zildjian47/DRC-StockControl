<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Master Item Nonaktif (Arsip)"
                       subtitle="Daftar item yang dinonaktifkan karena sudah memiliki riwayat transaksi (dapat diaktifkan kembali kapan saja)">
            <x-slot name="actions">
                <a href="{{ route('items.index') }}" class="btn">
                    <x-icon name="chevron-left" /> Kembali ke Master Item Aktif
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="{
            q: '',
            fType: '',
            visibleRows: {{ $items->count() }},
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
                    this.$el.querySelectorAll('tbody tr[data-trash-row]').forEach(tr => {
                        if (this.matchRow(tr)) c++;
                    });
                    this.visibleRows = c;
                });
            }
         }"
         x-init="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Item Nonaktif</span>
                <span class="count-chip"><span x-text="visibleRows"></span><span>Item</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Part No / Part Name..."
                       class="h-8 text-xs px-2.5 w-48">

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe Item</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
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
                        <th class="w-[5%] text-center">No</th>
                        <th class="w-[30%]">Part No &amp; Part Name</th>
                        <th class="w-[14%] text-center">Tipe &amp; UOM</th>
                        <th class="w-[22%]">Perusahaan</th>
                        <th class="w-[18%] text-left col-time">Waktu Dinonaktifkan</th>
                        <th class="w-[11%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $idx => $item)
                        <tr data-trash-row
                            data-search="{{ strtolower(($item->part_no ?? '') . ' ' . $item->part_name . ' ' . $item->type . ' ' . ($item->company->company_code ?? '')) }}"
                            data-type="{{ $item->type }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $item->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $item->part_name }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-neu">{{ $item->type }}</span>
                                <span class="num text-xs font-semibold text-slate-500 ml-1">{{ $item->uom }}</span>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-900 text-xs">{{ $item->company->company_code ?? '-' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $item->company->company_name ?? '-' }}</div>
                            </td>
                            <td class="num text-left col-time text-xs text-slate-600">
                                {{ $item->deleted_at ? $item->deleted_at->format('d M Y, H:i') : '-' }}
                            </td>
                            <td class="text-center">
                                <form action="{{ route('items.restore', $item->id) }}" method="POST" class="inline"
                                      @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Aktifkan Kembali Item', message: 'Aktifkan kembali item {{ addslashes($item->part_name) }}?' })">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary" title="Restore / Aktifkan Kembali">
                                        <x-icon name="restore" /> Aktifkan
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">Tidak ada item nonaktif di arsip.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>