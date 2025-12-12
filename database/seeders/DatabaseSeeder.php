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
    // Create users
    User::factory(10)->create();

    // Seed other tables
    $this->call([
        OlxCategoriesSeeder::class,
        AdSeeder::class, 
    ]);
}
}
