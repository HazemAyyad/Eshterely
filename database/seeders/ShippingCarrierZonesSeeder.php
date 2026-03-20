<?php

namespace Database\Seeders;

use App\Models\ShippingCarrierZone;
use Illuminate\Database\Seeder;

class ShippingCarrierZonesSeeder extends Seeder
{
    /**
     * Seed a practical starter set of carrier zones.
     *
     * Zones are intentionally coarse regional groupings so operations can
     * review and adjust them from the admin immediately after seeding.
     */
    public function run(): void
    {
        // All carrier codes are stored lowercase in the DB / UI.
        $zoneDefinitions = [
            // Palestine / Jordan / Gulf / Middle East
            [
                'carrier' => 'dhl',
                'zone_code' => 'ME_LEVANT',
                'countries' => ['PS', 'JO', 'LB', 'SY', 'IQ'],
                'notes' => 'Starter DHL Levant zone (Palestine, Jordan, Lebanon, Syria, Iraq)',
            ],
            [
                'carrier' => 'dhl',
                'zone_code' => 'ME_GULF',
                'countries' => ['SA', 'AE', 'KW', 'QA', 'BH', 'OM'],
                'notes' => 'Starter DHL Gulf / GCC zone',
            ],
            [
                'carrier' => 'ups',
                'zone_code' => 'ME_LEVANT',
                'countries' => ['PS', 'JO', 'LB', 'SY', 'IQ'],
                'notes' => 'Starter UPS Levant zone (Palestine, Jordan, Lebanon, Syria, Iraq)',
            ],
            [
                'carrier' => 'ups',
                'zone_code' => 'ME_GULF',
                'countries' => ['SA', 'AE', 'KW', 'QA', 'BH', 'OM'],
                'notes' => 'Starter UPS Gulf / GCC zone',
            ],
            [
                'carrier' => 'fedex',
                'zone_code' => 'ME_LEVANT',
                'countries' => ['PS', 'JO', 'LB', 'SY', 'IQ'],
                'notes' => 'Starter FedEx Levant zone (Palestine, Jordan, Lebanon, Syria, Iraq)',
            ],
            [
                'carrier' => 'fedex',
                'zone_code' => 'ME_GULF',
                'countries' => ['SA', 'AE', 'KW', 'QA', 'BH', 'OM'],
                'notes' => 'Starter FedEx Gulf / GCC zone',
            ],

            // Europe
            [
                'carrier' => 'dhl',
                'zone_code' => 'EU_STANDARD',
                'countries' => ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'SE', 'NO', 'DK'],
                'notes' => 'Starter DHL Europe standard zone',
            ],
            [
                'carrier' => 'ups',
                'zone_code' => 'EU_STANDARD',
                'countries' => ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'SE', 'NO', 'DK'],
                'notes' => 'Starter UPS Europe standard zone',
            ],
            [
                'carrier' => 'fedex',
                'zone_code' => 'EU_STANDARD',
                'countries' => ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'SE', 'NO', 'DK'],
                'notes' => 'Starter FedEx Europe standard zone',
            ],

            // USA / North America
            [
                'carrier' => 'dhl',
                'zone_code' => 'NA_US_CA',
                'countries' => ['US', 'CA'],
                'notes' => 'Starter DHL North America zone (US / CA)',
            ],
            [
                'carrier' => 'ups',
                'zone_code' => 'NA_US_CA',
                'countries' => ['US', 'CA'],
                'notes' => 'Starter UPS North America zone (US / CA)',
            ],
            [
                'carrier' => 'fedex',
                'zone_code' => 'NA_US_CA',
                'countries' => ['US', 'CA'],
                'notes' => 'Starter FedEx North America zone (US / CA)',
            ],
        ];

        foreach ($zoneDefinitions as $definition) {
            foreach ($definition['countries'] as $country) {
                ShippingCarrierZone::query()->updateOrCreate(
                    [
                        'carrier' => strtolower($definition['carrier']),
                        'destination_country' => strtoupper($country),
                        'zone_code' => $definition['zone_code'],
                    ],
                    [
                        'origin_country' => null,
                        'active' => true,
                        'notes' => $definition['notes'],
                    ]
                );
            }
        }
    }
}

