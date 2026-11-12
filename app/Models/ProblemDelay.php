<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProblemDelay extends Model
{
    use HasFactory;

    protected $fillable = ['date_time', 'company_id', 'item_id', 'user_id', 'problem_reason'];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}