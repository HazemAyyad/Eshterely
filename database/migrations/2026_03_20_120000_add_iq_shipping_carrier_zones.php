<?php

use App\Models\ShippingCarrierZone;
use Illuminate\Database\Migrations\Migration;

/**
 * Iraq (IQ) was missing from starter zones — quotes resolved to zero base rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['dhl', 'ups', 'fedex'] as $carrier) {
            ShippingCarrierZone::query()->updateOrCreate(
                [
                    'carrier' => $carrier,
                    'destination_country' => 'IQ',
                    'zone_code' => 'ME_LEVANT',
                ],
                [
                    'origin_country' => null,
                    'active' => true,
                    'notes' => 'Iraq — starter zone (same weight bands as ME_LEVANT)',
                ]
            );
        }
    }

    public function down(): void
    {
        ShippingCarrierZone::query()
            ->where('destination_country', 'IQ')
            ->where('zone_code', 'ME_LEVANT')
            ->delete();
    }
};
