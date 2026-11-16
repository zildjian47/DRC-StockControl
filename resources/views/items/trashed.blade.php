<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Item Nonaktif (Arsip)"
                       subtitle="Daftar Master Item yang dinonaktifkan karena sudah memiliki riwayat transaksi dan dapat diaktifkan kembali">
            <x-slot name="actions">
                <a href="{{ route('items.index') }}" class="btn">
                    <x-icon name="chevron-left" />
                    <span>Kembali ke Master Item</span>
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
                <span class="count-chip"><span x-text="visibleCount"></span><span>DATA</span></span>
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

                <button type="button"
                        x-show="q || fType" x-cloak
                        @click="q=''; fType=''; recalc()"
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
                        <th class="w-[16%]">Part No</th>
                        <th class="w-[28%]">Part Name</th>
                        <th class="w-[11%] text-center">Tipe</th>
                        <th class="w-[8%] text-center">UOM</th>
                        <th class="w-[14%]">Perusahaan</th>
                        <th class="w-[12%] col-time">Dinonaktifkan</th>
                        <th class="w-[6%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $idx => $item)
                        @php
                            $tyCls    = 'ty-' . ($item->type === 'FG/KOMPONEN' ? 'FGK' : $item->type);
                            $compCode = $item->company->company_code ?? 'INTERNAL';
                        @endphp
                        <tr data-row
                            data-search="{{ strtolower(($item->part_no ?? '') . ' ' . $item->part_name . ' ' . $compCode) }}"
                            data-type="{{ $item->type }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td class="num text-left font-bold text-accent">{{ $item->part_no ?: '-' }}</td>
                            <td class="font-semibold text-slate-900">{{ $item->part_name }}</td>
                            <td class="text-center"><span class="ty {{ $tyCls }}">{{ $item->type }}</span></td>
                            <td class="text-center"><span class="pill pill-neu">{{ $item->uom }}</span></td>
                            <td>
                                <span class="font-bold text-xs text-accent">{{ $compCode }}</span>
                            </td>
                            <td class="num col-time text-left text-xs text-slate-500">
                                {{ $item->deleted_at ? $item->deleted_at->format('d M Y, H:i') : '-' }}
                            </td>
                            <td class="text-center">
                                <form id="restore-item-{{ $item->id }}" action="{{ route('items.restore', $item->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="button" class="act-btn" title="Aktifkan Kembali (Restore)"
                                            @click="$dispatch('open-confirm', {
                                                title: 'Aktifkan Kembali Master Item',
                                                message: 'Aktifkan kembali item {{ addslashes($item->part_name) }} ke daftar Master Item aktif?',
                                                formId: 'restore-item-{{ $item->id }}'
                                            })">
                                        <x-icon name="restore" />
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty">Tidak ada Master Item yang berstatus nonaktif.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function trashedItemPage(initialCount) {
            return {
                q: '',
                fType: '',
                visibleCount: initialCount,

                matchRow(el) {
                    const s = el.dataset.search || '';
                    const t = el.dataset.type || '';
                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.fType && t !== this.fType) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-row]').forEach(tr => {
                            if (this.matchRow(tr)) c++;
                        });
                        this.visibleCount = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>