<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionNg extends Model
{
    use HasFactory;

    protected $fillable = [
        'date_time',
        'lot_number',
        'item_id',
        'user_id',
        'qty_ng',
        'problem_reason',
        'qty_check',
        'qty_original',
        'corrected_by',
        'corrected_at',
        'correction_notes',
    ];

    protected $casts = [
        'date_time'    => 'datetime',
        'qty_ng'       => 'decimal:2',
        'qty_original' => 'decimal:2',
        'corrected_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function correctedBy()
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}