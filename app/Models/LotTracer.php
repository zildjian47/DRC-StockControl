<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LotTracer extends Model
{
    use HasFactory;

    protected $fillable = [
        'lot_number',
        'process_id',
        'material_item_id',
        'target_item_id',
        'qty_material_out',
        'qty_material_return',
        'target_qty_estimasi',
        'actual_qty_prod',
        'qty_ng',
        'qty_wip',
        'status_lot',
    ];

    protected $casts = [
        'qty_material_out'    => 'decimal:2',
        'qty_material_return' => 'decimal:2',
        'target_qty_estimasi' => 'decimal:2',
        'actual_qty_prod'     => 'decimal:2',
        'qty_ng'              => 'decimal:2',
        'qty_wip'             => 'decimal:2',
    ];

    public function process()
    {
        return $this->belongsTo(Process::class);
    }

    public function materialItem()
    {
        return $this->belongsTo(Item::class, 'material_item_id')->withTrashed();
    }

    public function targetItem()
    {
        return $this->belongsTo(Item::class, 'target_item_id')->withTrashed();
    }

    public function getActFgAttribute(): float
    {
        return round(max(0, (float) $this->actual_qty_prod - (float) $this->qty_ng), 2);
    }

    public function resolveTriggerBom(): ?Bom
    {
        return Bom::where('process_id', $this->process_id)
            ->where('output_item_id', $this->target_item_id)
            ->where('input_item_id', $this->material_item_id)
            ->where('is_trigger', true)
            ->first()
            ?: Bom::where('output_item_id', $this->target_item_id)
                ->where('input_item_id', $this->material_item_id)
                ->where('is_trigger', true)
                ->first();
    }

    /**
     * Cek apakah material utama Lot ini adalah Coil (berdasarkan UOM KG atau nama mengandung COIL).
     */
    public function getIsCoilAttribute(): bool
    {
        $mat = $this->materialItem;
        if (!$mat) {
            return false;
        }
        return strtoupper((string) $mat->uom) === 'KG'
            || str_contains(strtoupper((string) $mat->part_name), 'COIL');
    }

    /**
     * Hitung sisa material fisik Trigger (sebelum ditutup FINISH).
     */
    public function computeRawRemainingMaterial(): float
    {
        $siblings = self::where('lot_number', $this->lot_number)->get();
        $maxUsedMaterial = 0.0;

        foreach ($siblings as $row) {
            $tb = $row->resolveTriggerBom();
            $usage = ($tb && (float) $tb->qty_usage > 0) ? (float) $tb->qty_usage : 1.0;
            $used = (float) $row->actual_qty_prod * $usage;
            if ($used > $maxUsedMaterial) {
                $maxUsedMaterial = $used;
            }
        }

        $netAvailable = (float) $this->qty_material_out - (float) $this->qty_material_return - $maxUsedMaterial;
        return round(max(0, $netAvailable), 2);
    }

    /**
     * Material Saat Ini (Trigger):
     * Jika Lot Coil sudah FINISH, sisa coil berpindah ke kolom Top/End Coil sehingga Material Saat Ini di mesin = 0.
     */
    public function getMaterialSaatIniAttribute(): float
    {
        if ($this->status_lot === 'FINISH' && $this->is_coil) {
            return 0.0;
        }
        return $this->computeRawRemainingMaterial();
    }

    /**
     * Nilai Top / End Coil (On-The-Fly):
     * Hanya bernilai angka (KG) apabila material adalah Coil DAN status_lot === 'FINISH'.
     * Jika bukan Coil atau masih ON PROGRESS, mengembalikan null (ditampilkan '-' di tabel).
     */
    public function getTopEndCoilAttribute(): ?float
    {
        if (!$this->is_coil || $this->status_lot !== 'FINISH') {
            return null;
        }
        return $this->computeRawRemainingMaterial();
    }

    /**
     * Rincian Komponen Pendamping (is_trigger = false) beserta:
     * - qty_out (Total keluar awal)
     * - qty_saat_ini (Sisa fisik pendamping di mesin saat ini setelah dikurangi retur & aktual produksi lintas shift)
     * - qty_return (Total diretur ke gudang)
     */
    public function getCompanionItemsAttribute(): array
    {
        $siblings = self::where('lot_number', $this->lot_number)->get();
        $companions = [];

        foreach ($siblings as $row) {
            $tb = $row->resolveTriggerBom();
            if (!$tb || (float) $tb->qty_usage <= 0) {
                continue;
            }

            $compBoms = Bom::with('inputItem')
                ->where('process_id', $row->process_id)
                ->where('output_item_id', $row->target_item_id)
                ->where('is_trigger', false)
                ->when($tb->bom_group, fn($q) => $q->where('bom_group', $tb->bom_group))
                ->get();

            $estInit = (float) $row->qty_material_out / (float) $tb->qty_usage;
            if (abs($estInit - round($estInit)) < 0.001) {
                $estInit = round($estInit);
            }

            $estRet = (float) $row->qty_material_return / (float) $tb->qty_usage;
            if (abs($estRet - round($estRet)) < 0.001) {
                $estRet = round($estRet);
            }

            $estNet  = max(0, $estInit - $estRet);
            $actProd = (float) $row->actual_qty_prod;

            foreach ($compBoms as $cb) {
                $cid = $cb->input_item_id;
                $usageComp = (float) $cb->qty_usage;

                if (!isset($companions[$cid])) {
                    $companions[$cid] = [
                        'input_item_id' => $cid,
                        'part_no'       => $cb->inputItem->part_no ?: '-',
                        'part_name'     => $cb->inputItem->part_name ?? '-',
                        'uom'           => $cb->inputItem->uom ?? 'PCS',
                        'qty_out'       => 0.0,
                        'qty_return'    => 0.0,
                        'qty_net'       => 0.0,
                        'qty_used_prod' => 0.0,
                        'qty_saat_ini'  => 0.0,
                    ];
                }

                $outVal  = round($estInit * $usageComp, 2);
                $retVal  = round($estRet * $usageComp, 2);
                $netVal  = round($estNet * $usageComp, 2);
                $usedVal = round($actProd * $usageComp, 2);
                $currVal = round(max(0, $netVal - $usedVal), 2);

                $companions[$cid]['qty_out']       = round($companions[$cid]['qty_out'] + $outVal, 2);
                $companions[$cid]['qty_return']    = round($companions[$cid]['qty_return'] + $retVal, 2);
                $companions[$cid]['qty_net']       = round($companions[$cid]['qty_net'] + $netVal, 2);
                $companions[$cid]['qty_used_prod'] = round($companions[$cid]['qty_used_prod'] + $usedVal, 2);
                $companions[$cid]['qty_saat_ini']  = round($companions[$cid]['qty_saat_ini'] + $currVal, 2);
            }
        }

        return array_values($companions);
    }

    public function recalculateLotMetrics(): void
    {
        $bom = $this->resolveTriggerBom();
        $netMaterial = max(0, (float) $this->qty_material_out - (float) $this->qty_material_return);

        if ($bom && (float) $bom->qty_usage > 0) {
            $rawEst = $netMaterial / (float) $bom->qty_usage;
            $nearestInt = round($rawEst);
            $this->target_qty_estimasi = abs($rawEst - $nearestInt) < 0.001
                ? $nearestInt
                : round($rawEst, 2);
        }

        if ($this->status_lot === 'FINISH') {
            $this->qty_wip = 0;
        } else {
            $this->qty_wip = round(max(0, (float) $this->target_qty_estimasi - (float) $this->actual_qty_prod), 2);
        }

        $this->save();
    }
}