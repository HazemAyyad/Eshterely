<?php

namespace App\Services\Shipping;

use App\Services\Shipping\Contracts\ShippingZoneRepositoryInterface;

/**
 * Default zone repository. Returns no zone / zero base price until zone and rate data are configured.
 * Structure ready for: carrier, origin_country, destination_country, zone_identifier, base_rate, weight range.
 * Future tables: shipping_carrier_zones (carrier, origin, destination, zone),
 * shipping_carrier_rates (carrier, zone, weight_min, weight_max, base_price).
 */
class ShippingZoneRepository implements ShippingZoneRepositoryInterface
{
    public function resolveZone(string $carrier, string $destinationCountry, ?string $originCountry = null): ?string
    {
        return null;
    }

    public function getBasePriceForWeight(string $carrier, string $zone, float $chargeableKg): float
    {
        return 0.0;
    }

    public function getZoneRateInfo(string $carrier, string $destinationCountry, ?string $originCountry, float $chargeableKg): ?ShippingZoneRateInfo
    {
        return null;
    }
}
