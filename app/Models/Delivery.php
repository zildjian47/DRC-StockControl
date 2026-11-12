<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = ['date_time', 'item_id', 'user_id', 'qty', 'qty_check'];

    protected $casts = [
        'date_time' => 'datetime',
        'qty'       => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}