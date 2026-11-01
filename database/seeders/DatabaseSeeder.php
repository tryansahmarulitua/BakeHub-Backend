<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $users = [
            ['name' => 'Owner',        'email' => 'owner@bakehub.web.id',   'role_id' => 1],
            ['name' => 'Admin',        'email' => 'admin@bakehub.web.id',   'role_id' => 2],
            ['name' => 'Staff Gudang', 'email' => 'gudang@bakehub.web.id',  'role_id' => 3],
            ['name' => 'Staff Produksi','email' => 'produksi@bakehub.web.id','role_id' => 4],
            ['name' => 'Kasir',        'email' => 'kasir@bakehub.web.id',   'role_id' => 5],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                array_merge($data, ['bakehub' => Hash::make('bakehub')])
            );
        }
    }
}
