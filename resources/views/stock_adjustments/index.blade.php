<x-app-layout>
    @php
        $types = $types ?? ['STOCK OPNAME', 'LOSS', 'SURPLUS', 'FOUND', 'REPAIR'];
        $itemStocks = $items->mapWithKeys(function ($i) {
            return [
                $i->id => [
                    'stock' => $i->inventoryStock ? (float) $i->inventoryStock->current_stock : 0.0,
                    'uom'   => $i->uom,
                ]
            ];
        });
    @endphp

    <div x-data="{
            openCreate: {{ $errors->any() ? 'true' : 'false' }},
            stocks: {{ Js::from($itemStocks) }},
            selectedItemId: '{{ old('item_id', '') }}',
            adjType: '{{ old('adjustment_type', 'STOCK OPNAME') }}',
            qtySystem: 0,
            qtyActual: '',
            qtyDiff: '',
            uom: '',
            startDate: '{{ request('start_date') }}',
            endDate: '{{ request('end_date') }}',

            evalMathSigned(raw) {
                if (raw === null || raw === undefined || String(raw).trim() === '') return null;
                let expr = String(raw).replace(/,/g, '.').replace(/[xX]/g, '*').replace(/:/g, '/').replace(/\s+/g, '');
                if (!expr || !/^[0-9.+\-*\/()]+$/.test(expr)) return null;
                let tokens = expr.match(/\d+(?:\.\d+)?|[+\-*\/()]/g);
                if (!tokens || tokens.join('') !== expr) return null;
                let prec = { '+': 1, '-': 1, '*': 2, '/': 2 };
                let out = [], ops = [];
                for (let i = 0; i < tokens.length; i++) {
                    let t = tokens[i];
                    if (!isNaN(t)) out.push(parseFloat(t));
                    else if (t === '(') ops.push(t);
                    else if (t === ')') {
                        while (ops.length && ops[ops.length - 1] !== '(') out.push(ops.pop());
                        if (!ops.length) return null;
                        ops.pop();
                    } else if (prec[t]) {
                        if ((t === '-' || t === '+') && (i === 0 || ['(', '+', '-', '*', '/'].includes(tokens[i - 1]))) {
                            out.push(0);
                        }
                        while (ops.length && ops[ops.length - 1] !== '(' && prec[ops[ops.length - 1]] >= prec[t]) out.push(ops.pop());
                        ops.push(t);
                    }
                }
                while (ops.length) {
                    let op = ops.pop();
                    if (op === '(' || op === ')') return null;
                    out.push(op);
                }
                let stack = [];
                for (let tk of out) {
                    if (typeof tk === 'number') stack.push(tk);
                    else {
                        if (stack.length < 2) return null;
                        let b = stack.pop(), a = stack.pop();
                        if (tk === '+') stack.push(a + b);
                        else if (tk === '-') stack.push(a - b);
                        else if (tk === '*') stack.push(a * b);
                        else if (tk === '/') {
                            if (Math.abs(b) < 1e-12) return null;
                            stack.push(a / b);
                        }
                    }
                }
                if (stack.length !== 1 || !isFinite(stack[0])) return null;
                return Math.round(stack[0] * 100) / 100;
            },
            onSelectItem() {
                if (this.selectedItemId && this.stocks[this.selectedItemId]) {
                    this.qtySystem = parseFloat(this.stocks[this.selectedItemId].stock) || 0;
                    this.uom = this.stocks[this.selectedItemId].uom || '';
                    this.qtyActual = String(this.qtySystem);
                    this.qtyDiff = '0';
                } else {
                    this.qtySystem = 0;
                    this.uom = '';
                    this.qtyActual = '';
                    this.qtyDiff = '';
                }
            },
            onActualChange() {
                this.qtyActual = String(this.qtyActual).replace(/,/g, '.');
                let act = this.evalMathSigned(this.qtyActual);
                if (act === null) { this.qtyDiff = ''; return; }
                let d = Math.round((act - this.qtySystem) * 100) / 100;
                this.qtyDiff = String(d);
            },
            onDiffChange() {
                this.qtyDiff = String(this.qtyDiff).replace(/,/g, '.');
                let d = this.evalMathSigned(this.qtyDiff);
                if (d === null) { this.qtyActual = ''; return; }
                let act = Math.round((this.qtySystem + d) * 100) / 100;
                this.qtyActual = String(Math.max(0, act));
            },
            calcPreviewText() {
                if (!this.selectedItemId) return 'Pilih barang terlebih dahulu untuk melihat kalkulasi.';
                let act = this.evalMathSigned(this.qtyActual);
                let diff = this.evalMathSigned(this.qtyDiff);
                if (act === null || diff === null) return 'Ketik angka atau rumus kalkulator (+, -, *, / seperti 3*50+12).';
                let sign = diff > 0 ? '+' : '';
                let u = this.uom ? ' ' + this.uom : '';
                return 'Hasil Qty Actual = ' + act + u + ' | Selisih (Qty Diff) = ' + sign + diff + u + ' (Stok Sistem: ' + this.qtySystem + u + ' → Menjadi: ' + act + u + ')';
            }
         }" class="space-y-[18px]">

        <div class="page-head">
            <x-page-header title="Stock Adjustment"
                           subtitle="Penyesuaian saldo stok fisik vs sistem dengan Kalkulator Pintar (2 Jalur Input: Desktop & Mobile Scanner)">
                <x-slot name="actions">
                    <a href="{{ route('stock_adjustments.index', ['mode' => 'scanner']) }}" class="btn">
                        <x-icon name="scan" /> Buka Mobile Scanner
                    </a>
                    <button type="button" @click="openCreate = true" class="btn btn-primary">
                        <x-icon name="plus" /> Input Adjustment
                    </button>
                </x-slot>
            </x-page-header>
        </div>

        <x-flash />

        <div class="panel">
            <div class="panel-head">
                <h3 class="panel-title">Riwayat Stock Adjustment ({{ $adjustments->count() }} Data)</h3>

                <form action="{{ route('stock_adjustments.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Cari Part No / Part Name..."
                           class="filter-input w-48">

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

                    <select name="adjustment_type" class="filter-input w-40">
                        <option value="">Semua Tipe</option>
                        @foreach ($types as $t)
                            <option value="{{ $t }}" @selected(request('adjustment_type') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th class="w-14 text-center">No</th>
                            <th class="text-left">Waktu</th>
                            <th>Petugas</th>
                            <th>Part No &amp; Part Name</th>
                            <th class="text-center">Adjustment Type</th>
                            <th class="num">Qty System</th>
                            <th class="num">Qty Actual</th>
                            <th class="num">Qty Diff</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($adjustments as $adj)
                            <tr>
                                <td class="num text-center text-slate-500 font-semibold">{{ $loop->iteration }}</td>
                                <td class="num text-left col-time whitespace-nowrap text-xs text-slate-600">
                                    {{ \Carbon\Carbon::parse($adj->date_time)->format('d M Y, H:i') }}
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="font-semibold text-slate-900 text-xs">{{ $adj->user->name ?? 'PPIC' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $adj->user->role ?? 'PPIC' }}</div>
                                </td>
                                <td>
                                    <div class="num text-xs font-semibold text-accent">{{ $adj->item->part_no ?: '-' }}</div>
                                    <div class="font-semibold text-slate-900">{{ $adj->item->part_name ?? '-' }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="pill {{ $adj->adjustment_type === 'LOSS' ? 'pill-crit' : (in_array($adj->adjustment_type, ['SURPLUS', 'FOUND', 'REPAIR']) ? 'pill-good' : 'pill-info') }}">
                                        {{ $adj->adjustment_type }}
                                    </span>
                                </td>
                                <td class="num">{{ floatval($adj->qty_system) }}</td>
                                <td class="num font-semibold text-slate-900">{{ floatval($adj->qty_actual) }}</td>
                                <td class="num font-bold {{ (float)$adj->qty_diff >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ (float)$adj->qty_diff > 0 ? '+' : '' }}{{ floatval($adj->qty_diff) }}
                                    {{ $adj->item->uom ?? '' }}
                                </td>
                                <td class="text-xs text-slate-600">{{ $adj->notes ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9"><div class="empty">Belum ada riwayat Stock Adjustment.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- POP-UP MODAL INPUT ADJUSTMENT DENGAN KALKULATOR --}}
        <div x-show="openCreate" x-cloak class="modal-backdrop">
            <div class="panel w-full max-w-2xl" @click.away="openCreate = false">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Input Stock Adjustment (Desktop)</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Mendukung kalkulator (+, -, *, /) dan konversi otomatis koma (,) ke titik desimal (.).</p>
                    </div>
                    <button type="button" @click="openCreate = false" class="act-btn" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form action="{{ route('stock_adjustments.store') }}" method="POST">
                    @csrf
                    <div class="panel-body space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">Pilih Item</label>
                                <select name="item_id" x-model="selectedItemId" @change="onSelectItem()" required class="w-full h-10">
                                    <option value="">-- Pilih Barang --</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}">
                                            {{ $item->part_no ? $item->part_no . ' - ' : '' }}{{ $item->part_name }} ({{ $item->type }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Kategori Adjustment</label>
                                <select name="adjustment_type" x-model="adjType" required class="w-full h-10">
                                    <option value="STOCK OPNAME">STOCK OPNAME (Bebas +/-)</option>
                                    <option value="LOSS">LOSS (Wajib Kurang dari System)</option>
                                    <option value="SURPLUS">SURPLUS (Wajib Lebih dari System)</option>
                                    <option value="FOUND">FOUND (Wajib Lebih dari System)</option>
                                    <option value="REPAIR">REPAIR (Wajib Lebih dari System / + Stok)</option>
                                </select>
                            </div>
                        </div>

                        <div class="p-4 rounded border border-slate-200 bg-slate-50 space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="lbl">Qty System Saat Ini</label>
                                    <input type="text" :value="qtySystem + (uom ? ' ' + uom : '')" disabled class="num w-full h-10 font-bold">
                                </div>
                                <div>
                                    <label class="lbl">Qty Actual (`+ - * /`)</label>
                                    <input type="text" name="qty_actual"
                                           x-model="qtyActual" @input="onActualChange()" required
                                           placeholder="Contoh: 150 atau 3*50+10"
                                           class="num w-full h-10 font-semibold">
                                </div>
                                <div>
                                    <label class="lbl">Qty Diff (`+ - * /`)</label>
                                    <input type="text" name="qty_diff"
                                           x-model="qtyDiff" @input="onDiffChange()" required
                                           placeholder="Contoh: -5 atau +10"
                                           class="num w-full h-10 font-semibold">
                                </div>
                            </div>

                            <div class="px-3 py-2 rounded border border-blue-200 bg-blue-50/70 text-xs font-semibold num text-accent"
                                 x-text="calcPreviewText()"></div>
                        </div>

                        <div>
                            <label class="lbl">Catatan / Keterangan (Opsional)</label>
                            <textarea name="notes" rows="2" placeholder="Masukkan alasan penyesuaian stok..." class="w-full"></textarea>
                        </div>
                    </div>
                    <div class="panel-foot">
                        <button type="button" @click="openCreate = false" class="btn">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Adjustment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>