<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Pengguna (PPIC & Operator Scanner)
        User::create([
            'name'     => 'Budi Santoso (PPIC)',
            'username' => 'ppic',
            'email'    => 'ppic@drc.co.id',
            'role'     => 'PPIC',
            'password' => Hash::make('password'),
        ]);

        User::create([
            'name'     => 'Andi Pratama (Operator)',
            'username' => 'operator',
            'email'    => 'operator@drc.co.id',
            'role'     => 'STAFF',
            'password' => Hash::make('password'),
        ]);
    }
}