<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_code',
        'company_name',
        'type_company' // Tambahkan ini
    ];

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}