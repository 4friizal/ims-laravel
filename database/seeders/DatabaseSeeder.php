<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => Role::Admin,
        ]);

        User::create([
            'name' => 'Operator User',
            'email' => 'operator@example.com',
            'password' => 'password',
            'role' => Role::Operator,
        ]);

        $this->call([
            InventorySeeder::class,
        ]);
    }
}
