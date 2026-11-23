<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bill of Materials (BOM)"
                       subtitle="Struktur resep kebutuhan material & komponen per proses produksi (Single, Multi-Input, Multi-Output, & Kombinasi)">
            <x-slot name="actions">
                <button type="button" class="btn btn-primary" @click="$dispatch('open-add-bom')">
                    <x-icon name="plus" />
                    <span>Input BOM</span>
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
         @open-add-bom.window="addOpen = true; addError = ''"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Struktur BOM</span>
                <span class="count-chip"><span x-text="visibleGroups"></span><span>KELOMPOK</span></span>
                <span class="count-chip count-chip-sub">{{ $boms->count() }} BARIS</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Input / Output / BOM ID..."
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
                        <th class="w-48">Proses &amp; Tipe BOM</th>
                        <th>Material Pembentuk (Trigger &amp; Pendamping)</th>
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
                            <td class="text-center num font-semibold text-slate-500" data-col-no>{{ $idx + 1 }}</td>
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
                                    <span class="count-chip count-chip-sub num">{{ $grp['group_key'] }}</span>
                                </div>
                            </td>

                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['inputs'] as $inRow)
                                        @php
                                            $inIt = $inRow['item'];
                                            $inTy = $inIt ? ('ty-' . ($inIt->type === 'FG/KOMPONEN' ? 'FGK' : $inIt->type)) : 'ty-RM';
                                        @endphp
                                        <div class="px-3 py-2.5 flex items-center justify-between gap-2 {{ $inRow['is_trigger'] ? '' : 'bg-slate-50/60' }}">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                @if($inRow['is_trigger'])
                                                    <span class="pill pill-good w-[88px] justify-center flex-none">TRIGGER</span>
                                                @else
                                                    <span class="pill pill-neu w-[88px] justify-center flex-none">PENDAMPING</span>
                                                @endif
                                                <div class="min-w-0 leading-tight">
                                                    <div class="flex items-center gap-1.5">
                                                        @if($inIt)
                                                            <span class="ty {{ $inTy }}">{{ $inIt->type }}</span>
                                                        @endif
                                                        <span class="num text-[11.5px] font-bold text-accent truncate">{{ $inIt->part_no ?: '-' }}</span>
                                                    </div>
                                                    <div class="text-xs font-semibold text-slate-900 truncate mt-0.5">{{ $inIt->part_name ?? '-' }}</div>
                                                </div>
                                            </div>
                                            <span class="num text-[11px] font-semibold text-slate-500 flex-none">{{ $inIt->uom ?? '' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="p-0">
                                <div class="divide-y divide-slate-200">
                                    @foreach($grp['outputs'] as $outRow)
                                        @php
                                            $outIt = $outRow['item'];
                                            $outTy = $outIt ? ('ty-' . ($outIt->type === 'FG/KOMPONEN' ? 'FGK' : $outIt->type)) : 'ty-FG';
                                        @endphp
                                        <div class="px-3 py-2.5">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex items-center gap-1.5 min-w-0">
                                                    @if($outIt)
                                                        <span class="ty {{ $outTy }}">{{ $outIt->type }}</span>
                                                    @endif
                                                    <span class="num text-xs font-bold text-accent">{{ $outIt->part_no ?: '-' }}</span>
                                                    <span class="font-semibold text-slate-900 text-xs truncate">{{ $outIt->part_name ?? '-' }}</span>
                                                </div>
                                                <span class="count-chip count-chip-sub">OUTPUT: 1 {{ $outIt->uom ?? 'PCS' }}</span>
                                            </div>
                                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                                @foreach($outRow['inputs'] as $u)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] border {{ $u['is_trigger'] ? 'bg-blue-50/70 border-blue-200 text-blue-900 font-semibold' : 'bg-slate-50 border-slate-200 text-slate-700' }}">
                                                        <span>{{ $u['input_part_name'] }}:</span>
                                                        <b class="num">{{ $u['qty_usage_display'] }} {{ $u['input_uom'] }}</b>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="text-center">
                                <form action="{{ route('boms.destroy', $grp['first_id']) }}" method="POST" class="inline"
                                      @submit.prevent="$dispatch('open-confirm', { form: $el, title: 'Hapus Kelompok BOM', message: {{ Js::from('Hapus seluruh resep BOM pada kelompok ' . $grp['group_key'] . '?') }} })">
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

        {{-- POP-UP MODAL INPUT BOM: ALUR NODE (Input -> Proses -> Output) --}}
        <div x-show="addOpen" x-cloak class="modal-backdrop" @click.self="addOpen = false" @keydown.escape.window="addOpen = false">
            <div class="panel dlg w-full max-w-5xl" role="dialog" aria-label="Input Resep BOM Baru">
                <div class="panel-head">
                    <div class="flex items-center gap-2.5">
                        <h3 class="panel-title">Input Resep BOM Baru</h3>
                        <span class="pill pill-info" x-text="'DETEKSI OTOMATIS: ' + detectedType"></span>
                    </div>
                    <button type="button" class="act-btn" @click="addOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('boms.store') }}" method="POST" novalidate
                      @submit.prevent="submitAdd($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    <input type="hidden" name="trigger_index" :value="triggerIndex">

                    <div class="dlg-main flex-1 min-h-0">
                        <div x-show="addError" x-cloak class="alert alert-crit text-xs" role="alert">
                            <x-icon name="alert" />
                            <span x-text="addError"></span>
                        </div>

                        <div class="flow3" x-ref="flow3"
                             @input="$nextTick(() => drawLinks())"
                             @change="$nextTick(() => drawLinks())"
                             @resize.window="drawLinks()">
                            <svg class="flow3-links" x-ref="links" aria-hidden="true"></svg>

                            {{-- KIRI: MATERIAL INPUT --}}
                            <div class="fcol">
                                <div class="fh3">
                                    <span>Material Pembentuk (Input)</span>
                                    <button type="button" class="btn btn-sm btn-primary" @click="addInput()">
                                        <x-icon name="plus" />
                                        <span>Input Material</span>
                                    </button>
                                </div>

                                <template x-for="(inp, iIdx) in formInputs" :key="iIdx">
                                    <div class="nodec inp" data-n="in">
                                        <div class="rr">
                                            <label class="trg">
                                                <input type="radio" name="trigger_radio" :checked="triggerIndex === iIdx" @change="triggerIndex = iIdx">
                                                <span class="pill"
                                                      :class="triggerIndex === iIdx ? 'pill-good' : 'pill-neu'"
                                                      x-text="triggerIndex === iIdx ? 'TRIGGER' : 'PENDAMPING'"></span>
                                            </label>
                                            <button type="button" x-show="formInputs.length > 1" @click="removeInput(iIdx)" class="link-act danger">Hapus</button>
                                        </div>

                                        <select :name="'inputs[' + iIdx + '][item_id]'" x-model="inp.item_id" required class="w-full h-9 text-xs">
                                            <option value="">-- Pilih Material / Komponen Input --</option>
                                            <template x-for="it in inputItemsList" :key="it.id">
                                                <option :value="it.id" x-text="it.label"></option>
                                            </template>
                                        </select>

                                        {{-- Output hanya 1 (SINGLE / MULTI-INPUT): Qty Usage diisi di sini --}}
                                        <div x-show="formOutputs.length === 1">
                                            <div class="inp-calc">
                                                <input type="text"
                                                       :name="'inputs[' + iIdx + '][qty_usage]'"
                                                       x-model="inp.qty_usage"
                                                       :required="formOutputs.length === 1"
                                                       aria-label="Qty Usage"
                                                       placeholder="Qty Usage (contoh: 1/3, 0.7, 2)"
                                                       class="num w-full h-9 text-xs font-semibold">
                                                <span class="inp-calc-ic" title="Mendukung perhitungan kalkulator (+, -, *, /)">
                                                    <x-icon name="calc" />
                                                </span>
                                            </div>
                                            <div class="calc-preview" x-show="evalFormula(inp.qty_usage) !== null">
                                                Hasil: <b x-text="evalFormula(inp.qty_usage)"></b>
                                                <span x-text="(getItemUom(inp.item_id, true) ? getItemUom(inp.item_id, true) + ' ' : '') + 'per 1 ' + (getItemUom(formOutputs[0]?.item_id, false) || 'Output')"></span>
                                            </div>
                                            <div class="calc-err" x-show="String(inp.qty_usage || '').trim() !== '' && evalFormula(inp.qty_usage) === null" x-cloak>
                                                <x-icon name="alert" />
                                                <span>Format perhitungan tidak valid</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- TENGAH: NODE PROSES --}}
                            <div class="fmid">
                                <div class="pnode" data-n="proc">
                                    <div class="dlg-k">Proses Manufaktur</div>
                                    <select name="process_id" x-model="addProcessId" required aria-label="Proses manufaktur" class="w-full h-9 text-xs">
                                        <option value="">-- Pilih Proses --</option>
                                        @foreach($processes as $proc)
                                            <option value="{{ $proc->id }}">{{ $proc->process_name }}</option>
                                        @endforeach
                                    </select>
                                    <span class="pill pill-info" x-text="detectedType"></span>
                                </div>
                            </div>

                            {{-- KANAN: TARGET OUTPUT --}}
                            <div class="fcol">
                                <div class="fh3">
                                    <span>Target Output</span>
                                    <button type="button" class="btn btn-sm btn-primary" @click="addOutput()">
                                        <x-icon name="plus" />
                                        <span>Input Output</span>
                                    </button>
                                </div>

                                <template x-for="(out, oIdx) in formOutputs" :key="oIdx">
                                    <div class="nodec out" data-n="out">
                                        <div class="rr">
                                            <b class="text-xs text-accent uppercase" x-text="'OUTPUT #' + (oIdx + 1)"></b>
                                            <button type="button" x-show="formOutputs.length > 1" @click="removeOutput(oIdx)" class="link-act danger">Hapus</button>
                                        </div>

                                        <select :name="'outputs[' + oIdx + '][item_id]'" x-model="out.item_id" required class="w-full h-9 text-xs">
                                            <option value="">-- Pilih Barang Target Output --</option>
                                            <template x-for="it in outputItemsList" :key="it.id">
                                                <option :value="it.id" x-text="it.label"></option>
                                            </template>
                                        </select>

                                        {{-- Output lebih dari 1 (MULTI-OUTPUT / KOMBINASI): rasio tiap material diisi per output --}}
                                        <div x-show="formOutputs.length > 1" class="grid gap-2 pt-1 border-t border-slate-100">
                                            <div class="hasil">Rasio pemakaian material untuk output ini (isi 0 bila pendamping tidak dipakai):</div>
                                            <template x-for="(inp, iIdx) in formInputs" :key="'u-' + oIdx + '-' + iIdx">
                                                <div>
                                                    <div class="flex items-center justify-between gap-1 mb-1">
                                                        <span class="hasil font-semibold text-slate-700"
                                                              x-text="getItemName(inp.item_id, true) || ('Material Input #' + (iIdx + 1))"></span>
                                                        <span class="pill"
                                                              :class="triggerIndex === iIdx ? 'pill-good' : 'pill-neu'"
                                                              x-text="triggerIndex === iIdx ? 'TRIGGER' : 'PENDAMPING'"></span>
                                                    </div>
                                                    <div class="inp-calc">
                                                        <input type="text"
                                                               :name="'outputs[' + oIdx + '][usages][' + iIdx + ']'"
                                                               x-model="out.usages[iIdx]"
                                                               :required="formOutputs.length > 1 && triggerIndex === iIdx"
                                                               aria-label="Rasio pemakaian"
                                                               placeholder="Contoh: 1/2, 0.7, atau 4"
                                                               class="num w-full h-8 text-xs font-semibold">
                                                        <span class="inp-calc-ic" title="Mendukung perhitungan kalkulator (+, -, *, /)">
                                                            <x-icon name="calc" />
                                                        </span>
                                                    </div>
                                                    <div class="calc-preview" x-show="evalFormula(out.usages[iIdx]) !== null">
                                                        Hasil: <b x-text="evalFormula(out.usages[iIdx])"></b>
                                                        <span x-text="getItemUom(inp.item_id, true)"></span>
                                                    </div>
                                                    <div class="calc-err" x-show="String(out.usages[iIdx] || '').trim() !== '' && evalFormula(out.usages[iIdx]) === null" x-cloak>
                                                        <x-icon name="alert" />
                                                        <span>Format perhitungan tidak valid</span>
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
                addProcessId: '',
                addError: '',
                visibleGroups: initialGroups,
                inputItemsList: inputItemsList,
                outputItemsList: outputItemsList,
                triggerIndex: 0,
                formInputs: [{ item_id: '', qty_usage: '1' }],
                formOutputs: [{ item_id: '', usages: { 0: '1' } }],

                init() {
                    this.$watch('addOpen', (open) => {
                        if (open) this.$nextTick(() => this.drawLinks());
                    });
                    this.$watch('addError', () => this.$nextTick(() => this.drawLinks()));
                    this.$watch('formInputs', () => this.$nextTick(() => this.drawLinks()));
                    this.$watch('formOutputs', () => this.$nextTick(() => this.drawLinks()));
                    if (window.ResizeObserver && this.$refs.flow3) {
                        new ResizeObserver(() => this.drawLinks()).observe(this.$refs.flow3);
                    }
                },
                drawLinks() {
                    const f = this.$refs.flow3;
                    const svg = this.$refs.links;
                    if (!f || !svg || f.offsetParent === null) return;
                    if (getComputedStyle(svg).display === 'none') return;

                    const fr = f.getBoundingClientRect();
                    svg.setAttribute('width', fr.width);
                    svg.setAttribute('height', fr.height);
                    svg.replaceChildren();

                    const proc = f.querySelector('[data-n="proc"]');
                    if (!proc) return;
                    const P = proc.getBoundingClientRect();
                    const py = P.top - fr.top + P.height / 2;
                    const pl = P.left - fr.left;
                    const pr = P.right - fr.left;
                    const NS = 'http://www.w3.org/2000/svg';
                    const add = (d) => {
                        const p = document.createElementNS(NS, 'path');
                        p.setAttribute('d', d);
                        svg.appendChild(p);
                    };

                    f.querySelectorAll('[data-n="in"]').forEach((el) => {
                        const r = el.getBoundingClientRect();
                        const x = r.right - fr.left;
                        const y = r.top - fr.top + Math.min(26, r.height / 2);
                        add(`M${x} ${y} C ${x + 30} ${y}, ${pl - 30} ${py}, ${pl} ${py}`);
                    });
                    f.querySelectorAll('[data-n="out"]').forEach((el) => {
                        const r = el.getBoundingClientRect();
                        const x = r.left - fr.left;
                        const y = r.top - fr.top + Math.min(26, r.height / 2);
                        add(`M${pr} ${py} C ${pr + 30} ${py}, ${x - 30} ${y}, ${x} ${y}`);
                    });
                },
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
                        if (!o.usages) o.usages = {};
                        if (o.usages[newIdx] === undefined) o.usages[newIdx] = '1';
                    });
                    this.$nextTick(() => this.drawLinks());
                },
                removeInput(idx) {
                    if (this.formInputs.length <= 1) return;
                    this.formInputs.splice(idx, 1);
                    this.formOutputs.forEach(o => {
                        const nextUsages = {};
                        this.formInputs.forEach((_, newIdx) => {
                            const oldIdx = newIdx >= idx ? newIdx + 1 : newIdx;
                            nextUsages[newIdx] = (o.usages && o.usages[oldIdx] !== undefined) ? o.usages[oldIdx] : '1';
                        });
                        o.usages = nextUsages;
                    });
                    if (this.triggerIndex === idx) {
                        this.triggerIndex = 0;
                    } else if (this.triggerIndex > idx) {
                        this.triggerIndex--;
                    }
                    if (this.triggerIndex >= this.formInputs.length) {
                        this.triggerIndex = 0;
                    }
                    this.$nextTick(() => this.drawLinks());
                },
                addOutput() {
                    if (this.formOutputs.length === 1) {
                        const firstUsages = {};
                        this.formInputs.forEach((inp, idx) => {
                            firstUsages[idx] = (inp.qty_usage !== undefined && String(inp.qty_usage).trim() !== '') ? inp.qty_usage : '1';
                        });
                        this.formOutputs[0].usages = firstUsages;
                    }
                    const usageInit = {};
                    this.formInputs.forEach((inp, idx) => {
                        usageInit[idx] = (inp.qty_usage !== undefined && String(inp.qty_usage).trim() !== '') ? inp.qty_usage : '1';
                    });
                    this.formOutputs.push({ item_id: '', usages: usageInit });
                    this.$nextTick(() => this.drawLinks());
                },
                removeOutput(idx) {
                    if (this.formOutputs.length <= 1) return;
                    this.formOutputs.splice(idx, 1);
                    if (this.formOutputs.length === 1) {
                        const remaining = this.formOutputs[0];
                        this.formInputs.forEach((inp, iIdx) => {
                            if (remaining.usages && remaining.usages[iIdx] !== undefined && String(remaining.usages[iIdx]).trim() !== '') {
                                inp.qty_usage = remaining.usages[iIdx];
                            }
                        });
                    }
                    this.$nextTick(() => this.drawLinks());
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
                    const expr = String(val).replace(/,/g, '.').replace(/[xX]/g, '*').replace(/:/g, '/').replace(/\s+/g, '');
                    if (!/^[0-9\.\+\-\*\/\(\)]+$/.test(expr)) return null;
                    if (/[\+\-\*\/]{2,}/.test(expr)) return null;
                    try {
                        const r = Function('return (' + expr + ')')();
                        return (typeof r === 'number' && isFinite(r) && r >= 0) ? Math.round(r * 100000000) / 100000000 : null;
                    } catch (e) {
                        return null;
                    }
                },
                submitAdd(formEl) {
                    this.addError = '';
                    if (!this.addProcessId) {
                        this.addError = 'Proses Manufaktur wajib dipilih.';
                        return;
                    }

                    const inIds = this.formInputs.map(i => String(i.item_id || '').trim());
                    const outIds = this.formOutputs.map(o => String(o.item_id || '').trim());

                    if (inIds.some(id => !id)) {
                        this.addError = 'Semua baris Material Pembentuk (Input) wajib dipilih.';
                        return;
                    }
                    if (outIds.some(id => !id)) {
                        this.addError = 'Semua baris Target Output wajib dipilih.';
                        return;
                    }
                    if (new Set(inIds).size !== inIds.length) {
                        this.addError = 'Terdapat Material Input yang dipilih ganda dalam 1 resep.';
                        return;
                    }
                    if (new Set(outIds).size !== outIds.length) {
                        this.addError = 'Terdapat Target Output yang dipilih ganda dalam 1 resep.';
                        return;
                    }
                    if (inIds.some(id => outIds.includes(id))) {
                        this.addError = 'Material Input dan Target Output tidak boleh item yang sama.';
                        return;
                    }

                    if (this.formOutputs.length === 1) {
                        for (let i = 0; i < this.formInputs.length; i++) {
                            const val = this.evalFormula(this.formInputs[i].qty_usage);
                            if (val === null || val <= 0) {
                                this.addError = 'Qty Usage pada setiap Material Input wajib berformat valid dan lebih besar dari 0.';
                                return;
                            }
                        }
                    } else {
                        for (let oIdx = 0; oIdx < this.formOutputs.length; oIdx++) {
                            const out = this.formOutputs[oIdx];
                            for (let iIdx = 0; iIdx < this.formInputs.length; iIdx++) {
                                const raw = out.usages ? out.usages[iIdx] : '';
                                const isTrig = (iIdx === this.triggerIndex);
                                if (isTrig) {
                                    const val = this.evalFormula(raw);
                                    if (val === null || val <= 0) {
                                        this.addError = 'Qty Usage untuk Material Trigger wajib valid dan lebih besar dari 0 pada setiap Target Output.';
                                        return;
                                    }
                                } else if (String(raw ?? '').trim() !== '') {
                                    const val = this.evalFormula(raw);
                                    if (val === null || val < 0) {
                                        this.addError = 'Format Qty Usage pada Material Pendamping tidak valid.';
                                        return;
                                    }
                                }
                            }
                        }
                    }

                    formEl.submit();
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
                            if (this.matchRow(tr)) {
                                c++;
                                const noCell = tr.querySelector('[data-col-no]');
                                if (noCell) noCell.textContent = c;
                            }
                        });
                        this.visibleGroups = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>