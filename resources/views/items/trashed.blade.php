<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Master Item Nonaktif (Arsip)"
                       subtitle="Daftar item yang dinonaktifkan karena sudah memiliki riwayat transaksi dan dapat diaktifkan kembali">
            <x-slot name="actions">
                <a href="{{ route('items.index') }}" class="btn">
                    <x-icon name="chevron-left" /> Kembali ke Master Item Aktif
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="trashedItemPage({{ $items->count() }})"
         x-init="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Item Nonaktif</span>
                <span class="count-chip"><span x-text="visibleRows"></span> Item</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Part No / Part Name..."
                       class="h-8 text-xs px-2.5 w-48">

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe Item</option>
                    @foreach($types as $tp)
                        <option value="{{ $tp }}">{{ $tp }}</option>
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
                        <th class="w-12 text-center">No</th>
                        <th>Part No &amp; Part Name</th>
                        <th class="w-32 text-center">Tipe &amp; UOM</th>
                        <th class="w-36 text-center">Perusahaan</th>
                        <th class="w-40 text-left col-time">Waktu Dinonaktifkan</th>
                        <th class="w-24 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $idx => $item)
                        @php
                            $comp = $item->company;
                            $searchStr = strtolower(($item->part_no ?? '') . ' ' . ($item->part_name ?? '') . ' ' . ($comp->company_code ?? ''));
                        @endphp
                        <tr data-row-item
                            data-search="{{ $searchStr }}"
                            data-type="{{ $item->type }}"
                            x-show="matchFilter($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $item->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900 text-xs">{{ $item->part_name }}</div>
                            </td>
                            <td class="text-center">
                                <span class="pill pill-neu">{{ $item->type }}</span>
                                <div class="num text-[11px] font-semibold text-slate-500 mt-0.5">{{ $item->uom }}</div>
                            </td>
                            <td class="text-center">
                                <span class="num text-xs font-bold text-accent">{{ $comp->company_code ?? '-' }}</span>
                            </td>
                            <td class="num text-left col-time text-xs text-slate-600">
                                {{ $item->deleted_at ? $item->deleted_at->format('d M Y, H:i') : '-' }}
                            </td>
                            <td class="text-center">
                                <form action="{{ route('items.restore', $item->id) }}" method="POST" class="inline"
                                      @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Aktifkan Kembali Item', message: 'Aktifkan kembali item {{ addslashes($item->part_name) }} ke daftar Master Item aktif?' })">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="act-btn" title="Aktifkan Kembali (Restore)">
                                        <x-icon name="restore" />
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">Tidak ada item yang dinonaktifkan.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function trashedItemPage(initialRows) {
            return {
                q: '',
                fType: '',
                visibleRows: initialRows,
                matchFilter(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const tp = el.dataset.type || '';
                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.fType && tp !== this.fType) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-row-item]').forEach(tr => {
                            if (this.matchFilter(tr)) c++;
                        });
                        this.visibleRows = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>