<x-app-layout>
    <div class="space-y-[18px]">
        <div class="page-head">
            <x-page-header title="Item Nonaktif (Arsip Master Item)"
                           subtitle="Daftar item yang dinonaktifkan karena sudah memiliki riwayat transaksi">
                <x-slot name="actions">
                    <a href="{{ route('items.index') }}" class="btn">
                        Kembali ke Master Item Aktif
                    </a>
                </x-slot>
            </x-page-header>
        </div>

        <x-flash />

        <div class="panel">
            <div class="panel-head">
                <h3 class="panel-title">Daftar Item Nonaktif ({{ $items->count() }})</h3>
                <form action="{{ route('items.trashed') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="search" value="{{ request('search') }}"
                           @input.debounce.400ms="$el.form.submit()"
                           placeholder="Cari Part No / Part Name..."
                           class="filter-input w-56">
                    <select name="type" class="filter-input w-44" onchange="this.form.submit()">
                        <option value="">Semua Tipe Item</option>
                        @foreach($types as $t)
                            <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                    @if(request()->anyFilled(['search', 'type']))
                        <a href="{{ route('items.trashed') }}" class="btn btn-sm">Reset</a>
                    @endif
                </form>
            </div>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th class="w-14">No</th>
                            <th>Part No &amp; Part Name</th>
                            <th>Type &amp; UOM</th>
                            <th>Perusahaan</th>
                            <th>Waktu Dinonaktifkan</th>
                            <th class="w-24 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td class="num text-xs font-semibold text-slate-500">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="num text-xs font-semibold text-accent">{{ $item->part_no ?: '-' }}</div>
                                    <div class="font-semibold text-slate-900">{{ $item->part_name }}</div>
                                </td>
                                <td>
                                    <span class="pill pill-neu">{{ $item->type }}</span>
                                    <span class="text-xs text-slate-500 ml-1">{{ $item->uom }}</span>
                                </td>
                                <td>
                                    <div class="text-xs font-semibold text-slate-800">{{ $item->company->company_code ?? '-' }}</div>
                                    <div class="text-xs text-slate-500">{{ $item->company->type_company ?? '-' }}</div>
                                </td>
                                <td class="num text-xs text-slate-500">
                                    {{ $item->deleted_at ? $item->deleted_at->format('d M Y, H:i') : '-' }}
                                </td>
                                <td class="text-right">
                                    <form action="{{ route('items.restore', $item->id) }}" method="POST" class="inline"
                                          onsubmit="return window.askConfirm(this, 'Aktifkan Kembali Item', 'Aktifkan kembali item {{ addslashes($item->part_name) }} ke daftar Master Item aktif?', false)">
                                        @csrf
                                        <button type="submit" class="act-btn" title="Aktifkan Kembali">
                                            <x-icon name="restore" />
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6"><div class="empty">Tidak ada item yang dinonaktifkan.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>