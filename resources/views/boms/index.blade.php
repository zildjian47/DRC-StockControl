<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bill of Materials (BOM)"
                       subtitle=" Pengaturan resep kebutuhan material input, trigger utama, dan target output produksi">
            <x-slot name="actions">
                <button type="button" class="btn btn-primary" @click="$dispatch('open-bom-modal')">
                    <x-icon name="plus" /> Buat BOM
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    @php
        $inputCatalog = $inputItems->map(fn ($i) => [
            'id'    => $i->id,
            'no'    => $i->part_no ?: '-',
            'name'  => $i->part_name,
            'type'  => $i->type,
            'uom'   => $i->uom,
            'label' => ($i->part_no ? $i->part_no . ' · ' : '') . $i->part_name . ' (' . $i->type . ' - ' . $i->uom . ')',
        ])->values();

        $outputCatalog = $outputItems->map(fn ($i) => [
            'id'    => $i->id,
            'no'    => $i->part_no ?: '-',
            'name'  => $i->part_name,
            'type'  => $i->type,
            'uom'   => $i->uom,
            'label' => ($i->part_no ? $i->part_no . ' · ' : '') . $i->part_name . ' (' . $i->type . ' - ' . $i->uom . ')',
        ])->values();
    @endphp

    <div class="panel"
         x-data="{
            q: '',
            fProcess: '',
            fType: '',
            visibleGroups: {{ $groupedBoms->count() }},
            matchGroup(el) {
                const s = (el.dataset.search || '').toLowerCase();
                const p = el.dataset.process || '';
                const t = el.dataset.type || '';
                const mq = !this.q || s.includes(this.q.toLowerCase());
                const mp = !this.fProcess || p === String(this.fProcess);
                const mt = !this.fType || t === this.fType;
                return mq && mp && mt;
            },
            recalc() {
                this.$nextTick(() => {
                    let c = 0;
                    this.$el.querySelectorAll('tbody tr[data-bom-row]').forEach(tr => {
                        if (tr.style.display !== 'none') c++;
                    });
                    this.visibleGroups = c;
                });
            }
         }"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Struktur BOM</span>
                <span class="count-chip"><span x-text="visibleGroups"></span> Kelompok</span>
                <span class="count-chip count-chip-sub">{{ $boms->count() }} Baris</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <input type="text" x-model="q" @input="recalc()"
                           placeholder="Cari Part No / Part Name..."
                           class="h-8 text-xs pl-8 pr-3 w-56">
                    <x-icon name="search" class="w-3.5 h-3.5 absolute left-2.5 top-2 text-slate-400" />
                </div>

                <select x-model="fProcess" @change="recalc()" class="h-8 text-xs py-0 pl-2.5 pr-7">
                    <option value="">Semua Proses</option>
                    @foreach($processes as $proc)
                        <option value="{{ $proc->id }}">{{ $proc->process_name }}</option>
                    @endforeach
                </select>

                <select x-model="fType" @change="recalc()" class="h-8 text-xs py-0 pl-2.5 pr-7">
                    <option value="">Semua Tipe BOM</option>
                    <option value="SINGLE">SINGLE</option>
                    <option value="MULTI-INPUT">MULTI-INPUT</option>
                    <option value="MULTI-OUTPUT">MULTI-OUTPUT</option>
                    <option value="KOMBINASI">KOMBINASI</option>
                </select>

                <button type="button" x-show="q || fProcess || fType" x-cloak
                        @click="q=''; fProcess=''; fType=''; recalc()"
                        class="btn btn-sm">
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
                        <th>Target Output &amp; Qty Usage</th>
                        <th class="w-16 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groupedBoms as $idx => $grp)
                        <tr data-bom-row
                            data-search="{{ $grp['search_index'] }}"
                            data-process="{{ $grp['process_id'] }}"
                            data-type="{{ $grp['type'] }}"
                            x-show="matchGroup($el)">
                            <td class="text-center num font-semibold text-slate-500">{{ $idx + 1 }}</td>
                            <td>
                                <div class="font-semibold text-slate-900">{{ $grp['process_name'] }}</div>
                                <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                                    @if($grp['type'] === 'SINGLE')
                                        <span class="pill pill-neu">SINGLE</span>
                                    @elseif($grp['type'] === 'MULTI-INPUT')
                                        <span class="pill pill-info">MULTI-INPUT</span>
                                    @elseif($grp['type'] === 'MULTI-OUTPUT')
                                        <span class="pill pill-warn">MULTI-OUTPUT</span>
                                    @else
                                        <span class="pill pill-good">KOMBINASI</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="space-y-1.5">
                                    @foreach($grp['inputs'] as $inItem)
                                        @php $it = $inItem['input_item']; @endphp
                                        <div class="flex items-center gap-2">
                                            @if($inItem['is_trigger'])
                                                <span class="pill pill-good text-[10.5px] px-1.5 h-5">Trigger</span>
                                            @else
                                                <span class="pill pill-neu text-[10.5px] px-1.5 h-5">Pendamping</span>
                                            @endif
                                            <span class="num text-xs font-semibold text-accent">{{ $it->part_no ?: '-' }}</span>
                                            <span class="font-medium text-slate-800">{{ $it->part_name ?? '-' }}</span>
                                            <span class="text-xs text-slate-400">({{ $it->type ?? '' }} · {{ $it->uom ?? '' }})</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                <div class="space-y-2">
                                    @foreach($grp['outputs'] as $outGrp)
                                        @php $outIt = $outGrp['output_item']; @endphp
                                        <div class="p-2 rounded border border-slate-200 bg-slate-50/70">
                                            <div class="flex items-center justify-between gap-2">
                                                <div>
                                                    <span class="num text-xs font-semibold text-accent">{{ $outIt->part_no ?: '-' }}</span>
                                                    <span class="font-semibold text-slate-900 ml-1">{{ $outIt->part_name ?? '-' }}</span>
                                                    <span class="pill pill-neu ml-1.5 text-[10.5px] h-5">{{ $outIt->type ?? '-' }}</span>
                                                </div>
                                            </div>
                                            <div class="mt-1.5 flex flex-wrap gap-2">
                                                @foreach($outGrp['inputs'] as $bomLine)
                                                    <div class="inline-flex items-center gap-1.5 text-xs bg-white px-2 py-0.5 rounded border border-slate-200">
                                                        <span class="text-slate-600">{{ $bomLine->inputItem->part_name ?? '-' }}:</span>
                                                        <b class="num text-slate-900">{{ rtrim(rtrim(number_format((float) $bomLine->qty_usage, 8, '.', ''), '0'), '.') }}</b>
                                                        <span class="text-[11px] text-slate-500">{{ $bomLine->inputItem->uom ?? '' }} / 1 {{ $outIt->uom ?? 'PCS' }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-center">
                                <form action="{{ route('boms.destroy', $grp['first_id']) }}" method="POST"
                                      @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Hapus Resep BOM', message: 'Hapus seluruh kelompok resep BOM ini?' })">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="act-icon act-icon-danger" title="Hapus Kelompok BOM">
                                        <x-icon name="trash" class="w-4 h-4" />
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5"><div class="empty">Belum ada struktur BOM yang terdaftar.</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- POP-UP MODAL BUAT BOM 2 KOLOM (KIRI = INPUT, KANAN = OUTPUT) --}}
    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            processId: '{{ old('process_id', '') }}',
            triggerIndex: 0,
            inputCatalog: {{ Js::from($inputCatalog) }},
            outputCatalog: {{ Js::from($outputCatalog) }},
            inputs: [
                { input_item_id: '', search: '', dropdown: false, qty_usage: '1' }
            ],
            outputs: [
                { output_item_id: '', search: '', dropdown: false, usages: { 0: '1' } }
            ],
            get detectedType() {
                const ni = this.inputs.length;
                const no = this.outputs.length;
                if (ni === 1 && no === 1) return 'SINGLE (1 Input → 1 Output)';
                if (ni > 1 && no === 1) return 'MULTI-INPUT (' + ni + ' Input → 1 Output)';
                if (ni === 1 && no > 1) return 'MULTI-OUTPUT (1 Input → ' + no + ' Output)';
                return 'KOMBINASI (' + ni + ' Input → ' + no + ' Output)';
            },
            addInput() {
                const newIdx = this.inputs.length;
                this.inputs.push({ input_item_id: '', search: '', dropdown: false, qty_usage: '1' });
                this.outputs.forEach(o => {
                    if (!o.usages) o.usages = {};
                    o.usages[newIdx] = '1';
                });
            },
            removeInput(idx) {
                if (this.inputs.length <= 1) return;
                this.inputs.splice(idx, 1);
                if (this.triggerIndex === idx) {
                    this.triggerIndex = 0;
                } else if (this.triggerIndex > idx) {
                    this.triggerIndex--;
                }
                this.outputs.forEach(o => {
                    const nextUsages = {};
                    this.inputs.forEach((_, i) => {
                        const oldKey = i >= idx ? i + 1 : i;
                        nextUsages[i] = o.usages[oldKey] ?? '1';
                    });
                    o.usages = nextUsages;
                });
            },
            addOutput() {
                const u = {};
                this.inputs.forEach((inp, i) => {
                    u[i] = inp.qty_usage || '1';
                });
                this.outputs.push({ output_item_id: '', search: '', dropdown: false, usages: u });
            },
            removeOutput(idx) {
                if (this.outputs.length <= 1) return;
                this.outputs.splice(idx, 1);
            },
            getInputObj(id) {
                return this.inputCatalog.find(x => String(x.id) === String(id)) || null;
            },
            getOutputObj(id) {
                return this.outputCatalog.find(x => String(x.id) === String(id)) || null;
            },
            evalFormula(val) {
                if (!val) return null;
                const expr = String(val).replace(/,/g, '.').replace(/\s+/g, '');
                if (!/^[0-9\.\+\-\*\/\(\)]+$/.test(expr)) return null;
                try {
                    const res = Function('\"use strict\"; return (' + expr + ')')();
                    if (typeof res === 'number' && isFinite(res) && res >= 0) {
                        return Math.round(res * 100000000) / 100000000;
                    }
                } catch (e) {}
                return null;
            }
         }"
         @open-bom-modal.window="open = true"
         x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 !mt-0"
         @click.self="open = false">

        <div class="panel w-full max-w-5xl max-h-[92vh] flex flex-col overflow-hidden">
            <div class="panel-head">
                <div class="flex items-center gap-3 flex-wrap">
                    <h3 class="panel-title">Buat Resep BOM Baru</h3>
                    <span class="pill pill-info" x-text="'Deteksi Otomatis: ' + detectedType"></span>
                </div>
                <button type="button" class="btn btn-sm" @click="open = false">Tutup</button>
            </div>

            <form action="{{ route('boms.store') }}" method="POST" class="flex-1 overflow-y-auto">
                @csrf
                <input type="hidden" name="trigger_index" :value="triggerIndex">

                <div class="panel-body space-y-5">
                    {{-- Pilih Proses --}}
                    <div>
                        <label class="lbl">Proses Manufaktur</label>
                        <select name="process_id" x-model="processId" required class="w-full h-10">
                            <option value="">-- Pilih Proses --</option>
                            @foreach($processes as $proc)
                                <option value="{{ $proc->id }}">{{ $proc->process_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Grid 2 Kolom: Kiri = Material Input, Kanan = Target Output --}}
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">

                        {{-- KOLOM KIRI: MATERIAL INPUT --}}
                        <div class="border border-slate-200 rounded bg-slate-50/60 p-3.5 space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-200 pb-2.5">
                                <div>
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Kolom Kiri · Material Input</h4>
                                    <p class="text-[11.5px] text-slate-500">Pilih bahan baku/komponen dan tandai 1 Trigger Utama</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" @click="addInput()">
                                    <x-icon name="plus" class="w-3.5 h-3.5" /> Tambah Input
                                </button>
                            </div>

                            <template x-for="(inp, iIdx) in inputs" :key="iIdx">
                                <div class="p-3 rounded bg-white border border-slate-200 space-y-2.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="inline-flex items-center gap-2 text-xs font-semibold cursor-pointer"
                                               :class="triggerIndex === iIdx ? 'text-emerald-700' : 'text-slate-600'">
                                            <input type="radio" name="trigger_radio_ui" :checked="triggerIndex === iIdx" @change="triggerIndex = iIdx">
                                            <span x-text="triggerIndex === iIdx ? 'Input #' + (iIdx + 1) + ' · TRIGGER UTAMA' : 'Input #' + (iIdx + 1) + ' · Jadikan Trigger'"></span>
                                        </label>

                                        <button type="button" x-show="inputs.length > 1" @click="removeInput(iIdx)"
                                                class="text-xs text-rose-600 hover:underline font-medium">
                                            Hapus
                                        </button>
                                    </div>

                                    {{-- Searchable Dropdown Input Item --}}
                                    <div class="relative" @click.outside="inp.dropdown = false">
                                        <input type="hidden" :name="'inputs[' + iIdx + '][input_item_id]'" :value="inp.input_item_id" required>
                                        <input type="text"
                                               x-model="inp.search"
                                               @focus="inp.dropdown = true"
                                               @input="inp.dropdown = true; inp.input_item_id = ''"
                                               placeholder="Ketik Part No / Nama Material Input..."
                                               class="w-full h-9 text-xs">
                                        <div x-show="inp.dropdown" x-cloak
                                             class="absolute z-30 mt-1 w-full bg-white border border-slate-300 rounded shadow-lg max-h-48 overflow-y-auto">
                                            <template x-for="item in inputCatalog.filter(x => !inp.search || x.label.toLowerCase().includes(inp.search.toLowerCase()))" :key="item.id">
                                                <div @click="inp.input_item_id = item.id; inp.search = item.label; inp.dropdown = false"
                                                     class="px-3 py-2 text-xs hover:bg-blue-50 cursor-pointer border-b border-slate-100">
                                                    <div class="font-semibold text-slate-900" x-text="item.name"></div>
                                                    <div class="text-[11px] text-slate-500" x-text="item.no + ' · ' + item.type + ' (' + item.uom + ')'"></div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Jika Output hanya 1 (SINGLE / MULTI-INPUT), isi Qty Usage langsung di Kiri --}}
                                    <div x-show="outputs.length === 1">
                                        <label class="lbl text-[11px]">Qty Usage (Kalkulator: contoh 1/10, 0.5, atau 2)</label>
                                        <input type="text"
                                               :name="'inputs[' + iIdx + '][qty_usage]'"
                                               x-model="inp.qty_usage"
                                               :required="outputs.length === 1"
                                               placeholder="Contoh: 1/10 atau 2"
                                               class="num w-full h-9 text-xs">
                                        <div class="text-[11px] text-slate-500 mt-1" x-show="evalFormula(inp.qty_usage) !== null">
                                            Hasil: <b class="num text-slate-800" x-text="evalFormula(inp.qty_usage)"></b>
                                            <span x-text="getInputObj(inp.input_item_id)?.uom || 'Unit'"></span>
                                            per 1 <span x-text="getOutputObj(outputs[0]?.output_item_id)?.uom || 'PCS'"></span> Output
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- KOLOM KANAN: TARGET OUTPUT --}}
                        <div class="border border-slate-200 rounded bg-slate-50/60 p-3.5 space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-200 pb-2.5">
                                <div>
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Kolom Kanan · Target Output</h4>
                                    <p class="text-[11.5px] text-slate-500">Pilih part hasil produksi (bisa 1 atau lebih sekaligus)</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" @click="addOutput()">
                                    <x-icon name="plus" class="w-3.5 h-3.5" /> Tambah Output
                                </button>
                            </div>

                            <template x-for="(out, oIdx) in outputs" :key="oIdx">
                                <div class="p-3 rounded bg-white border border-slate-200 space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold text-slate-700" x-text="'Target Output #' + (oIdx + 1)"></span>
                                        <button type="button" x-show="outputs.length > 1" @click="removeOutput(oIdx)"
                                                class="text-xs text-rose-600 hover:underline font-medium">
                                            Hapus
                                        </button>
                                    </div>

                                    {{-- Searchable Dropdown Output Item --}}
                                    <div class="relative" @click.outside="out.dropdown = false">
                                        <input type="hidden" :name="'outputs[' + oIdx + '][output_item_id]'" :value="out.output_item_id" required>
                                        <input type="text"
                                               x-model="out.search"
                                               @focus="out.dropdown = true"
                                               @input="out.dropdown = true; out.output_item_id = ''"
                                               placeholder="Ketik Part No / Nama Target Output..."
                                               class="w-full h-9 text-xs">
                                        <div x-show="out.dropdown" x-cloak
                                             class="absolute z-30 mt-1 w-full bg-white border border-slate-300 rounded shadow-lg max-h-48 overflow-y-auto">
                                            <template x-for="item in outputCatalog.filter(x => !out.search || x.label.toLowerCase().includes(out.search.toLowerCase()))" :key="item.id">
                                                <div @click="out.output_item_id = item.id; out.search = item.label; out.dropdown = false"
                                                     class="px-3 py-2 text-xs hover:bg-blue-50 cursor-pointer border-b border-slate-100">
                                                    <div class="font-semibold text-slate-900" x-text="item.name"></div>
                                                    <div class="text-[11px] text-slate-500" x-text="item.no + ' · ' + item.type + ' (' + item.uom + ')'"></div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Jika Output >= 2 (MULTI-OUTPUT / KOMBINASI), tampilkan Qty Usage tiap Input pada masing-masing Output --}}
                                    <div x-show="outputs.length > 1" class="pt-2 border-t border-slate-100 space-y-2">
                                        <div class="text-[11px] font-semibold text-slate-600">
                                            Kebutuhan Material untuk 1 <span x-text="getOutputObj(out.output_item_id)?.uom || 'PCS'"></span> Output Ini:
                                        </div>

                                        <template x-for="(inp, iIdx) in inputs" :key="'u-' + oIdx + '-' + iIdx">
                                            <div class="grid grid-cols-12 gap-2 items-center bg-slate-50 p-2 rounded border border-slate-200/80">
                                                <div class="col-span-7 text-xs truncate">
                                                    <span class="font-semibold"
                                                          :class="triggerIndex === iIdx ? 'text-emerald-700' : 'text-slate-700'"
                                                          x-text="triggerIndex === iIdx ? '[Trigger] ' : '[Pendamping] '"></span>
                                                    <span x-text="getInputObj(inp.input_item_id)?.name || ('Input #' + (iIdx + 1))"></span>
                                                </div>
                                                <div class="col-span-5">
                                                    <input type="text"
                                                           :name="'outputs[' + oIdx + '][usages][' + iIdx + ']'"
                                                           x-model="out.usages[iIdx]"
                                                           :placeholder="triggerIndex === iIdx ? 'Contoh: 1 atau 1/2' : 'Isi 0 jika tidak pakai'"
                                                           class="num w-full h-8 text-xs">
                                                    <div class="text-[10.5px] text-slate-500 mt-0.5" x-show="evalFormula(out.usages[iIdx]) !== null">
                                                        = <b class="num text-slate-800" x-text="evalFormula(out.usages[iIdx])"></b>
                                                        <span x-text="getInputObj(inp.input_item_id)?.uom || ''"></span>
                                                    </div>
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
                    <button type="button" class="btn" @click="open = false">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Resep BOM</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>