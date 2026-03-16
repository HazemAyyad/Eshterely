<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ShippingConfigurationSeeder extends Seeder
{
    /**
     * Master shipping configuration seeder.
     *
     * Runs all shipping-related seeders so that:
     * - Calculation settings are populated with sensible defaults.
     * - Carrier zones and rates exist for DHL / UPS / FedEx.
     */
    public function run(): void
    {
        $this->call([
            ShippingSettingsSeeder::class,
            ShippingCarrierZonesSeeder::class,
            ShippingCarrierRatesSeeder::class,
        ]);
    }
}

