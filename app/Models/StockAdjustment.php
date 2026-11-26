<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'date_time',
        'item_id',
        'user_id',
        'qty_system',
        'qty_actual',
        'qty_diff',
        'adjustment_type',
        'notes',
    ];

    protected $casts = [
        'date_time'  => 'datetime',
        'qty_system' => 'decimal:2',
        'qty_actual' => 'decimal:2',
        'qty_diff'   => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}