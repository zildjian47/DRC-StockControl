<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Import Update Qty PO Bulanan"
                       subtitle="Perbarui Qty PO massal via Excel (.xlsx) dengan layar pratinjau sebelum simpan">
            <x-slot name="actions">
                <span class="btn-group">
                    <a href="{{ route('items.index') }}" class="btn">
                        <x-icon name="chevron-left" />
                        <span>Master Item</span>
                    </a>
                    <a href="{{ route('items.po_import.template') }}" class="btn btn-primary">
                        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        <span>Unduh Template Excel (.xlsx)</span>
                    </a>
                </span>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $fmtNum = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
    @endphp

    <div class="space-y-4">
        {{-- LANGKAH 1 & 2: PANDUAN & FORM UNGGAH EXCEL --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            <section class="panel lg:col-span-5">
                <div class="panel-head">
                    <div class="panel-title">Aturan Import Qty PO</div>
                    <span class="count-chip"><span class="num">{{ $eligibleCount }}</span><span>ITEM PO</span></span>
                </div>
                <div class="panel-body space-y-2.5 text-xs text-slate-600 leading-relaxed">
                    <ol class="list-decimal pl-4 space-y-1.5">
                        <li>Klik <b>Unduh Template Excel (.xlsx)</b> — file sudah terisi otomatis daftar seluruh item aktif bertipe <b>FG</b>, <b>FG/KOMPONEN</b>, dan <b>TRANSIT</b> beserta angka PO saat ini.</li>
                        <li>Isi hanya kolom hijau <b>I (Qty PO Baru)</b> dengan angka minimal <b>0</b>. Biarkan kosong pada baris item yang tidak berubah.</li>
                        <li>Kolom <b>Working Days (WD)</b>, <b>Kanban</b>, nama, tipe, dan perusahaan <b>tidak diubah</b> melalui import ini.</li>
                        <li>Unggah file lalu periksa tabel perbandingan <b>Qty PO &amp; Std Day 1</b> sebelum menekan tombol simpan.</li>
                    </ol>

                    <div class="alert alert-info text-xs leading-relaxed">
                        <x-icon name="info" />
                        <div>Item bertipe <b>RM</b> dan <b>KOMPONEN</b> tidak perlu diisi karena <b>Std Day 1/2/3</b> dan status stoknya akan otomatis terhitung ulang mengikuti resep BOM.</div>
                    </div>
                </div>
            </section>

            <section class="panel lg:col-span-7">
                <div class="panel-head">
                    <div class="panel-title">Unggah File Excel (.xlsx)</div>
                    <span class="pill pill-info">KHUSUS PPIC</span>
                </div>
                <form action="{{ route('items.po_import.preview') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="panel-body space-y-3">
                        <div>
                            <label for="po_excel_file" class="lbl">Pilih File Excel (.xlsx, maks 5 MB)</label>
                            <input id="po_excel_file"
                                   type="file"
                                   name="file"
                                   accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                   required
                                   class="block w-full text-xs text-slate-700 file:mr-3 file:py-2 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-white hover:file:bg-slate-700 border border-slate-300 rounded p-1.5 bg-white">
                            @error('file')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="text-xs text-slate-500">
                            File yang diunggah akan dibaca terlebih dahulu ke layar <b>Pratinjau (Preview)</b>. Data di database belum berubah sampai Anda menekan konfirmasi simpan.
                        </div>
                    </div>

                    <div class="panel-foot">
                        <a href="{{ route('items.po_import.template') }}" class="btn">Unduh Template Dulu</a>
                        <button type="submit" class="btn btn-primary">
                            <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            <span>Baca &amp; Tampilkan Pratinjau</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>

        {{-- LANGKAH 3: LAYAR PRATINJAU HASIL PARSING EXCEL --}}
        @if(!empty($preview))
            @php
                $changes      = $preview['changes'] ?? [];
                $unchanged    = $preview['unchanged'] ?? [];
                $parseErrors  = $preview['errors'] ?? [];
                $blankSkipped = (int) ($preview['blank_skipped'] ?? 0);
            @endphp

            <section class="panel" x-data="{ showUnchanged: false, ignoreErrors: false }">
                <div class="panel-head">
                    <div>
                        <div class="panel-title">
                            <span>Pratinjau Import: {{ $preview['filename'] ?? 'File Excel' }}</span>
                        </div>
                        <p class="hint" style="margin-top: 2px">Dibaca pada {{ $preview['uploaded_at'] ?? '-' }} · Database belum diubah.</p>
                    </div>
                    <form action="{{ route('items.po_import.cancel') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm">Batalkan Pratinjau</button>
                    </form>
                </div>

                <div class="panel-body space-y-4">
                    {{-- RINGKASAN HITUNGAN --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="p-3 rounded bg-blue-50/70 border border-blue-200">
                            <div class="text-[10.5px] font-bold uppercase tracking-wider text-blue-700">Akan Diubah</div>
                            <div class="num text-xl font-bold text-slate-900 mt-0.5">{{ count($changes) }}</div>
                            <div class="text-[11px] text-slate-500">Baris valid dengan PO baru</div>
                        </div>
                        <div class="p-3 rounded bg-slate-50 border border-slate-200">
                            <div class="text-[10.5px] font-bold uppercase tracking-wider text-slate-500">Nilai Sama</div>
                            <div class="num text-xl font-bold text-slate-800 mt-0.5">{{ count($unchanged) }}</div>
                            <div class="text-[11px] text-slate-500">Tidak ada selisih angka</div>
                        </div>
                        <div class="p-3 rounded bg-slate-50 border border-slate-200">
                            <div class="text-[10.5px] font-bold uppercase tracking-wider text-slate-500">Kosong / Dilewati</div>
                            <div class="num text-xl font-bold text-slate-800 mt-0.5">{{ $blankSkipped }}</div>
                            <div class="text-[11px] text-slate-500">Kolom Qty PO Baru kosong</div>
                        </div>
                        <div class="p-3 rounded {{ count($parseErrors) > 0 ? 'bg-red-50 border-red-200' : 'bg-emerald-50/70 border-emerald-200' }} border">
                            <div class="text-[10.5px] font-bold uppercase tracking-wider {{ count($parseErrors) > 0 ? 'text-red-700' : 'text-emerald-700' }}">Baris Error</div>
                            <div class="num text-xl font-bold {{ count($parseErrors) > 0 ? 'text-red-700' : 'text-emerald-700' }} mt-0.5">{{ count($parseErrors) }}</div>
                            <div class="text-[11px] text-slate-500">{{ count($parseErrors) > 0 ? 'Perlu dicek sebelum simpan' : 'Semua baris valid' }}</div>
                        </div>
                    </div>

                    {{-- DAFTAR ERROR VALIDASI (JIKA ADA) --}}
                    @if(count($parseErrors) > 0)
                        <div class="alert alert-crit text-xs">
                            <x-icon name="alert" />
                            <div class="w-full space-y-2">
                                <div class="font-bold">Ditemukan {{ count($parseErrors) }} baris error pada file Excel:</div>
                                <div class="max-h-44 overflow-y-auto bg-white/80 rounded border border-red-200 divide-y divide-red-100">
                                    @foreach($parseErrors as $err)
                                        <div class="px-2.5 py-1.5 flex flex-wrap items-center gap-x-2">
                                            <span class="pill pill-crit num">Baris {{ $err['row'] }}</span>
                                            <span class="num font-bold text-slate-800">{{ $err['ref'] }}</span>
                                            <span class="text-red-800">{{ $err['message'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- TABEL ITEM YANG AKAN BERUBAH --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="sec-h" style="margin:0;">Daftar Perubahan Qty PO ({{ count($changes) }} Item)</h4>
                            @if(count($unchanged) > 0)
                                <button type="button" class="btn btn-sm" @click="showUnchanged = !showUnchanged">
                                    <span x-text="showUnchanged ? 'Sembunyikan Item Sama' : 'Lihat Item Sama ({{ count($unchanged) }})'"></span>
                                </button>
                            @endif
                        </div>

                        <div class="tbl-wrap border border-slate-200 rounded">
                            <table class="tbl">
                                <thead>
                                    <tr>
                                        <th class="w-[6%] text-center">Baris</th>
                                        <th class="w-[26%]">Part No &amp; Part Name</th>
                                        <th class="w-[10%] text-center">Tipe</th>
                                        <th class="w-[12%] text-center">WD / Kanban</th>
                                        <th class="num w-[11%]">PO Lama</th>
                                        <th class="num w-[11%]">PO Baru</th>
                                        <th class="num w-[10%]">Selisih</th>
                                        <th class="num w-[14%]">Std Day 1</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($changes as $ch)
                                        @php
                                            $tyCls = 'ty-' . ($ch['type'] === 'FG/KOMPONEN' ? 'FGK' : $ch['type']);
                                            $diff  = (float) $ch['diff_qty_po'];
                                        @endphp
                                        <tr>
                                            <td class="text-center num text-slate-500">#{{ $ch['row'] }}</td>
                                            <td>
                                                <div class="num text-xs font-bold text-accent">{{ $ch['part_no'] }}</div>
                                                <div class="font-semibold text-slate-900">{{ $ch['part_name'] }}</div>
                                                <div class="text-[11px] text-slate-500">{{ $ch['company_code'] }}</div>
                                            </td>
                                            <td class="text-center">
                                                <span class="ty {{ $tyCls }}">{{ $ch['type'] }}</span>
                                                <div class="text-[11px] text-slate-500 mt-0.5">{{ $ch['uom'] }}</div>
                                            </td>
                                            <td class="text-center num text-xs text-slate-600">
                                                <div>WD: <b>{{ $ch['working_days'] }}</b></div>
                                                <div>KB: <b>{{ $fmtNum($ch['qty_kanban']) }}</b></div>
                                            </td>
                                            <td class="num text-slate-600">{{ $fmtNum($ch['old_qty_po']) }}</td>
                                            <td class="num font-bold text-slate-900">{{ $fmtNum($ch['new_qty_po']) }}</td>
                                            <td class="num font-bold {{ $diff >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                                {{ $diff > 0 ? '+' : '' }}{{ $fmtNum($diff) }}
                                            </td>
                                            <td class="num text-xs">
                                                <span class="text-slate-500">{{ $fmtNum($ch['old_std_1']) }}</span>
                                                <span class="mx-1 text-slate-400">→</span>
                                                <b class="text-accent">{{ $fmtNum($ch['new_std_1']) }}</b>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8">
                                                <div class="empty">Tidak ada perubahan Qty PO baru yang siap disimpan.</div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- TABEL ITEM SAMA (OPSIONAL TOGGLE) --}}
                    @if(count($unchanged) > 0)
                        <div x-show="showUnchanged" x-cloak>
                            <h4 class="sec-h">Item dengan Nilai Qty PO Sama (Dilewati · {{ count($unchanged) }} Item)</h4>
                            <div class="tbl-wrap border border-slate-200 rounded">
                                <table class="tbl">
                                    <thead>
                                        <tr>
                                            <th class="w-[8%] text-center">Baris</th>
                                            <th class="w-[48%]">Part No &amp; Part Name</th>
                                            <th class="w-[14%] text-center">Tipe</th>
                                            <th class="num w-[15%]">Qty PO</th>
                                            <th class="num w-[15%]">Std Day 1</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($unchanged as $uc)
                                            <tr>
                                                <td class="text-center num text-slate-500">#{{ $uc['row'] }}</td>
                                                <td>
                                                    <span class="num font-bold text-accent">{{ $uc['part_no'] }}</span>
                                                    <span class="text-slate-700 ml-1.5">{{ $uc['part_name'] }}</span>
                                                </td>
                                                <td class="text-center text-xs">{{ $uc['type'] }}</td>
                                                <td class="num text-slate-600">{{ $fmtNum($uc['old_qty_po']) }}</td>
                                                <td class="num text-slate-600">{{ $fmtNum($uc['old_std_1']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                @if(count($changes) > 0)
                    <form action="{{ route('items.po_import.commit') }}" method="POST">
                        @csrf
                        <input type="hidden" name="preview_token" value="{{ $preview['token'] }}">

                        <div class="panel-foot flex-wrap gap-3">
                            @if(count($parseErrors) > 0)
                                <label class="inline-flex items-center gap-2 text-xs text-slate-700 mr-auto cursor-pointer">
                                    <input type="checkbox" name="ignore_errors" value="1" x-model="ignoreErrors">
                                    <span>Lewati {{ count($parseErrors) }} baris error dan simpan hanya {{ count($changes) }} item yang valid</span>
                                </label>
                            @else
                                <span class="text-xs text-slate-500 mr-auto">
                                    Menyimpan akan menghitung ulang Std Day 1/2/3 dan status stok seluruh item (termasuk RM &amp; Komponen via BOM).
                                </span>
                            @endif

                            <button type="submit"
                                    class="btn btn-primary"
                                    :disabled="{{ count($parseErrors) > 0 ? '!ignoreErrors' : 'false' }}">
                                Konfirmasi &amp; Simpan {{ count($changes) }} Perubahan Qty PO
                            </button>
                        </div>
                    </form>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>