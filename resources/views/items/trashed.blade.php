<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Item Nonaktif (Arsip)"
                       subtitle="Daftar Master Item yang dinonaktifkan karena sudah memiliki riwayat transaksi">
            <x-slot name="actions">
                <a href="{{ route('items.index') }}" class="btn">
                    <x-icon name="chevron-left" /> <span>Kembali ke Master Item</span>
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    <div class="panel" x-data="{ q: '', fType: '' }">
        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Item Nonaktif</span>
                <span class="count-chip"><span>{{ $items->count() }}</span><span>Item</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" placeholder="Cari Part No / Part Name..." class="h-8 text-xs px-2.5 w-48">
                <select x-model="fType" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
                <button type="button" x-show="q || fType" x-cloak @click="q=''; fType=''" class="btn btn-sm">Reset</button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[6%] text-center">No</th>
                        <th class="w-[30%]">Part No &amp; Part Name</th>
                        <th class="w-[14%] text-center">Tipe &amp; UOM</th>
                        <th class="w-[20%]">Perusahaan</th>
                        <th class="w-[18%] col-time">Waktu Dinonaktifkan</th>
                        <th class="w-[12%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $idx => $item)
                        @php
                            $tyCls = 'ty-' . ($item->type === 'FG/KOMPONEN' ? 'FGK' : $item->type);
                        @endphp
                        <tr data-search="{{ strtolower(($item->part_no ?? '') . ' ' . $item->part_name) }}"
                            data-type="{{ $item->type }}"
                            x-show="(!q || $el.dataset.search.includes(q.toLowerCase())) && (!fType || $el.dataset.type === fType)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td>
                                <div class="num text-xs font-bold text-accent">{{ $item->part_no ?: '-' }}</div>
                                <div class="font-semibold text-slate-900">{{ $item->part_name }}</div>
                            </td>
                            <td class="text-center">
                                <span class="ty {{ $tyCls }}">{{ $item->type }}</span>
                                <span class="num text-xs text-slate-500 ml-1">{{ $item->uom }}</span>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-800">{{ $item->company->company_code ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $item->company->company_name ?? '-' }}</div>
                            </td>
                            <td class="num col-time text-slate-600">
                                {{ $item->deleted_at ? $item->deleted_at->format('d M Y, H:i') : '-' }}
                            </td>
                            <td class="text-center">
                                <form action="{{ route('items.restore', $item->id) }}" method="POST" class="inline"
                                      @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Aktifkan Kembali Item', message: 'Aktifkan kembali item {{ addslashes($item->part_name) }}?' })">
                                    @csrf
                                    <button type="submit" class="act-btn" title="Aktifkan Kembali (Restore)">
                                        <x-icon name="restore" />
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">Tidak ada item nonaktif.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>