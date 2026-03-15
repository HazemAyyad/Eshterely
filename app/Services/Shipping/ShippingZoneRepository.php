<?php

namespace App\Services\Shipping;

use App\Services\Shipping\Contracts\ShippingZoneRepositoryInterface;

/**
 * Default zone repository. Returns no zone / zero base price until zone and rate data are configured.
 * Supports future tables: shipping_carrier_zones (carrier, origin, destination, zone),
 * shipping_carrier_rates (carrier, zone, weight_min, weight_max, base_price).
 */
class ShippingZoneRepository implements ShippingZoneRepositoryInterface
{
    public function resolveZone(string $carrier, string $destinationCountry, ?string $originCountry = null): ?string
    {
        // Foundation: no zone data yet; resolvers use min_charge + warehouse fee only
        return null;
    }

    public function getBasePriceForWeight(string $carrier, string $zone, float $chargeableKg): float
    {
        return 0.0;
    }
}
