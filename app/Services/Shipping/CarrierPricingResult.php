<?php

namespace App\Services\Shipping;

/**
 * Result from a single carrier pricing resolver.
 */
final class CarrierPricingResult
{
    public function __construct(
        public string $carrier,
        public string $currency,
        public float $amount,
        public string $pricingMode,
        public array $breakdown = [],
        public array $notes = [],
    ) {}
}
