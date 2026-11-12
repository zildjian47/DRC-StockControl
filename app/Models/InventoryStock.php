<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'current_stock',
        'status_stock',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id')->withTrashed();
    }

    /**
     * Ratio Stock (Hari) On-The-Fly = current_stock / std_day_1
     */
    public function getRatioStockAttribute(): float
    {
        $item = $this->item;
        if (!$item) {
            return 0.0;
        }
        $std1 = (float) $item->calculated_std_day_1;
        if ($std1 <= 0) {
            return 0.0;
        }
        return round(((float) $this->current_stock) / $std1, 2);
    }

    /**
     * Kalkulasi Dinamis 5 Level Status Stock:
     * - current_stock <= 0                    -> EMPTY STOCK
     * - 0 < current_stock < std_day_1         -> VERY LOW
     * - std_day_1 <= current_stock < std_day_2 -> LOW STOCK
     * - std_day_2 <= current_stock <= std_day_3 -> NORMAL STOCK
     * - current_stock > std_day_3             -> OVERSTOCK
     */
    public function calculateDynamicStatus(): string
    {
        $current = (float) $this->current_stock;

        if ($current <= 0) {
            return 'EMPTY STOCK';
        }

        $item = $this->item;
        if (!$item) {
            return 'NORMAL STOCK';
        }

        $std1 = (float) $item->calculated_std_day_1;
        $std2 = (float) $item->std_day_2;
        $std3 = (float) $item->std_day_3;

        if ($std1 <= 0) {
            return 'NORMAL STOCK';
        }

        if ($current < $std1) {
            return 'VERY LOW';
        }

        if ($current < $std2) {
            return 'LOW STOCK';
        }

        if ($current <= $std3) {
            return 'NORMAL STOCK';
        }

        return 'OVERSTOCK';
    }

    public function adjustStock(float $deltaQty): void
    {
        $newStock = max(0, (float) $this->current_stock + $deltaQty);
        $this->current_stock = round($newStock, 2);
        $this->status_stock  = $this->calculateDynamicStatus();
        $this->save();
    }
}