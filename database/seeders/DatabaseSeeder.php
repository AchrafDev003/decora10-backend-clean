<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    // en database/seeders/DatabaseSeeder.php
    public function run(): void
    {
        $this->call(ReviewSeeder::class);
        $this->call(OrderSeeder::class);




    }

}
