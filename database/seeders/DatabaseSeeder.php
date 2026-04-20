<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['name' => 'admin'],
            [
                'email' => 'admin@local.user',
                'password' => Hash::make('admin12345'),
                'role' => User::ROLE_ADMIN,
            ]
        );

        User::updateOrCreate(
            ['name' => 'user'],
            [
                'email' => 'user@local.user',
                'password' => Hash::make('user12345'),
                'role' => User::ROLE_USER,
            ]
        );
    }
}
