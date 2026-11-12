<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Item extends Model
{
    use HasFactory, SoftDeletes;

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

    public function company()
    {
        return $this->belongsTo(Company::class);
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

        $std1 = $qtyKanban > 0
            ? ceil($poWd / $qtyKanban) * $qtyKanban
            : ceil($poWd);

        return [
            'std_day_1_po_wd' => round($poWd, 2),
            'std_day_1'       => round($std1, 2),
        ];
    }

    public function getCalculatedStdDay1Attribute(): float
    {
        return $this->computeStdDay1Recursive([]);
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

        $bomRows = $this->bomsAsInput()->with('outputItem')->get();
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

        // 1. Inbound (+)
        foreach ($this->inbounds()->with('user')->get() as $inb) {
            $dt = Carbon::parse($inb->date_time);
            $events[] = [
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Inbound',
                'reference'    => 'Supplier: ' . ($this->company->company_code ?? '-'),
                'user_name'    => $inb->user->name ?? 'Operator',
                'qty_change'   => +(float) $inb->qty,
                'uom'          => $this->uom,
                'status'       => $inb->qty_check,
            ];
        }

        // 2. Material Out Trigger (-)
        foreach ($this->materialOutsAsMaterial()->with(['targetItem', 'user'])->get() as $mo) {
            $dt = Carbon::parse($mo->date_time);
            $events[] = [
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Material Out (Trigger)',
                'reference'    => 'Lot: ' . $mo->lot_number . ' → ' . ($mo->targetItem->part_name ?? '-'),
                'user_name'    => $mo->user->name ?? 'Operator',
                'qty_change'   => -(float) $mo->qty,
                'uom'          => $this->uom,
                'status'       => $mo->qty_check,
            ];
        }

        // 3. Material Return Trigger (+)
        foreach ($this->materialReturnsAsMaterial()->with(['targetItem', 'user'])->get() as $mr) {
            $dt = Carbon::parse($mr->date_time);
            $events[] = [
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Material Return (Trigger)',
                'reference'    => 'Lot: ' . $mr->lot_number . ' → ' . ($mr->targetItem->part_name ?? '-'),
                'user_name'    => $mr->user->name ?? 'Operator',
                'qty_change'   => +(float) $mr->qty,
                'uom'          => $this->uom,
                'status'       => $mr->qty_check,
            ];
        }

        // 4. Material Out & Return Pendamping
        $companionBoms = Bom::with('outputItem')
            ->where('input_item_id', $this->id)
            ->where('is_trigger', false)
            ->get();

        if ($companionBoms->isNotEmpty()) {
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

                $relatedMos = MaterialOut::with('user')
                    ->where('material_item_id', $triggerBom->input_item_id)
                    ->where('target_item_id', $cb->output_item_id)
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
                    $dt = Carbon::parse($mo->date_time);
                    $events[] = [
                        'timestamp'    => $dt->timestamp,
                        'date'         => $dt->format('Y-m-d'),
                        'time'         => $dt->format('H:i'),
                        'datetime_fmt' => $dt->format('d M Y, H:i'),
                        'type_label'   => 'Material Out (Pendamping)',
                        'reference'    => 'Lot: ' . $mo->lot_number . ' → ' . ($cb->outputItem->part_name ?? '-'),
                        'user_name'    => $mo->user->name ?? 'Operator',
                        'qty_change'   => -$deducted,
                        'uom'          => $this->uom,
                        'status'       => $mo->qty_check,
                    ];
                }

                $relatedMrs = MaterialReturn::with('user')
                    ->where('material_item_id', $triggerBom->input_item_id)
                    ->where('target_item_id', $cb->output_item_id)
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
                    $dt = Carbon::parse($mr->date_time);
                    $events[] = [
                        'timestamp'    => $dt->timestamp,
                        'date'         => $dt->format('Y-m-d'),
                        'time'         => $dt->format('H:i'),
                        'datetime_fmt' => $dt->format('d M Y, H:i'),
                        'type_label'   => 'Material Return (Pendamping)',
                        'reference'    => 'Lot: ' . $mr->lot_number . ' → ' . ($cb->outputItem->part_name ?? '-'),
                        'user_name'    => $mr->user->name ?? 'Operator',
                        'qty_change'   => +$restored,
                        'uom'          => $this->uom,
                        'status'       => $mr->qty_check,
                    ];
                }
            }
        }

        // 5. Production (+)
        foreach ($this->productions()->with('user')->get() as $prod) {
            $dt = Carbon::parse($prod->date_time);
            $events[] = [
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Production',
                'reference'    => 'Lot: ' . $prod->lot_number,
                'user_name'    => $prod->user->name ?? 'Operator',
                'qty_change'   => +(float) $prod->qty,
                'uom'          => $this->uom,
                'status'       => $prod->qty_check,
            ];
        }

        // 6. Production NG (-)
        foreach ($this->productionNgs()->with('user')->get() as $ng) {
            $dt = Carbon::parse($ng->date_time);
            $events[] = [
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Production NG',
                'reference'    => 'Lot: ' . $ng->lot_number . ' (' . $ng->problem_reason . ')',
                'user_name'    => $ng->user->name ?? 'QC',
                'qty_change'   => -(float) $ng->qty_ng,
                'uom'          => $this->uom,
                'status'       => 'TERCATAT',
            ];
        }

        // 7. Delivery (-)
        foreach ($this->deliveries()->with('user')->get() as $del) {
            $dt = Carbon::parse($del->date_time);
            $events[] = [
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Delivery',
                'reference'    => 'Customer: ' . ($this->company->company_code ?? '-'),
                'user_name'    => $del->user->name ?? 'Operator',
                'qty_change'   => -(float) $del->qty,
                'uom'          => $this->uom,
                'status'       => $del->qty_check,
            ];
        }

        // 8. Return to Supplier (-)
        foreach ($this->returnToSuppliers()->with('user')->get() as $ret) {
            $dt = Carbon::parse($ret->date_time);
            $events[] = [
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Return to Supplier',
                'reference'    => 'Supplier: ' . ($this->company->company_code ?? '-'),
                'user_name'    => $ret->user->name ?? 'Operator',
                'qty_change'   => -(float) $ret->qty,
                'uom'          => $this->uom,
                'status'       => $ret->qty_check,
            ];
        }

        // 9. Stock Adjustment (+/-)
        foreach ($this->stockAdjustments()->with('user')->get() as $adj) {
            $dt = Carbon::parse($adj->date_time);
            $events[] = [
                'timestamp'    => $dt->timestamp,
                'date'         => $dt->format('Y-m-d'),
                'time'         => $dt->format('H:i'),
                'datetime_fmt' => $dt->format('d M Y, H:i'),
                'type_label'   => 'Adjustment (' . $adj->adjustment_type . ')',
                'reference'    => $adj->notes ?: '-',
                'user_name'    => $adj->user->name ?? 'PPIC',
                'qty_change'   => (float) $adj->qty_diff,
                'uom'          => $this->uom,
                'status'       => $adj->adjustment_type,
            ];
        }

        usort($events, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_values($events);
    }

    public function getMaterialInfoPayloadAttribute(): array
    {
        $stock        = $this->inventoryStock;
        $currentStock = $stock ? (float) $stock->current_stock : 0.0;
        $std1         = $this->calculated_std_day_1;
        $ratio        = $std1 > 0 ? round($currentStock / $std1, 2) : 0.0;
        $status       = $stock ? $stock->calculateDynamicStatus() : 'EMPTY STOCK';

        $inputs = $this->bomsAsOutput()->with(['inputItem.inventoryStock', 'process'])->get()->map(function ($b) {
            $inItem  = $b->inputItem;
            $inStock = $inItem && $inItem->inventoryStock ? (float) $inItem->inventoryStock->current_stock : 0.0;
            $inStd1  = $inItem ? $inItem->calculated_std_day_1 : 0.0;
            $inRatio = $inStd1 > 0 ? round($inStock / $inStd1, 2) : 0.0;
            $inStat  = $inItem && $inItem->inventoryStock ? $inItem->inventoryStock->calculateDynamicStatus() : 'EMPTY STOCK';

            return [
                'process'       => $b->process->process_name ?? '-',
                'part_no'       => $inItem->part_no ?: '-',
                'part_name'     => $inItem->part_name ?? '-',
                'type'          => $inItem->type ?? '-',
                'uom'           => $inItem->uom ?? '',
                'qty_usage'     => $b->formatted_qty_usage,
                'is_trigger'    => (bool) $b->is_trigger,
                'current_stock' => $inStock,
                'ratio_stock'   => $inRatio,
                'status_stock'  => $inStat,
            ];
        })->values()->all();

        $outputs = $this->bomsAsInput()->with(['outputItem.inventoryStock', 'process'])->get()->map(function ($b) {
            $outItem  = $b->outputItem;
            $outStock = $outItem && $outItem->inventoryStock ? (float) $outItem->inventoryStock->current_stock : 0.0;
            $outStd1  = $outItem ? $outItem->calculated_std_day_1 : 0.0;
            $outRatio = $outStd1 > 0 ? round($outStock / $outStd1, 2) : 0.0;
            $outStat  = $outItem && $outItem->inventoryStock ? $outItem->inventoryStock->calculateDynamicStatus() : 'EMPTY STOCK';

            return [
                'process'       => $b->process->process_name ?? '-',
                'group_type'    => $b->group_type,
                'part_no'       => $outItem->part_no ?: '-',
                'part_name'     => $outItem->part_name ?? '-',
                'type'          => $outItem->type ?? '-',
                'uom'           => $outItem->uom ?? '',
                'qty_usage'     => $b->formatted_qty_usage,
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
            'company'       => $this->company ? ($this->company->company_code . ' (' . $this->company->type_company . ')') : 'INTERNAL',
            'current_stock' => $currentStock,
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