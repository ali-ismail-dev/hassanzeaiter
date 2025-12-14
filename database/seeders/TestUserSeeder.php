<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'ali@gmail.com'],
            [
                'name' => 'Ali Ismail',
                'email' => 'ali@gmail.com',
                'password' => Hash::make('pass1234'),
                'email_verified_at' => now(),
            ]
        );
    }
}
