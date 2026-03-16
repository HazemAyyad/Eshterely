<?php

namespace App\Services\Shipping;

use App\Models\ShippingCarrierRate;
use App\Models\ShippingCarrierZone;
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
            $carrier = strtolower($carrier);
            $destinationCountry = strtoupper($destinationCountry);
            $originCountry = $originCountry ? strtoupper($originCountry) : null;

            $query = ShippingCarrierZone::query()
                ->where('carrier', $carrier)
                ->where('destination_country', $destinationCountry)
                ->where('active', true);

            if ($originCountry !== null) {
                $zone = (clone $query)->where('origin_country', $originCountry)->first();
                if ($zone) {
                    return $zone->zone_code;
                }
            }

            $zone = $query->whereNull('origin_country')->first();

            return $zone?->zone_code;
    }

    public function getBasePriceForWeight(string $carrier, string $zone, float $chargeableKg): float
    {
            $carrier = strtolower($carrier);

            $rate = ShippingCarrierRate::query()
                ->where('carrier', $carrier)
                ->where('zone_code', $zone)
                ->where('active', true)
                ->where('weight_min_kg', '<=', $chargeableKg)
                ->where(function ($q) use ($chargeableKg) {
                    $q->whereNull('weight_max_kg')
                        ->orWhere('weight_max_kg', '>=', $chargeableKg);
                })
                ->orderBy('weight_min_kg')
                ->first();

            return $rate?->base_rate ?? 0.0;
    }

    public function getZoneRateInfo(string $carrier, string $destinationCountry, ?string $originCountry, float $chargeableKg): ?ShippingZoneRateInfo
    {
            $zone = $this->resolveZone($carrier, $destinationCountry, $originCountry);
            if (! $zone) {
                return null;
            }

            $carrier = strtolower($carrier);
            $destinationCountry = strtoupper($destinationCountry);
            $originCountry = $originCountry ? strtoupper($originCountry) : '';

            $rate = ShippingCarrierRate::query()
                ->where('carrier', $carrier)
                ->where('zone_code', $zone)
                ->where('active', true)
                ->where('weight_min_kg', '<=', $chargeableKg)
                ->where(function ($q) use ($chargeableKg) {
                    $q->whereNull('weight_max_kg')
                        ->orWhere('weight_max_kg', '>=', $chargeableKg);
                })
                ->orderBy('weight_min_kg')
                ->first();

            if (! $rate) {
                return null;
            }

            return new ShippingZoneRateInfo(
                carrier: $carrier,
                originCountry: $originCountry,
                destinationCountry: $destinationCountry,
                zoneIdentifier: $zone,
                baseRate: (float) $rate->base_rate,
                weightMinKg: (float) $rate->weight_min_kg,
                weightMaxKg: (float) ($rate->weight_max_kg ?? $chargeableKg),
            );
    }
}
