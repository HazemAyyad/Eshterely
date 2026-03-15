<?php

namespace App\Services\Shipping;

/**
 * Zone-based rate info for future expansion.
 * Structure supports: carrier, origin country, destination country, zone identifier,
 * base rate, and weight range. Used by ShippingZoneRepositoryInterface implementations.
 */
final class ShippingZoneRateInfo
{
    public function __construct(
        public string $carrier,
        public string $originCountry,
        public string $destinationCountry,
        public string $zoneIdentifier,
        public float $baseRate,
        public float $weightMinKg,
        public float $weightMaxKg,
    ) {}

    public function toArray(): array
    {
        return [
            'carrier' => $this->carrier,
            'origin_country' => $this->originCountry,
            'destination_country' => $this->destinationCountry,
            'zone_identifier' => $this->zoneIdentifier,
            'base_rate' => $this->baseRate,
            'weight_min_kg' => $this->weightMinKg,
            'weight_max_kg' => $this->weightMaxKg,
        ];
    }
}
