<?php

namespace Database\Seeders;

use App\Models\User;
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
        // Seed categories first (required for ads)
        $this->call([
            OlxCategoriesSeeder::class,
        ]);

        // Create test user
        $this->call([
            TestUserSeeder::class,
        ]);

        // Create test ads with field values
        $this->call([
            TestAdSeeder::class,
        ]);

        // Create additional random users
        User::factory(10)->create();
    }
}
