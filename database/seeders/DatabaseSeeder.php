<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Company;
use App\Models\Process;
use App\Models\Item;
use App\Models\Bom;
use App\Models\InventoryStock;
use App\Models\LotTracer;
use App\Models\Inbound;
use App\Models\MaterialOut;
use App\Models\MaterialReturn;
use App\Models\Production;
use App\Models\ProductionNg;
use App\Models\Delivery;
use App\Models\ProblemDelay;
use App\Models\StockAdjustment;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Pengguna (PPIC & Operator Scanner)
        $ppic = User::create([
            'name'     => 'Budi Santoso (PPIC)',
            'username' => 'ppic',
            'email'    => 'ppic@drc.co.id',
            'role'     => 'PPIC',
            'password' => Hash::make('password'),
        ]);

        $staff = User::create([
            'name'     => 'Andi Pratama (Operator)',
            'username' => 'operator',
            'email'    => 'operator@drc.co.id',
            'role'     => 'STAFF',
            'password' => Hash::make('password'),
        ]);

    }
}