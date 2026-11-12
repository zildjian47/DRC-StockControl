<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Process extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function boms()
    {
        return $this->hasMany(Bom::class, 'process_id');
    }

    public function lotTracers()
    {
        return $this->hasMany(LotTracer::class, 'process_id');
    }
}