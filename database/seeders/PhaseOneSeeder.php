
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
        // Membuat role jika belum tersedia
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

        // Mengambil role berdasarkan nama
        $ownerRole = Role::where('name', 'Owner')->first();
        $adminRole = Role::where('name', 'Admin')->first();

        // Membuat akun Owner
        User::firstOrCreate(
            ['email' => 'owner@bakehub.web.id'],
            [
                'name' => 'Owner',
                'password' => Hash::make('password'),
                'role_id' => $ownerRole->id,
            ]
        );

        // Membuat akun Administrator
        User::firstOrCreate(
            ['email' => 'admin@bakehub.web.id'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
            ]
        );
    }
}
