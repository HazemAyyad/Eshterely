<?php

namespace Database\Seeders;

use App\Models\ShippingCarrierRate;
use Illuminate\Database\Seeder;

class ShippingCarrierRatesSeeder extends Seeder
{
    /**
     * Seed weight-based pricing rows for each carrier / zone combination.
     *
     * Values are intentionally simple, realistic starter numbers so that
     * admins can immediately see and edit them from the panel. They are NOT
     * intended to represent real carrier tariff books.
     */
    public function run(): void
    {
        // Weight bands in kg used for all carriers / zones.
        $bands = [
            ['min' => 0.00, 'max' => 0.50],
            ['min' => 0.50, 'max' => 1.00],
            ['min' => 1.00, 'max' => 3.00],
            ['min' => 3.00, 'max' => 5.00],
            ['min' => 5.00, 'max' => 10.00],
            ['min' => 10.00, 'max' => 20.00],
            ['min' => 20.00, 'max' => null], // open-ended
        ];

        /**
         * Base matrix by carrier / zone / pricing mode.
         *
         * Numbers are approximate starter rates in the default currency.
         */
        $rateMatrix = [
            // DHL – generally premium but strong in Gulf / international.
            'dhl' => [
                'ME_LEVANT' => [
                    'direct' =>  [
                        18, 24, 32, 40, 55, 80, 120,
                    ],
                    'warehouse' => [
                        16, 22, 30, 38, 52, 76, 110,
                    ],
                ],
                'ME_GULF' => [
                    'direct' =>  [
                        15, 20, 28, 36, 50, 70, 105,
                    ],
                    'warehouse' => [
                        13, 18, 26, 34, 47, 66, 98,
                    ],
                ],
                'EU_STANDARD' => [
                    'direct' =>  [
                        20, 28, 38, 48, 65, 90, 135,
                    ],
                    'warehouse' => [
                        18, 26, 36, 46, 62, 86, 128,
                    ],
                ],
                'NA_US_CA' => [
                    'direct' =>  [
                        22, 30, 40, 52, 70, 95, 145,
                    ],
                    'warehouse' => [
                        20, 28, 38, 50, 67, 90, 138,
                    ],
                ],
            ],

            // UPS – slightly different profile, still realistic-ish.
            'ups' => [
                'ME_LEVANT' => [
                    'direct' =>  [
                        17, 22, 30, 38, 52, 76, 115,
                    ],
                    'warehouse' => [
                        15, 20, 28, 36, 49, 72, 108,
                    ],
                ],
                'ME_GULF' => [
                    'direct' =>  [
                        14, 19, 26, 34, 48, 68, 100,
                    ],
                    'warehouse' => [
                        12, 17, 24, 32, 45, 63, 93,
                    ],
                ],
                'EU_STANDARD' => [
                    'direct' =>  [
                        19, 26, 36, 46, 62, 86, 130,
                    ],
                    'warehouse' => [
                        17, 24, 34, 44, 59, 82, 123,
                    ],
                ],
                'NA_US_CA' => [
                    'direct' =>  [
                        21, 28, 38, 50, 68, 92, 140,
                    ],
                    'warehouse' => [
                        19, 26, 36, 48, 65, 88, 133,
                    ],
                ],
            ],

            // FedEx – similar tiers but slightly tweaked.
            'fedex' => [
                'ME_LEVANT' => [
                    'direct' =>  [
                        16, 21, 29, 37, 50, 74, 112,
                    ],
                    'warehouse' => [
                        14, 19, 27, 35, 47, 70, 105,
                    ],
                ],
                'ME_GULF' => [
                    'direct' =>  [
                        13, 18, 25, 33, 46, 66, 98,
                    ],
                    'warehouse' => [
                        11, 16, 23, 31, 43, 61, 91,
                    ],
                ],
                'EU_STANDARD' => [
                    'direct' =>  [
                        18, 25, 35, 45, 60, 84, 128,
                    ],
                    'warehouse' => [
                        16, 23, 33, 43, 57, 80, 121,
                    ],
                ],
                'NA_US_CA' => [
                    'direct' =>  [
                        20, 27, 37, 49, 66, 90, 138,
                    ],
                    'warehouse' => [
                        18, 25, 35, 47, 63, 86, 131,
                    ],
                ],
            ],
        ];

        foreach ($rateMatrix as $carrier => $zones) {
            foreach ($zones as $zoneCode => $modes) {
                foreach ($modes as $pricingMode => $rates) {
                    foreach ($bands as $index => $band) {
                        $baseRate = $rates[$index] ?? null;
                        if ($baseRate === null) {
                            continue;
                        }

                        ShippingCarrierRate::query()->updateOrCreate(
                            [
                                'carrier' => $carrier,
                                'zone_code' => $zoneCode,
                                'pricing_mode' => $pricingMode,
                                'weight_min_kg' => $band['min'],
                                'weight_max_kg' => $band['max'],
                            ],
                            [
                                'base_rate' => $baseRate,
                                'active' => true,
                                'notes' => 'Starter seed rate – adjust from admin before production.',
                            ]
                        );
                    }
                }
            }
        }
    }
}

