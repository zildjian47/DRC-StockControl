<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Batas maksimum nilai kolom decimal(10, 2) pada MySQL.
     */
    public const MAX_QTY = 99999999.99;

    protected ?float $cachedStdDay1 = null;

    protected $fillable = [
        'part_no',
        'part_name',
        'type',
        'uom',
        'company_id',
        'qty_po',
        'working_days',
        'qty_kanban',
        'std_day_1_po_wd',
        'std_day_1',
    ];

    protected $casts = [
        'qty_po'          => 'decimal:2',
        'working_days'    => 'integer',
        'qty_kanban'      => 'decimal:2',
        'std_day_1_po_wd' => 'decimal:2',
        'std_day_1'       => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $item) {
            $item->cachedStdDay1 = null;
        });
    }

    /**
     * Pencarian item AKTIF (anti-bypass SoftDeletes) dari hasil scan QR maupun ketik manual.
     * Mendukung:
     * 1. URL QR Rak permanen (/item-card/{id}) — kebal ganti domain/tunnel
     * 2. Kode pendek ITEM:{id} / ITEM-{id}
     * 3. Part No atau Part Name (case-insensitive)
     */
    public static function findActiveByScanCode(string $rawCode, array $with = []): ?self
    {
        $trimmed = trim($rawCode);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('#/item-card/(\d+)(?:[/?\#]|$)#i', $trimmed, $matches)) {
            $query = self::query();
            if (!empty($with)) {
                $query->with($with);
            }
            return $query->find((int) $matches[1]);
        }

        if (preg_match('/^ITEM[:\-](\d+)$/i', $trimmed, $matches)) {
            $query = self::query();
            if (!empty($with)) {
                $query->with($with);
            }
            return $query->find((int) $matches[1]);
        }

        $upper = mb_strtoupper($trimmed, 'UTF-8');
        $query = self::query();
        if (!empty($with)) {
            $query->with($with);
        }

        return $query
            ->where(function ($q) use ($upper) {
                $q->whereRaw('UPPER(part_no) = ?', [$upper])
                  ->orWhereRaw('UPPER(part_name) = ?', [$upper]);
            })
            ->first();
    }

    public function company()
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    public function inventoryStock()
    {
        return $this->hasOne(InventoryStock::class);
    }

    public function bomsAsOutput()
    {
        return $this->hasMany(Bom::class, 'output_item_id');
    }

    public function bomsAsInput()
    {
        return $this->hasMany(Bom::class, 'input_item_id');
    }

    public function inbounds()
    {
        return $this->hasMany(Inbound::class);
    }

    public function materialOutsAsMaterial()
    {
        return $this->hasMany(MaterialOut::class, 'material_item_id');
    }

    public function materialOutsAsTarget()
    {
        return $this->hasMany(MaterialOut::class, 'target_item_id');
    }

    public function materialReturnsAsMaterial()
    {
        return $this->hasMany(MaterialReturn::class, 'material_item_id');
    }

    public function materialReturnsAsTarget()
    {
        return $this->hasMany(MaterialReturn::class, 'target_item_id');
    }

    public function productions()
    {
        return $this->hasMany(Production::class);
    }

    public function productionNgs()
    {
        return $this->hasMany(ProductionNg::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function returnToSuppliers()
    {
        return $this->hasMany(ReturnToSupplier::class);
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function problemDelays()
    {
        return $this->hasMany(ProblemDelay::class);
    }

    public function lotTracersAsMaterial()
    {
        return $this->hasMany(LotTracer::class, 'material_item_id');
    }

    public function lotTracersAsTarget()
    {
        return $this->hasMany(LotTracer::class, 'target_item_id');
    }

    public static function allowedCompanyTypesForItemType(string $itemType): array
    {
        return match (strtoupper(trim($itemType))) {
            'RM'          => ['SUPPLIER'],
            'KOMPONEN'    => ['SUPPLIER', 'INTERNAL'],
            'FG/KOMPONEN' => ['INTERNAL'],
            'TRANSIT'     => ['SUPPLIER'],
            'FG'          => ['CUSTOMER'],
            default       => ['SUPPLIER', 'INTERNAL', 'CUSTOMER'],
        };
    }

    public function hasTransactionHistory(): bool
    {
        $countAttrs = [
            'inbounds_count',
            'material_outs_as_material_count',
            'material_outs_as_target_count',
            'material_returns_as_material_count',
            'material_returns_as_target_count',
            'productions_count',
            'production_ngs_count',
            'deliveries_count',
            'return_to_suppliers_count',
            'stock_adjustments_count',
            'problem_delays_count',
            'lot_tracers_as_material_count',
            'lot_tracers_as_target_count',
            'boms_as_output_count',
            'boms_as_input_count',
        ];

        $allLoaded = true;
        $total     = 0;
        foreach ($countAttrs as $attr) {
            if (!array_key_exists($attr, $this->attributes)) {
                $allLoaded = false;
                break;
            }
            $total += (int) $this->attributes[$attr];
        }

        if ($allLoaded) {
            return $total > 0;
        }

        return $this->inbounds()->exists()
            || $this->materialOutsAsMaterial()->exists()
            || $this->materialOutsAsTarget()->exists()
            || $this->materialReturnsAsMaterial()->exists()
            || $this->materialReturnsAsTarget()->exists()
            || $this->productions()->exists()
            || $this->productionNgs()->exists()
            || $this->deliveries()->exists()
            || $this->returnToSuppliers()->exists()
            || $this->stockAdjustments()->exists()
            || $this->problemDelays()->exists()
            || $this->lotTracersAsMaterial()->exists()
            || $this->lotTracersAsTarget()->exists()
            || $this->bomsAsOutput()->exists()
            || $this->bomsAsInput()->exists();
    }

    public static function calculatePoKanbanStd(float $qtyPo, int $workingDays, float $qtyKanban): array
    {
        $poWd = ($workingDays > 0 && $qtyPo > 0) ? round($qtyPo / $workingDays, 2) : 0.0;
        if ($poWd <= 0) {
            return ['std_day_1_po_wd' => 0.0, 'std_day_1' => 0.0];
        }

        // Pembulatan 6 desimal sebelum ceil() mencegah artefak IEEE-754 pada kanban desimal
        $std1 = $qtyKanban > 0
            ? ceil(round($poWd / $qtyKanban, 6)) * $qtyKanban
            : ceil(round($poWd, 6));

        return [
            'std_day_1_po_wd' => round($poWd, 2),
            'std_day_1'       => round($std1, 2),
        ];
    }

    public function getCalculatedStdDay1Attribute(): float
    {
        if ($this->cachedStdDay1 !== null) {
            return $this->cachedStdDay1;
        }
        return $this->cachedStdDay1 = $this->computeStdDay1Recursive([]);
    }

    public function computeStdDay1Recursive(array $visited = []): float
    {
        if (in_array($this->id, $visited, true)) {
            return 0.0;
        }
        $visited[] = $this->id;

        $poStd1 = 0.0;
        if (in_array($this->type, ['FG', 'TRANSIT', 'FG/KOMPONEN'], true)) {
            $calc   = self::calculatePoKanbanStd((float) $this->qty_po, (int) $this->working_days, (float) $this->qty_kanban);
            $poStd1 = $calc['std_day_1'];
        }

        if (in_array($this->type, ['FG', 'TRANSIT'], true)) {
            return round($poStd1, 2);
        }

        $bomRows = $this->relationLoaded('bomsAsInput')
            ? $this->bomsAsInput
            : $this->bomsAsInput()->with('outputItem')->get();

        if ($bomRows->isEmpty()) {
            return round($poStd1, 2);
        }

        $bomStd1   = 0.0;
        $sharedMax = [];

        foreach ($bomRows as $bom) {
            $outItem = $bom->outputItem;
            if (!$outItem || $outItem->trashed()) {
                continue;
            }

            $outStd1 = $outItem->computeStdDay1Recursive($visited);
            $req     = $outStd1 * (float) $bom->qty_usage;
            $grp     = (string) $bom->bom_group;

            if ($bom->is_trigger && ($grp !== '' && (str_starts_with($grp, 'MO-') || str_starts_with($grp, 'CB-')))) {
                if (!isset($sharedMax[$grp]) || $req > $sharedMax[$grp]) {
                    $sharedMax[$grp] = $req;
                }
            } else {
                $bomStd1 += $req;
            }
        }

        foreach ($sharedMax as $maxVal) {
            $bomStd1 += $maxVal;
        }

        if ($this->type === 'FG/KOMPONEN') {
            return round($poStd1 + $bomStd1, 2);
        }

        return round($bomStd1, 2);
    }

    public function getStdDay2Attribute(): float
    {
        return round($this->calculated_std_day_1 * 2, 2);
    }

    public function getStdDay3Attribute(): float
    {
        return round($this->calculated_std_day_1 * 3, 2);
    }

    public function getStockMutationHistory(): array
    {
        $events = [];

        $extractAudit = function ($record): array {
            return [
                'qty_original'     => $record->qty_original !== null ? (float) $record->qty_original : null,
                'corrected_by'     => $record->correctedBy->name ?? null,
                'corrected_at_fmt' => $record->corrected_at ? Carbon::parse($record->corrected_at)->format('d M Y, H:i') : null,
                'correction_notes' => $record->correction_notes ?: null,
            ];
        };

        // 1. Inbound (+)
        foreach ($this->inbounds()->with(['user', 'correctedBy'])->get() as $inb) {
            $dt = Carbon::parse($inb->date_time);
            $events[] = array_merge([
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Inbound',
                'reference'    => 'Supplier: ' . ($this->company->company_code ?? '-'),
                'user_name'    => $inb->user->name ?? 'Operator',
                'qty_change'   => +(float) $inb->qty,
                'uom'          => $this->uom,
                'status'       => $inb->qty_check === 'DIKOREKSI' ? 'DIKOREKSI' : 'TERCATAT',
            ], $extractAudit($inb));
        }

        // 2. Material Out Trigger (-) — dikelompokkan per Lot agar Multi-Output tampil 1 baris sesuai pemotongan stok fisik
        foreach ($this->materialOutsAsMaterial()->with(['targetItem', 'user', 'correctedBy'])->get()->groupBy('lot_number') as $lotNo => $moRows) {
            $first       = $moRows->first();
            $dt          = Carbon::parse($first->date_time);
            $targets     = $moRows->map(fn ($r) => $r->targetItem->part_name ?? '-')->unique()->values()->implode(', ');
            $isCorrected = $moRows->contains('qty_check', 'DIKOREKSI');
            $auditRow    = $moRows->first(fn ($r) => $r->qty_check === 'DIKOREKSI') ?? $first;

            $events[] = array_merge([
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Material Out (Trigger)',
                'reference'    => 'Lot: ' . $lotNo . ' → ' . $targets,
                'user_name'    => $first->user->name ?? 'Operator',
                'qty_change'   => -(float) $first->qty,
                'uom'          => $this->uom,
                'status'       => $isCorrected ? 'DIKOREKSI' : 'TERCATAT',
            ], $extractAudit($auditRow));
        }

        // 3. Material Return Trigger (+) — dikelompokkan per return_batch (fallback lot_number untuk data lama)
        foreach (
            $this->materialReturnsAsMaterial()
                ->with(['targetItem', 'user', 'correctedBy'])
                ->get()
                ->groupBy(fn ($r) => !empty($r->return_batch) ? $r->return_batch : ('LEGACY-' . $r->lot_number)) as $mrRows
        ) {
            $first       = $mrRows->first();
            $lotNo       = $first->lot_number;
            $dt          = Carbon::parse($first->date_time);
            $targets     = $mrRows->map(fn ($r) => $r->targetItem->part_name ?? '-')->unique()->values()->implode(', ');
            $isCorrected = $mrRows->contains('qty_check', 'DIKOREKSI');
            $auditRow    = $mrRows->first(fn ($r) => $r->qty_check === 'DIKOREKSI') ?? $first;

            $events[] = array_merge([
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Material Return (Trigger)',
                'reference'    => 'Lot: ' . $lotNo . ' → ' . $targets,
                'user_name'    => $first->user->name ?? 'Operator',
                'qty_change'   => +(float) $first->qty,
                'uom'          => $this->uom,
                'status'       => $isCorrected ? 'DIKOREKSI' : 'TERCATAT',
            ], $extractAudit($auditRow));
        }

        // 4. Material Out & Return Pendamping — dikelompokkan per Lot (MO) & per return_batch (MR)
        $companionBoms = Bom::with('outputItem')
            ->where('input_item_id', $this->id)
            ->where('is_trigger', false)
            ->get();

        if ($companionBoms->isNotEmpty()) {
            $compMoByLot   = [];
            $compMrByBatch = [];

            foreach ($companionBoms as $cb) {
                $triggerQuery = Bom::where('process_id', $cb->process_id)
                    ->where('output_item_id', $cb->output_item_id)
                    ->where('is_trigger', true);

                if ($cb->bom_group) {
                    $triggerQuery->where('bom_group', $cb->bom_group);
                }

                $triggerBom = $triggerQuery->first();
                if (!$triggerBom || (float) $triggerBom->qty_usage <= 0) {
                    continue;
                }

                $relatedMos = MaterialOut::with(['user', 'correctedBy'])
                    ->where('material_item_id', $triggerBom->input_item_id)
                    ->where('target_item_id', $cb->output_item_id)
                    ->when($cb->bom_group, function ($q) use ($cb) {
                        $q->where(function ($sub) use ($cb) {
                            $sub->where('bom_group', $cb->bom_group)
                                ->orWhereNull('bom_group');
                        });
                    })
                    ->get();

                foreach ($relatedMos as $mo) {
                    $estOut = (float) $mo->qty / (float) $triggerBom->qty_usage;
                    if (abs($estOut - round($estOut)) < 0.001) {
                        $estOut = round($estOut);
                    }
                    $deducted = round($estOut * (float) $cb->qty_usage, 2);
                    if ($deducted <= 0) {
                        continue;
                    }
                    $lotKey      = $mo->lot_number;
                    $tgtName     = $cb->outputItem->part_name ?? '-';
                    $isCorrected = $mo->qty_check === 'DIKOREKSI';

                    $origComp = null;
                    if ($mo->qty_original !== null) {
                        $estOrig = (float) $mo->qty_original / (float) $triggerBom->qty_usage;
                        if (abs($estOrig - round($estOrig)) < 0.001) {
                            $estOrig = round($estOrig);
                        }
                        $origComp = round($estOrig * (float) $cb->qty_usage, 2);
                    }

                    if (!isset($compMoByLot[$lotKey])) {
                        $dt = Carbon::parse($mo->date_time);
                        $compMoByLot[$lotKey] = [
                            'timestamp'        => $dt->timestamp,
                            'date'             => $dt->format('Y-m-d'),
                            'time'             => $dt->format('H:i'),
                            'datetime_fmt'     => $dt->format('d M Y, H:i'),
                            'type_label'       => 'Material Out (Pendamping)',
                            'targets'          => [$tgtName],
                            'user_name'        => $mo->user->name ?? 'Operator',
                            'qty_change'       => -$deducted,
                            'uom'              => $this->uom,
                            'status'           => $isCorrected ? 'DIKOREKSI' : 'TERCATAT',
                            'qty_original'     => $origComp,
                            'corrected_by'     => $mo->correctedBy->name ?? null,
                            'corrected_at_fmt' => $mo->corrected_at ? Carbon::parse($mo->corrected_at)->format('d M Y, H:i') : null,
                            'correction_notes' => $mo->correction_notes ?: null,
                        ];
                    } else {
                        $compMoByLot[$lotKey]['qty_change'] = round($compMoByLot[$lotKey]['qty_change'] - $deducted, 2);
                        if ($origComp !== null) {
                            $compMoByLot[$lotKey]['qty_original'] = round((float) ($compMoByLot[$lotKey]['qty_original'] ?? 0) + $origComp, 2);
                        }
                        if (!in_array($tgtName, $compMoByLot[$lotKey]['targets'], true)) {
                            $compMoByLot[$lotKey]['targets'][] = $tgtName;
                        }
                        if ($isCorrected) {
                            $compMoByLot[$lotKey]['status']           = 'DIKOREKSI';
                            $compMoByLot[$lotKey]['corrected_by']     = $mo->correctedBy->name ?? $compMoByLot[$lotKey]['corrected_by'];
                            $compMoByLot[$lotKey]['corrected_at_fmt'] = $mo->corrected_at
                                ? Carbon::parse($mo->corrected_at)->format('d M Y, H:i')
                                : $compMoByLot[$lotKey]['corrected_at_fmt'];
                            $compMoByLot[$lotKey]['correction_notes'] = $mo->correction_notes ?: $compMoByLot[$lotKey]['correction_notes'];
                        }
                    }
                }

                $relatedMrs = MaterialReturn::with(['user', 'correctedBy'])
                    ->where('material_item_id', $triggerBom->input_item_id)
                    ->where('target_item_id', $cb->output_item_id)
                    ->when($cb->bom_group, function ($q) use ($cb) {
                        $q->where(function ($sub) use ($cb) {
                            $sub->where('bom_group', $cb->bom_group)
                                ->orWhereNull('bom_group');
                        });
                    })
                    ->get();

                foreach ($relatedMrs as $mr) {
                    $estRet = (float) $mr->qty / (float) $triggerBom->qty_usage;
                    if (abs($estRet - round($estRet)) < 0.001) {
                        $estRet = round($estRet);
                    }
                    $restored = round($estRet * (float) $cb->qty_usage, 2);
                    if ($restored <= 0) {
                        continue;
                    }
                    $batchKey    = !empty($mr->return_batch) ? $mr->return_batch : ('LEGACY-' . $mr->lot_number);
                    $tgtName     = $cb->outputItem->part_name ?? '-';
                    $isCorrected = $mr->qty_check === 'DIKOREKSI';

                    $origComp = null;
                    if ($mr->qty_original !== null) {
                        $estOrig = (float) $mr->qty_original / (float) $triggerBom->qty_usage;
                        if (abs($estOrig - round($estOrig)) < 0.001) {
                            $estOrig = round($estOrig);
                        }
                        $origComp = round($estOrig * (float) $cb->qty_usage, 2);
                    }

                    if (!isset($compMrByBatch[$batchKey])) {
                        $dt = Carbon::parse($mr->date_time);
                        $compMrByBatch[$batchKey] = [
                            'timestamp'        => $dt->timestamp,
                            'date'             => $dt->format('Y-m-d'),
                            'time'             => $dt->format('H:i'),
                            'datetime_fmt'     => $dt->format('d M Y, H:i'),
                            'type_label'       => 'Material Return (Pendamping)',
                            'lot_number'       => $mr->lot_number,
                            'targets'          => [$tgtName],
                            'user_name'        => $mr->user->name ?? 'Operator',
                            'qty_change'       => +$restored,
                            'uom'              => $this->uom,
                            'status'           => $isCorrected ? 'DIKOREKSI' : 'TERCATAT',
                            'qty_original'     => $origComp,
                            'corrected_by'     => $mr->correctedBy->name ?? null,
                            'corrected_at_fmt' => $mr->corrected_at ? Carbon::parse($mr->corrected_at)->format('d M Y, H:i') : null,
                            'correction_notes' => $mr->correction_notes ?: null,
                        ];
                    } else {
                        $compMrByBatch[$batchKey]['qty_change'] = round($compMrByBatch[$batchKey]['qty_change'] + $restored, 2);
                        if ($origComp !== null) {
                            $compMrByBatch[$batchKey]['qty_original'] = round((float) ($compMrByBatch[$batchKey]['qty_original'] ?? 0) + $origComp, 2);
                        }
                        if (!in_array($tgtName, $compMrByBatch[$batchKey]['targets'], true)) {
                            $compMrByBatch[$batchKey]['targets'][] = $tgtName;
                        }
                        if ($isCorrected) {
                            $compMrByBatch[$batchKey]['status']           = 'DIKOREKSI';
                            $compMrByBatch[$batchKey]['corrected_by']     = $mr->correctedBy->name ?? $compMrByBatch[$batchKey]['corrected_by'];
                            $compMrByBatch[$batchKey]['corrected_at_fmt'] = $mr->corrected_at
                                ? Carbon::parse($mr->corrected_at)->format('d M Y, H:i')
                                : $compMrByBatch[$batchKey]['corrected_at_fmt'];
                            $compMrByBatch[$batchKey]['correction_notes'] = $mr->correction_notes ?: $compMrByBatch[$batchKey]['correction_notes'];
                        }
                    }
                }
            }

            foreach ($compMoByLot as $lotKey => $ev) {
                $ev['reference'] = 'Lot: ' . $lotKey . ' → ' . implode(', ', $ev['targets']);
                unset($ev['targets']);
                $events[] = $ev;
            }
            foreach ($compMrByBatch as $ev) {
                $ev['reference'] = 'Lot: ' . $ev['lot_number'] . ' → ' . implode(', ', $ev['targets']);
                unset($ev['targets'], $ev['lot_number']);
                $events[] = $ev;
            }
        }

        // 5. Production (+)
        foreach ($this->productions()->with(['user', 'correctedBy'])->get() as $prod) {
            $dt = Carbon::parse($prod->date_time);
            $events[] = array_merge([
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Production',
                'reference'    => 'Lot: ' . $prod->lot_number,
                'user_name'    => $prod->user->name ?? 'Operator',
                'qty_change'   => +(float) $prod->qty,
                'uom'          => $this->uom,
                'status'       => $prod->qty_check === 'DIKOREKSI' ? 'DIKOREKSI' : 'TERCATAT',
            ], $extractAudit($prod));
        }

        // 6. Production NG (-)
        foreach ($this->productionNgs()->with(['user', 'correctedBy'])->get() as $ng) {
            $dt = Carbon::parse($ng->date_time);
            $events[] = array_merge([
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Production NG',
                'reference'    => 'Lot: ' . $ng->lot_number . ' (' . $ng->problem_reason . ')',
                'user_name'    => $ng->user->name ?? 'QC',
                'qty_change'   => -(float) $ng->qty_ng,
                'uom'          => $this->uom,
                'status'       => $ng->qty_check === 'DIKOREKSI' ? 'DIKOREKSI' : 'TERCATAT',
            ], $extractAudit($ng));
        }

        // 7. Delivery (-)
        foreach ($this->deliveries()->with(['user', 'correctedBy'])->get() as $del) {
            $dt = Carbon::parse($del->date_time);
            $events[] = array_merge([
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Delivery',
                'reference'    => 'Customer: ' . ($this->company->company_code ?? '-'),
                'user_name'    => $del->user->name ?? 'Operator',
                'qty_change'   => -(float) $del->qty,
                'uom'          => $this->uom,
                'status'       => $del->qty_check === 'DIKOREKSI' ? 'DIKOREKSI' : 'TERCATAT',
            ], $extractAudit($del));
        }

        // 8. Return to Supplier (-)
        foreach ($this->returnToSuppliers()->with(['user', 'correctedBy'])->get() as $ret) {
            $dt = Carbon::parse($ret->date_time);
            $events[] = array_merge([
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Return to Supplier',
                'reference'    => 'Supplier: ' . ($this->company->company_code ?? '-'),
                'user_name'    => $ret->user->name ?? 'Operator',
                'qty_change'   => -(float) $ret->qty,
                'uom'          => $this->uom,
                'status'       => $ret->qty_check === 'DIKOREKSI' ? 'DIKOREKSI' : 'TERCATAT',
            ], $extractAudit($ret));
        }

        // 9. Stock Adjustment (+/-)
        foreach ($this->stockAdjustments()->with('user')->get() as $adj) {
            $dt = Carbon::parse($adj->date_time);
            $events[] = [
                'timestamp'        => $dt->timestamp,
                'date'             => $dt->format('Y-m-d'),
                'time'             => $dt->format('H:i'),
                'datetime_fmt'     => $dt->format('d M Y, H:i'),
                'type_label'       => 'Adjustment (' . $adj->adjustment_type . ')',
                'reference'        => $adj->notes ?: '-',
                'user_name'        => $adj->user->name ?? 'PPIC',
                'qty_change'       => (float) $adj->qty_diff,
                'uom'              => $this->uom,
                'status'           => $adj->adjustment_type,
                'qty_original'     => null,
                'corrected_by'     => null,
                'corrected_at_fmt' => null,
                'correction_notes' => null,
            ];
        }

        usort($events, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_values($events);
    }

    public function getMaterialInfoPayloadAttribute(): array
    {
        $stock        = $this->inventoryStock;
        $currentStock = $stock ? (float) $stock->current_stock : 0.0;
        $std1         = $this->calculated_std_day_1;
        $ratio        = $std1 > 0 ? round($currentStock / $std1, 2) : 0.0;
        $status       = $stock ? $stock->calculateDynamicStatus() : 'EMPTY STOCK';
        $wipQty       = round((float) $this->lotTracersAsTarget()->where('status_lot', 'ON PROGRESS')->sum('qty_wip'), 2);

        $bomsOut = $this->relationLoaded('bomsAsOutput')
            ? $this->bomsAsOutput
            : $this->bomsAsOutput()->with(['inputItem.inventoryStock', 'process'])->get();

        $inputs = $bomsOut->map(function ($b) {
            $inItem  = $b->inputItem;
            $inStock = $inItem && $inItem->inventoryStock ? (float) $inItem->inventoryStock->current_stock : 0.0;
            $inStd1  = $inItem ? $inItem->calculated_std_day_1 : 0.0;
            $inRatio = $inStd1 > 0 ? round($inStock / $inStd1, 2) : 0.0;
            $inStat  = $inItem && $inItem->inventoryStock ? $inItem->inventoryStock->calculateDynamicStatus() : 'EMPTY STOCK';

            return [
                'bom_group'     => $b->bom_group ?: ('SG-' . $b->id),
                'group_type'    => $b->group_type,
                'process'       => $b->process->process_name ?? '-',
                'part_no'       => $inItem?->part_no ?: '-',
                'part_name'     => $inItem?->part_name ?? '-',
                'type'          => $inItem?->type ?? '-',
                'uom'           => $inItem?->uom ?? '',
                'qty_usage'     => $b->formatted_qty_usage,
                'is_trigger'    => (bool) $b->is_trigger,
                'current_stock' => $inStock,
                'ratio_stock'   => $inRatio,
                'status_stock'  => $inStat,
            ];
        })->values()->all();

        $bomsIn = $this->relationLoaded('bomsAsInput')
            ? $this->bomsAsInput
            : $this->bomsAsInput()->with(['outputItem.inventoryStock', 'process'])->get();

        $outputs = $bomsIn->map(function ($b) {
            $outItem  = $b->outputItem;
            $outStock = $outItem && $outItem->inventoryStock ? (float) $outItem->inventoryStock->current_stock : 0.0;
            $outStd1  = $outItem ? $outItem->calculated_std_day_1 : 0.0;
            $outRatio = $outStd1 > 0 ? round($outStock / $outStd1, 2) : 0.0;
            $outStat  = $outItem && $outItem->inventoryStock ? $outItem->inventoryStock->calculateDynamicStatus() : 'EMPTY STOCK';

            return [
                'bom_group'     => $b->bom_group ?: ('SG-' . $b->id),
                'group_type'    => $b->group_type,
                'process'       => $b->process->process_name ?? '-',
                'part_no'       => $outItem?->part_no ?: '-',
                'part_name'     => $outItem?->part_name ?? '-',
                'type'          => $outItem?->type ?? '-',
                'uom'           => $outItem?->uom ?? '',
                'qty_usage'     => $b->formatted_qty_usage,
                'is_trigger'    => (bool) $b->is_trigger,
                'current_stock' => $outStock,
                'ratio_stock'   => $outRatio,
                'status_stock'  => $outStat,
            ];
        })->values()->all();

        return [
            'id'            => $this->id,
            'part_no'       => $this->part_no ?: '-',
            'part_name'     => $this->part_name,
            'type'          => $this->type,
            'uom'           => $this->uom,
            'company'       => $this->company ? ($this->company->company_code . ' — ' . $this->company->company_name) : 'INTERNAL',
            'company_type'  => $this->company ? $this->company->type_company : 'INTERNAL',
            'current_stock' => $currentStock,
            'wip_qty'       => $wipQty,
            'std_day_1'     => $std1,
            'std_day_2'     => $this->std_day_2,
            'std_day_3'     => $this->std_day_3,
            'ratio_stock'   => $ratio,
            'status_stock'  => $status,
            'inputs'        => $inputs,
            'outputs'       => $outputs,
            'history'       => $this->getStockMutationHistory(),
        ];
    }
}