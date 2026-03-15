<?php

namespace App\Services\Shipping;

/**
 * Context passed to carrier pricing resolvers.
 * All weights are in kg, dimensions in cm (normalized).
 */
final class CarrierPricingContext
{
    public function __construct(
        public string $carrier,
        public string $destinationCountry,
        public bool $warehouseMode,
        public float $actualWeightKg,
        public float $volumetricWeightKg,
        public float $chargeableWeightKg,
        public float $lengthCm,
        public float $widthCm,
        public float $heightCm,
        public int $quantity,
    ) {}
}
