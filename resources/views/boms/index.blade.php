<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bill of Materials (BOM)"
                       subtitle="Struktur resep kebutuhan material & komponen per proses produksi (Single, Multi-Input, Multi-Output, & Kombinasi)">
            <x-slot name="actions">
                <button type="button" class="btn btn-primary" @click="$dispatch('open-add-bom')">
                    <x-icon name="plus" /> Buat BOM
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $groupedBoms = $groupedBoms ?? ($groups ?? collect());
        $inputItemsJs = ($inputItems ?? collect())->map(fn ($i) => [
            'id'    => $i->id,
            'label' => ($i->part_no ? $i->part_no . ' — ' : '') . $i->part_name . ' (' . $i->uom . ')',
            'name'  => $i->part_name,
            'uom'   => $i->uom,
            'type'  => $i->type,
        ])->values();

        $outputItemsJs = ($outputItems ?? collect())->map(fn ($i) => [
            'id'    => $i->id,
            'label' => ($i->part_no ? $i->part_no . ' — ' : '') . $i->part_name . ' (' . $i->uom . ')',
            'name'  => $i->part_name,
            'uom'   => $i->uom,
            'type'  => $i->type,
        ])->values();
    @endphp

    <div class="panel"
         x-data="bomPage({{ $groupedBoms->count() }}, {{ $boms->count() }}, {{ Js::from($inputItemsJs) }}, {{ Js::from($outputItemsJs) }}, {{ $errors->any() ? 'true' : 'false' }})"
         @open-add-bom.window="addOpen = true"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Struktur BOM</span>
                <span class="count-chip"><span x-text="visibleGroups"></span> Kelompok</span>
                <span class="count-chip count-chip-sub">{{ $boms->count() }} Baris</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Input / Output..."
                       class="h-8 text-xs px-2.5 w-48">

                <select x-model="fProcess" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Proses</option>
                    @foreach($processes as $proc)
                        <option value="{{ $proc->id }}">{{ $proc->process_name }}</option>
                    @endforeach
                </select>

                <select x-model="fBomType" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Tipe BOM</option>
                    <option value="SINGLE">SINGLE</option>
                    <option value="MULTI-INPUT">MULTI-INPUT</option>
                    <option value="MULTI-OUTPUT">MULTI-OUTPUT</option>
                    <option value="KOMBINASI">KOMBINASI</option>
                </select>

                <button type="button" x-show="q || fProcess || fBomType" x-cloak
                        @click="q=''; fProcess=''; fBomType=''; recalc()" class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-12 text-center">No</th>
                        <th class="w-44">Proses &amp; Tipe BOM</th>
                        <th>Material Input (Trigger &amp; Pendamping)</th>
                        <th>Target Output &amp; Rasio Pemakaian (Qty Usage)</th>
                        <th class="w-20 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groupedBoms as $idx => $grp)
                        @php $bType = $grp['bom_type']; @endphp
                        <tr data-bom-row
                            data-search="{{ $grp['search_index'] }}"
                            data-process="{{ $grp['process_id'] }}"
                            data-bomtype="{{ $bType }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td>
                                <div class="font-semibold text-slate-900">{{ $grp['process_name'] }}</div>
                                <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                                    @if($bType === 'SINGLE')
                                        <span class="pill pill-neu">SINGLE</span>
                                    @elseif($bType === 'MULTI-INPUT')
                                        <span class="pill pill-info">MULTI-INPUT</span>
                                    @elseif($bType === 'MULTI-OUTPUT')
                                        <span class="pill pill-warn">MULTI-OUTPUT</span>
                                    @else
                                        <span class="pill pill-good">KOMBINASI</span>
                                    @endif
                                    <span class="num text-[11px] text-slate-400">{{ $grp['group_key'] }}</span>
                                </div>
                            </td>

                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['inputs'] as $inRow)
                                        @php $inIt = $inRow['item']; @endphp
                                        <div class="px-3 py-2.5 flex items-center justify-between gap-2 {{ $inRow['is_trigger'] ? '' : 'bg-slate-50/60' }}">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                @if($inRow['is_trigger'])
                                                    <span class="pill pill-good w-[88px] justify-center flex-none text-[10px] h-5 px-1.5">Trigger Utama</span>
                                                @else
                                                    <span class="pill pill-neu w-[88px] justify-center flex-none text-[10px] h-5 px-1.5">Pendamping</span>
                                                @endif
                                                <div class="min-w-0 leading-tight">
                                                    <div class="num text-[11.5px] font-bold text-slate-600 truncate">{{ $inIt->part_no ?: '-' }}</div>
                                                    <div class="text-xs font-semibold text-slate-900 truncate">{{ $inIt->part_name ?? '-' }}</div>
                                                </div>
                                            </div>
                                            <span class="pill pill-neu text-[10.5px] flex-none">{{ $inIt->uom ?? '' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['outputs'] as $outRow)
                                        @php $outIt = $outRow['item']; @endphp
                                        <div class="px-3 py-2.5">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <span class="num text-xs font-bold text-accent">{{ $outIt->part_no ?: '-' }}</span>
                                                    <span class="font-semibold text-slate-900 text-xs ml-1">{{ $outIt->part_name ?? '-' }}</span>
                                                </div>
                                                <span class="pill pill-neu text-[10.5px]">Output: 1 {{ $outIt->uom ?? 'PCS' }}</span>
                                            </div>
                                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                                @foreach($outRow['inputs'] as $u)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] border {{ $u['is_trigger'] ? 'bg-blue-50/70 border-blue-200 text-blue-900 font-semibold' : 'bg-slate-50 border-slate-200 text-slate-700' }}">
                                                        <span>{{ $u['input_part_name'] }}:</span>
                                                        <b class="num">{{ floatval($u['qty_usage']) }} {{ $u['input_uom'] }}</b>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="text-center">
                                <form action="{{ route('boms.destroy', $grp['first_id']) }}" method="POST" class="inline"
                                      @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Hapus Kelompok BOM', message: 'Hapus seluruh resep BOM pada kelompok {{ $grp['group_key'] }}?' })">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="act-btn danger" title="Hapus Kelompok BOM">
                                        <x-icon name="trash" />
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">Belum ada data BOM yang terdaftar.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL BUAT BOM 2 KOLOM (KIRI = INPUT, KANAN = OUTPUT) --}}
        <div x-show="addOpen" x-cloak
             class="modal-backdrop"
             @click.self="addOpen = false">
            <div class="panel w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">
                <div class="panel-head">
                    <div class="flex items-center gap-2.5">
                        <h3 class="panel-title">Buat Resep BOM Baru</h3>
                        <span class="pill pill-info" x-text="'Deteksi Otomatis: ' + detectedType"></span>
                    </div>
                    <button type="button" class="act-btn" @click="addOpen = false">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('boms.store') }}" method="POST" class="flex-1 overflow-y-auto">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div>
                            <label class="lbl">Pilih Proses Manufaktur</label>
                            <select name="process_id" required class="w-full h-9 text-xs">
                                <option value="">-- Pilih Proses --</option>
                                @foreach($processes as $proc)
                                    <option value="{{ $proc->id }}">{{ $proc->process_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <input type="hidden" name="trigger_index" :value="triggerIndex">

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
                            {{-- KOLOM KIRI: MATERIAL INPUT --}}
                            <div class="p-3 rounded border border-slate-200 bg-slate-50/60 space-y-3">
                                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                    <div>
                                        <div class="text-xs font-bold text-slate-900 uppercase">Kolom Kiri · Material Input</div>
                                        <div class="text-[11px] text-slate-500">Pilih 1 Trigger Utama; sisanya otomatis menjadi Pendamping</div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" @click="addInput()">
                                        <x-icon name="plus" /> Tambah Input
                                    </button>
                                </div>

                                <template x-for="(inp, iIdx) in formInputs" :key="iIdx">
                                    <div class="p-2.5 rounded bg-white border border-slate-200 space-y-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <label class="inline-flex items-center gap-1.5 text-xs font-semibold cursor-pointer">
                                                <input type="radio" name="trigger_radio" :checked="triggerIndex === iIdx" @change="triggerIndex = iIdx">
                                                <span :class="triggerIndex === iIdx ? 'text-emerald-700 font-bold' : 'text-slate-600'"
                                                      x-text="triggerIndex === iIdx ? 'Trigger Utama' : 'Komponen Pendamping'"></span>
                                            </label>
                                            <button type="button" x-show="formInputs.length > 1" @click="removeInput(iIdx)"
                                                    class="text-xs text-rose-600 font-semibold hover:underline">Hapus</button>
                                        </div>

                                        <div>
                                            <select :name="'inputs[' + iIdx + '][item_id]'" x-model="inp.item_id" required class="w-full h-9 text-xs">
                                                <option value="">-- Pilih Material / Komponen Input --</option>
                                                <template x-for="it in inputItemsList" :key="it.id">
                                                    <option :value="it.id" x-text="it.label"></option>
                                                </template>
                                            </select>
                                        </div>

                                        {{-- Jika Output hanya 1 (SINGLE / MULTI-INPUT), Qty Usage diisi langsung di Kiri --}}
                                        <div x-show="formOutputs.length === 1">
                                            <label class="lbl">Qty Usage (Kalkulator: contoh 1/3, 0.5, 2)</label>
                                            <input type="text"
                                                   :name="'inputs[' + iIdx + '][qty_usage]'"
                                                   x-model="inp.qty_usage"
                                                   :required="formOutputs.length === 1"
                                                   placeholder="Contoh: 1/3 atau 0.2 atau 2"
                                                   class="num w-full h-9 text-xs font-semibold">
                                            <div class="text-[11px] text-slate-500 mt-1" x-show="evalFormula(inp.qty_usage) !== null">
                                                Hasil: <b class="num text-slate-900" x-text="evalFormula(inp.qty_usage)"></b>
                                                <span x-text="getItemUom(inp.item_id, true) + ' per 1 ' + (getItemUom(formOutputs[0]?.item_id, false) || 'Output')"></span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- KOLOM KANAN: TARGET OUTPUT --}}
                            <div class="p-3 rounded border border-slate-200 bg-slate-50/60 space-y-3">
                                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                    <div>
                                        <div class="text-xs font-bold text-slate-900 uppercase">Kolom Kanan · Target Output</div>
                                        <div class="text-[11px] text-slate-500">Tambahkan &gt;1 Output untuk Multi-Output / Kombinasi</div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" @click="addOutput()">
                                        <x-icon name="plus" /> Tambah Output
                                    </button>
                                </div>

                                <template x-for="(out, oIdx) in formOutputs" :key="oIdx">
                                    <div class="p-2.5 rounded bg-white border border-slate-200 space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-accent" x-text="'Target Output #' + (oIdx + 1)"></span>
                                            <button type="button" x-show="formOutputs.length > 1" @click="removeOutput(oIdx)"
                                                    class="text-xs text-rose-600 font-semibold hover:underline">Hapus</button>
                                        </div>

                                        <div>
                                            <select :name="'outputs[' + oIdx + '][item_id]'" x-model="out.item_id" required class="w-full h-9 text-xs">
                                                <option value="">-- Pilih Barang Target Output --</option>
                                                <template x-for="it in outputItemsList" :key="it.id">
                                                    <option :value="it.id" x-text="it.label"></option>
                                                </template>
                                            </select>
                                        </div>

                                        {{-- Jika Output > 1 (MULTI-OUTPUT / KOMBINASI), Qty Usage tiap Input diisi per Output di Kanan --}}
                                        <div x-show="formOutputs.length > 1" class="space-y-2 pt-1 border-t border-slate-100">
                                            <div class="text-[11px] font-semibold text-slate-600">
                                                Rasio Pemakaian Material untuk Output Ini (isi 0 jika pendamping tidak dipakai):
                                            </div>
                                            <template x-for="(inp, iIdx) in formInputs" :key="'u-' + oIdx + '-' + iIdx">
                                                <div class="p-2 rounded bg-slate-50 border border-slate-200">
                                                    <div class="flex items-center justify-between text-[11px] mb-1">
                                                        <span class="font-semibold text-slate-800" x-text="getItemName(inp.item_id, true) || ('Material Input #' + (iIdx + 1))"></span>
                                                        <span class="pill text-[10px] h-4 px-1.5"
                                                              :class="triggerIndex === iIdx ? 'pill-good' : 'pill-neu'"
                                                              x-text="triggerIndex === iIdx ? 'Trigger (Wajib > 0)' : 'Pendamping'"></span>
                                                    </div>
                                                    <input type="text"
                                                           :name="'outputs[' + oIdx + '][usages][' + iIdx + ']'"
                                                           x-model="out.usages[iIdx]"
                                                           :required="formOutputs.length > 1 && triggerIndex === iIdx"
                                                           placeholder="Contoh: 1 atau 1/2 atau 4"
                                                           class="num w-full h-8 text-xs font-semibold">
                                                    <div class="text-[10.5px] text-slate-500 mt-0.5" x-show="evalFormula(out.usages[iIdx]) !== null">
                                                        = <b class="num text-slate-900" x-text="evalFormula(out.usages[iIdx])"></b>
                                                        <span x-text="getItemUom(inp.item_id, true)"></span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="addOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Resep BOM</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function bomPage(initialGroups, initialRows, inputItemsList, outputItemsList, openInitially) {
            return {
                q: '',
                fProcess: '',
                fBomType: '',
                addOpen: openInitially,
                visibleGroups: initialGroups,
                inputItemsList: inputItemsList,
                outputItemsList: outputItemsList,
                triggerIndex: 0,
                formInputs: [{ item_id: '', qty_usage: '1' }],
                formOutputs: [{ item_id: '', usages: { 0: '1' } }],

                get detectedType() {
                    const i = this.formInputs.length;
                    const o = this.formOutputs.length;
                    if (i === 1 && o === 1) return 'SINGLE (1:1)';
                    if (i > 1 && o === 1) return 'MULTI-INPUT';
                    if (i === 1 && o > 1) return 'MULTI-OUTPUT';
                    return 'KOMBINASI';
                },
                addInput() {
                    const newIdx = this.formInputs.length;
                    this.formInputs.push({ item_id: '', qty_usage: '1' });
                    this.formOutputs.forEach(o => {
                        if (o.usages[newIdx] === undefined) o.usages[newIdx] = '1';
                    });
                },
                removeInput(idx) {
                    if (this.formInputs.length <= 1) return;
                    this.formInputs.splice(idx, 1);
                    if (this.triggerIndex >= this.formInputs.length) {
                        this.triggerIndex = 0;
                    }
                },
                addOutput() {
                    const usageInit = {};
                    this.formInputs.forEach((inp, idx) => {
                        usageInit[idx] = inp.qty_usage || '1';
                    });
                    this.formOutputs.push({ item_id: '', usages: usageInit });
                },
                removeOutput(idx) {
                    if (this.formOutputs.length <= 1) return;
                    this.formOutputs.splice(idx, 1);
                },
                getItemName(id, isInput) {
                    const list = isInput ? this.inputItemsList : this.outputItemsList;
                    const f = list.find(x => String(x.id) === String(id));
                    return f ? f.name : '';
                },
                getItemUom(id, isInput) {
                    const list = isInput ? this.inputItemsList : this.outputItemsList;
                    const f = list.find(x => String(x.id) === String(id));
                    return f ? f.uom : '';
                },
                evalFormula(val) {
                    if (val === undefined || val === null || String(val).trim() === '') return null;
                    const expr = String(val).replace(/,/g, '.').replace(/\s+/g, '');
                    if (!/^[0-9\.\+\-\*\/\(\)]+$/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r) && r >= 0) ? Math.round(r * 100000000) / 100000000 : null;
                    } catch (e) {
                        return null;
                    }
                },
                matchRow(el) {
                    const s = (el.dataset.search || '').toLowerCase();
                    const p = el.dataset.process || '';
                    const bt = el.dataset.bomtype || '';
                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.fProcess && p !== String(this.fProcess)) return false;
                    if (this.fBomType && bt !== this.fBomType) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-bom-row]').forEach(tr => {
                            if (this.matchRow(tr)) c++;
                        });
                        this.visibleGroups = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>