<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PhaseOneSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Owner',
            'Admin',
            'Staff Gudang',
            'Staff Produksi',
            'Kasir',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
            ]);
        }

        $ownerRole = Role::where('name', 'Owner')->first();
        $adminRole = Role::where('name', 'Admin')->first();

        User::firstOrCreate(
            ['email' => 'owner@bakehub.web.id'],
            [
                'name' => 'Owner',
                'password' => Hash::make('password'),
                'role_id' => $ownerRole->id,
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@bakehub.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
            ]
        );
    }
}