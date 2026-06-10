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
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'password' => 'password',
            'role' => Role::Manager,
        ]);

        User::create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => 'password',
            'role' => Role::Staff,
        ]);
    }
}
