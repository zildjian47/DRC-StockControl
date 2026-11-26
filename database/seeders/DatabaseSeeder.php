<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name'              => 'Budi Santoso (PPIC)',
                'username'          => 'ppic',
                'email'             => 'ppic@drc.co.id',
                'role'              => User::ROLE_PPIC,
                'status'            => User::STATUS_ACTIVE,
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name'              => 'Andi Pratama (Material)',
                'username'          => 'material',
                'email'             => 'material@drc.co.id',
                'role'              => User::ROLE_STAFF_MATERIAL,
                'status'            => User::STATUS_ACTIVE,
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name'              => 'Riko Saputra (FG)',
                'username'          => 'fg',
                'email'             => 'fg@drc.co.id',
                'role'              => User::ROLE_STAFF_FG,
                'status'            => User::STATUS_ACTIVE,
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        ];

        foreach ($accounts as $acc) {
            User::updateOrCreate(
                ['email' => $acc['email']],
                $acc
            );
        }
    }
}