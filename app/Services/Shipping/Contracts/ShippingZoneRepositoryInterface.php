<?php

namespace App\Services\Shipping\Contracts;

use App\Services\Shipping\ShippingZoneRateInfo;

/**
 * Resolves shipping zone and base rate by carrier, origin, destination, and weight.
 * Foundation for zone-based pricing; implementations can use shipping_carrier_zones / shipping_carrier_rates or DB.
 * Structure supports: carrier, origin country, destination country, zone identifier, base rate, weight range.
 */
interface ShippingZoneRepositoryInterface
{
    /**
     * Resolve zone code for a carrier and destination (origin may be fixed in config).
     *
     * @return string|null Zone code or null if not found
     */
    public function resolveZone(string $carrier, string $destinationCountry, ?string $originCountry = null): ?string;

    /**
     * Get base price for carrier/zone/weight range. Weight in kg.
     *
     * @return float Base price in configured currency, or 0.0 if no rate found
     */
    public function getBasePriceForWeight(string $carrier, string $zone, float $chargeableKg): float;

    /**
     * Get full zone rate info for a destination and weight (for future expansion).
     * Returns null when no zone/rate is configured.
     */
    public function getZoneRateInfo(string $carrier, string $destinationCountry, ?string $originCountry, float $chargeableKg): ?ShippingZoneRateInfo;
}
