<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AppConfigSeeder::class,
            ShippingConfigurationSeeder::class,
            CountrySeeder::class,
            CitySeeder::class,
            AdminSeeder::class,
        ]);
    }
}
