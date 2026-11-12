<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'date_time',
        'material_item_id',
        'target_item_id',
        'user_id',
        'lot_number',
        'qty',
        'qty_check',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'qty'       => 'decimal:2',
    ];

    public function materialItem()
    {
        return $this->belongsTo(Item::class, 'material_item_id')->withTrashed();
    }

    public function targetItem()
    {
        return $this->belongsTo(Item::class, 'target_item_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}