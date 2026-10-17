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
        $roles = ['Owner', 'Admin', 'Staff Gudang', 'Staff Produksi', 'Kasir'];

        foreach ($roles as $roleName) {
            Role::create(['name' => $roleName]);
        }

        $adminRole = Role::where('name', 'Admin')->first();

        User::create([
            'name' => 'Administrator',
            'email' => 'admin@bakehub.com',
            'password' => Hash::make('password'),
            'role_id' => $adminRole->id,
        ]);
    }
}
